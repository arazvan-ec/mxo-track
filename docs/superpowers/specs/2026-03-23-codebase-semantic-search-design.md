# Design Spec — Codebase Semantic Search MCP Server

**Date:** 2026-03-23
**Type:** New feature (developer tooling)
**Bounded context:** Pragmático (tooling externo, no dominio de negocio)

---

## Summary

MCP server standalone en TypeScript (`/tools/codebase-search/`) que indexa el codebase completo (PHP, Twig, YAML, Markdown, migrations) usando AST parsing por clase/función, genera embeddings con OpenAI `text-embedding-3-small`, almacena en HNSW index, y expone búsqueda semántica como tool de Claude Code via MCP protocol.

## User Decisions

| Decision | Choice |
|----------|--------|
| Storage | JSON flat file con interfaz abstracta (swappable) |
| Scope | Todo: PHP, Twig, config YAML, docs Markdown, migrations |
| Granularity | AST parsing por clase/función |
| Consumption | MCP server para Claude Code (no CLI) |
| Re-indexing | Manual full + incremental automática |
| Embeddings | OpenAI `text-embedding-3-small` (1536 dims) |
| Search | HNSW (hnswlib-node) |
| Language | TypeScript |
| Location | `/tools/codebase-search/` (standalone) |

## Existing Functionality Inventory

No existing semantic search functionality in the project. Related:
- `.claude/settings.json` — hooks configured, no MCP servers
- `docs/codebase-manifest.md` — static manifest (not semantic)
- Grep/Glob — keyword search (not semantic)

## Omission Decisions

| Element | Decision | Justification |
|---------|----------|---------------|
| CLI command | Omit | User chose MCP-only (option B) |
| Web UI for search | Omit | Not requested, developer tool only |
| Codebase manifest integration | Omit | Different purpose (structural vs semantic) |

---

## Architecture

### Directory Structure

```
tools/codebase-search/
├── package.json
├── tsconfig.json
├── .env.example          # OPENAI_API_KEY
├── src/
│   ├── index.ts                    # MCP server entry point
│   ├── server.ts                   # MCP server setup + tool registration
│   │
│   ├── indexer/
│   │   ├── indexer.ts              # Orchestrates: parse → chunk → embed → store
│   │   ├── incremental.ts          # Git diff detection, mtime comparison
│   │   └── types.ts                # CodeChunk, IndexMetadata interfaces
│   │
│   ├── parser/
│   │   ├── parser-interface.ts     # FileParser interface
│   │   ├── php-parser.ts           # tree-sitter PHP → chunks by class/method
│   │   ├── twig-parser.ts          # tree-sitter Twig → chunks by block/macro
│   │   ├── yaml-parser.ts          # tree-sitter YAML → chunks by top-level key
│   │   ├── markdown-parser.ts      # Heading-based chunking (## sections)
│   │   ├── sql-parser.ts           # Migration files → chunks by statement
│   │   └── parser-registry.ts      # Extension → parser mapping
│   │
│   ├── embeddings/
│   │   ├── embedder-interface.ts   # Embedder interface (swappable)
│   │   └── openai-embedder.ts      # OpenAI text-embedding-3-small impl
│   │
│   ├── search/
│   │   ├── search-engine.ts        # HNSW search + result ranking
│   │   └── types.ts                # SearchResult, SearchOptions
│   │
│   └── storage/
│       ├── storage-interface.ts    # StorageBackend interface
│       ├── json-storage.ts         # JSON flat file implementation
│       └── types.ts                # StoredIndex, StoredChunk
│
├── data/                           # Generated (gitignored)
│   ├── index.json                  # Chunks + metadata
│   └── hnsw.dat                    # HNSW binary index
│
└── tests/
    ├── parser.test.ts
    ├── indexer.test.ts
    ├── search.test.ts
    └── storage.test.ts
```

### Core Interfaces

#### StorageInterface (swappable storage)

```typescript
interface StorageBackend {
  save(index: StoredIndex): Promise<void>;
  load(): Promise<StoredIndex | null>;
  saveHnswIndex(buffer: Buffer): Promise<void>;
  loadHnswIndex(): Promise<Buffer | null>;
  getMetadata(): Promise<IndexMetadata | null>;
}
```

Para cambiar de JSON a SQLite, Redis, o S3: implementar esta interfaz. Zero cambios en indexer/search.

#### FileParser (per-language AST parsing)

```typescript
interface FileParser {
  readonly extensions: string[];
  parse(filePath: string, content: string): CodeChunk[];
}

interface CodeChunk {
  id: string;                    // hash(filePath + name + type)
  filePath: string;              // relative to project root
  name: string;                  // class/method/function/block name
  type: ChunkType;               // 'class' | 'method' | 'function' | 'block' | 'section' | 'config' | 'migration'
  content: string;               // raw source code of the chunk
  startLine: number;
  endLine: number;
  parentName?: string;           // e.g., class name for a method
  language: string;              // 'php' | 'twig' | 'yaml' | 'markdown' | 'sql'
  metadata?: Record<string, string>; // namespace, annotations, etc.
}
```

#### Embedder (swappable model)

```typescript
interface Embedder {
  embed(texts: string[]): Promise<number[][]>;
  readonly dimensions: number;
  readonly modelId: string;
}
```

Para cambiar a Voyage, local, o cualquier otro: implementar esta interfaz.

### MCP Tools Exposed

#### `codebase_search`

Búsqueda semántica principal.

```typescript
{
  name: "codebase_search",
  description: "Semantic search across the codebase. Returns relevant code chunks ranked by similarity.",
  inputSchema: {
    query: string;           // Natural language query
    limit?: number;          // Max results (default 10)
    language?: string;       // Filter by language: php, twig, yaml, markdown, sql
    type?: string;           // Filter by chunk type: class, method, function, block, section
    minScore?: number;       // Minimum similarity threshold (default 0.5)
  }
}
```

Returns: Array of `{ filePath, name, type, startLine, endLine, score, snippet }`.

#### `codebase_index`

Trigger re-indexación.

```typescript
{
  name: "codebase_index",
  description: "Re-index the codebase. Use 'full' for complete reindex or 'incremental' for changes only.",
  inputSchema: {
    mode: "full" | "incremental";
  }
}
```

#### `codebase_index_status`

Estado del índice actual.

```typescript
{
  name: "codebase_index_status",
  description: "Show current index status: chunk count, last indexed, coverage.",
  inputSchema: {}
}
```

### Indexing Pipeline

```
1. Scan files (glob patterns per scope)
   ↓
2. Incremental check (git diff / mtime vs stored metadata)
   ↓
3. Parse changed files (tree-sitter AST → CodeChunks)
   ↓
4. Generate embeddings (OpenAI batch API, ~100 chunks per request)
   ↓
5. Update HNSW index (add/remove points)
   ↓
6. Persist to storage (JSON + HNSW binary)
```

### Incremental Re-indexation

```typescript
// Uses git diff to detect changes since last index
async function getChangedFiles(lastIndexedCommit: string): Promise<{
  added: string[];
  modified: string[];
  deleted: string[];
}> {
  // git diff --name-status <lastCommit> HEAD
}
```

- **Added/Modified files:** Re-parse, re-embed, update HNSW
- **Deleted files:** Remove chunks from index, rebuild HNSW (hnswlib doesn't support deletion — mark as deleted + periodic rebuild)
- **Unchanged files:** Skip entirely
- **Fallback:** If git diff fails or no previous commit stored, full reindex

### Chunk Content Formatting

Each chunk's text sent to embeddings includes contextual prefix for better semantic matching:

```
[php:class] App\Entity\Vehicle
namespace App\Entity;

class Vehicle implements CustomerScopedEntityInterface {
    ...
}
```

```
[php:method] App\Service\RouteOptimizationService::optimize
public function optimize(Route $route, OptimizationOptions $options): OptimizationResult {
    ...
}
```

This prefix helps the embedding model understand the type and location of code.

### Claude Code Integration

Add to `.claude/settings.json`:

```json
{
  "mcpServers": {
    "codebase-search": {
      "command": "node",
      "args": ["tools/codebase-search/dist/index.js"],
      "cwd": "/home/user/mxo-track",
      "env": {
        "OPENAI_API_KEY": "${OPENAI_API_KEY}"
      }
    }
  }
}
```

### Dependencies

```json
{
  "dependencies": {
    "@modelcontextprotocol/sdk": "^1.27",
    "openai": "^4.x",
    "hnswlib-node": "^3.x",
    "tree-sitter": "^0.22",
    "tree-sitter-php": "^0.23",
    "tree-sitter-yaml": "^0.6",
    "tree-sitter-twig": "^1.x",
    "zod": "^3.x",
    "glob": "^11.x"
  },
  "devDependencies": {
    "typescript": "^5.x",
    "vitest": "^2.x",
    "@types/node": "^22.x"
  }
}
```

### Error Handling

- **No OPENAI_API_KEY:** Server starts but `codebase_search` and `codebase_index` return clear error message asking to set the key
- **No index exists:** `codebase_search` returns message suggesting to run `codebase_index` first
- **tree-sitter grammar missing:** Skip that file type, log warning, continue with available parsers
- **Embedding API failure:** Retry 3x with exponential backoff, then fail with clear error
- **HNSW build failure:** Fall back to brute-force cosine similarity with warning

### File Scope Patterns

```typescript
const SCOPE_PATTERNS = {
  php: ['backend/src/**/*.php', 'backend/tests/**/*.php'],
  twig: ['backend/templates/**/*.twig', 'backend/templates/**/*.html.twig'],
  yaml: ['backend/config/**/*.yaml', 'backend/config/**/*.yml'],
  markdown: ['docs/**/*.md', '*.md', 'backend/*.md'],
  sql: ['backend/migrations/**/*.php'],  // Doctrine migrations are PHP files with SQL
};
```

### Performance Estimates

- **Full index:** ~2-5K chunks, ~$0.01 embedding cost, ~30-60 seconds
- **Incremental index:** Typically 5-50 chunks, <1 second + embedding time
- **Search query:** Embed query (~200ms API) + HNSW search (~2ms) = ~200ms total
- **Storage:** JSON ~2-5MB, HNSW binary ~5-15MB

---

## Non-Goals (explicitly out of scope)

- Code generation or AI-powered refactoring
- Real-time file watching (re-index is triggered manually or by tool call)
- Multi-project support (single project: mxo-track)
- Authentication/authorization for the MCP server
- Caching of embedding API responses (chunks are stored, no need to re-embed unchanged)

---

## Alternatives Evaluated

### Approach A: Standalone MCP server en `/tools/codebase-search/` (ELEGIDO)
- **Ventaja:** Aislado del frontend y backend, deploy independiente, no contamina dependencias
- **Desventaja:** Otro `node_modules/` en el repo
- **Trade-off:** Aislamiento > conveniencia de compartir dependencias

### Approach B: Dentro del frontend existente (`/frontend/src/mcp/`)
- **Ventaja:** Reutiliza `package.json` y toolchain TS existente
- **Desventaja:** Acopla herramienta de desarrollo con app de producción, dependencias nativas (hnswlib) contaminan el bundle
- **Descartado:** Mezcla concerns de producción con tooling de desarrollo

### Approach C: Monorepo con npm workspaces
- **Ventaja:** Gestión unificada de dependencias
- **Desventaja:** Over-engineering para un solo tool, complica el setup del frontend
- **Descartado:** Complejidad injustificada para el problema actual

### Otras decisiones de diseño

| Problema | Opcion elegida | Alternativa descartada | Razón |
|----------|---------------|----------------------|-------|
| Embeddings model | OpenAI `text-embedding-3-small` | Local `all-MiniLM-L6-v2` | ~15-20% mejor calidad en code search, costo mínimo ($0.01 full index) |
| Embeddings model | OpenAI `text-embedding-3-small` | `voyage-code-3` | Similar calidad, OpenAI SDK más maduro, menor costo |
| Vector search | HNSW (hnswlib-node) | Brute force cosine | User preference; aunque brute force suficiente para <5K chunks |
| Storage | JSON flat file + interfaz abstracta | SQLite / Redis | Simplicidad, sin dependencias extra, interfaz permite swap futuro |
| AST parsing | tree-sitter | Regex-based | Mejor precisión en boundaries de clases/métodos, soporte multi-lenguaje |
| Chunking | Por clase/función | Por archivo | Más preciso para búsqueda semántica, mejor ranking |
| MCP transport | stdio | HTTP/SSE | Claude Code usa stdio para MCP servers locales |
