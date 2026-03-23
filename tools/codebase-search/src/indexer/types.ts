export type ChunkType = 'class' | 'method' | 'function' | 'block' | 'section' | 'config' | 'migration';
export type Language = 'php' | 'twig' | 'yaml' | 'markdown' | 'sql';

export interface CodeChunk {
  id: string;
  filePath: string;
  name: string;
  type: ChunkType;
  content: string;
  startLine: number;
  endLine: number;
  parentName?: string;
  language: Language;
  metadata?: Record<string, string>;
}

export interface IndexMetadata {
  lastIndexedCommit: string;
  lastIndexedAt: string;
  totalChunks: number;
  embeddingModel: string;
  embeddingDimensions: number;
  fileCount: Record<Language, number>;
}
