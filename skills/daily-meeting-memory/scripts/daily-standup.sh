#!/usr/bin/env bash
# Daily Standup Memory Flow
# Usage: ./daily-standup.sh <agent_id> "<meeting_notes>" ["<task_assignments>"]
#
# Example:
#   ./daily-standup.sh 11 "Discussed API redesign, team agreed on REST" "Draft API spec by Friday"

set -e

AGENT_ID=${1:-11}
NOTES=${2:-""}
TASKS=${3:-""}

if [ -z "$NOTES" ]; then
    echo "Usage: ./daily-standup.sh <agent_id> \"<meeting_notes>\" [\"<task>\"]"
    echo ""
    echo "Example:"
    echo "  ./daily-standup.sh 11 \"Discussed API redesign\" \"Draft spec by Friday\""
    exit 1
fi

echo "=== Daily Standup Memory Flow ==="
echo "Agent: $AGENT_ID"
echo ""

# Step 1: Recall previous context
echo "--- Recalling previous context ---"
iris sdk:call memory.search agent_id=$AGENT_ID query="standup project status" 2>/dev/null || echo "No previous context found."
echo ""

# Step 2: Store today's meeting notes
echo "--- Storing today's notes ---"
iris sdk:call memory.store agent_id=$AGENT_ID type=context content="$NOTES" topic=daily_standup importance=7
echo ""

# Step 3: Store tasks if provided
if [ -n "$TASKS" ]; then
    echo "--- Assigning task ---"
    iris sdk:call memory.store agent_id=$AGENT_ID type=fact content="TODO: $TASKS" topic=tasks importance=9
    echo ""
fi

echo "=== Done ==="
