# Specification Template

This template defines the structure for **functional specifications** (Phase 2).

**SOLID is NOT a spec** - it's a design CONSTRAINT applied in Phase 3 (Solutions).

---

## Template: specs.md (Phase 2 Output)

```markdown
# Functional Specifications: ${FEATURE_ID}

**Created**: ${DATE}
**Status**: DRAFT | VALIDATED | APPROVED

---

## About This Document

This document defines **WHAT** the system must do (functional requirements).
**HOW** to implement (including SOLID compliance) is defined in `design.md`.

---

## Functional Specifications

### SPEC-F01: [Requirement Name]

**Description**: [What the system must do - from user perspective]

**Acceptance Criteria**:
- [ ] [Testable criterion 1]
- [ ] [Testable criterion 2]
- [ ] [Testable criterion 3]

**Verification Method**: [How to verify - test command, manual check, etc.]

**Priority**: Critical | High | Medium | Low

---

### SPEC-F02: [Requirement Name]

**Description**: [What the system must do]

**Acceptance Criteria**:
- [ ] [Testable criterion]

**Verification Method**: [How to verify]

**Priority**: Critical | High | Medium | Low

---

### SPEC-F03: [Requirement Name]

**Description**: [What the system must do]

**Acceptance Criteria**:
- [ ] [Testable criterion]

**Verification Method**: [How to verify]

**Priority**: Critical | High | Medium | Low

---

## Quality Specifications (Optional)

These are non-functional requirements that affect HOW we build.

### SPEC-Q01: Performance (if applicable)

**Description**: Performance requirements

**Acceptance Criteria**:
- [ ] Response time < [X]ms for [operation]
- [ ] Memory usage < [X]MB
- [ ] No N+1 queries

**Verification Method**: Performance tests / profiling

---

### SPEC-Q02: Testability

**Description**: Code must be testable

**Acceptance Criteria**:
- [ ] Unit test coverage ≥80%
- [ ] All public methods have tests

**Verification Method**: `./vendor/bin/phpunit --coverage`

---

## Specification Summary

| ID | Name | Type | Priority | Status |
|----|------|------|----------|--------|
| SPEC-F01 | [Name] | Functional | [Priority] | [ ] |
| SPEC-F02 | [Name] | Functional | [Priority] | [ ] |
| SPEC-F03 | [Name] | Functional | [Priority] | [ ] |
| SPEC-Q01 | Performance | Quality | Medium | [ ] |
| SPEC-Q02 | Testability | Quality | High | [ ] |

---

## What's NOT in This Document

❌ **SOLID compliance** - This is a design CONSTRAINT applied in Phase 3
❌ **Design patterns** - These are selected when designing solutions
❌ **Class structure** - This is part of the technical solution
❌ **Implementation details** - HOW belongs in `design.md`

---

## Approval

- [ ] Product Owner approved functional specs
- [ ] Specs describe WHAT, not HOW
- [ ] All specs are testable
- [ ] Ready for Phase 3 (Solutions)

**Approved By**: _______________
**Date**: _______________
```

---

## Template: design.md (Phase 3 Output)

This is where SOLID becomes mandatory:

```markdown
# Solutions: ${FEATURE_ID}

**Created**: ${DATE}
**Status**: DRAFT | VALIDATED | APPROVED

---

## SOLID Constraint (MANDATORY)

> ⚠️ All solutions MUST comply with SOLID principles.

**SOLID Baseline** (from `/workflow-skill:solid-analyzer --mode=baseline`):
- Current compliance: [per-principle analysis or N/A if greenfield]
- Violations found: [list or none]

**Target**: COMPLIANT (all relevant principles per `architecture-profile.yaml`)

---

## Solution for SPEC-F01: [Requirement Name]

**Approach**: [How to implement]

**SOLID Compliance**:

| Principle | Verdict | Reasoning | Pattern Used |
|-----------|---------|-----------|--------------|
| **S** - SRP | COMPLIANT / NEEDS_WORK / NON_COMPLIANT / N/A | [How this solution respects SRP] | [Pattern or N/A] |
| **O** - OCP | COMPLIANT / NEEDS_WORK / NON_COMPLIANT / N/A | [How this solution respects OCP] | [Pattern or N/A] |
| **L** - LSP | COMPLIANT / NEEDS_WORK / NON_COMPLIANT / N/A | [How this solution respects LSP] | [Pattern or N/A] |
| **I** - ISP | COMPLIANT / NEEDS_WORK / NON_COMPLIANT / N/A | [How this solution respects ISP] | [Pattern or N/A] |
| **D** - DIP | COMPLIANT / NEEDS_WORK / NON_COMPLIANT / N/A | [How this solution respects DIP] | [Pattern or N/A] |

**Files to Create/Modify**:
- `path/to/File1.php` (Principle: SRP reason)
- `path/to/File2.php` (Principle: DIP reason)

**Expected SOLID**: COMPLIANT

---

## Solution for SPEC-F02: [Requirement Name]

**Approach**: [How to implement]

**SOLID Compliance**:

| Principle | Verdict | Reasoning | Pattern Used |
|-----------|---------|-----------|--------------|
| ... | ... | ... | ... |

**Files to Create/Modify**:
- ...

**Expected SOLID**: COMPLIANT

---

## Overall SOLID Compliance

| Solution | Verdict | Notes |
|----------|---------|-------|
| SPEC-F01 | COMPLIANT / NEEDS_WORK / NON_COMPLIANT | [details] |
| SPEC-F02 | COMPLIANT / NEEDS_WORK / NON_COMPLIANT | [details] |
| **Global** | **[COMPLIANT / NEEDS_WORK / NON_COMPLIANT]** | |

**Gate**: COMPLIANT to proceed. NEEDS_WORK requires revision. NON_COMPLIANT blocks.

---

## Patterns Used Summary

| Pattern | Used For | SOLID Addressed |
|---------|----------|-----------------|
| Strategy | [What need] | OCP, SRP |
| Repository | [What need] | DIP, SRP |
| Value Object | [What need] | SRP |
| ... | ... | ... |

---

## Approval

- [ ] All specs have solutions
- [ ] **SOLID compliance documented for each solution**
- [ ] **All relevant SOLID principles verified as COMPLIANT**
- [ ] Patterns justified with "why"
- [ ] Ready for task breakdown

**Approved By**: _______________
**Date**: _______________
```

---

## Summary: Phase 2 vs Phase 3

| Document | Phase | Contains | SOLID? |
|----------|-------|----------|--------|
| `specs.md` | Phase 2 | WHAT (functional requirements) | ❌ No |
| `design.md` | Phase 3 | HOW (technical design) | ✅ **YES** |

---

## Related

- `/workflows:plan` - Uses these templates
- `/workflow-skill:criteria-generator` - Generates specs.md
- `/workflow-skill:solid-analyzer` - Validates SOLID in design.md
- `core/architecture-reference.md` - Principles, patterns, and quality criteria reference
