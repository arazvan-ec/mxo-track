import type { CodeChunk } from '../indexer/types.js';

export interface FileParser {
  readonly extensions: string[];
  parse(filePath: string, content: string): CodeChunk[];
}
