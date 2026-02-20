---
name: workflow-skill-solid-analyzer
description: "Contextual SOLID analysis with 3 modes: baseline (detect current state), design (validate design before implementation), verify (check code against design). Reads architecture-profile.yaml for project-specific analysis. <example>Context: Need to analyze SOLID compliance before designing.\\nuser: \"Check SOLID baseline for the user module\"\\nassistant: \"I'll use workflow-skill-solid-analyzer --mode=baseline --path=src/User to analyze the current state\"</example>"
model: inherit
context: fork
hooks:
  Stop:
    - command: "echo '[solid-analyzer] SOLID analysis complete.'"
---

# SOLID Analyzer Skill

Contextual SOLID analysis tool that evaluates code against the project's own architecture profile, not against static numeric scores. Every analysis is adapted to the project's stack, paradigm, and established patterns.

## Philosophy

> "SOLID compliance depends on context — a Go project and a PHP project need different analysis"

This skill provides **contextual, per-principle** SOLID analysis:
- **Adapted** to the project's stack, paradigm, and conventions (from `architecture-profile.yaml`)
- **Per-principle verdicts**: COMPLIANT, NEEDS_WORK, NON_COMPLIANT, or N/A — with evidence
- **3 modes** for different workflow stages: baseline, design validation, code verification

---

## Invocation

```bash
# MODE 1: BASELINE — Analyze existing code state before designing
/workflow-skill:solid-analyzer --mode=baseline --path=src/relevant-module

# MODE 2: DESIGN_VALIDATE — Validate a design before implementation
/workflow-skill:solid-analyzer --mode=design --design=design.md

# MODE 3: CODE_VERIFY — Verify implemented code against design
/workflow-skill:solid-analyzer --mode=verify --path=src/modified-path --design=design.md

# CODE_VERIFY with full scope (for review phase)
/workflow-skill:solid-analyzer --mode=verify --path=src --design=design.md --scope=full
```

---

## Common Logic (All Modes)

### Step 1: Load Architecture Profile

```
1. READ openspec/specs/architecture-profile.yaml
   - If exists: load stack, paradigm, solid_relevance, conventions, reference_files, quality_thresholds
   - If NOT exists: use fallback (see "Fallback Without Profile" section below)
```

### Step 2: Determine Principle Relevance

For each SOLID principle, check `solid_relevance.{principle}.relevance` from the profile:

| Relevance | Meaning | Impact on Verdict |
|-----------|---------|-------------------|
| `critical` | Core to this project's architecture | Any violation → NON_COMPLIANT |
| `high` | Important for this project | Violation → NEEDS_WORK (or NON_COMPLIANT if severe) |
| `medium` | Applicable but not central | Violation → NEEDS_WORK |
| `low` | Not very relevant for this stack/paradigm | Skip or note as informational |

### Step 3: Apply Stack-Adapted Detection

Detection rules adapt to the project's stack from the profile:

**PHP/Java (OOP with explicit interfaces)**:
- SRP: Measure by class (LOC, public methods, constructor deps)
- OCP: Look for switch/if-else chains by type, instanceof
- LSP: Check override contracts, preconditions/postconditions
- ISP: Count interface methods, check for empty implementations
- DIP: Check imports between layers, `new ConcreteClass()` in Domain

**Go (structs, implicit interfaces)**:
- SRP: Measure by struct + methods, or by package
- OCP: Look for type switches, check for interface-based extension points
- LSP: Check interface satisfaction across implementations
- ISP: Interfaces are naturally small in Go — check package-level API surface
- DIP: Check package import direction, no concrete dependencies crossing boundaries

**Python (duck typing, dynamic)**:
- SRP: Measure by class/module (LOC, method count)
- OCP: Look for isinstance chains, type string comparisons
- LSP: Check ABC/Protocol compliance
- ISP: Check Protocol definitions, `__all__` exports, ABC method counts
- DIP: Check import direction between packages/layers

**TypeScript (mixed paradigm)**:
- SRP: Measure by class or module depending on paradigm used
- OCP: Look for type guards, union type switches, if-else by type
- LSP: Check interface/type compliance
- ISP: Count interface members, check for Partial<> usage indicating fat interfaces
- DIP: Check import paths between layers/modules

**Functional (Haskell, Elm, Clojure, JS functional)**:
- SRP: Measure by module/function. Pure functions with single purpose.
- OCP: Check for composition patterns, higher-order functions as extension points
- LSP: Check type class/protocol implementations
- ISP: Check module export surface — minimal public API
- DIP: Check for dependency injection via function parameters

**API Consumer Patterns (Cross-Stack)**:

> **Dimensional Optimization**: When `openspec/specs/api-architecture-diagnostic.yaml` exists, use its dimensional values to skip irrelevant rules and reduce false positives:
>
> | Dimension Value | Rules to SKIP | Reason |
> |----------------|---------------|--------|
> | `dependency_isolation == fully_isolated` | DIP-005, DIP-006, DIP-007, DIP-008 | All externals already behind proper abstraction |
> | `dependency_isolation == no_externals` | DIP-005, DIP-006, DIP-007, DIP-008, SRP-007, SRP-008 | No external dependencies to isolate |
> | `consumer_diversity == single_consumer` | SRP-006, OCP-004 | No multi-platform serialization needed |
> | `response_customization == uniform` | SRP-006, OCP-004 | No response variation to manage |
> | `concurrency_model == not_applicable` | Async HTTP analysis | No concurrent operations to optimize |
> | `concurrency_model == fully_concurrent` | Async HTTP analysis | Already using concurrent patterns |
>
> When diagnostic does NOT exist, run all rules (default behavior).

When `architecture-profile.yaml` indicates `http_client_pattern != "none"`:
- **SRP**: Check serializers/transformers for platform switching logic (SRP-006). Check assemblers for too many data sources (SRP-007). Check for HTTP calls mixed with business logic (SRP-008).
- **OCP**: Check serialization/transformation code for if/switch by consumer platform (OCP-004).
- **DIP**: Check Domain/ and Application/ for vendor HTTP SDK imports (DIP-005, DIP-006). Check for port interfaces matching each HTTP adapter (DIP-007). Check for vendor response types outside Infrastructure (DIP-008).

Detection Commands (API Consumer):

```bash
# SRP-006: Fat Serializer
find . -name "*Transformer*" -o -name "*Serializer*" -o -name "*Normalizer*" | while read f; do
  wc -l "$f"     # >200 LOC = potential SRP-006
done
grep -r "if.*platform\|switch.*format\|mobile.*web" $(find . -name "*Transformer*" -o -name "*Normalizer*") 2>/dev/null

# SRP-007: Fat Assembler (>7 constructor dependencies)
grep -A20 "__construct\|constructor\|func New" $(find . -name "*Assembler*" -o -name "*Aggregator*") 2>/dev/null

# DIP-005/006: Vendor SDK in Domain/Application
# PHP
grep -r "use GuzzleHttp\|use Symfony\\Component\\HttpClient\|use Symfony\\Contracts\\HttpClient" src/Domain/ src/Application/ 2>/dev/null
# TypeScript
grep -r "import.*from.*axios\|import.*from.*node-fetch" src/domain/ src/application/ 2>/dev/null
# Go
grep -r "\"net/http\"\|\"github.com.*client\"" domain/ internal/domain/ 2>/dev/null

# DIP-007: Missing Port for External API
find . -path "*/Infrastructure/External/*" -name "*Adapter*" -o -name "*Client*" | while read adapter; do
  class=$(basename "$adapter" | sed 's/\..*//')
  grep -rl "interface.*${class}\|${class}Interface" src/Domain/ src/domain/ 2>/dev/null || echo "MISSING PORT for: $adapter"
done

# OCP-004: Platform switching in serializers
grep -rn "if.*mobile\|if.*platform\|switch.*platform\|switch.*format" $(find . -name "*Transform*" -o -name "*Serializ*" -o -name "*Normaliz*") 2>/dev/null
```

### Step 4: Compare Against References

If the profile has `reference_files`:
- Read the reference file for the relevant archetype
- Compare the analyzed code's structure against the reference
- Note deviations (positive or negative)

### Step 5: Emit Per-Principle Verdict

For each relevant principle:

```
{PRINCIPLE}: {VERDICT} — "{evidence/reasoning}"
```

Verdicts:
- **COMPLIANT**: The code/design follows this principle correctly
- **NEEDS_WORK**: Minor issues detected, not blocking but should be addressed
- **NON_COMPLIANT**: Significant violation that must be fixed
- **N/A**: Principle not applicable (e.g., LSP when there's no inheritance, ISP in Go)

### Step 6: Global Verdict

```
COMPLIANT      → All relevant principles are COMPLIANT or N/A
NEEDS_WORK     → At least one principle is NEEDS_WORK, none NON_COMPLIANT
NON_COMPLIANT  → At least one principle with relevance≥high is NON_COMPLIANT
```

---

## Mode 1: BASELINE

**Invocation**: `/workflow-skill:solid-analyzer --mode=baseline --path=src/relevant-module`

**Purpose**: Understand the current state of existing code BEFORE designing a solution. Used in Plan Phase 3, Step 3.1.

**Input**:
- `--path`: Path to the existing code to analyze
- Architecture profile (loaded automatically)

**Process**:
1. Load architecture profile (Step 1)
2. Scan the specified path for code files
3. For each SOLID principle with relevance ≥ medium:
   a. Apply detection rules adapted to the stack
   b. Identify existing patterns in use
   c. Identify current violations
   d. Note reference files that exemplify good practices
4. Compile baseline report

**Output Format**:

```markdown
# SOLID Baseline Analysis

**Path analyzed**: {path}
**Stack**: {language} / {framework} / {paradigm}
**Architecture**: {pattern}

## Patterns Already in Use
- Repository pattern: `src/Domain/Port/UserRepositoryInterface.php` + `src/Infrastructure/Persistence/DoctrineUserRepository.php`
- Strategy pattern: `src/Domain/Service/Pricing/PricingStrategy.php`
- Value Objects: `src/Domain/ValueObject/Money.php`, `src/Domain/ValueObject/Email.php`

## Current SOLID State

### SRP (relevance: high)
**Status**: NEEDS_WORK
- `src/Service/OrderService.php`: 342 LOC, 15 public methods, 9 constructor deps — exceeds thresholds
- `src/Service/UserService.php`: 89 LOC, 4 methods — COMPLIANT
- Reference good: `src/Domain/Entity/Order.php` (45 LOC, single responsibility)

### OCP (relevance: high)
**Status**: COMPLIANT
- No type-switching detected
- Strategy pattern already used for pricing

### LSP (relevance: medium)
**Status**: N/A
- No inheritance hierarchies in analyzed path

### ISP (relevance: high)
**Status**: COMPLIANT
- All interfaces ≤5 methods
- No empty implementations detected

### DIP (relevance: critical)
**Status**: COMPLIANT
- Domain/ has zero imports from Infrastructure/
- All dependencies injected via constructor interfaces

## API Consumer Patterns (if detected)

- Anti-Corruption Layer: `src/Domain/Port/ContentProviderInterface.php` + `src/Infrastructure/External/CmsApiAdapter.php` — COMPLIANT
- Vendor SDK Leakage: `src/Application/Service/EditorialService.php` imports GuzzleHttp — DIP-005 VIOLATION
- Fat Serializer: `src/Application/Transformer/EditorialTransformer.php` — 380 LOC with platform switch — SRP-006 + OCP-004 VIOLATIONS
- Async HTTP: Sequential calls detected in `EditorialAssembler` — 5 HTTP calls in sequence — NEEDS_WORK

## Recommendations for Design Phase
- OrderService needs decomposition (SRP violation)
- Consider the project's existing Strategy pattern for extracting algorithms
- Follow the Reference good files for naming and structure conventions
- Address vendor SDK leakage with Anti-Corruption Layer (see AC-01 in architecture-reference)
- Decompose fat serializer with DTO Transformer Strategy (see AC-04)
- Group sequential HTTP calls with async facade (see AC-03)
```

**Who consumes this**: The agent in Plan Step 3.2 uses this to design solutions coherent with the project.

---

## Mode 2: DESIGN_VALIDATE

**Invocation**: `/workflow-skill:solid-analyzer --mode=design --design=design.md`

**Purpose**: Validate that a proposed design respects SOLID BEFORE implementation. Used in Plan Phase 3, Step 3.4.

**Input**:
- `--design`: Path to the design document (design.md)
- Architecture profile (loaded automatically)

**Process**:
1. Load architecture profile (Step 1)
2. Read design.md — extract:
   - Proposed classes/modules/functions
   - Their responsibilities
   - Dependency structure
   - Patterns planned
3. For each SOLID principle with relevance ≥ medium:
   a. Evaluate whether the design satisfies the principle
   b. Compare against profile's conventions and patterns
   c. Check consistency with reference files
4. Emit per-principle verdict

**Output Format**:

```markdown
# SOLID Design Validation

**Design**: {design.md path}
**Stack**: {language} / {framework} / {paradigm}

## Per-Principle Analysis

### SRP: COMPLIANT
Each proposed class has a single responsibility:
- `UserService`: orchestrates user creation (single use case)
- `Email` (Value Object): validates and encapsulates email format
- `PasswordHasher` (Infrastructure): handles hashing logic

### OCP: COMPLIANT
Strategy pattern for token generation allows adding new token types without modifying existing code.
Consistent with project's existing pattern in `src/Domain/Service/Pricing/PricingStrategy.php`.

### LSP: N/A
No inheritance in this design.

### ISP: COMPLIANT
- `UserRepositoryInterface`: 3 methods (below threshold of 5)
- `TokenGeneratorInterface`: 2 methods

### DIP: COMPLIANT
- Domain defines `UserRepositoryInterface` and `TokenGeneratorInterface`
- Infrastructure implements both
- No Domain → Infrastructure dependencies

## Global Verdict: COMPLIANT

**Gate**: Design may proceed to Phase 4 (tasks).
```

**Gate Logic**:
- `COMPLIANT` → Design proceeds to task generation
- `NEEDS_WORK` → Agent must revise the specific principles flagged, then re-validate
- `NON_COMPLIANT` → Design is blocked. Agent must return to Step 3.2 and redesign

---

## Mode 3: CODE_VERIFY

**Invocation**: `/workflow-skill:solid-analyzer --mode=verify --path=src/modified-path --design=design.md`

**Purpose**: Verify that IMPLEMENTED code matches both SOLID principles and the approved design. Used in Work Steps 5/7 and Review Phase 4.

**Input**:
- `--path`: Path to the implemented code
- `--design`: Path to the approved design document
- `--scope=full` (optional): Verify the entire codebase, not just modified path. Used in Review.
- Architecture profile (loaded automatically)

**Process**:
1. Load architecture profile (Step 1)
2. Read design.md — extract expected structure
3. Scan the implemented code at --path
4. For each SOLID principle with relevance ≥ medium:
   a. Apply stack-adapted detection rules to the actual code
   b. Compare against the design expectations
   c. Verify patterns were implemented as designed
5. Emit per-principle verdict with design match information

**Output Format**:

```markdown
# SOLID Code Verification

**Path verified**: {path}
**Design reference**: {design.md path}
**Scope**: {normal | full}

## Per-Principle Verification

### SRP: COMPLIANT
- `UserService`: 45 LOC, 3 public methods, 1 responsibility ✓
- `Email` (Value Object): 28 LOC, immutable, single validation purpose ✓
- `PasswordHasher`: 32 LOC, hashing only ✓

### OCP: COMPLIANT
- `TokenGeneratorInterface` + `JwtTokenGenerator` + `OpaqueTokenGenerator` ✓
- New token type = new class, no modification needed ✓

### LSP: N/A
No inheritance in implementation.

### ISP: COMPLIANT
- `UserRepositoryInterface`: 3 methods ✓
- `TokenGeneratorInterface`: 2 methods ✓

### DIP: COMPLIANT
- Domain/ has zero imports from Infrastructure/ ✓
- All dependencies injected via constructor ✓

## Design Match Verification

| Design Element | Expected | Implemented | Match |
|---------------|----------|-------------|-------|
| Strategy for tokens | TokenGeneratorInterface | ✓ JwtTokenGenerator + OpaqueTokenGenerator | ✓ |
| Repository pattern | UserRepositoryInterface in Domain | ✓ DoctrineUserRepository in Infrastructure | ✓ |
| Value Object Email | Immutable, self-validating | ✓ Email VO with validation | ✓ |

## Global Verdict: COMPLIANT

**Implementation matches design and satisfies all relevant SOLID principles.**
```

**Gate Logic**:
- In **Work Step 5** (after TDD cycle):
  - `COMPLIANT` → Proceed to next task
  - `NEEDS_WORK` → Refactor before proceeding
  - `NON_COMPLIANT` → Enter BCP correction loop
- In **Work Step 7** (checkpoint):
  - `COMPLIANT` → Checkpoint passes
  - `NON_COMPLIANT` on any layer → Checkpoint fails
- In **Review Phase 4** (--scope=full):
  - `COMPLIANT` → Approve
  - `NEEDS_WORK` on relevance=medium → Approve with notes
  - `NON_COMPLIANT` on relevance≥high → REJECT

---

## Fallback Without Architecture Profile

If `openspec/specs/architecture-profile.yaml` does not exist (project hasn't run `discover --setup`):

```
1. Detect stack by heuristics:
   - package.json → TypeScript/JavaScript
   - composer.json → PHP
   - go.mod → Go
   - Cargo.toml → Rust
   - requirements.txt / pyproject.toml → Python
   - pom.xml / build.gradle → Java

2. Assume all principles relevance = medium

3. Use default thresholds:
   - max_class_loc: 200
   - max_public_methods: 7
   - max_constructor_deps: 7
   - max_interface_methods: 5

4. No reference_files available (skip comparison step)

5. WARN in output:
   "⚠️ No architecture profile found. Analysis uses default settings.
    Run /workflows:discover --setup for project-specific SOLID analysis."
```

---

## Detection Rules Reference

### SRP Detection

| Rule ID | Violation | Detection Method | Default Threshold |
|---------|-----------|-----------------|-------------------|
| SRP-001 | God Class | Lines of code per class | > max_class_loc from profile |
| SRP-002 | Too Many Methods | Public method count | > max_public_methods from profile |
| SRP-003 | Too Many Dependencies | Constructor params | > max_constructor_deps from profile |
| SRP-004 | Mixed Concerns | Domain + Infrastructure imports | Any mix |
| SRP-005 | Multiple Responsibilities | Class name analysis (Manager, Handler, etc.) | Name pattern |
| SRP-006 | Fat Serializer/Transformer | Class with serialization logic + platform switching | Class name contains "Serializer"/"Transformer" AND has if/switch by platform |
| SRP-007 | Fat Assembler | Service assembling data from >5 sources in one method | Method with >5 distinct HTTP/provider calls; class with >7 provider dependencies |
| SRP-008 | Mixed HTTP + Business | HTTP client calls mixed with business logic in same class | Domain/Application class importing HTTP client classes |

### OCP Detection

| Rule ID | Violation | Detection Method | Threshold |
|---------|-----------|-----------------|-----------|
| OCP-001 | Type Switching | switch/if-else by type | Any occurrence |
| OCP-002 | Instanceof Chains | Multiple instanceof/is checks | >2 in method |
| OCP-003 | Hardcoded Types | String type comparisons | Any occurrence |
| OCP-004 | Platform Switching in Serializer | if/switch by platform in serialization code | `if ($platform === 'mobile')` or `switch($format)` in Transformer/Serializer |
| OCP-005 | Hardcoded External API Config | Inline URLs, API keys, or vendor config in adapter | Hardcoded strings matching URL/key patterns in Adapter classes |

### LSP Detection

| Rule ID | Violation | Detection Method | Threshold |
|---------|-----------|-----------------|-----------|
| LSP-001 | Exception in Override | throw in overridden method | New exception types |
| LSP-002 | Empty Implementation | Empty method body or return null | Any occurrence |
| LSP-003 | Contract Change | Different behavior in subtype | Analysis-based |

### ISP Detection

| Rule ID | Violation | Detection Method | Threshold |
|---------|-----------|-----------------|-----------|
| ISP-001 | Fat Interface | Interface method count | > max_interface_methods from profile |
| ISP-002 | Unused Methods | NotImplementedException | Any occurrence |
| ISP-003 | Partial Implementation | Empty methods in impl | Any occurrence |

### DIP Detection

| Rule ID | Violation | Detection Method | Threshold |
|---------|-----------|-----------------|-----------|
| DIP-001 | Concrete Dependency | new ConcreteClass() | Any in domain layer |
| DIP-002 | Layer Violation | Domain→Infrastructure import | Any occurrence |
| DIP-003 | Missing Interface | Service/Repository without interface | Any occurrence |
| DIP-004 | Static Calls | Static method calls in domain | Any occurrence |
| DIP-005 | Vendor SDK in Domain | Domain layer imports vendor HTTP SDK classes | Domain/ files importing Guzzle, HttpClient, axios, requests, net/http |
| DIP-006 | Vendor SDK in Application | Application layer imports vendor HTTP SDK classes | Application/ files importing vendor HTTP client packages |
| DIP-007 | Missing Port for External API | HTTP adapter exists but no domain interface/port | Infrastructure adapter class with no corresponding interface in Domain/ |
| DIP-008 | Vendor Response Types Leaked | Vendor response objects used outside Infrastructure | Vendor SDK response types appearing in Application/Domain type hints |

---

## Workflow Integration

### Where This Skill Is Used

| Workflow Phase | Mode | Purpose |
|---------------|------|---------|
| `plan` Step 3.1 | `--mode=baseline` | Understand current SOLID state before designing |
| `plan` Step 3.4 | `--mode=design` | Validate design satisfies SOLID before implementation |
| `work` Step 5 | `--mode=verify` | Check code after TDD cycle |
| `work` Step 7 | `--mode=verify` | Checkpoint verification per layer |
| `review` Phase 4 | `--mode=verify --scope=full` | Final full-scope verification |

### Automatic Triggers

The analyzer runs when:
- `/workflows:plan` Phase 3 starts (baseline mode)
- `/workflows:plan` Step 3.4 validates design (design mode)
- `/workflows:work` completes a TDD cycle (verify mode)
- `/workflows:work` reaches a checkpoint (verify mode)
- `/workflows:review` Phase 4 QA (verify mode, full scope)

---

## Related

- `core/architecture-reference.md` - SOLID principles, patterns, and quality criteria reference
- `core/templates/architecture-profile-template.yaml` - Template for project architecture profile
- `openspec/specs/architecture-profile.yaml` - Project-specific architecture profile (generated by discover)
- `agents/review/architecture-reviewer.md` - Architecture validation agent
