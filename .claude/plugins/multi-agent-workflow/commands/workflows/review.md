---
name: workflows:review
description: "Multi-agent review before merge. Validates implementation against specs, runs tests, and approves or rejects."
argument_hint: <feature-name> [--agent=<security|performance|architecture|code>]
---

# Multi-Agent Workflow: Review

Comprehensive review phase using specialized review agents.

## Flow Guard (prerequisite check)

Before executing, verify the flow has been followed:

```
PREREQUISITE CHECK:
  1. Does tasks.md exist for this feature?
     - NO: STOP. This feature has not been planned or worked on.

  2. Is the implementation work COMPLETED?
     - Check backend status = COMPLETED (if applicable)
     - Check frontend status = COMPLETED or WAITING_API (if applicable)
     - If neither is COMPLETED: STOP. Run /workflows:work first.

  3. Is QA status already APPROVED?
     - YES: Review already passed. Confirm re-review with user.
     - NO: Proceed with review.

  If checks 1 or 2 fail, do NOT proceed.
```

## Automatic Operations (built into this command)

The following are executed automatically as part of `/workflows:review`:
- **Solution validation check** -- validates approach before implementation
- **Comprehension check** -- verifies understanding of generated code
- **SOLID compliance verification** -- must be COMPLIANT per solid-analyzer
- **Spec validation** -- validates implementation against specs
- **Trust-level evaluation** -- applies appropriate scrutiny per file
- **Validation learning integration** -- reads `validation-learning-log.md` for relevant preferences and patterns

You do NOT need to invoke these separately.

## Usage

```bash
# Full review (all agents)
/workflows:review user-authentication

# Specific review agent
/workflows:review user-authentication --agent=security
/workflows:review user-authentication --agent=performance
/workflows:review user-authentication --agent=architecture
/workflows:review user-authentication --agent=code
```

## Review Agents

| Agent | Focus | When to Use |
|-------|-------|-------------|
| **qa** (default) | Full review (includes SOLID) | Always |
| **solid** | SOLID compliance, patterns | All features (mandatory) |
| **security** | OWASP, vulnerabilities | Auth, payments, sensitive data |
| **performance** | Speed, optimization | High-traffic features |
| **architecture** | DDD + SOLID architecture | Backend with business logic |
| **code** | Code quality, patterns | All features |

**Note**: SOLID review is ALWAYS included in the default `qa` review. Use `--agent=solid` for SOLID-only review.

## Philosophy

> "Trust = Passing Test Suite + Evidence"

Never say "it works" without:
- Test results with commands
- Screenshots/logs as evidence
- Explicit acceptance criteria verification

## Review Process

### Phase 1: API Testing (Backend Verification)

```bash
# Happy path
curl -X POST localhost:8000/api/users -d '{"email":"test@example.com",...}'
# Expected: 201 Created
# Actual: [document response]

# Validation errors
curl -X POST localhost:8000/api/users -d '{"email":"invalid"}'
# Expected: 400 Bad Request
# Actual: [document response]

# Edge cases
curl -X POST localhost:8000/api/users -d '{"email":"existing@email.com"}'
# Expected: 409 Conflict
# Actual: [document response]
```

### Phase 2: UI Testing (Frontend Verification)

```markdown
Test 1: Happy Path
- Steps: Open /register → Fill valid data → Submit
- Expected: Success toast, redirect to /dashboard
- Actual: [document result]
- Evidence: [screenshot description]

Test 2: Validation
- Steps: Enter invalid email → Submit
- Expected: Error message under field
- Actual: [document result]

Test 3: Responsive
- Mobile (375px): [document result]
- Tablet (768px): [document result]
- Desktop (1024px): [document result]
```

### Phase 3: Automated Test Execution

```bash
# Backend
php bin/phpunit tests/
# Result: X/Y tests passing
# Coverage: Z%

# Frontend
npm test
# Result: X/Y tests passing
# Coverage: Z%

# E2E
npm run test:e2e -- feature-name
# Result: X/Y scenarios passing
```

### Phase 4: Code Quality Review

```markdown
1. TDD Compliance (CRITICAL):
   - [ ] Git history shows tests before implementation
   - [ ] Red-Green-Refactor cycle followed

   Verification:
   git log --oneline | head -20
   # Expected: test commits before implementation commits

2. **SOLID Design Implementation (CRITICAL)**:

   > QA verifies that the SOLID design from design.md was IMPLEMENTED,
   > NOT whether the design itself is correct (that's Phase 3's responsibility).

   - [ ] Patterns from design.md were correctly implemented
   - [ ] Class structure matches the design document
   - [ ] SOLID compliance verified per solid-analyzer

   Verification:
   # 1. Read expected design
   Read: openspec/changes/${FEATURE_ID}/design.md

   # 2. Verify implementation matches design
   /workflow-skill:solid-analyzer --mode=verify --path=src --design=design.md --scope=full

   # 3. Evaluate compliance verdict
   # COMPLIANT → approve
   # NEEDS_WORK on relevance=medium → approve with notes
   # NON_COMPLIANT on relevance≥high → REJECT

   SOLID Checklist (verify design was followed, per-principle verdict):
   - [ ] **S** - SRP: COMPLIANT / NEEDS_WORK / NON_COMPLIANT — Classes structured as designed in design.md
   - [ ] **O** - OCP: COMPLIANT / NEEDS_WORK / NON_COMPLIANT — Patterns (Strategy, Decorator) implemented as specified
   - [ ] **L** - LSP: COMPLIANT / NEEDS_WORK / N/A — Inheritance used as designed
   - [ ] **I** - ISP: COMPLIANT / NEEDS_WORK / NON_COMPLIANT — Interfaces match design (size, roles)
   - [ ] **D** - DIP: COMPLIANT / NEEDS_WORK / NON_COMPLIANT — Dependencies injected as specified in design

3. DDD Compliance (Backend):
   - [ ] Domain layer has no infrastructure dependencies
   - [ ] Entities have behavior, not just getters
   - [ ] Value objects are immutable

3b. **Dimensional Constraint Compliance** (if design.md contains "## API Architecture Constraints Addressed"):

   > QA verifies that dimensional constraints from design.md were IMPLEMENTED,
   > NOT whether the constraints were correct (that's the planner's responsibility).

   - [ ] Each "must" constraint has corresponding code (with file path evidence)
   - [ ] "should" constraints either implemented or deferred with documented rationale
   - [ ] Patterns referenced (AC-01/02/03/04) are present in code
   - [ ] No new vendor SDK imports leaked into Domain/Application layers

   Verification:
   # 1. Read constraint table from design.md
   Read: openspec/changes/${FEATURE_ID}/design.md → "API Architecture Constraints Addressed"

   # 2. For each "must" constraint, verify in code
   # COMPLIANT → constraint satisfied with evidence
   # NEEDS_WORK → partially satisfied
   # NON_COMPLIANT on "must" → REJECT

4. Test Coverage:
   - Backend: >80% required, actual: [X]%
   - Frontend: >70% required, actual: [X]%

5. Code Style:
   - Backend: ./vendor/bin/php-cs-fixer fix --dry-run
   - Frontend: npm run lint

6. Contradiction Check (CDP):
   - [ ] Implementation is consistent with specs.md (no WHAT/HOW mismatch)
   - [ ] Implementation is consistent with constitution.md (if exists)
   - [ ] No Decision Log entries contradict what was actually implemented
   - If contradiction found → apply CDP (framework_rules.md §12):
     classify as DESIGN_FEEDBACK or SPEC_FEEDBACK per Feedback Loop
```

### Phase 5: Acceptance Criteria Validation

For each criterion in proposal.md:

```markdown
Criterion 1: "User can register with email/name/password"
- [✓] PASS
- Evidence: curl test returned 201, user in database
- Screenshot: [description]

Criterion 2: "System validates email format"
- [✓] PASS
- Evidence: Invalid email → 400 with error details

Criterion 3: "User redirected after registration"
- [✓] PASS
- Evidence: Browser redirected to /dashboard
```

## Issue Reporting Template

```markdown
## Issue #1: [Title]

**Severity**: CRITICAL | MAJOR | MINOR
**Type**: Backend | Frontend | Integration
**Status**: OPEN

**Steps to Reproduce**:
1. [Step 1]
2. [Step 2]

**Expected**: [What should happen]
**Actual**: [What happened]

**Evidence**:
- Error log: [paste log]
- Screenshot: [description]

**Root Cause** (if known): [hypothesis]
**Suggested Fix**: [recommendation]
**Location**: file.php:45

**Assigned To**: Backend Engineer | Frontend Engineer
**Priority**: P0 | P1 | P2
```

## Decision Criteria

### APPROVED - All must be true:
- ✅ All acceptance criteria met (with evidence)
- ✅ No critical/major bugs (P0/P1)
- ✅ All automated tests passing
- ✅ **SOLID design was implemented** (COMPLIANT per solid-analyzer, matches design.md)
- ✅ Code meets quality standards
- ✅ Documentation complete

### REJECTED - Any of these:
- ❌ Any acceptance criterion fails
- ❌ Critical or major bug found
- ❌ Automated tests failing
- ❌ **Implementation doesn't match SOLID design** (NON_COMPLIANT per solid-analyzer)
- ❌ Security vulnerability present
- ❌ Code quality below standards

> **Note**: QA rejects if implementation doesn't match the SOLID design.
> If the design itself was wrong, that's a Phase 3 issue, not QA's responsibility.

## QA Report Template

```markdown
# QA Report: ${FEATURE_ID}

**Reviewer**: Claude QA
**Date**: 2026-01-16
**Status**: APPROVED | REJECTED

## Summary
[1-2 sentence summary]

## Test Results
- API Tests: X/Y passing
- UI Tests: X/Y passing
- Unit Tests: X/Y passing (Z% coverage)
- E2E Tests: X/Y passing

### SOLID Compliance Verification
- **SRP**: COMPLIANT — [evidence]
- **OCP**: COMPLIANT — [evidence]
- **LSP**: N/A — [reason]
- **ISP**: COMPLIANT — [evidence]
- **DIP**: COMPLIANT — [evidence]
**Global Verdict**: COMPLIANT
**Design Match**: Implementation matches design.md ✓
- **Patterns from Design**:
  - [ ] Strategy for [X] - Implemented: ✅/❌
  - [ ] Repository for [Y] - Implemented: ✅/❌
  - [ ] Value Object for [Z] - Implemented: ✅/❌
- **Design Deviations Found**: [None | List what wasn't implemented as designed]

## Acceptance Criteria
- [✓] Criterion 1 - Evidence: [...]
- [✓] Criterion 2 - Evidence: [...]
- [✗] Criterion 3 - Issue #1

## Issues Found
### Critical (blocks approval)
- Issue #1: [description]
- SOLID Issue: [if any principle NON_COMPLIANT]

### Minor (can fix later)
- Issue #2: [description]

## Decision
**Status**: REJECTED

**Reason**: Issue #1 blocks user registration flow / Implementation doesn't match design

**Must fix before approval**:
1. Fix Issue #1 (Backend)
2. [If design not followed]: Implement patterns as specified in design.md

**Next steps**:
- Backend fixes Issue #1
- Run /workflow-skill:solid-analyzer --mode=verify --path=src --design=design.md --scope=full
- Re-review after fix pushed
```

## State Update

After review, update `tasks.md`:

```markdown
## QA / Reviewer
**Status**: APPROVED | REJECTED
**Review Date**: 2026-01-16
**SOLID Compliance**: COMPLIANT (all principles verified)
**Critical Issues**: 0 | [count]
**Minor Issues**: [count]

### Review Summary
- Acceptance Criteria: 5/5 passed
- Test Coverage: Backend 87%, Frontend 78%
- **SOLID Compliance**: COMPLIANT (all principles verified)
- Patterns Implemented: Strategy ✅, Repository ✅, Value Object ✅
- Issues Found: 0 critical, 2 minor

### Decision
APPROVED - Feature ready for merge (design implemented correctly)
```

## Feedback Loop: REVIEW → PLAN (Architecture Issues)

When review finds issues that are NOT implementation bugs but **design flaws**, route feedback back to planning instead of just rejecting:

```
FEEDBACK CLASSIFICATION:

1. IMPLEMENTATION BUG (code doesn't match design):
   → REJECT → send back to /workflows:work
   → Implementer fixes code to match design.md

2. DESIGN FLAW (design itself is wrong or incomplete):
   → REJECT with DESIGN_FEEDBACK tag
   → Feedback routed to /workflows:plan for design revision
   → Planner updates design.md + Decision Log
   → Then /workflows:work re-implements affected tasks

3. SPEC GAP (requirements were incomplete):
   → REJECT with SPEC_FEEDBACK tag
   → Feedback routed to /workflows:plan Phase 2 for spec revision
   → Planner updates specs.md + design.md
   → Then /workflows:work re-implements affected tasks
```

### Feedback Report Template (for DESIGN_FEEDBACK or SPEC_FEEDBACK)

```markdown
## Review Feedback → Planning

**Type**: DESIGN_FEEDBACK | SPEC_FEEDBACK
**Feature**: ${FEATURE_ID}
**Reviewer**: Claude QA
**Date**: ${DATE}

### Issue
[What the review found that points to a design/spec problem, not an implementation bug]

### Evidence
[Why this is a design issue, not a code issue]

### Recommendation
[What should change in design.md or specs.md]

### Affected Tasks
[Which tasks in tasks.md need re-implementation after design fix]
```

When feedback is generated, append it to `openspec/changes/${FEATURE_ID}/proposal.md` under a `## Review Feedback` section for traceability.

---

## Error Recovery

- **Test suite fails to run**: Verify test infrastructure (dependencies installed, config valid). If environment issue, document and ask user to fix environment before re-review.
- **SOLID analyzer unavailable**: Fall back to manual SOLID checklist verification (Phase 4 Code Quality Review). Document that automated verification was skipped.
- **Implementation doesn't match design.md**: REJECT with specific deviations listed. Reference exact sections of design.md that were not followed.
- **Design itself is flawed**: REJECT with DESIGN_FEEDBACK — route to planner via Feedback Loop (above).
- **Acceptance criteria ambiguous**: If a criterion cannot be objectively verified, flag it in the QA Report as INCONCLUSIVE and ask the user for clarification before final verdict.
- **Review blocked by missing artifacts**: If proposal.md, specs.md, or design.md are missing, STOP review. These are prerequisites — run `/workflows:plan` to generate them.

---

## Compound Effect

Good reviews compound:
- Issues found early save debugging time
- Documented patterns prevent future bugs
- Test evidence becomes regression prevention
- Quality standards improve over time
