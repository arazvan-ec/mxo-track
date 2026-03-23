import { describe, it, expect, beforeEach } from 'vitest';
import { SearchEngine } from '../src/search/search-engine.js';
import type { StoredChunk } from '../src/storage/types.js';

function createMockChunk(overrides: Partial<StoredChunk> & { embedding: number[] }): StoredChunk {
  return {
    id: 'chunk-' + Math.random().toString(36).slice(2, 8),
    filePath: 'src/test.php',
    name: 'TestChunk',
    type: 'class',
    content: 'test content',
    startLine: 1,
    endLine: 10,
    language: 'php',
    ...overrides,
  };
}

// Simple 4-dim embeddings for testing
function makeEmbedding(values: number[]): number[] {
  // Normalize to unit vector for cosine similarity
  const norm = Math.sqrt(values.reduce((sum, v) => sum + v * v, 0));
  return values.map(v => v / norm);
}

describe('SearchEngine', () => {
  let engine: SearchEngine;

  beforeEach(() => {
    engine = new SearchEngine();
  });

  it('should build index from stored chunks', () => {
    const chunks: StoredChunk[] = [
      createMockChunk({ name: 'A', embedding: makeEmbedding([1, 0, 0, 0]) }),
      createMockChunk({ name: 'B', embedding: makeEmbedding([0, 1, 0, 0]) }),
    ];

    // Should not throw
    engine.buildIndex(chunks, 4);
    expect(true).toBe(true);
  });

  it('should return ranked results by similarity', () => {
    const chunks: StoredChunk[] = [
      createMockChunk({ name: 'Close', embedding: makeEmbedding([0.9, 0.1, 0, 0]) }),
      createMockChunk({ name: 'Far', embedding: makeEmbedding([0, 0, 1, 0]) }),
      createMockChunk({ name: 'Exact', embedding: makeEmbedding([1, 0, 0, 0]) }),
    ];

    engine.buildIndex(chunks, 4);
    const query = makeEmbedding([1, 0, 0, 0]);
    const results = engine.search(query, chunks, { limit: 3, minScore: 0 });

    expect(results).toHaveLength(3);
    // Exact match should be first
    expect(results[0].name).toBe('Exact');
    // Close should be second
    expect(results[1].name).toBe('Close');
    // Far should be last
    expect(results[2].name).toBe('Far');
  });

  it('should filter by language', () => {
    const chunks: StoredChunk[] = [
      createMockChunk({ name: 'PhpClass', language: 'php', embedding: makeEmbedding([1, 0, 0, 0]) }),
      createMockChunk({ name: 'YamlConfig', language: 'yaml', embedding: makeEmbedding([0.9, 0.1, 0, 0]) }),
    ];

    engine.buildIndex(chunks, 4);
    const query = makeEmbedding([1, 0, 0, 0]);
    const results = engine.search(query, chunks, { limit: 10, minScore: 0, language: 'yaml' });

    expect(results).toHaveLength(1);
    expect(results[0].name).toBe('YamlConfig');
  });

  it('should filter by type', () => {
    const chunks: StoredChunk[] = [
      createMockChunk({ name: 'MyClass', type: 'class', embedding: makeEmbedding([1, 0, 0, 0]) }),
      createMockChunk({ name: 'myMethod', type: 'method', embedding: makeEmbedding([0.9, 0.1, 0, 0]) }),
    ];

    engine.buildIndex(chunks, 4);
    const query = makeEmbedding([1, 0, 0, 0]);
    const results = engine.search(query, chunks, { limit: 10, minScore: 0, type: 'method' });

    expect(results).toHaveLength(1);
    expect(results[0].name).toBe('myMethod');
  });

  it('should filter by minScore', () => {
    const chunks: StoredChunk[] = [
      createMockChunk({ name: 'Close', embedding: makeEmbedding([1, 0, 0, 0]) }),
      createMockChunk({ name: 'Orthogonal', embedding: makeEmbedding([0, 1, 0, 0]) }),
    ];

    engine.buildIndex(chunks, 4);
    const query = makeEmbedding([1, 0, 0, 0]);
    const results = engine.search(query, chunks, { limit: 10, minScore: 0.8 });

    expect(results).toHaveLength(1);
    expect(results[0].name).toBe('Close');
  });

  it('should return empty array for empty index', () => {
    engine.buildIndex([], 4);
    const query = makeEmbedding([1, 0, 0, 0]);
    const results = engine.search(query, [], { limit: 10, minScore: 0 });
    expect(results).toHaveLength(0);
  });

  it('should respect limit parameter', () => {
    const chunks: StoredChunk[] = Array.from({ length: 10 }, (_, i) =>
      createMockChunk({
        name: `Chunk${i}`,
        embedding: makeEmbedding([1 - i * 0.05, i * 0.05, 0, 0]),
      }),
    );

    engine.buildIndex(chunks, 4);
    const query = makeEmbedding([1, 0, 0, 0]);
    const results = engine.search(query, chunks, { limit: 3, minScore: 0 });

    expect(results).toHaveLength(3);
  });

  it('should include snippet in results (first 200 chars of content)', () => {
    const longContent = 'A'.repeat(300);
    const chunks: StoredChunk[] = [
      createMockChunk({ name: 'Long', content: longContent, embedding: makeEmbedding([1, 0, 0, 0]) }),
    ];

    engine.buildIndex(chunks, 4);
    const query = makeEmbedding([1, 0, 0, 0]);
    const results = engine.search(query, chunks, { limit: 1, minScore: 0 });

    expect(results[0].snippet.length).toBeLessThanOrEqual(200);
  });
});
