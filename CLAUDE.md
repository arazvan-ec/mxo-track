# mxo-track — Guide for Claude Code

<!-- GENERIC-START: Project identity section — adapt for any project -->
## What This Project Is

**transporte-tracking** (mxo-track) — Logistics tracking portal built on **Symfony 7.4 LTS** (strict lock, no 8.x components). Monorepo with `backend/` (Symfony) and `docs/`. Deployed on Railway.

The system tracks vehicles via Traccar integration, manages delivery routes with driver proof-of-delivery (POD), and provides real-time position updates via Mercure. Multi-tenant via `customer_id` Doctrine SQL filter.

**Core business value:** Route optimization — the business sells saved kilometers and saved time. Everything else (fleet management, multi-tenancy, portals, tracking) is infrastructure serving that goal.
<!-- GENERIC-END -->

<!-- PROJECT-SPECIFIC-START -->
### Tech Stack

- PHP 8.4, Symfony 7.4 LTS (Flex + recipes), PostgreSQL 16, Redis 7 (sessions), Mercure (SSE)
- Doctrine ORM 3.x with attribute mapping (`naming_strategy: underscore_number_aware`)
- Twig + Turbo for legacy frontend, React SPA for map views (`/app/*`)
- Traccar for GPS device tracking

### Common Commands

```bash
cd backend && composer install          # Install dependencies
php bin/console about                   # Verify Symfony is working
php bin/console doctrine:migrations:migrate -n  # Run migrations
php bin/console doctrine:fixtures:load -n       # Load fixtures
make lint                               # PHP syntax lint
make lint-shell                         # Shellcheck .claude/hooks/*.sh + scripts/*.sh
php vendor/bin/phpunit                  # Run tests
cd frontend && npm run build            # Frontend: TypeScript + Vite (EXACT deploy command)
```
<!-- PROJECT-SPECIFIC-END -->

---

<!-- GENERIC-START: Why the workflow exists — the mantra + the 4-test -->
## Why This Workflow Exists

LLMs do not autonomously apply development practices. Without external
discipline, the model's first instinct is to ship code that works — not
necessarily code that respects SOLID, was written test-first, learned
from past mistakes, or honors the architecture's boundaries.

This workflow exists to inject that discipline at the right moments,
with the right cost. Each phase forces a specific quality practice the
model wouldn't apply on its own:

| Phase | Practice forced |
|---|---|
| `consult` | Read past decisions and execution logs (don't repeat mistakes) |
| `brainstorming` | Propose ≥2 alternatives, Prior Art Audit, Architectural Adversarial Review |
| `planning` | Write TDD-shaped tasks, decompose for parallelism |
| `implementation` | Plan-bound; no scope creep without reclassification |
| `verification` | Tests + lint must pass before claiming success |
| `capture` | Write execution log so the next session can consult |
| `retrospective` | Reflect on estimate accuracy, process gaps, emergent patterns |
| `finalize` | Branch strategy declared; pre-push gate verifies completion |

Validators (`.claude/hooks/validators/*`) and edit-time hooks
(`classify-validator`, `ddd-boundary-check`, `pre-tool-freshness`) are the
**mechanism** — they block exit until the practice was applied.

Skills (Brainstorming, TDD, Verification, etc., catalogued in
`docs/knowledge/superpowers-skills.md`) are the **playbook** — loaded
only when the relevant phase is active, so context tokens are spent on
the right practice at the right moment.

### The 4-Test for Workflow Changes

Any proposal to add, remove, or modify a phase / validator / gate must
pass ALL of:

1. **Forces a quality practice the LLM wouldn't do spontaneously.**
   Without this gate, would the model still apply the practice? If
   yes — the gate is redundant ceremony.

2. **Injected at the right phase.** Not so early it's speculative
   (e.g., asking for code-level review before planning); not so late
   it forces rollback (e.g., catching architectural issues
   post-verification when refactor cost is high).

3. **Token cost proportional to value.** Every byte read, every regex
   evaluated, every section parsed must pay for itself in solution
   improvement. Reading a 200-line knowledge module to catch a 3-line
   bug class is wasteful; reading a 5-line YAML for the same is fine.

4. **Backed by a source.** Knowledge module, decision log, execution
   log, CLAUDE.md rule, or a cited external convention (SOLID, TDD,
   DDD, Conway's Law, etc.). Not invented ad-hoc. If you can't point
   to where the practice originates, the practice itself may not be
   load-bearing.

A change that fails any of the 4 is ceremony, not flow. Ceremony costs
attention and tokens without improving the solution — remove or
rewrite it until it passes.

### Recursive application

This 4-test applies to itself. The mantra + test were codified
2026-04-26 because: (1) the model would not articulate this reasoning
spontaneously each session [Test 1 ✓], (2) the test belongs at the
top of CLAUDE.md so it's read before any decision in every
interaction [Test 2 ✓], (3) ~80 lines justifies every gate that
follows in the file — favorable cost/value [Test 3 ✓], (4) grounded
in 6 execution logs (2026-04-21 through 2026-04-24) that documented
the same observations bottom-up [Test 4 ✓].
<!-- GENERIC-END -->

---

<!-- GENERIC-START: Core philosophy — applies to any project using this harness -->
## How Claude Thinks in This Repo

### Context Is a Scarce Resource

Claude Code assembles context once per conversation and memoizes it. Every token
loaded into the system prompt is a token unavailable for reasoning about your code.
This is why instructions are distributed across a hierarchy:

- **This file (root CLAUDE.md)** loads in every conversation — it contains philosophy,
  workflow, and cross-cutting rules
- **Subdirectory CLAUDE.md files** load only when working in that directory —
  `backend/CLAUDE.md` has architecture rules, `backend/src/CLAUDE.md` has implementation
  rules, etc.
- **AGENTS.md** loads when dispatching subagents — full instructions for reviewers,
  parallel agents, etc.

This way, answering a question costs ~400 tokens of instructions, not ~2000.

### The Manifest as Codebase Cache

#### Why a pre-computed snapshot, not live search

Every Grep call consumes context tokens — a search returning 50 results costs ~2000
tokens. Reading the manifest costs ~500 tokens for the same information. Over a session
with 10+ lookups, that's 15,000 tokens saved — tokens that become available for reasoning
about your code instead of storing search results. **The manifest exists because context
is a zero-sum budget: every token spent on discovery is a token unavailable for thinking.**

The alternative — grepping the codebase each time — also has a hidden cost: search results
lack structure. A grep for "Route" returns entity files, controllers, templates, tests,
and config mixed together. The manifest pre-organizes this into entity list, service map,
route map, and metrics, so the answer is immediately usable without further filtering.

#### How the manifest stays current

The manifest is regenerated with `make manifest` (~1 second). The flow has two paths:

**Fast path (0 tool calls):** Read `docs/codebase-manifest.md` or the relevant
`docs/knowledge/` module → the answer is there → use it directly. This is the common
case and the reason the manifest exists.

**Update path (1-3 tool calls):** The answer isn't in the manifest → directed search
(Grep/Glob with specific terms) → find the answer → **update the manifest before
continuing.** This last step is critical: if you found something the manifest didn't
know, the manifest is stale and must be fixed. Otherwise the next session hits the
same gap and wastes the same tokens rediscovering it.

This feeds everything downstream: brainstorming reads the manifest to inventory existing
functionality (Step 3 of the checklist), planning reads it for exact file paths,
verification regenerates it post-push to capture what changed.

### Session-State: Memory That Survives Compaction

#### How the feedback loop works

The session-state participates in a loop that runs every turn:

```
Model updates session-state (jq) → Hook reads session-state → Hook injects status line
    → Model sees its own state in the next turn → Model knows where it is
```

1. **Model writes state** with `jq` after each phase transition. `jq` is used because
   it's atomic (read-transform-write) — a manual Edit risks corrupting JSON mid-write.
2. **SessionStart hook** resets state daily but preserves `last_work_summary` so the
   model has previous session context on startup.
3. **UserPromptSubmit hook** reads the file and injects a status line between every
   tool call. This is how the model "remembers" across compaction — even if old messages
   are gone, the hook re-injects the current state into every new prompt.
4. **Phase-advance script** (`.claude/hooks/phase-advance.sh`) enforces legal phase
   transitions — no skips, no backwards. This prevents the model from writing arbitrary
   state that doesn't match the workflow.

**Full schema and update patterns:** `.claude/README.md`
<!-- GENERIC-END -->

---

<!-- GENERIC-START: Workflow classification and flow -->
## The Workflow: Every Interaction Has Structure

Every interaction follows a structured flow. The depth scales with the type, but the
structure is always present. **Why:** Unstructured work skips verification, loses context,
and accumulates technical debt silently.

### Classify First (before any response)

#### How classification produces the right flow

Classification writes to session-state → hooks read session-state → hooks block premature
code edits. Each type activates a different gate chain:

| Type | Signal | Flow activated | What the gates enforce |
|------|--------|----------------|----------------------|
| **Informational** | "what does X do?" | Micro — consult → answer → capture gaps | No code edits allowed (must reclassify if needed) |
| **Documentation** | Edit docs | Light — check overlap → execute → verify | No `src/` edits (must reclassify if scope grows) |
| **Bug fix** | Error, unexpected behavior | Debug — root cause → pattern-wide → TDD fix → verification → capture → retrospective | Blocks fix until root cause + pattern-wide search done |
| **Code change** | New feature, refactor | Full — consult → brainstorming → planning → implementation → verification → capture → retrospective → finalize | Blocks `src/` edits until consult + brainstorming + planning complete |
| **Exploration** | "audit X", "how does Z?" | Explore — manifest → explore → capture | No code edits allowed (must reclassify if needed) |

After classifying: `jq '.flow_type = "<type>"' .claude/session-state.json`

**Why "Exploration" is separate from "Informational":** Informational answers from existing
docs (0-1 tool calls). Exploration actively investigates the codebase (5-20 tool calls)
and captures findings. The distinction matters because exploration produces artifacts
(updated knowledge modules) while informational does not.

**Phase transitions:** Use `.claude/hooks/phase-advance.sh <next_phase>` to advance.
Direct writes to `phase_history` via `jq` are detected and reverted. The script enforces
legal sequence (no skips, no backwards) and adds timestamps automatically.

### Deviation for Wiring-Only Changes

#### How deviation mode works

Deviation skips brainstorm and plan but keeps everything else. The phases that remain —
consult, implement, verify, capture, retrospective, finalize — are the ones that catch
errors even in simple changes:

```
consult → [user approves deviation] → implementation → verification → capture → retrospective → finalize
   │              │                                                        │
   reads past     gate: model                                   catches patterns:
   decisions      presents evidence,                            "this is the 3rd page
                  user decides                                   missing onStopClick"
```

**Why consult is mandatory even for deviations:** The consult phase reads execution logs
and decision logs. Without it, the model doesn't know if this "simple" change was already
attempted and failed, or if a decision log explicitly chose a different approach. Consult
produces the context that makes the deviation assessment meaningful — skipping it means
the model evaluates complexity without knowing what it doesn't know.

**The retrospective is the most important phase for wiring changes** — it's where you
detect that a "simple" change is actually a symptom of a missing abstraction. Three
similar wiring fixes in a week means the codebase needs a structural fix, not a fourth
wiring patch. The retrospective feeds the learning loop: its output goes to the execution
log, which consult reads in the next session. Without it, the pattern is invisible.

#### The approval gate: criteria and flow

**Criteria (ALL must be met):**
- **< 30 lines** changed across all files
- **0 design decisions** — no new abstractions, patterns, or trade-offs
- **No new entities, migrations, or API endpoints**
- **Pattern already exists** in the codebase (copying an established approach)

These criteria exist because they are the conditions under which brainstorm produces zero
value: no alternatives to evaluate (the pattern already exists), no design trade-offs to
weigh (zero decisions), and not enough code to hide architectural mistakes (< 30 lines).
If any criterion fails, brainstorm has nonzero value and must not be skipped.

**Activation flow:**
1. Complete the `consult` phase — read decisions/execution logs. This is not optional.
   Consult produces the context that determines whether deviation is safe.
2. Present deviation request to the user with **concrete evidence** for each criterion:
   ```
   Propongo desviación (wiring-only):
   - Líneas estimadas: ~15 (< 30 ✓)
   - Decisiones de diseño: 0 — solo conectar X con Y (✓)
   - Nuevas entidades/migraciones/endpoints: ninguna (✓)
   - Patrón existente: [citar archivo:línea del ejemplo concreto] (✓)
   ¿Apruebas la desviación?
   ```
   The evidence must be specific: "pattern exists" requires a file:line reference to where
   the pattern is used, not a vague "similar things exist." "< 30 lines" requires a count,
   not "it's small." Vague evidence is not evidence — it's rationalization wearing a lab coat.
3. **Wait for explicit user confirmation.** Do not proceed. Do not start "reading files
   in the meantime." The gate is closed until the user opens it.
4. If user confirms → activate in session-state and proceed to implementation.
5. If user denies → continue with full flow (brainstorming + planning). No re-requesting.

**Activate (only after user confirmation):**
```bash
jq '.deviation = {"active": true, "reason": "wiring-only (<30 lines, 0 design decisions)", "skipped_phases": ["brainstorming", "planning"], "return_to_phase": null, "acknowledged_by_user": true}' \
  .claude/session-state.json > /tmp/ss.json && mv /tmp/ss.json .claude/session-state.json
```

### Full-Flow: The 8 Phases

Each phase produces something that feeds the next. The workflow engine (hooks in
`.claude/hooks/`) blocks code edits if prior phases aren't completed.

```
consult → brainstorming → planning → implementation → verification → capture → retrospective → finalize
   │           │              │           │              │          │            │            │
   │     produces spec  produces    follows          runs       writes     reflects    merges/
   │     with design    plan with  TDD       tests   execution  on what     creates PR
   │     + approval     tasks      cycle     + lint  log        worked
   │                                                            
   reads decisions,                                             
   execution logs,                                              
   retrospectives                                               
```

**Retrospective visibility rule:** The retrospective MUST be presented to the user as a
visible message BEFORE writing it to the execution log. The retrospective is not a
formality to file away — it's a conversation with the user about what worked and what
didn't. Three mandatory points:
1. **Estimate accuracy** — estimated vs. actual (lines, files, time). Root cause of any gap.
2. **Process gap** — what allowed something to go wrong or deviate? What's the fix?
3. **Emergent patterns** — any new pattern? If 3+ occurrences, graduate to knowledge module.

Only after the user has seen the retrospective, write it to the execution log.

**Scope change detection:** If the user requests something NOT in the current plan,
it's a new interaction. Increment `interaction_id`, reclassify, restart the flow.

### Why Debug Needs Capture + Retrospective

The debug flow's phases after verification serve a different purpose than in the full flow:

```
root_cause → pattern-wide → fix → verification → capture → retrospective
   │              │           │         │            │            │
   finds the     prevents    TDD     proves      writes       asks: "what
   real cause    recurrence  cycle   the fix     execution    process gap
                                     works       log          let this bug
                                                              reach prod?"
```

**Capture in debug** writes the execution log with: root cause, why it wasn't caught
earlier, the fix, and which files changed. This feeds future consult phases — "has this
type of bug happened before?" Without it, the same bug class reappears because no one
remembers the previous instance.

**Retrospective in debug** asks the meta-question: "What process gap allowed this bug to
exist?" A bug is a symptom of two failures — the code failure AND the process failure that
didn't catch it. The code fix addresses the first. The retrospective addresses the second
by updating CLAUDE.md, knowledge modules, or verification procedures.

**Example:** `tsc --noEmit` passed locally but `tsc -b` failed in deploy. The code fix
was trivial (2 lines). The process fix — "always run the exact deploy command" — prevents
an entire class of future bugs. Without retrospective, only the 2-line fix happens.

### Fix Invalidation

If a fix doesn't work, the root cause analysis was wrong. Don't iterate on a failed
hypothesis — reset completely and re-examine with fresh eyes:

```bash
jq '.interaction_id = (.interaction_id + 1) | .evidence.root_cause_identified = false | .evidence.pattern_wide_search_done = false | .evidence.tests_passed = null | .current_phase = "root_cause"' \
  .claude/session-state.json > /tmp/ss.json && mv /tmp/ss.json .claude/session-state.json
```

### Workflow Engine (summary)

#### What each gate blocks

| Flow | Gate | What it prevents |
|------|------|-----------------|
| micro/light/explore | DENY edits to all business paths (`src/`, `templates/`, `config/`, `migrations/`, `assets/`, `tests/`, `docker/`, `scripts/`, `ml-service/`, `openspec/`) | Scope creep — a "quick look" turning into unplanned code changes without design review |
| debug | HARD: needs root_cause + pattern-wide; capture + retrospective post-fix (all business paths) | Symptom fixes — patching what's visible without understanding what's broken. Skipping retrospective — losing the process fix that prevents recurrence |
| full | HARD: needs consult + brainstorming + planning (all business paths, not just `src/`) | Cowboy coding — implementing the first idea without evaluating alternatives or checking existing patterns |

The gates are deliberately strict. A false negative (blocking a legitimate edit) costs
minutes to reclassify. A false positive (allowing an unreviewed edit) costs hours to
debug the resulting regression.

#### Enforcement gates — shortcuts they catch

The workflow engine pairs each CLAUDE.md discipline rule with a hook that
enforces it. Twelve concrete shortcuts the model is prone to taking, each
mapped to the gate that blocks it:

| Shortcut | Gate that catches it |
|----------|---------------------|
| Calling framework changes "light" to skip brainstorm | `classify-validator.sh` (Layer A) — blocks edits to `.claude/`, `scripts/`, `backend/src/`, `frontend/src/`, `ml-service/`, `docker/` unless classification is `full` or `debug` |
| `consult → brainstorm` without reading decisions/logs | `consult-validator.sh` (Layer B) — requires BOTH `decisions_read=true` AND `logs_scanned=true` |
| `brainstorm → planning` without alternatives or approval | `brainstorm-validator.sh` — requires `alternatives_proposed`, `user_approved`, `spec_path`, ≥1 user turn |
| **Spec mirrors tech-debt pattern** without acknowledging | `brainstorm-validator.sh` (Layer H, HARD) — when spec references `src/Domain/{Route,Shipment}/` or `src/Controller/Api/Admin/`, requires a `## Prior Art Audit` section with at least one row classified as ✅, ❌ tech-debt, or `new` |
| **Spec lacks adversarial architectural review** | `brainstorm-validator.sh` invokes `socratic-review-validator.sh` (Layer C, HARD) — when spec references critical paths, requires a `## Architectural Adversarial Review` section with ≥3 numbered Q/A entries (each ≥30 char); at least one question must include an architectural keyword (endorsed, boundary, DDD, tech-debt, architecture, coupling, pattern, tradeoff). Relocated 2026-04-24 from post-verification phase to spec-exit to eliminate rollback cost. |
| **Mentions ungraduated pattern name** in spec | `brainstorm-validator.sh` (Layer J, SOFT) — warns when a pattern name appears in the spec but is absent from `docs/knowledge/_graduations.yaml` |
| **Edit adds ORM coupling in critical context** | `ddd-boundary-check.sh` (Layer F, WARNING) — reads `docs/knowledge/_ddd-boundaries.yaml`; emits a warning when a non-Infrastructure edit adds `createQueryBuilder` or `getRepository` against a critical aggregate (Route, Shipment). Known violations are exempted. |
| `verification → capture` without running tests/lint | `verification-validator.sh` — `tests_passed` and `lint_clean` must be `true` (no `skipped` in full/debug) |
| `capture → retrospective` without writing the execution log | `capture-validator.sh` (Layer B, HARD) — `execution_log_path` must be set and file must exist |
| **Retrospective omits architectural concern** | `retrospective-validator.sh` (Layer I, HARD) — Lessons section must mention adversarial question, prior art, DDD, boundary, coupling, architectural concern, endorsed/tech-debt, OR set `evidence.retrospective_no_architectural_concerns=true` with justification |
| `retrospective → finalize` without presenting retrospective to user | `retrospective-validator.sh` — requires `evidence.retrospective_shown=true` flag set after visible chat presentation |
| Forgetting to advance `problems.current` when switching petitions | `todowrite-mirror.sh` (Layer C) — auto-derives `problems.current` from `[prefix]` of active todo |
| Multiple `in_progress` todos at once | `todowrite-mirror.sh` (Layer C) — rejects input with >1 `in_progress` (exit 2) |
| Stale session-state when committing/writing artifacts | `pre-tool-freshness.sh` (Layer D, non-blocking) — emits `⚠ POSIBLE STALE STATE:` warning when upcoming tool call signals inconsistency |

#### Bypass env vars (documented escape hatches)

Every HARD gate has a documented bypass for false positives. Using a bypass
requires an entry in `docs/decisions/log.md` explaining the case.

| Env var | Effect | When to use |
|---------|--------|-------------|
| `SKIP_CLASSIFY_GATE=1` | Disables `classify-validator.sh` | Emergency edits to framework paths when reclassification has already been discussed but session-state is stuck |
| `SKIP_PHASE_EXIT_GATE=1` | Disables all phase exit validators in `phase-advance.sh` (incl. consult, verification, socratic-review, capture, retrospective) | Recovery from corrupted evidence state; rebuild session after interruption |
| `SKIP_DDD_BOUNDARY_GATE=1` | Disables `ddd-boundary-check.sh` | Edits that legitimately touch critical contexts without adding new ORM coupling (e.g., refactoring existing violations); decision log entry describing why required |

Never bypass without thinking. A gate that blocks legitimate work is a gate
that needs its conditions tuned — not a gate to silence.

**Full reference:** `.claude/README.md` (gates, validators, deviation mode, harness assumptions)
<!-- GENERIC-END -->

---

## Autonomy Contract

The harness has structured checkpoints where the user's judgment is
obligatory. **Between checkpoints, the model operates autonomously without
per-tool-call confirmation.** The goal: concentrate the user's attention
on high-value decisions (design, scope, retrospective, merge) instead of
low-value interruptions (individual file edits, local commits, running
tests).

This contract exists because the repo observed repeatedly that per-edit
prompts were redundant with phase-level approvals — the user was
gatekeeping at both levels, paying the attention cost twice.

### Requires user input (always)

- **Design approval** during brainstorming (spec sign-off before planning)
- **Scope changes** — any user request outside the current plan triggers
  a new interaction; the model must reclassify
- **Retrospective approval** before advancing to finalize (visible
  presentation, user acknowledgment)
- **Destructive git operations** — `reset --hard`, `push --force`,
  `branch -D`, amending published commits
- **Side effects on shared systems** — GitHub PRs, pushes to `main`,
  Slack/email messages, external API calls, uploads to third-party tools
  (pastebins, gists, diagram renderers)
- **Bypass env vars** — using `SKIP_*_GATE=1` requires an entry in
  `docs/decisions/log.md` explaining the case

### Does NOT require user input (autonomy)

- **File edits** inside the plan's scope — create, modify, delete any
  file the plan lists or that the brainstormed approach implies
- **Tests, lint, build, manifest** — run `make lint`, `npm run build`,
  `phpunit`, `make manifest`, `bash .claude/hooks/test-*.sh` freely
- **Local git** — `git add`, `git commit`, `git branch <create>`,
  `git push origin <feature-branch>`
- **Read / search / explore** — Read, Grep, Glob, read-only Bash
- **Subagent dispatch** for parallel work already planned
- **`jq` updates** to `session-state.json` for phase and evidence
  advancement (these are the model's own state, not user-facing data)
- **Writing spec / plan / execution-log / retrospective** docs under
  `docs/superpowers/`
- **Regenerating `.claude/session-state.json`** during session bootstrap
  or phase transitions

### Mechanism

Two layers, orthogonal:

1. **Claude Code permission layer** (`.claude/settings.local.json`)
   controls whether the TOOL surfaces a permission prompt. Recommended
   config for smooth flow:
   ```json
   { "permissions": { "defaultMode": "acceptEdits" } }
   ```
   This auto-approves Edit/Write; destructive Bash and external-effect
   tools still prompt.

2. **Harness hooks** (`.claude/hooks/**`) enforce architectural discipline
   regardless of permission settings. They run orthogonally — auto-approve
   does NOT defeat classify-validator, brainstorm-validator, F/H/I/J, or
   the pre-push-gate.

The combination: user grants the TOOL level autonomy (no prompts); the
HARNESS keeps the architectural checkpoints. The user stays decisive
where it matters.

### When the model should still ask

Inside "autonomy" scope above, there are still situations where the model
pauses and asks:
- **Ambiguous spec** — approach could be interpreted two ways; pick one
  is risky, ask user which.
- **Discovered scope creep** — implementation touches a file not in the
  plan and the model can't tell if it should include it.
- **Tool failure with unclear recovery** — `git rebase` conflict, `npm
  install` fails, etc. — surface to user.
- **Architectural decision that wasn't in the brainstorm** — e.g., a
  shared component modification (this is already called out in "Shared
  Component Modifications" — ALWAYS stops and re-brainstorms).

Asking is not ceremony in these cases; it's honest escalation. The
contract is not "never ask" — it's "ask only when the answer materially
changes the outcome."

---

<!-- GENERIC-START: The QA loop -->
## Before Writing Code: The QA Loop

### Why Brainstorming Is Not Bureaucracy

#### The cost asymmetry that justifies the overhead

In conversation, changing approach costs zero: no lines deleted, no tests broken, no
commits reverted. In code, every approach change is a `git reset` — tests rewritten,
interfaces re-designed, dependent code updated. **Brainstorming exploits this asymmetry:
it moves design failures from code-time (expensive) to conversation-time (free).**

The model's natural bias is "I understand the problem, let me code." But understanding
and designing are different activities. The model can understand a problem perfectly and
still choose a suboptimal approach because it didn't inventory existing functionality,
didn't consider alternatives, or didn't check how similar problems were solved before.

#### How the checklist produces a validated design

Each step produces an artifact that the next step needs. The order is not arbitrary —
it's a dependency chain:

0. **Consult past decisions** — Read `docs/decisions/log.md` and recent execution logs.
   → produces: context about what was tried before and why.
   This prevents re-discovering lessons the codebase already learned. Without it, the
   model proposes approaches that were previously tried and rejected.

1. **Classify bounded context** — Is this critical (DDD pure) or pragmatic (Symfony)?
   → produces: the architectural style for this change.
   This determines whether to use value objects or primitives, domain events or direct
   calls, repository interfaces or Doctrine queries. Getting it wrong means rewriting
   after review.

2. **Explore project context** — Check files, docs, recent commits.
   → produces: awareness of recent changes that might conflict or overlap.

3. **Inventory existing functionality** — Enumerate what exists in the affected area.
   → produces: a map of every element with an explicit decision: Include / Omit / Transform.
   **Why explicit decisions?** Silent omissions are the #1 brainstorming bug. The model
   "forgets" that a component exists, designs without it, and the implementation collides
   with it. The Include/Omit/Transform decision forces acknowledgment of every element.

4. **Ask clarifying questions** — One at a time. Multiple choice when possible.
   → produces: resolved ambiguities. Multiple choice is preferred because it constrains
   the answer space — "Do you want A, B, or C?" gets a decision faster than "What do
   you want?"

5. **Propose 2-3 approaches** — With trade-offs and recommendation.
   → produces: evaluated alternatives. This is where the consult (step 0) and inventory
   (step 3) pay off — the approaches account for past decisions and existing code.

6. **Present design, get approval** — Section by section.
   → produces: validated design with user sign-off.

7. **Write spec** — Save to `docs/superpowers/specs/YYYY-MM-DD-<topic>-design.md`
   → produces: persistent artifact that planning and implementation reference.

8. **Transition to planning** — The spec feeds directly into the planning phase.

**The spec must include:**
```markdown
## Existing Functionality Inventory
[List of existing elements, or "No existing functionality affected"]

## Omission Decisions
| Element | Decision | Justification |
```

### Planning: v0 → Mature

**Why two phases:** Validating a concept is separate from perfecting its architecture.
v0 proves the solution works. Phase 2 refactors toward production quality. Tests from
v0 are the safety net — they must stay green throughout Phase 2.

Plans go to `docs/superpowers/plans/YYYY-MM-DD-<feature>.md` with:
- Phase 1 (v0): simplest working implementation with tests
- Phase 2 (Mature): refactor toward target architecture
- Each task follows TDD: write test → verify fail → implement → verify pass → commit
- **Never create a separate "add tests" task.** Tests are integral to each task via TDD —
  writing the test IS the first step of implementing the task, not a task on its own.

### Parallel-First Planning

#### Why parallel, not sequential

Sequential plans are the natural default — list steps in order, execute top to bottom.
But sequential plans hide a critical design flaw: **they don't reveal which tasks are
truly dependent and which are merely ordered by habit.** When you force yourself to
identify what can run concurrently, you discover the real dependency graph of the work.
This has three effects:

1. **Surfaces hidden coupling.** If two tasks "feel" sequential but have no data
   dependency, the sequentiality was masking unclear interfaces between them.
2. **Forces cleaner task boundaries.** Parallel tasks can't share mutable state, so
   each task must have well-defined inputs and outputs — this is better design.
3. **Reduces wall-clock time.** A 6-task plan where 4 tasks are parallel might execute
   in the time of 3, not 6.

The alternative — "just list tasks in order" — works for small changes but collapses at
scale: a 12-task plan executed sequentially takes 12x, while the same work decomposed
into 4 parallel waves takes 4x. **This is why parallel decomposition is a design
technique, not an optimization to apply later.**

#### How parallel decomposition works

The technique is a three-step analysis that produces a dependency graph:

**Step 1: Atomic decomposition.** List every discrete unit of work without thinking
about order. Each unit should touch a single concern (one entity, one component, one
test file). This produces a flat list.

**Step 2: Dependency identification.** For each pair of tasks, ask: "Does task B need
the *output* (file, type, API) that task A creates?" If yes, B depends on A. If B
merely comes "after" A by convention, there is no dependency. **"It's easier sequentially"
is not a dependency.** This produces a directed acyclic graph (DAG).

**Step 3: Wave grouping.** Group tasks into waves (parallel groups). Wave 1 has all
tasks with zero dependencies. Wave 2 has tasks whose dependencies are all in Wave 1.
And so on. The **parallel frontier** at each wave is the number of concurrent tasks —
maximize this number. If a wave has only 1 task, look for ways to split it.

This analysis feeds directly into the plan document:

```markdown
### [parallel] Wave 1: Tarea 1a + 1b + 1c
- **1a:** Backend — add field to snapshot (backend/src/...)
  → produces: updated entity, migration
- **1b:** Frontend — extend TypeScript type (frontend/src/...)
  → produces: updated interface
- **1c:** Tests — add fixture data for new field (backend/tests/...)
  → produces: test fixtures

### Wave 2: Tarea 2 (needs 1a outputs + 1b outputs)
- Frontend — use new fields in component
  → produces: working component with new data

### [parallel] Wave 3: Tarea 3a + 3b (needs Wave 2)
- **3a:** Backend — add validation rule
- **3b:** Frontend — add error display component
```

Each task declares what it **produces** (→), which makes dependencies explicit and
auditable. When a new developer reads the plan, they understand *why* Wave 2 waits
for Wave 1 — not because "it comes next" but because it needs specific artifacts.

#### Execution: waves become Agent dispatches

When executing, each wave maps to a set of concurrent Agent tool calls. Wave 1 launches
all its tasks simultaneously. When all Wave 1 agents complete, Wave 2 starts. This is
mechanical — the plan's structure directly dictates the execution pattern.

**Critical rule:** Never dispatch agents from different waves concurrently. The wave
boundaries exist because of real data dependencies — violating them causes merge
conflicts or compile errors from missing artifacts.

**Self-check during planning:** At every wave boundary, ask: "What is the maximum
number of tasks that could run right now?" If the answer is 1 and the plan has > 4
tasks total, the decomposition likely missed parallelism. Revisit Step 1.

#### Multi-task requests: parallel by default

When the user requests multiple tasks in one message, they are independent by definition
— the user wouldn't bundle them if they were sequential. **Execute them in parallel, not
sequentially.** The mechanism:

1. **Independent tasks → background agents.** Launch each task as a background agent
   (`run_in_background: true`). Small tasks (<5 lines, hooks/config) can use
   `isolation: "worktree"` or direct edit if no conflict risk. Continue with the next
   task immediately — don't wait.
2. **Within a single task's plan, waves are parallel.** Each wave's independent tasks
   launch as concurrent agents. Don't serialize tasks within a wave.
3. **Dependency test before serializing:** For each pair of tasks, ask: "Does task B
   import, read, or reference a file/type/artifact that task A creates?" If yes → B
   depends on A, serialize them. If no → they are independent, run in parallel.
   **File conflict rule:** Two tasks that edit the same file cannot run in parallel —
   the second agent gets "file modified since read" and fails. During planning, verify
   that parallel tasks touch **disjoint file sets**. If they share a file, put them in
   sequential waves.

**The failure mode this prevents:** The user says "do A and B in parallel" and the model
does A completely, then B completely — burning twice the wall-clock time while claiming
both are done. The user sees through this because the response time is 2× what parallel
would take. **Sequential execution of independent tasks is a process bug, not a style
preference.**

**Detail:** TDD rules in `backend/src/CLAUDE.md`, debugging rules in `backend/src/CLAUDE.md`

### Task Progress Tracking

#### How task_progress feeds the status line

The model writes task_progress → the UserPromptSubmit hook reads it → the hook injects
it into the status line → the model sees its position in the next turn.

Three transitions maintain the counter:
1. **Enter implementation:** `phase-advance.sh implementation` auto-runs
   `plan-progress.sh init` when `task_progress.total == 0` and `plan_path` is set.
   Manual invocation (`bash .claude/hooks/plan-progress.sh init`) is only needed if
   the plan was written after entering implementation.
2. **Start each new task:** `bash .claude/hooks/plan-progress.sh advance <task_id>`
   (e.g. `advance 1a`) advances `current`. Before advancing the next task, run
   `plan-progress.sh complete` to archive the current label.
3. **All tasks done:** `plan-progress.sh complete` on the last task auto-resets
   `current` and `label` to null.

This produces: `📍 full | Implementation (4/8) | ✅🔄⬚⬚⬚ t2/5: Add toggle button`

**Alternative — TodoWrite-driven flows:** if you use TodoWrite instead of (or
alongside) a parsed plan, `todowrite-mirror.sh` automatically mirrors the todo
list to `task_progress` — total = item count, current = completed + 1, label =
active form of the `in_progress` item. The mirror is suppressed if
`task_progress.task_index` is populated (plan is authoritative). Use TodoWrite
for process/wiring flows where tasks are enumerated ad-hoc rather than parsed
from a plan with waves.

#### Multi-problem tracking via work_context.problems

When the user requests 2+ problems in one interaction, use `work_context.problems` to
track which problem is active:

```bash
jq '.evidence.work_context.problems = {"total": 2, "current": 1, "labels": ["ListFilterApplier refactor", "Regex aprobación"]}' \
  .claude/session-state.json > /tmp/ss.json && mv /tmp/ss.json .claude/session-state.json
```

Advance `current` when switching problems. The hook reads `problems.labels[current-1]`
and includes it in the status line. **Every message header and status update must prefix
the problem name when `problems.total >= 2`.** Without this, the user cannot tell which
problem the progress corresponds to.

### Message Progress Display

#### Why visible progress, not just internal state

The session-state hook injects a status line between tool calls, but the user only sees
it as a system annotation — not as a deliberate communication from the model. **The
progress header in each message is the model's commitment to transparency:** it proves
the model knows where it is and what it accomplished, not just that a hook read a JSON file.

Without explicit progress, long tool chains appear as silence. The user sees 30 seconds
of "thinking..." with no indication of whether the model is stuck, working, or lost.
The progress header breaks this opacity.

**Principio clave: RESULTADO, no proceso.** Los mensajes comunican qué se completó y qué
sigue — nunca el proceso interno de pensamiento. El usuario no necesita saber "voy a leer
archivos" o "necesito entender X". Solo necesita saber qué se hizo y qué falta.

**PROHIBIDO:**
- Narrar intenciones: "Voy a hacer...", "Necesito ver...", "Primero tengo que..."
- Repetir lo obvio: "Tengo toda la info", "Ahora voy a implementar"
- Mensajes sin contenido útil: solo texto que describe lo que harás a continuación

**CORRECTO:** Cada mensaje incluye (1) qué se completó con dato concreto, (2) qué sigue.

**Formato jerárquico de progreso:** Los mensajes reflejan la jerarquía completa del
trabajo. La jerarquía tiene hasta 4 niveles, y **siempre se muestra el nivel más alto
que tenga más de 1 elemento:**

```
problema (solo si hay 2+ problemas en la interacción)
  └─ fase del flujo
       └─ wave
            └─ tarea concreta
```

**Regla clave: cuando hay múltiples problemas, SIEMPRE identificar cuál.** Sin esto el
usuario no sabe a qué problema corresponde el progreso. El nombre del problema es el
nivel raíz del header.

**Estructura del mensaje de progreso:**
- **Línea 1:** [problema si 2+] · wave actual + fase del flujo
- **Línea 2:** tarea concreta + qué se está construyendo

**Con problema único (formato habitual):**
```
🔄 Wave 2/4 · Fase 1: Rutas + Vehículos (Implementation 4/8)
📍 Tarea 5/10: RouteListApiController — endpoint con filtros
```

**Con múltiples problemas (siempre prefijo del problema):**
```
🔄 [ListFilterApplier] Wave 2/4 · Implementation (3-5/7)
📍 Tareas 3a-3c: Refactor 3 controllers simples

✅ [Regex aprobación] Completado — 1 línea en user-prompt-state.sh
```

**Wave completada → siguiente:**
```
✅ [ListFilterApplier] Wave 2/4 — Service + value object creados, lint limpio
🔄 [ListFilterApplier] Wave 3/4 — 2 controllers complejos (Shipment + Route)
```

**En verificación:**
```
🧪 Verificación — TypeScript: ✅ | Lint: ✅ | Tests: ✅ (602 tests, 0 nuevos fallos)
```

**Resumen final (solo lo nuevo de esta interacción, no repetir lo ya mergeado):**
```
✅ Fase 2 completada — 12 archivos, +709 líneas. PR #237.

| Página | Filtros | Mobile card |
|--------|---------|-------------|
| Envíos | Cliente dropdown | PriorityBadge 5 niveles + carga |
| Clientes | — | ActiveBadge + conteo usuarios |
| Conductores | — | ActiveBadge + acción Horario |
```

**Commit/push:** Solo resultado, no output de git.
```
Committed y pusheado — 6 archivos, +374 líneas.
```

**NUNCA repetir fases ya completadas en el texto** — el status bar del hook ya las muestra.
"✅ Consult + Brainstorm + Planning completados" es redundante. Solo comunicar la fase
actual con su contexto de plan/wave/tarea.

**PROHIBIDO entre tool calls:** NO emitir texto entre herramientas consecutivas salvo que
haya un resultado concreto nuevo. Esto incluye:
- NO repetir el status line entre Grep/Read/Bash/ToolSearch — el hook ya lo inyecta
- NO emitir texto antes de ToolSearch (cargar herramientas es técnico, no visible)
- NO emitir texto entre ToolSearch y la herramienta que se carga (son un par atómico)
- NO narrar pasos intermedios: "voy a leer...", "ahora busco..."

**Solo emitir texto entre tool calls cuando:**
- Hay un **cambio de fase** real (root_cause → pattern_search → fix)
- Hay un **resultado concreto** que comunicar al usuario
- Se necesita **decisión del usuario** antes de continuar

**Regla de estado anticipado:** El hook `UserPromptSubmit` inyecta el status line
automáticamente entre tool calls. Por tanto, actualiza `session-state.json` **ANTES** de
ejecutar la acción que cambia la fase, no después. Ejemplos:
- Antes de crear un PR → actualizar `branch_strategy = "pr"` y `current_phase = "finalize"`
- Antes de hacer push → actualizar `tests_passed`, `lint_clean`
- Antes de marcar root cause → actualizar `root_cause_identified`

Así cuando el hook se dispare entre tools, mostrará el estado correcto y no uno stale.
Esto es especialmente importante en pares atómicos como ToolSearch → herramienta MCP,
donde el hook se dispara entre ambos y muestra el estado al usuario.

**Granularidad:** Actualiza el estado con la mayor frecuencia posible — no solo en cambios
de fase, sino antes de cada grupo de tool calls significativo. El usuario ve el status line
entre CADA herramienta, así que cada disparo es una oportunidad de comunicar qué se está
haciendo. Ejemplo durante debug:
- Antes de investigar → `current_phase = "root_cause"` con label de qué se investiga
- Antes de aplicar fix → actualizar `root_cause_identified = true`
- Antes de correr tests → actualizar a fase de verificación
- Antes de push → actualizar `tests_passed`, `lint_clean`

**Header de respuesta:** Inicia CADA respuesta con el header apropiado al tipo de flujo.
En flujos que no son full/debug, usar formato simple. En full/debug, usar el formato
jerárquico (wave · etapa · fase). Ejemplos por tipo:
```
💬 El endpoint devuelve 404 porque falta la ruta en routing.yaml
📝 Light — Eliminados 2 imports no usados en RoutePlannerPage, TS limpio
🐛 Debug (fix) — TS6133 por import no usado, eliminado, build pasa
🔍 Explore — 8 controllers encontrados, 3 usan HubInterface directamente
🔄 Wave 2/4 · Fase 1: Rutas + Vehículos (Implementation 4/8)
```

#### Why the status line is compact (not verbose)

The hook output is deliberately minimal (~36 tokens/turn). Previous versions used a
verbose format with decorators, `Evidence: key=value` pairs, and a full `DISPLAY RULE`
template (~88 tokens/turn). Over 20 turns, that's 1,760 vs 720 tokens — a 1,000-token
difference spent on status alone, tokens unavailable for reasoning.

**Why no DISPLAY RULE per turn:** This file already instructs the response format (loaded
once). Repeating it every turn was redundant. A one-line `Header:` template (~8 tokens)
in the hook output serves as post-compaction reminder without the verbosity.

**Why readable done/todo instead of key=value:** `Evidence: decisions=Y user_turns=2`
is opaque — the model must decode it, the user can't read it. `✅ consult, dialogo(2)`
communicates the same state directly. Same information, zero decoding overhead.

**Why no decorators:** `── WORKFLOW STATE ──` and `────` cost ~10 tokens/turn with zero
information. The `📍` prefix already signals status.

**NUNCA revertir a formato verbose.** Si necesitas agregar información al status line,
agrégala como una línea done/todo legible, no como key=value. Si necesitas una instrucción
de formato, usa la línea `Header:`, no un bloque DISPLAY RULE multilínea.

**Formato:** Usar prefijos emoji (✅ completado, 🔄 en curso, ⬚ pendiente, ❌ fallo)
para que el estado sea visible de un vistazo. Idioma: español.
<!-- GENERIC-END -->

---

<!-- GENERIC-START: Verification and closure -->
### Shared Component Modifications

Mid-implementation, if you need to modify a component consumed by more than one file
(a UI primitive, a base class, a registry schema, a shared interface), **STOP editing**.
Shared modifications silently widen the scope beyond what was brainstormed.

The sequence is:

1. **Do not edit.** Revert any partial change to the shared component.
2. **Update the spec** with the new requirement and the reason it emerged.
3. **Re-enter brainstorming** with the user. Present the new requirement, alternatives
   you've considered (including "don't modify the shared component — work around it"),
   and ask for approval.
4. **Only after approval,** make the change.

Why this matters: an unreviewed extension to a shared API becomes load-bearing the
moment a second consumer uses it. Rolling back becomes expensive. The brainstorming
gate caught the full design once — bypassing it mid-impl means the new design is
never reviewed.

Examples of "shared component" in this repo:
- `CollapsibleWidget`, `BottomSheet`, `AnimatedCounter` and other UI primitives
- `WidgetProps`, `WidgetRegistryEntry` and other public types
- Base classes like `AbstractController` extensions, Domain service interfaces
- Registry/enum schemas (`WidgetType`, `PageKey`, `SheetState`)

Counter-example (not shared): a private helper used by only one component — free to
modify.

## After Writing Code: Verify and Close

### Evidence Before Claims

#### The cognitive trap this prevents

After writing code, the model has a confirmation bias — it "believes" the code works
because it wrote it with intent. This bias produces claims like "tests pass" based on
expectation rather than evidence. The model reasons: "I wrote correct code → therefore
tests pass" — but this syllogism skips the step where reality might disagree.

**The verification command is a circuit breaker against confirmation bias.** It forces
the model to consult reality (command output) instead of consulting its own confidence.

#### Why "fresh, not cached"

A previous test result may have been invalidated by subsequent changes. Reading old
output and claiming "tests pass" is not verification — it's memory. **Freshness is the
difference between evidence and recollection.** The command must run in the current
message, after the latest code changes, with full output read.

#### The gate function

1. **Identify** — What command proves this specific claim?
2. **Run** — Execute the full command (fresh, not cached, not partial)
3. **Read** — Complete output, check exit code, count failures
4. **Only then** — Make the claim

Skip any step = the claim is unverified. "Should work," "probably passes," and "seems
fine" are all synonyms for "I didn't check."

#### Run the deploy command, not approximations

**Always run the EXACT command that CI/deploy executes.** Approximations with different
flags silently diverge from production checks:

| Wrong (approximation) | Right (exact deploy command) | Why it diverges |
|---|---|---|
| `npx tsc --noEmit` + `npx vite build` | `cd frontend && npm run build` | `tsc --noEmit` ≠ `tsc -b` (build mode uses project references, stricter) |
| `php -l src/` | `make lint` | Make target may include additional checks |
| Running tools separately | Running the combined pipeline | Intermediate failures get swallowed |

**The rule:** If the deploy runs `npm run build`, verification runs `npm run build`.
If CI runs `make lint && php vendor/bin/phpunit`, verification runs that exact sequence.
Never substitute individual tools for the pipeline — they may have different configs,
flags, or strictness levels.

### Closing the Cycle

#### Why capture matters: the learning loop

Without capture, each session starts from zero. The model re-discovers the same lessons,
re-evaluates the same alternatives, re-encounters the same blockers. **Capture is what
makes iteration N+1 better than iteration N** — it converts ephemeral session knowledge
into persistent artifacts that brainstorming reads at the start of the next session.

The mechanism is specific: Step 0 of the brainstorming checklist ("Consult past decisions")
reads execution logs and decision logs. If those logs don't exist or are poorly structured,
Step 0 produces nothing and the brainstorming proceeds blind. **The quality of today's
capture determines the quality of tomorrow's brainstorming.**

```
brainstorming → spec → planning → implementation → verification → capture
     ↑                                            │
     └────────── learning loop ───────────────────┘
```

#### Why one log per feature, not per session

When future brainstorming searches for "how did we solve something similar?", it needs
the log for *that specific feature* — not a session log mixing 3 unrelated features where
the relevant one is buried on page 4. **Granularity determines searchability.** One log
per feature means each is independently consultable with a single file read.

**Finish the branch** (Skill 12): verify tests → validate merge with base → present options
(merge/PR/keep/discard) → design retrospective → cleanup.

**Capture to execution log:** `docs/superpowers/execution-logs/YYYY-MM-DD-<feature>.md`
with data from each phase (alternatives considered, blockers hit, test results, lessons).

**Update decision log:** If design decisions were made, add entry to `docs/decisions/log.md`.
When the same lesson appears 3+ times across execution logs, it graduates to the relevant
knowledge module — that's the signal it's a pattern, not an incident.

**Graduating a pattern (the blessed path):** use `scripts/graduate.sh <name>
--module=<file> --section=<heading>` to atomically register the graduation in
`docs/knowledge/_graduations.yaml`. The script validates (a) the module exists,
(b) the section appears as heading in that module, (c) the tag/pattern has ≥3
occurrences in logs. `pattern-audit.sh` runs at `retrospective → finalize` and
surfaces candidates with a ready-to-paste `graduate.sh` command. Add `--pattern`
to register under `patterns:` instead of `tags:`.

### Querying past execution logs: `consult.sh`

Execution logs carry YAML frontmatter (`type`, `tags`, `files_touched`, `patterns`, `outcome`).
During Step 0 of brainstorming, use `.claude/hooks/consult.sh` instead of manual grep:

```bash
.claude/hooks/consult.sh file frontend/src/index.css  # logs that touched this file
.claude/hooks/consult.sh tag glass-overlay            # logs tagged with this
.claude/hooks/consult.sh pattern tailwind-override    # logs documenting this pattern
.claude/hooks/consult.sh stats                        # tag frequency + 3+ alerts
```

Output: `date | type | outcome | filename | title` (parseable).
<!-- GENERIC-END -->

---

<!-- GENERIC-START: Subagent skill triggers -->
## Working with Subagents

#### Why subagents instead of doing everything in the main context

The main context window is finite. A subagent gets a fresh context for its specific task,
executes it, and returns only the result — not the 50 intermediate tool calls it took.
This protects the main window from pollution by large searches or multi-file edits.

But subagents have costs: setup overhead (~10s), no access to conversation history, and
permission constraints (background agents can't prompt for approval). **Use subagents for
tasks requiring >20 lines of new code. For 1-2 line edits, do them directly** — the setup
overhead exceeds the benefit.

#### When to use each pattern

| Pattern | When | Why not the alternative |
|---------|------|----------------------|
| **Subagent-Driven Dev** (Skill 5) | Plan has 3+ independent tasks | Sequential execution wastes parallelism |
| **Parallel Agents** (Skill 6) | 2+ independent problems to investigate | Sequential investigation loses context between problems |
| **Direct edit** | Change is <20 lines, touching 1-2 files | Agent setup overhead > edit time |

#### Permission model for background agents

Background agents inherit your session's auto-approve settings but **cannot prompt for
manual approval**. If a tool requires confirmation, the agent receives "denied" and fails.

**Mitigation:** Use `isolation: "worktree"` for agents that modify existing files — they
work on an isolated copy and you merge their changes. For agents creating new files,
standard mode usually works because Write-to-new-file is typically auto-approved.

**Subagent mini-flow:** Every agent prompt should include `consult` + `verify` boilerplate
— subagents skip the full 8-phase workflow but these two steps produce higher quality
output. See "Subagent Mini-Flow" in `AGENTS.md`.

**Parallel dispatch (2+ agents):** Include the progress-tracking boilerplate so agents
update `.claude/parallel-tasks.json` via `task-progress.sh`. The orchestrator sees live
status in the status line instead of waiting for completion notifications. See
"Parallel Task Progress Tracking" in `AGENTS.md`.

**Full instructions:** `AGENTS.md` (Skill 5, 6, 10, 11)
<!-- GENERIC-END -->

---

<!-- GENERIC-START: Skills invocation rule -->
## Skills: Check Before Every Action

#### Why skills are checked proactively, not reactively

Skills encode process knowledge — the brainstorming skill prevents cowboy coding, the
debugging skill prevents symptom fixes, the verification skill prevents false claims.
Without proactive checking, the model defaults to the fastest path (edit code directly)
and skips the process that catches errors.

**The 1% rule:** If there's even a slight chance a skill applies, invoke it. The cost of
a false positive (invoking a skill unnecessarily) is minutes. The cost of a false negative
(skipping brainstorming on a feature) is hours of rework. The asymmetry justifies
aggressive checking.

#### Skill invocation order

Process skills activate gates — invoke them first so the gates are set before code edits:

| If you're about to... | Invoke | Why first |
|----------------------|--------|-----------|
| Build something new | Brainstorming (Skill 2) | Sets spec requirement before implementation |
| Create implementation plan | Writing Plans (Skill 3) | Structures TDD cycle for each task |
| Execute a plan | Executing Plans (Skill 4) | Enforces task order and commit cadence |
| Fix a bug | Systematic Debugging (Skill 8) | Blocks fix until root cause identified |
| Write code | TDD (Skill 7) | Ensures test exists before production code |
| Claim something works | Verification (Skill 9) | Circuit breaker against confirmation bias |
| Finish a branch | Finishing Branch (Skill 12) | Enforces retrospective before merge |

**Complete skill reference:** `docs/knowledge/superpowers-skills.md`
<!-- GENERIC-END -->

---

<!-- GENERIC-START: Commits -->
## Commits and Push

#### Why commit frequency equals risk management

Claude Code sessions are fragile: context compacts, tool_use errors corrupt conversations,
network failures kill sessions. **Every commit is a savepoint. Every push is a backup.**
The frequency of commits is inversely proportional to the amount of work lost on crash.

A commit after each completed task means a crash loses at most one task's work. A commit
only at the end of a feature means a crash loses everything. The choice isn't about git
hygiene — it's about recovery cost.

**Why push before launching subagents:** Subagents operate on the repository's state at
the time they're dispatched. If uncommitted changes exist, the subagent doesn't see them.
Worse, if the subagent modifies the same files, you get merge conflicts on return. Push
first = clean handoff.

### When to commit

Each commit marks a point where the codebase is internally consistent:
- After each file that works (compiles, doesn't break tests)
- After each completed task in a plan
- After writing a test (even if it fails — the test alone is a valid checkpoint)
- After making a test pass (commit the implementation that greened it)

### When to push
- After each commit (or max 2-3 if part of same logical step)
- **Always** before launching subagents (ensures clean handoff)
- **Always** run `make manifest` before final push (captures codebase changes)

### Commit format
Prefixes: `feat:`, `fix:`, `refactor:`, `test:`, `docs:`, `chore:`
Short, descriptive. One logical change per commit.

### Work artifacts go to the repo

Plans, specs, and execution logs are part of the codebase — not ephemeral session data.
They're read by future brainstorming sessions (Step 0 of the checklist) and must be
findable via file path, not buried in conversation history that compacts away.

- Plans → `docs/superpowers/plans/`
- Specs → `docs/superpowers/specs/`
- Execution logs → `docs/superpowers/execution-logs/`
- Never only in ephemeral paths (`/root/.claude/`, TodoWrite)
<!-- GENERIC-END -->

---

<!-- GENERIC-START: Decision principles -->
## Decision Principles

#### Why explicit principles instead of "use good judgment"

The model's default judgment optimizes for the current task. Principles override this local
optimization with global constraints that prevent patterns the model can't see from within
a single task. Each principle below was extracted from 3+ execution logs where the opposite
choice caused rework.

### Scalability Over Convenience

A 3-line patch that doesn't scale creates debt that compounds across every future feature.
A 20-file change that scales correctly is more work now but zero work later. **The
evaluation criterion is total lifetime cost, not implementation cost.** This principle
exists because the model's natural bias is "minimize changes in this PR" — which
optimizes for the wrong metric.

### No Redundancy

Every tool call costs context tokens. Before any action: Was this already done? Will the
result differ from current state? If the manifest already answers the question, don't
grep. If the file was just read 3 messages ago and hasn't changed, don't re-read. **The
model's bias toward "let me double-check" is expensive when context is finite.**

### Context Hygiene

Context exhaustion is silent — the model doesn't notice compaction happening. These rules
make the finite budget explicit:
- **Checkpoint** after ~50 tool calls or when compaction is noticed — push, update
  session-state, so recovery is possible
- **Post-compaction:** verify access to spec and plan paths before continuing — compaction
  may have lost the file contents that guide implementation
- **Split large tasks:** >8 steps means the plan won't survive a single compaction cycle.
  Better to split into 2 sessions with a push between them
<!-- GENERIC-END -->

---

<!-- PROJECT-SPECIFIC-START -->
## Knowledge Modules

#### Why modules instead of one large document

A single architecture document would load ~5000 tokens in every conversation — even when
working on a CSS change that needs zero backend context. Knowledge modules split by
subsystem so only the relevant ~500 tokens load. This is the same principle as the
CLAUDE.md hierarchy: **pay only for the context you use.**

Modules also have independent freshness — `ui-frontend.md` can be updated weekly while
`deployment.md` stays stable for months. A monolith document would require reading the
whole thing to check if any section is stale.

#### How modules feed the workflow

Step 0 of brainstorming ("Consult past decisions") reads both `docs/decisions/log.md` AND
the relevant knowledge module. The module provides the architectural context that prevents
re-discovering constraints the codebase already encodes. After any task that changes a
subsystem, update the relevant module — this is what keeps Step 0 useful for the next
session.

The `finalize-validator` automatically checks which modules may need updating based on
changed files (threshold: ≥5 files or new files in a pattern).

| Working on... | Read |
|--------------|------|
| Entities, migrations | `domain-model.md` |
| Providers, factories | `provider-framework.md` |
| Controllers, APIs | `api-surface.md` |
| Deploy, Docker | `deployment.md` |
| Tests | `testing.md` |
| Mercure, SSE | `realtime.md` |
| GPS, Traccar | `gps-tracking.md` |
| Route optimization | `route-optimization.md` |
| DDD, architecture | `architecture-ddd.md` |
| Design patterns | `design-patterns.md` |
| Security, roles | `security.md` |
| UI, Twig, React | `ui-frontend.md` |
| CSS layout, positioning | `ui-layout-contracts.md` |
| Full index | `index.md` |

**Freshness:** < 14 days → trust directly. Older → spot-check 2-3 claims before trusting.

**Graduated tags/patterns:** registered in `docs/knowledge/_graduations.yaml`
(single source of truth). Query via `consult.sh tag <name>` for related logs;
detect drift via `scripts/validate-graduations.sh`. Add new graduations via
`scripts/graduate.sh` (blessed atomic path — see "Closing the Cycle").
<!-- PROJECT-SPECIFIC-END -->

---

<!-- GENERIC-START: Governance -->
## Governance

### This File's Hierarchy

```
CLAUDE.md              ← Philosophy, workflow, cross-cutting rules (always loaded, ~800 lines)
AGENTS.md              ← Subagent instructions (loaded when dispatching agents)
backend/CLAUDE.md      ← Architecture, SOLID, DDD, conventions (loaded in backend/)
backend/src/CLAUDE.md  ← TDD, debugging, critical patterns (loaded in src/)
backend/tests/CLAUDE.md ← Testing conventions (loaded in tests/)
docs/CLAUDE.md         ← Documentation rules, knowledge modules (loaded in docs/)
.claude/README.md      ← Workflow engine technical reference (manual consultation)
```

#### How to decide where new rules go

Ask: "Does this rule need to apply in EVERY conversation?" If yes → this file. If it only
applies when working in a specific directory → that directory's CLAUDE.md. If it's
reference data that might be stale → `docs/knowledge/`. If it's a one-time design decision
→ `docs/decisions/log.md`.

### Decision Log

Decision logs feed future brainstorming (Step 0) — without them, the model re-evaluates
alternatives already tried and discarded.

Non-trivial design decisions go to `docs/decisions/log.md`:
```markdown
### [YYYY-MM-DD] Brief context
- **Problem:** What needed solving
- **Decision:** What was chosen and why
- **Alternatives discarded:** What else was evaluated
- **Result:** (fill post-implementation) Did it work? What was learned?
```

When the same lesson appears 3+ times across execution logs, it graduates to the relevant
knowledge module — that's the signal it's a pattern, not an incident.
<!-- GENERIC-END -->
