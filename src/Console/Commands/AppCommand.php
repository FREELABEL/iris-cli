<?php

declare(strict_types=1);

namespace IRIS\SDK\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use IRIS\SDK\IRIS;
use IRIS\SDK\Auth\CredentialStore;

/**
 * Manage IRIS-hosted apps via CLI.
 *
 * Usage:
 *   iris app:create my-app              Create new app with scaffolding
 *   iris app:deploy                     Deploy current directory to IRIS
 *   iris app:list                       List your apps
 *   iris app:delete 123                 Delete an app
 */
class AppCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('app')
            ->setDescription('Manage IRIS-hosted apps')
            ->setHelp(<<<HELP
Manage IRIS-hosted web applications.

<info>Commands:</info>
  app:create <name>     Create a new app with scaffolding
  app:deploy            Deploy current directory to IRIS
  app:list              List your apps
  app:delete <id>       Delete an app

<info>Quick Start:</info>
  # Create a new app
  iris app:create my-calculator

  # Navigate to app directory and deploy
  cd my-calculator
  iris app:deploy

  # List your apps
  iris app:list

<info>Templates:</info>
  --template=basic      Simple HTML/JS app (default)
  --template=react      React app with IRIS bridge
  --template=vue        Vue app with IRIS bridge

<info>Examples:</info>
  iris app:create my-app --template=react
  iris app:deploy --path=/path/to/app
  iris app:delete 123

<info>Environment Variables:</info>
  IRIS_API_KEY    Your API key
  IRIS_USER_ID    Your user ID
HELP
            )
            ->addArgument('action', InputArgument::REQUIRED, 'Action: create, deploy, list, delete')
            ->addArgument('name_or_id', InputArgument::OPTIONAL, 'App name (for create) or App ID (for delete)')
            ->addOption('template', 't', InputOption::VALUE_REQUIRED, 'App template (basic, react, vue)', 'basic')
            ->addOption('path', 'p', InputOption::VALUE_REQUIRED, 'Path to app directory', '.')
            ->addOption('api-key', null, InputOption::VALUE_REQUIRED, 'API key')
            ->addOption('user-id', null, InputOption::VALUE_REQUIRED, 'User ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getArgument('action');

        switch ($action) {
            case 'create':
                return $this->createApp($input, $output, $io);
            case 'deploy':
                return $this->deployApp($input, $output, $io);
            case 'list':
            case 'ls':
                return $this->listApps($input, $output, $io);
            case 'delete':
            case 'rm':
                return $this->deleteApp($input, $output, $io);
            default:
                $io->error("Unknown action: {$action}. Use: create, deploy, list, delete");
                return Command::FAILURE;
        }
    }

    /**
     * Create a new app with scaffolding.
     */
    private function createApp(InputInterface $input, OutputInterface $output, SymfonyStyle $io): int
    {
        $name = $input->getArgument('name_or_id');
        $template = $input->getOption('template');

        if (!$name) {
            $io->error('App name is required. Usage: iris app create <name>');
            return Command::FAILURE;
        }

        $io->title("🚀 Create IRIS App: {$name}");

        // Check if directory already exists
        $targetDir = getcwd() . '/' . $name;
        if (is_dir($targetDir)) {
            $io->error("Directory \"{$name}\" already exists");
            return Command::FAILURE;
        }

        $io->text("Creating app scaffolding with template: <info>{$template}</info>");

        // Create directory
        if (!mkdir($targetDir, 0755, true)) {
            $io->error("Failed to create directory: {$targetDir}");
            return Command::FAILURE;
        }

        // Generate template files
        $this->generateTemplateFiles($targetDir, $name, $template);

        $io->success([
            "App created: {$name}/",
            "Files created:",
            "  - {$name}/index.html",
            "  - {$name}/README.md",
            "  - {$name}/iris.json",
        ]);

        $io->section('Next Steps');
        $io->listing([
            "cd {$name}",
            "# Edit your files",
            "iris app deploy",
        ]);

        return Command::SUCCESS;
    }

    /**
     * Deploy app to IRIS.
     */
    private function deployApp(InputInterface $input, OutputInterface $output, SymfonyStyle $io): int
    {
        $io->title('🚀 Deploy to IRIS');

        // Load credentials
        $store = new CredentialStore();
        $apiKey = $input->getOption('api-key') ?: getenv('IRIS_API_KEY') ?: $store->get('api_key');
        $userId = $input->getOption('user-id') ?: getenv('IRIS_USER_ID') ?: $store->get('user_id');

        if (!$apiKey || !$userId) {
            $io->error('Missing credentials. Set IRIS_API_KEY and IRIS_USER_ID environment variables or run: iris config');
            return Command::FAILURE;
        }

        $appPath = realpath($input->getOption('path'));
        if (!$appPath || !is_dir($appPath)) {
            $io->error("Invalid path: {$input->getOption('path')}");
            return Command::FAILURE;
        }

        // Read or create iris.json
        $configPath = $appPath . '/iris.json';
        $config = null;

        if (file_exists($configPath)) {
            $config = json_decode(file_get_contents($configPath), true);
            if (!$config) {
                $io->error("Invalid iris.json file");
                return Command::FAILURE;
            }
        } else {
            $io->warning('No iris.json found. Creating one...');

            $name = $io->ask('App name', basename($appPath));
            $config = [
                'name' => $name,
                'entry_point' => 'index.html',
                'version' => '1.0.0',
            ];

            file_put_contents($configPath, json_encode($config, JSON_PRETTY_PRINT));
            $io->success('Created iris.json');
        }

        $io->text("Deploying: <info>{$config['name']}</info>");

        // Collect files
        $io->text('Collecting files...');
        $files = $this->collectFiles($appPath);

        if (empty($files)) {
            $io->error('No files found to deploy');
            return Command::FAILURE;
        }

        $io->text("Found " . count($files) . " files");

        // Create bundle (base64 encoded files)
        $bundle = [];
        foreach ($files as $relativePath => $content) {
            $bundle[$relativePath] = base64_encode($content);
        }

        // Upload to IRIS API
        $io->text('Uploading to IRIS...');

        $apiUrl = getenv('IRIS_API_URL') ?: 'https://api.heyiris.io';

        $ch = curl_init("{$apiUrl}/api/v1/apps/deploy");
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'config' => $config,
                'bundle' => $bundle,
            ]),
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 && $httpCode !== 201) {
            $error = json_decode($response, true);
            $io->error("Deployment failed: " . ($error['error'] ?? $response));
            return Command::FAILURE;
        }

        $result = json_decode($response, true);

        if (!isset($result['success']) || !$result['success']) {
            $io->error("Deployment failed: " . ($result['error'] ?? 'Unknown error'));
            return Command::FAILURE;
        }

        $io->success([
            "App deployed successfully!",
            "App ID: {$result['data']['id']}",
            "URL: {$result['data']['url']}",
        ]);

        return Command::SUCCESS;
    }

    /**
     * List user's apps.
     */
    private function listApps(InputInterface $input, OutputInterface $output, SymfonyStyle $io): int
    {
        $io->title('📱 Your IRIS Apps');

        // Load credentials
        $store = new CredentialStore();
        $apiKey = $input->getOption('api-key') ?: getenv('IRIS_API_KEY') ?: $store->get('api_key');
        $userId = $input->getOption('user-id') ?: getenv('IRIS_USER_ID') ?: $store->get('user_id');

        if (!$apiKey || !$userId) {
            $io->error('Missing credentials. Set IRIS_API_KEY and IRIS_USER_ID environment variables or run: iris config');
            return Command::FAILURE;
        }

        $apiUrl = getenv('IRIS_API_URL') ?: 'https://api.heyiris.io';

        $ch = curl_init("{$apiUrl}/api/v1/users/{$userId}/bloqs/apps");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Accept: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            $error = json_decode($response, true);
            $io->error("Failed to fetch apps: " . ($error['error'] ?? $response));
            return Command::FAILURE;
        }

        $result = json_decode($response, true);
        $apps = $result['data'] ?? [];

        if (empty($apps)) {
            $io->info('No apps found. Create one with: iris app create <name>');
            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($apps as $app) {
            $type = $app['storage_type'] === 'github' ? '🔗 GitHub' : '☁️ IRIS';
            $source = $app['storage_type'] === 'github'
                ? ($app['repository_url'] ?? 'N/A')
                : 'IRIS Cloud';
            $agent = $app['agent']['name'] ?? '-';
            $synced = $app['last_synced_at']
                ? date('Y-m-d', strtotime($app['last_synced_at']))
                : 'Never';

            $rows[] = [
                $app['id'],
                $app['name'],
                $type,
                strlen($source) > 30 ? substr($source, 0, 30) . '...' : $source,
                $agent,
                $synced,
            ];
        }

        $io->table(
            ['ID', 'Name', 'Type', 'Source', 'Agent', 'Last Synced'],
            $rows
        );

        $io->text("Total: " . count($apps) . " app(s)");

        return Command::SUCCESS;
    }

    /**
     * Delete an app.
     */
    private function deleteApp(InputInterface $input, OutputInterface $output, SymfonyStyle $io): int
    {
        $appId = $input->getArgument('name_or_id');

        if (!$appId || !is_numeric($appId)) {
            $io->error('App ID is required. Usage: iris app delete <id>');
            return Command::FAILURE;
        }

        $io->title("🗑️ Delete App #{$appId}");

        // Load credentials
        $store = new CredentialStore();
        $apiKey = $input->getOption('api-key') ?: getenv('IRIS_API_KEY') ?: $store->get('api_key');
        $userId = $input->getOption('user-id') ?: getenv('IRIS_USER_ID') ?: $store->get('user_id');

        if (!$apiKey || !$userId) {
            $io->error('Missing credentials. Set IRIS_API_KEY and IRIS_USER_ID environment variables or run: iris config');
            return Command::FAILURE;
        }

        // Confirm deletion
        if (!$io->confirm("Are you sure you want to delete app #{$appId}?", false)) {
            $io->warning('Deletion cancelled.');
            return Command::SUCCESS;
        }

        $apiUrl = getenv('IRIS_API_URL') ?: 'https://api.heyiris.io';

        $ch = curl_init("{$apiUrl}/api/v1/users/{$userId}/bloqs/apps/{$appId}");
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Accept: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 && $httpCode !== 204) {
            $error = json_decode($response, true);
            $io->error("Failed to delete app: " . ($error['error'] ?? $response));
            return Command::FAILURE;
        }

        $io->success("App #{$appId} deleted successfully");

        return Command::SUCCESS;
    }

    /**
     * Generate template files for a new app.
     */
    private function generateTemplateFiles(string $targetDir, string $name, string $template): void
    {
        // Generate index.html based on template
        $indexContent = $this->getTemplateContent($name, $template);
        file_put_contents($targetDir . '/index.html', $indexContent);

        // Generate README.md
        $readmeContent = $this->getReadmeContent($name, $template);
        file_put_contents($targetDir . '/README.md', $readmeContent);

        // Generate iris.json
        $config = [
            'name' => $name,
            'entry_point' => 'index.html',
            'version' => '1.0.0',
            'description' => "{$name} - Built with IRIS",
        ];
        file_put_contents($targetDir . '/iris.json', json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Get HTML template content.
     */
    private function getTemplateContent(string $name, string $template): string
    {
        switch ($template) {
            case 'react':
                return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{$name}</title>
  <script src="https://unpkg.com/react@18/umd/react.development.js"></script>
  <script src="https://unpkg.com/react-dom@18/umd/react-dom.development.js"></script>
  <script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>
  <script src="https://cdn.heyiris.io/iris-bridge.js"></script>
  <style>
    body { font-family: system-ui, sans-serif; margin: 0; padding: 20px; }
    .app { max-width: 800px; margin: 0 auto; }
  </style>
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
        <div className="app">
          <h1>Hello from {$name}!</h1>
          <p>Built with IRIS + React</p>
          {context && <pre>{JSON.stringify(context, null, 2)}</pre>}
        </div>
      );
    }

    ReactDOM.createRoot(document.getElementById('root')).render(<App />);
  </script>
</body>
</html>
HTML;

            case 'vue':
                return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{$name}</title>
  <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
  <script src="https://cdn.heyiris.io/iris-bridge.js"></script>
  <style>
    body { font-family: system-ui, sans-serif; margin: 0; padding: 20px; }
    .app { max-width: 800px; margin: 0 auto; }
  </style>
</head>
<body>
  <div id="app">
    <h1>Hello from {$name}!</h1>
    <p>Built with IRIS + Vue</p>
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
HTML;

            default: // basic
                return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{$name}</title>
  <script src="https://cdn.heyiris.io/iris-bridge.js"></script>
  <style>
    body {
      font-family: system-ui, -apple-system, sans-serif;
      margin: 0;
      padding: 20px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
      color: white;
    }
    .container {
      max-width: 800px;
      margin: 0 auto;
      text-align: center;
    }
    h1 { font-size: 3rem; margin-bottom: 0.5rem; }
    p { font-size: 1.2rem; opacity: 0.9; }
    .card {
      background: rgba(255,255,255,0.1);
      backdrop-filter: blur(10px);
      border-radius: 12px;
      padding: 20px;
      margin-top: 30px;
    }
    pre {
      text-align: left;
      background: rgba(0,0,0,0.2);
      padding: 15px;
      border-radius: 8px;
      overflow-x: auto;
    }
  </style>
</head>
<body>
  <div class="container">
    <h1>Hello from {$name}!</h1>
    <p>Built with IRIS</p>
    <div class="card">
      <h3>IRIS Context</h3>
      <pre id="context">Loading...</pre>
    </div>
  </div>
  <script>
    window.iris?.getContext().then(ctx => {
      document.getElementById('context').textContent = JSON.stringify(ctx, null, 2);
    }).catch(err => {
      document.getElementById('context').textContent = 'No IRIS context available';
    });
  </script>
</body>
</html>
HTML;
        }
    }

    /**
     * Get README content.
     */
    private function getReadmeContent(string $name, string $template): string
    {
        $framework = match($template) {
            'react' => ' + React',
            'vue' => ' + Vue',
            default => '',
        };

        return <<<MD
# {$name}

An IRIS-hosted web app{$framework}.

## Development

Open `index.html` in your browser to preview.

## Deployment

Deploy to IRIS with:

```bash
iris app deploy
```

Your app will be available at `https://apps.heyiris.io/{app-id}/`

## IRIS Bridge

This app includes the IRIS bridge script for context sharing:
- `window.iris.getContext()` - Get app context from IRIS
- `window.iris.sendMessage(msg)` - Send messages to IRIS agent
MD;
    }

    /**
     * Collect all files from a directory.
     */
    private function collectFiles(string $dir): array
    {
        $files = [];
        $skipDirs = ['node_modules', '.git', '.iris', '__pycache__', '.venv', 'vendor'];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            // Skip directories
            if ($file->isDir()) {
                continue;
            }

            // Get relative path
            $relativePath = substr($file->getPathname(), strlen($dir) + 1);

            // Skip common directories
            $shouldSkip = false;
            foreach ($skipDirs as $skipDir) {
                if (str_starts_with($relativePath, $skipDir . '/') || $relativePath === $skipDir) {
                    $shouldSkip = true;
                    break;
                }
            }

            if ($shouldSkip) {
                continue;
            }

            // Read file content
            $files[$relativePath] = file_get_contents($file->getPathname());
        }

        return $files;
    }
}
