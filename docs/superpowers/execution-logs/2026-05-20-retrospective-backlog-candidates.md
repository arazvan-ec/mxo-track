---
type: feature
tags: [harness, retrospective, backlog-candidates, hard-gate, governance]
files_touched:
  - docs/CLAUDE.md
  - .claude/hooks/validators/retrospective-validator.sh
  - .claude/hooks/test-retrospective-validator.sh
patterns:
  - always-present-checklist-item
  - retro-to-backlog-handoff
  - em-dash-encoding-portability
outcome: success
outcome_verified_at: null
regressions_later: []
pr_number: null
estimated_lines: 65
actual_lines: 90
duration_minutes: null
consulted_in_future: []
---

# Execution Log — 2026-05-20 — Retrospective Auto-Propose Backlog Candidates (P2 of 3)

## Spec / Plan
- Spec: `docs/superpowers/specs/2026-05-20-retrospective-backlog-candidates-design.md`
- Plan: `docs/superpowers/plans/2026-05-20-retrospective-backlog-candidates.md`

## Brainstorming
- **Alternatives:** A (CLAUDE.md rule + validator HARD + model heuristic — all three), B (CLAUDE.md only), C (validator only). Chose A. User chose HARD over WARNING.
- **Complexity estimate:** ~55 lines (rule + validator + 4 test cases).

## Planning
- TDD red (4 new test cases) → CLAUDE.md edit + validator extension → verification.

## Implementation
- **`docs/CLAUDE.md`:** added new "Retrospective Visibility Rule" section with 4th obligatory point ("Backlog candidates analysis") + heuristic for model to ask before phase-advance finalize. Renumbered prior 3 points unaffected; the new section also covers the "always present, 0 candidates is valid" pattern.
- **`retrospective-validator.sh`:** new check after the retrospective-section length validation. Detects `## Backlog candidates` heading with bullets OR literal "Backlog candidates: 0 — no surfaced" line. When bullets are present, validates `docs/backlog.md` is in git diff (via shared `lib/git-refs.sh::get_plan_commit_parent` or origin/main fallback).
- **Discovered blocker:** initial regex `Backlog\s+candidates:?\s*0\s*[—-]\s*no\s+surfaced` with em-dash `—` (U+2014) inside a bracket class failed silently when the script ran under `set -euo pipefail`. Direct `grep -iE` matched; inside the script with the same regex, no match. Root cause likely shell-quoting / locale interaction. **Resolution:** replaced with simpler regex `^Backlog candidates.*0.*no surfaced` (case-insensitive). Works reliably.
- **Discovered second blocker:** existing tests 5/6/7 lacked `## Backlog candidates` section → my new validator blocked them. Updated their fixtures to include the "0 — no surfaced" line, preserving the tests' original intent (test 7 is about Layer-I removal, not backlog).
- Actual: 90 lines total (CLAUDE.md ~22, validator ~50, test ~80 added) vs 55 estimated.

## Verification
- `test-retrospective-validator.sh`: 11/11 ✓ (7 existing + 4 new: Test A blocks without section, Test B blocks with bullets + no backlog diff, Test C passes with literal 0-line, Test D defensive both signals).

## Retrospective

### Estimate accuracy
65 lines estimated, 90 actual (~1.4x). Underestimated: (1) the em-dash regex debug cycle (~30 minutes of bash quoting investigation), (2) the retrofit of existing tests 5/6/7. The retrofit was forced by my new requirement — would have been spotted in planning if I had inventoried existing test fixtures more carefully.

### Process gap
The em-dash issue (regex with multi-byte char inside bracket class failing under set -e+bash quoting) is the kind of detail that doesn't surface from spec/plan review — only from running the code. **Lesson:** when a spec includes a regex with non-ASCII chars, test the regex isolated before integrating. Add a TDD test that asserts the regex matches the exact target string BEFORE assembling the surrounding logic.

### Emergent patterns
- **Em-dash / multi-byte regex fragility:** 1st occurrence formally tracked. If recurs 3+ times across hooks, propose a knowledge-module entry "Bash regex portability — use ASCII-only or POSIX classes; avoid em-dash inside brackets".
- **Test fixtures need retrofit when validator gains a requirement:** universal pattern that should be a check during planning. 2nd occurrence (1st was 2026-05-18 P2 isolation fix in test-pattern-audit).

## Backlog candidates

- **Bash regex portability guideline** — knowledge module section about non-ASCII chars + bracket classes. Tracking (1st occurrence).
- **"Validator change → fixture audit" as a planning step** — when extending a validator with new requirements, planning should explicitly enumerate fixtures that may need updating. (2nd occurrence — tracking; graduate at 3rd.)
