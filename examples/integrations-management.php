<?php
/**
 * Integration Management - SDK & CLI Usage Examples
 * 
 * This file demonstrates how to use the new integration management features
 * in both SDK (programmatic) and CLI (command-line) modes.
 */

require_once __DIR__ . '/vendor/autoload.php';

use IRIS\SDK\IRIS;

echo "===========================================\n";
echo "Integration Management - SDK Examples\n";
echo "===========================================\n\n";

// Initialize SDK
$iris = new IRIS([
    'api_key' => getenv('IRIS_API_KEY') ?: 'your-api-key',
    'user_id' => (int)(getenv('IRIS_USER_ID') ?: 1),
]);

// Example 1: List all integrations
echo "1️⃣  List all integrations:\n";
echo "-------------------------------------------\n";
try {
    $integrations = $iris->integrations->list();
    
    if ($integrations->isEmpty()) {
        echo "   No integrations connected yet.\n";
    } else {
        foreach ($integrations as $integration) {
            $status = $integration->status === 'active' ? '✓' : '✗';
            echo "   {$status} {$integration->name} ({$integration->type})\n";
        }
    }
} catch (Exception $e) {
    echo "   Error: {$e->getMessage()}\n";
}
echo "\n";

// Example 2: Check integration status
echo "2️⃣  Check Vapi connection status:\n";
echo "-------------------------------------------\n";
try {
    $status = $iris->integrations->status('vapi');
    
    if ($status['connected']) {
        echo "   ✓ Vapi is connected\n";
        echo "   Integration ID: {$status['integration']->id}\n";
    } else {
        echo "   ✗ Vapi is not connected\n";
    }
} catch (Exception $e) {
    echo "   Error: {$e->getMessage()}\n";
}
echo "\n";

// Example 3: Connect Vapi (commented out to avoid accidental execution)
echo "3️⃣  Connect Vapi:\n";
echo "-------------------------------------------\n";
echo "   // Uncomment to test:\n";
echo "   // \$integration = \$iris->integrations->connectVapi('vapi_key_xxx', '+15551234567');\n";
echo "   // echo \"Connected! ID: {\$integration->id}\\n\";\n";
echo "\n";

// Example 4: Connect Servis.ai (commented out)
echo "4️⃣  Connect Servis.ai:\n";
echo "-------------------------------------------\n";
echo "   // Uncomment to test:\n";
echo "   // \$integration = \$iris->integrations->connectServisAi('client_id', 'client_secret');\n";
echo "   // echo \"Connected! ID: {\$integration->id}\\n\";\n";
echo "\n";

// Example 5: Connect SMTP Email (commented out)
echo "5️⃣  Connect SMTP Email:\n";
echo "-------------------------------------------\n";
echo "   // Uncomment to test:\n";
echo "   // \$integration = \$iris->integrations->connectSmtp(\n";
echo "   //     'smtp.gmail.com', 587, 'user@gmail.com', 'password',\n";
echo "   //     'from@example.com', 'My Name', 'tls'\n";
echo "   // );\n";
echo "   // echo \"Connected! ID: {\$integration->id}\\n\";\n";
echo "\n";

// Example 6: Get all available types
echo "6️⃣  Get available integration types:\n";
echo "-------------------------------------------\n";
try {
    $response = $iris->integrations->types();
    $types = $response['data'] ?? $response;
    
    $count = 0;
    foreach ($types as $typeKey => $typeInfo) {
        echo "   • {$typeInfo['name']} ({$typeKey})\n";
        $count++;
        if ($count >= 5) {
            echo "   ... and " . (count($types) - 5) . " more\n";
            break;
        }
    }
} catch (Exception $e) {
    echo "   Error: {$e->getMessage()}\n";
}
echo "\n";

// Example 7: Check OAuth vs API Key
echo "7️⃣  Check authentication methods:\n";
echo "-------------------------------------------\n";
$testTypes = ['vapi', 'gmail', 'servis-ai', 'google-drive'];
foreach ($testTypes as $type) {
    $usesOAuth = $iris->integrations->usesOAuth($type);
    $usesApiKey = $iris->integrations->usesApiKey($type);
    $method = $usesOAuth ? 'OAuth' : ($usesApiKey ? 'API Key' : 'Unknown');
    echo "   {$type}: {$method}\n";
}
echo "\n";

// Example 8: Start OAuth flow (for OAuth-based integrations)
echo "8️⃣  Start OAuth flow:\n";
echo "-------------------------------------------\n";
echo "   // For OAuth integrations like Gmail, Google Drive:\n";
echo "   // \$flow = \$iris->integrations->startOAuthFlow('gmail');\n";
echo "   // echo \$flow['instructions'];\n";
echo "   // echo \"URL: {\$flow['url']}\\n\";\n";
echo "\n";

// Example 9: Disconnect integration (commented out)
echo "9️⃣  Disconnect integration:\n";
echo "-------------------------------------------\n";
echo "   // Uncomment to test:\n";
echo "   // \$result = \$iris->integrations->disconnect('vapi');\n";
echo "   // echo \$result ? \"Disconnected\" : \"Not connected\";\n";
echo "\n";

// Example 10: Get only connected integrations
echo "🔟 Get only connected integrations:\n";
echo "-------------------------------------------\n";
try {
    $connected = $iris->integrations->connected();
    echo "   Found {$connected->count()} connected integration(s)\n";
} catch (Exception $e) {
    echo "   Error: {$e->getMessage()}\n";
}
echo "\n";

echo "===========================================\n";
echo "CLI Command Examples\n";
echo "===========================================\n\n";

echo "List all integrations:\n";
echo "  $ iris integrations list\n\n";

echo "Show available types:\n";
echo "  $ iris integrations types\n\n";

echo "Connect Vapi (interactive):\n";
echo "  $ iris integrations connect vapi\n\n";

echo "Connect Servis.ai (interactive):\n";
echo "  $ iris integrations connect servis-ai\n\n";

echo "Check status:\n";
echo "  $ iris integrations status vapi\n\n";

echo "Test connection:\n";
echo "  $ iris integrations test vapi\n\n";

echo "Disconnect:\n";
echo "  $ iris integrations disconnect vapi\n\n";

echo "With custom API key:\n";
echo "  $ iris integrations list --api-key=your-key --user-id=123\n\n";

echo "===========================================\n";
echo "✅ Integration Management MVP Ready!\n";
echo "===========================================\n";
