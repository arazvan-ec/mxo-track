# Superpowers Skills (from [obra/superpowers](https://github.com/obra/superpowers))

Las siguientes skills definen el flujo de trabajo y la disciplina de desarrollo que se debe seguir en este proyecto.

---

## Skill 1: Using Superpowers

```yaml
name: using-superpowers
description: Use when starting any conversation - establishes how to find and use skills, requiring Skill tool invocation before ANY response including clarifying questions
```

<EXTREMELY-IMPORTANT>
If you think there is even a 1% chance a skill might apply to what you are doing, you ABSOLUTELY MUST invoke the skill.

IF A SKILL APPLIES TO YOUR TASK, YOU DO NOT HAVE A CHOICE. YOU MUST USE IT.

This is not negotiable. This is not optional. You cannot rationalize your way out of this.
</EXTREMELY-IMPORTANT>

### Instruction Priority

Superpowers skills override default system prompt behavior, but **user instructions always take precedence**:

1. **User's explicit instructions** (CLAUDE.md, AGENTS.md, direct requests) — highest priority
2. **Superpowers skills** — override default system behavior where they conflict
3. **Default system prompt** — lowest priority

### The Rule

**Invoke relevant or requested skills BEFORE any response or action.** Even a 1% chance a skill might apply means you should invoke the skill.

### Red Flags (rationalizations to STOP)

| Thought | Reality |
|---------|---------|
| "This is just a simple question" | Questions are tasks. Check for skills. |
| "I need more context first" | Skill check comes BEFORE clarifying questions. |
| "Let me explore the codebase first" | Skills tell you HOW to explore. Check first. |
| "This doesn't need a formal skill" | If a skill exists, use it. |
| "The skill is overkill" | Simple things become complex. Use it. |
| "I'll just do this one thing first" | Check BEFORE doing anything. |

### Skill Priority

1. **Process skills first** (brainstorming, debugging) - determine HOW to approach the task
2. **Implementation skills second** - guide execution

### Skill Types

**Rigid** (TDD, debugging): Follow exactly. Don't adapt away discipline.
**Flexible** (patterns): Adapt principles to context.

---

## Skill 2: Brainstorming

```yaml
name: brainstorming
description: "You MUST use this before any creative work - creating features, building components, adding functionality, or modifying behavior. Explores user intent, requirements and design before implementation."
```

Help turn ideas into fully formed designs and specs through natural collaborative dialogue. Start by understanding the current project context, then ask questions one at a time to refine the idea.

**Do NOT invoke any implementation skill, write any code, scaffold any project, or take any implementation action until you have presented a design and the user has approved it.**

### Anti-Pattern: "This Is Too Simple To Need A Design"

Every project goes through this process. A todo list, a single-function utility, a config change — all of them. "Simple" projects are where unexamined assumptions cause the most wasted work.

### Checklist (MUST complete in order)

1. **Explore project context** — check files, docs, recent commits
2. **Offer visual companion** (if topic will involve visual questions)
3. **Ask clarifying questions** — one at a time, understand purpose/constraints/success criteria
4. **Propose 2-3 approaches** — with trade-offs and your recommendation
5. **Present design** — in sections scaled to their complexity, get user approval after each section
6. **Write design doc** — save to `docs/superpowers/specs/YYYY-MM-DD-<topic>-design.md` and commit
7. **Transition to implementation** — invoke writing-plans skill to create implementation plan

### Key Principles

- **One question at a time** - Don't overwhelm with multiple questions
- **Multiple choice preferred** - Easier to answer than open-ended when possible
- **YAGNI ruthlessly** - Remove unnecessary features from all designs
- **Explore alternatives** - Always propose 2-3 approaches before settling
- **Incremental validation** - Present design, get approval before moving on

### Design for Isolation and Clarity

- Break the system into smaller units with one clear purpose, well-defined interfaces, testable independently
- Can someone understand what a unit does without reading its internals?
- Smaller, well-bounded units are easier to work with

### Working in Existing Codebases

- Explore the current structure before proposing changes. Follow existing patterns.
- Where existing code has problems that affect the work, include targeted improvements as part of the design
- Don't propose unrelated refactoring. Stay focused on what serves the current goal.

### After the Design

1. Write the validated design (spec) to `docs/superpowers/specs/YYYY-MM-DD-<topic>-design.md`
2. Dispatch spec-document-reviewer subagent; fix issues until Approved (max 5 iterations)
3. Invoke the **writing-plans** skill to create implementation plan

---

## Skill 3: Writing Plans

```yaml
name: writing-plans
description: Use when you have a validated design and need to create a detailed implementation plan before coding
```

Write comprehensive implementation plans assuming the engineer has zero context for the codebase.

### Key Principles

- Assume skilled developers with minimal domain knowledge
- Map file structure and responsibilities upfront
- Break work into 2-5 minute steps following TDD pattern
- Save plans to `docs/superpowers/plans/YYYY-MM-DD-<feature-name>.md`

### Code Quality

- DRY, YAGNI, TDD patterns
- Frequent commits after each task
- Focused files with single responsibilities
- Exact file paths and complete code samples

### Task Structure (cycle)

Each task in the plan includes its own TDD cycle. **Never create a separate "add tests"
or "write tests" task** — the test is step 1 of implementing each task.

1. Write failing test
2. Verify failure
3. Implement minimal solution
4. Verify pass
5. Commit

### Plan Document Requirements

Every plan must include:
- Header with goal, architecture, tech stack
- File structure mapping
- Numbered tasks with exact file paths
- Complete code snippets (not pseudocode)
- Exact commands with expected outputs
- Checkbox tracking (`- [ ]`)

---

## Skill 4: Executing Plans

```yaml
name: executing-plans
description: Use when you have a written implementation plan to execute in a separate session with review checkpoints
```

Load plan, review critically, execute all tasks, report when complete.

### The Process

**Step 1: Load and Review Plan**
1. Read plan file
2. Review critically - identify any questions or concerns
3. If concerns: Raise them with user before starting
4. If no concerns: Create TodoWrite and proceed

**Step 2: Execute Tasks**
For each task:
1. Mark as in_progress
2. Follow each step exactly
3. Run verifications as specified
4. Mark as completed

**Step 3: Complete Development**
After all tasks complete: Use **finishing-a-development-branch** skill.

### When to Stop and Ask for Help

**STOP executing immediately when:**
- Hit a blocker (missing dependency, test fails, instruction unclear)
- Plan has critical gaps
- You don't understand an instruction
- Verification fails repeatedly

**Ask for clarification rather than guessing.**

---

## Skill 5: Subagent-Driven Development

```yaml
name: subagent-driven-development
description: Use when executing implementation plans with independent tasks in the current session
```

Execute plan by dispatching fresh subagent per task, with two-stage review after each: **spec compliance review first, then code quality review**.

**Core principle:** Fresh subagent per task + two-stage review (spec then quality) = high quality, fast iteration

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

### Red Flags

- Never skip reviews (spec compliance OR code quality)
- Never dispatch multiple implementation subagents in parallel (conflicts)
- Never make subagent read plan file (provide full text instead)
- Never start code quality review before spec compliance is approved
- If subagent asks questions, answer clearly before proceeding
- If reviewer finds issues, implementer fixes and reviewer re-reviews

---

## Skill 6: Dispatching Parallel Agents

```yaml
name: dispatching-parallel-agents
description: Use when facing 2+ independent tasks that can be worked on without shared state or sequential dependencies
```

When you have multiple unrelated failures, investigating them sequentially wastes time.

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

1. **Identify Independent Domains** - Group failures by what's broken
2. **Create Focused Agent Tasks** - Each agent gets: specific scope, clear goal, constraints, expected output
3. **Dispatch in Parallel**
4. **Review and Integrate** - Read each summary, verify fixes don't conflict, run full test suite

### Agent Prompt Structure

Good agent prompts are:
1. **Focused** - One clear problem domain
2. **Self-contained** - All context needed to understand the problem
3. **Specific about output** - What should the agent return?

### Common Mistakes

- **Too broad:** "Fix all the tests" → agent gets lost
- **No context:** "Fix the race condition" → agent doesn't know where
- **No constraints:** Agent might refactor everything
- **Vague output:** "Fix it" → you don't know what changed

---

## Skill 7: Test-Driven Development

```yaml
name: test-driven-development
description: Use when implementing any feature or bugfix, before writing implementation code
```

Write the test first. Watch it fail. Write minimal code to pass.

**Core principle:** If you didn't watch the test fail, you don't know if it tests the right thing.

**Violating the letter of the rules is violating the spirit of the rules.**

### The Iron Law

```
NO PRODUCTION CODE WITHOUT A FAILING TEST FIRST
```

Write code before the test? Delete it. Start over.

**No exceptions:**
- Don't keep it as "reference"
- Don't "adapt" it while writing tests
- Don't look at it
- Delete means delete

### Red-Green-Refactor

**RED - Write Failing Test**
- One behavior, clear name, real code (no mocks unless unavoidable)

**Verify RED - Watch It Fail (MANDATORY)**
- Test fails (not errors), failure message is expected, fails because feature missing

**GREEN - Minimal Code**
- Write simplest code to pass the test. Don't add features.

**Verify GREEN - Watch It Pass (MANDATORY)**
- Test passes, other tests still pass, output pristine

**REFACTOR - Clean Up**
- After green only: remove duplication, improve names, extract helpers
- Keep tests green. Don't add behavior.

### Common Rationalizations

| Excuse | Reality |
|--------|---------|
| "Too simple to test" | Simple code breaks. Test takes 30 seconds. |
| "I'll test after" | Tests passing immediately prove nothing. |
| "Need to explore first" | Fine. Throw away exploration, start with TDD. |
| "TDD will slow me down" | TDD faster than debugging. |
| "Already spent X hours, deleting is wasteful" | Sunk cost fallacy. |

### Red Flags - STOP and Start Over

- Code before test
- Test passes immediately
- Can't explain why test failed
- Rationalizing "just this once"
- "Tests after achieve the same purpose"
- "Keep as reference"
- "TDD is dogmatic, I'm being pragmatic"

**All of these mean: Delete code. Start over with TDD.**

### Verification Checklist

- [ ] Every new function/method has a test
- [ ] Watched each test fail before implementing
- [ ] Each test failed for expected reason
- [ ] Wrote minimal code to pass each test
- [ ] All tests pass
- [ ] Output pristine (no errors, warnings)
- [ ] Tests use real code (mocks only if unavoidable)
- [ ] Edge cases and errors covered

---

## Skill 8: Systematic Debugging

```yaml
name: systematic-debugging
description: Use when encountering any bug, test failure, or unexpected behavior, before proposing fixes
```

**Core principle:** ALWAYS find root cause before attempting fixes. Symptom fixes are failure.

**Violating the letter of this process is violating the spirit of debugging.**

### The Iron Law

```
NO FIXES WITHOUT ROOT CAUSE INVESTIGATION FIRST
```

### The Four Phases

**Phase 1: Root Cause Investigation (MANDATORY before any fix)**

1. **Read Error Messages Carefully** - Don't skip. Read stack traces completely.
2. **Reproduce Consistently** - Can you trigger it reliably? If not → gather more data, don't guess.
3. **Check Recent Changes** - Git diff, recent commits, new dependencies, config changes.
4. **Gather Evidence in Multi-Component Systems** - For EACH component boundary: log what enters/exits, verify config propagation, check state at each layer. Run once to gather evidence.
5. **Trace Data Flow** - Where does bad value originate? Keep tracing up until you find the source. Fix at source, not at symptom.

**Phase 2: Pattern Analysis**
1. Find working examples in same codebase
2. Compare against references COMPLETELY (don't skim)
3. Identify ALL differences between working and broken
4. Understand dependencies

**Phase 3: Hypothesis and Testing**
1. Form SINGLE hypothesis: "I think X is the root cause because Y"
2. Make SMALLEST possible change to test
3. One variable at a time
4. If didn't work → form NEW hypothesis, DON'T add more fixes on top

**Phase 4: Implementation**
1. Create failing test case (MUST have before fixing)
2. Implement SINGLE fix addressing root cause
3. Verify fix: test passes, no other tests broken
4. **If 3+ fixes failed:** STOP. Question the architecture. Discuss with user before attempting more fixes.

### Red Flags - STOP and Follow Process

- "Quick fix for now, investigate later"
- "Just try changing X and see if it works"
- Proposing solutions before tracing data flow
- "One more fix attempt" (when already tried 2+)
- Each fix reveals new problem in different place

**ALL of these mean: STOP. Return to Phase 1.**

### Real-World Impact

- Systematic approach: 15-30 minutes to fix
- Random fixes approach: 2-3 hours of thrashing
- First-time fix rate: 95% vs 40%

---

## Skill 9: Verification Before Completion

```yaml
name: verification-before-completion
description: Use when about to claim work is complete, fixed, or passing - requires running verification commands and confirming output before making any success claims
```

**Core principle:** Evidence before claims, always.

**Violating the letter of this rule is violating the spirit of this rule.**

### The Iron Law

```
NO COMPLETION CLAIMS WITHOUT FRESH VERIFICATION EVIDENCE
```

If you haven't run the verification command in this message, you cannot claim it passes.

### The Gate Function

```
BEFORE claiming any status:
1. IDENTIFY: What command proves this claim?
2. RUN: Execute the FULL command (fresh, complete)
3. READ: Full output, check exit code, count failures
4. VERIFY: Does output confirm the claim?
5. ONLY THEN: Make the claim

Skip any step = lying, not verifying
```

### Common Failures

| Claim | Requires | Not Sufficient |
|-------|----------|----------------|
| Tests pass | Test command output: 0 failures | Previous run, "should pass" |
| Linter clean | Linter output: 0 errors | Partial check, extrapolation |
| Build succeeds | Build command: exit 0 | Linter passing, logs look good |
| Bug fixed | Test original symptom: passes | Code changed, assumed fixed |
| Agent completed | VCS diff shows changes | Agent reports "success" |
| Requirements met | Line-by-line checklist | Tests passing |

### Red Flags - STOP

- Using "should", "probably", "seems to"
- Expressing satisfaction before verification ("Great!", "Perfect!", "Done!")
- About to commit/push/PR without verification
- Trusting agent success reports
- Relying on partial verification

### Rationalization Prevention

| Excuse | Reality |
|--------|---------|
| "Should work now" | RUN the verification |
| "I'm confident" | Confidence ≠ evidence |
| "Just this once" | No exceptions |
| "Agent said success" | Verify independently |
| "Partial check is enough" | Partial proves nothing |

---

## Skill 10: Receiving Code Review

```yaml
name: receiving-code-review
description: Use when receiving code review feedback - requires technical rigor and verification, not performative agreement or blind implementation
```

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

**INSTEAD:** Restate the technical requirement, ask clarifying questions, push back with technical reasoning if wrong, just start working.

### Handling Unclear Feedback

If ANY item is unclear: **STOP** - do not implement anything yet. Ask for clarification on unclear items.

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

## Skill 11: Requesting Code Review

```yaml
name: requesting-code-review
description: Use when completing tasks, implementing major features, or before merging to verify work meets requirements
```

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

## Skill 12: Finishing a Development Branch

```yaml
name: finishing-a-development-branch
description: Use when implementation is complete and you need to decide how to integrate the work
```

**Core principle:** Verify tests → Present options → Execute choice → Clean up.

### The Process

**Step 1: Verify Tests** - Run project test suite. If tests fail, STOP. Don't proceed.

**Step 2: Determine Base Branch**

**Step 3: Present Options**
```
1. Merge back to <base-branch> locally
2. Push and create a Pull Request
3. Keep the branch as-is (I'll handle it later)
4. Discard this work
```

**Step 4: Execute Choice**
- Option 1: Merge locally, verify tests on merged result, delete feature branch
- Option 2: Push branch, create PR via `gh pr create`
- Option 3: Keep as-is, report location
- Option 4: Confirm with user before deleting (require typed "discard")

**Step 5: Cleanup Worktree** (for Options 1, 2, 4 only)

### Red Flags

- Never proceed with failing tests
- Never merge without verifying tests on result
- Never delete work without confirmation
- Never force-push without explicit request

---

## Skill 13: Using Git Worktrees

```yaml
name: using-git-worktrees
description: Use when starting feature work that needs isolation from current workspace
```

**Core principle:** Systematic directory selection + safety verification = reliable isolation.

### Directory Selection Process

1. Check existing: `.worktrees/` (preferred, hidden) or `worktrees/`
2. Check CLAUDE.md for preference
3. Ask user if no directory exists

### Safety Verification

**MUST verify directory is gitignored before creating worktree.** If NOT ignored: add to `.gitignore`, commit, then proceed.

### Creation Steps

1. Detect project name
2. Create worktree with new branch: `git worktree add "$path" -b "$BRANCH_NAME"`
3. Run project setup (auto-detect: `composer install`, `npm install`, etc.)
4. Verify clean baseline (run tests)
5. Report location and test status

---

## Skill 14: Writing Skills

```yaml
name: writing-skills
description: Use when creating new skills, editing existing skills, or verifying skills work before deployment
```

**Writing skills IS Test-Driven Development applied to process documentation.**

### What is a Skill?

A **skill** is a reference guide for proven techniques, patterns, or tools. Skills help future Claude instances find and apply effective approaches.

**Skills are:** Reusable techniques, patterns, tools, reference guides
**Skills are NOT:** Narratives about how you solved a problem once

### The Iron Law (Same as TDD)

```
NO SKILL WITHOUT A FAILING TEST FIRST
```

### TDD Mapping for Skills

| TDD Concept | Skill Creation |
|-------------|----------------|
| Test case | Pressure scenario with subagent |
| Production code | Skill document (SKILL.md) |
| Test fails (RED) | Agent violates rule without skill (baseline) |
| Test passes (GREEN) | Agent complies with skill present |
| Refactor | Close loopholes while maintaining compliance |

### RED-GREEN-REFACTOR for Skills

**RED:** Run pressure scenario WITHOUT skill. Document exact behavior and rationalizations.
**GREEN:** Write minimal skill addressing those specific violations. Verify agents now comply.
**REFACTOR:** Identify new rationalizations → add explicit counters → re-test until bulletproof.

### SKILL.md Structure

```markdown
---
name: skill-name-with-hyphens
description: Use when [specific triggering conditions]
---

# Skill Name

## Overview - Core principle in 1-2 sentences
## When to Use - Symptoms and use cases
## Core Pattern - Before/after code comparison
## Quick Reference - Table or bullets for scanning
## Common Mistakes - What goes wrong + fixes
```

### Claude Search Optimization (CSO)

- Description starts with "Use when..." — triggering conditions only
- **NEVER summarize the skill's process in the description** (Claude may follow description instead of reading full skill)
- Use concrete triggers, symptoms, and situations
- Keywords throughout for search (errors, symptoms, tools)

### Skill Creation Checklist

**RED Phase:**
- [ ] Create pressure scenarios (3+ combined pressures for discipline skills)
- [ ] Run scenarios WITHOUT skill - document baseline behavior
- [ ] Identify patterns in rationalizations/failures

**GREEN Phase:**
- [ ] Name, YAML frontmatter, description starts with "Use when..."
- [ ] Clear overview with core principle
- [ ] Address specific baseline failures
- [ ] Run scenarios WITH skill - verify compliance

**REFACTOR Phase:**
- [ ] Identify NEW rationalizations
- [ ] Add explicit counters
- [ ] Build rationalization table
- [ ] Re-test until bulletproof

---

## Problemas Conocidos

### Fallos de infraestructura en subagentes

Los subagentes (Agent tool) pueden fallar con errores de runtime del entorno de ejecución, como `undefined is not an object (evaluating 'H.includes')`. Cuando esto ocurre, **todas** las herramientas del subagente fallan (Read, Bash, Grep, Glob) y el agente no puede hacer ningún trabajo útil.

**Síntomas:**
- El subagente reporta que no puede ejecutar ninguna herramienta
- Errores JavaScript internos en las llamadas a herramientas
- El resultado del agente dice "infrastructure errors" o similar

**Solución:**
1. **No reintentar el mismo subagente** — el entorno está roto y reintentar no lo arregla
2. **Ejecutar la tarea en el hilo principal** — si el subagente falla, hacer el trabajo directamente sin delegar
3. **Alternativa: lanzar un nuevo subagente** — un nuevo agente obtiene un entorno fresco que puede funcionar
4. **Si persiste:** informar al usuario y sugerir reiniciar la sesión de Claude Code

**Regla:** Cuando un subagente falla por infraestructura, no marcar la tarea como completada. Reintentarla en el hilo principal o con un nuevo subagente.

---

### Error "tool_use ids must be unique" (API 400)

La API de Claude rechaza peticiones con HTTP 400 y mensaje `tool_use ids must be unique` cuando el historial de conversación contiene bloques `tool_use` con IDs duplicados. Esto es un **bug del cliente** (Claude Code / Agent SDK), no del servidor.

**Causas principales:**
- Llamadas a herramientas en paralelo que generan IDs duplicados
- Conversaciones largas con muchos turnos de tool_use donde la reconstrucción del historial introduce duplicados
- Sesiones reanudadas (`--resume`) con historial corrupto

**Síntomas:**
- Error 400: `messages.N.content.M: tool_use ids must be unique`
- La conversación se corta abruptamente y no se puede continuar
- Las herramientas dejan de funcionar en la sesión actual

**Mitigación (qué hacer Claude para reducir riesgo):**
1. **Hacer commits frecuentes** — cada tarea completada debe committearse inmediatamente para que el progreso no se pierda si la sesión se corrompe
2. **Documentar estado en TodoWrite** — mantener el todo list actualizado para que al reanudar se sepa qué falta
3. **Preferir tareas atómicas** — dividir trabajo grande en pasos pequeños e independientes; si la sesión se rompe a mitad de un paso, se pierde menos trabajo
4. **Limitar profundidad de subagentes** — conversaciones con muchas llamadas paralelas a herramientas son más propensas al error; si una tarea necesita >20 tool calls secuenciales, considerar dividirla

**Recuperación (qué hacer cuando ocurre):**
1. **Usar `/clear`** — resetea el historial de la conversación y puede permitir continuar
2. **Iniciar nueva sesión** — `claude` sin `--resume` empieza con historial limpio
3. **Resumir sesión anterior con cuidado** — `claude --resume <id>` puede funcionar si el error fue puntual, pero si el historial está corrupto fallará de nuevo
4. **Revisar git log** — verificar qué commits se hicieron antes del error para saber desde dónde continuar
5. **Leer TodoWrite** — si había una lista de tareas, verificar cuáles están completadas y cuáles pendientes

**Regla:** Ante este error, NUNCA asumir que el trabajo previo se guardó. Verificar con `git log` y `git status` antes de continuar. Hacer commits más frecuentes es la mejor protección.

---

## Calibration Data

Real measurements from past tasks. Use to estimate future work of similar type.

### Task Type: Wiring (connect existing callback/prop)

| Date | Task | Files | Lines | Time |
|------|------|-------|-------|------|
| 2026-04-06 | OperatorDashboardPage: wire onStopClick to pageData | 1 | +24 | Trivial (<5 min) |
| 2026-04-06 | OperatorDashboardPage: unify handlers with routePublicId | 1 | +3/-23 | Trivial (<5 min) |
| 2026-04-06 | RoutePlannerPage: add handlePreviewStopClick | 1 | +16 | Trivial (<5 min) |
| 2026-04-06 | TestRoutingPage: rewrite handleStopClick with flyTo | 1 | +13/-3 | Trivial (<5 min) |

**Pattern:** Wiring tasks average ~15 lines, 1 file, <5 min. Full brainstorm+plan adds
10-15 min overhead with zero design value. Use deviation mode for these.

### Task Type: Boilerplate Migration (SQL widget layout)

| Date | Task | Files | Lines | Time |
|------|------|-------|-------|------|
| 2026-04-06 | RouteAnalysisPage: add stop_list to widget layout | 1 | ~120 | <10 min |

**Pattern:** Widget layout migrations follow an established DO $$ block pattern
(Version20260401000100). ~120 lines but 90% is boilerplate (explicit id generation,
sequence fix, down() rollback). Actual logic is 2-3 VALUES tuples. Copy the pattern,
change the page_key and widget list.

### When to Add Entries

Add a calibration entry after any task that was:
- Significantly faster or slower than expected
- A new type not yet represented in this table
- A repeat of an existing type (validates the estimate)
