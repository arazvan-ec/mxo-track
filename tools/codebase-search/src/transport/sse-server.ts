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

  // Health check — no auth
  app.get('/health', (_req: Request, res: Response) => {
    res.json({ status: 'ok' });
  });

  // Webhook — own auth via signature
  app.post('/webhook/github', express.json(), createWebhookHandler(gitManager, indexer));

  // MCP SSE — Bearer auth
  const transports = new Map<string, SSEServerTransport>();

  app.get('/sse', authMiddleware, async (req: Request, res: Response) => {
    try {
      const transport = new SSEServerTransport('/messages', res);
      const sessionId = transport.sessionId;
      transports.set(sessionId, transport);

      transport.onclose = () => {
        transports.delete(sessionId);
      };

      res.on('close', () => {
        transports.delete(sessionId);
      });

      await server.connect(transport);
    } catch (error) {
      console.error('[sse] Error establishing SSE stream:', error);
      if (!res.headersSent) {
        res.status(500).send('Error establishing SSE stream');
      }
    }
  });

  app.post('/messages', authMiddleware, express.json(), async (req: Request, res: Response) => {
    const sessionId = req.query.sessionId as string;
    if (!sessionId) {
      res.status(400).json({ error: 'Missing sessionId parameter' });
      return;
    }

    const transport = transports.get(sessionId);
    if (!transport) {
      res.status(404).json({ error: 'Session not found' });
      return;
    }

    try {
      await transport.handlePostMessage(req, res, req.body);
    } catch (error) {
      console.error('[sse] Error handling message:', error);
      if (!res.headersSent) {
        res.status(500).json({ error: 'Error handling message' });
      }
    }
  });

  return app;
}
