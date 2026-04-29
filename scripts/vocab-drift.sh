#!/usr/bin/env bash
# vocab-drift.sh — Hito 3 Phase C, C-3.
#
# Read-only check: each entry in docs/knowledge/_vocabulary.yaml
# whose `bounded_context` is curated (not TODO) should have an
# `authoritative_path` that exists and a `canonical` that grep-
# matches inside that file.
#
# Output rows (TSV):  kind \t canonical \t path \t detail
# kinds:
#   MISSING_PATH  authoritative_path does not exist on disk
#   NAME_DRIFT    canonical name not found inside the file
#
# Exit 0 → no drift; Exit 1 → drift reported; Exit 2 → invalid args/env.
#
# Origin: 2026-04-29 hito 3 Phase C tooling.

set -euo pipefail

REPO_ROOT="$(git rev-parse --show-toplevel 2>/dev/null)"
if [ -z "$REPO_ROOT" ]; then
  REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
fi
VOCAB_FILE="${VOCAB_FILE:-$REPO_ROOT/docs/knowledge/_vocabulary.yaml}"

if [ ! -f "$VOCAB_FILE" ]; then
  echo "ERROR: vocab file not found: $VOCAB_FILE" >&2
  exit 2
fi

# Extract entries (canonical, bounded_context, authoritative_path,
# definition first-line) from the YAML using awk.
extract_entries() {
  awk '
    function reset() {
      canonical=""; ctx=""; path=""; def=""
    }
    BEGIN { reset(); have=0 }
    /^  - canonical: / {
      if (have) print canonical "\t" ctx "\t" path "\t" def
      reset()
      canonical=$0; sub(/^  - canonical: /, "", canonical)
      have=1
      next
    }
    /^    bounded_context: / {
      ctx=$0; sub(/^    bounded_context: /, "", ctx)
    }
    /^    authoritative_path: / {
      path=$0; sub(/^    authoritative_path: /, "", path)
    }
    /^    definition: / {
      def=$0; sub(/^    definition: /, "", def)
    }
    END { if (have) print canonical "\t" ctx "\t" path "\t" def }
  ' "$VOCAB_FILE"
}

drift_count=0

while IFS=$'\t' read -r canonical ctx path definition; do
  [ -z "$canonical" ] && continue
  # Skip uncurated entries — drift only applies to curated registry.
  if [ "$ctx" = "TODO" ]; then continue; fi
  case "$definition" in
    *TODO:*) continue ;;
  esac
  if [ -z "$path" ]; then continue; fi
  full="$REPO_ROOT/$path"
  if [ ! -f "$full" ]; then
    printf 'MISSING_PATH\t%s\t%s\t%s\n' \
      "$canonical" "$path" "authoritative_path does not exist on disk"
    drift_count=$((drift_count + 1))
    continue
  fi
  if ! grep -wF -q -- "$canonical" "$full" 2>/dev/null; then
    hint=""
    candidates=$(grep -rwlF --include='*.php' --include='*.ts' --include='*.tsx' \
      --include='*.sh' -- "$canonical" "$REPO_ROOT" 2>/dev/null | head -3 || true)
    if [ -n "$candidates" ]; then
      first=$(echo "$candidates" | head -1)
      first="${first#"$REPO_ROOT/"}"
      hint="canonical found in: $first"
    else
      hint="canonical not found anywhere in repo"
    fi
    printf 'NAME_DRIFT\t%s\t%s\t%s\n' "$canonical" "$path" "$hint"
    drift_count=$((drift_count + 1))
  fi
done < <(extract_entries)

if [ "$drift_count" -gt 0 ]; then
  echo "" >&2
  echo "vocab-drift: $drift_count drift row(s) reported" >&2
  exit 1
fi

exit 0
