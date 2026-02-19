<?php
/**
 * Example: Creating an Agent with Integration Validation
 * 
 * This example demonstrates the recommended pattern for creating agents
 * that depend on third-party integrations (Gmail, Google Drive, etc.)
 * 
 * Key improvements:
 * 1. Connection validation before agent creation
 * 2. Test integration to ensure credentials work
 * 3. Secure environment variable usage
 * 4. Clear error messages
 */

require __DIR__ . '/vendor/autoload.php';

use IRIS\SDK\IRIS;
use IRIS\SDK\Resources\Agents\AgentConfig;
use Dotenv\Dotenv;

// Load credentials from .env (SECURE)
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$iris = new IRIS([
    'api_key' => $_ENV['IRIS_API_KEY'], // From .env, not hardcoded
    'user_id' => (int) $_ENV['IRIS_USER_ID'],
]);

echo "==============================================\n";
echo "Creating Agent with Gmail Integration\n";
echo "==============================================\n\n";

// STEP 1: Validate Gmail Connection
echo "Step 1: Checking Gmail connection...\n";

$status = $iris->integrations->status('gmail');

if (!$status->isConnected()) {
    echo "❌ Error: {$status->getStatusMessage()}\n";
    echo "\n";
    echo "To connect Gmail:\n";
    echo "  ./bin/iris integrations connect gmail\n";
    echo "\n";
    exit(1);
}

echo "✅ Gmail is connected (Integration ID: {$status->getIntegrationId()})\n\n";

// STEP 2: Test the Connection
echo "Step 2: Testing Gmail integration...\n";

$test = $iris->integrations->testByType('gmail');

if (!$test->success) {
    echo "❌ Gmail connection test failed: {$test->error}\n";
    echo "\n";
    echo "Possible causes:\n";
    echo "  - OAuth token expired\n";
    echo "  - Missing permissions/scopes\n";
    echo "  - API quota exceeded\n";
    echo "\n";
    echo "To reconnect:\n";
    echo "  ./bin/iris integrations disconnect gmail\n";
    echo "  ./bin/iris integrations connect gmail\n";
    echo "\n";
    exit(1);
}

echo "✅ Gmail connection test passed";
if ($test->latencyMs) {
    echo " ({$test->latencyMs}ms)";
}
echo "\n\n";

// STEP 3: Create the Agent
echo "Step 3: Creating agent...\n";

$agent = $iris->agents->createFromArray([
    'name' => 'Email Assistant',
    'prompt' => 'You are a helpful email assistant that monitors my inbox and helps me stay organized.

**Your Tasks:**
1. Check for urgent emails and flag them
2. For urgent emails, draft a reply and include it in your summary report (DO NOT send it directly)
3. Organize unread emails by priority
4. Summarize key action items

**Important:**
- NEVER use the "send_email" function without explicit user approval
- For draft replies, include the full text in your report for manual review
- Always provide context in your summaries

Report back with:
- Number of urgent emails
- Draft replies (full text for review)
- Action items requiring attention',
    'model' => 'gpt-4o-mini',
    'settings' => [
        'agentIntegrations' => [
            'gmail' => true,
        ],
        'enabledFunctions' => [
            'searchEmails' => true,
            'readEmail' => true,
            // Note: send_email is available but agent is instructed not to use it
        ],
    ],
]);

echo "✅ Agent created successfully!\n\n";
echo "Agent ID: {$agent->id}\n";
echo "Name: {$agent->name}\n";
echo "URL: https://app.heyiris.io/agent/simple/{$agent->id}\n";
echo "\n";

echo "==============================================\n";
echo "✅ AGENT CREATED WITH VALIDATED INTEGRATION\n";
echo "==============================================\n\n";

echo "Next steps:\n";
echo "1. Test the agent: ./bin/iris chat {$agent->id} \"Check my inbox\"\n";
echo "2. View in dashboard: https://app.heyiris.io/agent/simple/{$agent->id}\n";
echo "\n";

echo "Safety Features:\n";
echo "  ✅ Integration validated before creation\n";
echo "  ✅ Connection tested for validity\n";
echo "  ✅ Agent configured to NOT send emails automatically\n";
echo "  ✅ Draft replies included in reports for manual review\n";
echo "\n";
