SHELL := /bin/bash

.PHONY: help lint test
help:
	@echo "make lint   - Sintaxis PHP"
	@echo "make test   - Ejecutar tests (si vendor disponible)"

lint:
	@find backend/src backend/tests -name '*.php' -print0 | xargs -0 -n1 php -l

test:
	cd backend && php bin/phpunit
