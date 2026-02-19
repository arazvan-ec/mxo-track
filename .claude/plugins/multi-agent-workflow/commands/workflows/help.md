---
name: workflows:help
description: "Quick help and navigation for the Multi-Agent Workflow plugin. Shows the flow, available commands by tier, and resources."
argument_hint: [topic]
---

# /workflows:help - Quick Help and Navigation

**Version**: 2.1.0
**Category**: Reference

---

## Purpose

Quick reference for the Multi-Agent Workflow plugin. Shows the core flow, command tiers, and resources.

## Invocation

```bash
# General help
/workflows:help

# Help on specific topic
/workflows:help commands
/workflows:help agents
/workflows:help concepts
/workflows:help troubleshooting
```

## Execution Protocol

### Default (No Arguments): Show Quick Reference Card

Display this card:

```markdown
## Multi-Agent Workflow - Quick Reference

### The Flow

  ROUTE --> SHAPE --> PLAN --> WORK --> REVIEW --> COMPOUND
  (entry)  (optional)  (80%)   (15%)     (4%)       (1%)

### Core Commands (use these)

| # | Command             | Purpose                            |
|---|---------------------|------------------------------------|
| 0 | /workflows:route    | Entry point: classify and route    |
| 1 | /workflows:shape    | Pre-planning for complex features  |
| 2 | /workflows:plan     | Architecture-first planning        |
| 3 | /workflows:work     | Execute implementation (TDD)       |
| 4 | /workflows:review   | Multi-agent quality review         |
| 5 | /workflows:compound | Capture learnings                  |

### What Do You Need?

| If you want to...                | Use...                            |
|----------------------------------|-----------------------------------|
| Start something new              | /workflows:route "description"    |
| Check progress                   | /workflows:status feature-name    |
| Analyze a new project            | /workflows:discover               |
| First time setup                 | /workflows:discover --setup       |
| First time with the plugin       | /workflows:help concepts          |

### Resources

| Resource      | Description              |
|---------------|--------------------------|
| QUICKSTART.md | Get started in 5 minutes |
| TUTORIAL.md   | Full step-by-step example|
| GLOSSARY.md   | Term definitions         |

For more: /workflows:help [topic]
Topics: commands, agents, concepts, troubleshooting
```

### Topic: commands

Display the tiered command structure:

```markdown
## All Commands by Tier

### Tier 1: Core Flow (use these in order)

| # | Command              | Purpose                           | Prerequisite          |
|---|----------------------|-----------------------------------|-----------------------|
| 0 | /workflows:route     | Classify and route request        | None (always first)   |
| 1 | /workflows:shape     | Problem/solution separation       | Routed (optional)     |
| 2 | /workflows:plan      | Architecture-first planning       | Routed                |
| 3 | /workflows:work      | Execute with TDD + BCP   | Plan = COMPLETED      |
| 4 | /workflows:review    | Multi-agent quality review        | Work = COMPLETED      |
| 5 | /workflows:compound  | Capture learnings                 | Review = APPROVED     |

### Tier 2: Support (use during the flow when needed)

| Command              | Purpose                              |
|----------------------|--------------------------------------|
| /workflows:status    | View all roles' progress             |
| /workflows:help      | This help                            |
| /workflows:discover  | Auto-analyze project + first-time setup (--setup) |
```

### Topic: agents

```markdown
## Agents

### Role Agents (3) -- invoked by plan, work, review

| Agent       | Function                             |
|-------------|--------------------------------------|
| Planner     | Architecture and specification       |
| Implementer | Code implementation (any stack)      |
| Reviewer    | Testing and validation               |

### Review Agents (4) -- invoked by /workflows:review (context: fork)

| Agent                       | Speciality                              |
|-----------------------------|-----------------------------------------|
| Code Reviewer               | Stack-agnostic code quality             |
| Security Reviewer           | OWASP, vulnerabilities                  |
| Performance Reviewer        | N+1, memory leaks, bundle size          |
| Architecture Reviewer       | DDD + SOLID architecture (context-activated) |

### Research Agents (2) -- invoked by route, plan (context: fork)

| Agent                       | Speciality                  |
|-----------------------------|-----------------------------|
| Codebase Analyzer           | Structure analysis          |
| Learnings Researcher        | Past successful patterns    |

### Workflow Agents (2) -- invoked by work, review

| Agent                       | Speciality                  |
|-----------------------------|-----------------------------|
| Spec Analyzer               | Validate vs specifications  |
| Diagnostic Agent            | Bug reproduction + BCP escalation |

All agents are invoked automatically by the core commands.
You rarely need to invoke them directly.
```

### Topic: concepts

```markdown
## Key Concepts

### The Flow

  ROUTE --> SHAPE --> PLAN --> WORK --> REVIEW --> COMPOUND
  (entry)  (optional)  (80%)   (15%)     (4%)       (1%)

Every request follows this flow. Stages cannot be skipped.

### Core Ideas

| Concept                   | What it means                                  |
|---------------------------|------------------------------------------------|
| Compound Engineering      | Each task makes the next one easier            |
| 80/20 Rule                | 80% planning, 20% execution                   |
| Bounded Correction Protocol | Auto-correct up to 10 times, then BLOCKED      |
| TDD                       | Tests before code (Red-Green-Refactor)         |
| DDD                       | Domain-Driven Design architecture              |
| SOLID Constraint          | Phase 3 solutions must be COMPLIANT per contextual analysis |
| Context Engineering       | Curate what information the model sees         |
| Decision-Challenge Loop   | Challenge assumptions + compare alternatives   |
| context: fork             | Isolated agents returning summaries only       |

### Important Files

| File          | Purpose                        | Location                           |
|---------------|--------------------------------|------------------------------------|
| proposal.md   | Problem + scope + criteria     | openspec/changes/{feature}/        |
| specs.md      | Functional specs + integration | openspec/changes/{feature}/        |
| design.md     | Technical design with SOLID    | openspec/changes/{feature}/        |
| tasks.md      | Tasks + verify + workflow state| openspec/changes/{feature}/        |

### Task States

  PENDING --> IN_PROGRESS --> COMPLETED --> APPROVED
                  |                          |
               BLOCKED                    REJECTED
                  |                          |
             [resolve]              [fix and re-review]

See GLOSSARY.md for full definitions.
```

### Topic: troubleshooting

```markdown
## Common Issues

### "I don't know where to start"
/workflows:route "describe what you need"

### "Context window is getting heavy"
Commit your work, start a new Claude session, and resume from `tasks.md` Workflow State.

### "Something failed and I don't know what"
/workflows:status my-feature
Check tasks.md Workflow State for BLOCKED status with details.

### "Tests won't pass"
The Bounded Correction Protocol auto-corrects up to 10 times.
If still failing: status will be BLOCKED with root cause.
/workflows:status my-feature

### "How do I customize the workflow?"
Project rules: .ai/project/rules/
Plugin agents: plugins/multi-agent-workflow/agents/

Problem not listed? Describe your situation and ask.
```

## Related Commands

- `/workflows:route` - Entry point for all requests
- `/workflows:status` - Check current state
