# Spec: Automatic Status Line in Every Response

**Date:** 2026-03-23
**Type:** Enhancement (tooling/infrastructure — pragmatic)
**Branch:** `claude/fix-fleet-map-routing-Kllk0`

---

## Problem

The workflow engine (`workflow-engine.sh`) enforces gates mechanically, and `session-state.json` tracks flow type, current phase, and evidence. However, the user has **zero visibility** into the workflow state during conversation unless they run `make workflow-status` manually.

Three visibility mechanisms were approved in the original workflow verification design:
- A) **Status line in every Claude response** — NOT implemented
- B) `make workflow-status` command — implemented
- C) `.claude/workflow-status.md` live file — implemented

This spec implements mechanism A — the missing piece.

## Design Decisions (from brainstorming)

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Approach | C: CLAUDE.md instruction + PostToolUse hook + status-line file | Double mechanism: hook prepares data, instruction ensures display |
| Hook trigger | PostToolUse on ALL tools | Maximum freshness (~10ms per execution) |
| Status levels | Two: full (phase change) + compact (same phase) | Avoids noise while keeping context |
| Flow coverage | All flows (full, debug, micro, light, explore) | User wants visibility always |
| Fallback if no state | `📍 no flow declared` | Reminds Claude to classify |

## Existing Functionality Inventory

| Element | Decision | Justification |
|---------|----------|---------------|
| `workflow-status.sh` (generates detailed .md) | Include — keep as-is | Complementary: detailed view for `make workflow-status` |
| `.claude/workflow-status.md` | Include — keep as-is | Different purpose: full dashboard vs one-liner |
| `session-state.json` schema | Include — no changes | Status line is read-only consumer |
| `settings.json` PostToolUse hooks | Transform — add new matcher | New matcher for status-line generation |
| CLAUDE.md behavioral instructions | Transform — add new section | Mandatory instruction for status display |

## Omission Decisions

No omissions — all inventory items addressed.

## Components

### 1. Hook Script: `.claude/hooks/workflow-status-line.sh`

Reads `session-state.json` and writes a single line to `.claude/workflow-status-line.txt`.

**Input:** `session-state.json` (read-only)
**Output:** `.claude/workflow-status-line.txt` (one line, UTF-8)

**Logic:**

```
1. Read flow_type, current_phase from session-state.json
2. If flow_type is null → output "📍 no flow declared"
3. If flow_type is micro/light/explore → output "📍 {flow} | {label}"
   - micro → "Responder"
   - light → "Documentar"
   - explore → "Investigar"
4. If flow_type is full → compute phase index (1-8), list completed phases, output:
   "📍 full | {Phase} ({index}/8) | {completed_phases} → 🔄 {current} | Pendiente: {remaining}"
5. If flow_type is debug → compute phase index (1-4), output similarly:
   "📍 debug | {Phase} ({index}/4) | {completed} → 🔄 {current} | Pendiente: {remaining}"
6. If deviation.active → append " | ⚠ DESVÍO"
```

**Phase mappings:**

Full-flow (8 phases):
```
consult(1) → brainstorming(2) → planning(3) → implementation(4) → verification(5) → capture(6) → retrospective(7) → finalize(8)
```

Debug-flow (4 phases):
```
consult(1) → root_cause(2) → pattern_search(3) → fix(4)
```

Debug phase detection from evidence:
- `root_cause_identified = true` → root_cause completed
- `pattern_wide_search_done = true` → pattern_search completed
- current_phase = "implementation" → fix phase active

**Performance:** ~10ms (jq read + echo). Non-blocking (exit 0 always).

### 2. Hook Registration: `settings.json`

New PostToolUse entry with matcher `""` (empty string = matches all tools):

```json
{
  "matcher": "",
  "hooks": [
    {
      "type": "command",
      "command": "/home/user/mxo-track/.claude/hooks/workflow-status-line.sh",
      "timeout": 2,
      "statusMessage": "Updating status line..."
    }
  ]
}
```

### 3. CLAUDE.md Instruction: "Automatic Status Line (mandatory)"

New section in CLAUDE.md behavioral instructions. Key rules:

1. **Read** `.claude/workflow-status-line.txt` at the start of composing each response
2. **Display** the content as the FIRST line of your response, verbatim
3. **Two display levels:**
   - **Full** (when phase changed since last response): show the complete line as-is
   - **Compact** (same phase as last response): show only `📍 {flow} | {Phase} ({index}/{total})`
4. **Never skip** — even for short answers, clarifying questions, or error messages
5. **If file doesn't exist or is empty:** show `📍 status unavailable`

### Examples

Full (phase just changed):
```
📍 full | Brainstorming (2/8) | ✅ consult → 🔄 brainstorm | Pendiente: spec, plan
```

Compact (same phase):
```
📍 full | Brainstorming (2/8)
```

Micro-flow:
```
📍 micro | Responder
```

Debug-flow:
```
📍 debug | Root Cause (2/4) | ✅ consult → 🔄 root_cause | Pendiente: pattern_search, fix
```

No flow declared:
```
📍 no flow declared
```

### 4. `.gitignore` Update

Add `.claude/workflow-status-line.txt` (ephemeral session data, not committed).

## File Changes Summary

| File | Action | Description |
|------|--------|-------------|
| `.claude/hooks/workflow-status-line.sh` | **Create** | Hook that generates one-line status |
| `.claude/settings.json` | **Edit** | Add PostToolUse matcher for all tools |
| `CLAUDE.md` | **Edit** | Add "Automatic Status Line" mandatory section |
| `.gitignore` | **Edit** | Add `.claude/workflow-status-line.txt` |

## Testing Strategy

1. **Unit tests for hook:** Run with various session-state.json states (null flow, full-flow at each phase, debug-flow, deviation active, micro/light/explore)
2. **Integration:** Verify hook runs after tool calls by checking `.claude/workflow-status-line.txt` timestamp
3. **Manual verification:** After implementation, every response in this session should show the status line
