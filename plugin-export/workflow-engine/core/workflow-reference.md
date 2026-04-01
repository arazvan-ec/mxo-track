# Workflow Engine — Technical Reference

Detailed reference for the workflow engine mechanics. Consult when you need detail beyond what's in the plugin CLAUDE.md.

---

## session-state.json Schema

```jsonc
{
  "session_date": "YYYY-MM-DD",
  "flow_type": "micro|light|debug|full|explore|null",
  "current_phase": "consult|brainstorming|planning|implementation|verification|capture|retrospective|finalize|null",
  "interaction_id": 0,
  "phase_history": [],
  "last_work_summary": {
    "previous_date": "YYYY-MM-DD",
    "previous_branch": "...",
    "previous_flow": "...",
    "previous_phase": "...",
    "recent_commits": ["..."],
    "merged_branches": ["..."],
    "last_execution_log": { "file": "...", "preview": "..." }
  },
  "evidence": {
    "interaction_id": 0,
    "decisions_read": false,
    "logs_scanned": false,
    "user_turns": 0,
    "alternatives_proposed": false,
    "user_approved": false,
    "spec_path": null,
    "plan_path": null,
    "tests_written": 0,
    "tests_passed": null,
    "lint_clean": null,
    "execution_log_path": null,
    "branch_strategy": null,
    "root_cause_identified": false,
    "pattern_wide_search_done": false,
    "task_progress": {
      "current": 0,
      "total": 0,
      "label": null,
      "completed_labels": []
    }
  },
  "deviation": {
    "active": false,
    "reason": null,
    "skipped_phases": [],
    "return_to_phase": null,
    "acknowledged_by_user": false
  }
}
```

---

## How to Update session-state.json

State updates use `jq` for atomic writes.

**Phase transition:**
```bash
REPO=$(git rev-parse --show-toplevel 2>/dev/null || pwd)
jq '.phase_history += ["consult"] | .current_phase = "brainstorming"' \
  "$REPO/.claude/session-state.json" > /tmp/ss.json && mv /tmp/ss.json "$REPO/.claude/session-state.json"
```

**Single evidence field:**
```bash
jq '.evidence.decisions_read = true' \
  "$REPO/.claude/session-state.json" > /tmp/ss.json && mv /tmp/ss.json "$REPO/.claude/session-state.json"
```

---

## Gates by Flow and File Type

| Flow | src/tests | specs/plans | docs/config | other |
|------|-----------|-------------|-------------|-------|
| **micro** | DENY | DENY | pass | pass |
| **light** | DENY | DENY | pass | pass |
| **explore** | DENY | DENY | pass | pass |
| **debug** | HARD (debug-validator) | pass | pass | pass |
| **full** | HARD (full validators) | HARD (phase validators) | SOFT | SOFT |

### Full-Flow Gates by File

| To edit... | Required phases | Gate |
|-----------|-----------------|------|
| specs/* | consult | HARD |
| plans/* | consult, brainstorming | HARD |
| src/*, tests/* | consult, brainstorming, planning | HARD |
| execution-logs/* | (self) | SOFT |
| decisions/* | (self) | SOFT |

### Debug-Flow Gates

| Step | Requires | Gate |
|------|----------|------|
| Consult | decisions_read OR logs_scanned | HARD |
| Root Cause | root_cause_identified | HARD |
| Pattern-Wide | pattern_wide_search_done | HARD |

---

## Validators — Evidence Required per Phase

| Phase | Evidence | Level |
|-------|----------|-------|
| consult | decisions_read OR logs_scanned | HARD |
| brainstorming | user_turns >= 1 (HARD) + SOFT warn if < 3 + alternatives + approved + spec (>= 500B) | MIXED |
| planning | plan_path (file >= 300B with keywords) | HARD |
| implementation | plan exists (HARD) + tests_written > 0 (SOFT) | MIXED |
| verification | tests_passed + lint_clean | HARD |
| capture | execution_log_path exists | SOFT |
| retrospective | (reminder only) | SOFT |
| finalize | branch_strategy declared | SOFT |
| debug-code | decisions/logs + root_cause + pattern_wide | HARD |

---

## Deviation Mode

Activate for genuine emergencies only. Requires explicit user confirmation.

When active: HARD gates become SOFT warnings. All phases can be skipped.

---

## Enforcement Levels

- **HARD** — Blocks the action (exit 2). For validated necessary assumptions.
- **SOFT** — Warning but allows continuation (exit 1). For assumptions in transition.
- **Docs** — Documented best practice, no mechanical enforcement.

Evolution model:
```
HARD → (stress-test: 5 tasks, >= 90% compliance) → SOFT → (10 tasks, >= 95%) → Docs → Remove
```
