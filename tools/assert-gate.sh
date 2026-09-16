#!/usr/bin/env bash
set -euo pipefail

# Maintained stable aggregate gate for maatify/php-eligibility workflows.
#
# Usage:
#   tools/assert-gate.sh '<needs.*.result JSON array>'
#
# Fails closed when any upstream required job reported failure, was cancelled,
# or was skipped. This repository uses the always-run CI model, so an
# unexpected skip is always a defect and must fail the gate.

results="${1:-}"
if [[ -z "$results" ]]; then
	echo "error: no upstream job results were provided to the aggregate gate." >&2
	exit 1
fi

if ! printf '%s' "$results" | jq -e 'all(.[]; . == "success")' >/dev/null 2>&1; then
	echo "error: one or more required upstream jobs did not succeed: $results" >&2
	exit 1
fi

echo "Aggregate gate passed: all upstream jobs succeeded."