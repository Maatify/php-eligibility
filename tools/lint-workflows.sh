#!/usr/bin/env bash
set -euo pipefail

# Maintained GitHub Actions workflow lint gate for maatify/php-eligibility.
#
# Uses actionlint pinned to a fixed released version. When an `actionlint`
# binary is already available on PATH (for example after `brew install
# actionlint`), it is used as-is. Otherwise the pinned release binary is
# downloaded from GitHub Releases and verified against the official SHA-256
# checksum file before execution, satisfying the documented integrity-verifiable
# installation policy for downloaded CI tools.
#
# Version policy: the version below is the immutable reference. Upgrade it
# deliberately with the same release and record the change in CHANGELOG.md.

version="${ACTIONLINT_VERSION:-1.7.12}"
repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
workflows_dir="$repo_root/.github/workflows"

fail() {
	echo "error: $1" >&2
	exit 1
}

if [[ ! -d "$workflows_dir" ]]; then
	echo "Workflow lint passed: no .github/workflows directory is present."
	exit 0
fi

verify_asset() {
	local asset="$1"
	local checksums_dir="$2"
	local shasum_bin=""
	if command -v sha256sum >/dev/null 2>&1; then
		shasum_bin="sha256sum"
	elif command -v shasum >/dev/null 2>&1; then
		shasum_bin="shasum -a 256"
	else
		fail "no sha256 verification tool available"
	fi
	(
		cd "$checksums_dir"
		if ! $shasum_bin -c "$asset.sha256" >/dev/null; then
			fail "actionlint release checksum verification failed for $asset"
		fi
	)
}

actionlint_bin="${ACTIONLINT_BIN:-}"
if [[ -z "$actionlint_bin" ]]; then
	actionlint_bin="$(command -v actionlint || true)"
fi

if [[ -z "$actionlint_bin" ]]; then
	case "$(uname -s)" in
		Linux) os="linux" ;;
		Darwin) os="darwin" ;;
		*) fail "unsupported operating system for pinned actionlint download: $(uname -s)" ;;
	esac

	case "$(uname -m)" in
		x86_64 | amd64) arch="amd64" ;;
		arm64 | aarch64) arch="arm64" ;;
		*) fail "unsupported architecture for pinned actionlint download: $(uname -m)" ;;
	esac

	temp_dir="$(mktemp -d)"
	# shellcheck disable=SC2064
	trap 'rm -rf "$temp_dir"' EXIT

	asset="actionlint_${version}_${os}_${arch}.tar.gz"
	base_url="https://github.com/rhysd/actionlint/releases/download/v${version}"

	curl -fsSL -o "$temp_dir/checksums.txt" "$base_url/actionlint_${version}_checksums.txt"
	curl -fsSL -o "$temp_dir/$asset" "$base_url/$asset"
	grep -F "  $asset" "$temp_dir/checksums.txt" > "$temp_dir/$asset.sha256"
	verify_asset "$asset" "$temp_dir"
	tar -xzf "$temp_dir/$asset" -C "$temp_dir"
	actionlint_bin="$temp_dir/actionlint"
fi

files=()
while IFS= read -r -d '' file; do
	files+=("$file")
done < <(find "$workflows_dir" -maxdepth 1 -type f \( -name '*.yml' -o -name '*.yaml' \) -print0)

if [[ ${#files[@]} -eq 0 ]]; then
	echo "Workflow lint passed: no workflow files present."
	exit 0
fi

"$actionlint_bin" "${files[@]}"
echo "Workflow lint passed using actionlint."