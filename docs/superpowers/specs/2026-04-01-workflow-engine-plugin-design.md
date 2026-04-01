# Spec — workflow-engine Claude Code Plugin

**Date:** 2026-04-01
**Type:** Plugin packaging (full code change)
**Branch:** `claude/package-workflow-plugin-jLMCc`

---

## Problem

The workflow engine (8 hooks, 10 validators, session-state, behavioral instructions in CLAUDE.md) is hardcoded to mxo-track with absolute paths (`/home/user/mxo-track`). It cannot be reused in other projects. The engine should be a standalone Claude Code plugin, installable anywhere.

## Existing Functionality Inventory

| Element | Decision | Justification |
|---|---|---|
| `session-start.sh` | Transform | Dynamic paths, configurable dirs |
| `user-prompt-state.sh` | Transform | Dynamic paths |
| `workflow-engine.sh` | Transform | Dynamic paths, configurable src/test patterns |
| `pre-push-gate.sh` | Transform | Dynamic paths, configurable protected paths |
| `auto-evidence.sh` | Transform | Dynamic paths, configurable test/lint commands |
| `workflow-status-line.sh` | Transform | Dynamic paths |
| `post-commit-validator.sh` | Transform | Dynamic paths, configurable exec-log dir |
| `post-push-validator.sh` | Transform | Dynamic paths |
| `workflow-status.sh` | Omit | Duplicate of workflow-status-line.sh |
| 10 validators in `validators/` | Transform | Dynamic paths |
| `session-state.json` schema | Include | As-is in core/ |
| `.claude/README.md` | Transform | Becomes core/workflow-reference.md |
| Root CLAUDE.md workflow sections | Transform | Becomes plugin CLAUDE.md (generic only) |
| 4 test scripts | Transform | Dynamic paths |
| `multi-agent-workflow` plugin | Omit | Already deleted per user request |

## Omission Decisions

| Element | Decision | Justification |
|---|---|---|
| `workflow-status.sh` | Omit | Superseded by `workflow-status-line.sh` |
| `settings.json` plan-copy hook | Omit | Project-specific (copies from /root/.claude/plans/) |
| `post-push-validator.sh` | Omit | If it only does `make manifest` — project-specific |
| mxo-track CLAUDE.md sections (tech stack, knowledge modules, entity model) | Omit | Project-specific, stays in project CLAUDE.md |

---

## Design

### Plugin Structure

```
plugin-export/workflow-engine/
├── .claude-plugin/
│   └── plugin.json
├── CLAUDE.md                          # Behavioral instructions (generic workflow)
├── hooks/
│   ├── hooks.json                     # Hook event bindings
│   └── scripts/
│       ├── session-start.sh
│       ├── user-prompt-state.sh
│       ├── workflow-engine.sh
│       ├── pre-push-gate.sh
│       ├── auto-evidence.sh
│       ├── workflow-status-line.sh
│       ├── post-commit-validator.sh
│       └── validators/
│           ├── brainstorm-validator.sh
│           ├── capture-validator.sh
│           ├── consult-validator.sh
│           ├── debug-validator.sh
│           ├── finalize-validator.sh
│           ├── implementation-validator.sh
│           ├── planning-validator.sh
│           ├── retrospective-validator.sh
│           ├── spec-compliance-validator.sh
│           └── verification-validator.sh
├── skills/
│   ├── classify/SKILL.md
│   ├── consult/SKILL.md
│   ├── brainstorm/SKILL.md
│   ├── plan/SKILL.md
│   ├── verify/SKILL.md
│   ├── capture/SKILL.md
│   ├── retrospective/SKILL.md
│   ├── finalize/SKILL.md
│   └── deviate/SKILL.md
├── agents/
│   ├── task-executor.md
│   └── code-reviewer.md
├── core/
│   ├── workflow-reference.md
│   └── session-state-schema.json
├── tests/
│   ├── test-workflow-engine.sh
│   ├── test-auto-evidence.sh
│   ├── test-status-line.sh
│   └── test-self-gating.sh
└── README.md
```

### Path Resolution

All hooks replace `REPO="/home/user/mxo-track"` with:

```bash
REPO=$(git rev-parse --show-toplevel 2>/dev/null || pwd)
```

Plugin-relative paths use:

```bash
PLUGIN_DIR="$(cd "$(dirname "$0")/.." && pwd)"
# or for scripts in scripts/:
PLUGIN_DIR="$(cd "$(dirname "$0")/../.." && pwd)"
```

### Configuration via workflow.json

Each project creates `workflow.json` at repo root (or `.claude/workflow.json`):

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
  "commit_prefixes": ["feat", "fix", "refactor", "test", "docs", "chore"]
}
```

Hooks read this config with fallback defaults:

```bash
CONFIG_FILE="$REPO/workflow.json"
if [ ! -f "$CONFIG_FILE" ]; then
  CONFIG_FILE="$REPO/.claude/workflow.json"
fi

read_config() {
  local key="$1" default="$2"
  if [ -f "$CONFIG_FILE" ]; then
    jq -r --arg k "$key" --arg d "$default" '.[$k] // $d' "$CONFIG_FILE" 2>/dev/null || echo "$default"
  else
    echo "$default"
  fi
}
```

Defaults match mxo-track conventions so it works zero-config in this repo.

### State File Location

`$REPO/.claude/session-state.json` — same as now. The plugin creates it on SessionStart if missing.

`$REPO/.claude/workflow-status-line.txt` — generated display file, same location.

### Skills Design

Each skill is a SKILL.md with instructions for that phase. Invocable as `/workflow-engine:classify`, `/workflow-engine:brainstorm`, etc.

Skills update session-state.json as part of their execution, ensuring evidence fields are set correctly.

| Skill | Invocation | Does |
|---|---|---|
| classify | `/workflow-engine:classify` | Classify interaction type, set flow_type |
| consult | `/workflow-engine:consult` | Read decision log + execution logs |
| brainstorm | `/workflow-engine:brainstorm` | Inventory, alternatives, spec writing |
| plan | `/workflow-engine:plan` | Write v0→mature plan with TDD tasks |
| verify | `/workflow-engine:verify` | Run tests + lint, update evidence |
| capture | `/workflow-engine:capture` | Write execution log |
| retrospective | `/workflow-engine:retrospective` | Update decision log |
| finalize | `/workflow-engine:finalize` | Branch strategy, cleanup |
| deviate | `/workflow-engine:deviate` | Activate deviation mode |

### Agents Design

| Agent | Purpose |
|---|---|
| task-executor | Execute a single task from a plan (TDD cycle) |
| code-reviewer | Review code before merge (spec compliance + quality) |

### Plugin CLAUDE.md

Contains ONLY generic workflow instructions:
- Flow classification table (micro/light/debug/full/explore)
- The 8 phases and what each produces
- Evidence-before-claims principle
- Display rules (progress headers, emoji prefixes)
- Commit conventions (when to commit/push, format)
- Session-state management
- Deviation mode
- Context hygiene

Does NOT contain: tech stack, entity model, knowledge modules, deployment, or any project-specific content.

---

## Risks

1. **Hook paths in hooks.json** — Plugin hooks use relative paths from plugin root. Need to verify Claude Code resolves `${CLAUDE_PLUGIN_ROOT}` correctly.
2. **Config file discovery** — If `workflow.json` is missing, defaults must be sensible. Using `src/` and `tests/` as universal defaults.
3. **State file conflicts** — If a project already has `.claude/session-state.json` from a manual setup, the plugin should respect it, not overwrite.
