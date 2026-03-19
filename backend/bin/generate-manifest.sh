#!/usr/bin/env bash
# Generates docs/codebase-manifest.md with structural facts about the codebase.
# Usage: bash backend/bin/generate-manifest.sh (from project root)
#    or: make manifest

set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
SRC="$PROJECT_ROOT/backend/src"
TESTS="$PROJECT_ROOT/backend/tests"
MIGRATIONS="$PROJECT_ROOT/backend/migrations"
OUTPUT="$PROJECT_ROOT/docs/codebase-manifest.md"

# --- Counting helpers ---
count_php() { find "$1" -maxdepth "${2:-99}" -name "*.php" 2>/dev/null | wc -l | tr -d ' '; }
list_php_basenames() { find "$1" -maxdepth "${2:-1}" -name "*.php" 2>/dev/null | xargs -I{} basename {} .php | sort; }

# --- Counts ---
ENTITIES=$(count_php "$SRC/Entity" 1)
ENUMS_CORE=$(count_php "$SRC/Enum" 1)
ENUMS_PROVIDER=$(find "$SRC/Provider" -name "*.php" -path "*/Enum/*" 2>/dev/null | wc -l | tr -d ' ')
ENUMS_PROVIDER=$((ENUMS_PROVIDER + $(find "$SRC/Provider" -maxdepth 2 -name "*Type.php" ! -path "*/Enum/*" 2>/dev/null | wc -l | tr -d ' ')))
DOMAIN_MODELS=$(find "$SRC/Domain" -name "*.php" -path "*/Model/*" 2>/dev/null | wc -l | tr -d ' ')
CONTROLLERS=$(count_php "$SRC/Controller" 99)
SERVICES=$(count_php "$SRC/Service" 99)
COMMANDS=$(count_php "$SRC/Command" 1)
REPOSITORIES=$(count_php "$SRC/Repository" 1)
TESTS_COUNT=$(count_php "$TESTS" 99)
MIGRATIONS_COUNT=$(count_php "$MIGRATIONS" 1)
APP_SERVICES=$(count_php "$SRC/Application" 99)
DTOS=$(count_php "$SRC/Dto" 99)
EVENT_LISTENERS=$(count_php "$SRC/EventListener" 99)
MESSAGES=$(count_php "$SRC/Message" 1)
MESSAGE_HANDLERS=$(count_php "$SRC/MessageHandler" 1)

TOTAL_ENUMS=$((ENUMS_CORE + ENUMS_PROVIDER))

GENERATED_AT=$(date '+%Y-%m-%d %H:%M')

# --- Generate ---
cat > "$OUTPUT" <<EOF
# Codebase Manifest

> **Auto-generated** by \`make manifest\` (\`backend/bin/generate-manifest.sh\`).
> Do not edit manually — regenerate after adding/removing entities, enums, services, or controllers.

**Generated:** $GENERATED_AT
**Regenerate:** \`make manifest\`

## Metrics

| Category | Count |
|----------|------:|
| Entities (src/Entity/) | $ENTITIES |
| Domain Models (src/Domain/*/Model/) | $DOMAIN_MODELS |
| Enums — core (src/Enum/) | $ENUMS_CORE |
| Enums — provider | $ENUMS_PROVIDER |
| **Enums total** | **$TOTAL_ENUMS** |
| Controllers | $CONTROLLERS |
| Application Services (src/Application/) | $APP_SERVICES |
| Domain/Infra Services (src/Service/) | $SERVICES |
| Repositories | $REPOSITORIES |
| Console Commands | $COMMANDS |
| DTOs | $DTOS |
| Event Listeners | $EVENT_LISTENERS |
| Messenger Messages | $MESSAGES |
| Message Handlers | $MESSAGE_HANDLERS |
| Tests | $TESTS_COUNT |
| Migrations | $MIGRATIONS_COUNT |

## Entity List

EOF

list_php_basenames "$SRC/Entity" 1 | sed 's/^/- /' >> "$OUTPUT"

cat >> "$OUTPUT" <<EOF

## Domain Models

EOF

if [ "$DOMAIN_MODELS" -gt 0 ]; then
    find "$SRC/Domain" -name "*.php" -path "*/Model/*" 2>/dev/null | sort | while read -r f; do
        context=$(echo "$f" | sed "s|$SRC/Domain/||" | cut -d/ -f1)
        name=$(basename "$f" .php)
        echo "- **$context/** $name"
    done >> "$OUTPUT"
else
    echo "_None yet._" >> "$OUTPUT"
fi

cat >> "$OUTPUT" <<EOF

## Enum List

### Core (src/Enum/)

EOF

list_php_basenames "$SRC/Enum" 1 | sed 's/^/- /' >> "$OUTPUT"

cat >> "$OUTPUT" <<EOF

### Provider

EOF

{
    find "$SRC/Provider" -name "*.php" -path "*/Enum/*" 2>/dev/null
    find "$SRC/Provider" -maxdepth 2 -name "*Type.php" ! -path "*/Enum/*" 2>/dev/null
} | sort | while read -r f; do
    rel=$(echo "$f" | sed "s|$SRC/||")
    name=$(basename "$f" .php)
    echo "- $name (\`$rel\`)"
done >> "$OUTPUT"

cat >> "$OUTPUT" <<EOF

## Bounded Contexts (src/Domain/)

EOF

find "$SRC/Domain" -mindepth 1 -maxdepth 1 -type d 2>/dev/null | sort | while read -r d; do
    ctx=$(basename "$d")
    subdirs=$(find "$d" -mindepth 1 -maxdepth 1 -type d | xargs -I{} basename {} | sort | tr '\n' ', ' | sed 's/,$//')
    echo "- **$ctx/** — $subdirs"
done >> "$OUTPUT"

cat >> "$OUTPUT" <<EOF

## src/ Directory Tree (2 levels)

\`\`\`
EOF

find "$SRC" -mindepth 1 -maxdepth 2 -type d | sed "s|$SRC/||" | sort >> "$OUTPUT"

cat >> "$OUTPUT" <<EOF
\`\`\`

## Deep Reference

For detailed information beyond this manifest, consult:

| Topic | Document |
|-------|----------|
| Entity details, relations, traits | \`docs/knowledge/domain-model.md\` |
| Full feature inventory | \`docs/FEATURES.md\` |
| Architecture, bounded contexts | \`docs/knowledge/architecture-ddd.md\` |
| API endpoints, controllers | \`docs/knowledge/api-surface.md\` |
| Design patterns in use | \`docs/knowledge/design-patterns.md\` |
| All knowledge modules | \`docs/knowledge/index.md\` |
EOF

echo ""
echo "✓ Manifest generated: $OUTPUT"
echo "  Entities: $ENTITIES | Enums: $TOTAL_ENUMS | Controllers: $CONTROLLERS | Tests: $TESTS_COUNT"
