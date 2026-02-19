---
name: validation-learning-log
description: "Manages the persistent validation learning log. Records questions, answers, and patterns from solution validations to prevent repeated questions and enable dynamic workflow improvement."
model: inherit
context: fork
hooks:
  Stop:
    - command: "echo '[validation-learning-log] Log updated successfully'"
      description: "Confirm log update"
---

# Validation Learning Log Skill

Manages the persistent learning log that captures all validation interactions (questions asked, user answers, validated assumptions, and discovered patterns). This log is the foundation of the dynamic improvement system — it ensures the workflow learns from every interaction.

## Philosophy

> "Preguntar dos veces lo mismo es desperdiciar la confianza del usuario."
> — Validation Learning Principle

> "Each answer from the user is a permanent learning, not a one-time input."

## Log Location

```
.ai/project/validation-learning-log.md
```

## Log Structure

```markdown
# Validation Learning Log

> Auto-managed by the validation-learning-log skill.
> Read by: planner, review agents.
> Written by: /workflows:compound, /workflows:review.
> Last updated: ${TIMESTAMP}

## Metadata

- **Total entries**: ${COUNT}
- **Questions asked**: ${Q_COUNT}
- **Questions avoided (learned)**: ${AVOIDED_COUNT}
- **Learning effectiveness**: ${AVOIDED / (AVOIDED + Q_COUNT) * 100}%

## Active Learnings

### Patterns (recurring answers that became rules)

| ID | Pattern | Confidence | Times Confirmed | Source Entries | Status |
|----|---------|-----------|-----------------|---------------|--------|
| PAT-001 | Team prefers Repository pattern | 95% | 4/4 features | LOG-001, LOG-005, LOG-008, LOG-012 | ACTIVE |
| PAT-002 | Always use UUID for entity IDs | 90% | 3/3 features | LOG-003, LOG-007, LOG-011 | ACTIVE |
| PAT-003 | Redis preferred over Memcached | 80% | 2/3 features | LOG-002, LOG-009 | ACTIVE |

### Preferences (user choices that inform defaults)

| ID | Preference | Value | Last Confirmed | Entry |
|----|-----------|-------|---------------|-------|
| PREF-001 | Error response format | RFC 7807 Problem Details | 2026-01-20 | LOG-004 |
| PREF-002 | Naming convention for events | PastTense (UserCreated) | 2026-01-18 | LOG-006 |
| PREF-003 | Test naming style | should_action_when_condition | 2026-01-22 | LOG-010 |

## Entry Log (chronological)

### LOG-XXX: ${FEATURE_NAME} — ${DATE}

**Context**: ${brief description of what was being validated}
**Phase**: ${plan | work | review}

#### Questions Asked

| # | Question | User Answer | Confidence Before | Confidence After |
|---|---------|-------------|-------------------|-----------------|
| 1 | ${question} | ${answer} | ${before}% | ${after}% |

#### Assumptions Validated

| # | Assumption | Result | Notes |
|---|-----------|--------|-------|
| 1 | ${assumption} | ✅ Correct / ❌ Wrong / ⚠️ Partially | ${notes} |

#### Blind Spots Found

| # | Blind Spot | Severity | Resolution |
|---|-----------|----------|-----------|
| 1 | ${blind spot} | ${severity} | ${how it was resolved} |

#### Learnings Extracted

| # | Learning | Type | Promoted To |
|---|---------|------|-------------|
| 1 | ${learning} | pattern / preference / rule | ${PAT-XXX or PREF-XXX or "not yet"} |
```

## Operations

### 1. Record Entry (`--record`)

Record a new validation interaction.

**Input**: Validation report from validation workflow
**Process**:

```
1. Parse validation report for:
   - Questions asked + user answers
   - Assumptions checked + results
   - Blind spots found
   - Context (feature name, phase, date)

2. Generate entry ID: LOG-${sequential_number}

3. For each user answer:
   a. Search existing Patterns and Preferences for similar questions
   b. If MATCH found:
      - Same answer → Increment confirmation count, update confidence
      - Different answer → Flag as CONFLICT, keep both, mark for review
   c. If NO match:
      - If answer reveals a pattern → Create new PAT-XXX entry
      - If answer reveals a preference → Create new PREF-XXX entry
      - Otherwise → Just log the entry

4. Update metadata counters

5. APPEND entry to "Entry Log" section
```

### 2. Query Learnings (`--query`)

Find relevant learnings for a given context.

**Input**: Feature description, solution type, technology involved
**Process**:

```
1. Parse input for keywords and context signals
2. Search Active Learnings:
   a. Patterns: Match by technology, pattern type, architecture layer
   b. Preferences: Match by category (naming, format, tooling)
3. Search Entry Log:
   a. grep for similar feature names
   b. grep for similar technologies
   c. grep for similar assumption types
4. Return ranked results:
   - Confidence ≥ 80% → "Apply directly"
   - Confidence 50-79% → "Suggest as default, but verify"
   - Confidence < 50% → "Mention but don't assume"
```

### 3. Promote Learning (`--promote`)

Promote a recurring pattern to project rules when threshold is met.

**Promotion Criteria:**
- Pattern confirmed in ≥ 5 features
- Confidence ≥ 90%
- No conflicts in last 3 features

**Process**:

```
1. Check promotion criteria
2. If MET:
   a. Add rule to .ai/extensions/rules/project_rules.md
   b. Mark pattern as [PROMOTED to project_rules.md on ${DATE}]
   c. Keep in log for audit trail
   d. Update compound-memory.md if relevant
3. If NOT MET:
   a. Return: "Not ready for promotion. Need ${remaining} more confirmations."
```

### 4. Analyze Effectiveness (`--analyze`)

Generate a report on validation learning effectiveness.

**Output:**

```markdown
## Validation Learning Analysis

### Efficiency Metrics
- **Total questions ever asked**: ${TOTAL}
- **Questions avoided via learnings**: ${AVOIDED}
- **Learning effectiveness rate**: ${RATE}%
- **Average questions per feature** (last 5): ${AVG}
- **Trend**: ${IMPROVING | STABLE | DECLINING}

### Pattern Distribution
| Category | Active | Promoted | Conflicting |
|----------|--------|----------|-------------|
| Architecture | ${N} | ${N} | ${N} |
| Testing | ${N} | ${N} | ${N} |
| Naming/Style | ${N} | ${N} | ${N} |
| Business Rules | ${N} | ${N} | ${N} |
| Infrastructure | ${N} | ${N} | ${N} |

### Most Valuable Learnings (by times applied)
1. ${PAT-XXX}: Applied ${N} times, saved ~${N} questions
2. ${PAT-YYY}: Applied ${N} times, saved ~${N} questions

### Stale Learnings (not referenced in last 5 features)
| ID | Learning | Last Referenced | Action |
|----|---------|----------------|--------|
| ${ID} | ${learning} | ${date} | Review for removal |

### Conflict Log
| ID | Pattern | Conflicting Answers | Resolution |
|----|---------|---------------------|-----------|
| ${ID} | ${pattern} | ${answer_a} vs ${answer_b} | ${resolved | pending} |
```

## Integration Points

### With validation workflow
- **Before validation**: Workflow queries log (`--query`) for applicable learnings
- **After validation**: Workflow records entry (`--record`) with results
- **Loop**: Each validation makes the next one smarter

### With /workflows:discover --setup
- **On install**: Creates empty `validation-learning-log.md` with structure
- **Seed data**: Can import learnings from other projects (`--import`)

### With /workflows:compound
- **After compound**: Cross-reference compound learnings with validation log
- **Promotion**: Check if any patterns are ready for promotion

### With /workflows:plan
- **During planning**: Planner reads active learnings to inform architecture decisions
- **Risk assessment**: Learnings inform which areas need extra validation

### With /workflows:review
- **Before review**: Review agents read preferences to align with team standards
- **After review**: Any new insights from review are recorded

## Modes

```bash
# Record a new validation entry
/workflow-skill:validation-learning-log --record --feature=user-auth --source=validation-report.md

# Query learnings for a context
/workflow-skill:validation-learning-log --query --context="REST API design, Symfony, authentication"

# Check if any patterns are ready for promotion
/workflow-skill:validation-learning-log --promote --check

# Analyze learning effectiveness
/workflow-skill:validation-learning-log --analyze

# Import learnings from another project
/workflow-skill:validation-learning-log --import --source=/path/to/other-project/validation-learning-log.md
```

## Constraints

1. **Log must stay manageable** — max 500 lines. Archive old entries when exceeded.
2. **Patterns max 30** — promote or remove stale patterns regularly.
3. **No sensitive data** — never log passwords, tokens, or PII in answers.
4. **Versioned in git** — the log is committed so the team benefits.
5. **No hallucinated learnings** — only record actual user interactions, never infer.
