#!/usr/bin/env bash
set -euo pipefail

mode="${1:-}"
case "$mode" in
	integration|full|harness|examples) ;;
	*)
		echo "error: mode must be one of integration, full, harness, examples" >&2
		exit 2
		;;
esac

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
compose_file="$repo_root/docker-compose.integration.yml"
for required in docker php "$compose_file" "$repo_root/tools/mysql-ready.php"; do
	if [[ "$required" == /* ]]; then
		[[ -e "$required" ]] || { echo "error: required repository file is missing: $required" >&2; exit 1; }
	else
		command -v "$required" >/dev/null 2>&1 || { echo "error: required command is missing: $required" >&2; exit 1; }
	fi
done
docker compose version >/dev/null 2>&1 || { echo 'error: docker compose is unavailable' >&2; exit 1; }
docker info >/dev/null 2>&1 || { echo 'error: Docker daemon is unavailable' >&2; exit 1; }
php -m | grep -Fx pdo_mysql >/dev/null || { echo 'error: PHP ext-pdo_mysql is required' >&2; exit 1; }
[[ -x "$repo_root/vendor/bin/phpunit" ]] || { echo 'error: vendor/bin/phpunit is missing; install development dependencies first' >&2; exit 1; }

case "$mode" in
	integration|full) required_env_file="$repo_root/tests/Support/IntegrationDatabase.php" ;;
	harness) required_env_file="$repo_root/consumer-harness/run.php" ;;
	examples) required_env_file="$repo_root/tools/smoke-examples.php" ;;
esac
[[ -f "$required_env_file" ]] || { echo "error: required repository file is missing: $required_env_file" >&2; exit 1; }

project="eligibility-rc2-${mode}-$(printf '%s' "$$" | tr -cd '[:alnum:]')-$(od -An -N6 -tx1 /dev/urandom | tr -d ' \n')"
project="${project:0:60}"
compose=(docker compose -p "$project" -f "$compose_file")
cleanup_status=0
cleanup() {
	local status=$?
	if (( status != 0 )); then
		printf '\n=== Compose diagnostics (%s) ===\n' "$project" >&2
		"${compose[@]}" ps >&2 || true
		"${compose[@]}" logs >&2 || true
	fi
	set +e
	"${compose[@]}" down -v --remove-orphans >/dev/null 2>&1
	cleanup_status=$?
	set -e
	if (( status != 0 )); then
		exit "$status"
	fi
	if (( cleanup_status != 0 )); then
		echo "error: Compose teardown failed for project $project" >&2
		exit "$cleanup_status"
	fi
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

cd "$repo_root"
"${compose[@]}" up -d --wait
endpoint="$("${compose[@]}" port mysql 3306)"
if [[ ! "$endpoint" =~ ^127\.0\.0\.1:([0-9]+)$ ]]; then
	echo "error: Compose did not expose a loopback-only dynamic MySQL endpoint: $endpoint" >&2
	exit 1
fi
port="${BASH_REMATCH[1]}"
export ELIGIBILITY_TEST_DB_HOST=127.0.0.1 ELIGIBILITY_TEST_DB_PORT="$port" ELIGIBILITY_TEST_DB_NAME=maatify_eligibility_test ELIGIBILITY_TEST_DB_USER=eligibility_test ELIGIBILITY_TEST_DB_PASSWORD=eligibility_test
export ELIGIBILITY_HARNESS_DB_HOST="$ELIGIBILITY_TEST_DB_HOST" ELIGIBILITY_HARNESS_DB_PORT="$ELIGIBILITY_TEST_DB_PORT" ELIGIBILITY_HARNESS_DB_NAME="$ELIGIBILITY_TEST_DB_NAME" ELIGIBILITY_HARNESS_DB_USER="$ELIGIBILITY_TEST_DB_USER" ELIGIBILITY_HARNESS_DB_PASSWORD="$ELIGIBILITY_TEST_DB_PASSWORD"
export ELIGIBILITY_DB_HOST="$ELIGIBILITY_TEST_DB_HOST" ELIGIBILITY_DB_PORT="$ELIGIBILITY_TEST_DB_PORT" ELIGIBILITY_DB_NAME="$ELIGIBILITY_TEST_DB_NAME" ELIGIBILITY_DB_USER="$ELIGIBILITY_TEST_DB_USER" ELIGIBILITY_DB_PASSWORD="$ELIGIBILITY_TEST_DB_PASSWORD"
printf 'COMPOSE_PROJECT=%s\nMYSQL_ENDPOINT=%s\n' "$project" "$endpoint"
php tools/mysql-ready.php

case "$mode" in
	integration) vendor/bin/phpunit --testsuite integration ;;
	full) vendor/bin/phpunit ;;
	harness) php consumer-harness/run.php ;;
	examples) php tools/smoke-examples.php ;;
esac
