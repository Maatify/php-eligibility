#!/usr/bin/env bash
set -euo pipefail

# Local parity aggregate gate for maatify/php-eligibility.
#
# This is the repository-owned maintained command that mirrors the mandatory CI
# gates which do not require an external service:
#
#   composer validate, optimized strict autoload, platform requirements,
#   PHP syntax lint, PHPStan level max, whitespace check, Composer security
#   audit, workflow lint, Unit suite, Golden suite.
#
# Before running, resolve development dependencies once:
#
#   composer update --no-interaction --prefer-dist --no-progress
#
# To also run the real-service gates locally, start the MySQL fixture first
# and pass --with-integration. A real PDO readiness check
# (tools/mysql-ready.php) runs before the suites, mirroring ci-integration:
#
#   docker compose -f docker-compose.integration.yml up -d --wait
#   tools/check-local.sh --with-integration

with_integration=false
for arg in "$@"; do
	case "$arg" in
		--with-integration) with_integration=true ;;
		*)
			echo "error: unknown argument: $arg" >&2
			exit 2
			;;
	esac
done

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$repo_root"

run_step() {
	local label="$1"
	shift
	printf '\n=== %s ===\n' "$label"
	"$@"
}

if [[ ! -d vendor ]]; then
	echo "error: vendor/ is missing. Run: composer update --no-interaction --prefer-dist --no-progress" >&2
	exit 1
fi

run_step "Composer validation" composer validate --strict
run_step "Optimized strict PSR-4 autoload" composer dump-autoload --optimize --strict-psr
run_step "Platform requirements" composer check-platform-reqs
run_step "PHP syntax lint" php tools/php-lint.php
run_step "PHPStan level max" composer analyse
run_step "Whitespace check" tools/check-whitespace.sh
run_step "Composer security audit" composer audit --no-interaction --abandoned=fail
run_step "Workflow lint" tools/lint-workflows.sh
run_step "Unit suite" composer test:unit
run_step "Golden suite" composer test:golden

if [[ "$with_integration" == true ]]; then
	run_step "MySQL fixture readiness" php tools/mysql-ready.php
	run_step "Integration suite" composer test:integration
	run_step "Integration suite repeatability run" composer test:integration
	run_step "Consumer Verification Harness" composer test:harness
fi

printf '\nLocal aggregate gate passed.\n'