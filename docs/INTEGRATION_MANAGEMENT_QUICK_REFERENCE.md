# Integration Management - Quick Reference

## Overview

The Integration Management MVP adds powerful CLI and SDK tools for connecting and managing third-party integrations like Vapi, Servis.ai, SMTP, OAuth services, and more.

## CLI Commands

### List Integrations
```bash
iris integrations list
```
Shows all connected integrations with status, type, and creation date.

### Show Available Types
```bash
iris integrations types
```
Displays all available integration types with authentication methods and descriptions.

### Connect Integration

**Vapi (Voice AI):**
```bash
iris integrations connect vapi
```
Prompts for:
- API Key (hidden input)
- Phone Number (optional)

**Servis.ai:**
```bash
iris integrations connect servis-ai
```
Prompts for:
- Client ID
- Client Secret (hidden input)

**SMTP Email:**
```bash
iris integrations connect smtp-email
```
Prompts for:
- SMTP Host
- Port (default: 587)
- Username
- Password (hidden input)
- From Email
- From Name
- Encryption (tls/ssl/none)

**OAuth Integrations (Gmail, Google Drive, etc.):**
```bash
iris integrations connect gmail
```
Opens OAuth URL in browser for authorization.

### Check Status
```bash
iris integrations status vapi
```
Shows connection status and details for a specific integration.

### Test Connection
```bash
iris integrations test vapi
```
Tests the integration connection and displays results.

### Disconnect
```bash
iris integrations disconnect vapi
```
Removes integration (requires confirmation).

### With Custom Credentials
```bash
iris integrations list --api-key=your-key --user-id=123
```

## SDK Usage

### Initialize
```php
use IRIS\SDK\IRIS;

$iris = new IRIS([
    'api_key' => 'your-api-key',
    'user_id' => 123,
]);
```

### List All Integrations
```php
$integrations = $iris->integrations->list();

foreach ($integrations as $integration) {
    echo "{$integration->name} ({$integration->type})\n";
}
```

### Check Status
```php
$status = $iris->integrations->status('vapi');

if ($status['connected']) {
    echo "Vapi is connected!";
    echo "ID: {$status['integration']->id}";
}
```

### Connect Vapi
```php
$integration = $iris->integrations->connectVapi(
    'vapi_key_xxx',
    '+15551234567'  // optional phone number
);

echo "Connected! ID: {$integration->id}";
```

### Connect Servis.ai
```php
$integration = $iris->integrations->connectServisAi(
    'client_id',
    'client_secret'
);
```

### Connect SMTP
```php
$integration = $iris->integrations->connectSmtp(
    'smtp.gmail.com',
    587,
    'user@gmail.com',
    'password',
    'from@example.com',
    'My Name',
    'tls'
);
```

### Generic API Key Connection
```php
$integration = $iris->integrations->connectWithApiKey(
    'integration-type',
    ['api_key' => 'xxx'],
    'Optional Name'
);
```

### Start OAuth Flow
```php
$flow = $iris->integrations->startOAuthFlow('gmail');

echo $flow['instructions'];
echo $flow['url'];  // Open this in browser
```

### Test Integration
```php
$integrations = $iris->integrations->list();
$integration = $integrations->findByType('vapi');

if ($integration) {
    $result = $iris->integrations->test($integration->id);
    
    if ($result->success) {
        echo "Test passed!";
    }
}
```

### Disconnect
```php
$success = $iris->integrations->disconnect('vapi');
```

### Get Connected Only
```php
$connected = $iris->integrations->connected();

echo "Connected: {$connected->count()}";
```

### Filter by Status
```php
$integrations = $iris->integrations->list();

$active = $integrations->filterByStatus('active');
$inactive = $integrations->filterByStatus('inactive');
```

### Filter by Category
```php
$integrations = $iris->integrations->list();

$communication = $integrations->filterByCategory('communication');
$ai = $integrations->filterByCategory('ai');
```

### Find by Type
```php
$integrations = $iris->integrations->list();
$vapi = $integrations->findByType('vapi');

if ($vapi) {
    echo "Found Vapi: {$vapi->name}";
}
```

### Check Auth Method
```php
// Check if integration uses OAuth
if ($iris->integrations->usesOAuth('gmail')) {
    echo "Gmail uses OAuth";
}

// Check if integration uses API key
if ($iris->integrations->usesApiKey('vapi')) {
    echo "Vapi uses API key";
}
```

### Get Available Types
```php
$response = $iris->integrations->types();
$types = $response['data'] ?? $response;

foreach ($types as $typeKey => $typeInfo) {
    echo "{$typeInfo['name']} ({$typeKey})\n";
}
```

## Integration Types

### API Key Authentication
- `vapi` - Vapi Voice AI
- `servis-ai` - Servis.ai
- `smtp-email` - SMTP Email
- `mailjet` - Mailjet
- `google-gemini` - Google Gemini
- `savelife-ai` - SaveLife.AI

### OAuth Authentication
- `google-drive` - Google Drive
- `google-calendar` - Google Calendar
- `gmail` - Gmail
- `slack` - Slack
- `github` - GitHub
- `mailchimp` - Mailchimp

## Error Handling

### SDK
```php
try {
    $integration = $iris->integrations->connectVapi('key', 'phone');
} catch (\Exception $e) {
    echo "Error: {$e->getMessage()}";
}
```

### CLI
Commands return exit codes:
- `0` - Success
- `1` - Failure

Use verbose mode for debugging:
```bash
iris integrations connect vapi -v
```

## Tips

1. **Store credentials securely** - Never commit API keys to version control
2. **Use environment variables** - Set `IRIS_API_KEY` and `IRIS_USER_ID`
3. **Test after connecting** - Always test connections after setup
4. **Check status first** - Verify if already connected before attempting to connect
5. **OAuth popup blockers** - Ensure browser allows popups for OAuth flows

## Examples

### Deployment Script
```bash
#!/bin/bash
# Connect integrations during deployment

iris integrations connect vapi --api-key=$IRIS_API_KEY --user-id=$USER_ID
iris integrations test vapi
```

### Health Check Script
```php
// Check all integrations are working
$integrations = $iris->integrations->connected();

foreach ($integrations as $integration) {
    $result = $iris->integrations->test($integration->id);
    
    if (!$result->success) {
        error_log("Integration {$integration->type} failed health check");
    }
}
```

### Automated Setup
```php
// Auto-connect all required integrations
$required = [
    'vapi' => ['api_key' => getenv('VAPI_KEY')],
    'servis-ai' => [
        'client_id' => getenv('SERVIS_CLIENT_ID'),
        'client_secret' => getenv('SERVIS_SECRET'),
    ],
];

foreach ($required as $type => $creds) {
    $status = $iris->integrations->status($type);
    
    if (!$status['connected']) {
        $iris->integrations->connectWithApiKey($type, $creds);
        echo "Connected {$type}\n";
    }
}
```

## Troubleshooting

### "Integration not found"
- Check spelling of integration type
- Run `iris integrations types` to see available options

### "Already connected"
- Disconnect first: `iris integrations disconnect <type>`
- Or use SDK to update existing integration

### "Test failed"
- Verify credentials are correct
- Check integration service is online
- Review error message for specific issues

### OAuth not working
- Ensure browser allows popups
- Check callback URL is whitelisted
- Verify OAuth credentials in integration dashboard

## Support

For issues or questions:
- Check examples: `sdk/php/examples/integrations-management.php`
- Review plan: `sdk/php/docs/INTEGRATION_MANAGEMENT_SDK_CLI_PLAN.md`
- Run with verbose: `iris integrations <command> -v`
