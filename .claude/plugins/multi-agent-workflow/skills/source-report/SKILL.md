---
name: source-report
description: "Generate a structured report of external sources, research links, and references used in workflow improvement plans. Use when you need to audit, analyze, or improve the quality of research behind decisions."
model: sonnet
context: fork
hooks:
  Stop:
    - command: "echo '[source-report] Report generated.'"
---

# Source Report Skill

Generate structured reports of external sources and references used in research, plans, and decisions.

## What This Skill Does

- Collects all external sources referenced in improvement plans and decision documents
- Classifies sources by type (official documentation, blog post, academic, industry report)
- Evaluates source credibility and recency
- Identifies gaps in research coverage
- Generates a structured, linkable report for audit and review
- Suggests additional sources for areas with weak coverage

## When to Use

- **After creating improvement plans**: Audit the research quality behind recommendations
- **Before implementing changes**: Verify that recommendations are backed by credible, recent sources
- **During review**: Cross-reference decisions with their supporting evidence
- **Knowledge consolidation**: Build a reference library for the project

## Invocation

```
/multi-agent-workflow:source-report
/multi-agent-workflow:source-report --plan <plan-file>
/multi-agent-workflow:source-report --topic <topic>
```

## Report Generation Process

### Step 1: Collect Sources

Scan the target plan file(s) in `plans/` for all external references (URLs, citations, named sources).

If no plan file is specified, scan all files in `plans/` directory, **excluding `plans/source-reports/`** to avoid re-ingesting previously generated reports (which would duplicate citations and distort source counts).

### Step 2: Classify Sources

For each source, determine:

| Field | Description |
|-------|-------------|
| **URL** | Full link to the source |
| **Title** | Article/page title |
| **Author/Org** | Who published it |
| **Type** | `official-docs` / `blog` / `industry-report` / `academic` / `tool-docs` / `community` |
| **Date** | Publication date (or "unknown") |
| **Topic** | Primary topic covered |
| **Credibility** | `HIGH` (official docs, established orgs) / `MEDIUM` (known blogs, tech companies) / `LOW` (unknown, outdated) |
| **Used In** | Which plan/gap/recommendation references this source |

### Step 3: Coverage Analysis

Identify which areas of the plan have:
- **Strong coverage** (3+ credible sources)
- **Adequate coverage** (1-2 credible sources)
- **Weak coverage** (0 sources or only low-credibility sources)

### Step 4: Gap Detection

For each recommendation in the plan, verify:
- Is there at least 1 official documentation source?
- Is there at least 1 source from 2025 or later?
- Are there contradicting sources that should be noted?

### Step 5: Generate Report

Output the report in the following format:

```markdown
# Source Report: [Plan Name]

**Generated**: [date]
**Plan analyzed**: [file path]
**Total sources**: [count]
**Coverage score**: [strong/adequate/weak]

## Sources by Topic

### [Topic 1]
| # | Source | Type | Credibility | Date | Used In |
|---|--------|------|-------------|------|---------|
| 1 | [Title](url) | official-docs | HIGH | 2025-06 | Gap 1.1 |

### [Topic 2]
...

## Coverage Analysis

| Area | Sources | Credibility Mix | Verdict |
|------|---------|-----------------|---------|
| SDD | 5 | 3 HIGH, 2 MEDIUM | Strong |
| Context Engineering | 5 | 2 HIGH, 3 MEDIUM | Strong |

## Gaps & Recommendations

- [ ] Area X has no official documentation sources — consider adding [suggestion]
- [ ] Source Y is from 2023 — look for a more recent equivalent

## Contradictions Found

| Topic | Source A says | Source B says | Implication |
|-------|-------------|---------------|-------------|
```

### Step 6: Save Report

Save the generated report to `plans/source-reports/[plan-name]-sources.md`.

## Integration with Workflow

This skill supports the **Compound Engineering** philosophy — each analysis makes subsequent analyses better. Source reports feed into:

- `/workflows:plan` — Verify research quality before design decisions
- `/workflows:review` — Check that architectural decisions have evidence
- `/workflows:compound` — Consolidate validated sources into project knowledge base

## Quality Criteria

A good source report should:
- Cover every recommendation with at least 1 credible source
- Flag any source older than 18 months as potentially outdated
- Identify the strongest single source for each topic
- Note any contradictions between sources
- Suggest specific searches for areas with weak coverage
