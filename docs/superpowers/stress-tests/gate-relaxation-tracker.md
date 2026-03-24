# Gate Relaxation Stress Test Tracker

**Start date:** 2026-03-24
**Model:** Opus 4.6
**Change:** All phase-ordering HARD gates relaxed to SOFT (warnings instead of blocks)
**Decision criteria:** >=90% compliance across 5 tasks → permanent SOFT | 70-89% → revert to HARD | <70% → revert immediately

## Validators Relaxed

| Validator | Original | Stress-test |
|-----------|----------|-------------|
| consult-validator.sh | HARD (exit 2) | SOFT (exit 1) |
| brainstorm-validator.sh | HARD (exit 2) | SOFT (exit 1) |
| planning-validator.sh | HARD (exit 2) | SOFT (exit 1) |
| implementation-validator.sh | HARD (exit 2) | SOFT (exit 1) |
| debug-validator.sh | HARD (exit 2) | SOFT (exit 1) |
| verification-validator.sh | HARD (exit 2) | **NOT relaxed** (safety) |

## Scorecard

### Task 1: ___
- **Date:** ___
- **Type:** full / debug
- **Phases complied voluntarily:**
  - [ ] consult (read decisions/logs before starting)
  - [ ] brainstorming (proposed alternatives, got approval, wrote spec)
  - [ ] planning (wrote plan with tasks)
  - [ ] implementation (TDD, tests before code)
  - [ ] verification (ran tests + lint before claiming done)
  - [ ] capture (wrote execution log)
  - [ ] retrospective (updated decision log)
- **Phases skipped despite warning:** ___
- **Quality impact of skips:** ___
- **Compliance score:** ___ / 7

### Task 2: ___
- **Date:** ___
- **Type:** full / debug
- **Phases complied voluntarily:**
  - [ ] consult
  - [ ] brainstorming
  - [ ] planning
  - [ ] implementation (TDD)
  - [ ] verification
  - [ ] capture
  - [ ] retrospective
- **Phases skipped despite warning:** ___
- **Quality impact of skips:** ___
- **Compliance score:** ___ / 7

### Task 3: ___
- **Date:** ___
- **Type:** full / debug
- **Phases complied voluntarily:**
  - [ ] consult
  - [ ] brainstorming
  - [ ] planning
  - [ ] implementation (TDD)
  - [ ] verification
  - [ ] capture
  - [ ] retrospective
- **Phases skipped despite warning:** ___
- **Quality impact of skips:** ___
- **Compliance score:** ___ / 7

### Task 4: ___
- **Date:** ___
- **Type:** full / debug
- **Phases complied voluntarily:**
  - [ ] consult
  - [ ] brainstorming
  - [ ] planning
  - [ ] implementation (TDD)
  - [ ] verification
  - [ ] capture
  - [ ] retrospective
- **Phases skipped despite warning:** ___
- **Quality impact of skips:** ___
- **Compliance score:** ___ / 7

### Task 5: ___
- **Date:** ___
- **Type:** full / debug
- **Phases complied voluntarily:**
  - [ ] consult
  - [ ] brainstorming
  - [ ] planning
  - [ ] implementation (TDD)
  - [ ] verification
  - [ ] capture
  - [ ] retrospective
- **Phases skipped despite warning:** ___
- **Quality impact of skips:** ___
- **Compliance score:** ___ / 7

## Aggregate Results

| Metric | Result |
|--------|--------|
| Total tasks | 0 / 5 |
| Average compliance | ___ % |
| Phases most skipped | ___ |
| Quality incidents from skips | 0 |
| **Decision** | pending |

## Decision Log

- **2026-03-24:** Stress test started. All HARD gates relaxed to SOFT.
