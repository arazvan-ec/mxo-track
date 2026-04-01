# Instructions for Subagents

This file is loaded when the main thread dispatches subagents. It contains
the complete instructions that subagents need to do their work correctly.

## Output Limits (mandatory)

**Why:** Subagents have a ~25,000 token read limit. If output exceeds this,
the parent agent can't read the result and work is lost.

- **Absolute limit:** 300 lines or 15,000 tokens (whichever comes first)
- **Prefer writing to file:** If analysis is extensive, write to
  `docs/superpowers/agent-outputs/` and return only a 50-100 line summary with the file path
- **Never include full source code:** Reference files and line numbers instead
- **Start with executive summary:** 5-10 lines with key findings

Anti-patterns:
- Returning full content of multiple files → reference paths and lines
- Listing all grep/glob results → filter and summarize
- Plans with 500+ lines of inline code → reference file paths, steps only

---

## Subagent-Driven Development (Skill 5)

**Why:** Fresh subagent per task + two-stage review (spec then quality) = high quality, fast
iteration. Each subagent gets a clean context without accumulated noise from previous tasks.

Execute plan by dispatching fresh subagent per task, with two-stage review after each:
**spec compliance review first, then code quality review**.

### When to Use

- Implementation plan already written
- Tasks that are mostly independent
- Staying in the current session

### The Process

1. Read plan once; extract all tasks with full text and context
2. Create TodoWrite with all tasks
3. Per task (loop):
   - Dispatch implementer subagent
   - Handle questions or concerns if raised
   - Implementer implements, tests, commits, self-reviews
   - Dispatch spec compliance reviewer
   - If issues found: implementer fixes, reviewer re-reviews
   - Dispatch code quality reviewer
   - If issues found: implementer fixes, reviewer re-reviews
   - Mark task complete
4. After all tasks complete, dispatch final code reviewer
5. Use **finishing-a-development-branch** skill

### Model Selection

- **Mechanical implementation** (1-2 files, clear specs): fast, cheap model
- **Integration and judgment** (multi-file coordination): standard model
- **Architecture, design, review**: most capable model

### Handling Implementer Status

- **DONE:** Proceed to spec compliance review
- **DONE_WITH_CONCERNS:** Read concerns before proceeding
- **NEEDS_CONTEXT:** Provide context and re-dispatch
- **BLOCKED:** Assess: context problem → provide context; task too large → break into pieces; plan wrong → escalate to human

### Sprint Contract Pattern

Al despachar cada implementador, incluir acceptance criteria explícitos:

```
## Task: [task name from plan]
## Phase: v0 | Mature
## Acceptance Criteria
- [ ] [Specific verifiable criterion from plan]
- [ ] [Specific verifiable criterion]
- [ ] No introduces unnecessary code for this task
- [ ] Tests cover the new/modified behavior
- [ ] (Phase Mature only) Tests from v0 still pass without modification
```

These criteria go in both the implementer prompt AND the spec compliance reviewer prompt.
The reviewer reports PASS/FAIL per item.

### Checkpoint Reviews (XL features)

For XL features (>10 tasks or >5 files):

1. **Mid-implementation review:** At ~50% of tasks, dispatch reviewer with spec + implemented files. Question: "Is direction coherent with spec? Deviations? Quality?"
2. **Act on feedback:** Correct deviations before continuing
3. **Does not replace** per-task review — it is additional global coherence verification

### Red Flags

- Never skip reviews (spec compliance OR code quality)
- Never dispatch multiple implementation subagents in parallel (conflicts)
- Never make subagent read plan file (provide full text instead)
- Never start code quality review before spec compliance is approved
- If subagent asks questions, answer clearly before proceeding
- If reviewer finds issues, implementer fixes and reviewer re-reviews

---

## Dispatching Parallel Agents (Skill 6)

**Why:** Sequential investigation of independent problems wastes time.
One agent per independent domain, working concurrently.

**Core principle:** Dispatch one agent per independent problem domain. Let them work concurrently.

### When to Use

- 3+ test files failing with different root causes
- Multiple subsystems broken independently
- Each problem can be understood without context from others
- No shared state between investigations

### When NOT to Use

- Failures are related (fix one might fix others)
- Need to understand full system state
- Agents would interfere with each other

### The Pattern

1. **Identify Independent Domains** — Group failures by what's broken
2. **Create Focused Agent Tasks** — Each agent gets: specific scope, clear goal, constraints, expected output
3. **Dispatch in Parallel**
4. **Review and Integrate** — Read each summary, verify fixes don't conflict, run full test suite

### Agent Prompt Structure

Good agent prompts are:
1. **Focused** — One clear problem domain
2. **Self-contained** — All context needed to understand the problem
3. **Specific about output** — What should the agent return?

### Common Mistakes

- **Too broad:** "Fix all the tests" → agent gets lost
- **No context:** "Fix the race condition" → agent doesn't know where
- **No constraints:** Agent might refactor everything
- **Vague output:** "Fix it" → you don't know what changed

---

## Receiving Code Review (Skill 10)

**Why:** Code review should be technically rigorous, not socially performative.
"Great point!" before verification is harmful — it signals agreement without understanding.

**Core principle:** Verify before implementing. Ask before assuming. Technical correctness over social comfort.

### The Response Pattern

```
1. READ: Complete feedback without reacting
2. UNDERSTAND: Restate requirement in own words (or ask)
3. VERIFY: Check against codebase reality
4. EVALUATE: Technically sound for THIS codebase?
5. RESPOND: Technical acknowledgment or reasoned pushback
6. IMPLEMENT: One item at a time, test each
```

### Forbidden Responses

**NEVER:** "You're absolutely right!", "Great point!", "Let me implement that now" (before verification)

**INSTEAD:** Restate the technical requirement, ask clarifying questions, push back with
technical reasoning if wrong, just start working.

### Handling Unclear Feedback

If ANY item is unclear: **STOP** — do not implement anything yet. Ask for clarification on unclear items.

### When To Push Back

- Suggestion breaks existing functionality
- Reviewer lacks full context
- Violates YAGNI (unused feature)
- Technically incorrect for this stack
- Conflicts with user's architectural decisions

### Implementation Order (for multi-item feedback)

1. Clarify anything unclear FIRST
2. Blocking issues (breaks, security)
3. Simple fixes (typos, imports)
4. Complex fixes (refactoring, logic)
5. Test each fix individually
6. Verify no regressions

---

## Requesting Code Review (Skill 11)

### When to Request Review

**Mandatory:**
- After each task in subagent-driven development
- After completing major feature
- Before merge to main

**Optional but valuable:**
- When stuck (fresh perspective)
- Before refactoring (baseline check)
- After fixing complex bug

### How to Request

1. Get git SHAs (BASE_SHA and HEAD_SHA)
2. Dispatch code-reviewer subagent with: what was implemented, plan/requirements, base SHA, head SHA, description
3. Act on feedback: Fix Critical immediately, Fix Important before proceeding, Note Minor for later

---

## Known Infrastructure Issues

### Subagent Runtime Failures

Subagents can fail with runtime environment errors like `undefined is not an object (evaluating 'H.includes')`. When this happens, **all** subagent tools fail (Read, Bash, Grep, Glob) and the agent cannot do useful work.

**Symptoms:**
- Subagent reports it cannot execute any tools
- Internal JavaScript errors in tool calls
- Agent result says "infrastructure errors" or similar

**Solution:**
1. **Do not retry the same subagent** — the environment is broken; retrying won't fix it
2. **Execute the task in the main thread** — if subagent fails, do the work directly without delegating
3. **Alternative: launch a new subagent** — a new agent gets a fresh environment that may work
4. **If it persists:** inform the user and suggest restarting the Claude Code session

**Rule:** When a subagent fails due to infrastructure, do not mark the task as completed. Retry in the main thread or with a new subagent.

---

### Error: "tool_use ids must be unique" (API 400)

The Claude API rejects requests with HTTP 400 and message `tool_use ids must be unique` when the conversation history contains `tool_use` blocks with duplicate IDs. This is a **client bug** (Claude Code / Agent SDK), not a server issue.

**Causes:**
- Parallel tool calls generating duplicate IDs
- Long conversations with many tool_use turns where history reconstruction introduces duplicates
- Resumed sessions (`--resume`) with corrupted history

**Symptoms:**
- Error 400: `messages.N.content.M: tool_use ids must be unique`
- Conversation cuts off abruptly and cannot continue
- Tools stop working in the current session

**Mitigation:**
1. **Commit frequently** — each completed task must be committed immediately so progress isn't lost if the session corrupts
2. **Document state in TodoWrite** — keep the todo list updated so on resume you know what's left
3. **Prefer atomic tasks** — break large work into small independent steps
4. **Limit subagent depth** — conversations with many parallel tool calls are more prone to this error

**Recovery:**
1. Use `/clear` — resets conversation history
2. Start a new session — `claude` without `--resume`
3. Resume with caution — `claude --resume <id>` may work if error was isolated
4. Check `git log` — verify what was committed before the error
5. Read TodoWrite — check which tasks are completed vs pending

**Rule:** On this error, NEVER assume previous work was saved. Verify with `git log` and `git status` before continuing.

---

### Error: "assistant message prefill" (API 400)

The Claude API rejects requests with HTTP 400 and message `This model does not support assistant message prefill` when the client attempts to prefill the assistant response with a model that doesn't support it. This is a **client bug**, not a workflow issue.

**Causes:**
- Client incorrectly constructs the API request (sends assistant message as last message)
- Long conversations where context compression corrupts the message structure
- Resumed sessions with malformed history

**Symptoms:**
- Error 400: `This model does not support assistant message prefill`
- Session interrupts abruptly
- Identical behavior to the tool_use ids duplicate error

**Mitigation and recovery:** Identical to the "tool_use ids must be unique" error above.
The same 4 mitigation rules and 5 recovery steps apply. Best protection: **frequent commits + atomic tasks + updated TodoWrite**.
