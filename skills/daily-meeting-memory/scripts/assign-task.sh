#!/usr/bin/env bash
# Assign a task to agent memory for tracking
# Usage: ./assign-task.sh <agent_id> "<task_description>" [importance]
#
# Example:
#   ./assign-task.sh 11 "Draft API spec by Friday" 9

set -e

AGENT_ID=${1:-11}
TASK=${2:-""}
IMPORTANCE=${3:-8}

if [ -z "$TASK" ]; then
    echo "Usage: ./assign-task.sh <agent_id> \"<task_description>\" [importance]"
    echo ""
    echo "Example:"
    echo "  ./assign-task.sh 11 \"Draft API spec by Friday\" 9"
    exit 1
fi

echo "=== Assigning Task ==="
echo "Agent: $AGENT_ID"
echo "Task: $TASK"
echo "Importance: $IMPORTANCE/10"
echo ""

iris sdk:call memory.store agent_id=$AGENT_ID type=fact content="TODO: $TASK" topic=tasks importance=$IMPORTANCE

echo ""
echo "Task assigned successfully."
