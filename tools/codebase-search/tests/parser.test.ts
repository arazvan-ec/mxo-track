import { describe, it, expect } from 'vitest';
import { MarkdownParser } from '../src/parser/markdown-parser.js';

describe('MarkdownParser', () => {
  const parser = new MarkdownParser();

  it('should have .md extension', () => {
    expect(parser.extensions).toContain('.md');
  });

  it('should split content by ## headings', () => {
    const content = `# Main Title

Some intro text.

## Section One

Content of section one.

## Section Two

Content of section two.
`;
    const chunks = parser.parse('docs/test.md', content);
    expect(chunks.length).toBeGreaterThanOrEqual(2);

    const sectionOne = chunks.find(c => c.name === 'Section One');
    expect(sectionOne).toBeDefined();
    expect(sectionOne!.type).toBe('section');
    expect(sectionOne!.language).toBe('markdown');
    expect(sectionOne!.content).toContain('Content of section one');

    const sectionTwo = chunks.find(c => c.name === 'Section Two');
    expect(sectionTwo).toBeDefined();
    expect(sectionTwo!.content).toContain('Content of section two');
  });

  it('should have correct startLine and endLine', () => {
    const content = `## First

Line 2
Line 3

## Second

Line 7
`;
    const chunks = parser.parse('docs/test.md', content);
    const first = chunks.find(c => c.name === 'First');
    expect(first).toBeDefined();
    expect(first!.startLine).toBe(1);

    const second = chunks.find(c => c.name === 'Second');
    expect(second).toBeDefined();
    expect(second!.startLine).toBe(6);
  });

  it('should produce a single chunk for file with no headings', () => {
    const content = `Just some plain text
without any headings.
Multiple lines.
`;
    const chunks = parser.parse('docs/plain.md', content);
    expect(chunks).toHaveLength(1);
    expect(chunks[0].name).toBe('plain.md');
    expect(chunks[0].content).toContain('Just some plain text');
  });

  it('should handle nested headings (### inside ##)', () => {
    const content = `## Parent Section

Intro text.

### Child Section

Child content.

## Next Section

Next content.
`;
    const chunks = parser.parse('docs/nested.md', content);
    const parent = chunks.find(c => c.name === 'Parent Section');
    expect(parent).toBeDefined();
    // Parent section should include child content since ### is nested under ##
    expect(parent!.content).toContain('Child Section');
    expect(parent!.content).toContain('Child content');
  });

  it('should include file path in each chunk', () => {
    const content = `## Test Section\n\nContent.`;
    const chunks = parser.parse('docs/knowledge/test.md', content);
    expect(chunks[0].filePath).toBe('docs/knowledge/test.md');
  });
});
