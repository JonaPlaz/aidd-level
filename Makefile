# All commands run inside a live Docker container: local PHP is not guaranteed to be 8.5.
# `make up` starts it (docker compose exec php ...); every other target requires it running,
# except `evaluate`, which is meant to be typed directly inside the container.
COMPOSE := docker compose
EXEC    := $(COMPOSE) exec -T php

# Bash's $$UID isn't exported and $$GID usually isn't set at all, so a bare
# `docker compose` would silently fall back to the compose file's 1000:1000
# default; export the real host ids for every invocation below.
export UID := $(shell id -u)
export GID := $(shell id -g)

# Duplication threshold (percent). Not sourced by the grid; set at the level of
# the best profile provided by the subject (leodagan: 1.7 %, arthur: 2.4 %).
DUPLICATION_MAX_PCT := 3

.PHONY: up exec down test lint dup demo self fmt evaluate require-up

# Fails with a clear message if the `php` service isn't running, instead of
# letting `docker compose exec` print its own opaque error.
require-up:
	@$(COMPOSE) ps --status running --services 2>/dev/null | grep -qx php || \
		{ echo "Lance d'abord : make up"; exit 1; }

up:
	$(COMPOSE) up -d --build
	# A vendor/ left by an earlier run as root would block Composer: take it back first.
	$(COMPOSE) exec -T --user root php sh -c 'chown -R $(UID):$(GID) /app/vendor /app/composer.lock 2>/dev/null || true'
	$(COMPOSE) exec -T php composer install --no-interaction --no-progress

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

# The four supplied profiles only: docs/calibration.md reproduces its verdicts from this
# exact command, so it must not silently gain an extra, repository-specific profile.
demo: require-up
	$(EXEC) bin/aidd-level evaluate profiles/perceval profiles/bohort profiles/leodagan profiles/arthur

self: require-up
	$(EXEC) bin/aidd-level evaluate profiles/self

fmt: require-up
	$(EXEC) vendor/bin/php-cs-fixer fix $(FILE) --quiet

# `make evaluate arthur [bohort ...]` — meant to be typed inside the container, straight
# after `make exec`, without wrapping "docker compose" around the tool. Profile names are
# picked up from the extra command-line goals (MAKECMDGOALS), caught by the empty pattern
# rule below so make doesn't complain about "No rule to make target"; `P=` is a fallback for
# names that don't survive as bare make goals. Each name resolves to `profiles/<name>`, then
# `fixtures/<name>`, then falls back to the literal path. Typed outside the container
# (IN_CONTAINER unset), it re-enters via `docker compose exec -T` so it works from either
# side (docs/specs/00-vue-ensemble.md § 6).
evaluate:
	@names="$(strip $(filter-out $@,$(MAKECMDGOALS)) $(P))"; \
	if [ -z "$$names" ]; then echo "Usage : make evaluate <profil> [<profil>...]"; exit 1; fi; \
	if [ -n "$$IN_CONTAINER" ]; then \
		paths=""; \
		for n in $$names; do \
			if [ -d "profiles/$$n" ]; then paths="$$paths profiles/$$n"; \
			elif [ -d "fixtures/$$n" ]; then paths="$$paths fixtures/$$n"; \
			else paths="$$paths $$n"; fi; \
		done; \
		bin/aidd-level evaluate $$paths; \
	else \
		$(MAKE) --no-print-directory require-up; \
		$(COMPOSE) exec -T php make evaluate $$names; \
	fi

# Swallows the profile names passed alongside `evaluate` (e.g. `make evaluate arthur`) so
# make doesn't treat them as targets to build in their own right.
%:
	@:
