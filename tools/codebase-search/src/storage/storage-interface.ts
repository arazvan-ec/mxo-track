import type { StoredIndex } from './types.js';
import type { IndexMetadata } from '../indexer/types.js';

export interface StorageBackend {
  save(index: StoredIndex): Promise<void>;
  load(): Promise<StoredIndex | null>;
  saveHnswIndex(data: Buffer): Promise<void>;
  loadHnswIndex(): Promise<Buffer | null>;
  getMetadata(): Promise<IndexMetadata | null>;
}
