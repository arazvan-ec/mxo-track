#!/usr/bin/env bash
# Generates docs/codebase-manifest.md with structural facts about the ENTIRE project.
# Usage: bash backend/bin/generate-manifest.sh (from project root)
#    or: make manifest

set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
SRC="$PROJECT_ROOT/backend/src"
TESTS="$PROJECT_ROOT/backend/tests"
MIGRATIONS="$PROJECT_ROOT/backend/migrations"
FRONTEND="$PROJECT_ROOT/frontend/src"
ML_SERVICE="$PROJECT_ROOT/ml-service"
DOCKER="$PROJECT_ROOT/docker"
SCRIPTS="$PROJECT_ROOT/scripts"
OPENSPEC="$PROJECT_ROOT/openspec"
OUTPUT="$PROJECT_ROOT/docs/codebase-manifest.md"

# --- Counting helpers ---
count_files() { find "$1" -maxdepth "${3:-99}" -name "$2" 2>/dev/null | wc -l | tr -d ' '; }
list_php_basenames() { find "$1" -maxdepth "${2:-1}" -name "*.php" 2>/dev/null | xargs -I{} basename {} .php | sort; }

# --- Backend counts ---
ENTITIES=$(count_files "$SRC/Entity" "*.php" 1)
ENUMS_CORE=$(count_files "$SRC/Enum" "*.php" 1)
ENUMS_PROVIDER=$(find "$SRC/Provider" -name "*.php" -path "*/Enum/*" 2>/dev/null | wc -l | tr -d ' ')
ENUMS_PROVIDER=$((ENUMS_PROVIDER + $(find "$SRC/Provider" -maxdepth 2 -name "*Type.php" ! -path "*/Enum/*" 2>/dev/null | wc -l | tr -d ' ')))
DOMAIN_MODELS=$(find "$SRC/Domain" -name "*.php" -path "*/Model/*" 2>/dev/null | wc -l | tr -d ' ')
CONTROLLERS=$(count_files "$SRC/Controller" "*.php")
SERVICES=$(count_files "$SRC/Service" "*.php")
COMMANDS=$(count_files "$SRC/Command" "*.php" 1)
REPOSITORIES=$(count_files "$SRC/Repository" "*.php" 1)
TESTS_COUNT=$(count_files "$TESTS" "*.php")
MIGRATIONS_COUNT=$(count_files "$MIGRATIONS" "*.php" 1)
APP_SERVICES=$(count_files "$SRC/Application" "*.php")
DTOS=$(count_files "$SRC/Dto" "*.php")
EVENT_LISTENERS=$(count_files "$SRC/EventListener" "*.php")
MESSAGES=$(count_files "$SRC/Message" "*.php" 1)
MESSAGE_HANDLERS=$(count_files "$SRC/MessageHandler" "*.php" 1)
TOTAL_ENUMS=$((ENUMS_CORE + ENUMS_PROVIDER))
BACKEND_TOTAL=$(count_files "$SRC" "*.php")

# --- Frontend counts ---
FRONTEND_JS=$(count_files "$FRONTEND" "*.js")
FRONTEND_TS=$(count_files "$FRONTEND" "*.ts")
FRONTEND_JSX=$(count_files "$FRONTEND" "*.jsx")
FRONTEND_TSX=$(count_files "$FRONTEND" "*.tsx")
FRONTEND_TOTAL=$((FRONTEND_JS + FRONTEND_TS + FRONTEND_JSX + FRONTEND_TSX))
FRONTEND_COMPONENTS=$(count_files "$FRONTEND/components" "*.jsx" 2>/dev/null; count_files "$FRONTEND/components" "*.tsx" 2>/dev/null; count_files "$FRONTEND/components" "*.js")
FRONTEND_PAGES=$(find "$FRONTEND/pages" -name "*.js" -o -name "*.jsx" -o -name "*.ts" -o -name "*.tsx" 2>/dev/null | wc -l | tr -d ' ')

# --- ML Service counts ---
ML_PYTHON=$(count_files "$ML_SERVICE" "*.py")
ML_ROUTERS=$(count_files "$ML_SERVICE/app/routers" "*.py" 1)
ML_MODELS=$(count_files "$ML_SERVICE/app/models" "*.py" 1)

# --- Docker/Infra counts ---
DOCKER_FILES=$(find "$DOCKER" -type f 2>/dev/null | wc -l | tr -d ' ')
SCRIPT_FILES=$(find "$SCRIPTS" -type f 2>/dev/null | wc -l | tr -d ' ')

# --- OpenSpec counts ---
OPENSPEC_TOTAL=$(find "$OPENSPEC" -type f -name "*.yaml" -o -name "*.yml" -o -name "*.md" 2>/dev/null | wc -l | tr -d ' ')
OPENSPEC_ENTITIES=$(find "$OPENSPEC" -path "*/entities/*" -type f 2>/dev/null | wc -l | tr -d ' ')
OPENSPEC_RULES=$(find "$OPENSPEC" -path "*/business-rules/*" -type f 2>/dev/null | wc -l | tr -d ' ')
OPENSPEC_CONTRACTS=$(find "$OPENSPEC" -path "*/api-contracts/*" -type f 2>/dev/null | wc -l | tr -d ' ')

GENERATED_AT=$(date '+%Y-%m-%d %H:%M')

# --- Generate ---
cat > "$OUTPUT" <<EOF
# Codebase Manifest

> **Auto-generated** by \`make manifest\` (\`backend/bin/generate-manifest.sh\`).
> Do not edit manually — regenerate with \`make manifest\`.

**Generated:** $GENERATED_AT
**Regenerate:** \`make manifest\`

## Project Overview

| Area | Path | Files | Tech |
|------|------|------:|------|
| Backend | \`backend/\` | $BACKEND_TOTAL PHP | Symfony 7.4, PHP 8.4 |
| Frontend | \`frontend/\` | $FRONTEND_TOTAL JS/TS | React |
| ML Service | \`ml-service/\` | $ML_PYTHON Python | FastAPI |
| Docker/Infra | \`docker/\` + \`scripts/\` | $DOCKER_FILES + $SCRIPT_FILES | Docker, OSRM, VROOM, Traccar |
| OpenSpec | \`openspec/\` | $OPENSPEC_TOTAL specs | YAML specs |
| Docs | \`docs/\` | — | Knowledge modules, analysis |

---

## Backend Metrics

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

## Backend Directory Tree (2 levels)

\`\`\`
EOF

find "$SRC" -mindepth 1 -maxdepth 2 -type d | sed "s|$SRC/||" | sort >> "$OUTPUT"

cat >> "$OUTPUT" <<EOF
\`\`\`

---

## Frontend

| Category | Count |
|----------|------:|
| JS/TS files total | $FRONTEND_TOTAL |
| Pages | $FRONTEND_PAGES |

### Directory Tree

\`\`\`
EOF

find "$FRONTEND" -mindepth 1 -maxdepth 2 -type d 2>/dev/null | sed "s|$FRONTEND/||" | sort >> "$OUTPUT"

cat >> "$OUTPUT" <<EOF
\`\`\`

---

## ML Service

| Category | Count |
|----------|------:|
| Python files | $ML_PYTHON |
| API Routers | $ML_ROUTERS |
| Models | $ML_MODELS |

### Directory Tree

\`\`\`
EOF

find "$ML_SERVICE" -mindepth 1 -maxdepth 2 -type d -not -path "*/__pycache__*" -not -path "*/.git*" -not -path "*/venv*" -not -path "*/.venv*" 2>/dev/null | sed "s|$ML_SERVICE/||" | sort >> "$OUTPUT"

cat >> "$OUTPUT" <<EOF
\`\`\`

---

## Docker & Infrastructure

### Docker configs (\`docker/\`)

EOF

find "$DOCKER" -type f 2>/dev/null | sed "s|$DOCKER/||" | sort | sed 's/^/- `/' | sed 's/$/`/' >> "$OUTPUT"

cat >> "$OUTPUT" <<EOF

### Scripts (\`scripts/\`)

EOF

find "$SCRIPTS" -type f 2>/dev/null | sed "s|$SCRIPTS/||" | sort | sed 's/^/- `/' | sed 's/$/`/' >> "$OUTPUT"

cat >> "$OUTPUT" <<EOF

---

## OpenSpec

| Category | Count |
|----------|------:|
| Total spec files | $OPENSPEC_TOTAL |
| Entity specs | $OPENSPEC_ENTITIES |
| Business rules | $OPENSPEC_RULES |
| API contracts | $OPENSPEC_CONTRACTS |

### Spec files

EOF

find "$OPENSPEC" -type f \( -name "*.yaml" -o -name "*.yml" -o -name "*.md" \) 2>/dev/null | sed "s|$OPENSPEC/||" | sort | sed 's/^/- `/' | sed 's/$/`/' >> "$OUTPUT"

cat >> "$OUTPUT" <<EOF

---

## Deep Reference

| Topic | Document |
|-------|----------|
| Entity details, relations, traits | \`docs/knowledge/domain-model.md\` |
| Full feature inventory | \`docs/FEATURES.md\` |
| Architecture, bounded contexts | \`docs/knowledge/architecture-ddd.md\` |
| API endpoints, controllers | \`docs/knowledge/api-surface.md\` |
| Design patterns in use | \`docs/knowledge/design-patterns.md\` |
| All knowledge modules | \`docs/knowledge/index.md\` |
| Deployment, Docker, Railway | \`docs/knowledge/deployment.md\` |
| GPS tracking, Traccar | \`docs/knowledge/gps-tracking.md\` |
| Route optimization, VROOM/OSRM | \`docs/knowledge/route-optimization.md\` |
EOF

echo ""
echo "✓ Manifest generated: $OUTPUT"
echo "  Backend: $BACKEND_TOTAL PHP ($ENTITIES entities, $TOTAL_ENUMS enums, $CONTROLLERS controllers, $TESTS_COUNT tests)"
echo "  Frontend: $FRONTEND_TOTAL JS/TS | ML: $ML_PYTHON Python | Docker: $DOCKER_FILES configs | OpenSpec: $OPENSPEC_TOTAL specs"
