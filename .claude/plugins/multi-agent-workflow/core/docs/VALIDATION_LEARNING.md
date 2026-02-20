# Validation Learning System

> **Purpose**: Dynamic self-improvement system that learns from every AI solution validation.
> **Philosophy**: Every question asked is an investment. Every answer received is a permanent asset.

---

## Overview

The Validation Learning System is a **closed-loop feedback mechanism** that makes the workflow smarter with every feature. It connects three components:

```
┌─────────────────────────────────────────────────────────────┐
│                    VALIDATION LEARNING LOOP                   │
│                                                               │
│   ┌──────────────┐     ┌──────────────┐     ┌──────────────┐│
│   │   Validation  │────▶│  Validation  │────▶│   Learning   ││
│   │   (in review) │     │  Learning    │     │   Loader     ││
│   │               │◀────│  Log (Skill) │◀────│   (Install)  ││
│   └──────────────┘     └──────────────┘     └──────────────┘│
│         │                     │                     │        │
│         ▼                     ▼                     ▼        │
│   Questions AI         Persistent              Pre-loaded    │
│   solutions            Q&A history             learnings     │
│                                                               │
└─────────────────────────────────────────────────────────────┘
```

### The Three Components

| Component | Type | Purpose | File |
|-----------|------|---------|------|
| **Validation (in review)** | Inline step | Questions AI solutions, detects assumptions and blind spots | Integrated within `/workflows:review` |
| **Validation Learning Log** | Skill | Records Q&A interactions, manages patterns and preferences | `skills/validation-learning-log/SKILL.md` |
| **Validation Learning Loader** | Install-time | Pre-loads learnings at project setup for immediate benefit | Integrated in `/workflows:discover --setup` |

---

## How It Works: The Learning Cycle

### Cycle 1: First Feature (Cold Start)

```
Validation step (in review):
  - No learning log exists → Uses defaults
  - Detects 5 assumptions → Asks 4 questions
  - User answers all 4

Learning Log:
  - Creates 4 entries (LOG-001 to LOG-004)
  - Extracts 2 patterns (PAT-001, PAT-002)
  - Extracts 1 preference (PREF-001)

Result: Effectiveness = 0% (all questions were new)
```

### Cycle 2: Second Feature (Warm Start)

```
Validation step (in review):
  - Reads learning log → Finds 2 applicable patterns
  - Detects 5 assumptions → 2 already answered by log
  - Asks 3 new questions
  - User answers all 3

Learning Log:
  - Creates 3 new entries
  - Confirms PAT-001 (now 2/2 features)
  - Adds 1 new pattern (PAT-003)

Result: Effectiveness = 40% (2 of 5 questions avoided)
```

### Cycle 3: Third Feature (Learning)

```
Validation step (in review):
  - Reads learning log → Finds 4 applicable patterns
  - Detects 6 assumptions → 4 already answered
  - Asks 2 new questions
  - User answers 2

Learning Log:
  - Creates 2 new entries
  - Confirms PAT-001 (3/3 → confidence 90%)
  - Confirms PAT-002 (2/3)
  - No new patterns

Result: Effectiveness = 67% (4 of 6 questions avoided)
```

### Cycle N: Mature Workflow (Hot)

```
Validation step (in review):
  - Reads learning log → 15 active patterns, 8 preferences
  - Detects 5 assumptions → All answered by log
  - Asks 0 questions
  - Uses --skip-questions safely

Learning Log:
  - Confirms existing patterns
  - PAT-001 promoted to project_rules.md (≥5 confirmations)

Result: Effectiveness = 100% (all questions learned)
```

---

## Integration with Existing Workflow

### Where Validation Learning Plugs In

```
/workflows:discover --setup
  └── Creates validation-learning-log.md (empty)
  └── Optionally imports learnings from shared template

/workflows:route
  └── No direct integration (routing is pre-solution)

/workflows:plan
  └── Validation reviews architecture decisions
  └── Learning log provides pattern defaults
  └── Planner reads preferences for design choices

/workflows:work
  └── On completion: Reminds to validate solution
  └── Can validate mid-work for complex features

/workflows:review ◄── PRIMARY ENTRY POINT (validation is integrated)
  └── Runs validation step inline
  └── Asks user questions
  └── Updates learning log
  └── Produces validation report (18_validation_report.md)
  └── Adjusts review focus based on validation caveats

/workflows:compound
  └── Cross-references validation learnings with compound captures
  └── Promotes mature patterns to project rules
  └── Updates compound-memory.md with validation data
```

### The Feature Directory (with validation)

```
openspec/changes/${FEATURE_ID}/
├── 10_requirements.md          # From /workflows:route
├── specs.md                 # From /workflows:plan (Phase 2)
├── 12_architecture_criteria.md # From /workflows:plan (Phase 3)
├── design.md             # From /workflows:plan (Phase 3)
├── 18_validation_report.md     # ◄── From validation step within /workflows:review
├── 20_implementation.md        # From /workflows:work
├── 30_qa_report.md             # From /workflows:review
├── 40_compound_entry.md        # From /workflows:compound
└── tasks.md                 # Shared state
```

---

## The Learning Log File

### Location

```
.ai/project/validation-learning-log.md
```

### Why a Single File?

1. **Grep-friendly** — agents can search it efficiently
2. **Git-trackable** — team sees learning evolution in diffs
3. **Size-bounded** — max 500 lines with archival policy
4. **Human-readable** — team can review and override learnings

### Sections

| Section | Purpose | Written By | Read By |
|---------|---------|-----------|---------|
| Metadata | Effectiveness metrics | Skill (auto) | Analyze command |
| Active Learnings: Patterns | Recurring answers as rules | Skill (auto) | Validator, Planner |
| Active Learnings: Preferences | User choices as defaults | Skill (auto) | Validator, All agents |
| Entry Log | Chronological Q&A history | Skill (auto) | Query command |

---

## Pattern Lifecycle

```
OBSERVATION → PATTERN → CONFIRMED → PROMOTED → RULE

1. OBSERVATION: User answers a question
   → Recorded in Entry Log as LOG-XXX

2. PATTERN: Same answer appears in 2+ features
   → Extracted as PAT-XXX with confidence 70%

3. CONFIRMED: Pattern holds in 3+ features
   → Confidence rises to 85-95%
   → Used as default in future validations

4. PROMOTED: Pattern confirmed in 5+ features with 90%+ confidence
   → Added to project_rules.md
   → Marked as [PROMOTED] in learning log
   → Becomes permanent project knowledge

5. RULE: Now enforced by framework
   → Validator no longer questions this
   → Part of the workflow's DNA
```

### Conflict Resolution

When a user's answer contradicts an existing pattern:

```
IF new answer CONTRADICTS existing pattern:
  1. DO NOT silently update the pattern
  2. Record both answers in Entry Log
  3. Add to Conflict Log with both values
  4. Lower pattern confidence by 20%
  5. On next validation: Present conflict to user
     "Previously you chose X, but last time you chose Y. Which is correct?"
  6. User resolves → Update pattern with resolution
```

---

## Install-Time Learning (The Loader)

### How It Works

During `/workflows:discover --setup`, the system can pre-load learnings:

```
DISCOVER --SETUP STEP:
  1. Create empty validation-learning-log.md
  2. Check for shared learning templates:
     a. .ai/templates/validation-learnings-template.md (project template)
     b. Plugin default patterns (universal learnings)
  3. If template exists:
     a. Import patterns with confidence = 50% (not yet confirmed in this project)
     b. Import preferences with confidence = 40%
     c. Mark all as [IMPORTED - needs confirmation]
  4. On first validation: Imported learnings used as "suggested defaults"
     → User confirms → Confidence increases to 70%
     → User rejects → Pattern marked as [NOT_APPLICABLE] for this project
```

### Universal Learnings (Plugin Defaults)

These patterns are so common they're pre-loaded at 50% confidence:

```markdown
## Universal Patterns (pre-loaded)

| ID | Pattern | Confidence | Note |
|----|---------|-----------|------|
| UNI-001 | Always validate input at API boundary | 50% | Imported |
| UNI-002 | Use DTOs for data transfer between layers | 50% | Imported |
| UNI-003 | Separate read and write models for complex domains | 50% | Imported |
| UNI-004 | Database migrations need rollback strategy | 50% | Imported |
| UNI-005 | Error responses should follow a standard format | 50% | Imported |
```

---

## Metrics & Effectiveness

### Key Metrics

| Metric | Formula | Target |
|--------|---------|--------|
| **Learning Effectiveness** | Questions avoided / Total questions needed | > 60% after 5 features |
| **Pattern Accuracy** | Patterns confirmed / Patterns created | > 80% |
| **Conflict Rate** | Conflicts / Total patterns | < 10% |
| **Time to Promotion** | Features needed to reach 90% confidence | 3-5 features |
| **User Satisfaction** | Questions per feature (decreasing trend) | < 2 after 10 features |

### Compound Effect on Validation

```
Feature 1:  5 questions asked, 0 learned     → 0% effectiveness
Feature 3:  2 questions asked, 3 learned     → 60% effectiveness
Feature 5:  1 question asked,  4 learned     → 80% effectiveness
Feature 10: 0 questions asked, all learned   → 100% effectiveness

Total questions across 10 features: ~15 (vs 50 without learning)
Time saved: ~70% reduction in validation interruptions
```

---

## Constraints

1. **Learning log is project-scoped** — no cross-project contamination
2. **Max 500 lines** — archive old entries to `.ai/logs/validation-archive/`
3. **Max 30 active patterns** — promote or prune regularly
4. **No PII or secrets** — never log sensitive user answers
5. **Human override** — team can edit the log directly
6. **Versioned in git** — changes visible in pull requests
7. **Idempotent recording** — recording the same validation twice doesn't create duplicates

---

## Relationship to Compound Memory

The Validation Learning Log and Compound Memory (`compound-memory.md`) serve complementary purposes:

| Aspect | Compound Memory | Validation Learning Log |
|--------|----------------|------------------------|
| **Focus** | Pain points & patterns from completed features | Questions & answers from solution validations |
| **Written by** | `/workflows:compound` | Validation step within `/workflows:review` |
| **Read by** | Review agents, Planner | Planner, all agents |
| **Updates when** | After feature completion | After every validation |
| **Contains** | Anti-patterns, 70% boundary data | User preferences, confirmed patterns, Q&A history |
| **Promotion target** | `project_rules.md` (when universal) | `project_rules.md` (when ≥5 confirmations) |
| **Size limit** | 200 lines | 500 lines |

**They complement each other:**
- Compound Memory tells agents WHERE problems occur
- Validation Learning Log tells agents WHAT the user prefers as solutions

Together, they enable the workflow to both avoid past mistakes AND apply past preferences.
