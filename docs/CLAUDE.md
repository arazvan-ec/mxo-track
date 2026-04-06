# Documentation — Rules

<!-- GENERIC-START -->
## Philosophy

Documentation describes what IS, not what should be. When the codebase has 
aspirational architecture (planned but not implemented):
- **Current state** is the default voice: "Entities use ORM attributes in `src/Entity/`"
- **Aspirational state** uses markers: "**[PLANNED]** Entities in critical contexts will migrate to POPOs"
- **Partial state** uses: "**[PARTIAL]** Domain events are POPOs, but entities remain with ORM attributes"
<!-- GENERIC-END -->

<!-- PROJECT-SPECIFIC-START -->
## Knowledge Modules

Before working on a subsystem, read the relevant module in `docs/knowledge/`:

| Working on... | Read first |
|--------------|------------|
| Entities, relations, migrations | `domain-model.md` |
| Providers, factories, per-tenant | `provider-framework.md` |
| Controllers, DTOs, APIs | `api-surface.md` |
| Docker, Railway, env vars | `deployment.md` |
| Tests, PHPUnit | `testing.md` |
| Mercure, SSE, JWT | `realtime.md` |
| GPS, Traccar | `gps-tracking.md` |
| SMS, WhatsApp, push | `notifications.md` |
| AI, embeddings, ML | `ai-ml.md` |
| VROOM, OSRM, routes | `route-optimization.md` |
| DDD, SOLID, architecture | `architecture-ddd.md` |
| Design patterns | `design-patterns.md` |
| Roles, security, CSRF | `security.md` |
| Skills, superpowers | `superpowers-skills.md` |
| Feedback, logs, learning | `feedback-learning.md` |
| Twig, Alpine.js, Tailwind | `ui-frontend.md` |
| Full index | `index.md` |

### Freshness Protocol
- Verified (< 14 days): use directly
- Unverified or > 14 days: spot-check 2-3 claims before trusting
- After any task: update the relevant module if something changed
- Never leave a stale module if you discovered the discrepancy
<!-- PROJECT-SPECIFIC-END -->

<!-- PROJECT-SPECIFIC-START -->
## Features Document

`docs/FEATURES.md` — complete feature description. Keep updated with every PR 
that adds, modifies, or removes functionality.
<!-- PROJECT-SPECIFIC-END -->

<!-- GENERIC-START -->
## Learning Review (Skill 15)

**When:** Monthly, or when 5+ execution logs accumulated without analysis.

**Process:**
1. Read all execution logs from the period
2. Read recent decision log entries
3. Analyze: estimation accuracy, blocker frequency, decision outcomes
4. Write review in `docs/superpowers/retrospectives/YYYY-MM-review.md`
5. Act: update knowledge modules, propose CLAUDE.md changes, adjust calibration
<!-- GENERIC-END -->

<!-- GENERIC-START -->
## Execution Logs and Retrospectives

After EVERY code change or bug fix, create a **separate** execution log per feature/interaction:
`docs/superpowers/execution-logs/YYYY-MM-DD-<feature-name>.md`

**One log per feature, not per session.** If a session implements 3 different features,
create 3 separate files. This makes each log independently consultable in future
brainstorming sessions. Never append unrelated features to an existing log.

| Phase | Data to capture |
|-------|----------------|
| Brainstorming | Alternatives, chosen approach, complexity estimate |
| Planning | Task count, affected files, time estimate |
| Implementation | Actual time, blockers, deviations |
| Verification | Test results, lint, coverage delta |
| Retrospective | Estimate accuracy, lessons learned |
<!-- GENERIC-END -->
