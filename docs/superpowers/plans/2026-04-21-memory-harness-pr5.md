# Plan — 2026-04-21 — Memory/Harness PR5 (Workflow Flow Improvements)

**Spec:** `docs/superpowers/specs/2026-04-21-memory-harness-pr5.md`
**Branch:** `claude/view-plan-progress-ddWZc`

---

## Parallelism analysis

| Task | Files touched |
|------|---------------|
| 1a make lint-shell target | `Makefile` (edit) |
| 1b fix SC2064 traps | 5 test files |
| 1c fix SC2221/2222 case patterns | `scripts/backfill-exec-logs.sh` |
| 1d fix SC1083, SC1010, SC2155 | 2-3 files |
| 1e disable-directives for false positives | various test files |
| 2 harden verification-validator | `verification-validator.sh` |
| 3 CLAUDE.md edits | `CLAUDE.md` |
| 4a fix plan-progress wave regex | `plan-progress.sh` |
| 4b regression test | new `test-plan-progress.sh` |

Wave 1 (1a-1e): shellcheck fixes — sequential por simplicidad.
Waves 2, 3, 4: disjoint files → parallel posible pero serializo por tamaño pequeño.

---

## Phase 1: v0

### Wave 1: Shellcheck foundation + fixes

- **1a: Add `make lint-shell` target**
  - Edit `Makefile`: new target running `shellcheck -S warning`
  - → produces: `make lint-shell` runnable

- **1b: Fix SC2064 trap expansion (5 test files)**
  - `trap "rm -rf $TMPDIR" EXIT` → `trap 'rm -rf "$TMPDIR"' EXIT`
  - → produces: traps use single quotes

- **1c: Fix SC2221/2222 case patterns**
  - `scripts/backfill-exec-logs.sh`: remove overlapping patterns
  - → produces: case without warnings

- **1d: Fix remaining warnings**
  - SC1083 (2), SC1010 (1), SC2155 (1) — individual fixes

- **1e: Add disable-directives for intent**
  - SC2034 in tests (vars consumed indirectly)
  - SC2317 top-of-file in workflow-status-line.sh
  - → produces: `make lint-shell` exits 0

**Commit 1:** `feat: add lint-shell target + fix 9 shellcheck warnings`

### Wave 2: Harden verification-validator

- **2a: Edit `verification-validator.sh`**
  - In full/debug flows, reject `lint_clean = "skipped"` → ERROR
  - Same for `tests_passed = "skipped"`
  - Keep "skipped" as valid in informational/light/explore
  - → produces: gate con teeth en full/debug

**Commit 2:** `feat: reject lint/tests skipped in full+debug verification gate`

### Wave 3: CLAUDE.md integration

- **3a: Edit CLAUDE.md**
  - Section "Closing the Cycle": mención de `graduate.sh`
  - Section "Knowledge Modules": mención de `_graduations.yaml`
  - Section "Common Commands": añadir `make lint-shell`
  - → produces: CLAUDE.md refleja PR4+PR5 reality

**Commit 3:** `docs: integrate graduation registry + lint-shell into CLAUDE.md`

### Wave 4: plan-progress wave regex fix

- **4a: Fix regex en `plan-progress.sh`**
  - `re.compile(r'^###\s+(?:\[[^\]]*\]\s+)?Wave\s+(\d+)...')`
  - → produces: parser cuenta waves con `[parallel]` prefix

- **4b: Regression test**
  - Nuevo `test-plan-progress.sh`
  - Fixtures con 3 variantes de wave headers
  - → produces: bug guarded against regression

**Commit 4:** `fix: plan-progress wave regex accepts [prefix] before Wave N`

### Wave 5: Verification + close-out

- **5a:** `make lint-shell` must exit 0
- **5b:** full test suite (all prior + new test-plan-progress)
- **5c:** make manifest
- **5d:** execution log + phases + push

**Commit 5:** `docs: execution log + manifest`

---

## Task count: 9 tareas, 5 waves, 5 commits

## Files affected

- **New:** `.claude/hooks/test-plan-progress.sh`
- **Modified:** `Makefile`, `CLAUDE.md`, `verification-validator.sh`,
  `plan-progress.sh`, `backfill-exec-logs.sh`, 5 test files, `workflow-status-line.sh`

## Time estimate: 60-90 min

## Risk: Low-Medium

- **Medium:** endurecer verification-validator (Wave 2) DESPUÉS de fix warnings
  (Wave 1). Orden crítico — si invertido, bloquea su propio push.
- **Low:** demás cambios aditivos.
