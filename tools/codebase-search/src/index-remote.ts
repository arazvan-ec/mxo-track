#!/usr/bin/env node

import { createServer } from './server.js';
import { ParserRegistry } from './parser/parser-registry.js';
import { OpenAIEmbedder } from './embeddings/openai-embedder.js';
import { JsonStorage } from './storage/json-storage.js';
import { SearchEngine } from './search/search-engine.js';
import { Indexer } from './indexer/indexer.js';
import { GitManager } from './git/git-manager.js';
import { createSseApp } from './transport/sse-server.js';

const DATA_DIR = process.env.DATA_DIR ?? '/data';
const GITHUB_REPO_URL = process.env.GITHUB_REPO_URL?.trim();
const OPENAI_API_KEY = process.env.OPENAI_API_KEY;
const PORT = parseInt(process.env.PORT ?? '3000', 10);

if (!GITHUB_REPO_URL) {
  console.error('[remote] GITHUB_REPO_URL is required');
  process.exit(1);
}

if (!OPENAI_API_KEY) {
  console.error('[remote] WARNING: OPENAI_API_KEY not set. Search and indexing will fail.');
}

async function main() {
  // Clone or pull the repository
  const gitManager = new GitManager(GITHUB_REPO_URL!, DATA_DIR);
  const projectRoot = await gitManager.ensureRepo();
  console.error(`[remote] Repo ready at ${projectRoot}`);

  // Initialize components
  const parserRegistry = new ParserRegistry();
  const embedder = new OpenAIEmbedder(OPENAI_API_KEY ?? '');
  const storage = new JsonStorage(DATA_DIR);
  const searchEngine = new SearchEngine();
  const indexer = new Indexer(parserRegistry, embedder, storage, searchEngine, projectRoot, DATA_DIR);

  // Auto-index if no existing index
  const existing = await storage.load();
  if (!existing) {
    console.error('[remote] No index found, running full reindex...');
    await indexer.indexFull();
    console.error('[remote] Initial indexing complete');
  } else {
    console.error(`[remote] Existing index loaded: ${existing.metadata.totalChunks} chunks`);
  }

  // Create MCP server and HTTP app
  const mcpServer = createServer(indexer, searchEngine, storage, embedder);
  const app = createSseApp(mcpServer, gitManager, indexer);

  app.listen(PORT, () => {
    console.error(`[remote] MCP server listening on port ${PORT}`);
    console.error(`[remote] SSE endpoint: http://0.0.0.0:${PORT}/sse`);
    console.error(`[remote] Health check: http://0.0.0.0:${PORT}/health`);
  });
}

main().catch((error) => {
  console.error('[remote] Fatal error:', error);
  process.exit(1);
});
