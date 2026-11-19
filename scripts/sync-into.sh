#!/usr/bin/env bash
# Copies the vendored set into <target-dir>/vendor-chatparts/, replacing it wholesale,
# and verifies every copied file against CHECKSUMS. The copy is staged and verified beside
# the old one and only then swapped in, so a failed sync leaves the previous copy as it was.
# Exits 1 on any mismatch.
# Usage: scripts/sync-into.sh <target-dir>
set -euo pipefail
export LC_ALL=C
unset CDPATH

fail() { echo "sync-into: $*" >&2; exit 1; }

root="$(cd -P -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd -P)"
target="${1:-}"
[[ -n "$target" ]] || fail "usage: scripts/sync-into.sh <target-dir>"
[[ -d "$target" ]] || fail "target is not a directory: $target"
target="$(cd -P -- "$target" >/dev/null && pwd -P)"
[[ "$target" != "/" ]] || fail "refusing to sync into /"
case "$target/" in
  "$root"/*) fail "target is inside the chatparts checkout: $target" ;;
esac
dest="$target/vendor-chatparts"

current="$(mktemp)"
stage=""
cleanup() { rm -f -- "$current"; [[ -z "$stage" ]] || rm -rf -- "$stage"; }
trap cleanup EXIT

# CHECKSUMS must describe the source tree exactly, before anything is copied.
[[ -f "$root/CHECKSUMS" ]] || fail "CHECKSUMS is missing; run scripts/checksums.sh"
"$root/scripts/checksums.sh" "$current"
cmp -s "$current" "$root/CHECKSUMS" \
  || fail "CHECKSUMS is out of date for the source tree; run scripts/checksums.sh"

# Stage the copy next to the destination (same filesystem, so the swap is a rename).
stage="$(mktemp -d "$target/.vendor-chatparts.XXXXXX")"
chmod 755 -- "$stage"
(cd -- "$root" && cp -R --parents src assets autoload.php LICENSE THIRD_PARTY.md CHECKSUMS "$stage/") \
  || fail "copying into $stage failed"

# Verify the stage: every listed file matches, and nothing unlisted (symlinks included) is there.
(cd -- "$stage" && sha256sum --quiet --strict -c CHECKSUMS) \
  || fail "a copied file does not match CHECKSUMS; $dest left as it was"
expected="$(cut -c67- "$stage/CHECKSUMS"; echo CHECKSUMS)"
actual="$(cd -- "$stage" && find . ! -type d | sed 's|^\./||')"
extra="$(comm -13 <(sort <<<"$expected") <(sort <<<"$actual"))"
[[ -z "$extra" ]] || fail "files not in CHECKSUMS were copied; $dest left as it was: $extra"

# Swap the verified copy in, replacing the old one wholesale.
rm -rf -- "$dest"
mv -- "$stage" "$dest"
stage=""

echo "sync-into: vendored chatparts into $dest ($(wc -l < "$dest/CHECKSUMS") files verified)"
