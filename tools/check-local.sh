#!/usr/bin/env bash
set -euo pipefail

# Local parity aggregate gate for maatify/php-eligibility.
#
# This is the repository-owned maintained command for the mandatory local gates
# which do not require an external service. It requires Composer 2.10.x, matching
# the Composer capability used by the authoritative CI quality gate, but it does
# not resolve either the latest-compatible or lowest-supported dependency matrix.
#
#   composer validate, Composer policy contract, optimized strict autoload,
#   platform requirements, PHP syntax lint, PHPStan level max, PER-CS style, whitespace check,
#   Composer security audit, workflow lint, Unit suite, Golden suite.
#
# Before running, verify Composer 2.10.x and resolve the latest-compatible
# development dependencies once:
#
#   composer --version | grep -Eq '^Composer version 2\.10\.'
#   composer update --no-interaction --prefer-dist --no-progress
#
# To also run the real-service gates locally, pass --with-integration. Each
# command owns a fresh Compose project, dynamic loopback endpoint, readiness
# probe, and teardown; no manual service lifecycle is required.

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

require_composer_210() {
	local version
	version="$(composer --version)"
	printf '%s\n' "$version"
	if ! printf '%s\n' "$version" | grep -Eq '^Composer version 2\.10\.'; then
		echo "error: Composer 2.10.x is required for the repository development/verification workflow" >&2
		exit 1
	fi
}

if [[ ! -d vendor ]]; then
	echo "error: vendor/ is missing. Run: composer update --no-interaction --prefer-dist --no-progress" >&2
	exit 1
fi

run_step "Composer 2.10 capability" require_composer_210
run_step "Composer validation" composer validate --strict
run_step "Composer policy contract" composer check:composer-policy
run_step "Optimized strict PSR-4 autoload" composer dump-autoload --optimize --strict-psr
run_step "Platform requirements" composer check-platform-reqs
run_step "PHP syntax lint" php tools/php-lint.php
run_step "PHPStan level max" composer analyse
run_step "PER-CS 3.1 style check" composer check:style
run_step "Whitespace check" tools/check-whitespace.sh
run_step "Composer security audit" composer audit --no-interaction --abandoned=fail
run_step "Workflow lint" tools/lint-workflows.sh
run_step "Unit suite" composer test:unit
run_step "Golden suite" composer test:golden

if [[ "$with_integration" == true ]]; then
	run_step "Integration suite" composer test:integration
	run_step "Integration suite repeatability run" composer test:integration
	run_step "Consumer Verification Harness" composer test:harness
	run_step "Runnable examples" composer test:examples
fi

printf '\nLocal aggregate gate passed.\n'
