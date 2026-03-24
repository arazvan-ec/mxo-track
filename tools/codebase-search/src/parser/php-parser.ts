import { createHash } from 'node:crypto';
import Parser from 'tree-sitter';
import PHP from 'tree-sitter-php';
import type { FileParser } from './parser-interface.js';
import type { CodeChunk, ChunkType } from '../indexer/types.js';

export class PhpParser implements FileParser {
  readonly extensions = ['.php'];
  private parser: Parser;

  constructor() {
    this.parser = new Parser();
    this.parser.setLanguage(PHP.php);
  }

  parse(filePath: string, content: string): CodeChunk[] {
    const tree = this.parser.parse(content);
    const chunks: CodeChunk[] = [];
    const namespace = this.extractNamespace(tree.rootNode);

    for (const node of tree.rootNode.children) {
      if (
        node.type === 'class_declaration' ||
        node.type === 'interface_declaration' ||
        node.type === 'trait_declaration'
      ) {
        const className = this.getNodeName(node);
        if (!className) continue;

        // Add class-level chunk
        chunks.push(this.createChunk(filePath, className, 'class', node, namespace));

        // Extract methods
        const bodyNode = node.childForFieldName('body') ?? this.findChild(node, 'declaration_list');
        if (bodyNode) {
          for (const member of bodyNode.children) {
            if (member.type === 'method_declaration') {
              const methodName = this.getNodeName(member);
              if (methodName) {
                chunks.push(
                  this.createChunk(filePath, methodName, 'method', member, namespace, className),
                );
              }
            }
          }
        }
      } else if (node.type === 'function_definition') {
        const funcName = this.getNodeName(node);
        if (funcName) {
          chunks.push(this.createChunk(filePath, funcName, 'function', node, namespace));
        }
      }
    }

    return chunks;
  }

  private extractNamespace(rootNode: Parser.SyntaxNode): string | undefined {
    for (const node of rootNode.children) {
      if (node.type === 'namespace_definition') {
        const nameNode = node.children.find(c => c.type === 'namespace_name');
        if (nameNode) {
          return nameNode.text;
        }
      }
    }
    return undefined;
  }

  private getNodeName(node: Parser.SyntaxNode): string | undefined {
    const nameNode = node.children.find(c => c.type === 'name');
    return nameNode?.text;
  }

  private findChild(node: Parser.SyntaxNode, type: string): Parser.SyntaxNode | null {
    return node.children.find(c => c.type === type) ?? null;
  }

  private createChunk(
    filePath: string,
    name: string,
    type: ChunkType,
    node: Parser.SyntaxNode,
    namespace?: string,
    parentName?: string,
  ): CodeChunk {
    const prefix = namespace ? `[php:${type}] ${namespace}\\${name}` : `[php:${type}] ${name}`;

    return {
      id: this.chunkId(filePath, name, type),
      filePath,
      name,
      type,
      content: `${prefix}\n${node.text}`,
      startLine: node.startPosition.row + 1,
      endLine: node.endPosition.row + 1,
      parentName,
      language: 'php',
      metadata: namespace ? { namespace } : undefined,
    };
  }

  private chunkId(filePath: string, name: string, type: string): string {
    return createHash('sha256')
      .update(`${filePath}:${name}:${type}`)
      .digest('hex')
      .slice(0, 16);
  }
}
