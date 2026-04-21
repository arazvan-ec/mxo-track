#!/usr/bin/env bash
# consult.sh — query execution logs by frontmatter (tags, files, patterns, outcome, type)
#
# Reads YAML frontmatter from docs/superpowers/execution-logs/*.md and filters.
# Override corpus via CONSULT_LOGS_DIR env var (used by tests).
#
# Output format (universal): date | type | outcome | filename | title
# Exit codes: 0=results, 1=valid query no results, 2=error

set -uo pipefail

DEFAULT_DIR="docs/superpowers/execution-logs"
LOGS_DIR="${CONSULT_LOGS_DIR:-$DEFAULT_DIR}"
QUIET=0

usage() {
  cat <<EOF
Usage: consult.sh [--quiet] <subcommand> [args]

Subcommands:
  tag <tag>              logs with this tag
  file <path>            logs that touched this exact path
  file-glob <pattern>    logs touching files matching glob (e.g. 'src/*.tsx')
  pattern <name>         logs with this pattern
  type <type>            logs of this type (feature|bugfix|refactor|docs|process)
  recent [N]             N most recent logs (default 10)
  by-outcome <outcome>   success|partial|reverted|null
  stats                  tag frequency + 3+ pattern alerts
  show <filename>        print frontmatter of a specific log
  unverified             success logs with null outcome_verified_at

Env:
  CONSULT_LOGS_DIR       override corpus directory (default: $DEFAULT_DIR)

Output format: date | type | outcome | filename | title
EOF
}

# Extract frontmatter block between first two --- markers.
# Emits blank if no frontmatter.
extract_fm() {
  awk '
    /^---[[:space:]]*$/ {
      c++
      if (c==1) next
      if (c==2) exit
    }
    c==1 { print }
  ' "$1"
}

# Extract field value from frontmatter block (stdin or file arg).
# Arrays returned as inline "[a, b]" literal; caller can strip brackets.
fm_get() {
  local key="$1"
  awk -v k="$key" '
    $0 ~ "^"k":" {
      sub("^"k":[[:space:]]*", "")
      print
      exit
    }
  '
}

# Normalize array-like field `[a, b, c]` → `a b c` (space-separated, for grep -w).
normalize_array() {
  local val="$1"
  # Strip brackets, commas → spaces, squeeze whitespace
  val="${val#[}"
  val="${val%]}"
  val="${val//,/ }"
  echo "$val" | awk '{$1=$1; print}'
}

# Extract title (first # heading in body, after frontmatter).
extract_title() {
  awk '
    /^---[[:space:]]*$/ {
      if (!seen_end) { c++; if (c==2) seen_end=1; next }
    }
    seen_end && /^#[[:space:]]/ {
      sub(/^#[[:space:]]*/, "")
      print
      exit
    }
    # Handle logs without frontmatter: print first heading
    !c && /^#[[:space:]]/ {
      sub(/^#[[:space:]]*/, "")
      print
      exit
    }
  ' "$1"
}

# Emit normalized index line per log with frontmatter.
# Columns (pipe-separated):
#   1: date (YYYY-MM-DD from filename)
#   2: type
#   3: outcome (- if null/empty)
#   4: filename
#   5: tags (space-separated, no brackets)
#   6: files_touched (space-separated, no brackets)
#   7: patterns (space-separated, no brackets)
#   8: outcome_verified_at (- if null)
#   9: title
emit_index() {
  local log fm filename date type tags files patterns outcome verified title
  shopt -s nullglob
  for log in "$LOGS_DIR"/*.md; do
    [ -f "$log" ] || continue
    filename=$(basename "$log")
    date="${filename:0:10}"
    # Skip logs without valid YYYY-MM-DD prefix
    [[ "$date" =~ ^[0-9]{4}-[0-9]{2}-[0-9]{2}$ ]] || continue
    fm=$(extract_fm "$log")
    # Skip logs without frontmatter
    [ -z "$fm" ] && continue
    type=$(echo "$fm" | fm_get type)
    tags=$(normalize_array "$(echo "$fm" | fm_get tags)")
    files=$(normalize_array "$(echo "$fm" | fm_get files_touched)")
    patterns=$(normalize_array "$(echo "$fm" | fm_get patterns)")
    outcome=$(echo "$fm" | fm_get outcome)
    verified=$(echo "$fm" | fm_get outcome_verified_at)
    [ -z "$outcome" ] || [ "$outcome" = "null" ] && outcome="-"
    [ -z "$verified" ] || [ "$verified" = "null" ] && verified="-"
    title=$(extract_title "$log")
    printf '%s|%s|%s|%s|%s|%s|%s|%s|%s\n' \
      "$date" "$type" "$outcome" "$filename" "$tags" "$files" "$patterns" "$verified" "$title"
  done
  shopt -u nullglob
}

# Format output: hide internal columns (5-8), show date|type|outcome|filename|title
format_rows() {
  awk -F'|' '{ printf "%s | %s | %s | %s | %s\n", $1, $2, $3, $4, $9 }'
}

header() {
  [ "$QUIET" = "1" ] && return 0
  echo "=== $1 ==="
}

# ── Subcommand implementations ──

cmd_tag() {
  local tag="${1:-}"; [ -z "$tag" ] && { echo "ERROR: tag <tag> requires argument" >&2; exit 2; }
  header "Logs with tag: $tag"
  local rows
  rows=$(emit_index | awk -F'|' -v t="$tag" '{
    n=split($5, arr, " ")
    for (i=1; i<=n; i++) if (arr[i]==t) { print; next }
  }' | format_rows)
  [ -z "$rows" ] && return 1
  echo "$rows"
  return 0
}

cmd_file() {
  local path="${1:-}"; [ -z "$path" ] && { echo "ERROR: file <path> requires argument" >&2; exit 2; }
  header "Logs touching: $path"
  local rows
  rows=$(emit_index | awk -F'|' -v p="$path" '{
    n=split($6, arr, " ")
    for (i=1; i<=n; i++) if (arr[i]==p) { print; next }
  }' | format_rows)
  [ -z "$rows" ] && return 1
  echo "$rows"
  return 0
}

cmd_file_glob() {
  local glob="${1:-}"; [ -z "$glob" ] && { echo "ERROR: file-glob <pattern> requires argument" >&2; exit 2; }
  header "Logs touching files matching: $glob"
  local rows
  # Convert glob to regex: * → [^/]*, ? → ., escape dots
  local regex
  regex=$(echo "$glob" | sed -e 's/\./\\./g' -e 's/\*/[^ ]*/g' -e 's/?/./g')
  regex="^${regex}$"
  rows=$(emit_index | awk -F'|' -v re="$regex" '{
    n=split($6, arr, " ")
    for (i=1; i<=n; i++) if (arr[i] ~ re) { print; next }
  }' | format_rows)
  [ -z "$rows" ] && return 1
  echo "$rows"
  return 0
}

cmd_pattern() {
  local p="${1:-}"; [ -z "$p" ] && { echo "ERROR: pattern <name> requires argument" >&2; exit 2; }
  header "Logs with pattern: $p"
  local rows
  rows=$(emit_index | awk -F'|' -v t="$p" '{
    n=split($7, arr, " ")
    for (i=1; i<=n; i++) if (arr[i]==t) { print; next }
  }' | format_rows)
  [ -z "$rows" ] && return 1
  echo "$rows"
  return 0
}

cmd_type() {
  local t="${1:-}"; [ -z "$t" ] && { echo "ERROR: type <type> requires argument" >&2; exit 2; }
  header "Logs of type: $t"
  local rows
  rows=$(emit_index | awk -F'|' -v t="$t" '$2==t { print }' | format_rows)
  [ -z "$rows" ] && return 1
  echo "$rows"
  return 0
}

cmd_recent() {
  local n="${1:-10}"
  header "Most recent $n logs"
  local rows
  rows=$(emit_index | sort -r | head -n "$n" | format_rows)
  [ -z "$rows" ] && return 1
  echo "$rows"
  return 0
}

cmd_by_outcome() {
  local o="${1:-}"; [ -z "$o" ] && { echo "ERROR: by-outcome <outcome> requires argument" >&2; exit 2; }
  header "Logs with outcome: $o"
  local target="$o"
  [ "$o" = "null" ] && target="-"
  local rows
  rows=$(emit_index | awk -F'|' -v t="$target" '$3==t { print }' | format_rows)
  [ -z "$rows" ] && return 1
  echo "$rows"
  return 0
}

cmd_stats() {
  header "Tag frequency"
  local all_tags
  all_tags=$(emit_index | awk -F'|' '{ print $5 }' | tr ' ' '\n' | grep -v '^$' | sort | uniq -c | sort -rn)
  if [ -z "$all_tags" ]; then
    echo "(no tagged logs)"
    return 1
  fi
  echo "$all_tags" | awk '{
    count=$1; tag=$2
    if (count >= 3) printf "  %-30s : %d logs ⚠ PATTERN (≥3)\n", tag, count
    else printf "  %-30s : %d logs\n", tag, count
  }'
  header "Pattern frequency"
  local all_patterns
  all_patterns=$(emit_index | awk -F'|' '{ print $7 }' | tr ' ' '\n' | grep -v '^$' | sort | uniq -c | sort -rn)
  if [ -n "$all_patterns" ]; then
    echo "$all_patterns" | awk '{
      count=$1; p=$2
      if (count >= 3) printf "  %-30s : %d logs ⚠ PATTERN (≥3)\n", p, count
      else printf "  %-30s : %d logs\n", p, count
    }'
  fi
  return 0
}

cmd_show() {
  local filename="${1:-}"; [ -z "$filename" ] && { echo "ERROR: show <filename> requires argument" >&2; exit 2; }
  local path="$LOGS_DIR/$filename"
  if [ ! -f "$path" ]; then
    echo "ERROR: log not found: $path" >&2
    exit 2
  fi
  header "Frontmatter: $filename"
  extract_fm "$path"
  return 0
}

cmd_unverified() {
  header "Success logs with null outcome_verified_at"
  local rows
  rows=$(emit_index | awk -F'|' '$3=="success" && $8=="-" { print }' | format_rows)
  [ -z "$rows" ] && return 1
  echo "$rows"
  return 0
}

# ── Arg parsing ──

if [ "${1:-}" = "--quiet" ]; then
  QUIET=1
  shift
fi

SUBCMD="${1:-}"
shift || true

case "$SUBCMD" in
  tag) cmd_tag "$@" ;;
  file) cmd_file "$@" ;;
  file-glob) cmd_file_glob "$@" ;;
  pattern) cmd_pattern "$@" ;;
  type) cmd_type "$@" ;;
  recent) cmd_recent "$@" ;;
  by-outcome) cmd_by_outcome "$@" ;;
  stats) cmd_stats "$@" ;;
  show) cmd_show "$@" ;;
  unverified) cmd_unverified "$@" ;;
  -h|--help|help|"")
    usage
    [ -z "$SUBCMD" ] && exit 2
    exit 0
    ;;
  *)
    echo "ERROR: unknown subcommand: $SUBCMD" >&2
    usage >&2
    exit 2
    ;;
esac
