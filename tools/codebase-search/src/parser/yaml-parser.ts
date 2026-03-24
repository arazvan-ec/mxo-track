import { createHash } from 'node:crypto';
import type { FileParser } from './parser-interface.js';
import type { CodeChunk } from '../indexer/types.js';

export class YamlParser implements FileParser {
  readonly extensions = ['.yaml', '.yml'];

  parse(filePath: string, content: string): CodeChunk[] {
    const lines = content.split('\n');
    const sections: { name: string; startLine: number; lines: string[] }[] = [];
    let current: { name: string; startLine: number; lines: string[] } | null = null;

    for (let i = 0; i < lines.length; i++) {
      const line = lines[i];
      // Top-level key: starts at column 0, no leading whitespace, ends with ':'
      const match = line.match(/^(\w[\w.-]*)\s*:/);

      if (match && !line.startsWith(' ') && !line.startsWith('\t')) {
        if (current) {
          sections.push(current);
        }
        current = { name: match[1], startLine: i + 1, lines: [line] };
      } else if (current) {
        current.lines.push(line);
      }
    }

    if (current) {
      sections.push(current);
    }

    if (sections.length === 0) {
      return [];
    }

    return sections.map((section, idx) => {
      const endLine =
        idx < sections.length - 1
          ? sections[idx + 1].startLine - 1
          : lines.length;

      return {
        id: this.chunkId(filePath, section.name, 'config'),
        filePath,
        name: section.name,
        type: 'config' as const,
        content: section.lines.join('\n').trimEnd(),
        startLine: section.startLine,
        endLine,
        language: 'yaml' as const,
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
