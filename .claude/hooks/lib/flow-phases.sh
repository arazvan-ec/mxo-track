#!/usr/bin/env bash
# flow-phases.sh — single source of truth for legal phase sequences.
#
# Usage: source "$REPO/.claude/hooks/lib/flow-phases.sh"
#
# Consumers that need per-flow phase lists (phase-advance, status-lines)
# source this file to avoid drift. Phase-advance is the operative SoT for
# legal transitions; this file reflects its definitions.
#
# Note: bash arrays don't export across process boundaries — consumers must
# source, not invoke.

FULL_PHASES=(consult brainstorming planning implementation verification capture retrospective finalize)
DEBUG_PHASES=(root_cause pattern_wide fix verification capture retrospective finalize)
AGENT_PHASES=(implementation verification)

# Short display labels (parallel to *_PHASES arrays)
FULL_PHASES_SHORT=(consult brainstorm planning impl verify capture retro finalize)
DEBUG_PHASES_SHORT=(root_cause pattern_wide fix verify capture retro finalize)
AGENT_PHASES_SHORT=(impl verify)
