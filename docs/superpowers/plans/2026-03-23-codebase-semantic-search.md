# Implementation Plan — Codebase Semantic Search MCP Server

**Date:** 2026-03-23
**Spec:** `docs/superpowers/specs/2026-03-23-codebase-semantic-search-design.md`
**Branch:** `claude/add-workflow-documentation-Ygde6`
**Estimated complexity:** L (13 tasks)

---

## Goal

Build a standalone MCP server in TypeScript at `/tools/codebase-search/` that provides semantic code search to Claude Code via tree-sitter AST parsing, OpenAI embeddings, and HNSW vector search.

## Architecture

- **Language:** TypeScript (Node.js)
- **AST:** tree-sitter (PHP, Twig, YAML, Markdown)
- **Embeddings:** OpenAI `text-embedding-3-small` (1536 dims)
- **Search:** hnswlib-node (HNSW algorithm)
- **Storage:** JSON flat file (behind interface)
- **Protocol:** MCP via stdio transport

## File Structure

```
tools/codebase-search/
├── package.json
├── tsconfig.json
├── .env.example
├── .gitignore
├── src/
│   ├── index.ts
│   ├── server.ts
│   ├── indexer/
│   │   ├── indexer.ts
│   │   ├── incremental.ts
│   │   └── types.ts
│   ├── parser/
│   │   ├── parser-interface.ts
│   │   ├── php-parser.ts
│   │   ├── twig-parser.ts
│   │   ├── yaml-parser.ts
│   │   ├── markdown-parser.ts
│   │   ├── sql-parser.ts
│   │   └── parser-registry.ts
│   ├── embeddings/
│   │   ├── embedder-interface.ts
│   │   └── openai-embedder.ts
│   ├── search/
│   │   ├── search-engine.ts
│   │   └── types.ts
│   └── storage/
│       ├── storage-interface.ts
│       ├── json-storage.ts
│       └── types.ts
├── data/                  # gitignored
└── tests/
    ├── parser.test.ts
    ├── indexer.test.ts
    ├── search.test.ts
    └── storage.test.ts
```

---

## Tasks

### Task 1: Project scaffold
- [ ] Create `tools/codebase-search/` directory
- [ ] Create `package.json` with all dependencies
- [ ] Create `tsconfig.json` (ES2022, ESNext modules, strict)
- [ ] Create `.env.example` with `OPENAI_API_KEY=sk-...`
- [ ] Create `.gitignore` (node_modules, dist, data, .env)
- [ ] Run `npm install`
- [ ] Verify: `npx tsc --noEmit` exits clean
- [ ] **Commit:** `chore: scaffold codebase-search MCP server project`

### Task 2: Core types and interfaces
- [ ] Create `src/indexer/types.ts` — CodeChunk, ChunkType, Language, IndexMetadata
- [ ] Create `src/search/types.ts` — SearchResult, SearchOptions
- [ ] Create `src/storage/types.ts` — StoredChunk (CodeChunk + embedding), StoredIndex
- [ ] Create `src/parser/parser-interface.ts` — FileParser interface
- [ ] Create `src/embeddings/embedder-interface.ts` — Embedder interface
- [ ] Create `src/storage/storage-interface.ts` — StorageBackend interface
- [ ] Verify: `npx tsc --noEmit`
- [ ] **Commit:** `feat: add core types and interfaces for codebase search`

### Task 3: JSON storage implementation (TDD)
- [ ] Write test `tests/storage.test.ts` — save/load roundtrip, null on missing, HNSW binary roundtrip, getMetadata
- [ ] Run tests → verify they fail (RED)
- [ ] Implement `src/storage/json-storage.ts` — read/write JSON + binary files to data dir
- [ ] Run tests → verify they pass (GREEN)
- [ ] **Commit:** `feat: implement JSON file storage backend`

### Task 4: Markdown parser (TDD)
- [ ] Write test in `tests/parser.test.ts` — splits by ## headings, correct line numbers, no-heading fallback
- [ ] Run tests → verify they fail (RED)
- [ ] Implement `src/parser/markdown-parser.ts` — heading-based chunking, no tree-sitter needed
- [ ] Run tests → verify they pass (GREEN)
- [ ] **Commit:** `feat: implement markdown parser for codebase search`

### Task 5: PHP parser with tree-sitter (TDD)
- [ ] Write tests — class extraction, method extraction with parentName, standalone functions, namespace in metadata
- [ ] Run tests → verify they fail (RED)
- [ ] Implement `src/parser/php-parser.ts` — tree-sitter PHP grammar, walk AST for class/method/function nodes
- [ ] Run tests → verify they pass (GREEN)
- [ ] **Commit:** `feat: implement PHP tree-sitter parser`

### Task 6: YAML, Twig, and SQL parsers (TDD)
- [ ] Write tests for each parser
- [ ] Run tests → verify they fail (RED)
- [ ] Implement `src/parser/yaml-parser.ts` — top-level key chunking
- [ ] Implement `src/parser/twig-parser.ts` — block/macro detection (regex-based)
- [ ] Implement `src/parser/sql-parser.ts` — extract addSql() from Doctrine migrations
- [ ] Run tests → verify they pass (GREEN)
- [ ] **Commit:** `feat: implement YAML, Twig, and SQL parsers`

### Task 7: Parser registry (TDD)
- [ ] Write test — correct parser for each extension, null for unknown
- [ ] Run tests → verify they fail (RED)
- [ ] Implement `src/parser/parser-registry.ts` — extension → parser mapping
- [ ] Run tests → verify they pass (GREEN)
- [ ] **Commit:** `feat: implement parser registry`

### Task 8: OpenAI embedder (TDD)
- [ ] Write test `tests/indexer.test.ts` — mock OpenAI API, correct dimensions, batch embedding, error handling
- [ ] Run tests → verify they fail (RED)
- [ ] Implement `src/embeddings/openai-embedder.ts` — batch API calls, retry logic
- [ ] Run tests → verify they pass (GREEN)
- [ ] **Commit:** `feat: implement OpenAI embedder`

### Task 9: HNSW search engine (TDD)
- [ ] Write test `tests/search.test.ts` — build index, ranked results, filters (language, type, minScore), empty index
- [ ] Run tests → verify they fail (RED)
- [ ] Implement `src/search/search-engine.ts` — build HNSW, search with filters, save/load index
- [ ] Run tests → verify they pass (GREEN)
- [ ] **Commit:** `feat: implement HNSW search engine`

### Task 10: Incremental indexer (TDD)
- [ ] Write tests in `tests/indexer.test.ts` — git diff detection, full vs incremental mode, orchestration pipeline
- [ ] Run tests → verify they fail (RED)
- [ ] Implement `src/indexer/incremental.ts` — git diff based change detection
- [ ] Implement `src/indexer/indexer.ts` — orchestrate parse → embed → store pipeline
- [ ] Run tests → verify they pass (GREEN)
- [ ] **Commit:** `feat: implement indexer with incremental support`

### Task 11: MCP server
- [ ] Implement `src/server.ts` — register 3 tools (codebase_search, codebase_index, codebase_index_status)
- [ ] Implement `src/index.ts` — entry point, stdio transport, component initialization
- [ ] Verify: `npm run build` succeeds
- [ ] **Commit:** `feat: implement MCP server with search and index tools`

### Task 12: Claude Code integration
- [ ] Add `mcpServers.codebase-search` to `.claude/settings.json`
- [ ] Add `tools/codebase-search/data/` to root `.gitignore`
- [ ] **Commit:** `feat: integrate codebase-search MCP server with Claude Code`

### Task 13: End-to-end verification
- [ ] Build: `npm run build`
- [ ] Run all tests: `npm test`
- [ ] Manual test: start MCP server, run full index, execute search queries
- [ ] Verify search returns relevant results
- [ ] **Commit:** `test: verify end-to-end codebase search functionality`

---

## Risk Mitigation

| Risk | Mitigation |
|------|-----------|
| tree-sitter-php native build fails | Fallback: regex-based PHP parser |
| hnswlib-node native build fails | Fallback: brute-force cosine similarity |
| tree-sitter-twig not available | Regex-based Twig parser (already designed as regex) |
| OpenAI API rate limits | Batch requests (100/call), exponential backoff |
| Large files cause chunking issues | Max chunk size limit (500 lines), split if needed |
