SHELL := /bin/bash

.PHONY: help lint lint-shell manifest preflight workflow-status workflow-reset hooks-health
help:
	@echo "make lint            - Sintaxis PHP"
	@echo "make lint-shell      - Shellcheck sobre .claude/hooks/*.sh y scripts/*.sh (severity=warning)"
	@echo "make preflight       - Validar proceso antes de push"
	@echo "make manifest        - Regenerar codebase manifest"
	@echo "make workflow-status - Show current workflow status"
	@echo "make workflow-reset  - Reset workflow state (new session)"
	@echo "make hooks-health    - Check hooks are properly configured"

lint:
	@find backend/src -name '*.php' -print0 | xargs -0 -n1 php -l

lint-shell:
	@command -v shellcheck >/dev/null 2>&1 || { echo "ERROR: shellcheck not installed (apt-get install shellcheck)" >&2; exit 2; }
	@shellcheck -S warning .claude/hooks/*.sh scripts/*.sh

manifest:
	@bash backend/bin/generate-manifest.sh
	@bash scripts/render-vocabulary.sh

preflight:
	@bash backend/bin/preflight.sh

workflow-status:  ## Show current workflow status
	@.claude/hooks/workflow-status.sh 2>/dev/null
	@cat .claude/workflow-status.md 2>/dev/null || echo "No workflow status available"

workflow-reset:  ## Reset workflow state (new session)
	@rm -f .claude/session-state.json
	@.claude/hooks/session-start.sh
	@echo "Workflow state reset."

hooks-health:  ## Check hooks are properly configured
	@echo "Checking hooks..."
	@for f in .claude/hooks/workflow-engine.sh .claude/hooks/post-commit-validator.sh .claude/hooks/post-push-validator.sh .claude/hooks/workflow-status.sh; do \
		if [ -x "$$f" ]; then echo "  ✅ $$f"; else echo "  ❌ $$f (missing or not executable)"; fi; \
	done
	@for f in .claude/hooks/validators/*-validator.sh; do \
		if [ -x "$$f" ]; then echo "  ✅ $$f"; else echo "  ❌ $$f (missing or not executable)"; fi; \
	done
