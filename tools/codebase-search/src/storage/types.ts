import type { CodeChunk, IndexMetadata } from '../indexer/types.js';

export interface StoredChunk extends CodeChunk {
  embedding: number[];
}

export interface StoredIndex {
  metadata: IndexMetadata;
  chunks: StoredChunk[];
}
