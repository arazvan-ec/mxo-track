import { createHash } from 'node:crypto';
import type { FileParser } from './parser-interface.js';
import type { CodeChunk } from '../indexer/types.js';

export class SqlParser implements FileParser {
  readonly extensions = ['.php'];

  parse(filePath: string, content: string): CodeChunk[] {
    // Only parse Doctrine migration files
    if (!content.includes('AbstractMigration') && !content.includes('addSql')) {
      return [];
    }

    const chunks: CodeChunk[] = [];
    const lines = content.split('\n');

    // Extract $this->addSql('...') statements
    const addSqlRegex = /\$this->addSql\(\s*'((?:[^'\\]|\\.)*)'\s*\)/g;
    let match: RegExpExecArray | null;
    let sqlIndex = 0;

    while ((match = addSqlRegex.exec(content)) !== null) {
      const sql = match[1].replace(/\\'/g, "'");
      const startLine = this.offsetToLine(content, match.index);
      const endLine = this.offsetToLine(content, match.index + match[0].length);

      // Derive a name from the SQL statement
      const name = this.deriveSqlName(sql, sqlIndex);

      chunks.push({
        id: this.chunkId(filePath, name, 'migration'),
        filePath,
        name,
        type: 'migration',
        content: sql,
        startLine,
        endLine,
        language: 'sql',
      });

      sqlIndex++;
    }

    return chunks;
  }

  private deriveSqlName(sql: string, index: number): string {
    const upper = sql.trim().toUpperCase();
    if (upper.startsWith('CREATE TABLE')) {
      const tableMatch = sql.match(/CREATE TABLE\s+(?:IF NOT EXISTS\s+)?(\w+)/i);
      return tableMatch ? `CREATE TABLE ${tableMatch[1]}` : `sql_${index}`;
    }
    if (upper.startsWith('ALTER TABLE')) {
      const tableMatch = sql.match(/ALTER TABLE\s+(\w+)/i);
      return tableMatch ? `ALTER TABLE ${tableMatch[1]}` : `sql_${index}`;
    }
    if (upper.startsWith('DROP TABLE')) {
      const tableMatch = sql.match(/DROP TABLE\s+(?:IF EXISTS\s+)?(\w+)/i);
      return tableMatch ? `DROP TABLE ${tableMatch[1]}` : `sql_${index}`;
    }
    if (upper.startsWith('CREATE INDEX') || upper.startsWith('CREATE UNIQUE INDEX')) {
      const idxMatch = sql.match(/CREATE\s+(?:UNIQUE\s+)?INDEX\s+(\w+)/i);
      return idxMatch ? `CREATE INDEX ${idxMatch[1]}` : `sql_${index}`;
    }
    return `sql_${index}`;
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
