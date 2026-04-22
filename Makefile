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
	@echo "make test-new-failures - Run hook/script test suites, flag regressions vs docs/knowledge/test-suite-health.md"

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

test-new-failures:  ## Run test-*.sh suites, compare failures vs docs/knowledge/test-suite-health.md baseline
	@set +e; \
	health_doc="docs/knowledge/test-suite-health.md"; \
	if [ ! -f "$$health_doc" ]; then \
		echo "ERROR: $$health_doc not found" >&2; exit 2; \
	fi; \
	regressions=0; \
	suites=0; \
	printf "%-40s %-10s %-10s %s\n" "SUITE" "EXPECTED" "ACTUAL" "STATUS"; \
	printf "%-40s %-10s %-10s %s\n" "----------------------------------------" "----------" "----------" "------"; \
	for suite in .claude/hooks/test-*.sh scripts/test-*.sh; do \
		[ -f "$$suite" ] || continue; \
		suites=$$((suites + 1)); \
		name=$$(basename "$$suite"); \
		baseline=$$(awk -v s="$$name" '/^## machine-readable/{found=1; next} found && $$0 ~ "^"s":" {print; exit}' "$$health_doc"); \
		expected_fails=""; \
		expected_total=""; \
		if [ -n "$$baseline" ]; then \
			rhs=$$(echo "$$baseline" | sed -E "s/^[^:]+:[[:space:]]*//"); \
			if [ "$$rhs" = "unknown" ]; then \
				expected_fails="unknown"; \
			else \
				expected_fails=$$(echo "$$rhs" | cut -d/ -f1); \
				expected_total=$$(echo "$$rhs" | cut -d/ -f2); \
			fi; \
		fi; \
		output=$$(bash "$$suite" 2>&1); \
		summary=$$(echo "$$output" | grep -E "Results:[[:space:]]*[0-9]+[[:space:]]*(run[[:space:]]*·[[:space:]]*[0-9]+[[:space:]]*)?passed.*[0-9]+[[:space:]]*failed" | tail -1); \
		actual_passed=""; \
		actual_failed=""; \
		if [ -n "$$summary" ]; then \
			actual_passed=$$(echo "$$summary" | grep -oE "[0-9]+[[:space:]]*passed" | head -1 | grep -oE "[0-9]+"); \
			actual_failed=$$(echo "$$summary" | grep -oE "[0-9]+[[:space:]]*failed" | head -1 | grep -oE "[0-9]+"); \
		else \
			pf_pass=$$(echo "$$output" | grep -E "^PASS:[[:space:]]*[0-9]+$$" | tail -1 | grep -oE "[0-9]+"); \
			pf_fail=$$(echo "$$output" | grep -E "^FAIL:[[:space:]]*[0-9]+$$" | tail -1 | grep -oE "[0-9]+"); \
			if [ -n "$$pf_pass" ] && [ -n "$$pf_fail" ]; then \
				actual_passed="$$pf_pass"; \
				actual_failed="$$pf_fail"; \
			fi; \
		fi; \
		if [ -z "$$actual_failed" ]; then \
			actual_str="no-summary"; \
			if [ "$$expected_fails" = "unknown" ]; then \
				status="OK (baseline=unknown, suite still has no Results line)"; \
			elif [ -z "$$expected_fails" ]; then \
				status="UNKNOWN (not in baseline, no Results line)"; \
			else \
				status="REGRESSION (expected $$expected_fails/$$expected_total fails, suite produced no Results line)"; \
				regressions=$$((regressions + 1)); \
			fi; \
		else \
			actual_total=$$((actual_passed + actual_failed)); \
			actual_str="$$actual_failed/$$actual_total"; \
			if [ "$$expected_fails" = "unknown" ]; then \
				if [ "$$actual_failed" -gt 0 ]; then \
					status="REGRESSION (baseline=unknown, now $$actual_failed fails — update baseline)"; \
					regressions=$$((regressions + 1)); \
				else \
					status="OK (baseline=unknown, 0 fails — consider updating baseline)"; \
				fi; \
			elif [ -z "$$expected_fails" ]; then \
				if [ "$$actual_failed" -gt 0 ]; then \
					status="REGRESSION (not in baseline, $$actual_failed fails)"; \
					regressions=$$((regressions + 1)); \
				else \
					status="OK (not in baseline, 0 fails)"; \
				fi; \
			else \
				if [ "$$actual_failed" -gt "$$expected_fails" ]; then \
					diff=$$((actual_failed - expected_fails)); \
					status="REGRESSION (+$$diff new fails vs baseline $$expected_fails)"; \
					regressions=$$((regressions + 1)); \
				elif [ "$$actual_failed" -lt "$$expected_fails" ]; then \
					status="OK (improved: $$actual_failed < baseline $$expected_fails — update doc)"; \
				else \
					status="OK (matches baseline)"; \
				fi; \
			fi; \
		fi; \
		if [ -z "$$expected_fails" ]; then expected_str="(none)"; \
		elif [ "$$expected_fails" = "unknown" ]; then expected_str="unknown"; \
		else expected_str="$$expected_fails/$$expected_total"; fi; \
		printf "%-40s %-10s %-10s %s\n" "$$name" "$$expected_str" "$$actual_str" "$$status"; \
	done; \
	echo ""; \
	echo "Suites checked: $$suites | New regressions: $$regressions"; \
	if [ "$$regressions" -gt 0 ]; then \
		echo "FAIL: $$regressions suite(s) regressed vs baseline."; \
		exit 1; \
	fi; \
	echo "PASS: no new regressions."; \
	exit 0
