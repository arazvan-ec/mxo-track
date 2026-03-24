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

    const body = JSON.stringify(req.body);
    const expected =
      'sha256=' + crypto.createHmac('sha256', secret).update(body).digest('hex');

    if (
      signature.length !== expected.length ||
      !crypto.timingSafeEqual(Buffer.from(signature), Buffer.from(expected))
    ) {
      res.status(401).json({ error: 'Invalid signature' });
      return;
    }

    // Only process pushes to main
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

/** Helper to compute webhook signature (exported for testing) */
export function computeSignature(secret: string, body: string): string {
  return 'sha256=' + crypto.createHmac('sha256', secret).update(body).digest('hex');
}
