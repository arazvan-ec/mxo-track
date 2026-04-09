#!/usr/bin/env bash
# Shared file classification for workflow gates.
# Single source of truth for which paths are "code", "test", etc.
#
# Usage: source this file, then call classify_file "$path"
# Expects absolute or */prefixed paths (workflow-engine) or
# relative paths prepended with $REPO/ (pre-push-gate).

classify_file() {
  case "$1" in
    */backend/src/*|*/frontend/src/*|*/backend/templates/*|*/backend/config/*|*/backend/migrations/*|*/backend/assets/*|*/ml-service/*|*/docker/*|*/scripts/*|*/openspec/*)
                                                     echo "code" ;;
    */backend/tests/*|*/frontend/tests/*)             echo "test" ;;
    */docs/superpowers/specs/*)                       echo "spec" ;;
    */docs/superpowers/plans/*)                       echo "plan" ;;
    */docs/superpowers/execution-logs/*)              echo "execution-log" ;;
    */docs/decisions/*)                               echo "decision" ;;
    */docs/knowledge/*|*/docs/FEATURES.md|*/docs/codebase-manifest.md) echo "docs" ;;
    */CLAUDE.md|*/AGENTS.md)                         echo "config" ;;
    *)                                                echo "other" ;;
  esac
}

# Valid flow types — used for validation
VALID_FLOW_TYPES="micro light debug full explore"

is_valid_flow_type() {
  local flow="$1"
  case "$flow" in
    micro|micro-flow|light|light-flow|debug|debug-flow|full|full-flow|explore|explore-flow)
      return 0 ;;
    *)
      return 1 ;;
  esac
}
