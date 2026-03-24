import { describe, it, expect, vi } from 'vitest';
import { OpenAIEmbedder } from '../src/embeddings/openai-embedder.js';

// Mock the openai module
vi.mock('openai', () => {
  return {
    default: class MockOpenAI {
      embeddings = {
        create: vi.fn().mockImplementation(async (params: { input: string | string[] }) => {
          const inputs = Array.isArray(params.input) ? params.input : [params.input];
          return {
            data: inputs.map((_: string, index: number) => ({
              embedding: new Array(1536).fill(0).map((_, i) => i * 0.001 + index * 0.01),
              index,
            })),
          };
        }),
      };
    },
  };
});

describe('OpenAIEmbedder', () => {
  it('should have correct dimensions and model', () => {
    const embedder = new OpenAIEmbedder('test-key');
    expect(embedder.dimensions).toBe(1536);
    expect(embedder.modelId).toBe('text-embedding-3-small');
  });

  it('should embed a single text', async () => {
    const embedder = new OpenAIEmbedder('test-key');
    const results = await embedder.embed(['hello world']);
    expect(results).toHaveLength(1);
    expect(results[0]).toHaveLength(1536);
  });

  it('should embed multiple texts in batch', async () => {
    const embedder = new OpenAIEmbedder('test-key');
    const texts = ['text one', 'text two', 'text three'];
    const results = await embedder.embed(texts);
    expect(results).toHaveLength(3);
    results.forEach(embedding => {
      expect(embedding).toHaveLength(1536);
    });
  });

  it('should return different embeddings for different texts', async () => {
    const embedder = new OpenAIEmbedder('test-key');
    const results = await embedder.embed(['text one', 'text two']);
    // Our mock generates different values per index
    expect(results[0][0]).not.toBe(results[1][0]);
  });

  it('should handle empty input array', async () => {
    const embedder = new OpenAIEmbedder('test-key');
    const results = await embedder.embed([]);
    expect(results).toHaveLength(0);
  });
});
