<?php

namespace IRIS\SDK\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use IRIS\SDK\IRIS;
use IRIS\SDK\Config;

/**
 * Integration Management Command
 * 
 * Provides CLI tools for managing third-party integrations.
 */
class IntegrationsCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('integrations')
            ->setDescription('Manage third-party integrations')
            ->setHelp('Manage integrations like Vapi, Servis.ai, SMTP, OAuth services, etc.')
            ->addArgument('action', InputArgument::OPTIONAL, 'Action: list, connect, disconnect, test, status, types', 'list')
            ->addArgument('type', InputArgument::OPTIONAL, 'Integration type (e.g., vapi, servis-ai, smtp-email)')
            ->addOption('api-key', null, InputOption::VALUE_REQUIRED, 'API key for authentication')
            ->addOption('user-id', null, InputOption::VALUE_REQUIRED, 'User ID');
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getArgument('action') ?? 'list';
        
        try {
            // Initialize SDK
            $configOptions = [];
            if ($apiKey = $input->getOption('api-key')) {
                $configOptions['api_key'] = $apiKey;
            }
            if ($userId = $input->getOption('user-id')) {
                $configOptions['user_id'] = (int)$userId;
            }
            
            $iris = new IRIS($configOptions);
            
            // Route to appropriate handler
            switch ($action) {
                case 'list':
                    return $this->listIntegrations($iris, $io);
                case 'connect':
                    return $this->connectIntegration($iris, $io, $input, $output);
                case 'disconnect':
                    return $this->disconnectIntegration($iris, $io, $input);
                case 'test':
                    return $this->testIntegration($iris, $io, $input);
                case 'status':
                    return $this->showStatus($iris, $io, $input);
                case 'types':
                    return $this->showTypes($iris, $io);
                default:
                    $io->error("Unknown action: {$action}");
                    $io->text("Available actions: list, connect, disconnect, test, status, types");
                    return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $io->error($e->getMessage());
            if ($output->isVerbose()) {
                $io->text($e->getTraceAsString());
            }
            return Command::FAILURE;
        }
    }
    
    private function listIntegrations(IRIS $iris, SymfonyStyle $io): int
    {
        $io->title('🔗 Your Connected Integrations');
        
        $integrations = $iris->integrations->list();
        
        if ($integrations->isEmpty()) {
            $io->warning('No integrations connected yet.');
            $io->newLine();
            $io->text([
                'Get started by connecting an integration:',
                '  <info>iris integrations connect vapi</info>',
                '  <info>iris integrations connect servis-ai</info>',
                '  <info>iris integrations connect smtp-email</info>',
                '',
                'See all available types:',
                '  <info>iris integrations types</info>',
            ]);
            return Command::SUCCESS;
        }
        
        $rows = [];
        foreach ($integrations as $integration) {
            $statusIcon = $integration->status === 'active' ? '<info>✓</info>' : '<comment>✗</comment>';
            $rows[] = [
                $integration->id,
                $integration->name,
                $integration->type,
                $integration->category,
                $statusIcon,
                $integration->created_at ?? 'N/A',
            ];
        }
        
        $io->table(
            ['ID', 'Name', 'Type', 'Category', 'Status', 'Created'],
            $rows
        );
        
        $io->newLine();
        $io->text(sprintf('Total: <info>%d</info> integration(s)', count($integrations)));
        
        return Command::SUCCESS;
    }
    
    private function connectIntegration(IRIS $iris, SymfonyStyle $io, InputInterface $input, OutputInterface $output): int
    {
        $type = $input->getArgument('type');
        
        if (!$type) {
            $io->error('Please specify an integration type to connect');
            $io->text('Example: <info>iris integrations connect vapi</info>');
            $io->newLine();
            $io->text('See available types: <info>iris integrations types</info>');
            return Command::FAILURE;
        }
        
        $io->section("🔌 Connecting {$type}");
        
        // Check if already connected
        $status = $iris->integrations->status($type);
        if ($status['connected']) {
            $io->warning("Already connected to {$type}");
            $helper = $this->getHelper('question');
            $question = new Question('Disconnect and reconnect? (yes/no) ', 'no');
            $answer = $helper->ask($input, $output, $question);
            
            if (strtolower($answer) !== 'yes') {
                return Command::SUCCESS;
            }
            
            $iris->integrations->disconnect($type);
            $io->text("✓ Disconnected from {$type}");
        }
        
        // Determine auth method
        if ($iris->integrations->usesOAuth($type)) {
            return $this->connectOAuth($iris, $io, $type);
        } else {
            return $this->connectApiKey($iris, $io, $input, $output, $type);
        }
    }
    
    private function connectOAuth(IRIS $iris, SymfonyStyle $io, string $type): int
    {
        $io->text("Starting OAuth flow for {$type}...");
        
        try {
            $flow = $iris->integrations->startOAuthFlow($type);
            
            $io->newLine();
            $io->text('📋 <info>Step 1:</info> Open this URL in your browser:');
            $io->text($flow['url']);
            $io->newLine();
            
            // Try to open in browser automatically
            if (PHP_OS_FAMILY === 'Darwin') {
                exec("open '{$flow['url']}' 2>/dev/null");
            } elseif (PHP_OS_FAMILY === 'Windows') {
                exec("start '{$flow['url']}' 2>/dev/null");
            } elseif (PHP_OS_FAMILY === 'Linux') {
                exec("xdg-open '{$flow['url']}' 2>/dev/null");
            }
            
            $io->note('After authorizing in your browser, the integration will be automatically connected.');
            $io->text('Check the status with: <info>iris integrations status ' . $type . '</info>');
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error("Failed to start OAuth flow: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
    
    private function connectApiKey(IRIS $iris, SymfonyStyle $io, InputInterface $input, OutputInterface $output, string $type): int
    {
        $helper = $this->getHelper('question');
        
        try {
            // Type-specific credential collection
            switch ($type) {
                case 'vapi':
                    $io->text('📍 Get your API key from: <comment>https://dashboard.vapi.ai</comment>');
                    $io->newLine();
                    
                    $question = new Question('Vapi API Key: ');
                    $question->setHidden(true);
                    $question->setHiddenFallback(false);
                    $apiKey = $helper->ask($input, $output, $question);
                    
                    if (!$apiKey) {
                        $io->error('API key is required');
                        return Command::FAILURE;
                    }
                    
                    $phoneQuestion = new Question('Phone Number (optional, press enter to skip): ');
                    $phoneNumber = $helper->ask($input, $output, $phoneQuestion);
                    
                    $io->text('Connecting to Vapi...');
                    $integration = $iris->integrations->connectVapi($apiKey, $phoneNumber ?: null);
                    break;
                    
                case 'servis-ai':
                    $io->text('📍 Get your credentials from your Servis.ai dashboard');
                    $io->newLine();
                    
                    $clientIdQuestion = new Question('Client ID: ');
                    $clientId = $helper->ask($input, $output, $clientIdQuestion);
                    
                    if (!$clientId) {
                        $io->error('Client ID is required');
                        return Command::FAILURE;
                    }
                    
                    $secretQuestion = new Question('Client Secret: ');
                    $secretQuestion->setHidden(true);
                    $secretQuestion->setHiddenFallback(false);
                    $clientSecret = $helper->ask($input, $output, $secretQuestion);
                    
                    if (!$clientSecret) {
                        $io->error('Client Secret is required');
                        return Command::FAILURE;
                    }
                    
                    $io->text('Connecting to Servis.ai...');
                    $integration = $iris->integrations->connectServisAi($clientId, $clientSecret);
                    break;
                    
                case 'smtp-email':
                    $io->text('📧 Configure SMTP email settings');
                    $io->newLine();
                    
                    $host = $helper->ask($input, $output, new Question('SMTP Host: '));
                    $port = $helper->ask($input, $output, new Question('SMTP Port (587): ', 587));
                    $username = $helper->ask($input, $output, new Question('Username: '));
                    
                    $passwordQuestion = new Question('Password: ');
                    $passwordQuestion->setHidden(true);
                    $passwordQuestion->setHiddenFallback(false);
                    $password = $helper->ask($input, $output, $passwordQuestion);
                    
                    $fromEmail = $helper->ask($input, $output, new Question('From Email: '));
                    $fromName = $helper->ask($input, $output, new Question('From Name: '));
                    
                    $encryptionQuestion = new ChoiceQuestion('Encryption:', ['tls', 'ssl', 'none'], 'tls');
                    $encryption = $helper->ask($input, $output, $encryptionQuestion);
                    
                    $io->text('Connecting to SMTP...');
                    $integration = $iris->integrations->connectSmtp(
                        $host, (int)$port, $username, $password,
                        $fromEmail, $fromName, $encryption
                    );
                    break;
                    
                default:
                    // Generic API key prompt
                    $io->text("📍 Enter your {$type} API credentials");
                    $io->newLine();
                    
                    $question = new Question('API Key: ');
                    $question->setHidden(true);
                    $question->setHiddenFallback(false);
                    $apiKey = $helper->ask($input, $output, $question);
                    
                    if (!$apiKey) {
                        $io->error('API key is required');
                        return Command::FAILURE;
                    }
                    
                    $io->text("Connecting to {$type}...");
                    $integration = $iris->integrations->connectWithApiKey($type, [
                        'api_key' => $apiKey,
                    ]);
            }
            
            // Test the connection
            $io->text('Testing connection...');
            $testResult = $iris->integrations->test($integration->id);
            
            if ($testResult->success) {
                $io->newLine();
                $io->success("✓ Successfully connected to {$type}!");
                $io->text("Integration ID: <info>{$integration->id}</info>");
            } else {
                $io->newLine();
                $io->error("✗ Connection test failed: " . ($testResult->message ?? 'Unknown error'));
                $io->text("The integration was created but the test failed. You may need to check your credentials.");
                return Command::FAILURE;
            }
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $io->error("Failed to connect: " . $e->getMessage());
            if ($output->isVerbose()) {
                $io->text($e->getTraceAsString());
            }
            return Command::FAILURE;
        }
    }
    
    private function disconnectIntegration(IRIS $iris, SymfonyStyle $io, InputInterface $input): int
    {
        $type = $input->getArgument('type');
        
        if (!$type) {
            $io->error('Please specify integration type to disconnect');
            $io->text('Example: <info>iris integrations disconnect vapi</info>');
            return Command::FAILURE;
        }
        
        // Check if connected
        $status = $iris->integrations->status($type);
        if (!$status['connected']) {
            $io->warning("Integration {$type} is not connected");
            return Command::SUCCESS;
        }
        
        $io->warning("⚠️  About to disconnect {$type}");
        $io->text("This will remove the integration and all its credentials.");
        $io->newLine();
        
        $helper = $this->getHelper('question');
        $question = new Question('Are you sure? (yes/no) ', 'no');
        $answer = $helper->ask($input, $io, $question);
        
        if (strtolower($answer) !== 'yes') {
            $io->text('Cancelled');
            return Command::SUCCESS;
        }
        
        try {
            if ($iris->integrations->disconnect($type)) {
                $io->success("✓ Disconnected from {$type}");
            } else {
                $io->error("Failed to disconnect {$type}");
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $io->error("Error: " . $e->getMessage());
            return Command::FAILURE;
        }
        
        return Command::SUCCESS;
    }
    
    private function testIntegration(IRIS $iris, SymfonyStyle $io, InputInterface $input): int
    {
        $type = $input->getArgument('type');
        
        if (!$type) {
            $io->error('Please specify integration type to test');
            $io->text('Example: <info>iris integrations test vapi</info>');
            return Command::FAILURE;
        }
        
        $integrations = $iris->integrations->list();
        $integration = $integrations->findByType($type);
        
        if (!$integration) {
            $io->error("Integration {$type} not found");
            $io->text("Connect it first: <info>iris integrations connect {$type}</info>");
            return Command::FAILURE;
        }
        
        $io->text("🔍 Testing {$type} connection...");
        
        try {
            $result = $iris->integrations->test($integration->id);
            
            if ($result->success) {
                $io->newLine();
                $io->success("✓ Connection test successful!");
                
                if (isset($result->data) && is_array($result->data) && !empty($result->data)) {
                    $io->newLine();
                    $io->text('<comment>Test Details:</comment>');
                    foreach ($result->data as $key => $value) {
                        if (is_scalar($value)) {
                            $io->text("  {$key}: <info>{$value}</info>");
                        }
                    }
                }
            } else {
                $io->newLine();
                $io->error("✗ Connection test failed: " . ($result->message ?? 'Unknown error'));
                return Command::FAILURE;
            }
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $io->error("Test failed: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
    
    private function showStatus(IRIS $iris, SymfonyStyle $io, InputInterface $input): int
    {
        $type = $input->getArgument('type');
        
        if ($type) {
            // Show status for specific integration
            $status = $iris->integrations->status($type);
            
            $io->title("Status: {$type}");
            
            if ($status['connected']) {
                $integration = $status['integration'];
                
                $io->success('Connected ✓');
                $io->newLine();
                $io->definitionList(
                    ['ID' => $integration->id],
                    ['Name' => $integration->name],
                    ['Type' => $integration->type],
                    ['Category' => $integration->category],
                    ['Status' => $integration->status],
                    ['Created' => $integration->created_at ?? 'N/A']
                );
            } else {
                $io->warning('Not connected ✗');
                $io->newLine();
                $io->text("Run: <info>iris integrations connect {$type}</info>");
            }
        } else {
            // Show overview of all integrations
            return $this->listIntegrations($iris, $io);
        }
        
        return Command::SUCCESS;
    }
    
    private function showTypes(IRIS $iris, SymfonyStyle $io): int
    {
        $io->title('📦 Available Integration Types');
        
        try {
            $response = $iris->integrations->types();
            $types = $response['data'] ?? $response;
            
            if (empty($types)) {
                $io->warning('No integration types available');
                return Command::SUCCESS;
            }
            
            $rows = [];
            foreach ($types as $typeKey => $typeInfo) {
                $authMethod = $iris->integrations->usesOAuth($typeKey) ? 'OAuth' : 'API Key';
                
                $rows[] = [
                    $typeKey,
                    $typeInfo['name'] ?? ucfirst($typeKey),
                    $typeInfo['category'] ?? 'other',
                    $authMethod,
                    substr($typeInfo['description'] ?? '', 0, 50) . '...',
                ];
            }
            
            $io->table(
                ['Type', 'Name', 'Category', 'Auth', 'Description'],
                $rows
            );
            
            $io->newLine();
            $io->text([
                'To connect an integration:',
                '  <info>iris integrations connect <type></info>',
                '',
                'Example:',
                '  <info>iris integrations connect vapi</info>',
            ]);
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $io->error("Failed to fetch integration types: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
