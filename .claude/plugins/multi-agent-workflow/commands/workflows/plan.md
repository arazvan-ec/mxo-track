---
name: workflows:plan
description: "Convert ideas into implementable strategies with detailed planning. The foundation of compound engineering (80% planning, 20% execution)."
argument_hint: <feature-name> [--workflow=default|task-breakdown] [--show-impact=true|false]
---

# Multi-Agent Workflow: Plan

The planning phase is the foundation of compound engineering. Invest 80% of effort here.

## Flow Guard (prerequisite check)

Before executing, verify the flow has been followed:

```
PREREQUISITE CHECK:
  1. Was this request routed via /workflows:route?
     - YES: Continue to planning
     - NO: STOP. Run /workflows:route first, then return here.

  2. If tasks.md exists in openspec/changes/{slug}/ for this feature, is this a continuation?
     - YES (planner = IN_PROGRESS): Resume planning from last checkpoint
     - YES (planner = COMPLETED): Plan already exists. Confirm re-planning with user.
     - NO: Fresh start, proceed normally.

  If either check fails, do NOT proceed. Route first.
```

## Usage

```
/workflows:plan user-authentication
/workflows:plan payment-system --workflow=task-breakdown
/workflows:plan order-management --show-impact=true
```

## Flags

| Flag | Default | Description |
|------|---------|-------------|
| `--workflow` | `default` | Workflow type: `default` or `task-breakdown` |
| `--show-impact` | `true` | Show detailed integration impact analysis |

## Philosophy

> "Each unit of engineering work should make subsequent units easier—not harder"

> "El código de alta calidad cumple SOLID de forma rigurosa"

Good planning means:
- Engineers can start WITHOUT asking questions
- **Solutions are designed with SOLID compliance from the start**
- API contracts are complete enough to mock
- Every task has clear "definition of done"

---

## Quality Gate Protocol (shared by all phases)

Each planning phase ends with a Quality Gate before writing its output file. The protocol is the same; only the specific checks change per phase.

```
QUALITY GATE (max 3 iterations):
  iteration = 0
  while iteration < 3:
    Step 0 (Reflection): Before checking, state 3 things that could be wrong
      with this output. Check specifically for these self-identified risks.

    Run phase-specific checks (see each phase below)

    IF all checks pass → WRITE file, advance to next phase
    IF any check fails → log which failed, revise, iteration += 1

  IF 3 iterations exhausted:
    WRITE best version with "## Quality Warnings" section
    NOTIFY user of concerns
    ADVANCE to next phase (do not block indefinitely)
```

After passing any Quality Gate, follow the Per-Phase Write Directives below.

---

## MANDATORY: Incremental Persistence Protocol

> **CRITICAL RULE**: Every planning phase MUST write its output file to disk IMMEDIATELY upon completion, BEFORE starting the next phase. Planning is NOT an in-memory exercise. If Claude is interrupted at any point, all completed phases must be recoverable from disk.

### The Write-Then-Advance Rule

```
PHASE COMPLETION PROTOCOL (applies to every phase):

1. GENERATE the phase output in full
2. WRITE the output file to disk immediately (use Write tool)
3. UPDATE tasks.md Workflow State with phase completion status + timestamp
4. VERIFY the file exists on disk (use Read tool to confirm)
5. ONLY THEN advance to the next phase

If step 2 fails, RETRY the write. Do NOT proceed to next phase
with unwritten output.
```

### Planning Progress Tracker (in tasks.md Workflow State)

At the START of planning, create `openspec/changes/${FEATURE_ID}/tasks.md` using the canonical template from `core/templates/tasks-template.md`. Set Planner status to IN_PROGRESS and fill in Feature ID and timestamps.

### Per-Phase Write Directives

After completing each phase, apply the Write-Then-Advance Rule:

| Phase | Output File | Extra Steps |
|-------|------------|-------------|
| Step 0 | (none — context only) | UPDATE tasks.md: Step 0 → COMPLETED |
| Phase 1 | `proposal.md` | — |
| Phase 2 | `specs.md` | RUN Integration Analysis pre-hook before generating |
| Phase 3 | `design.md` | — |
| Phase 4 | Update `tasks.md` (Progress + Details) | APPEND summary to `proposal.md`, proceed to Completeness Verification |

---

## Shaping Integration (Optional Pre-Phase)

If `/workflows:shape` was run before planning, the following artifacts are available in `openspec/changes/${FEATURE_ID}/`:

| File | Content | How Planner Uses It |
|------|---------|---------------------|
| `01_shaped_brief.md` | Frame, requirements (R), shape, fit check | Accelerates Phase 1 (Understand) and Phase 2 (Specs) |
| `02_breadboard.md` | Places, affordances, wiring diagram | Informs Phase 3 (Design) with concrete mechanisms |
| `03_slices.md` | Vertical slices with demo statements | Becomes the task group structure in Phase 4 |
| `spike-*.md` | Technical investigation findings | Provides context for design decisions |

When shaped brief exists, the planner should:
1. **Phase 1**: Use the Frame as the problem statement (verify with user, don't re-derive)
2. **Phase 2**: Use Requirements (R) as foundation for functional specs (add formal structure)
3. **Phase 3**: Use Shape parts as starting point for design (add SOLID analysis)
4. **Phase 4**: Use Slices (V1, V2...) to structure task groups vertically

---

## Planning Depth Resolution

Before starting the planning process, resolve the `planning_depth` provider from `core/providers.yaml`:

```
READ core/providers.yaml → providers.planning_depth

IF "auto":
  ├── Complexity from /workflows:route == "complex" → full
  ├── Complexity from /workflows:route == "medium"  → standard
  └── Complexity from /workflows:route == "simple"  → minimal

IF "full":    Execute ALL phases (Step 0 + Phase 1 + Phase 2 + Phase 3 + Phase 4)
IF "standard": Execute Step 0 + Phase 1 + Phase 2 + Phase 3 (skip integration/impact detail) + Phase 4
IF "minimal":  Execute Step 0 + Phase 1 + Phase 4 ONLY (skip Phase 2 specs and Phase 3 solutions)
```

| Depth | Phases | Output Files | Best For |
|-------|--------|-------------|----------|
| **full** | All phases + integration + SOLID + impact | proposal, specs, design, tasks | Complex features, multi-layer, security |
| **standard** | Phase 1-4, no detailed impact | proposal, specs, design, tasks | Medium features, single-layer |
| **minimal** | Phase 1 + Phase 4 only | proposal, tasks | Simple features, bug fixes |

When `planning_depth` is `minimal`, the Quality Gates for Phase 2 and Phase 3 are skipped (those phases don't execute).

---

## The Architecture-First Planning Process

> **"Every new feature is an INTEGRATION into existing architecture, not an isolated solution."**

```
┌─────────────────────────────────────────────────────────────────┐
│                    STEP 0: LOAD PROJECT SPECS                   │
│  Read existing specs → Understand current architecture          │
│  └── entities, api-contracts, business-rules, constraints       │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│                    PHASE 1: UNDERSTAND                          │
│  Analyze request → Ask clarifying questions → Document problem  │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│                    PHASE 2: SPECS + INTEGRATION ANALYSIS        │
│  Define WHAT the system must do:                                │
│  ├── Task-specific specs (user requirements, acceptance criteria│
│  └── **Integration Analysis** (existing specs impact)           │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│                    PHASE 3: SOLUTIONS + ARCHITECTURAL IMPACT    │
│  Design HOW to implement each spec:                             │
│  ├── Functional solutions (implementation approach)             │
│  ├── **CONSTRAINT: SOLID** (patterns + quality = mandatory)     │
│  └── **Architectural Impact** (layers, modules, change scope)   │
└─────────────────────────────────────────────────────────────────┘
```

### Key Distinction

| Phase 2: SPECS | Phase 3: SOLUTIONS |
|----------------|-------------------|
| **QUÉ** debe hacer | **CÓMO** hacerlo |
| Requisitos funcionales | Diseño técnico |
| "User can register" | "Use Strategy pattern for validators" |
| Del usuario/negocio | Del desarrollador/arquitecto |

**SOLID es un CONSTRAINT de diseño en Fase 3, no una spec funcional en Fase 2.**

---

## STEP 0: LOAD PROJECT SPECS (Architecture Context)

Before planning any new feature, load and understand the existing project architecture.

### Step 0.0: Load Implementation Preferences (if exists)

```bash
# Check if pre-planning phase captured preferences
PREFERENCES="openspec/changes/${FEATURE_ID}/01_preferences.md"
if [ -f "$PREFERENCES" ]; then
  echo "Preferences found from pre-planning. Loading into planning context."
  # Read technology choices, architecture preferences, code style, constraints
  # Do NOT re-ask questions already answered in 01_preferences.md
  # Use preferences to guide Phase 1 constraints and Phase 3 design
fi
```

### Step 0.0b: Check for Shaped Brief (if exists)

```bash
# Check if shaping was done before planning
SHAPED_BRIEF="openspec/changes/${FEATURE_ID}/01_shaped_brief.md"
if [ -f "$SHAPED_BRIEF" ]; then
  echo "Shaped brief found. Using as input for planning."
  # Read shaped brief, breadboard, slices
  # Accelerate Phase 1 and Phase 2 with existing context
fi
```

### Step 0.0c: Load Project Constitution (if exists)

```bash
# Check for project constitution (non-negotiable principles)
CONSTITUTION="openspec/specs/constitution.md"
if [ -f "$CONSTITUTION" ]; then
  echo "Project constitution found. Loading constraints into planning context."
  # Read architecture principles, quality standards, technology constraints
  # ALL planning decisions must be consistent with constitution.md
  # If a design decision would violate a constitutional principle, flag it to the user
fi
# Template: core/templates/constitution-template.md
# Create with: /workflows:discover --setup (generates initial constitution from project analysis)
```

### Step 0.1: Read Existing Specifications

```bash
# Load all existing project specs (BASELINE — read-only, only compound writes here)
SPECS_BASE="openspec/specs"

# Entities - the domain model
ls -la ${SPECS_BASE}/entities/*.yaml

# API Contracts - existing endpoints
ls -la ${SPECS_BASE}/api-contracts/*.yaml

# Business Rules - domain logic constraints
ls -la ${SPECS_BASE}/business-rules/*.yaml

# Architectural Constraints - system boundaries
ls -la ${SPECS_BASE}/architectural-constraints/*.yaml
```

### Step 0.2: Generate and Display Specs Summary

Summarize the loaded specs as a table (entities, endpoints, rules, constraints) and display to the user when `--show-impact=true`. This establishes the architecture context before planning begins.

---

## PHASE 1: UNDERSTAND (Entender el Problema)

### Step 1.1: Analyze the Request

```markdown
## Request Analysis

**Original Request**: [user's exact words]
**Request Type**: [feature | refactor | bugfix | architecture | investigation]
**Affected Areas**: [modules/services involved]
**Confidence Level**: [0-100%]
```

### Step 1.2: Ask Clarifying Questions (if confidence < 60%)

Before proceeding, ensure you understand:

| Question Category | Example Questions |
|-------------------|-------------------|
| **Functional Scope** | ¿Qué debe hacer exactamente? ¿Qué NO debe hacer? |
| **Users/Actors** | ¿Quién usará esta funcionalidad? |
| **Integration** | ¿Se conecta con APIs externas? ¿Qué sistemas afecta? |
| **Constraints** | ¿Hay restricciones de tiempo/tecnología/rendimiento? |
| **Success Criteria** | ¿Cómo sabremos que está completo y funciona? |

### Step 1.3: Document the Understood Problem

```markdown
## Problem Statement

### What We're Building
[Clear description of the feature/task]

### Why It's Needed
[Business justification]

### Who Benefits
[Users/stakeholders]

### Constraints
- Technical: [stack, performance, integrations]
- Business: [timeline, budget, compliance]
- Team: [skills, availability]

### Success Criteria
1. [Measurable criterion 1]
2. [Measurable criterion 2]
```

### Phase 1 Quality Gate

Apply the Quality Gate Protocol (above) with these 4 checks before writing `proposal.md`:

1. **Specific to request**: References user's exact words/intent. FAIL if generic/templated.
2. **Substantive content**: ≥10 non-header content lines. FAIL if only section headers.
3. **Measurable criteria**: Each success criterion is testable (pass/fail). FAIL if vague.
4. **Complete coverage**: All aspects of user's request addressed. FAIL if significant gaps.

**CDP check**: If the user's request contradicts `constitution.md` or existing specs in `openspec/specs/`, apply the Contradiction Detection Protocol (see `framework_rules.md` §12) before proceeding.

---

## PHASE 2: SPECS (Requisitos Funcionales)

Phase 2 defines **WHAT** the system must do - the functional requirements from the user's perspective.

> **Spec Flow**: Phase 2 reads `openspec/specs/` as the project baseline before generating feature specs. Feature specs are written to `openspec/changes/${FEATURE_ID}/specs.md`. After implementation and review, `/workflows:compound` merges feature specs back into the baseline.

### Functional Specs Only

```markdown
## Functional Specs: ${FEATURE_ID}

### SPEC-F01: [Functional Requirement 1]
**Description**: [What it must do - from user perspective]
**Acceptance Criteria**:
- [ ] [Testable criterion]
- [ ] [Testable criterion]
**Verification**: [How to test]

### SPEC-F02: [Functional Requirement 2]
**Description**: [What it must do]
**Acceptance Criteria**:
- [ ] [Testable criterion]
**Verification**: [How to test]

### SPEC-F03: [Functional Requirement 3]
...
```

### Examples of Good Specs (Functional)

| Good Spec (QUÉ) | Bad Spec (CÓMO) |
|-----------------|-----------------|
| "User can register with email and password" | "Use Strategy pattern" |
| "System validates email format" | "Create EmailValidator class" |
| "Login returns authentication token" | "Implement Repository pattern" |
| "Password must be ≥8 characters" | "Use DIP for dependencies" |

**Note**: SOLID, patterns, and technical decisions belong in Phase 3, not here.

### How to Generate Specs

```bash
# Generate functional specs interactively
/workflow-skill:criteria-generator --feature=${FEATURE_ID} --interview

# Output: openspec/changes/${FEATURE_ID}/specs.md
```

### Integration Analysis (when --show-impact=true)

After defining functional specs, analyze how they integrate with existing specs.

#### Step 2.A: Identify Integration Points

```markdown
## Integration Analysis: ${FEATURE_ID}

### Entities Impact

#### EXTENDED (existing entities with new properties/methods)
| Entity | New Property/Method | Reason |
|--------|---------------------|--------|
| User | `subscription_tier` | Support premium features |
| Order | `discount_applied` | Apply subscription discounts |

#### MODIFIED (existing entities with changed behavior)
| Entity | Change | Impact |
|--------|--------|--------|
| User | Validation rules | Add tier validation |

#### NEW (entities created by this feature)
| Entity | Purpose | Relationships |
|--------|---------|---------------|
| Subscription | Track user subscription | belongs_to: User |
| SubscriptionPlan | Define available plans | has_many: Subscriptions |

### API Contracts Impact

#### EXTENDED (existing endpoints with new parameters/responses)
| Endpoint | Change | Backward Compatible |
|----------|--------|---------------------|
| GET /api/users/{id} | Add `subscription` to response | YES |
| POST /api/orders | Accept `discount_code` param | YES |

#### MODIFIED (existing endpoints with changed behavior)
| Endpoint | Change | Migration Required |
|----------|--------|-------------------|
| None | - | - |

#### NEW (endpoints created by this feature)
| Endpoint | Method | Purpose |
|----------|--------|---------|
| /api/subscriptions | GET, POST | Subscription CRUD |
| /api/subscription-plans | GET | List available plans |

### Business Rules Impact

#### CONFLICTS (potential conflicts with existing rules)
| New Rule | Existing Rule | Resolution |
|----------|---------------|------------|
| "Premium users get 20% discount" | BR-001 "Order total > 0" | Check after discount |

#### NEW (business rules added by this feature)
| Rule ID | Entity | Description |
|---------|--------|-------------|
| BR-010 | Subscription | Active subscription required for premium features |
| BR-011 | Order | Discount cannot exceed 50% of order total |
```

#### Step 2.B: API Contract Design (for new/modified endpoints)

When the feature introduces new API endpoints or modifies existing ones, apply these conventions:

```
API CONTRACT DESIGN PROTOCOL:

1. NAMING: RESTful resource-based URLs
   - Resources are nouns, plural: /api/users, /api/orders
   - Nested resources for relationships: /api/users/{id}/orders
   - Actions as sub-resources when CRUD doesn't fit: /api/orders/{id}/cancel

2. CONTRACT DEFINITION: For each new/modified endpoint, specify:
   - Method + Path
   - Request: headers, path params, query params, body schema
   - Response: status codes (success + error), body schema
   - Authentication: required/optional, token type
   - Rate limiting: if applicable

3. CONSISTENCY: Match existing project conventions
   - Read existing API contracts in openspec/specs/api-contracts/
   - Follow the same response envelope format
   - Use consistent error response structure
   - Match pagination style (cursor vs offset)

4. VERSIONING: Follow project's versioning strategy
   - If existing endpoints use /api/v1/, new endpoints must too
   - If no versioning exists, don't introduce it unnecessarily

5. OUTPUT: Include contracts in specs.md Integration Analysis section
```

#### Step 2.C: Conflict Detection

Before proceeding to Phase 3, verify no unresolved conflicts:

```bash
# Check for spec conflicts
/workflow-skill:spec-analyzer --feature=${FEATURE_ID} --check-conflicts

# Output:
# ✅ No entity name conflicts
# ✅ No endpoint path conflicts
# ⚠️ 1 business rule conflict (BR-001 vs new discount rule)
# Action: Resolve conflict before proceeding
```

### Phase 2 Quality Gate

Apply the Quality Gate Protocol (above) with these 4 checks before writing `specs.md`:

1. **WHAT not HOW**: Each spec describes functional requirements. FAIL if contains implementation details.
2. **Testable criteria**: ≥2 acceptance criteria per spec, each verifiable. FAIL if missing or vague.
3. **Full scope**: Every user requirement maps to at least one spec. FAIL if gaps.
4. **Substantive integration**: Integration analysis identifies extended/modified/new entities AND endpoints. FAIL if all say "None" for non-trivial feature.

**CDP check**: If new specs conflict with existing specs in `openspec/specs/` (detected in Step 2.C), apply the Contradiction Detection Protocol (`framework_rules.md` §12) — do not silently override.

---

### HITL Checkpoint: Phase 2 → Phase 3

Before proceeding to design, present the spec summary to the user:

```
"Specs complete for ${FEATURE_ID}:
 - ${N} functional specs defined
 - Integration: ${E} entities extended, ${M} modified, ${C} new
 - ${X} API endpoints affected

 Do these specs cover your requirements? [yes / adjust / restart]"
```

If "adjust": revise specs based on feedback, re-run Phase 2 Quality Gate.
If "restart": return to Phase 1 with new understanding.

---

### Phase 2.5: Test Contract Sketch (conditional: planning_depth=full or standard)

When `planning_depth` is `full` or `standard`, outline test contracts before designing solutions. This ensures Phase 3 designs for testability and Phase 5 (work) starts TDD with pre-validated criteria.

```markdown
## Test Contract Sketch: ${FEATURE_ID}

### Test Mapping (per spec)

| Spec | Test Type | Key Scenarios | Edge Cases | Dependencies |
|------|-----------|---------------|------------|--------------|
| SPEC-F01 | Unit + Integration | [happy path, validation] | [empty input, max length] | [mock: UserRepository] |
| SPEC-F02 | Integration | [API response, timeout] | [null response, 500 error] | [fixture: test DB] |

### Test Boundaries

- **System boundary** (where to mock): [e.g., external APIs, database, file system]
- **Trust boundary** (where to validate input): [e.g., controllers, CLI handlers]
- **Integration boundary** (where unit tests are insufficient): [e.g., repository queries, API contracts]

### Test Infrastructure Needed

- [ ] Fixtures: [list any test data needed]
- [ ] Mocks: [list interfaces that need mocking]
- [ ] Test environment: [any special setup — test DB, env vars, etc.]
```

This is NOT full test implementation — it's a contract that:
1. Informs Phase 3 design decisions (design for testability)
2. Feeds directly into `/workflows:work` TDD cycle (pre-validated scenarios)
3. Prevents the implementer from having to guess test boundaries

**Append the test contract sketch to `specs.md`** after the functional specs section.

---

## PHASE 3: DESIGN

Phase 3 defines **HOW** to implement each spec. This is where **SOLID becomes mandatory**.

### The SOLID Constraint

> ⚠️ **MANDATORY CONSTRAINT**: All solutions MUST comply with SOLID principles.
>
> This is NOT optional. Every solution designed in Phase 3 must:
> 1. Be analyzed for SOLID compliance per principle
> 2. Use appropriate design patterns
> 3. Achieve COMPLIANT to proceed, NEEDS_WORK requires revision, NON_COMPLIANT blocks

### Step 3.1: Analyze Existing Code (SOLID Baseline)

Before designing solutions, understand the current state:

```bash
# Get SOLID baseline of affected areas
/workflow-skill:solid-analyzer --mode=baseline --path=src/relevant-module

# Output:
# - Patterns detected in module: [list]
# - Violations found: [list]
# - Principles most relevant to this module: [list]
# - Recommended patterns: [list]
```

### Step 3.1b: Reason About API Architecture Constraints (if diagnostic exists)

When DISCOVER has classified the project's API architecture dimensions, this step **reasons** about what constraints apply to THIS specific feature. The diagnostic file describes reality (detection); this step derives what must be true (reasoning).

```bash
# Check for API architecture dimensional profile
DIAGNOSTIC="openspec/specs/api-architecture-diagnostic.yaml"
if [ -f "$DIAGNOSTIC" ]; then
  echo "API Architecture Dimensional Profile found. Reasoning about constraints for this feature..."
fi
```

**When diagnostic does NOT exist**: Skip this step. The project either has no API complexity or has not run `/workflows:discover`.

**When diagnostic exists**, the planner executes 4 reasoning steps:

#### Step 3.1b.1: Filter Relevant Dimensions

Not all project dimensions affect every feature. Before generating constraints, determine which dimensions are relevant:

```
FOR each dimension in [data_flow, data_source_topology, consumer_diversity,
                       dependency_isolation, concurrency_model, response_customization]:
  IF the feature touches code paths affected by this dimension:
    → Mark as RELEVANT (include in constraint reasoning)
  ELSE:
    → Mark as NOT_RELEVANT (skip, document why)

Example:
  Feature: "Add pagination to list endpoint"
  - data_flow: RELEVANT (feature serves API responses)
  - data_source_topology: NOT_RELEVANT (feature doesn't add new data sources)
  - consumer_diversity: RELEVANT (response format may differ per consumer)
  - dependency_isolation: NOT_RELEVANT (feature doesn't touch external APIs)
  - concurrency_model: NOT_RELEVANT (no concurrent operations involved)
  - response_customization: RELEVANT (pagination may vary per consumer)
```

#### Step 3.1b.2: Generate Per-Dimension Constraints

For each RELEVANT dimension, apply these reasoning rules to generate constraints:

```
DATA FLOW constraints:
  IF primary in [ingress, aggregation, bidirectional]:
    → MUST: "Inbound data crosses an abstraction boundary before entering Domain" (DIP)
  IF primary in [egress, bidirectional]:
    → MUST: "Outbound data shaped by Application/Infrastructure, not Domain" (SRP)
  IF primary == aggregation:
    → MUST: "Multi-source assembly coordinated by a dedicated orchestrator" (SRP)
  IF primary == transformation:
    → MUST: "Transformation logic isolated in mapper classes" (SRP, OCP)
  IF primary == passthrough:
    → SHOULD: "Passthrough logic does not add domain coupling" (DIP)

DATA SOURCE TOPOLOGY constraints:
  IF value in [single_external, multi_external, mixed_db_external, hybrid]:
    → MUST: "Each external source accessed through Port interface in Domain" (DIP)
    → MUST: "Each external source has Adapter implementation in Infrastructure" (DIP)
  IF value in [multi_external, mixed_db_external, hybrid]:
    → MUST: "Vendor SDK types do not appear outside Infrastructure layer" (DIP)
    → MUST: "Each external source has independent Provider interface" (ISP)
  IF value == event_driven:
    → MUST: "Event payloads translated to domain types at boundary" (DIP, SRP)

CONSUMER DIVERSITY constraints:
  IF value in [multi_platform, public_api, mixed]:
    → MUST: "Each consumer type has its own response transformation" (SRP, OCP)
    → MUST: "Domain entities contain no serialization annotations or logic" (SRP)
  IF value == inter_service:
    → MUST: "API contracts versioned and backward-compatible" (OCP)
  IF value == public_api:
    → MUST: "Response format documented and stable"

DEPENDENCY ISOLATION constraints:
  IF value == direct_coupling:
    → MUST: "External dependencies in Domain/Application replaced with Port interfaces" (DIP)
    → MUST: "Vendor SDK instantiation moves to Infrastructure adapters" (DIP)
  IF value == partially_wrapped:
    → MUST: "Missing port interfaces created in Domain for each adapter" (DIP)
    → MUST: "Vendor response types replaced with domain DTOs" (DIP)
  IF value == fully_isolated:
    → REVIEW: "Confirm no new vendor SDK types crossed layer boundaries"

CONCURRENCY MODEL constraints (cross-reference with data_source_topology):
  IF value == synchronous AND topology in [multi_external, hybrid]:
    → MUST: "Independent external API calls evaluated for concurrent execution" (SRP)
    → SHOULD: "Sequential HTTP calls to independent sources refactored to concurrent"
  IF value == async_capable:
    → SHOULD: "Evaluate whether sequential bottlenecks justify async migration"
  IF value == fully_concurrent:
    → MUST: "Concurrent operations handle partial failures gracefully"

RESPONSE CUSTOMIZATION constraints:
  IF value == parameterized:
    → MUST: "Field filtering handled in Application layer, not Domain" (SRP)
  IF value == per_consumer_shaped:
    → MUST: "Each consumer has dedicated DTO or Transformer" (SRP, OCP)
    → MUST: "No switch/if-else by consumer type in serialization code" (OCP)
  IF value == context_dependent:
    → MUST: "Context resolution happens at boundary (controller/middleware)" (SRP)
```

#### Step 3.1b.3: Generate Derived Constraints

Check dimension COMBINATIONS that produce compound architectural risks:

```
IF topology in [multi_external, hybrid] AND isolation == direct_coupling:
  → CRITICAL: "Multiple external APIs with direct coupling = cascading vendor risk"
  → Pattern: AC-01 (Anti-Corruption Layer) from architecture-reference.md

IF data_flow == aggregation AND concurrency == synchronous:
  → WARNING: "Aggregating N sources synchronously = N * avg_latency response time"
  → Pattern: AC-03 (Async HTTP Grouping) from architecture-reference.md

IF consumer_diversity in [multi_platform, mixed] AND customization == per_consumer_shaped:
  → "Platform-specific shaping requires Strategy or dedicated Transformers"
  → Pattern: AC-04 (Multi-Platform Serialization) from architecture-reference.md

IF data_flow == aggregation AND topology in [multi_external, hybrid]:
  → "Multi-source aggregation requires Assembler + independent Providers"
  → Pattern: AC-02 (Data Assembler) from architecture-reference.md

IF topology in [multi_external, hybrid] AND concurrency == synchronous:
  → "Performance bottleneck: N sequential HTTP calls = N * avg_latency"
  → Pattern: AC-03 (Async HTTP Grouping) from architecture-reference.md
```

#### Step 3.1b.4: Inject Into Design Phase

Categorize all generated constraints by enforcement level and pass to Step 3.2:

- **MUST constraints** → Mandatory design requirements (SOLID violation if not met)
- **SHOULD constraints** → Recommended (quality/performance risk if not met)
- **REVIEW criteria** → What to verify during code review

**Output enrichment**: In `design.md`, add:

```markdown
## API Architecture Constraints Addressed

**Dimensional Profile**: `openspec/specs/api-architecture-diagnostic.yaml`

### Dimensional Context (relevant to this feature)
| Dimension | Value | Relevant | Impact on Design |
|-----------|-------|----------|-----------------|
| Data Flow | [value] | YES/NO | [how it affects this feature's design] |
| Data Source Topology | [value] | YES/NO | [how it affects this feature's design] |
| Consumer Diversity | [value] | YES/NO | [how it affects this feature's design] |
| Dependency Isolation | [value] | YES/NO | [how it affects this feature's design] |
| Concurrency Model | [value] | YES/NO | [how it affects this feature's design] |
| Response Customization | [value] | YES/NO | [how it affects this feature's design] |

### Constraints Satisfied by This Design
| Constraint (must) | SOLID | How Addressed | Pattern Used |
|-------------------|-------|---------------|-------------|
| [constraint text] | [DIP/SRP/OCP/ISP] | [design decision] | [AC-01/02/03/04 or N/A] |

### Constraints Deferred (should)
| Constraint (should) | Reason for Deferral |
|---------------------|---------------------|
| [constraint text] | [why not addressed in this feature] |

### Derived Risks
| Risk | Severity | Mitigation in Design |
|------|----------|---------------------|
| [compound risk from dimension combination] | CRITICAL/WARNING | [how addressed] |
```

### Step 3.2: Design Solutions with SOLID

For EACH functional spec, propose a solution that complies with SOLID:

```markdown
## Solutions: ${FEATURE_ID}

---

### Solution for SPEC-F01: User Registration

**Approach**: Create User entity with email validation

**SOLID Compliance**:
- **SRP**: COMPLIANT — User entity only holds data, validation logic isolated in Email ValueObject
- **OCP**: COMPLIANT — New validators can be added without modifying User (Strategy pattern)
- **LSP**: N/A — No inheritance hierarchy in this solution
- **ISP**: COMPLIANT — Small, focused interfaces for repository and validation
- **DIP**: COMPLIANT — Repository interface defined in Domain, implemented in Infrastructure

**Files to Create**:
- `Domain/Entity/User.php` (SRP: only user data)
- `Domain/ValueObject/Email.php` (SRP: email rules)
- `Domain/Repository/UserRepositoryInterface.php` (DIP: abstraction)
- `Infrastructure/Repository/DoctrineUserRepository.php` (DIP: implementation)

---

### Solution for SPEC-F02: User Login

**Approach**: Authentication service with token generation

**SOLID Compliance**:
- **SRP**: COMPLIANT — Auth logic separate from token generation (Extract Class)
- **OCP**: COMPLIANT — Token strategies can be added without modification (Strategy pattern)
- **LSP**: N/A — No inheritance hierarchy in this solution
- **ISP**: COMPLIANT — Token generator interface is minimal and focused
- **DIP**: COMPLIANT — Token generator injected via interface (DI)

**Files to Create**:
- `Application/Service/AuthenticationService.php`
- `Domain/Service/TokenGeneratorInterface.php`
- `Infrastructure/Service/JwtTokenGenerator.php`
```

### Step 3.3: Pattern Selection Guide

When designing solutions, select patterns based on the need:

| If You Need... | Use Pattern | SOLID Addressed |
|----------------|-------------|-----------------|
| Multiple behaviors/algorithms | **Strategy** | OCP, SRP |
| Add functionality without modification | **Decorator** | OCP, SRP |
| Abstract object creation | **Factory Method** | DIP, OCP |
| Integrate external systems | **Adapter / Ports & Adapters** | DIP, OCP |
| Abstract data persistence | **Repository** | SRP, DIP |
| Chain of processing steps | **Chain of Responsibility** | SRP, OCP |
| Encapsulate validation rules | **Value Object** | SRP |
| Decouple layers | **Dependency Injection** | DIP |
| Aggregate multi-source data | **Data Assembler + Providers** | SRP, DIP, ISP |
| Serve multiple consumers/platforms | **DTO Transformer Strategy** | SRP, OCP, LSP |
| Optimize concurrent HTTP calls | **Async HTTP Facade** | SRP |

See `core/architecture-reference.md` for complete mapping (including AC-01 through AC-04 for API consumer patterns). Consult openspec/specs/architecture-profile.yaml for project patterns.

### Step 3.4: Verify SOLID Compliance

Before finalizing the plan:

```bash
# Validate proposed design against SOLID principles
/workflow-skill:solid-analyzer --mode=design --design=design.md

# Gate logic:
# - COMPLIANT → proceed to implementation
# - NEEDS_WORK → revise design before proceeding
# - NON_COMPLIANT → blocked, requires redesign
```

### SOLID Verdict Gate

| Verdict | Action |
|---------|--------|
| All relevant principles COMPLIANT | ✅ Approve — proceed to implementation |
| Any principle NEEDS_WORK | ⚠️ Revise — address noted concerns before proceeding |
| Any principle NON_COMPLIANT | ❌ Blocked — redesign required for non-compliant principles |
| Principles marked N/A | ✅ Acceptable — with justification for why principle does not apply |

### Step 3.5: Architectural Impact Analysis (when --show-impact=true)

After designing solutions, analyze the architectural impact across the codebase.

#### Layers Affected

```markdown
## Architectural Impact: ${FEATURE_ID}

### Layer Analysis

| Layer | Impact Level | Changes Required |
|-------|--------------|------------------|
| **Domain** | HIGH | 2 new entities, 1 extended |
| **Application** | MEDIUM | 3 new use cases |
| **Infrastructure** | MEDIUM | 2 new repositories |
| **Presentation/API** | LOW | 2 new endpoints |

### Affected Layers Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│ PRESENTATION                                                     │
│   [MODIFIED] UserController.php                                  │
│   [NEW] SubscriptionController.php                               │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│ APPLICATION                                                      │
│   [NEW] CreateSubscriptionUseCase.php                            │
│   [NEW] ApplyDiscountUseCase.php                                 │
│   [MODIFIED] CreateOrderUseCase.php                              │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│ DOMAIN                                                           │
│   [EXTENDED] User.php (add subscription_tier)                    │
│   [NEW] Subscription.php                                         │
│   [NEW] SubscriptionPlan.php                                     │
│   [NEW] DiscountCalculator.php (Domain Service)                  │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│ INFRASTRUCTURE                                                   │
│   [NEW] DoctrineSubscriptionRepository.php                       │
│   [NEW] StripePaymentGateway.php                                 │
│   [MODIFIED] migrations/Version20260201_subscriptions.php        │
└─────────────────────────────────────────────────────────────────┘
```

### Existing Modules Touched

| Module | Files Touched | Risk Level | Notes |
|--------|---------------|------------|-------|
| `src/User/` | 3 files | MEDIUM | Core entity modified |
| `src/Order/` | 2 files | LOW | Minor additions |
| `src/Subscription/` | 8 files | N/A | New module |
| `tests/` | 12 files | LOW | New tests + updates |

### Change Scope Estimation

```
╔══════════════════════════════════════════════════════════════════╗
║                    CHANGE SCOPE SUMMARY                          ║
╠══════════════════════════════════════════════════════════════════╣
║  Files to CREATE:     14                                         ║
║  Files to MODIFY:      6                                         ║
║  Files to DELETE:      0                                         ║
║  ────────────────────────────────────────────────                ║
║  Total files affected: 20                                        ║
║                                                                  ║
║  Estimated LOC added:   ~800                                     ║
║  Estimated LOC modified: ~120                                    ║
║  ────────────────────────────────────────────────                ║
║  Complexity: MEDIUM                                              ║
║  Estimated effort: 2-3 days                                      ║
╚══════════════════════════════════════════════════════════════════╝
```

### Risk Assessment

| Risk | Probability | Impact | Mitigation |
|------|-------------|--------|------------|
| User entity changes break existing tests | MEDIUM | HIGH | Run full test suite before merge |
| Payment gateway integration issues | LOW | HIGH | Use sandbox environment |
| Migration conflicts | LOW | MEDIUM | Coordinate with team |
```

### Phase 3 Quality Gate

Apply the Quality Gate Protocol (above) with these 4 checks before writing `design.md`:

1. **Spec coverage**: Every spec from Phase 2 has a corresponding solution. FAIL if any spec lacks one.
2. **Concrete files**: Each solution lists actual file paths to create/modify. FAIL if abstract ("implement a service").
3. **SOLID verdicts**: Each relevant principle has a reasoned verdict (COMPLIANT/N_A with justification). FAIL if missing reasoning.
4. **Architectural impact**: Lists specific layers and files to CREATE and MODIFY. FAIL if empty or "TBD".

**CDP check**: If design decisions contradict `architecture-profile.yaml` patterns or `constitution.md` principles, apply the Contradiction Detection Protocol (`framework_rules.md` §12).

### Phase 3.5: Security Threat Analysis (conditional: planning_depth=full)

When `planning_depth=full` (complex features or sensitive areas), add a lightweight threat analysis:

- **Attack surface**: Which new endpoints/inputs does this feature introduce?
- **Trust boundaries**: Where does data cross trust boundaries (user→server, service→service)?
- **Sensitive data**: Does this feature handle PII, credentials, tokens, or payment data?
- **Mitigation**: For each identified threat, specify the mitigation strategy in `design.md`

Skip this phase when `planning_depth=standard` or `planning_depth=minimal`.

---

---

## Task Template (includes SOLID)

Each task must include SOLID requirements:

```markdown
### Task BE-001: Create User Entity

**Role**: Backend Engineer
**Methodology**: TDD (Red-Green-Refactor)

**Functional Requirement** (from SPEC-F01):
- User entity with id, email, name, password

**SOLID Requirements** (from solution design):
- **SRP**: Entity only holds data, no business logic
- **DIP**: No infrastructure imports in Entity
- **Pattern**: Value Object for Email

**Tests to Write FIRST**:
- [ ] test_user_can_be_created_with_valid_data()
- [ ] test_email_value_object_validates_format()

**Acceptance Criteria**:
- [ ] Entity in src/Domain/Entity/
- [ ] Value Object in src/Domain/ValueObject/
- [ ] Architecture: Follow project patterns, SOLID compliance required per solid-analyzer
- [ ] No Doctrine imports in Domain

**Reference**: src/Domain/Entity/Order.php (existing pattern)
```

---

## Planning Checklist

Before marking planning as COMPLETED, verify each phase delivered its key outputs:

| Phase | Must Have |
|-------|----------|
| Step 0 | Existing specs loaded, architecture context understood |
| Phase 1 | Problem documented, constraints identified, clarifications asked |
| Phase 2 | Testable specs (WHAT not HOW), EXTENDED/MODIFIED/NEW entities + endpoints, conflicts resolved |
| Phase 3 | SOLID baseline analyzed, patterns selected, all principles COMPLIANT, layers + modules + files documented |
| Final | Engineer can start WITHOUT asking questions, integration with existing code is clear |

**If SOLID is not addressed in Phase 3, the plan is INCOMPLETE.**
**If integration analysis is missing, the plan treats feature as ISOLATED (anti-pattern).**

---

## Self-Review (Reflection Pattern — MANDATORY before Completeness Verification)

Before verifying completeness, the planner performs a structured self-critique of the entire plan:

```
SELF-REVIEW PROTOCOL:

1. DESIGN COHERENCE: Re-read design.md end-to-end. Ask:
   - Does each solution actually solve the spec it references?
   - Are there contradictions between solutions (e.g., two solutions creating the same class differently)?
   - Could any solution be simpler without losing functionality?

2. TESTABILITY CHECK: Re-read test contract sketch in specs.md. Ask:
   - Can every acceptance criterion be tested with the designed architecture?
   - Are there hidden dependencies that make testing difficult?

3. INTEGRATION SANITY: Compare design.md against openspec/specs/ baseline. Ask:
   - Does the design conflict with any existing pattern in the codebase?
   - Are all extended/modified entities backward-compatible?

4. DECISION LOG AUDIT: Review the Decision Log in tasks.md. Ask:
   - Is every non-obvious design choice documented with rationale?
   - Are there implicit decisions that should be explicit?

IF self-review finds issues:
  → Fix them BEFORE presenting to user
  → Log what was caught in Decision Log: "Self-review: [what was fixed and why]"

IF no issues found:
  → Proceed to Completeness Verification
```

This is NOT the multi-agent review (`/workflows:review`). This is the planner's own critical reflection before handing off to the implementer.

---

## Decision Log Enforcement (MANDATORY in Phase 4)

Every non-obvious design decision MUST be logged in `tasks.md`:

```markdown
## Decision Log

| # | Decision | Alternatives Considered | Rationale | Phase | Risk |
|---|----------|------------------------|-----------|-------|------|
| D-001 | Use Strategy pattern for validators | Chain of Responsibility, simple if/else | Multiple validator types expected, OCP compliance | Phase 3 | LOW |
| D-002 | JWT over session-based auth | Session cookies, OAuth2 | Stateless API requirement, mobile client support | Phase 3 | MEDIUM |
| D-003 | Self-review: simplified User entity | Original had 12 methods | SRP violation caught during self-review | Self-Review | LOW |
```

The Decision Log:
- Is populated during Phase 3 (design decisions) and Phase 4 (task breakdown decisions)
- Is updated during Self-Review when issues are caught and fixed
- Is carried forward into `/workflows:work` and `/workflows:review` for traceability
- Feeds into `/workflows:compound` for learning extraction

---

## Plan Completeness Verification (MANDATORY before marking COMPLETED)

Before setting planner status to `COMPLETED`, verify:

1. **Files exist**: All 4 output files (`proposal.md`, `specs.md`, `design.md`, `tasks.md`) exist in `openspec/changes/${FEATURE_ID}/`. If missing → generate them. Do NOT mark COMPLETED with missing files.
2. **Substantive content**: Each file has ≥5 non-header content lines. If insufficient → enrich and rewrite.
3. **Cross-reference**: Every user requirement maps to ≥1 spec, every spec maps to ≥1 task. If gaps found → ask user whether to add them.
4. **Decision Log**: tasks.md contains ≥1 decision entry. FAIL if Decision Log is empty — every plan has at least one non-obvious choice.
5. **Test Contract Sketch**: specs.md contains test mapping table (Phase 2.5). FAIL if missing for `planning_depth=full` or `standard`.
6. **Self-Review done**: Self-Review Protocol was executed and any fixes logged.
7. **User confirmation**: Present summary (spec count, task count, files to create/modify, SOLID status, decision count). Ask: "Ready for /workflows:work? (yes/review/revise)"
8. **Mark COMPLETED**: Update tasks.md Workflow State → Planner: COMPLETED, all phases COMPLETED with timestamps.

---

## Output Files

### OpenSpec Structure (all workflows):
```
openspec/changes/${FEATURE_ID}/
├── proposal.md     # Phase 1: Problem statement, motivation, scope, success criteria
├── specs.md        # Phase 2: Functional specs (WHAT) + Integration Analysis + Test Contract Sketch
├── design.md       # Phase 3: Solutions + SOLID + Architectural Impact (HOW)
├── tasks.md        # Phase 4: Task breakdown + verify conditions + Workflow State + Decision Log
└── scratchpad.md   # Runtime: Working notes, hypotheses, context breadcrumbs (ephemeral)
```

### Project-Level Specs (baseline):
```
openspec/specs/
├── constitution.md               # Non-negotiable project principles (template: core/templates/constitution-template.md)
├── architecture-profile.yaml     # Project architecture patterns and SOLID baseline
├── api-architecture-diagnostic.yaml  # API dimensional profile (generated by /workflows:discover)
├── entities/                     # Domain model specs
├── api-contracts/                # API endpoint specs
├── business-rules/               # Business logic constraints
└── architectural-constraints/    # System boundaries
```

The structure for `specs.md` and `design.md` is defined inline in Phase 2 and Phase 3 respectively.

---

## Summary: Architecture-First Planning

| Step/Phase | Content | Output File | SOLID? | Integration? |
|------------|---------|-------------|--------|--------------|
| **Step 0: Load Specs** | Existing architecture context | (context only) | ❌ No | ✅ **YES - CONTEXT** |
| Phase 1: Understand | Problem statement | `proposal.md` | ❌ No | ❌ No |
| **Phase 2: Specs** | Functional requirements (WHAT) | `specs.md` | ❌ No | ✅ **YES - ANALYSIS** |
| **Phase 3: Design** | Technical design (HOW) | `design.md` | ✅ **YES - MANDATORY** | ✅ **YES - IMPACT** |
| Phase 4: Tasks | Task breakdown + verify + state | `tasks.md` | ❌ No | ❌ No |

**SOLID is a design CONSTRAINT in Phase 3, not a functional SPEC in Phase 2.**

**Integration analysis ensures every feature is designed as an EXTENSION of existing architecture, not an isolated solution.**

### The Integration Mindset

```
❌ WRONG: "I'll create a new User service for authentication"
✅ RIGHT: "I'll EXTEND the existing architecture with User entity,
          MODIFY Order to require auth, and CREATE 3 new endpoints"
```

Every planning session should answer:
1. What EXISTING specs will this feature EXTEND?
2. What EXISTING specs will this feature MODIFY?
3. What NEW specs will this feature CREATE?
4. Are there any CONFLICTS with existing specs?
5. What is the ARCHITECTURAL IMPACT (layers, modules, files)?

---

## Chunking Directive

Large planning outputs should be chunked to avoid context exhaustion:

| Phase | Max output size | Action if exceeded |
|-------|----------------|-------------------|
| Phase 1 (proposal.md) | ~200 lines | Split into problem statement + appendix |
| Phase 2 (specs.md) | ~300 lines | Group specs by domain boundary |
| Phase 3 (design.md) | ~400 lines | Split into core design + detailed appendix |
| Phase 4 (tasks.md) | ~200 lines | Use task IDs, keep descriptions concise |

If any phase output exceeds its limit, write the core content first, then append supplementary detail.

---

## Error Recovery

- **Phase output fails to write**: Retry write. If disk error persists, write to a fallback path (`/tmp/openspec-${FEATURE_ID}/`) and notify user.
- **Quality Gate fails after max BCP iterations (3)**: Document the blocker in `tasks.md`, mark phase as BLOCKED, and present the issue to the user for decision.
- **Spec conflicts detected in Phase 2**: Log conflict in `specs.md` with `[CONFLICT]` tag. Do not proceed to Phase 3 until conflicts are resolved (user decision or merge).
- **HITL checkpoint rejected by user**: Return to the previous phase output, incorporate user feedback, and re-run the phase. Do not skip the checkpoint.
- **Session interrupted mid-phase**: On resume, read `tasks.md` Planning Progress to identify last completed phase. Re-read the last written output file to verify integrity. Continue from the next phase.

---

## Related Commands

- `/workflow-skill:criteria-generator` - Generate functional specs
- `/workflow-skill:solid-analyzer` - Analyze SOLID compliance
- `/workflow-skill:spec-analyzer` - Validate specs and detect conflicts
- `/workflows:work` - Execute the plan
- `/workflows:review` - Review implementation

## Related Documentation

- `core/architecture-reference.md` - Violation → Pattern mapping and quality metrics
- `skills/workflow-skill-solid-analyzer.md` - SOLID analysis tool
