# Spec — Verification `lint_clean=skipped` Smart Acceptance

**Date:** 2026-05-20
**Branch:** `claude/compare-claude-workflows-yrl2P`
**Type:** code change (full flow)
**Scope ref:** P3 of 3 — backlog item "verification-validator aceptación inteligente de lint_clean=skipped".

## Problem

`verification-validator.sh` rejects `lint_clean=skipped` in flow=full/debug, demanding `lint_clean=true`. In sandbox environments without `shellcheck` (Claude Code on the web standard), `make lint-shell` fails as infrastructure-missing, not as lint-dirty. The bypass `SKIP_PHASE_EXIT_GATE=1` with decision-log entry is used repeatedly. **5 documented occurrences** (2026-04-22, 2026-05-03, 2026-05-04, 2026-05-06, 2026-05-18) of the same rejection-with-bypass cycle.

The right structural fix: accept `skipped` automatically when the skip is **provably honest** — either the tool isn't available or the diff doesn't contain code the tool would inspect.

## Approach Chosen

Extend `verification-validator.sh` to auto-accept `lint_clean=skipped` in exactly **two scenarios**, propagating as ⚠ (not ✅) so PR reviewers still see the skip:

### Scenario 1: shellcheck missing

```bash
if ! command -v shellcheck >/dev/null 2>&1; then
  ACCEPT_SHELL_SKIP=1
  LINT_SKIP_REASON="shellcheck_missing"
fi
```

### Scenario 2: diff doesn't contain shell files

Reference is the **plan commit** (consistent with sync-validator). Use `git diff --name-only ${PLAN_COMMIT_PARENT}...HEAD` plus working tree files:

```bash
SHELL_FILES_TOUCHED=$(git diff --name-only "$PLAN_COMMIT_PARENT...HEAD" 2>/dev/null \
  | grep -E '\.(sh|bash)$' || true)
WT_SHELL=$(git status --porcelain | awk '{print $2}' | grep -E '\.(sh|bash)$' || true)
if [ -z "$SHELL_FILES_TOUCHED" ] && [ -z "$WT_SHELL" ]; then
  ACCEPT_SHELL_SKIP=1
  LINT_SKIP_REASON="no_shell_files_in_diff"
fi
```

### Validator logic

```
if lint_clean == "true":
  pass
elif lint_clean == "skipped":
  if ACCEPT_SHELL_SKIP:
    write evidence.lint_skip_reason
    pass with ⚠ propagation
  else:
    block (current behavior preserved for legit cases)
elif lint_clean in (false, null):
  block (current behavior preserved)
```

### Propagation as ⚠

`pre-push-gate.sh` and execution log YAML frontmatter should reflect `lint_skip_reason` so PR reviewers see explicit skip rationale, not silent acceptance.

## Maximal Version Considered

**Maximal version:** install `shellcheck` in the sandbox base image. **Rejected, not on cost** but on:

- **Pattern alignment:** the harness already treats "honest skip" as a distinct evidence state (`tests_passed="skipped"` for repos without test infra). Smart acceptance generalizes this pattern. Installing binaries is an ad-hoc per-tool fix.
- **Single source of truth:** the propagation chain (verification → pre-push-gate → PR reviewer) already exists for ⚠ states. Reusing it preserves consistency.
- **Independent superiority:** smart acceptance is **resilient to future tool gaps** (e.g., when `eslint`, `phpunit`, `mypy` are missing in some sandbox) — same logic generalizes. Installing shellcheck fixes 1 case; smart acceptance fixes the **class** of cases.

## Prior Art Audit

| File | Status | Coverage |
|---|---|---|
| `.claude/hooks/validators/verification-validator.sh` | ✅ Endorsed | Existing reject logic for `lint_clean=skipped`. **Control flow check:** validator returns early on `tests_passed` check before reaching `lint_clean`; new logic must run AFTER tests_passed succeeds, BEFORE lint_clean reject. Specific position documented in plan |
| `.claude/hooks/validators/sync-validator.sh` | ✅ Endorsed | Provides the "plan commit" reference logic. Reuse: extract helper `get_plan_commit_parent()` to `.claude/hooks/lib/git-refs.sh` (new) so both validators share it |
| `.claude/hooks/pre-push-gate.sh` | ✅ Endorsed | Existing ⚠ propagation logic; add `lint_skip_reason` extraction + display |
| `.claude/hooks/test-verification-validator.sh` | new | Does not exist; create with cases (a) shellcheck missing + no shell diff → accept, (b) shellcheck missing + shell file in diff → block, (c) shellcheck present + lint=skipped → block (no smart acceptance when honest skip isn't justified) |
| `.claude/hooks/lib/git-refs.sh` | new | Extracted helper; minimal lib file with 1 function |

## Existing Functionality Inventory

| Element | Decision | Justification |
|---|---|---|
| `lint_clean=true` accept | **Keep, unchanged** | Happy path preserved |
| `lint_clean=null` block | **Keep, unchanged** | Null is still invalid evidence |
| `lint_clean=false` block | **Keep, unchanged** | False is still failure |
| Existing reject of `skipped` | **Replace with conditional accept** | Auto-accept ONLY when scenarios 1 or 2 trigger; reject otherwise |
| Bypass `SKIP_PHASE_EXIT_GATE=1` | **Keep available** | Still works for cases not covered by smart acceptance (defense in depth) |
| `evidence.lint_skip_reason` field | **Add new** | Captures WHY the skip was accepted; enables audit |

## Omission Decisions

| Element | Decision | Justification |
|---|---|---|
| Generalize same logic to `tests_passed=skipped` | **Omit** | Out of scope; tests skip is already accepted with explicit string. Could be follow-up if friction documented |
| Auto-install shellcheck attempt | **Omit** | No sudo in sandbox; would fail silently |
| Track count of `lint_skip_reason` per repo to detect drift | **Omit** | Pattern-audit could surface if used 10+ times; defer |
| Distinguish `make lint` (PHP) skipped vs `make lint-shell` skipped | **Omit** | PHP lint always runs if composer installed; v1 assumes PHP lint always = `true`. If composer missing later → add second scenario |

## Norms

- `lint_clean=skipped` **must** be accepted automatically ONLY when scenario 1 OR scenario 2 conditions hold. Manual `skipped` without justification **is still rejected**.
- When accepted, `evidence.lint_skip_reason` **must** be set to one of `shellcheck_missing` or `no_shell_files_in_diff`. Setting `lint_skip_reason` without `lint_clean=skipped` is forbidden.
- The acceptance **must** propagate ⚠ (not ✅) through pre-push-gate to PR reviewer. Silent acceptance is forbidden.
- The plan commit reference **must** use the same logic as `sync-validator.sh` — DRY via shared helper in `lib/git-refs.sh`.

## Safeguards

| Risk | Mitigation |
|---|---|
| False positive: lint truly dirty but shellcheck happens to be missing | Scenario 1 requires NO `shellcheck` binary; if developer installs shellcheck locally and forgets to run, they'd still set lint_clean explicitly. Smart accept only triggers when binary genuinely absent |
| False positive: diff contains non-`*.sh` files that shellcheck would inspect (e.g., shebang in `*.bash` already covered; what about extensionless scripts?) | Detection regex `\.(sh|bash)$` covers 99% of cases. Extensionless scripts are rare in this repo (validated by `find . -type f ! -name "*.*" -exec head -1 {} \;` audit during impl); if found, extend regex |
| Sync-validator and verification-validator drift in plan-commit logic | Extracted to shared `lib/git-refs.sh` — single source of truth |
| `lint_skip_reason` leaks into commits if model forgets to reset | session-state.json is gitignored; the field lives only in evidence, never committed |
| Propagation to pre-push-gate misses a case | Test 4 covers full integration: skip accepted → pre-push-gate reads it → displays ⚠ |

## Verification

1. **Test 1:** shellcheck missing + diff contains 0 shell files → validator accepts (`pass`), `lint_skip_reason=no_shell_files_in_diff` (Scenario 2 wins as more specific).
2. **Test 2:** shellcheck missing + diff contains `*.sh` file → validator accepts (`pass`), `lint_skip_reason=shellcheck_missing`.
3. **Test 3:** shellcheck present (binary in PATH) + lint=skipped → validator **blocks** (manual skip with available tool is invalid).
4. **Test 4:** Integration — full flow with skip accepted, push triggered, pre-push-gate emits ⚠ with reason.
5. **Test 5:** plan_commit-parent reference returns same value as sync-validator (DRY assertion).
6. **Regression:** existing tests for `lint_clean=true` and `lint_clean=null` still pass.
