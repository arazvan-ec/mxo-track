SHELL := /bin/bash

.PHONY: help lint manifest
help:
	@echo "make lint         - Sintaxis PHP"

lint:
	@find backend/src -name '*.php' -print0 | xargs -0 -n1 php -l

manifest:
	@bash backend/bin/generate-manifest.sh
