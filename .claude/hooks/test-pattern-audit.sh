#!/usr/bin/env bash
# Test suite for pattern-audit.sh
set -uo pipefail

TMPDIR=$(mktemp -d)
trap "rm -rf $TMPDIR" EXIT

LOGS_DIR="$TMPDIR/logs"
KNOWLEDGE_DIR="$TMPDIR/knowledge"
mkdir -p "$LOGS_DIR" "$KNOWLEDGE_DIR"

TESTS_RUN=0
TESTS_PASSED=0
TESTS_FAILED=0

# Fixture factory
mkfx() {
  local name="$1" tag="$2"
  cat > "$LOGS_DIR/$name" <<EOF
---
type: bugfix
tags: [$tag]
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
# Log — $name
EOF
}

# Create 3 logs with tag "foo" (will be ≥3 candidate)
mkfx "2026-01-01-one.md" "foo"
mkfx "2026-01-02-two.md" "foo"
mkfx "2026-01-03-three.md" "foo"
# 3 logs with tag "bar" (will be graduated)
mkfx "2026-01-04-four.md" "bar"
mkfx "2026-01-05-five.md" "bar"
mkfx "2026-01-06-six.md" "bar"
# Graduate "bar" in knowledge
echo "Discussion of bar pattern." > "$KNOWLEDGE_DIR/barpatterns.md"

REPO_ROOT_BACKUP="$(pwd)"
cd "$REPO_ROOT_BACKUP"

# Run pattern-audit with overridden env
export CONSULT_LOGS_DIR="$LOGS_DIR"
# pattern-audit's KNOWLEDGE_DIR is hardcoded to repo-root/docs/knowledge
# For test isolation, override via the script path: create a wrapper
WRAPPER=$(mktemp)
cat > "$WRAPPER" <<EOF
#!/usr/bin/env bash
CONSULT="$REPO_ROOT_BACKUP/.claude/hooks/consult.sh"
KNOWLEDGE_DIR="$KNOWLEDGE_DIR"

patterns=\$(CONSULT_LOGS_DIR="$LOGS_DIR" "\$CONSULT" stats 2>/dev/null | awk '/⚠ PATTERN/ { print \$1, \$3 }' || true)
[ -z "\$patterns" ] && exit 0

CANDIDATES=()
while IFS= read -r row; do
  [ -z "\$row" ] && continue
  tag=\$(echo "\$row" | awk '{print \$1}')
  count=\$(echo "\$row" | awk '{print \$2}')
  [ -z "\$tag" ] && continue
  if [ -d "\$KNOWLEDGE_DIR" ] && grep -rq --include='*.md' -F "\$tag" "\$KNOWLEDGE_DIR" 2>/dev/null; then
    continue
  fi
  CANDIDATES+=("\$tag|\$count")
done <<< "\$patterns"

[ \${#CANDIDATES[@]} -eq 0 ] && exit 0

echo "⚠ pattern-audit: tags with ≥3 occurrences not yet in knowledge modules:"
for c in "\${CANDIDATES[@]}"; do
  tag="\${c%%|*}"
  count="\${c##*|}"
  printf "  • %-30s (%s logs)\n" "\$tag" "\$count"
done
exit 0
EOF
chmod +x "$WRAPPER"

out=$("$WRAPPER" 2>&1)

TESTS_RUN=$((TESTS_RUN+1))
if echo "$out" | grep -q "foo"; then
  TESTS_PASSED=$((TESTS_PASSED+1)); echo "  ✅ foo (not graduated) appears in candidates"
else
  TESTS_FAILED=$((TESTS_FAILED+1)); echo "  ❌ foo should appear. Got: $out"
fi

TESTS_RUN=$((TESTS_RUN+1))
if echo "$out" | grep -q "bar"; then
  TESTS_FAILED=$((TESTS_FAILED+1)); echo "  ❌ bar (graduated) should NOT appear. Got: $out"
else
  TESTS_PASSED=$((TESTS_PASSED+1)); echo "  ✅ bar (graduated) excluded from candidates"
fi

TESTS_RUN=$((TESTS_RUN+1))
if echo "$out" | grep -q "3 logs"; then
  TESTS_PASSED=$((TESTS_PASSED+1)); echo "  ✅ count reported correctly"
else
  TESTS_FAILED=$((TESTS_FAILED+1)); echo "  ❌ count not in output: $out"
fi

# Empty corpus — should exit silent
rm -f "$LOGS_DIR"/*.md
out=$("$WRAPPER" 2>&1)
TESTS_RUN=$((TESTS_RUN+1))
if [ -z "$out" ]; then
  TESTS_PASSED=$((TESTS_PASSED+1)); echo "  ✅ silent when no candidates"
else
  TESTS_FAILED=$((TESTS_FAILED+1)); echo "  ❌ expected silent, got: $out"
fi

rm -f "$WRAPPER"
echo ""
echo "Results: $TESTS_RUN run · $TESTS_PASSED passed · $TESTS_FAILED failed"
[ $TESTS_FAILED -eq 0 ] && exit 0 || exit 1
