# All commands run inside a live Docker container: local PHP is not guaranteed to be 8.5.
# `make up` starts it (docker compose exec php ...); every other target requires it running.
COMPOSE := docker compose
EXEC    := $(COMPOSE) exec -T php

# Duplication threshold (percent). Not sourced by the grid; set at the level of
# the best profile provided by the subject (leodagan: 1.7 %, arthur: 2.4 %).
DUPLICATION_MAX_PCT := 3

.PHONY: up build exec down test lint dup demo fmt require-up

# Fails with a clear message if the `php` service isn't running, instead of
# letting `docker compose exec` print its own opaque error.
require-up:
	@$(COMPOSE) ps --status running --services 2>/dev/null | grep -qx php || \
		{ echo "Lance d'abord : make up"; exit 1; }

up:
	$(COMPOSE) up -d --build
	$(COMPOSE) exec php composer install --no-interaction --no-progress

build: up

exec: require-up
	$(COMPOSE) exec php sh

down:
	$(COMPOSE) down

test: require-up
	$(EXEC) vendor/bin/phpunit

# --memory-limit above the container's 128M php.ini default: analysing src/ and tests/
# together already crosses that ceiling with the infrastructure layer in place, unrelated
# to any single file's size (docs/specs/08-harnais.md § chantier 6).
lint: require-up
	$(EXEC) vendor/bin/phpstan analyse --no-progress --memory-limit=512M

dup: require-up
	$(EXEC) vendor/bin/phpcpd --min-lines=5 --min-tokens=70 src

demo: require-up
	$(EXEC) bin/aidd-level evaluate profiles/perceval profiles/bohort profiles/leodagan profiles/arthur
	$(EXEC) bin/aidd-level evaluate profiles/self

fmt: require-up
	$(EXEC) vendor/bin/php-cs-fixer fix $(FILE) --quiet
