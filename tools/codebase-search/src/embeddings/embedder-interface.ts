export interface Embedder {
  embed(texts: string[]): Promise<number[][]>;
  readonly dimensions: number;
  readonly modelId: string;
}
