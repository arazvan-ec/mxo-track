# workflow-engine

A Claude Code plugin that enforces structured development workflows with mechanical gates, evidence tracking, and session state persistence.

## What It Does

Forces every interaction through a structured flow:

- **Full flow** (features): consult → brainstorm → plan → implement → verify → capture → retrospective → finalize
- **Debug flow** (bugs): consult → root cause → pattern search → fix
- **Micro/Light/Explore** flows for questions, docs, and exploration

The engine uses hooks to **mechanically block** code edits until prerequisite phases are complete. No shortcuts, no skipping phases.

## Installation

### From GitHub

```bash
# Add the marketplace (if not already added)
/plugin marketplace add arazvan-ec/yader

# Install
/plugin install workflow-engine
```

### Local (for development)

```bash
claude --plugin-dir ./plugin-export/workflow-engine
```

### Project-scoped

Add to `.claude/settings.json`:
```json
{
  "plugins": [
    { "name": "workflow-engine", "path": "path/to/workflow-engine", "enabled": true }
  ]
}
```

## Configuration

Create `workflow.json` at your repo root (or `.claude/workflow.json`):

```json
{
  "src_paths": ["backend/src", "frontend/src"],
  "test_paths": ["backend/tests", "frontend/tests"],
  "test_command": "php vendor/bin/phpunit",
  "lint_command": "make lint",
  "specs_path": "docs/superpowers/specs",
  "plans_path": "docs/superpowers/plans",
  "execution_logs_path": "docs/superpowers/execution-logs",
  "decisions_log": "docs/decisions/log.md",
  "protected_paths": ["backend/src", "backend/tests", "frontend/src"],
  "commit_prefixes": "feat|fix|refactor|test|docs|chore"
}
```

All settings have defaults. Works zero-config for standard project layouts (`src/`, `tests/`, `docs/`).

## Skills

Invoke with `/workflow-engine:<skill>`:

| Skill | Description |
|-------|-------------|
| `classify` | Classify interaction type (micro/light/debug/full/explore) |
| `consult` | Read decision logs and execution logs |
| `brainstorm` | Explore, propose alternatives, write spec |
| `plan` | Create implementation plan with TDD tasks |
| `verify` | Run tests and linter |
| `capture` | Write execution log |
| `retrospective` | Update decision log |
| `finalize` | Declare branch strategy and close |
| `deviate` | Activate deviation mode for emergencies |

## Agents

| Agent | Description |
|-------|-------------|
| `task-executor` | Execute a single plan task following TDD |
| `code-reviewer` | Review changes for spec compliance + quality |

## How It Works

### Hooks

The plugin registers hooks on 4 Claude Code events:

- **SessionStart** — Initialize/reset session state, output context
- **UserPromptSubmit** — Inject workflow state into Claude's context
- **PreToolUse** (Edit/Write) — Gate file edits based on flow and phase
- **PreToolUse** (Bash) — Gate `git push` with completion checks
- **PostToolUse** — Auto-detect evidence, update status display, validate commits

### Gates

The workflow engine classifies each file edit and checks it against the current flow:

- **micro/light/explore** flows cannot edit source code (must reclassify to debug/full)
- **debug** flow requires root cause identification before code edits
- **full** flow requires consult + brainstorm + plan before code edits

### Evidence Tracking

Evidence is automatically detected from tool usage:
- Reading `docs/decisions/log.md` → sets `decisions_read`
- Writing to specs path → sets `spec_path`
- Running test command → sets `tests_passed`
- etc.

### Session State

`.claude/session-state.json` persists workflow state across context compactions. The SessionStart hook resets it daily but preserves a `last_work_summary` for continuity.

## License

MIT
