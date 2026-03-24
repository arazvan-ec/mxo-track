import { describe, it, expect, beforeEach, afterEach } from 'vitest';
import { JsonStorage } from '../src/storage/json-storage.js';
import type { StoredIndex } from '../src/storage/types.js';
import { mkdtempSync, rmSync } from 'node:fs';
import { join } from 'node:path';
import { tmpdir } from 'node:os';

function createMockIndex(): StoredIndex {
  return {
    metadata: {
      lastIndexedCommit: 'abc123',
      lastIndexedAt: '2026-03-23T00:00:00Z',
      totalChunks: 1,
      embeddingModel: 'text-embedding-3-small',
      embeddingDimensions: 1536,
      fileCount: { php: 1, twig: 0, yaml: 0, markdown: 0, sql: 0 },
    },
    chunks: [
      {
        id: 'chunk-1',
        filePath: 'src/Entity/Vehicle.php',
        name: 'Vehicle',
        type: 'class',
        content: 'class Vehicle {}',
        startLine: 1,
        endLine: 10,
        language: 'php',
        embedding: [0.1, 0.2, 0.3],
      },
    ],
  };
}

describe('JsonStorage', () => {
  let tmpDir: string;
  let storage: JsonStorage;

  beforeEach(() => {
    tmpDir = mkdtempSync(join(tmpdir(), 'codebase-search-test-'));
    storage = new JsonStorage(tmpDir);
  });

  afterEach(() => {
    rmSync(tmpDir, { recursive: true, force: true });
  });

  it('should return null when loading from non-existent directory', async () => {
    const result = await storage.load();
    expect(result).toBeNull();
  });

  it('should save and load index roundtrip', async () => {
    const mockIndex = createMockIndex();
    await storage.save(mockIndex);

    const loaded = await storage.load();
    expect(loaded).not.toBeNull();
    expect(loaded!.metadata.lastIndexedCommit).toBe('abc123');
    expect(loaded!.chunks).toHaveLength(1);
    expect(loaded!.chunks[0].name).toBe('Vehicle');
    expect(loaded!.chunks[0].embedding).toEqual([0.1, 0.2, 0.3]);
  });

  it('should save and load HNSW index binary roundtrip', async () => {
    const data = Buffer.from([1, 2, 3, 4, 5, 6, 7, 8]);
    await storage.saveHnswIndex(data);

    const loaded = await storage.loadHnswIndex();
    expect(loaded).not.toBeNull();
    expect(Buffer.compare(loaded!, data)).toBe(0);
  });

  it('should return null for HNSW index when file does not exist', async () => {
    const result = await storage.loadHnswIndex();
    expect(result).toBeNull();
  });

  it('should extract metadata from stored index', async () => {
    const mockIndex = createMockIndex();
    await storage.save(mockIndex);

    const metadata = await storage.getMetadata();
    expect(metadata).not.toBeNull();
    expect(metadata!.totalChunks).toBe(1);
    expect(metadata!.embeddingModel).toBe('text-embedding-3-small');
    expect(metadata!.lastIndexedCommit).toBe('abc123');
  });

  it('should return null metadata when no index exists', async () => {
    const metadata = await storage.getMetadata();
    expect(metadata).toBeNull();
  });
});
