SHELL := /bin/bash

.PHONY: help lint
help:
	@echo "make lint         - Sintaxis PHP"

lint:
	@find backend/src -name '*.php' -print0 | xargs -0 -n1 php -l
