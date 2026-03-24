import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { z } from 'zod';
import type { Indexer } from './indexer/indexer.js';
import type { SearchEngine } from './search/search-engine.js';
import type { StorageBackend } from './storage/storage-interface.js';
import type { Embedder } from './embeddings/embedder-interface.js';
import type { StoredIndex } from './storage/types.js';

export function createServer(
  indexer: Indexer,
  searchEngine: SearchEngine,
  storage: StorageBackend,
  embedder: Embedder,
): McpServer {
  const server = new McpServer({
    name: 'codebase-search',
    version: '0.1.0',
  });

  let cachedIndex: StoredIndex | null = null;

  async function ensureIndex(): Promise<StoredIndex | null> {
    if (!cachedIndex) {
      cachedIndex = await storage.load();
      if (cachedIndex) {
        searchEngine.buildIndex(cachedIndex.chunks, embedder.dimensions);
      }
    }
    return cachedIndex;
  }

  server.tool(
    'codebase_search',
    'Semantic search across the codebase. Returns relevant code chunks ranked by similarity.',
    {
      query: z.string().describe('Natural language query'),
      limit: z.number().optional().default(10).describe('Max results (default 10)'),
      language: z
        .enum(['php', 'twig', 'yaml', 'markdown', 'sql'])
        .optional()
        .describe('Filter by language'),
      type: z
        .enum(['class', 'method', 'function', 'block', 'section', 'config', 'migration'])
        .optional()
        .describe('Filter by chunk type'),
      minScore: z.number().optional().default(0.5).describe('Minimum similarity threshold'),
    },
    async (args) => {
      const index = await ensureIndex();

      if (!index) {
        return {
          content: [
            {
              type: 'text' as const,
              text: 'No index found. Run codebase_index with mode "full" first.',
            },
          ],
        };
      }

      // Embed the query
      const [queryEmbedding] = await embedder.embed([args.query]);

      const results = searchEngine.search(queryEmbedding, index.chunks, {
        limit: args.limit ?? 10,
        language: args.language,
        type: args.type,
        minScore: args.minScore ?? 0.5,
      });

      if (results.length === 0) {
        return {
          content: [
            {
              type: 'text' as const,
              text: 'No results found matching your query.',
            },
          ],
        };
      }

      const formatted = results
        .map((r, i) => {
          const parent = r.parentName ? ` (in ${r.parentName})` : '';
          return [
            `### ${i + 1}. ${r.name}${parent}`,
            `**File:** ${r.filePath}:${r.startLine}-${r.endLine}`,
            `**Type:** ${r.type} | **Language:** ${r.language} | **Score:** ${r.score.toFixed(3)}`,
            '```',
            r.snippet,
            '```',
          ].join('\n');
        })
        .join('\n\n');

      return {
        content: [{ type: 'text' as const, text: formatted }],
      };
    },
  );

  server.tool(
    'codebase_index',
    'Re-index the codebase. Use "full" for complete reindex or "incremental" for changes only.',
    {
      mode: z.enum(['full', 'incremental']).describe('Indexing mode'),
    },
    async (args) => {
      try {
        const metadata =
          args.mode === 'full'
            ? await indexer.indexFull()
            : await indexer.indexIncremental();

        // Invalidate cache so next search loads fresh index
        cachedIndex = null;

        return {
          content: [
            {
              type: 'text' as const,
              text: [
                `Indexing complete (${args.mode} mode).`,
                `Total chunks: ${metadata.totalChunks}`,
                `Commit: ${metadata.lastIndexedCommit.slice(0, 8)}`,
                `Files: PHP=${metadata.fileCount.php}, Twig=${metadata.fileCount.twig}, YAML=${metadata.fileCount.yaml}, MD=${metadata.fileCount.markdown}, SQL=${metadata.fileCount.sql}`,
              ].join('\n'),
            },
          ],
        };
      } catch (error) {
        return {
          content: [
            {
              type: 'text' as const,
              text: `Indexing failed: ${(error as Error).message}`,
            },
          ],
          isError: true,
        };
      }
    },
  );

  server.tool(
    'codebase_index_status',
    'Show current index status: chunk count, last indexed, coverage.',
    {},
    async () => {
      const metadata = await storage.getMetadata();

      if (!metadata) {
        return {
          content: [
            {
              type: 'text' as const,
              text: 'No index exists yet. Run codebase_index with mode "full" to create one.',
            },
          ],
        };
      }

      return {
        content: [
          {
            type: 'text' as const,
            text: [
              `**Index Status**`,
              `Last indexed: ${metadata.lastIndexedAt}`,
              `Commit: ${metadata.lastIndexedCommit.slice(0, 8)}`,
              `Total chunks: ${metadata.totalChunks}`,
              `Model: ${metadata.embeddingModel} (${metadata.embeddingDimensions}d)`,
              `Files: PHP=${metadata.fileCount.php}, Twig=${metadata.fileCount.twig}, YAML=${metadata.fileCount.yaml}, MD=${metadata.fileCount.markdown}, SQL=${metadata.fileCount.sql}`,
            ].join('\n'),
          },
        ],
      };
    },
  );

  return server;
}
