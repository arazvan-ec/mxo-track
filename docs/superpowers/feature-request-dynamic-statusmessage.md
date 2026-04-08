feat: support dynamic statusMessage interpolation for hooks

---

## Problem

Hook `statusMessage` in `.claude/settings.json` is a static string. When hooks fire on tool calls, the activity log shows repeated generic labels like "After tool use: Edit" with no indication of *which file* is being processed or *what* the hook is doing.

In a typical session with 30+ tool calls, this produces 60-100 identical entries that provide no useful feedback to the user.

### Current behavior

```json
{
  "matcher": "Read|Write|Edit|Agent",
  "hooks": [{
    "type": "command",
    "command": "/path/to/hook.sh",
    "statusMessage": "Processing..."
  }]
}
```

Activity log shows:

```
- After tool use: Edit    → "Processing..."
- After tool use: Edit    → "Processing..."
- After tool use: Edit    → "Processing..."
```

No way to tell which file or what action each entry corresponds to.

### Desired behavior

Allow `statusMessage` to interpolate variables from the hook context:

```json
{
  "statusMessage": "Processing: {{tool_input.file_path}}"
}
```

Activity log would then show:

```
- After tool use: Edit    → "Processing: src/Controller/VehicleController.php"
- After tool use: Edit    → "Processing: .claude/settings.json"
- After tool use: Edit    → "Processing: backend/config/routes.yaml"
```

## Proposed API

Support `{{variable}}` interpolation in `statusMessage` with access to the same context available to the hook script via stdin:

| Variable | Example value |
|----------|--------------|
| `{{tool_name}}` | `Edit` |
| `{{tool_input.file_path}}` | `src/Entity/Vehicle.php` |
| `{{tool_input.command}}` | `git push origin main` |
| `{{tool_input.pattern}}` | `*.tsx` |
| `{{tool_input.description}}` | `Search for patterns` |

Fallback: if the variable is not present in the context, replace with empty string (so `"{{tool_input.file_path}}"` on a Bash call just shows blank, not a literal `{{...}}`).

## Workaround attempted

We consolidated 3 PostToolUse hooks into 1 dispatcher to reduce the *number* of entries (3→1 per tool call). This helps but doesn't solve the readability problem — each entry still shows a generic static message.

## Environment

- Claude Code web (claude.ai/code)
- Hooks: PreToolUse + PostToolUse with matchers for Edit|Write|Read|Bash
- Typical session: 30-50 tool calls → 60-100 hook activity entries
