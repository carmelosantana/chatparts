#!/usr/bin/env bash
# Copies the vendored set into <target-dir>/vendor-chatparts/, replacing it wholesale,
# and verifies every copied file against CHECKSUMS. Exits 1 on any mismatch.
# Usage: scripts/sync-into.sh <target-dir>
set -euo pipefail
export LC_ALL=C

fail() { echo "sync-into: $*" >&2; exit 1; }

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
target="${1:-}"
[[ -n "$target" ]] || fail "usage: scripts/sync-into.sh <target-dir>"
[[ -d "$target" ]] || fail "target is not a directory: $target"
target="$(cd "$target" && pwd)"
[[ "$target" != "/" ]] || fail "refusing to sync into /"
dest="$target/vendor-chatparts"

# CHECKSUMS must describe the source tree exactly, before anything is copied.
[[ -f "$root/CHECKSUMS" ]] || fail "CHECKSUMS is missing; run scripts/checksums.sh"
current="$(mktemp)"
trap 'rm -f "$current"' EXIT
"$root/scripts/checksums.sh" "$current"
cmp -s "$current" "$root/CHECKSUMS" \
  || fail "CHECKSUMS is out of date for the source tree; run scripts/checksums.sh"

# Replace the vendored copy wholesale so no file from an older version survives.
rm -rf -- "$dest"
mkdir -p -- "$dest"
(cd "$root" && cp -R --parents src assets autoload.php LICENSE THIRD_PARTY.md CHECKSUMS "$dest/")

# Verify: every listed file matches, and nothing unlisted was copied.
(cd "$dest" && sha256sum --quiet --strict -c CHECKSUMS) \
  || fail "a copied file does not match CHECKSUMS in $dest"
expected="$(cut -c67- "$dest/CHECKSUMS"; echo CHECKSUMS)"
actual="$(cd "$dest" && find . -type f | sed 's|^\./||')"
extra="$(comm -13 <(sort <<<"$expected") <(sort <<<"$actual"))"
[[ -z "$extra" ]] || fail "files not in CHECKSUMS were copied into $dest: $extra"

echo "sync-into: vendored chatparts into $dest ($(wc -l < "$dest/CHECKSUMS") files verified)"
