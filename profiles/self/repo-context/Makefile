# All commands run inside Docker: local PHP is not guaranteed to be 8.5.
COMPOSE := docker compose
RUN     := $(COMPOSE) run --rm --no-deps php

# Duplication threshold (percent). Not sourced by the grid; set at the level of
# the best profile provided by the subject (leodagan: 1.7 %, arthur: 2.4 %).
DUPLICATION_MAX_PCT := 3

.PHONY: build test lint dup demo fmt shell

build:
	$(COMPOSE) build
	$(RUN) composer install --no-interaction --no-progress

test:
	$(RUN) vendor/bin/phpunit

# --memory-limit above the container's 128M php.ini default: analysing src/ and tests/
# together already crosses that ceiling with the infrastructure layer in place, unrelated
# to any single file's size (docs/specs/08-harnais.md § chantier 6).
lint:
	$(RUN) vendor/bin/phpstan analyse --no-progress --memory-limit=512M

dup:
	$(RUN) vendor/bin/phpcpd --min-lines=5 --min-tokens=70 src

demo:
	$(RUN) bin/aidd-level evaluate profiles/perceval profiles/bohort profiles/leodagan profiles/arthur

fmt:
	$(RUN) vendor/bin/php-cs-fixer fix $(FILE) --quiet

shell:
	$(RUN) sh
