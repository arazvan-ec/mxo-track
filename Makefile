SHELL := /bin/bash

.PHONY: help lint lint-shell manifest preflight workflow-status workflow-reset hooks-health test-new-failures
help:
	@echo "make lint              - Sintaxis PHP"
	@echo "make lint-shell        - Shellcheck sobre .claude/hooks/*.sh y scripts/*.sh (severity=warning)"
	@echo "make preflight         - Validar proceso antes de push"
	@echo "make manifest          - Regenerar codebase manifest"
	@echo "make workflow-status   - Show current workflow status"
	@echo "make workflow-reset    - Reset workflow state (new session)"
	@echo "make hooks-health      - Check hooks are properly configured"
	@echo "make test-new-failures - Run hook tests, filter known-flaky per docs/knowledge/test-suite-health.md"

lint:
	@find backend/src -name '*.php' -print0 | xargs -0 -n1 php -l

lint-shell:
	@command -v shellcheck >/dev/null 2>&1 || { echo "ERROR: shellcheck not installed (apt-get install shellcheck)" >&2; exit 2; }
	@shellcheck -S warning .claude/hooks/*.sh scripts/*.sh

manifest:
	@bash backend/bin/generate-manifest.sh

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

test-new-failures:  ## Run hook tests, report only failures above known-flaky baseline
	@set -u; \
	HEALTH=docs/knowledge/test-suite-health.md; \
	if [ ! -f "$$HEALTH" ]; then echo "ERROR: $$HEALTH not found"; exit 2; fi; \
	declare -A EXPECTED; \
	in_block=0; \
	while IFS= read -r line; do \
		if [ "$$line" = '```' ] && [ "$$in_block" = 0 ]; then in_block=1; continue; fi; \
		if [ "$$line" = '```' ] && [ "$$in_block" = 1 ]; then break; fi; \
		if [ "$$in_block" = 1 ] && [[ "$$line" =~ ^([a-zA-Z0-9._-]+\.sh):[[:space:]]*([0-9]+)$$ ]]; then \
			EXPECTED[$${BASH_REMATCH[1]}]=$${BASH_REMATCH[2]}; \
		fi; \
	done < <(awk '/^## machine-readable$$/{found=1} found' "$$HEALTH"); \
	OVERALL=0; \
	STATE=.claude/session-state.json; \
	BACKUP=$$(mktemp); \
	cp "$$STATE" "$$BACKUP" 2>/dev/null || true; \
	trap 'cp "$$BACKUP" "$$STATE" 2>/dev/null; rm -f "$$BACKUP"' EXIT; \
	printf "%-35s %-15s %s\n" "SUITE" "FAIL/EXPECTED" "STATUS"; \
	printf "%-35s %-15s %s\n" "-----" "-------------" "------"; \
	for script in .claude/hooks/test-*.sh; do \
		name=$$(basename "$$script"); \
		cp "$$BACKUP" "$$STATE" 2>/dev/null || true; \
		output=$$(timeout 30 bash "$$script" 2>&1 || true); \
		fails=""; \
		fails_line=$$(printf '%s\n' "$$output" | grep -iE 'passed,[[:space:]]*[0-9]+[[:space:]]*failed|Results:[[:space:]]*[0-9]+[[:space:]]*passed,[[:space:]]*[0-9]+[[:space:]]*failed' | tail -1); \
		if [ -n "$$fails_line" ]; then \
			fails=$$(echo "$$fails_line" | grep -oE '[0-9]+[[:space:]]*failed' | head -1 | grep -oE '[0-9]+' | head -1); \
		fi; \
		if [ -z "$$fails" ]; then \
			fail_summary=$$(printf '%s\n' "$$output" | grep -E '^FAIL:[[:space:]]+[0-9]+' | tail -1); \
			if [ -n "$$fail_summary" ]; then \
				fails=$$(echo "$$fail_summary" | grep -oE '[0-9]+' | head -1); \
			fi; \
		fi; \
		if [ -z "$$fails" ]; then \
			fails=$$(printf '%s\n' "$$output" | grep -cE '^[[:space:]]*❌[^✅]'); \
		fi; \
		fails=$${fails:-0}; \
		expected=$${EXPECTED[$$name]:-0}; \
		if [ "$$fails" -le "$$expected" ]; then \
			status="OK"; \
		else \
			status="REGRESSION +$$((fails - expected))"; \
			OVERALL=1; \
		fi; \
		printf "%-35s %-15s %s\n" "$$name" "$$fails/$$expected" "$$status"; \
	done; \
	echo ""; \
	if [ "$$OVERALL" = 0 ]; then \
		echo "✅ No new failures above the known-flaky baseline."; \
	else \
		echo "❌ New failures detected. Investigate the diff or update test-suite-health.md."; \
	fi; \
	exit $$OVERALL
