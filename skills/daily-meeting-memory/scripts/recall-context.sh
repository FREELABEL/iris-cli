#!/usr/bin/env bash
# Recall recent project context from agent memory
# Usage: ./recall-context.sh <agent_id> ["<search_query>"]
#
# Example:
#   ./recall-context.sh 11 "project status"

set -e

AGENT_ID=${1:-11}
QUERY=${2:-"project status standup"}

echo "=== Recalling Context for Agent $AGENT_ID ==="
echo "Query: $QUERY"
echo ""

# Search memories
iris sdk:call memory.search agent_id=$AGENT_ID query="$QUERY"
echo ""

# Also show recent tasks
echo "--- Active Tasks ---"
iris sdk:call memory.list agent_id=$AGENT_ID topic=tasks memory_type=fact min_importance=7 2>/dev/null || echo "No active tasks found."
