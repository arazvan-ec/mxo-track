import { execSync } from 'node:child_process';

export interface FileChanges {
  added: string[];
  modified: string[];
  deleted: string[];
}

export function getCurrentCommit(projectRoot: string): string {
  return execSync('git rev-parse HEAD', { cwd: projectRoot, encoding: 'utf-8' }).trim();
}

export function getChangedFiles(
  lastCommit: string,
  projectRoot: string,
): FileChanges {
  const result: FileChanges = { added: [], modified: [], deleted: [] };

  try {
    const output = execSync(`git diff --name-status ${lastCommit} HEAD`, {
      cwd: projectRoot,
      encoding: 'utf-8',
    });

    for (const line of output.split('\n')) {
      if (!line.trim()) continue;
      const [status, ...pathParts] = line.split('\t');
      const filePath = pathParts.join('\t');

      switch (status) {
        case 'A':
          result.added.push(filePath);
          break;
        case 'M':
          result.modified.push(filePath);
          break;
        case 'D':
          result.deleted.push(filePath);
          break;
        default:
          // Renames (R100), copies (C), etc. — treat as added
          if (status.startsWith('R') || status.startsWith('C')) {
            // pathParts[0] is old path, pathParts[1] is new path
            if (pathParts.length >= 2) {
              result.deleted.push(pathParts[0]);
              result.added.push(pathParts[1]);
            }
          }
      }
    }
  } catch {
    // If git diff fails, return empty (caller should fall back to full index)
    return { added: [], modified: [], deleted: [] };
  }

  return result;
}
