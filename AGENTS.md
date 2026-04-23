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

## Subagent Mini-Flow: consult → implement → verify

**Why:** Subagents don't follow the full 8-phase workflow, but experience shows they
produce higher quality output when they consult context first and verify their output
before reporting. Skipping these adds rework: subagents rediscover problems already
documented, or report "done" on broken output.

**Not the full flow.** Brainstorm/plan are orchestrator-level decisions (the prompt
encapsulates them). Capture/retrospective are orchestrator-level learning. But
**consult + verify are high-leverage for every subagent**.

**Mandatory instructions to include in EVERY agent prompt:**

```
## Before starting (consult, ~1 min)
- Read the most recent execution log in docs/superpowers/execution-logs/ that relates
  to this area. If a previous attempt failed or surfaced a gap, know it now.
- Read the relevant knowledge module in docs/knowledge/ if one exists for the area.

## Before reporting complete (verify)
- If you created/modified code: run the relevant check (tsc, phpunit, lint) and
  report the result.
- If you created/modified documentation: verify it doesn't contradict existing modules
  and that internal cross-references point to real files.
- Report "done" only when the verification passed. Report "failed" with specifics
  otherwise.
```

Paste this verbatim into the "Constraints" section of each agent prompt.

---

## Parallel Task Progress Tracking

**Why:** When dispatching 2+ subagents concurrently, the orchestrator only knows task
state via completion notifications. During execution, agents are opaque — no way to
see "which are reading, which are writing, which are blocked" without polling (which
pollutes context).

**Mechanism:** Shared state file `.claude/parallel-tasks.json` updated atomically by
each subagent via `.claude/scripts/task-progress.sh`. The `parallel-tasks-status.sh`
hook reads it and injects active task summary into the orchestrator's status line on
every prompt.

**Phases (5 valid values):** `started` `reading` `implementing` `verifying` `done` `failed`

**Mandatory boilerplate to include in EVERY parallel agent prompt:**

```
## Progress tracking (mandatory for parallel dispatch)

At each phase transition, run ONE bash command to update shared state:

    bash .claude/scripts/task-progress.sh <TASK_ID> <phase> [note]

Where <TASK_ID> is: <assign a unique slug per agent, e.g. "widget-system">

Phases to report:
1. `started`      — at the very start of your work
2. `reading`      — while reading source files (optional note: "N/M files")
3. `implementing` — while writing your output (optional note: current section)
4. `verifying`    — while running checks before reporting
5. `done`         — final state, with summary note (e.g. "222 lines written")
6. `failed`       — on error, with brief cause

Update at minimum: started, implementing, done. More granular updates help the
orchestrator see progress.
```

**Orchestrator usage:**
1. Assign a unique `task_id` per agent (short slug, e.g. `widget-system`, `map-components`).
2. Paste the boilerplate into each prompt, substituting `<TASK_ID>`.
3. Agents update state autonomously as they work.
4. Between every tool call, the orchestrator sees current state in the status line:
   ```
   🔀 Parallel tasks: 3 active, 2 done, 0 failed
     ✍️ widget-system — implementing: section 4/7
     📖 map-components — reading: 5/12 files
     🟢 driver-experience — started
   ```

**Automatic cleanup:** The hook prunes entries in terminal state (`done`/`failed`)
after 1 hour. Manually delete `.claude/parallel-tasks.json` to reset.

**When NOT to use:** Single agent dispatches don't benefit — the overhead (~3 bash
calls) exceeds the value. Only use for 2+ concurrent agents.

---

## Light Agent Mode (`flow_type = "agent"`)

**When:** The main agent has already completed consult → brainstorming → planning and
now dispatches a sub-agent (typically with `isolation: "worktree"`) to execute
implementation. The sub-agent should NOT repeat the workflow engine phases — it just
needs to write code, test, and commit.

**How to set up the sub-agent:**

1. In the prompt, tell the sub-agent to set `flow_type = "agent"` in session-state:
   ```bash
   jq '.flow_type = "agent" | .current_phase = "implementation"' \
     .claude/session-state.json > /tmp/ss.json && mv /tmp/ss.json .claude/session-state.json
   ```
2. The `agent` flow type has **zero workflow gates** — all file classes (code, test,
   spec, docs) are writable without passing validators.
3. Phase sequence is minimal: `implementation → verification` (only 2 phases).
4. The sub-agent does NOT need to write spec, plan, or execution log. Those are the
   main agent's responsibility.

**Why this exists:** In the 2026-04-14 session, two sub-agents hit rate limits (~8 min
each) because they spent ~50% of their token budget on the workflow engine (writing spec,
plan, advancing phases, session-state). The actual code was only ~40% of their work.
Light agent mode eliminates the overhead so sub-agents focus exclusively on coding.

**What the main agent must provide in the prompt:**
- Exact file paths to create/modify (with ownership boundaries)
- The design decision (what to build, not how to discover what to build)
- Verification commands to run before committing
- Branch name to push to

**What the sub-agent must NOT do:**
- Write to `.claude/session-state.json` in the MAIN repo (worktree has its own copy)
- Follow the full 8-phase workflow (that's the main agent's job)
- Create GitHub PRs (main agent coordinates merges)

### Session-State Isolation in Worktrees

Git worktrees have independent working directories. `.claude/session-state.json` is
gitignored, so each worktree gets its own copy (created by session-start hook or by
the sub-agent's initial `jq` command).

**Rule:** After sub-agent completion, the main agent should verify its own
`session-state.json` hasn't changed. If the sub-agent's worktree somehow shares the
same `.claude/` directory (shouldn't happen, but defensive), re-set the main agent's
`plan_path` and `spec_path` before continuing.

**Stale paths after agent completion:** When resuming after agents complete, verify
that `evidence.plan_path` and `evidence.spec_path` still point to valid files. A
sub-agent may have overwritten these with its own worktree paths during its run. Fix
by re-setting to the main agent's spec/plan paths.

---

## Agent Permission Model

**Why:** Background agents inherit the main session's auto-approve settings but
**cannot prompt for manual approval**. If a tool call requires confirmation, the
agent receives "denied" and fails silently. This section catalogs the permission
surfaces that bite most often.

### Path restrictions for background agents

**The sandbox blocks writes from background agents to `.claude/**` paths
regardless of auto-approve settings.** Reads succeed; Write, Edit, and any
Bash-based write (heredoc, `tee`, redirection) are denied. Setting
`dangerouslyDisableSandbox: true` does NOT lift this restriction — it is a
harness-level sandbox policy, not a per-call permission.

Writes to `/tmp/`, the repo root, `docs/**`, `backend/**`, `frontend/**`, and
every other non-`.claude/` path work normally.

**Evidence source:** `docs/superpowers/execution-logs/2026-04-22-knowledge-module-and-flow-phases-sot.md`
documented the first time the orchestrator discovered this the hard way — a
background agent tasked with refactoring `.claude/hooks/**` failed every write
attempt before we realized the sandbox was the cause.

### Consequences for dispatch

- **Docs-only tasks** (`docs/**`, repo-root `*.md` including `AGENTS.md` and
  `CLAUDE.md`, `backend/**`, `frontend/**`, `ml-service/**`): dispatch to a
  background agent normally.
- **Harness tasks** (`.claude/hooks/**`, `.claude/settings*.json`, `.claude/scripts/**`,
  or any path under `.claude/` the agent must modify): do NOT dispatch to a
  background agent. Two alternatives:
  1. **Foreground edit** — do the change directly in the main session.
  2. **Worktree isolation** — dispatch with `isolation: "worktree"`. The worktree
     operates on a clone outside the sandboxed `.claude/` of the main repo, so
     writes succeed; the orchestrator merges the result back.
- **Mid-task harness surprise** — if a background agent reports "permission
  denied" on a `.claude/` write it didn't expect to make, accept the partial
  result and finish the harness portion in the foreground. Do not retry the
  agent with the same prompt; it will fail identically.

### Mitigation pattern: split parallel work by path surface

When a single interaction touches both docs/source and `.claude/` harness files,
**split the work so `.claude/**` edits live in the foreground while pure
docs/source edits run concurrently as background agents**. Example from
2026-04-22: Problem A (knowledge module refactor under `docs/`) ran as a
subagent; Problem B (phase-advance refactor under `.claude/hooks/`) ran in the
foreground. The two finished in roughly the time of the longer one because
neither blocked the other.

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
