import OpenAI from 'openai';
import type { Embedder } from './embedder-interface.js';

const MAX_BATCH_SIZE = parseInt(process.env.EMBEDDING_BATCH_SIZE || '20', 10);
const MAX_RETRIES = 3;
const RETRY_BASE_DELAY_MS = 1000;
// OpenAI text-embedding-3-small has an 8192 token limit per input.
// Code tokenizes at ~2.5-3 chars/token (not ~4 as in prose), so use
// a conservative limit: 8192 tokens * 2.5 chars/token ≈ 20000 chars.
const MAX_INPUT_CHARS = 20000;

export class OpenAIEmbedder implements Embedder {
  readonly dimensions = 1536;
  readonly modelId = 'text-embedding-3-small';
  private client: OpenAI;

  constructor(apiKey: string) {
    this.client = new OpenAI({ apiKey });
  }

  async embed(texts: string[]): Promise<number[][]> {
    if (texts.length === 0) {
      return [];
    }

    const allEmbeddings: number[][] = [];

    // Process in batches
    for (let i = 0; i < texts.length; i += MAX_BATCH_SIZE) {
      const batch = texts.slice(i, i + MAX_BATCH_SIZE);
      const embeddings = await this.embedBatch(batch);
      allEmbeddings.push(...embeddings);
    }

    return allEmbeddings;
  }

  private async embedBatch(texts: string[]): Promise<number[][]> {
    let lastError: Error | undefined;

    for (let attempt = 0; attempt < MAX_RETRIES; attempt++) {
      try {
        const truncated = texts.map(t =>
          t.length > MAX_INPUT_CHARS ? t.slice(0, MAX_INPUT_CHARS) : t,
        );
        const response = await this.client.embeddings.create({
          model: this.modelId,
          input: truncated,
        });

        // Sort by index to ensure correct order
        const sorted = response.data.sort((a, b) => a.index - b.index);
        return sorted.map(item => item.embedding);
      } catch (error) {
        lastError = error as Error;
        if (attempt < MAX_RETRIES - 1) {
          const delay = RETRY_BASE_DELAY_MS * Math.pow(2, attempt);
          await new Promise(resolve => setTimeout(resolve, delay));
        }
      }
    }

    throw new Error(`Embedding failed after ${MAX_RETRIES} retries: ${lastError?.message}`);
  }
}
