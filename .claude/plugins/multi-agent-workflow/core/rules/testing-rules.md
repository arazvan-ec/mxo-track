# Testing Rules

Rules that apply when working with test files and test-related code.

**Applies to**: Test files (`*Test.php`, `*.test.ts`, `*.test.tsx`, `*.spec.ts`, `*.spec.tsx`), test configuration, and test utilities.

---

## TDD Workflow

Follow the Red-Green-Refactor cycle:

1. **Red**: Write a failing test that describes expected behavior
2. **Green**: Write the minimum code to make the test pass
3. **Refactor**: Improve code structure while keeping tests green

Always write tests before implementation. The test defines the contract; the implementation fulfills it.

## Test Requirements

- Every task includes tests as part of its definition of done
- Prefer unit tests for business logic, integration tests for infrastructure
- Test names should describe behavior, not implementation (`test_user_can_register_with_valid_email` over `test_create_user`)
- Each test should verify one behavior

## Coverage

- Target minimum 80% code coverage for new code
- Use `/skill:coverage-checker` to validate thresholds
- Coverage gaps in critical paths (auth, payments, domain logic) are not acceptable

## Prohibitions

- Do not commit code without corresponding tests
- Do not push with failing tests
- Do not mock domain entities — test them directly
- Do not write tests that depend on execution order

## Bounded Correction Protocol

The correction protocol detects and addresses three types of deviations, not just test failures.

### Deviation Types

1. **TYPE 1 — Test Failure**: Tests fail with errors. Fix the implementation (NEVER the test, unless the test itself is wrong).
2. **TYPE 2 — Missing Functionality**: Tests pass but acceptance criteria from `tasks.md` are not met. Add the missing implementation.
3. **TYPE 3 — Incomplete Pattern**: Implementation doesn't match the reference file pattern. Compare against the reference and complete the pattern.

### Correction Loop

```
iteration = 0
while (tests_failing OR deviation_detected) and iteration < max_iterations:
    CLASSIFY the deviation:

    IF TYPE 1 (test failure):
        analyze test error output
        fix implementation code (NOT the test)
    ELSE IF TYPE 2 (missing functionality):
        compare implementation vs acceptance criteria in tasks.md
        identify gap → add missing implementation
    ELSE IF TYPE 3 (incomplete pattern):
        compare vs reference file from task definition
        identify missing pieces → complete the pattern

    run verification (tests + acceptance criteria check)
    iteration++

if all_verified:
    proceed to checkpoint
else:
    document_blocker(deviation_type, attempts_per_type)
    mark task as BLOCKED
```

### Escape Hatch

If max iterations reached, document with deviation classification:

```markdown
## Blocker: [Task Name]
**Deviation type**: TYPE 1 (test failure) | TYPE 2 (missing functionality) | TYPE 3 (incomplete pattern)
**Iterations attempted**: [N] (Type 1: X, Type 2: Y, Type 3: Z)
**Last error/gap**: [exact error or missing criterion]
**What was tried**: [approaches per deviation type]
**Status**: BLOCKED - Needs Planner decision
```

### Iteration Limits by Complexity

The `max_iterations` value is resolved from `providers.yaml` → `thresholds.correction_limits`:

| Complexity | max_iterations | When to use |
|------------|---------------|-------------|
| **simple** | 5 | Fix, patch, refactor, single-file changes |
| **moderate** | 10 | Standard feature, multi-file implementation |
| **complex** | 15 | New architecture, multi-layer integration, novel patterns |

**Resolution order**: Task definition in `tasks.md` → inferred from scope → `correction_limits.default` (10).

This bounded protocol prevents infinite loops while detecting problems beyond simple test failures.
