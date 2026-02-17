SHELL := /bin/bash

.PHONY: help lint e2e-symfony
help:
	@echo "make lint         - Sintaxis PHP"
	@echo "make e2e-symfony  - Verificación E2E manual local con Docker"

lint:
	@find backend/src -name '*.php' -print0 | xargs -0 -n1 php -l

e2e-symfony:
	bash scripts/symfony_e2e_boot_check.sh
