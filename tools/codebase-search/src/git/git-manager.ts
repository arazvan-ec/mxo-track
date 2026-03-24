import { execFile } from 'node:child_process';
import { join } from 'node:path';
import { access } from 'node:fs/promises';
import { promisify } from 'node:util';

const execFileAsync = promisify(execFile);

export class GitManager {
  private repoPath: string;

  constructor(
    private repoUrl: string,
    private dataDir: string,
  ) {
    this.repoPath = join(dataDir, 'repo');
  }

  async ensureRepo(): Promise<string> {
    // Disable SSL verification for environments missing CA certificates (e.g. slim containers)
    const env = { ...process.env, GIT_SSL_NO_VERIFY: 'true' };

    try {
      await access(join(this.repoPath, '.git'));
      // Repo exists, pull latest
      await execFileAsync('git', ['pull', 'origin', 'main'], { cwd: this.repoPath, env });
    } catch {
      // Clone fresh (shallow for speed)
      await execFileAsync('git', ['clone', '--depth=1', this.repoUrl, this.repoPath], { env });
    }
    return this.repoPath;
  }

  async pullAndDiff(): Promise<{ changed: string[]; deleted: string[] }> {
    const env = { ...process.env, GIT_SSL_NO_VERIFY: 'true' };

    const { stdout: beforeHead } = await execFileAsync('git', ['rev-parse', 'HEAD'], {
      cwd: this.repoPath,
    });

    await execFileAsync('git', ['fetch', 'origin', 'main'], { cwd: this.repoPath, env });
    await execFileAsync('git', ['reset', '--hard', 'origin/main'], { cwd: this.repoPath });

    const { stdout: afterHead } = await execFileAsync('git', ['rev-parse', 'HEAD'], {
      cwd: this.repoPath,
    });

    if (beforeHead.trim() === afterHead.trim()) {
      return { changed: [], deleted: [] };
    }

    const { stdout } = await execFileAsync(
      'git',
      ['diff', '--name-status', beforeHead.trim(), afterHead.trim()],
      { cwd: this.repoPath },
    );

    return this.parseDiffOutput(stdout);
  }

  parseDiffOutput(stdout: string): { changed: string[]; deleted: string[] } {
    const changed: string[] = [];
    const deleted: string[] = [];

    for (const line of stdout.split('\n').filter(Boolean)) {
      const [status, ...fileParts] = line.split('\t');
      const file = fileParts.join('\t');
      if (status === 'D') {
        deleted.push(file);
      } else {
        changed.push(file);
      }
    }

    return { changed, deleted };
  }

  getRepoPath(): string {
    return this.repoPath;
  }
}
