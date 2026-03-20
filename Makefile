SHELL := /bin/bash

.PHONY: help lint manifest preflight
help:
	@echo "make lint         - Sintaxis PHP"
	@echo "make preflight    - Validar proceso antes de push"
	@echo "make manifest     - Regenerar codebase manifest"

lint:
	@find backend/src -name '*.php' -print0 | xargs -0 -n1 php -l

manifest:
	@bash backend/bin/generate-manifest.sh

preflight:
	@bash backend/bin/preflight.sh
