# Design Spec — Codebase Search Remote Microservice

**Date:** 2026-03-24
**Type:** Enhancement (developer tooling)
**Bounded context:** Pragmático (tooling externo, no dominio de negocio)
**Base:** Builds on `2026-03-23-codebase-semantic-search-design.md` (local MCP server already implemented)

---

## Summary

Convert the existing local MCP server (`tools/codebase-search/`) into a remotely deployable microservice on Railway. The server switches from `stdio` to `SSE` transport, clones the repo via git instead of reading from the local filesystem, and auto-reindexes on GitHub push webhooks. Claude Code connects via remote MCP SSE URL with Bearer auth.

## User Decisions

| Decision | Choice |
|----------|--------|
| Transport | SSE (HTTP) — replacing stdio |
| Code source | git clone inside container (not local filesystem) |
| Re-indexing trigger | GitHub push webhook → automatic |
| Auth | Bearer API key on SSE connection |
| Deploy target | Railway |
| Index storage | Volume persistente Railway (`/data`) |

## Existing Functionality Inventory

| Element | Status | Notes |
|---------|--------|-------|
| `src/server.ts` — MCP tool registration (3 tools) | Exists | No changes needed to tool logic |
| `src/index.ts` — stdio entrypoint | Exists | Replaced by HTTP entrypoint |
| `src/search/search-engine.ts` | Exists | No changes |
| `src/indexer/indexer.ts` | Exists | Minor: accept configurable project root |
| `src/parser/*` | Exists | No changes |
| `src/embeddings/*` | Exists | No changes |
| `src/storage/*` | Exists | No changes, DATA_DIR becomes env var |
| `.claude/settings.json` MCP config | Exists | Switch from `command` to `url` |

## Omission Decisions

| Element | Decision | Justification |
|---------|----------|---------------|
| stdio transport | Keep as fallback | Dual-mode: stdio for local dev, SSE for remote |
| Authentication UI | Omit | API key in env var, no user-facing config |
| Multi-repo support | Omit | Single repo (mxo-track) for now |
| Rate limiting | Omit | Single-user tool, not public-facing |
| Webhook signature validation | Include | Security: validate `X-Hub-Signature-256` |

---

## Architecture

### What Changes vs Local Version

| Aspect | Local (current) | Microservice (new) |
|--------|----------------|-------------------|
| Transport | stdio | SSE (HTTP) |
| Code source | Local filesystem | git clone in container |
| Index location | `tools/codebase-search/data/` | `/data` volume (Railway) |
| Re-indexing | Manual (`codebase_index` tool) | Auto via webhook + manual tool |
| Auth | None (local) | Bearer API key |
| Config in Claude Code | `command: "node"` | `url: "https://..."` |

### New Files

```
tools/codebase-search/
├── src/
│   ├── transport/
│   │   ├── sse-server.ts          # HTTP server + SSE transport + auth middleware
│   │   └── webhook-handler.ts     # GitHub push webhook handler
│   ├── git/
│   │   └── git-manager.ts         # Clone, pull, detect project root
│   └── index-remote.ts            # New entrypoint for remote mode
├── Dockerfile
└── .dockerignore
```

### Modified Files

```
tools/codebase-search/
├── src/
│   ├── server.ts                  # Accept transport parameter (stdio or SSE)
│   └── indexer/
│       └── indexer.ts             # Accept configurable project root path
├── package.json                   # Add start:remote script
```

### Endpoint Map

| Route | Method | Purpose | Auth |
|-------|--------|---------|------|
| `/sse` | GET | MCP SSE connection | Bearer API_KEY |
| `/messages` | POST | MCP client messages | Bearer API_KEY (session-bound) |
| `/webhook/github` | POST | Push events | X-Hub-Signature-256 |
| `/health` | GET | Railway health check | None |

### SSE Transport (`sse-server.ts`)

```typescript
import express from 'express';
import { SSEServerTransport } from '@modelcontextprotocol/sdk/server/sse.js';
import { createServer } from './server.js';

const app = express();

// Auth middleware for MCP endpoints
function authMiddleware(req, res, next) {
  const token = req.headers.authorization?.replace('Bearer ', '');
  if (token !== process.env.API_KEY) {
    return res.status(401).json({ error: 'Unauthorized' });
  }
  next();
}

// Health check (no auth)
app.get('/health', (req, res) => res.json({ status: 'ok' }));

// Webhook (own auth via signature)
app.post('/webhook/github', express.json(), webhookHandler);

// MCP SSE endpoints (Bearer auth)
let transport: SSEServerTransport;
app.get('/sse', authMiddleware, (req, res) => {
  transport = new SSEServerTransport('/messages', res);
  server.connect(transport);
});
app.post('/messages', authMiddleware, (req, res) => {
  transport.handlePostMessage(req, res);
});
```

### Git Manager (`git-manager.ts`)

```typescript
class GitManager {
  constructor(
    private repoUrl: string,
    private dataDir: string
  ) {}

  /** Clone repo if not present, pull if exists */
  async ensureRepo(): Promise<string> {
    const repoPath = path.join(this.dataDir, 'repo');
    if (await exists(repoPath)) {
      await exec('git pull origin main', { cwd: repoPath });
    } else {
      await exec(`git clone --depth=1 ${this.repoUrl} ${repoPath}`);
    }
    return repoPath;
  }

  /** Pull latest and return changed files for incremental index */
  async pullAndDiff(): Promise<{ changed: string[]; deleted: string[] }> {
    const repoPath = path.join(this.dataDir, 'repo');
    const beforeHead = await exec('git rev-parse HEAD', { cwd: repoPath });
    await exec('git pull origin main', { cwd: repoPath });
    const afterHead = await exec('git rev-parse HEAD', { cwd: repoPath });
    if (beforeHead === afterHead) return { changed: [], deleted: [] };
    // git diff --name-status beforeHead..afterHead
    // parse output into changed/deleted
  }
}
```

### Webhook Handler (`webhook-handler.ts`)

```typescript
import crypto from 'crypto';

async function webhookHandler(req, res) {
  // 1. Validate signature
  const signature = req.headers['x-hub-signature-256'];
  const expected = 'sha256=' + crypto
    .createHmac('sha256', process.env.GITHUB_WEBHOOK_SECRET)
    .update(JSON.stringify(req.body))
    .digest('hex');
  if (!crypto.timingSafeEqual(Buffer.from(signature), Buffer.from(expected))) {
    return res.status(401).json({ error: 'Invalid signature' });
  }

  // 2. Only process push events to main
  if (req.body.ref !== 'refs/heads/main') {
    return res.status(200).json({ skipped: true });
  }

  // 3. Pull and reindex
  const { changed, deleted } = await gitManager.pullAndDiff();
  if (changed.length > 0 || deleted.length > 0) {
    await indexer.indexIncremental(changed, deleted);
  }
  res.json({ indexed: changed.length, deleted: deleted.length });
}
```

### Environment Variables (Railway)

| Variable | Purpose | Required |
|----------|---------|----------|
| `OPENAI_API_KEY` | Embeddings API | Yes |
| `API_KEY` | Bearer auth for MCP clients | Yes |
| `GITHUB_WEBHOOK_SECRET` | Validate webhook signatures | Yes |
| `GITHUB_REPO_URL` | URL to clone (with token if private) | Yes |
| `DATA_DIR` | Persistent volume mount path | Yes (default: `/data`) |
| `PORT` | HTTP server port | No (default: 3000, Railway sets this) |

### Dockerfile

```dockerfile
FROM node:22-bookworm-slim

RUN apt-get update && apt-get install -y git && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY package*.json ./
RUN npm ci --production

COPY dist/ ./dist/

ENV DATA_DIR=/data
VOLUME ["/data"]

EXPOSE 3000
CMD ["node", "dist/index-remote.js"]
```

### Claude Code Configuration (Remote)

```json
{
  "mcpServers": {
    "codebase-search": {
      "url": "https://codebase-search.up.railway.app/sse",
      "headers": {
        "Authorization": "Bearer ${MCP_CODEBASE_SEARCH_KEY}"
      }
    }
  }
}
```

### Startup Sequence

1. Server starts, reads env vars
2. `GitManager.ensureRepo()` — clone or pull
3. Check if index exists in `DATA_DIR`
   - If no index: full reindex automatically
   - If index exists: ready to serve
4. Start Express server on `PORT`
5. `/health` returns 200

### Dual Mode Support

The server supports both local (stdio) and remote (SSE) modes:

- `npm start` → stdio mode (existing behavior, for local dev)
- `npm run start:remote` → SSE mode (for Railway deployment)
- `src/server.ts` creates the MCP server instance without transport
- `src/index.ts` connects via stdio
- `src/index-remote.ts` connects via SSE + starts HTTP server

---

## Non-Goals

- Multi-repo support
- Rate limiting or usage quotas
- HTTPS termination (Railway handles this)
- CI/CD pipeline (manual deploy via Railway CLI or GitHub integration)
- Streaming reindex progress to client

---

## Complexity Estimate

**S/M** — Core search engine untouched. Changes are:
- `sse-server.ts` (~80 lines)
- `git-manager.ts` (~60 lines)
- `webhook-handler.ts` (~40 lines)
- `index-remote.ts` (~30 lines)
- Minor edits to `server.ts` and `indexer.ts` (~20 lines)
- `Dockerfile` + `.dockerignore` (~20 lines)

Total new code: ~250 lines.
