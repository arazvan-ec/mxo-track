# Spec — Learning Review: Gate-Drift Sub-Section

**Date:** 2026-05-18
**Branch:** `claude/compare-claude-workflows-yrl2P`
**Type:** code change (full flow)
**Scope ref:** Harness improvement P3 of 3 — close the loop opened by P2 (`pattern-audit.sh` gate-drift detection) at the periodic review level.

## Problem

`pattern-audit.sh` is invoked at `retrospective → finalize` of each full/debug flow. If no full/debug flow runs for several weeks, gate-bypass signals accumulate in `docs/decisions/log.md` without ever being surfaced. **The hook needs a periodic backstop.**

Skill 15 "Learning Review" (`docs/CLAUDE.md`) already provides a monthly review cycle that reads execution logs and decision log, analyzes patterns, and proposes CLAUDE.md changes. But its checklist does not currently include an **explicit gate-drift sub-section**. The signal from P2 has no defined consumer at the review level — Learning Review readers may miss it because nothing in the checklist points there.

## Approach Chosen

**Extend Skill 15 in `docs/CLAUDE.md`** with an explicit "Gate-drift review" sub-section in the Process checklist. The sub-section is **always present** in every monthly review, with explicit handling for the empty case ("0 gates flagged — harness stable for the period").

### Wording added to `docs/CLAUDE.md` Skill 15 § Process

After current step 3 ("Analyze: estimation accuracy, blocker frequency, decision outcomes"), insert as new step 4:

```markdown
4. **Gate-drift review (always perform; "0 flagged" is a valid result):**
   a. Run `.claude/hooks/pattern-audit.sh` and capture the "gates with ≥3 bypasses" block.
   b. For each flagged gate, choose `[TUNE]` (update validator heuristic) or `[LEGITIMIZE]` (document the case in CLAUDE.md § Bypass env vars). Both choices require a decision-log entry justifying the call.
   c. If no gates flagged: write the line `Gate-drift: 0 gates flagged — harness stable for the period` in the review document.
```

Renumber subsequent steps (current 4 → 5).

### Why always-present (not conditional)

- **Consistency:** Skill 15's checklist is fixed; making one item conditional breaks the pattern and risks omission.
- **"0 flagged" is positive evidence:** a documented zero confirms gates are stable. Silence is ambiguous.
- **Cost is negligible:** ~30 seconds when empty; ~5 minutes when 1-2 gates flagged. Bounded.

## Maximal Version Considered

The maximal version was a **new Skill 16 "Quarterly Gate Audit"** with its own cadence, output artifact (`docs/superpowers/retrospectives/YYYY-QN-gate-audit.md`), and dedicated checklist. The current spec reduces that to a sub-section of Skill 15.

**Independent superiority of the reduction (not on cost grounds):**

- **Consistency / alignment:** Skill 16 would split retrospection across two parallel cadences (monthly Learning Review + quarterly Gate Audit). Two artifacts reviewing overlapping signals (the same decision log, the same bypass entries) creates **drift** between them: lessons in one may contradict the other. A single review process operating on the same dataset eliminates this drift by construction.
- **Pattern alignment with graduation:** The harness's existing pattern is *3+ occurrences → graduation to existing knowledge module*. Gate drift fits this pattern as a sub-case (sub-section) of the existing review process, not a parallel process. Forking it into Skill 16 would contradict the graduation pattern itself.
- **Coverage gap closed:** Quarterly cadence in Skill 16 would miss up to 2 months of accumulating signals (worst case: bypass count of 5 across 60 days never surfaces until quarter-end). Monthly cadence in Skill 15 catches signals at half the latency.
- **Single source of truth for review artifacts:** All review documents under `docs/superpowers/retrospectives/YYYY-MM-review.md` — no parallel directory or naming convention.

The cost asymmetry exists (1 skill update vs. 2 specs + 2 artifacts + 2 checklists) but is **not** the deciding factor — the design-quality arguments above stand independently.

## Existing Functionality Inventory

| Element | Decision | Justification |
|---|---|---|
| Skill 15 § When ("Monthly, or when 5+ execution logs accumulated") | **Keep, unchanged** | Cadence trigger unaffected |
| Skill 15 § Process steps 1-3 | **Keep, unchanged** | Gate-drift inserted as step 4, no displacement of existing steps |
| Skill 15 § Process step 4 (current "Write review") | **Renumber to 5** | Mechanical rename only |
| Skill 15 § Process step 5 (current "Act") | **Renumber to 6** | Mechanical rename only |
| `pattern-audit.sh` (post-P2) | **Consume, do not modify** | Step 4a invokes the hook; no edits to the script from this spec |
| `docs/decisions/log.md` | **No format change** | Step 4b adds entries using existing entry format |

## Omission Decisions

| Element | Decision | Justification |
|---|---|---|
| Backfill — run a Learning Review now for the 5 bypass entries since 2026-04-22 | **Omit from this spec; defer to next Learning Review cycle** | Backfill mixes "establish the process" with "exercise the process"; cleaner to ship the process change and let next monthly cycle naturally consume it. Time elapsed since 2026-04-22 (~26 days) falls within the 90-day window of P2's detection, so signal will not be lost. |
| Auto-generate the review document from `pattern-audit.sh` output | **Omit** | Review is a human reasoning exercise; auto-generation defeats its purpose |
| Add a hook that warns when monthly review is overdue | **Omit** | Out of scope; calendar discipline is a human concern |
| Cross-link Skill 15 from CLAUDE.md root § Workflow Engine | **Omit** | CLAUDE.md root already mentions retrospective/graduation patterns; explicit cross-link adds bytes without changing behavior |
| Template file for review document | **Omit** | Skill 15 already implies structure; no proven need for a stricter template |

## Norms

- The gate-drift sub-section **must** appear in every monthly Learning Review document, including months with zero flagged gates.
- When zero gates are flagged, the review document **shall** include the literal line `Gate-drift: 0 gates flagged — harness stable for the period`. Silence is not permitted.
- Every `[TUNE]` or `[LEGITIMIZE]` decision **must** produce a corresponding entry in `docs/decisions/log.md` — undocumented gate changes are forbidden.
- The sub-section **shall** consume `pattern-audit.sh` output as-is; the review document **never** duplicates or re-formats the hook's detection logic.

## Safeguards

| Risk | Mitigation |
|---|---|
| Reviewer skips the sub-section when no gates flagged (treats it as optional) | Norm requires the explicit "0 flagged" line; absence is auditable |
| `[TUNE]` decisions accumulate without action (review proposes, nothing changes) | Norm requires decision-log entry; future audit can verify action via decision log diff |
| Skill 15 becomes too long, reviewers skim past gate-drift | Sub-section is bounded (single hook invocation + simple choice); estimated <100 words in review |
| Monthly cadence lapses, signal accumulates beyond 90-day window of P2 | If review skipped, P2's 90-day window may roll forward and drop entries — accepted risk; safeguarded by Skill 15 trigger "5+ execution logs accumulated" (forcing review when activity is high) |
| Decision-log entry format diverges from existing convention | Norm references existing entry format; no new template introduced |

## Verification

1. Read modified `docs/CLAUDE.md` and confirm Skill 15 § Process has steps 1-6 (was 1-5) with new step 4.
2. Dry-run the checklist mentally against current state: run `pattern-audit.sh` (post-P2), confirm output contains gates ≥3, draft `[TUNE]`/`[LEGITIMIZE]` decisions for them.
3. Confirm wording is consistent with existing Skill 15 style (numbered list, imperative verbs).
4. `make lint` passes (no PHP changes but contractual).
