# Integration Management Guide

## Overview

The IRIS SDK provides comprehensive integration management capabilities, allowing you to programmatically connect, configure, test, and manage third-party services from code or command line.

## Quick Start

### CLI Usage

```bash
# List all connected integrations
iris integrations list

# Show available integration types
iris integrations types

# Connect an integration (interactive)
iris integrations connect vapi

# Check status
iris integrations status vapi

# Test connection
iris integrations test vapi

# Disconnect
iris integrations disconnect vapi
```

### SDK Usage

```php
use IRIS\SDK\IRIS;

$iris = new IRIS(['api_key' => 'your-key', 'user_id' => 193]);

// Check if Vapi is connected
$status = $iris->integrations->status('vapi');
if ($status['connected']) {
    echo "Vapi is ready!\n";
}

// Connect Vapi
$integration = $iris->integrations->connectVapi('vapi_api_key', '+15551234567');

// Test the connection
$result = $iris->integrations->test($integration->id);
if ($result->success) {
    echo "Connection successful!\n";
}

// List all connected integrations
$connected = $iris->integrations->connected();
foreach ($connected as $integration) {
    echo "- {$integration->name} ({$integration->type})\n";
}

// Disconnect
$iris->integrations->disconnect('vapi');
```

## Supported Integrations

### API Key Based

#### Vapi Voice AI
```php
// SDK
$integration = $iris->integrations->connectVapi(
    apiKey: 'vapi_xxx',
    phoneNumber: '+15551234567'  // optional
);

// CLI
iris integrations connect vapi
# Prompts for: API Key, Phone Number (optional)
```

#### Servis.ai
```php
// SDK
$integration = $iris->integrations->connectServisAi(
    clientId: 'your-client-id',
    clientSecret: 'your-client-secret'
);

// CLI
iris integrations connect servis-ai
# Prompts for: Client ID, Client Secret
```

#### SMTP Email
```php
// SDK
$integration = $iris->integrations->connectSmtp(
    host: 'smtp.gmail.com',
    port: 587,
    username: 'user@example.com',
    password: 'your-password',
    fromEmail: 'noreply@example.com',
    fromName: 'My App',
    encryption: 'tls'
);

// CLI
iris integrations connect smtp-email
# Interactive prompts for all settings
```

#### Generic API Key
```php
// SDK
$integration = $iris->integrations->connectWithApiKey(
    type: 'mailjet',
    credentials: ['api_key' => 'xxx', 'secret_key' => 'yyy'],
    name: 'My Mailjet Connection'
);

// CLI
iris integrations connect mailjet
# Prompts for credentials based on type
```

### OAuth Based

OAuth integrations require browser-based authorization:

```php
// SDK - Start OAuth flow
$flow = $iris->integrations->startOAuthFlow('google-drive');
echo $flow['instructions'];
echo $flow['url'];

// CLI - Automatically opens browser
iris integrations connect google-drive
```

**Supported OAuth Integrations:**
- Google Drive
- Google Calendar  
- Gmail
- Slack
- GitHub
- Mailchimp

## SDK Methods Reference

### Connection Management

#### `status(string $type): array`
Check if an integration is connected.

```php
$status = $iris->integrations->status('vapi');

if ($status['connected']) {
    $integration = $status['integration'];
    echo "Connected: {$integration->name}\n";
    echo "Status: {$integration->status}\n";
    echo "ID: {$integration->id}\n";
} else {
    echo "Not connected\n";
}
```

#### `connected(): IntegrationCollection`
Get all active integrations.

```php
$connected = $iris->integrations->connected();
echo "You have " . count($connected) . " integrations\n";

foreach ($connected as $integration) {
    echo "- {$integration->name}\n";
}
```

#### `disconnect(string $type): bool`
Disconnect an integration by type.

```php
if ($iris->integrations->disconnect('vapi')) {
    echo "Disconnected from Vapi\n";
}
```

### Connection Methods

#### `connectWithApiKey(string $type, array $credentials, ?string $name): Integration`
Generic method for API key-based integrations.

```php
$integration = $iris->integrations->connectWithApiKey(
    type: 'custom-service',
    credentials: [
        'api_key' => 'xxx',
        'api_secret' => 'yyy',
    ],
    name: 'My Custom Service'
);
```

#### `connectVapi(string $apiKey, ?string $phoneNumber): Integration`
Connect Vapi Voice AI.

```php
$integration = $iris->integrations->connectVapi(
    apiKey: 'vapi_xxx',
    phoneNumber: '+15551234567'
);
```

#### `connectServisAi(string $clientId, string $clientSecret): Integration`
Connect Servis.ai.

```php
$integration = $iris->integrations->connectServisAi(
    clientId: 'client-id',
    clientSecret: 'client-secret'
);
```

#### `connectSmtp(...): Integration`
Connect SMTP email service.

```php
$integration = $iris->integrations->connectSmtp(
    host: 'smtp.gmail.com',
    port: 587,
    username: 'user@example.com',
    password: 'app-password',
    fromEmail: 'noreply@example.com',
    fromName: 'My App',
    encryption: 'tls'
);
```

### OAuth Methods

#### `startOAuthFlow(string $type): array`
Initiate OAuth authorization flow.

```php
$flow = $iris->integrations->startOAuthFlow('google-drive');

echo $flow['instructions'];
echo "\nURL: " . $flow['url'] . "\n";

// User opens URL in browser and authorizes
// Integration is automatically created after authorization
```

#### `usesOAuth(string $type): bool`
Check if integration requires OAuth.

```php
if ($iris->integrations->usesOAuth('slack')) {
    echo "Slack requires OAuth authorization\n";
}
```

#### `usesApiKey(string $type): bool`
Check if integration uses API key authentication.

```php
if ($iris->integrations->usesApiKey('vapi')) {
    echo "Vapi uses API key authentication\n";
}
```

### Testing & Information

#### `test(int $integrationId): TestResult`
Test an integration connection.

```php
$integration = $iris->integrations->list()->findByType('vapi');
$result = $iris->integrations->test($integration->id);

if ($result->success) {
    echo "Test passed!\n";
    if ($result->data) {
        print_r($result->data);
    }
} else {
    echo "Test failed: {$result->message}\n";
}
```

#### `types(): array`
Get available integration types and their metadata.

```php
$types = $iris->integrations->types();

foreach ($types['data'] as $key => $info) {
    echo "{$key}: {$info['name']} ({$info['category']})\n";
}
```

#### `list(): IntegrationCollection`
Get all integrations for the authenticated user.

```php
$all = $iris->integrations->list();

foreach ($all as $integration) {
    echo "- {$integration->name} ({$integration->type}) - {$integration->status}\n";
}
```

## Collection Methods

The `IntegrationCollection` provides helper methods for filtering and finding integrations.

### `findByType(string $type): ?Integration`
Find an integration by its type.

```php
$vapi = $iris->integrations->list()->findByType('vapi');
if ($vapi) {
    echo "Found Vapi integration: {$vapi->id}\n";
}
```

### `filterByStatus(string $status): IntegrationCollection`
Filter integrations by status.

```php
$active = $iris->integrations->list()->filterByStatus('active');
$inactive = $iris->integrations->list()->filterByStatus('inactive');

echo "Active: " . count($active) . "\n";
echo "Inactive: " . count($inactive) . "\n";
```

### `filterByCategory(string $category): IntegrationCollection`
Filter integrations by category.

```php
$email = $iris->integrations->list()->filterByCategory('email');
$voice = $iris->integrations->list()->filterByCategory('voice');

echo "Email integrations: " . count($email) . "\n";
echo "Voice integrations: " . count($voice) . "\n";
```

## CLI Command Reference

### `iris integrations list`
List all connected integrations.

```bash
iris integrations list

# With credentials
iris integrations list --api-key=xxx --user-id=193
```

Output:
```
🔗 Your Connected Integrations
==============================

ID  Name           Type        Category  Status  Created
1   Vapi Voice AI  vapi        voice     ✓       2024-12-26
2   Servis.ai      servis-ai   automation ✓      2024-12-26
```

### `iris integrations types`
Show available integration types.

```bash
iris integrations types
```

Output:
```
📦 Available Integration Types
==============================

Type           Name            Category      Auth      Description
vapi           Vapi            voice         API Key   Voice AI integration...
servis-ai      Servis.ai       automation    API Key   Service automation...
smtp-email     SMTP Email      email         API Key   Email delivery...
google-drive   Google Drive    storage       OAuth     Cloud storage...
```

### `iris integrations connect <type>`
Connect an integration interactively.

```bash
iris integrations connect vapi

# Example session:
# 📍 Get your API key from: https://dashboard.vapi.ai
#
# Vapi API Key: **************
# Phone Number (optional): +15551234567
# Connecting to Vapi...
# Testing connection...
#
# ✓ Successfully connected to vapi!
# Integration ID: 1
```

### `iris integrations disconnect <type>`
Disconnect an integration.

```bash
iris integrations disconnect vapi

# Example session:
# ⚠️  About to disconnect vapi
# This will remove the integration and all its credentials.
#
# Are you sure? (yes/no) yes
# ✓ Disconnected from vapi
```

### `iris integrations test <type>`
Test an integration connection.

```bash
iris integrations test vapi

# Output:
# 🔍 Testing vapi connection...
#
# ✓ Connection test successful!
#
# Test Details:
#   status: active
#   phone_number: +15551234567
#   last_call: 2024-12-26
```

### `iris integrations status <type>`
Show integration status.

```bash
iris integrations status vapi

# Output:
# Status: vapi
#
# Connected ✓
#
# ID:       1
# Name:     Vapi Voice AI
# Type:     vapi
# Category: voice
# Status:   active
# Created:  2024-12-26
```

## Error Handling

### SDK Errors

```php
use IRIS\SDK\Exceptions\IntegrationException;

try {
    $integration = $iris->integrations->connectVapi('invalid-key');
} catch (IntegrationException $e) {
    echo "Failed to connect: {$e->getMessage()}\n";
    
    // Get error details
    if ($e->getResponse()) {
        print_r($e->getResponse());
    }
}
```

### Testing for Errors

```php
$result = $iris->integrations->test($integrationId);

if (!$result->success) {
    echo "Test failed: {$result->message}\n";
    
    // Check error details
    if (isset($result->data['error_code'])) {
        echo "Error code: {$result->data['error_code']}\n";
    }
}
```

## Common Workflows

### Initial Setup Workflow

```php
// 1. Check what's available
$types = $iris->integrations->types();

// 2. Connect required integrations
$vapi = $iris->integrations->connectVapi($apiKey, $phone);
$smtp = $iris->integrations->connectSmtp($host, $port, ...);

// 3. Test connections
foreach ([$vapi, $smtp] as $integration) {
    $result = $iris->integrations->test($integration->id);
    if ($result->success) {
        echo "✓ {$integration->name} ready\n";
    }
}

// 4. Verify all connected
$connected = $iris->integrations->connected();
echo "Total connected: " . count($connected) . "\n";
```

### Health Check Workflow

```php
// Get all active integrations
$integrations = $iris->integrations->connected();

foreach ($integrations as $integration) {
    echo "Testing {$integration->name}...\n";
    
    $result = $iris->integrations->test($integration->id);
    
    if ($result->success) {
        echo "  ✓ OK\n";
    } else {
        echo "  ✗ FAILED: {$result->message}\n";
        
        // Optionally disconnect failed integrations
        if (shouldDisconnect($result)) {
            $iris->integrations->disconnect($integration->type);
        }
    }
}
```

### Migration Workflow

```php
// Export integrations from one environment
$integrations = $iris->integrations->list();
$config = [];

foreach ($integrations as $integration) {
    $config[$integration->type] = [
        'name' => $integration->name,
        'category' => $integration->category,
        // Note: Credentials are not exported for security
    ];
}

file_put_contents('integrations.json', json_encode($config, JSON_PRETTY_PRINT));

// Import to new environment (requires manual credential entry)
$config = json_decode(file_get_contents('integrations.json'), true);

foreach ($config as $type => $settings) {
    echo "Connect {$type} manually with your credentials\n";
    // User connects via CLI or provides credentials
}
```

## Security Best Practices

### Credential Storage

**Never hardcode credentials:**
```php
// ❌ Bad
$integration = $iris->integrations->connectVapi('vapi_1234567890abcdef');

// ✅ Good
$apiKey = getenv('VAPI_API_KEY');
$integration = $iris->integrations->connectVapi($apiKey);
```

### Token Handling

**Store tokens securely:**
```php
// Use environment variables
$apiKey = $_ENV['IRIS_API_KEY'];

// Or encrypted storage
$apiKey = decrypt(file_get_contents('~/.iris/credentials.enc'));
```

### Testing in Development

**Use separate credentials for development:**
```bash
# .env.local
VAPI_API_KEY=vapi_dev_xxx
SMTP_PASSWORD=dev_password

# .env.production  
VAPI_API_KEY=vapi_prod_xxx
SMTP_PASSWORD=prod_password
```

## Troubleshooting

### "Integration not found"
The integration hasn't been connected yet.
```bash
iris integrations connect <type>
```

### "Test failed: Invalid credentials"
Credentials are incorrect or expired.
```bash
# Disconnect and reconnect with valid credentials
iris integrations disconnect <type>
iris integrations connect <type>
```

### "OAuth callback failed"
OAuth authorization wasn't completed.
```bash
# Try again and complete the browser authorization
iris integrations connect <type>
```

### "Unauthenticated"
Your SDK API key is invalid or expired.
```bash
# Update your .env file with a valid key
iris config show  # Check current config
```

## Advanced Usage

### Custom Integration Types

If you need to connect a service not yet supported:

```php
$integration = $iris->integrations->connectWithApiKey(
    type: 'custom-api',
    credentials: [
        'api_key' => 'your-key',
        'api_endpoint' => 'https://api.custom.com',
        // Add any custom fields needed
    ],
    name: 'My Custom API'
);
```

### Bulk Operations

```php
// Connect multiple integrations
$credentials = [
    'vapi' => ['api_key' => getenv('VAPI_KEY')],
    'servis-ai' => ['client_id' => getenv('SERVIS_ID'), 'client_secret' => getenv('SERVIS_SECRET')],
];

foreach ($credentials as $type => $creds) {
    try {
        $integration = $iris->integrations->connectWithApiKey($type, $creds);
        echo "✓ Connected {$type}\n";
    } catch (\Exception $e) {
        echo "✗ Failed {$type}: {$e->getMessage()}\n";
    }
}
```

### Integration Status Monitoring

```php
// Monitor integration health
while (true) {
    $integrations = $iris->integrations->connected();
    
    foreach ($integrations as $integration) {
        $result = $iris->integrations->test($integration->id);
        
        if (!$result->success) {
            // Send alert
            notify("Integration {$integration->name} is down!");
        }
    }
    
    sleep(300); // Check every 5 minutes
}
```

## Next Steps

- Review [TECHNICAL.md](TECHNICAL.md) for complete SDK documentation
- Check [examples/integrations/](../examples/integrations/) for more code samples
- See [CLI_USAGE.md](CLI_USAGE.md) for all CLI commands
- Read [AUTH_GUIDE.md](AUTH_GUIDE.md) for authentication details
