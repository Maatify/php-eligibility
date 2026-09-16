#!/usr/bin/env bash
set -euo pipefail

# Maintained whitespace integrity gate for maatify/php-eligibility.
#
# Local usage (checks the index and the working tree against HEAD):
#   tools/check-whitespace.sh
#
# CI usage (checks exactly the commit range <base>...<head>):
#   tools/check-whitespace.sh <base> <head>
#
# The first push to a new default branch has no before-commit (all-zero base);
# in that case the script falls back to the parent of HEAD, and for a root
# commit there is no comparison base to check.

base="${1:-}"
head="${2:-}"

check_range() {
	local from="$1"
	local to="$2"
	if ! git diff --check --no-color "${from}...${to}"; then
		echo "error: whitespace defects found in ${from}...${to}" >&2
		exit 1
	fi
	echo "Whitespace check passed for ${from}...${to}"
}

if [[ -n "$base" && -n "$head" ]]; then
	zeros="0000000000000000000000000000000000000000"
	if [[ "$base" == "$zeros" ]]; then
		if git rev-parse "$head^" >/dev/null 2>&1; then
			check_range "$head^" "$head"
			exit 0
		fi
		echo "Whitespace check passed: no comparison base exists for the root commit."
		exit 0
	fi
	check_range "$base" "$head"
	exit 0
fi

if ! git diff --cached --check --no-color || ! git diff --check --no-color; then
	echo "error: whitespace defects found in the index or working tree" >&2
	exit 1
fi
echo "Whitespace check passed."