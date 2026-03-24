# Implementation Plan — Codebase Search Remote Microservice

**Goal:** Add SSE transport, git manager, webhook handler, and Dockerfile to deploy codebase-search as a Railway microservice.
**Spec:** `docs/superpowers/specs/2026-03-24-codebase-search-microservice-design.md`
**Branch:** `claude/add-workflow-documentation-Ygde6`
**Complexity:** S/M (~250 lines new code)

---

## File Structure

```
tools/codebase-search/
├── src/
│   ├── index.ts                   # NO CHANGES (stdio entrypoint stays)
│   ├── index-remote.ts            # NEW: remote entrypoint (SSE)
│   ├── server.ts                  # NO CHANGES
│   ├── indexer/indexer.ts         # MODIFY: configurable data dir for HNSW
│   ├── transport/
│   │   ├── sse-server.ts          # NEW: Express + SSE transport + auth
│   │   └── webhook-handler.ts     # NEW: GitHub push webhook
│   └── git/
│       └── git-manager.ts         # NEW: clone/pull repo
├── Dockerfile                     # NEW
├── .dockerignore                  # NEW
└── package.json                   # MODIFY: add express, start:remote script
```

---

## Tasks

### Task 1: Add express dependency and start:remote script

**File:** `tools/codebase-search/package.json`

- [ ] Add `"express": "^5.1.0"` to dependencies
- [ ] Add `"@types/express": "^5.0.0"` to devDependencies
- [ ] Add script: `"start:remote": "node dist/index-remote.js"`
- [ ] Run `cd tools/codebase-search && npm install`
- [ ] Verify build: `npm run build`
- [ ] Commit

### Task 2: Create GitManager

**File:** `tools/codebase-search/src/git/git-manager.ts`

```typescript
import { execFile } from 'node:child_process';
import { join } from 'node:path';
import { access } from 'node:fs/promises';
import { promisify } from 'node:util';

const execFileAsync = promisify(execFile);

export class GitManager {
  private repoPath: string;

  constructor(
    private repoUrl: string,
    private dataDir: string,
  ) {
    this.repoPath = join(dataDir, 'repo');
  }

  async ensureRepo(): Promise<string> {
    try {
      await access(join(this.repoPath, '.git'));
      await execFileAsync('git', ['pull', 'origin', 'main'], { cwd: this.repoPath });
    } catch {
      await execFileAsync('git', ['clone', '--depth=1', this.repoUrl, this.repoPath]);
    }
    return this.repoPath;
  }

  async pullAndDiff(): Promise<{ changed: string[]; deleted: string[] }> {
    const { stdout: beforeHead } = await execFileAsync('git', ['rev-parse', 'HEAD'], { cwd: this.repoPath });
    await execFileAsync('git', ['fetch', 'origin', 'main'], { cwd: this.repoPath });
    await execFileAsync('git', ['reset', '--hard', 'origin/main'], { cwd: this.repoPath });
    const { stdout: afterHead } = await execFileAsync('git', ['rev-parse', 'HEAD'], { cwd: this.repoPath });

    if (beforeHead.trim() === afterHead.trim()) {
      return { changed: [], deleted: [] };
    }

    const { stdout } = await execFileAsync(
      'git', ['diff', '--name-status', beforeHead.trim(), afterHead.trim()],
      { cwd: this.repoPath },
    );

    const changed: string[] = [];
    const deleted: string[] = [];

    for (const line of stdout.split('\n').filter(Boolean)) {
      const [status, ...fileParts] = line.split('\t');
      const file = fileParts.join('\t');
      if (status === 'D') {
        deleted.push(file);
      } else {
        changed.push(file);
      }
    }

    return { changed, deleted };
  }

  getRepoPath(): string {
    return this.repoPath;
  }
}
```

- [ ] Write test `tools/codebase-search/tests/git-manager.test.ts`
- [ ] Run tests, verify pass
- [ ] Commit

### Task 3: Create webhook handler

**File:** `tools/codebase-search/src/transport/webhook-handler.ts`

```typescript
import crypto from 'node:crypto';
import type { Request, Response } from 'express';
import type { GitManager } from '../git/git-manager.js';
import type { Indexer } from '../indexer/indexer.js';

export function createWebhookHandler(gitManager: GitManager, indexer: Indexer) {
  return async (req: Request, res: Response) => {
    const signature = req.headers['x-hub-signature-256'] as string | undefined;
    const secret = process.env.GITHUB_WEBHOOK_SECRET;

    if (!secret || !signature) {
      res.status(401).json({ error: 'Missing signature or secret' });
      return;
    }

    const expected = 'sha256=' + crypto
      .createHmac('sha256', secret)
      .update(JSON.stringify(req.body))
      .digest('hex');

    if (!crypto.timingSafeEqual(Buffer.from(signature), Buffer.from(expected))) {
      res.status(401).json({ error: 'Invalid signature' });
      return;
    }

    if (req.body.ref !== 'refs/heads/main') {
      res.status(200).json({ skipped: true, reason: 'not main branch' });
      return;
    }

    try {
      const { changed, deleted } = await gitManager.pullAndDiff();
      if (changed.length > 0 || deleted.length > 0) {
        await indexer.indexFull();
      }
      res.json({ indexed: changed.length, deleted: deleted.length });
    } catch (error) {
      console.error('[webhook] Reindex failed:', error);
      res.status(500).json({ error: 'Reindex failed' });
    }
  };
}
```

- [ ] Write test `tools/codebase-search/tests/webhook-handler.test.ts`
- [ ] Run tests, verify pass
- [ ] Commit

### Task 4: Create SSE server

**File:** `tools/codebase-search/src/transport/sse-server.ts`

```typescript
import express from 'express';
import type { Request, Response, NextFunction } from 'express';
import { SSEServerTransport } from '@modelcontextprotocol/sdk/server/sse.js';
import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { createWebhookHandler } from './webhook-handler.js';
import type { GitManager } from '../git/git-manager.js';
import type { Indexer } from '../indexer/indexer.js';

function authMiddleware(req: Request, res: Response, next: NextFunction) {
  const apiKey = process.env.API_KEY;
  if (!apiKey) {
    res.status(500).json({ error: 'Server API_KEY not configured' });
    return;
  }
  const token = req.headers.authorization?.replace('Bearer ', '');
  if (token !== apiKey) {
    res.status(401).json({ error: 'Unauthorized' });
    return;
  }
  next();
}

export function createSseApp(
  server: McpServer,
  gitManager: GitManager,
  indexer: Indexer,
) {
  const app = express();

  app.get('/health', (_req: Request, res: Response) => {
    res.json({ status: 'ok' });
  });

  app.post('/webhook/github', express.json(), createWebhookHandler(gitManager, indexer));

  const transports = new Map<string, SSEServerTransport>();

  app.get('/sse', authMiddleware, async (req: Request, res: Response) => {
    const transport = new SSEServerTransport('/messages', res);
    transports.set(transport.sessionId, transport);
    res.on('close', () => transports.delete(transport.sessionId));
    await server.connect(transport);
  });

  app.post('/messages', authMiddleware, express.json(), async (req: Request, res: Response) => {
    const sessionId = req.query.sessionId as string;
    const transport = transports.get(sessionId);
    if (!transport) {
      res.status(400).json({ error: 'Unknown session' });
      return;
    }
    await transport.handlePostMessage(req, res);
  });

  return app;
}
```

- [ ] Write test `tools/codebase-search/tests/sse-server.test.ts`
- [ ] Run tests, verify pass
- [ ] Commit

### Task 5: Modify Indexer for configurable data dir

**File:** `tools/codebase-search/src/indexer/indexer.ts`

Add optional `dataDir` as 6th constructor parameter:

```typescript
constructor(
  private parserRegistry: ParserRegistry,
  private embedder: Embedder,
  private storage: StorageBackend,
  private searchEngine: SearchEngine,
  private projectRoot: string,
  private dataDir?: string,
) {}

private getDataDir(): string {
  return this.dataDir ?? join(this.projectRoot, 'tools', 'codebase-search', 'data');
}
```

- [ ] Run existing tests — verify no regression
- [ ] Commit

### Task 6: Create remote entrypoint

**File:** `tools/codebase-search/src/index-remote.ts`

```typescript
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
const GITHUB_REPO_URL = process.env.GITHUB_REPO_URL;
const OPENAI_API_KEY = process.env.OPENAI_API_KEY;
const PORT = parseInt(process.env.PORT ?? '3000', 10);

if (!GITHUB_REPO_URL) {
  console.error('[remote] GITHUB_REPO_URL is required');
  process.exit(1);
}

if (!OPENAI_API_KEY) {
  console.error('[remote] WARNING: OPENAI_API_KEY not set');
}

async function main() {
  const gitManager = new GitManager(GITHUB_REPO_URL!, DATA_DIR);
  const projectRoot = await gitManager.ensureRepo();
  console.error(`[remote] Repo ready at ${projectRoot}`);

  const parserRegistry = new ParserRegistry();
  const embedder = new OpenAIEmbedder(OPENAI_API_KEY ?? '');
  const storage = new JsonStorage(DATA_DIR);
  const searchEngine = new SearchEngine();
  const indexer = new Indexer(parserRegistry, embedder, storage, searchEngine, projectRoot, DATA_DIR);

  const existing = await storage.load();
  if (!existing) {
    console.error('[remote] No index found, running full reindex...');
    await indexer.indexFull();
    console.error('[remote] Initial indexing complete');
  }

  const mcpServer = createServer(indexer, searchEngine, storage, embedder);
  const app = createSseApp(mcpServer, gitManager, indexer);

  app.listen(PORT, () => {
    console.error(`[remote] Listening on port ${PORT}`);
  });
}

main().catch((error) => {
  console.error('[remote] Fatal error:', error);
  process.exit(1);
});
```

- [ ] Build: `npm run build`
- [ ] Verify `dist/index-remote.js` exists
- [ ] Commit

### Task 7: Create Dockerfile and .dockerignore

**File:** `tools/codebase-search/Dockerfile`

```dockerfile
FROM node:22-bookworm-slim
RUN apt-get update && apt-get install -y --no-install-recommends git \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /app
COPY package*.json ./
RUN npm ci --omit=dev
COPY dist/ ./dist/
ENV DATA_DIR=/data
EXPOSE 3000
CMD ["node", "dist/index-remote.js"]
```

**File:** `tools/codebase-search/.dockerignore`

```
node_modules
src
tests
data
*.ts
tsconfig.json
.env
```

- [ ] Commit

### Task 8: Final verification

- [ ] Run all tests: `cd tools/codebase-search && npm test`
- [ ] Run build: `npm run build`
- [ ] Verify `dist/index-remote.js` exists
- [ ] Push all changes
