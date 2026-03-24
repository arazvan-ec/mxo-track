#!/usr/bin/env node

import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import { join } from 'node:path';
import { createServer } from './server.js';
import { ParserRegistry } from './parser/parser-registry.js';
import { OpenAIEmbedder } from './embeddings/openai-embedder.js';
import { JsonStorage } from './storage/json-storage.js';
import { SearchEngine } from './search/search-engine.js';
import { Indexer } from './indexer/indexer.js';

const PROJECT_ROOT = process.env.PROJECT_ROOT ?? join(import.meta.dirname, '..', '..');
const DATA_DIR = join(import.meta.dirname, '..', 'data');
const OPENAI_API_KEY = process.env.OPENAI_API_KEY;

if (!OPENAI_API_KEY) {
  console.error('[codebase-search] WARNING: OPENAI_API_KEY not set. Search and indexing will fail.');
}

// Initialize components
const parserRegistry = new ParserRegistry();
const embedder = new OpenAIEmbedder(OPENAI_API_KEY ?? '');
const storage = new JsonStorage(DATA_DIR);
const searchEngine = new SearchEngine();
const indexer = new Indexer(parserRegistry, embedder, storage, searchEngine, PROJECT_ROOT);

// Create and start MCP server
const server = createServer(indexer, searchEngine, storage, embedder);
const transport = new StdioServerTransport();

async function main() {
  await server.connect(transport);
  console.error('[codebase-search] MCP server started on stdio');
}

main().catch((error) => {
  console.error('[codebase-search] Fatal error:', error);
  process.exit(1);
});
