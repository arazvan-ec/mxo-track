import type { FileParser } from './parser-interface.js';
import { PhpParser } from './php-parser.js';
import { MarkdownParser } from './markdown-parser.js';
import { YamlParser } from './yaml-parser.js';
import { TwigParser } from './twig-parser.js';

export class ParserRegistry {
  private parsers: FileParser[];

  constructor() {
    this.parsers = [
      new TwigParser(), // Must come before generic extensions — .html.twig is more specific
      new PhpParser(),
      new MarkdownParser(),
      new YamlParser(),
    ];
  }

  getParser(filePath: string): FileParser | null {
    // Check longest extensions first (e.g., .html.twig before .twig)
    for (const parser of this.parsers) {
      for (const ext of parser.extensions) {
        if (filePath.endsWith(ext)) {
          return parser;
        }
      }
    }
    return null;
  }
}
