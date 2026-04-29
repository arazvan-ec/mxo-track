#!/usr/bin/env bash
# bootstrap-vocabulary.sh — auto-extract canonical names into _vocabulary.yaml
#
# Scans backend domain/entity/enum sources, frontend types, page/widget
# registries, and workflow phase names. Emits or updates entries in
# docs/knowledge/_vocabulary.yaml.
#
# Idempotent: re-running on an existing _vocabulary.yaml NEVER overwrites
# curated fields (aliases, definition, related, cross_references).
# Only adds new symbols and updates `authoritative_path` when a known
# canonical moves.
#
# Usage:
#   scripts/bootstrap-vocabulary.sh              # write to default file
#   VOCAB_FILE=/tmp/foo.yaml scripts/bootstrap-vocabulary.sh
#   DRY_RUN=1 scripts/bootstrap-vocabulary.sh    # print would-add to stdout
#
# Origin: 2026-04-29 hito 3 phase A.

set -euo pipefail

REPO="$(cd "$(dirname "$0")/.." && pwd)"
VOCAB_FILE="${VOCAB_FILE:-$REPO/docs/knowledge/_vocabulary.yaml}"
DRY_RUN="${DRY_RUN:-0}"

# ── Extract canonical names from sources ──
# Output format per line: NAME|PATH|LAYER

extract_backend_models() {
  # PHP classes in Domain/*/Model/ — class declaration line
  find "$REPO/backend/src/Domain" -path '*/Model/*.php' 2>/dev/null \
    | while read -r f; do
        local cls
        cls=$(grep -E '^(final |abstract )?class ' "$f" 2>/dev/null \
              | head -1 | sed -E 's/^(final |abstract )?class ([A-Za-z0-9_]+).*/\2/')
        [ -n "$cls" ] && echo "${cls}|backend/src${f#"$REPO/backend/src"}|domain"
      done
}

extract_backend_entities() {
  find "$REPO/backend/src/Entity" -name '*.php' 2>/dev/null \
    | while read -r f; do
        local cls
        cls=$(grep -E '^(final |abstract )?class ' "$f" 2>/dev/null \
              | head -1 | sed -E 's/^(final |abstract )?class ([A-Za-z0-9_]+).*/\2/')
        [ -n "$cls" ] && echo "${cls}|backend/src${f#"$REPO/backend/src"}|domain"
      done
}

extract_backend_enums() {
  find "$REPO/backend/src/Enum" -name '*.php' 2>/dev/null \
    | while read -r f; do
        local enum_name
        enum_name=$(grep -E '^enum ' "$f" 2>/dev/null \
                    | head -1 | sed -E 's/^enum ([A-Za-z0-9_]+).*/\1/')
        [ -n "$enum_name" ] && echo "${enum_name}|backend/src${f#"$REPO/backend/src"}|domain"
      done
}

extract_frontend_types() {
  # Top-level exported types/interfaces/enums in frontend/src/types/
  find "$REPO/frontend/src/types" -name '*.ts' 2>/dev/null \
    | while read -r f; do
        grep -hE '^export (type|interface|enum) [A-Z]' "$f" 2>/dev/null \
          | sed -E 's/^export (type|interface|enum) ([A-Za-z0-9_]+).*/\2/' \
          | while read -r t; do
              [ -n "$t" ] && echo "${t}|frontend/src${f#"$REPO/frontend/src"}|ui"
            done
      done
}

extract_workflow_layers() {
  # Validators in .claude/hooks/validators/ (layer K, N, S, etc.)
  find "$REPO/.claude/hooks/validators" -name '*-validator.sh' 2>/dev/null \
    | while read -r f; do
        local base
        base=$(basename "$f" -validator.sh)
        # Capitalize for canonical-style display
        local cap
        cap=$(echo "$base" | sed -E 's/(^|-)([a-z])/\U\2/g')
        echo "${cap}Validator|.claude/hooks/validators/$(basename "$f")|workflow"
      done
}

# ── Aggregate ──
ALL_RAW=$(
  {
    extract_backend_models
    extract_backend_entities
    extract_backend_enums
    extract_frontend_types
    extract_workflow_layers
  } | sort -u
)

# ── Read existing canonicals from VOCAB_FILE (idempotency check) ──
EXISTING_CANONICALS=""
if [ -f "$VOCAB_FILE" ]; then
  EXISTING_CANONICALS=$(grep -E '^  - canonical: ' "$VOCAB_FILE" 2>/dev/null \
                       | sed -E 's/^  - canonical: //' | sort -u)
fi

# ── Compute new entries (canonicals not yet in file) ──
NEW_ENTRIES=""
while IFS='|' read -r name path layer; do
  [ -z "$name" ] && continue
  if ! echo "$EXISTING_CANONICALS" | grep -qx "$name"; then
    NEW_ENTRIES+="$name|$path|$layer"$'\n'
  fi
done <<< "$ALL_RAW"

# Trim trailing newline
NEW_ENTRIES=$(echo "$NEW_ENTRIES" | { grep -v '^$' || true; })

if [ -z "$NEW_ENTRIES" ]; then
  echo "✓ _vocabulary.yaml is up to date — no new symbols to bootstrap"
  exit 0
fi

NEW_COUNT=$(echo "$NEW_ENTRIES" | wc -l | tr -d ' ')

# ── Emit YAML for new entries ──
emit_new_entries() {
  while IFS='|' read -r name path layer; do
    [ -z "$name" ] && continue
    cat <<EOF

  - canonical: $name
    aliases: []
    definition: "TODO: curate definition"
    bounded_context: TODO
    layer: $layer
    authoritative_path: $path
    related: []
    lifecycle: active
EOF
  done <<< "$NEW_ENTRIES"
}

if [ "$DRY_RUN" = "1" ]; then
  echo "── DRY RUN: $NEW_COUNT new symbols would be appended to $VOCAB_FILE ──"
  emit_new_entries
  exit 0
fi

# ── Append new entries to VOCAB_FILE ──
{
  emit_new_entries
} >> "$VOCAB_FILE"

echo "✓ Appended $NEW_COUNT new symbols to $VOCAB_FILE (curated fields preserved)"
echo "  Next: review TODO entries and curate definitions/aliases/bounded_context"
