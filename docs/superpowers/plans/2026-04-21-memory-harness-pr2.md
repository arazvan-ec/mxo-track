# Plan — 2026-04-21 — Memory/Harness PR2 (Surfacing + Audit + Outcome + Regressions + UA-Fix)

**Spec:** `docs/superpowers/specs/2026-04-21-memory-harness-pr2.md`
**Branch:** `claude/improve-keyboard-shortcuts-Pnrqv`
**Phase:** v0 directo (scope acotado, patrones establecidos en PR1)

---

## Parallelism analysis

File conflict matrix — tasks that edit the same file must be serialized:

| Task | Files touched |
|---|---|
| 1a pattern-audit.sh | `.claude/hooks/pattern-audit.sh` (new) |
| 1b mark-verified.sh | `scripts/mark-verified.sh` (new) |
| 1c link-regression.sh + template | `scripts/link-regression.sh` (new), `docs/superpowers/templates/execution-log-template.md` (edit) |
| 2 session-start.sh (surfacing + UA-fix) | `.claude/hooks/session-start.sh` (edit) |
| 3 hook wiring | `.claude/hooks/phase-advance.sh` (edit), `.claude/hooks/post-commit-validator.sh` (edit) |

Wave 1 tasks (1a, 1b, 1c) touch disjoint file sets → parallel.
Wave 2 is alone — session-start.sh gets both surfacing and UA-fix together to avoid merge conflict.
Wave 3 is alone — hook wiring depends on Wave 1 scripts existing.
Tests follow each wave.

---

## Phase 1: v0

### [parallel] Wave 1: Independent scripts + template edit

- **1a: pattern-audit.sh** (new)
  - Wrap `consult.sh stats`, parse lines with `⚠ PATTERN (≥3)`
  - Cross-check each tag against `docs/knowledge/*.md` (grep for the tag)
  - Output: "Candidate for graduation: `<tag>` (N logs, not in knowledge)"
  - Silent if all ≥3 tags are already in knowledge modules
  - Exit 0 always (never blocks, it's advisory)
  - → produces: script ready for Wave 3 hook wiring

- **1b: mark-verified.sh** (new)
  - Args: `<log-filename> [--force]`
  - Seteo idempotente de `outcome_verified_at: <today>` en el frontmatter
  - Refuse si outcome ≠ success (nada que verificar)
  - Refuse si ya tiene timestamp y no --force
  - → produces: manual outcome marking available

- **1c: link-regression.sh + template convention** (new + edit)
  - `link-regression.sh <new-log> <old-log>`: añade `<new-log>` al array `regressions_later` del old-log (idempotente)
  - Template edit: añadir comentario en sección Retrospective mostrando la convención `**Fixes previously:** `YYYY-MM-DD-xxx.md``
  - → produces: regression link tool + documented convention

**Commit 1:** `feat: add pattern-audit, mark-verified, link-regression scripts`
- Files: 3 new + 1 edit
- ~250 líneas

### Wave 2: session-start.sh extensions (needs Wave 1? No — independent)

Wave 2 is actually independent of Wave 1 scripts (doesn't call them). Could technically
parallelize with Wave 1, but since it's a single file edit and Wave 1 has 3 tasks, keeping
it after Wave 1 is cleaner for commit organization.

- **2a: Refactor `restore_approval_if_resumable()` function**
  - Extract existing same-day restore logic into reusable function
  - Preconditions: spec_path + plan_path exist + current_phase ∈ implementation..finalize
  - → produces: reusable function, same-day behavior unchanged

- **2b: Call restoration in new-day path**
  - After building new state with preserved `last_work_summary`, check if previous state qualifies for approval restoration, apply
  - → produces: user_approved survives new-day resume when work is mid-flow

- **2c: Surfacing — related past logs**
  - When branch != main, `git diff --name-only main...HEAD` → count + list
  - If count ≤5, loop each path calling `consult.sh --quiet file <path>` (dedupe by filename, top 3 per file, top 10 total)
  - Emit "Related past logs (N):" section in hook output
  - → produces: proactive context of relevant logs at session start

- **2d: Auto-verify merged logs (D2b)**
  - In same computation as `merged_branches`, for each merged branch:
    - Find log(s) whose body contains `**Branch:** \`<branch>\``
    - If log has `outcome: success` + `outcome_verified_at: null` + merge commit ≥3 days old, call `mark-verified.sh <log>`
  - Emit "Auto-verified N logs (merged ≥3d ago)" if any
  - → produces: outcome tracking closes automatically for stable merges

**Commit 2:** `feat: extend session-start with surfacing + approval restore + auto-verify`
- File: session-start.sh (~100 líneas added)

### Wave 3: Hook wiring (needs Wave 1)

- **3a: phase-advance.sh → pattern-audit trigger**
  - After successful `retrospective → finalize` transition, call `pattern-audit.sh` and emit output as advisory warning
  - Non-blocking (exit code ignored)
  - → produces: pattern alerts at finalize time

- **3b: post-commit-validator.sh → link-regression trigger**
  - On new execution-log commit, grep `**Fixes previously:** \`([^`]+)\`` in the log
  - If match, extract new-log filename + referenced old-log, invoke `link-regression.sh`
  - → produces: automatic bidirectional linking

**Commit 3:** `feat: wire pattern-audit to finalize + link-regression to post-commit`
- Files: phase-advance.sh, post-commit-validator.sh (edits, ~40 líneas each)

### Wave 4: Tests (needs all prior waves)

- **4a: test-pattern-audit.sh**
  - Fixtures: knowledge modules with/without tags, execution logs with tags
  - Assert: candidates emitted correctly, silent when graduated
- **4b: test-mark-verified.sh**
  - Fixtures: log with success/null-verified, log with non-success
  - Assert: idempotent, refuses non-success, --force works
- **4c: test-link-regression.sh**
  - Fixtures: old log + new log, simulate post-commit flow
  - Assert: regressions_later updated, idempotent
- **4d: test-session-start-extensions.sh**
  - Fixtures: session-state in different phases, spec+plan files
  - Assert: surfacing output, approval restoration, auto-verify

**Commit 4:** `test: add tests for PR2 scripts and session-start extensions`
- Files: 4 test scripts (~300 líneas total)

### Wave 5: Verification + push

- 5a: run all new tests
- 5b: regression test workflow-engine (ensure 23/29 unchanged, 6 pre-existing failures not worsened)
- 5c: make manifest
- 5d: capture + retrospective
- 5e: push

---

## Task count: 16 tareas, 5 waves, 4 commits

## Files affected
- **New:** `.claude/hooks/pattern-audit.sh`, `.claude/hooks/test-pattern-audit.sh`, `.claude/hooks/test-session-start-extensions.sh`, `scripts/mark-verified.sh`, `scripts/test-mark-verified.sh`, `scripts/link-regression.sh`, `scripts/test-link-regression.sh`
- **Modified:** `.claude/hooks/session-start.sh`, `.claude/hooks/phase-advance.sh`, `.claude/hooks/post-commit-validator.sh`, `docs/superpowers/templates/execution-log-template.md`

## Time estimate: 90-120 min

## Risk: Low-Medium
- Medium: `session-start.sh` is a critical hook — a bug breaks session context injection. Mitigation: refactor cleanly, tests cover each new branch
- Low: other scripts are isolated, git-revertible
