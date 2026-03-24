import { createHash } from 'node:crypto';
import { basename } from 'node:path';
import type { FileParser } from './parser-interface.js';
import type { CodeChunk } from '../indexer/types.js';

export class MarkdownParser implements FileParser {
  readonly extensions = ['.md'];

  parse(filePath: string, content: string): CodeChunk[] {
    const lines = content.split('\n');
    const sections: { name: string; startLine: number; lines: string[] }[] = [];
    let current: { name: string; startLine: number; lines: string[] } | null = null;

    for (let i = 0; i < lines.length; i++) {
      const line = lines[i];
      const match = line.match(/^## (.+)$/);

      if (match) {
        if (current) {
          sections.push(current);
        }
        current = { name: match[1].trim(), startLine: i + 1, lines: [line] };
      } else if (current) {
        current.lines.push(line);
      }
    }

    if (current) {
      sections.push(current);
    }

    // If no ## headings found, return entire file as one chunk
    if (sections.length === 0) {
      return [
        {
          id: this.chunkId(filePath, basename(filePath), 'section'),
          filePath,
          name: basename(filePath),
          type: 'section',
          content: content,
          startLine: 1,
          endLine: lines.length,
          language: 'markdown',
        },
      ];
    }

    return sections.map((section, idx) => {
      const endLine =
        idx < sections.length - 1
          ? sections[idx + 1].startLine - 1
          : lines.length;

      return {
        id: this.chunkId(filePath, section.name, 'section'),
        filePath,
        name: section.name,
        type: 'section' as const,
        content: section.lines.join('\n').trimEnd(),
        startLine: section.startLine,
        endLine,
        language: 'markdown' as const,
      };
    });
  }

  private chunkId(filePath: string, name: string, type: string): string {
    return createHash('sha256')
      .update(`${filePath}:${name}:${type}`)
      .digest('hex')
      .slice(0, 16);
  }
}
