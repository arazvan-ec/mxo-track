export interface SearchResult {
  filePath: string;
  name: string;
  type: string;
  startLine: number;
  endLine: number;
  score: number;
  snippet: string;
  language: string;
  parentName?: string;
}

export interface SearchOptions {
  limit: number;
  language?: string;
  type?: string;
  minScore: number;
}
