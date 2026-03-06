SHELL := /bin/bash

.PHONY: help lint test test-unit test-functional phpstan infection qa

help:
	@echo "make lint           - Sintaxis PHP"
	@echo "make test           - Todos los tests (unit + functional)"
	@echo "make test-unit      - Solo tests unitarios"
	@echo "make test-functional- Solo tests funcionales"
	@echo "make phpstan        - Análisis estático PHPStan"
	@echo "make infection      - Mutation testing con Infection"
	@echo "make qa             - Todo: lint + test-unit + phpstan + infection"

lint:
	@find backend/src -name '*.php' -print0 | xargs -0 -n1 php -l

test:
	cd backend && ./vendor/bin/phpunit

test-unit:
	cd backend && ./vendor/bin/phpunit --testsuite Unit

test-functional:
	cd backend && ./vendor/bin/phpunit --testsuite Functional

phpstan:
	cd backend && vendor/bin/phpstan analyse

infection:
	cd backend && XDEBUG_MODE=coverage vendor/bin/infection --threads=4

qa: lint test-unit phpstan infection
