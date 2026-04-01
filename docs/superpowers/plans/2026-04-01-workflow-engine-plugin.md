# Plan — workflow-engine Claude Code Plugin

**Spec:** `docs/superpowers/specs/2026-04-01-workflow-engine-plugin-design.md`
**Target:** `plugin-export/workflow-engine/`

---

## Phase 1 (v0): Working plugin with hooks + validators + skills

### [parallel] Tarea 1a + 1b + 1c — Scaffold

- **1a:** Create `plugin-export/workflow-engine/.claude-plugin/plugin.json` with manifest, userConfig, component paths
- **1b:** Create `plugin-export/workflow-engine/hooks/hooks.json` with all hook event bindings
- **1c:** Create `plugin-export/workflow-engine/core/session-state-schema.json` + `workflow.defaults.json`

### [parallel] Tarea 2a + 2b — Core hooks (7 scripts)

- **2a:** Port session-start.sh, user-prompt-state.sh, workflow-engine.sh, pre-push-gate.sh — replace hardcoded paths with `git rev-parse`, add config reader helper
- **2b:** Port auto-evidence.sh, workflow-status-line.sh, post-commit-validator.sh — same path changes + configurable commands

### Tarea 3 — Port validators (10 scripts)

- Port all 10 validators from `.claude/hooks/validators/` to `plugin-export/workflow-engine/hooks/scripts/validators/`
- Replace hardcoded REPO paths, use config for spec/plan/execution-log paths

### [parallel] Tarea 4a + 4b — Skills + Agents

- **4a:** Create 9 skill SKILL.md files (classify, consult, brainstorm, plan, verify, capture, retrospective, finalize, deviate)
- **4b:** Create 2 agent .md files (task-executor, code-reviewer)

### Tarea 5 — Plugin CLAUDE.md + README.md + core/workflow-reference.md

- Extract generic workflow instructions from root CLAUDE.md into plugin CLAUDE.md
- Create README.md with installation/usage instructions
- Adapt `.claude/README.md` into `core/workflow-reference.md`

### Tarea 6 — Port tests

- Port 4 test scripts, update paths to be plugin-relative

### Tarea 7 — Commit and push

- Commit all files, push to branch

---

## Phase 2 (Mature): Refinements

- Add `workflow.json` schema validation
- Add `/workflow-engine:status` skill for on-demand status display
- Add `/workflow-engine:init` skill to generate workflow.json for new projects
- Consider publishing to a Claude Code marketplace
