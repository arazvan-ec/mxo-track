#!/usr/bin/env bash
# Test suite for suggest-tags.sh
set -uo pipefail

TMPDIR=$(mktemp -d)
trap "rm -rf $TMPDIR" EXIT

LOGS_DIR="$TMPDIR/logs"
mkdir -p "$LOGS_DIR"

TESTS_RUN=0
TESTS_PASSED=0
TESTS_FAILED=0

mkfx() {
  local name="$1" tags="$2"
  cat > "$LOGS_DIR/$name" <<EOF
---
type: bugfix
tags: $tags
files_touched: []
patterns: []
outcome: success
outcome_verified_at: null
regressions_later: []
pr_number: null
estimated_lines: null
actual_lines: null
duration_minutes: null
consulted_in_future: []
---
# Log
EOF
}

ST="$(pwd)/scripts/suggest-tags.sh"
export SUGGEST_TAGS_LOGS_DIR="$LOGS_DIR"

check_exit() {
  local desc="$1" expected="$2" actual="$3"
  TESTS_RUN=$((TESTS_RUN+1))
  if [ "$expected" = "$actual" ]; then
    TESTS_PASSED=$((TESTS_PASSED+1)); echo "  ✅ $desc"
  else
    TESTS_FAILED=$((TESTS_FAILED+1)); echo "  ❌ $desc (expected $expected, got $actual)"
  fi
}

check_tags_contain() {
  local file="$1" expected="$2" desc="$3"
  local got
  got=$(awk '/^---[[:space:]]*$/{c++; if(c==2)exit; next} c==1 && /^tags:/{sub(/^tags:[[:space:]]*/,""); print; exit}' "$file")
  TESTS_RUN=$((TESTS_RUN+1))
  if echo "$got" | grep -qF "$expected"; then
    TESTS_PASSED=$((TESTS_PASSED+1)); echo "  ✅ $desc (got: $got)"
  else
    TESTS_FAILED=$((TESTS_FAILED+1)); echo "  ❌ $desc (expected '$expected', got: $got)"
  fi
}

check_tags_NOT_contain() {
  local file="$1" unexpected="$2" desc="$3"
  local got
  got=$(awk '/^---[[:space:]]*$/{c++; if(c==2)exit; next} c==1 && /^tags:/{sub(/^tags:[[:space:]]*/,""); print; exit}' "$file")
  TESTS_RUN=$((TESTS_RUN+1))
  if echo "$got" | grep -qF "$unexpected"; then
    TESTS_FAILED=$((TESTS_FAILED+1)); echo "  ❌ $desc (unexpected '$unexpected' in: $got)"
  else
    TESTS_PASSED=$((TESTS_PASSED+1)); echo "  ✅ $desc"
  fi
}

# Case 1: dry-run with empty tags — should suggest glass + sidebar
mkfx "2026-01-01-glass-sidebar-fix.md" "[]"
out=$("$ST" --dry-run 2>&1); rc=$?
check_exit "dry-run exit 0" 0 $rc
# dry-run does NOT modify the file
check_tags_contain "$LOGS_DIR/2026-01-01-glass-sidebar-fix.md" "[]" "dry-run leaves file unchanged"
TESTS_RUN=$((TESTS_RUN+1))
if echo "$out" | grep -q "glass-overlay"; then
  TESTS_PASSED=$((TESTS_PASSED+1)); echo "  ✅ dry-run suggests glass-overlay"
else
  TESTS_FAILED=$((TESTS_FAILED+1)); echo "  ❌ dry-run did not suggest glass-overlay. Got: $out"
fi

# Case 2: apply — file is written
"$ST" --apply >/dev/null 2>&1; rc=$?
check_exit "apply exit 0" 0 $rc
check_tags_contain "$LOGS_DIR/2026-01-01-glass-sidebar-fix.md" "glass-overlay" "apply added glass-overlay"
check_tags_contain "$LOGS_DIR/2026-01-01-glass-sidebar-fix.md" "sidebar" "apply added sidebar"

# Case 3: idempotence — re-apply does not duplicate
"$ST" --apply >/dev/null 2>&1
count=$(awk '/^---[[:space:]]*$/{c++; if(c==2)exit; next} c==1 && /^tags:/{print; exit}' "$LOGS_DIR/2026-01-01-glass-sidebar-fix.md" | grep -oF "glass-overlay" | wc -l)
TESTS_RUN=$((TESTS_RUN+1))
if [ "$count" = "1" ]; then
  TESTS_PASSED=$((TESTS_PASSED+1)); echo "  ✅ re-apply does not duplicate tags"
else
  TESTS_FAILED=$((TESTS_FAILED+1)); echo "  ❌ tags duplicated (count=$count)"
fi

# Case 4: existing tags are preserved
rm -f "$LOGS_DIR"/*.md
mkfx "2026-01-02-route-stop-reorder.md" "[manual-tag, existing]"
"$ST" --apply >/dev/null 2>&1
check_tags_contain "$LOGS_DIR/2026-01-02-route-stop-reorder.md" "manual-tag" "preserves manual-tag"
check_tags_contain "$LOGS_DIR/2026-01-02-route-stop-reorder.md" "existing" "preserves existing"
check_tags_contain "$LOGS_DIR/2026-01-02-route-stop-reorder.md" "route" "adds route"
check_tags_contain "$LOGS_DIR/2026-01-02-route-stop-reorder.md" "stop" "adds stop"

# Case 5: filename with no keywords → no suggestions
rm -f "$LOGS_DIR"/*.md
mkfx "2026-01-03-zzz-obscure-feature.md" "[]"
"$ST" --apply >/dev/null 2>&1
# tags should still be []
got=$(awk '/^---[[:space:]]*$/{c++; if(c==2)exit; next} c==1 && /^tags:/{print; exit}' "$LOGS_DIR/2026-01-03-zzz-obscure-feature.md")
TESTS_RUN=$((TESTS_RUN+1))
if echo "$got" | grep -qF "[]"; then
  TESTS_PASSED=$((TESTS_PASSED+1)); echo "  ✅ no keywords → empty tags preserved"
else
  TESTS_FAILED=$((TESTS_FAILED+1)); echo "  ❌ expected empty tags, got: $got"
fi

# Case 6: invalid flag → exit 2
"$ST" --bogus >/dev/null 2>&1; rc=$?
check_exit "invalid flag → exit 2" 2 $rc

# Case 7: log without frontmatter is skipped
rm -f "$LOGS_DIR"/*.md
echo "# No frontmatter" > "$LOGS_DIR/2026-01-04-widget-no-fm.md"
"$ST" --apply >/dev/null 2>&1
TESTS_RUN=$((TESTS_RUN+1))
if ! grep -q "^---" "$LOGS_DIR/2026-01-04-widget-no-fm.md"; then
  TESTS_PASSED=$((TESTS_PASSED+1)); echo "  ✅ log without frontmatter is skipped"
else
  TESTS_FAILED=$((TESTS_FAILED+1)); echo "  ❌ log without frontmatter was modified"
fi

# Case 8: custom registry via env var
FIX_REG="$TMPDIR/custom.yaml"
cat > "$FIX_REG" <<'EOF'
tags: {}
patterns: {}
keyword_mappings:
  unique: unique-tag
EOF
rm -f "$LOGS_DIR"/*.md
mkfx "2026-01-05-unique-case.md" "[]"
SUGGEST_TAGS_REGISTRY="$FIX_REG" "$ST" --apply >/dev/null 2>&1
check_tags_contain "$LOGS_DIR/2026-01-05-unique-case.md" "unique-tag" "custom registry keyword applied"

# Case 9: missing registry → exit 2
rm -f "$LOGS_DIR"/*.md
SUGGEST_TAGS_REGISTRY="/nonexistent/reg.yaml" "$ST" --apply >/dev/null 2>&1; rc=$?
check_exit "missing registry → exit 2" 2 $rc

# Case 10: empty keyword_mappings → exit 2
EMPTY_REG="$TMPDIR/empty.yaml"
cat > "$EMPTY_REG" <<'EOF'
tags: {}
patterns: {}
keyword_mappings: {}
EOF
SUGGEST_TAGS_REGISTRY="$EMPTY_REG" "$ST" --apply >/dev/null 2>&1; rc=$?
check_exit "empty keyword_mappings → exit 2" 2 $rc

echo ""
echo "Results: $TESTS_RUN run · $TESTS_PASSED passed · $TESTS_FAILED failed"
[ $TESTS_FAILED -eq 0 ] && exit 0 || exit 1
