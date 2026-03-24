import { readFile, writeFile, mkdir } from 'node:fs/promises';
import { existsSync } from 'node:fs';
import { join } from 'node:path';
import type { StorageBackend } from './storage-interface.js';
import type { StoredIndex } from './types.js';
import type { IndexMetadata } from '../indexer/types.js';

const INDEX_FILE = 'index.json';
const HNSW_FILE = 'hnsw.dat';

export class JsonStorage implements StorageBackend {
  constructor(private readonly dataDir: string) {}

  async save(index: StoredIndex): Promise<void> {
    await this.ensureDir();
    const filePath = join(this.dataDir, INDEX_FILE);
    await writeFile(filePath, JSON.stringify(index), 'utf-8');
  }

  async load(): Promise<StoredIndex | null> {
    const filePath = join(this.dataDir, INDEX_FILE);
    if (!existsSync(filePath)) {
      return null;
    }
    const content = await readFile(filePath, 'utf-8');
    return JSON.parse(content) as StoredIndex;
  }

  async saveHnswIndex(data: Buffer): Promise<void> {
    await this.ensureDir();
    const filePath = join(this.dataDir, HNSW_FILE);
    await writeFile(filePath, data);
  }

  async loadHnswIndex(): Promise<Buffer | null> {
    const filePath = join(this.dataDir, HNSW_FILE);
    if (!existsSync(filePath)) {
      return null;
    }
    return readFile(filePath);
  }

  async getMetadata(): Promise<IndexMetadata | null> {
    const index = await this.load();
    if (!index) {
      return null;
    }
    return index.metadata;
  }

  private async ensureDir(): Promise<void> {
    if (!existsSync(this.dataDir)) {
      await mkdir(this.dataDir, { recursive: true });
    }
  }
}
