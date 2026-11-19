#!/usr/bin/env bash
# Writes CHECKSUMS: the sha256 of every file a host vendors (src/, assets/, autoload.php,
# LICENSE, THIRD_PARTY.md), in sha256sum format, sorted, with repo-relative paths.
# Usage: scripts/checksums.sh [output-file]   (default: CHECKSUMS at the repo root)
set -euo pipefail
export LC_ALL=C
unset CDPATH

root="$(cd -P -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd -P)"
out="${1:-$root/CHECKSUMS}"

cd -- "$root"
tmp="$(mktemp)"
trap 'rm -f -- "$tmp"' EXIT
find src assets autoload.php LICENSE THIRD_PARTY.md -type f -print0 \
  | sort -z \
  | xargs -0 sha256sum > "$tmp"
chmod 644 -- "$tmp"
mv -- "$tmp" "$out"
trap - EXIT
