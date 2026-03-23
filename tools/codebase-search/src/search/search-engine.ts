import { HierarchicalNSW } from 'hnswlib-node';
import type { StoredChunk } from '../storage/types.js';
import type { SearchResult, SearchOptions } from './types.js';

const SNIPPET_MAX_LENGTH = 200;

export class SearchEngine {
  private index: HierarchicalNSW | null = null;

  buildIndex(chunks: StoredChunk[], dimensions: number): void {
    if (chunks.length === 0) {
      this.index = null;
      return;
    }

    this.index = new HierarchicalNSW('cosine', dimensions);
    this.index.initIndex(chunks.length);

    for (let i = 0; i < chunks.length; i++) {
      this.index.addPoint(chunks[i].embedding, i);
    }
  }

  search(
    queryEmbedding: number[],
    chunks: StoredChunk[],
    options: SearchOptions,
  ): SearchResult[] {
    if (!this.index || chunks.length === 0) {
      return [];
    }

    // Search more than limit to account for post-filtering
    const k = Math.min(chunks.length, options.limit * 3);
    const result = this.index.searchKnn(queryEmbedding, k);

    const results: SearchResult[] = [];

    for (let i = 0; i < result.neighbors.length; i++) {
      const chunkIndex = result.neighbors[i];
      const distance = result.distances[i];
      const chunk = chunks[chunkIndex];

      // Convert cosine distance to similarity score (1 - distance for hnswlib cosine)
      const score = 1 - distance;

      // Apply filters
      if (score < options.minScore) continue;
      if (options.language && chunk.language !== options.language) continue;
      if (options.type && chunk.type !== options.type) continue;

      results.push({
        filePath: chunk.filePath,
        name: chunk.name,
        type: chunk.type,
        startLine: chunk.startLine,
        endLine: chunk.endLine,
        score,
        snippet: chunk.content.slice(0, SNIPPET_MAX_LENGTH),
        language: chunk.language,
        parentName: chunk.parentName,
      });

      if (results.length >= options.limit) break;
    }

    return results;
  }

  saveIndex(path: string): void {
    if (this.index) {
      this.index.writeIndex(path);
    }
  }

  loadIndex(path: string, dimensions: number, maxElements: number): void {
    this.index = new HierarchicalNSW('cosine', dimensions);
    // hnswlib-node types expect boolean but actually accepts maxElements number
    (this.index as any).readIndex(path, maxElements);
  }
}
