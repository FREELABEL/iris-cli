# IRIS SDK Authentication Guide

This guide explains how to authenticate with the IRIS/FreeLABEL API and the different authentication methods required for different endpoints.

## Authentication Methods

The FL-API uses Laravel Passport OAuth2 and supports multiple authentication methods:

### 1. Client Credentials (Machine-to-Machine)

Used for server-side integrations where no user context is needed.

**Required for:**
- Agent CRUD operations (`/api/v1/users/{userId}/bloqs/agents/*`)
- Bloq CRUD operations (`/api/v1/user/{userId}/bloqs/*`)
- Most content management routes
- YouTube, services, and media endpoints

**How to obtain:**

```bash
# Step 1: Create a Passport client (on the server)
php artisan passport:client --client

# This will output:
# Client ID: <your-client-id>
# Client secret: <your-client-secret>

# Step 2: Get an access token
curl -X POST "https://api.freelabel.net/oauth/token" \
  -H "Content-Type: application/json" \
  -d '{
    "grant_type": "client_credentials",
    "client_id": "<your-client-id>",
    "client_secret": "<your-client-secret>"
  }'
```

**Response:**
```json
{
  "token_type": "Bearer",
  "expires_in": 31536000,
  "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9..."
}
```

**SDK Usage:**
```php
$iris = new IRIS\SDK\IRIS([
    'api_key' => $accessToken,  // The access_token from above
    'base_url' => 'https://api.freelabel.net',
    'user_id' => 193,  // The user ID to operate on
]);

// Now you can use agent and bloq methods
$agents = $iris->agents->list();
$bloqs = $iris->bloqs->list();
```

### 2. User OAuth Token (Password Grant)

Used when you need to act on behalf of a specific user.

**Required for:**
- User account info (`/api/user`)
- Personal integrations (`/api/v1/integrations`)
- User-specific operations

**How to obtain:**

```bash
# For password grant (requires first-party client)
curl -X POST "https://api.freelabel.net/oauth/token" \
  -H "Content-Type: application/json" \
  -d '{
    "grant_type": "password",
    "client_id": "<password-grant-client-id>",
    "client_secret": "<password-grant-client-secret>",
    "username": "user@example.com",
    "password": "user-password",
    "scope": ""
  }'
```

### 3. Public Endpoints (No Auth Required)

Some endpoints don't require authentication:

- Lead management (`/api/v1/leads/*`) - Currently public
- Integration types (`/api/v1/integrations/types`)
- Public agent chat (`/api/v1/public/agent/{slug}/chat`)
- Health check (`/api/health`)

**SDK Usage:**
```php
// These work without authentication
$iris = new IRIS\SDK\IRIS([
    'base_url' => 'https://api.freelabel.net',
]);

$leads = $iris->leads->list();
```

## Route Middleware Reference

| Route Pattern | Middleware | Token Type Required |
|--------------|------------|---------------------|
| `/api/v1/users/{userId}/bloqs/agents/*` | `client` | Client Credentials |
| `/api/v1/user/{userId}/bloqs/*` | `client` | Client Credentials |
| `/api/user` | `auth:api` | User OAuth Token |
| `/api/v1/integrations` | `auth:api` | User OAuth Token |
| `/api/v1/leads/*` | (none) | None (public) |
| `/api/v1/integrations/types` | (none) | None (public) |
| `/api/v1/bloqs/agents/generate-response` | (none) | None (but needs agent_id) |

## Common Authentication Errors

### 401 Unauthenticated

**Cause:** Missing or invalid token

```json
{"message": "Unauthenticated."}
```

**Solution:**
1. Check that you're including the `Authorization: Bearer <token>` header
2. Verify the token hasn't expired
3. Ensure you're using the correct token type for the endpoint

### 403 Forbidden / Invalid Scope

**Cause:** Token doesn't have required permissions

**Solution:**
1. For `client` middleware routes, use a client credentials token
2. For `auth:api` routes, use a user OAuth token
3. Check if the token's scopes include the required permissions

## Token Structure

JWT tokens contain claims that determine their type:

**Client Credentials Token:**
```json
{
  "aud": "<client-id>",
  "jti": "<unique-id>",
  "iat": 1756697247,
  "nbf": 1756697247,
  "exp": 1788233247,
  "sub": "",           // Empty = no user
  "scopes": []
}
```

**User Token:**
```json
{
  "aud": "<client-id>",
  "jti": "<unique-id>",
  "iat": 1756697247,
  "nbf": 1756697247,
  "exp": 1788233247,
  "sub": "193",        // User ID
  "scopes": ["*"]
}
```

## Example: Full SDK Setup

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use IRIS\SDK\IRIS;

// For agent/bloq operations (client credentials)
$iris = new IRIS([
    'api_key' => getenv('IRIS_CLIENT_TOKEN'),  // Client credentials token
    'base_url' => 'https://api.freelabel.net',
    'user_id' => 193,
]);

// List agents
$agents = $iris->agents->list();
echo "Found {$agents->count()} agents\n";

// Create an agent
$agent = $iris->agents->create(new AgentConfig(
    name: 'My AI Assistant',
    prompt: 'You are a helpful assistant.',
    model: 'gpt-4o-mini',
));
echo "Created agent: {$agent->name} (ID: {$agent->id})\n";

// Chat with agent
$response = $iris->agents->chat($agent->id, [
    ['role' => 'user', 'content' => 'Hello!']
]);
echo "Response: {$response->content}\n";
```

## Troubleshooting

### "Token not found" or Connection Errors

1. Verify the API is accessible:
   ```bash
   curl https://api.freelabel.net/api/health
   ```

2. Check your base URL doesn't have trailing slashes

3. Ensure your token is properly formatted (no extra whitespace)

### "Server error" on Agent Operations

1. Verify the user_id exists and has permission to create agents
2. Check if required fields are provided (name, prompt, model)
3. Use valid model names: `gpt-4o-mini`, `gpt-5-nano`, `gpt-4.1-nano`

### Rate Limiting

The API implements rate limiting:
- **Authenticated requests**: 500 req/min (production), 1000 req/min (local)
- **Unauthenticated**: 200 req/min (production), 600 req/min (local)

The SDK automatically handles rate limits with exponential backoff.

## Environment Setup

Set these environment variables for easy configuration:

```bash
# Client credentials token for agent/bloq operations
export IRIS_CLIENT_TOKEN="eyJ0eXAiOiJKV1QiLCJhbGciOi..."

# User OAuth token (if needed for user-specific operations)
export IRIS_USER_TOKEN="eyJ0eXAiOiJKV1QiLCJhbGciOi..."

# User ID for operations
export IRIS_USER_ID="193"

# API base URL
export IRIS_BASE_URL="https://api.freelabel.net"

# For local development
export IRIS_BASE_URL="http://localhost:8000"
```

Then in your code:

```php
$iris = new IRIS([
    'api_key' => getenv('IRIS_CLIENT_TOKEN'),
    'base_url' => getenv('IRIS_BASE_URL') ?: 'https://api.freelabel.net',
    'user_id' => (int) getenv('IRIS_USER_ID'),
]);
```
