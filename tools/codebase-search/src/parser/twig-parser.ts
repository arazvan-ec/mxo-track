import { createHash } from 'node:crypto';
import { basename } from 'node:path';
import type { FileParser } from './parser-interface.js';
import type { CodeChunk } from '../indexer/types.js';

export class TwigParser implements FileParser {
  readonly extensions = ['.twig', '.html.twig'];

  parse(filePath: string, content: string): CodeChunk[] {
    const chunks: CodeChunk[] = [];
    const lines = content.split('\n');

    // Extract {% block name %} ... {% endblock %}
    const blockRegex = /\{%[-\s]*block\s+(\w+)\s*[-]?%\}/g;
    const endBlockRegex = /\{%[-\s]*endblock\s*[-]?%\}/g;

    let match: RegExpExecArray | null;
    while ((match = blockRegex.exec(content)) !== null) {
      const blockName = match[1];
      const startOffset = match.index;
      const startLine = this.offsetToLine(content, startOffset);

      // Find matching endblock
      endBlockRegex.lastIndex = match.index + match[0].length;
      const endMatch = endBlockRegex.exec(content);
      if (endMatch) {
        const endOffset = endMatch.index + endMatch[0].length;
        const endLine = this.offsetToLine(content, endOffset);
        const blockContent = content.slice(startOffset, endOffset);

        chunks.push({
          id: this.chunkId(filePath, blockName, 'block'),
          filePath,
          name: blockName,
          type: 'block',
          content: blockContent,
          startLine,
          endLine,
          language: 'twig',
        });
      }
    }

    // Extract {% macro name(...) %} ... {% endmacro %}
    const macroRegex = /\{%[-\s]*macro\s+(\w+)\s*\([^)]*\)\s*[-]?%\}/g;
    const endMacroRegex = /\{%[-\s]*endmacro\s*[-]?%\}/g;

    while ((match = macroRegex.exec(content)) !== null) {
      const macroName = match[1];
      const startOffset = match.index;
      const startLine = this.offsetToLine(content, startOffset);

      endMacroRegex.lastIndex = match.index + match[0].length;
      const endMatch = endMacroRegex.exec(content);
      if (endMatch) {
        const endOffset = endMatch.index + endMatch[0].length;
        const endLine = this.offsetToLine(content, endOffset);
        const macroContent = content.slice(startOffset, endOffset);

        chunks.push({
          id: this.chunkId(filePath, `macro:${macroName}`, 'block'),
          filePath,
          name: `macro:${macroName}`,
          type: 'block',
          content: macroContent,
          startLine,
          endLine,
          language: 'twig',
        });
      }
    }

    // If no blocks or macros found, return whole file as single chunk
    if (chunks.length === 0) {
      chunks.push({
        id: this.chunkId(filePath, basename(filePath), 'block'),
        filePath,
        name: basename(filePath),
        type: 'block',
        content,
        startLine: 1,
        endLine: lines.length,
        language: 'twig',
      });
    }

    return chunks;
  }

  private offsetToLine(content: string, offset: number): number {
    let line = 1;
    for (let i = 0; i < offset && i < content.length; i++) {
      if (content[i] === '\n') line++;
    }
    return line;
  }

  private chunkId(filePath: string, name: string, type: string): string {
    return createHash('sha256')
      .update(`${filePath}:${name}:${type}`)
      .digest('hex')
      .slice(0, 16);
  }
}
