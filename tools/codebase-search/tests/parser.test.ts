import { describe, it, expect } from 'vitest';
import { MarkdownParser } from '../src/parser/markdown-parser.js';
import { PhpParser } from '../src/parser/php-parser.js';

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

describe('PhpParser', () => {
  const parser = new PhpParser();

  it('should have .php extension', () => {
    expect(parser.extensions).toContain('.php');
  });

  it('should extract class declaration as chunk', () => {
    const content = `<?php

namespace App\\Entity;

class Vehicle {
    private string $name;
}
`;
    const chunks = parser.parse('src/Entity/Vehicle.php', content);
    const classChunk = chunks.find(c => c.type === 'class');
    expect(classChunk).toBeDefined();
    expect(classChunk!.name).toBe('Vehicle');
    expect(classChunk!.language).toBe('php');
    expect(classChunk!.content).toContain('class Vehicle');
  });

  it('should extract each method as separate chunk with parentName', () => {
    const content = `<?php

class UserService {
    public function create(): void {}
    public function delete(): void {}
}
`;
    const chunks = parser.parse('src/Service/UserService.php', content);
    const methods = chunks.filter(c => c.type === 'method');
    expect(methods).toHaveLength(2);

    const createMethod = methods.find(c => c.name === 'create');
    expect(createMethod).toBeDefined();
    expect(createMethod!.parentName).toBe('UserService');

    const deleteMethod = methods.find(c => c.name === 'delete');
    expect(deleteMethod).toBeDefined();
    expect(deleteMethod!.parentName).toBe('UserService');
  });

  it('should extract standalone functions', () => {
    const content = `<?php

function helperFunction(): string {
    return 'hello';
}
`;
    const chunks = parser.parse('src/helpers.php', content);
    const func = chunks.find(c => c.type === 'function');
    expect(func).toBeDefined();
    expect(func!.name).toBe('helperFunction');
    expect(func!.parentName).toBeUndefined();
  });

  it('should include namespace in metadata', () => {
    const content = `<?php

namespace App\\Service;

class RouteService {}
`;
    const chunks = parser.parse('src/Service/RouteService.php', content);
    const classChunk = chunks.find(c => c.type === 'class');
    expect(classChunk).toBeDefined();
    expect(classChunk!.metadata?.namespace).toBe('App\\Service');
  });

  it('should handle file with multiple classes', () => {
    const content = `<?php

class First {}
class Second {}
`;
    const chunks = parser.parse('src/multi.php', content);
    const classes = chunks.filter(c => c.type === 'class');
    expect(classes).toHaveLength(2);
    expect(classes.map(c => c.name).sort()).toEqual(['First', 'Second']);
  });

  it('should handle interfaces and traits', () => {
    const content = `<?php

interface Searchable {
    public function search(): array;
}

trait Timestampable {
    private \\DateTimeInterface $createdAt;
}
`;
    const chunks = parser.parse('src/Contracts.php', content);
    const classLike = chunks.filter(c => c.type === 'class');
    expect(classLike).toHaveLength(2);
    expect(classLike.map(c => c.name).sort()).toEqual(['Searchable', 'Timestampable']);
  });
});
