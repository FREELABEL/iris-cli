# IRIS App Management

Comprehensive guide for creating, deploying, and managing IRIS-hosted web applications.

## Overview

IRIS supports two deployment modes for web applications:

| Mode | Description | Best For |
|------|-------------|----------|
| **GitHub-backed** | Connect existing GitHub repo, agent gets README context | Developers with version control |
| **IRIS-hosted** | Deploy via CLI to IRIS Cloud Storage | Quick deployments, no GitHub needed |

This guide covers the **IRIS-hosted** mode using the CLI `app` command.

## Quick Start

```bash
# 1. Create a new app
./bin/iris app create my-calculator

# 2. Edit your files
cd my-calculator
# ... make changes to index.html ...

# 3. Deploy to IRIS
cd ..
./bin/iris app deploy --path=my-calculator

# 4. View your apps
./bin/iris app list
```

## Commands Reference

### `app create <name>`

Create a new app with scaffolding files.

**Syntax:**
```bash
./bin/iris app create <name> [--template=<template>]
```

**Arguments:**
- `name` - App name (creates directory with this name)

**Options:**
- `--template`, `-t` - Template to use: `basic`, `react`, `vue` (default: `basic`)

**Examples:**
```bash
# Basic HTML/JS app
./bin/iris app create landing-page

# React app
./bin/iris app create dashboard --template=react

# Vue app
./bin/iris app create widget --template=vue
```

**Generated Structure:**
```
my-app/
├── index.html      # Main entry point (template-specific)
├── README.md       # Documentation
└── iris.json       # App configuration
```

### `app deploy`

Deploy an app to IRIS Cloud.

**Syntax:**
```bash
./bin/iris app deploy [--path=<directory>]
```

**Options:**
- `--path`, `-p` - Path to app directory (default: current directory)

**Examples:**
```bash
# Deploy current directory
./bin/iris app deploy

# Deploy specific directory
./bin/iris app deploy --path=/projects/my-app
```

**Behavior:**
1. Reads `iris.json` config (creates one if missing)
2. Collects all files (excludes `node_modules`, `.git`, `vendor`, etc.)
3. Uploads to IRIS Cloud Storage
4. Registers app in your IRIS dashboard
5. Returns public URL

### `app list`

List all your IRIS apps.

**Syntax:**
```bash
./bin/iris app list
```

**Aliases:** `app ls`

**Output Columns:**
- ID - App identifier
- Name - App display name
- Type - GitHub or IRIS-hosted
- Source - Repository URL or "IRIS Cloud"
- Agent - Assigned agent name (if any)
- Last Synced - Last README sync date

### `app delete <id>`

Delete an app.

**Syntax:**
```bash
./bin/iris app delete <id>
```

**Arguments:**
- `id` - App ID to delete

**Example:**
```bash
./bin/iris app delete 42
```

## Templates

### Basic Template

Simple HTML/JS with IRIS Bridge integration.

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>My App</title>
  <script src="https://cdn.heyiris.io/iris-bridge.js"></script>
</head>
<body>
  <h1>Hello from My App!</h1>
  <div id="context"></div>

  <script>
    window.iris?.getContext().then(ctx => {
      document.getElementById('context').textContent =
        JSON.stringify(ctx, null, 2);
    });
  </script>
</body>
</html>
```

### React Template

React 18 app with IRIS Bridge hooks.

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>My React App</title>
  <script src="https://unpkg.com/react@18/umd/react.development.js"></script>
  <script src="https://unpkg.com/react-dom@18/umd/react-dom.development.js"></script>
  <script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>
  <script src="https://cdn.heyiris.io/iris-bridge.js"></script>
</head>
<body>
  <div id="root"></div>
  <script type="text/babel">
    function App() {
      const [context, setContext] = React.useState(null);

      React.useEffect(() => {
        window.iris?.getContext().then(setContext);
      }, []);

      return (
        <div>
          <h1>Hello from My React App!</h1>
          {context && <pre>{JSON.stringify(context, null, 2)}</pre>}
        </div>
      );
    }

    ReactDOM.createRoot(document.getElementById('root')).render(<App />);
  </script>
</body>
</html>
```

### Vue Template

Vue 3 app with IRIS Bridge composables.

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>My Vue App</title>
  <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
  <script src="https://cdn.heyiris.io/iris-bridge.js"></script>
</head>
<body>
  <div id="app">
    <h1>Hello from My Vue App!</h1>
    <pre v-if="context">{{ JSON.stringify(context, null, 2) }}</pre>
  </div>
  <script>
    const { createApp, ref, onMounted } = Vue;

    createApp({
      setup() {
        const context = ref(null);

        onMounted(async () => {
          if (window.iris) {
            context.value = await window.iris.getContext();
          }
        });

        return { context };
      }
    }).mount('#app');
  </script>
</body>
</html>
```

## IRIS Bridge API

The IRIS Bridge script (`https://cdn.heyiris.io/iris-bridge.js`) provides communication between your app and IRIS.

### Methods

#### `getContext()`

Get app context including user info, agent data, and configuration.

```javascript
const ctx = await window.iris.getContext();
console.log(ctx.user);    // Current user
console.log(ctx.agent);   // Assigned agent
console.log(ctx.app);     // App metadata
```

#### `sendMessage(message)`

Send a message to the assigned IRIS agent.

```javascript
window.iris.sendMessage('Process this data: ' + JSON.stringify(data));
```

#### `onMessage(callback)`

Listen for messages from the IRIS agent.

```javascript
window.iris.onMessage((msg) => {
  console.log('Agent says:', msg);
  // Update UI with agent response
});
```

### Example: Interactive App

```html
<script>
  // Initialize
  let context = null;

  window.iris?.getContext().then(ctx => {
    context = ctx;
    document.getElementById('user').textContent = ctx.user?.name || 'Guest';
  });

  // Listen for agent messages
  window.iris?.onMessage(msg => {
    const chat = document.getElementById('chat');
    chat.innerHTML += `<div class="agent">${msg}</div>`;
  });

  // Send message to agent
  function sendToAgent() {
    const input = document.getElementById('input');
    window.iris?.sendMessage(input.value);

    const chat = document.getElementById('chat');
    chat.innerHTML += `<div class="user">${input.value}</div>`;
    input.value = '';
  }
</script>
```

## Configuration

### iris.json

The `iris.json` file configures your app:

```json
{
    "name": "my-calculator",
    "entry_point": "index.html",
    "version": "1.0.0",
    "description": "A simple calculator app"
}
```

| Field | Description | Required |
|-------|-------------|----------|
| `name` | App display name | Yes |
| `entry_point` | Main HTML file | Yes (default: `index.html`) |
| `version` | Semantic version | No |
| `description` | App description | No |

### Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `IRIS_API_KEY` | Your API key | Required |
| `IRIS_USER_ID` | Your user ID | Required |
| `IRIS_API_URL` | API base URL | `https://api.heyiris.io` |

## GitHub-Backed Apps (via Web UI)

For GitHub-backed apps, use the IRIS web dashboard:

1. Navigate to your BLOQ's URL Manager
2. Click "Register App" → "GitHub Repo"
3. Enter repository URL (e.g., `https://github.com/user/my-app`)
4. Assign or create an agent
5. Click "Sync" to fetch README and update agent context

**Benefits of GitHub-backed:**
- Agent gets README summarized and injected into its context
- GitHub integration enabled for the agent
- Agent can browse and reference repository files
- Automatic sync available

## Assigning Agents

Apps can have an assigned AI agent that understands the codebase:

### Via CLI (after deploy)

```bash
# List your apps to get the ID
./bin/iris app list

# Assign an agent via API (use sdk:call)
./bin/iris sdk:call apps.assignAgent 42 agent_id=337
```

### Via Web UI

1. Go to BLOQ → URL Manager → Apps
2. Click "Assign Agent" on your app
3. Choose "Create new agent" or "Use existing agent"

### Agent Context

When an agent is assigned:
- The app's README is summarized and added to agent's `initial_prompt`
- Agent can reference app documentation in conversations
- For GitHub apps, agent has full repository access

## Troubleshooting

### "Missing credentials" Error

```bash
# Check your configuration
./bin/iris config

# Ensure .env has required values
IRIS_API_KEY=your_key_here
IRIS_USER_ID=your_user_id
```

### "No iris.json found"

The deploy command will prompt you to create one, or create it manually:

```bash
echo '{"name":"my-app","entry_point":"index.html","version":"1.0.0"}' > iris.json
```

### Files Not Deploying

Check that files aren't in excluded directories:
- `node_modules/`
- `.git/`
- `vendor/`
- `__pycache__/`
- `.venv/`

### App Not Loading

1. Check the entry point file exists
2. Verify `iris.json` has correct `entry_point`
3. Check browser console for JavaScript errors
4. Ensure IRIS Bridge script is loaded correctly

## Best Practices

1. **Keep apps lightweight** - IRIS-hosted is best for simple apps
2. **Use templates** - Start with a template and customize
3. **Test locally first** - Open `index.html` in browser before deploying
4. **Version your apps** - Update `version` in `iris.json` with each deploy
5. **Assign agents** - Apps work best with an assigned agent for context

## API Endpoints (Backend)

For developers integrating directly:

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/v1/users/{userId}/bloqs/apps` | GET | List apps |
| `/api/v1/users/{userId}/bloqs/apps` | POST | Create app |
| `/api/v1/users/{userId}/bloqs/apps/{id}` | GET | Get app |
| `/api/v1/users/{userId}/bloqs/apps/{id}` | PUT | Update app |
| `/api/v1/users/{userId}/bloqs/apps/{id}` | DELETE | Delete app |
| `/api/v1/users/{userId}/bloqs/apps/{id}/sync` | POST | Sync README |
| `/api/v1/users/{userId}/bloqs/apps/{id}/assign-agent` | POST | Assign agent |
| `/api/v1/apps/deploy` | POST | Deploy app (CLI) |
| `/api/apps/{id}/serve/{path?}` | GET | Serve app files |
