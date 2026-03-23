import { describe, it, expect } from 'vitest';
import { MarkdownParser } from '../src/parser/markdown-parser.js';
import { PhpParser } from '../src/parser/php-parser.js';
import { YamlParser } from '../src/parser/yaml-parser.js';
import { TwigParser } from '../src/parser/twig-parser.js';
import { SqlParser } from '../src/parser/sql-parser.js';

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

describe('YamlParser', () => {
  const parser = new YamlParser();

  it('should have .yaml and .yml extensions', () => {
    expect(parser.extensions).toContain('.yaml');
    expect(parser.extensions).toContain('.yml');
  });

  it('should split by top-level keys', () => {
    const content = `framework:
    secret: '%env(APP_SECRET)%'
    http_method_override: false

doctrine:
    dbal:
        url: '%env(DATABASE_URL)%'
    orm:
        auto_generate_proxy_classes: true
`;
    const chunks = parser.parse('config/packages/doctrine.yaml', content);
    expect(chunks.length).toBeGreaterThanOrEqual(2);

    const framework = chunks.find(c => c.name === 'framework');
    expect(framework).toBeDefined();
    expect(framework!.type).toBe('config');
    expect(framework!.language).toBe('yaml');

    const doctrine = chunks.find(c => c.name === 'doctrine');
    expect(doctrine).toBeDefined();
    expect(doctrine!.content).toContain('dbal');
  });

  it('should handle file with single key', () => {
    const content = `services:
    _defaults:
        autowire: true
`;
    const chunks = parser.parse('config/services.yaml', content);
    expect(chunks).toHaveLength(1);
    expect(chunks[0].name).toBe('services');
  });
});

describe('TwigParser', () => {
  const parser = new TwigParser();

  it('should have .twig and .html.twig extensions', () => {
    expect(parser.extensions).toContain('.twig');
    expect(parser.extensions).toContain('.html.twig');
  });

  it('should extract block chunks', () => {
    const content = `{% extends 'base.html.twig' %}

{% block title %}My Page{% endblock %}

{% block body %}
<div class="container">
    <h1>Hello</h1>
</div>
{% endblock %}
`;
    const chunks = parser.parse('templates/page.html.twig', content);
    const blocks = chunks.filter(c => c.type === 'block');
    expect(blocks.length).toBeGreaterThanOrEqual(2);

    const bodyBlock = blocks.find(c => c.name === 'body');
    expect(bodyBlock).toBeDefined();
    expect(bodyBlock!.content).toContain('container');
  });

  it('should extract macro chunks', () => {
    const content = `{% macro input(name, value, type) %}
    <input type="{{ type }}" name="{{ name }}" value="{{ value }}">
{% endmacro %}
`;
    const chunks = parser.parse('templates/macros.html.twig', content);
    const macros = chunks.filter(c => c.type === 'block');
    expect(macros).toHaveLength(1);
    expect(macros[0].name).toBe('macro:input');
  });

  it('should return whole file if no blocks or macros', () => {
    const content = `<div>Just plain HTML with {{ variable }}</div>`;
    const chunks = parser.parse('templates/plain.html.twig', content);
    expect(chunks).toHaveLength(1);
    expect(chunks[0].name).toBe('plain.html.twig');
  });
});

describe('SqlParser', () => {
  const parser = new SqlParser();

  it('should have .php extension (for Doctrine migrations)', () => {
    expect(parser.extensions).toContain('.php');
  });

  it('should extract addSql statements from Doctrine migration', () => {
    const content = `<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\\DBAL\\Schema\\Schema;
use Doctrine\\Migrations\\AbstractMigration;

final class Version20240101000000 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE vehicle (id SERIAL PRIMARY KEY, name VARCHAR(255))');
        $this->addSql('CREATE TABLE driver (id SERIAL PRIMARY KEY, name VARCHAR(255))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE driver');
        $this->addSql('DROP TABLE vehicle');
    }
}
`;
    const chunks = parser.parse('migrations/Version20240101000000.php', content);
    expect(chunks.length).toBeGreaterThanOrEqual(2);
    expect(chunks[0].type).toBe('migration');
    expect(chunks[0].language).toBe('sql');
    expect(chunks[0].content).toContain('CREATE TABLE vehicle');
  });

  it('should return empty for non-migration PHP file', () => {
    const content = `<?php

class NotAMigration {
    public function doStuff(): void {}
}
`;
    const chunks = parser.parse('src/Service/Foo.php', content);
    expect(chunks).toHaveLength(0);
  });
});
