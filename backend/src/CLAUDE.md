# Writing Code — Implementation Rules

This file loads when you edit files in `backend/src/`. It contains the rules
for writing production code: TDD discipline, debugging process, and patterns
specific to this codebase.

<!-- GENERIC-START -->
## The TDD Cycle

**Why:** If you didn't watch the test fail, you don't know if it tests the right thing.
Tests written after code that pass immediately prove nothing — they test your
implementation, not your requirements.

### The Iron Law
NO PRODUCTION CODE WITHOUT A FAILING TEST FIRST.
Write code before the test? Delete it. Start over. No exceptions.

### Red-Green-Refactor
1. **RED** — Write one test for one behavior. Run it. It MUST fail (not error).
   The failure message must describe the missing feature.
2. **GREEN** — Write the simplest code to make it pass. Nothing more.
3. **REFACTOR** — Only after green: remove duplication, improve names. Keep tests green.
   In Phase 1 (v0): minimal cleanup. In Phase 2 (Mature): refactor IS the work.

### Rationalizations That Mean "Start Over"
- "Too simple to test" → Simple code breaks. Test takes 30 seconds.
- "I'll test after" → Tests passing immediately prove nothing.
- "Need to explore first" → Fine. Throw away exploration, start with TDD.
- Code exists before test → DELETE it. Sunk cost fallacy.
<!-- GENERIC-END -->

<!-- GENERIC-START -->
## Systematic Debugging

**Why:** Fixing symptoms instead of root causes creates an endless cycle of patches.
A systematic approach takes 15-30 minutes; random fixes take 2-3 hours.

### The Iron Law
NO FIXES WITHOUT ROOT CAUSE INVESTIGATION FIRST.

### Phase 1: Root Cause Investigation (MANDATORY before any fix)
1. Read error messages completely (don't skip stack traces)
2. Reproduce consistently (if you can't trigger it reliably, gather more data)
3. Check recent changes (git diff, new dependencies, config)
4. Trace data flow (where does the bad value originate? Fix at source, not symptom)

### Phase 2: Pattern Analysis
1. Find working examples in same codebase
2. Compare ALL differences between working and broken

### Phase 2.5: Pattern-Wide Investigation (MANDATORY before implementing fix)
After finding root cause of ONE bug, search for the same defective pattern across
the entire codebase. The fix must cover ALL instances, not just the reported one.
1. Abstract the pattern into a searchable form
2. Grep/Glob across entire codebase
3. Evaluate each instance (not all matches are bugs)
4. Include all defective instances in one fix

### Phase 3: Single Hypothesis
Form ONE hypothesis. Make SMALLEST possible change. One variable at a time.
If 3+ fixes fail: STOP. Question the architecture.

### Phase 4: Implementation
Create failing test → implement single fix → verify → no regressions.
<!-- GENERIC-END -->

<!-- GENERIC-START -->
## Executing Plans

**Why:** Plans exist to be followed, not reinterpreted. If a plan has problems,
raise them before starting — don't silently deviate.

1. Load plan, review critically. If concerns → raise with user BEFORE starting.
2. Execute each task exactly as specified.
3. Run verifications as specified in each task.
4. STOP and ask when: blocker, gap, unclear instruction, repeated verification failure.
<!-- GENERIC-END -->

<!-- PROJECT-SPECIFIC-START -->
## Critical Patterns

### Entity Identity
- **Internal PK:** BIGINT auto-increment (`id`) — joins, internal processing
- **Public ID:** ULID (`public_id`) via `PublicIdTrait` — APIs, URLs, Mercure topics
- **NEVER expose internal `id` in public APIs**

### Multi-Tenancy
- `CustomerTenantFilter` (Doctrine SQL filter) + `CustomerScopedEntityInterface`
- Admin/Operator bypass; ROLE_CUSTOMER and ROLE_DRIVER scoped

### Role Hierarchy
ROLE_ADMIN > ROLE_OPERATOR > ROLE_CUSTOMER
ROLE_ADMIN > ROLE_DRIVER

### Constructor Signature Changes
When modifying a constructor:
1. Search ALL call sites: `grep -r "new ClassName("` in `src/` AND `tests/`
2. Check Factory classes — they use `new` directly, NOT auto-wired by Symfony
3. Check DI config (services.yaml)
4. Run tests — verify no ArgumentCountError or TypeError

**Why:** Factories use `new` directly. Changing a constructor without updating
its Factory causes runtime errors that tests may not catch.

### DDD Anti-Patterns
- `$em->persist()` in domain services → use `RepositoryInterface::save()`
- `$em->getRepository()->createQueryBuilder()` in services → method in RepositoryInterface
- `EntityManagerInterface` in domain service constructors → depend on RepositoryInterface
- Lifecycle callbacks in DDD entities → timestamps in constructor or domain service
<!-- PROJECT-SPECIFIC-END -->
