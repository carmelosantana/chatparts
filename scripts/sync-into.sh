#!/usr/bin/env bash
# Copies the vendored set into <target-dir>/vendor-chatparts/, replacing it wholesale,
# and verifies every copied file against CHECKSUMS. The copy is staged and verified beside
# the old one and only then swapped in: the old copy is moved aside, the stage moved in, and
# the old copy deleted last, so a failed sync leaves (or puts back) the previous copy.
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
if [[ -e "$dest" ]]; then
  # A symlinked or misplaced destination must never be (or hold) the checkout it copies from.
  resolved="$(cd -P -- "$dest" >/dev/null 2>&1 && pwd -P)" || resolved="$dest"
  [[ "$resolved" != "/" ]] || fail "destination resolves to /: $dest"
  case "$root/" in
    "$resolved"/*) fail "destination is or contains the chatparts checkout: $dest -> $resolved" ;;
  esac
fi

current=""
stage=""
aside=""
# The aside dir is only removed when empty: if putting the old copy back failed, it stays.
cleanup() {
  [[ -z "$current" ]] || rm -f -- "$current"
  [[ -z "$stage" ]] || rm -rf -- "$stage"
  [[ -z "$aside" ]] || rmdir -- "$aside" 2>/dev/null || true
}
trap cleanup EXIT
current="$(mktemp)" || fail "could not make a temp file"

# CHECKSUMS must describe the source tree exactly, before anything is copied.
[[ -f "$root/CHECKSUMS" ]] || fail "CHECKSUMS is missing; run scripts/checksums.sh"
"$root/scripts/checksums.sh" "$current"
cmp -s "$current" "$root/CHECKSUMS" \
  || fail "CHECKSUMS is out of date for the source tree; run scripts/checksums.sh"

# Stage the copy next to the destination (same filesystem, so the swap is a rename).
stage="$(mktemp -d "$target/.vendor-chatparts.XXXXXX" 2>/dev/null)" \
  || fail "could not make a staging dir in $target; $dest left as it was"
chmod 755 -- "$stage" || fail "could not chmod $stage"
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
if [[ -e "$dest" || -L "$dest" ]]; then
  aside="$(mktemp -d "$target/.vendor-chatparts-old.XXXXXX" 2>/dev/null)" \
    || fail "could not make a dir to move the old copy aside in $target; $dest left as it was"
  mv -- "$dest" "$aside/old" || fail "could not move $dest aside; $dest left as it was"
fi
if ! mv -- "$stage" "$dest"; then
  [[ -n "$aside" ]] || fail "could not move the new copy into $dest"
  mv -- "$aside/old" "$dest" \
    || fail "could not move the new copy into $dest, nor restore the old one: it is at $aside/old"
  fail "could not move the new copy into $dest; the old copy is restored"
fi
stage=""
if [[ -n "$aside" ]]; then
  rm -rf -- "$aside" || fail "the new copy is in $dest, but removing the old one at $aside failed"
  aside=""
fi

echo "sync-into: vendored chatparts into $dest ($(wc -l < "$dest/CHECKSUMS") files verified)"
