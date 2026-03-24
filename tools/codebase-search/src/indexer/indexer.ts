import { readFileSync } from 'node:fs';
import { join, relative } from 'node:path';
import { glob } from 'glob';
import type { ParserRegistry } from '../parser/parser-registry.js';
import type { Embedder } from '../embeddings/embedder-interface.js';
import type { StorageBackend } from '../storage/storage-interface.js';
import { SearchEngine } from '../search/search-engine.js';
import type { CodeChunk, IndexMetadata, Language } from './types.js';
import type { StoredChunk, StoredIndex } from '../storage/types.js';
import { getCurrentCommit, getChangedFiles } from './incremental.js';
import { SqlParser } from '../parser/sql-parser.js';

const SCOPE_PATTERNS: Record<string, string[]> = {
  php: ['backend/src/**/*.php', 'backend/tests/**/*.php'],
  twig: ['backend/templates/**/*.twig', 'backend/templates/**/*.html.twig'],
  yaml: ['backend/config/**/*.yaml', 'backend/config/**/*.yml'],
  markdown: ['docs/**/*.md', '*.md', 'backend/*.md'],
  sql: ['backend/migrations/**/*.php'],
};

const EMBEDDING_BATCH_SIZE = parseInt(process.env.EMBEDDING_BATCH_SIZE || '20', 10);

export class Indexer {
  private sqlParser = new SqlParser();

  constructor(
    private parserRegistry: ParserRegistry,
    private embedder: Embedder,
    private storage: StorageBackend,
    private searchEngine: SearchEngine,
    private projectRoot: string,
    private dataDir?: string,
  ) {}

  async indexFull(): Promise<IndexMetadata> {
    const files = await this.scanFiles();
    const chunks = this.parseFiles(files);
    const embeddings = await this.embedChunks(chunks);

    const storedChunks: StoredChunk[] = chunks.map((chunk, i) => ({
      ...chunk,
      embedding: embeddings[i],
    }));

    const metadata = this.buildMetadata(storedChunks);
    const index: StoredIndex = { metadata, chunks: storedChunks };

    await this.storage.save(index);

    // Build and save HNSW index
    this.searchEngine.buildIndex(storedChunks, this.embedder.dimensions);
    const hnswPath = join(this.getDataDir(), 'hnsw.dat');
    this.searchEngine.saveIndex(hnswPath);

    return metadata;
  }

  async indexIncremental(): Promise<IndexMetadata> {
    const existingIndex = await this.storage.load();

    if (!existingIndex) {
      return this.indexFull();
    }

    const lastCommit = existingIndex.metadata.lastIndexedCommit;
    const changes = getChangedFiles(lastCommit, this.projectRoot);

    const hasChanges =
      changes.added.length > 0 ||
      changes.modified.length > 0 ||
      changes.deleted.length > 0;

    if (!hasChanges) {
      return existingIndex.metadata;
    }

    // Filter to only files we care about (matching scope patterns)
    const allScopeFiles = new Set(await this.scanFiles());
    const changedFiles = [...changes.added, ...changes.modified].filter(f =>
      allScopeFiles.has(f),
    );
    const deletedFiles = new Set(changes.deleted);

    // Remove deleted chunks
    let chunks = existingIndex.chunks.filter(c => !deletedFiles.has(c.filePath));

    // Remove chunks from modified files (will re-parse)
    const modifiedSet = new Set(changedFiles);
    chunks = chunks.filter(c => !modifiedSet.has(c.filePath));

    // Parse and embed new/modified files
    if (changedFiles.length > 0) {
      const newChunks = this.parseFiles(changedFiles);
      const newEmbeddings = await this.embedChunks(newChunks);

      const newStoredChunks: StoredChunk[] = newChunks.map((chunk, i) => ({
        ...chunk,
        embedding: newEmbeddings[i],
      }));

      chunks.push(...newStoredChunks);
    }

    const metadata = this.buildMetadata(chunks);
    const index: StoredIndex = { metadata, chunks };

    await this.storage.save(index);

    // Rebuild HNSW
    this.searchEngine.buildIndex(chunks, this.embedder.dimensions);
    const hnswPath = join(this.getDataDir(), 'hnsw.dat');
    this.searchEngine.saveIndex(hnswPath);

    return metadata;
  }

  private async scanFiles(): Promise<string[]> {
    const allFiles: string[] = [];
    const allPatterns = Object.values(SCOPE_PATTERNS).flat();

    for (const pattern of allPatterns) {
      const matches = await glob(pattern, { cwd: this.projectRoot });
      allFiles.push(...matches);
    }

    // Deduplicate
    return [...new Set(allFiles)];
  }

  private parseFiles(files: string[]): CodeChunk[] {
    const chunks: CodeChunk[] = [];

    for (const file of files) {
      try {
        const fullPath = join(this.projectRoot, file);
        const content = readFileSync(fullPath, 'utf-8');

        // Check if it's a migration file (parse with SQL parser too)
        if (file.match(/migrations\//)) {
          const sqlChunks = this.sqlParser.parse(file, content);
          chunks.push(...sqlChunks);
        }

        const parser = this.parserRegistry.getParser(file);
        if (parser) {
          const parsed = parser.parse(file, content);
          chunks.push(...parsed);
        }
      } catch (error) {
        // Skip files that can't be read/parsed
        console.error(`[indexer] Skipping ${file}: ${(error as Error).message}`);
      }
    }

    return chunks;
  }

  private async embedChunks(chunks: CodeChunk[]): Promise<number[][]> {
    if (chunks.length === 0) return [];

    const texts = chunks.map(c => c.content);
    const allEmbeddings: number[][] = [];

    for (let i = 0; i < texts.length; i += EMBEDDING_BATCH_SIZE) {
      const batch = texts.slice(i, i + EMBEDDING_BATCH_SIZE);
      const embeddings = await this.embedder.embed(batch);
      allEmbeddings.push(...embeddings);
    }

    return allEmbeddings;
  }

  private buildMetadata(chunks: StoredChunk[]): IndexMetadata {
    const fileCount: Record<Language, number> = {
      php: 0,
      twig: 0,
      yaml: 0,
      markdown: 0,
      sql: 0,
    };

    const seenFiles = new Map<Language, Set<string>>();
    for (const lang of Object.keys(fileCount) as Language[]) {
      seenFiles.set(lang, new Set());
    }

    for (const chunk of chunks) {
      seenFiles.get(chunk.language)?.add(chunk.filePath);
    }

    for (const [lang, files] of seenFiles) {
      fileCount[lang] = files.size;
    }

    return {
      lastIndexedCommit: getCurrentCommit(this.projectRoot),
      lastIndexedAt: new Date().toISOString(),
      totalChunks: chunks.length,
      embeddingModel: this.embedder.modelId,
      embeddingDimensions: this.embedder.dimensions,
      fileCount,
    };
  }

  private getDataDir(): string {
    return this.dataDir ?? join(this.projectRoot, 'tools', 'codebase-search', 'data');
  }
}
