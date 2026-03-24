import { describe, it, expect } from 'vitest';
import { GitManager } from '../src/git/git-manager.js';

describe('GitManager', () => {
  describe('parseDiffOutput', () => {
    const manager = new GitManager('https://example.com/repo.git', '/tmp/test');

    it('parses added files', () => {
      const output = 'A\tsrc/new-file.ts\n';
      const result = manager.parseDiffOutput(output);
      expect(result.changed).toEqual(['src/new-file.ts']);
      expect(result.deleted).toEqual([]);
    });

    it('parses modified files', () => {
      const output = 'M\tsrc/existing.ts\n';
      const result = manager.parseDiffOutput(output);
      expect(result.changed).toEqual(['src/existing.ts']);
      expect(result.deleted).toEqual([]);
    });

    it('parses deleted files', () => {
      const output = 'D\tsrc/removed.ts\n';
      const result = manager.parseDiffOutput(output);
      expect(result.changed).toEqual([]);
      expect(result.deleted).toEqual(['src/removed.ts']);
    });

    it('parses mixed changes', () => {
      const output = [
        'A\tsrc/new.ts',
        'M\tsrc/modified.ts',
        'D\tsrc/deleted.ts',
        'A\tdocs/readme.md',
      ].join('\n');
      const result = manager.parseDiffOutput(output);
      expect(result.changed).toEqual(['src/new.ts', 'src/modified.ts', 'docs/readme.md']);
      expect(result.deleted).toEqual(['src/deleted.ts']);
    });

    it('handles empty output', () => {
      const result = manager.parseDiffOutput('');
      expect(result.changed).toEqual([]);
      expect(result.deleted).toEqual([]);
    });

    it('handles renamed files (R status)', () => {
      const output = 'R100\told-name.ts\tnew-name.ts\n';
      const result = manager.parseDiffOutput(output);
      // Renamed files show up as changed (the new path)
      expect(result.changed.length).toBe(1);
      expect(result.deleted).toEqual([]);
    });
  });
});
