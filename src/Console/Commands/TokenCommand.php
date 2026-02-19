<?php

namespace IRIS\SDK\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use IRIS\SDK\IRIS;

/**
 * Token Management Command
 * 
 * Get, generate, and manage SDK authentication tokens.
 */
class TokenCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('token')
            ->setDescription('Manage SDK authentication tokens')
            ->setHelp('Get current token, generate new token, or validate token')
            ->addArgument('action', InputArgument::OPTIONAL, 'Action: show, generate, validate', 'show')
            ->addOption('email', 'e', InputOption::VALUE_REQUIRED, 'User email for token generation')
            ->addOption('password', 'p', InputOption::VALUE_REQUIRED, 'User password for token generation')
            ->addOption('token', 't', InputOption::VALUE_REQUIRED, 'Token to validate')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON');
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getArgument('action') ?? 'show';
        
        try {
            switch ($action) {
                case 'show':
                    return $this->showCurrentToken($input, $output, $io);
                    
                case 'generate':
                    return $this->generateToken($input, $output, $io);
                    
                case 'validate':
                    return $this->validateToken($input, $output, $io);
                    
                default:
                    $io->error("Unknown action: {$action}. Use: show, generate, or validate");
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
    
    protected function showCurrentToken(InputInterface $input, OutputInterface $output, SymfonyStyle $io): int
    {
        $jsonOutput = $input->getOption('json');
        
        // Try to load token from .env
        $envFile = dirname(__DIR__, 3) . '/.env';
        
        if (!file_exists($envFile)) {
            $io->error('.env file not found. Run: cp .env.example .env');
            return Command::FAILURE;
        }
        
        $envContent = file_get_contents($envFile);
        $apiKey = null;
        $userId = null;
        $environment = null;
        
        // Parse .env
        if (preg_match('/^IRIS_API_KEY=(.*)$/m', $envContent, $matches)) {
            $apiKey = trim($matches[1]);
        }
        if (preg_match('/^IRIS_USER_ID=(.*)$/m', $envContent, $matches)) {
            $userId = trim($matches[1]);
        }
        if (preg_match('/^IRIS_ENV=(.*)$/m', $envContent, $matches)) {
            $environment = trim($matches[1]);
        }
        
        if ($jsonOutput) {
            $output->writeln(json_encode([
                'api_key' => $apiKey,
                'user_id' => $userId,
                'environment' => $environment,
            ], JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }
        
        $io->title('🔑 Current SDK Token');
        
        if (!$apiKey) {
            $io->warning('No API key found in .env file');
            $io->newLine();
            $io->text([
                'To get your API key:',
                '',
                '1. <info>From Browser (Recommended):</info>',
                '   • Go to: https://app.heyiris.io',
                '   • Open browser console (F12)',
                '   • Run: <comment>localStorage.getItem("authToken")</comment>',
                '   • Copy the token',
                '',
                '2. <info>Generate New Token:</info>',
                '   • Run: <comment>./bin/iris token generate --email=your@email.com --password=yourpass</comment>',
                '',
                '3. <info>Add to .env:</info>',
                '   • Open: .env',
                '   • Set: <comment>IRIS_API_KEY=your_token_here</comment>',
                '   • Set: <comment>IRIS_USER_ID=your_user_id</comment>',
            ]);
            return Command::FAILURE;
        }
        
        // Mask the token for security
        $maskedKey = substr($apiKey, 0, 8) . '****' . substr($apiKey, -4);
        
        $io->definitionList(
            ['Environment' => $environment ?? 'local'],
            ['API Key' => $maskedKey],
            ['User ID' => $userId ?? 'Not set'],
            ['Full Token Length' => strlen($apiKey) . ' characters']
        );
        
        $io->newLine();
        $io->text([
            '<info>Token Status:</info> ' . ($apiKey ? '✓ Set' : '✗ Missing'),
            '<info>User ID Status:</info> ' . ($userId ? '✓ Set' : '✗ Missing'),
        ]);
        
        if ($apiKey && $userId) {
            $io->newLine();
            $io->success('SDK is configured and ready to use!');
        } else {
            $io->newLine();
            $io->warning('Configuration incomplete. Set both IRIS_API_KEY and IRIS_USER_ID in .env');
        }
        
        return Command::SUCCESS;
    }
    
    protected function generateToken(InputInterface $input, OutputInterface $output, SymfonyStyle $io): int
    {
        $email = $input->getOption('email');
        $password = $input->getOption('password');
        $jsonOutput = $input->getOption('json');
        
        if (!$email || !$password) {
            $io->error('Email and password are required');
            $io->text('Usage: ./bin/iris token generate --email=user@example.com --password=yourpass');
            return Command::FAILURE;
        }
        
        $io->title('🔐 Generating SDK Token');
        $io->text('Authenticating with FL-API...');
        
        // Authenticate with FL-API
        try {
            $iris = new IRIS([]);
            
            // Make a direct HTTP request to login endpoint
            $ch = curl_init('https://apiv2.heyiris.io/api/v1/auth/login');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode([
                    'email' => $email,
                    'password' => $password,
                ]),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                ],
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200) {
                $io->error('Authentication failed. Check email and password.');
                return Command::FAILURE;
            }
            
            $data = json_decode($response, true);
            $token = $data['data']['token'] ?? $data['token'] ?? null;
            $userId = $data['data']['user_id'] ?? $data['user']['id'] ?? null;
            
            if (!$token) {
                $io->error('Failed to retrieve token from response');
                return Command::FAILURE;
            }
            
            if ($jsonOutput) {
                $output->writeln(json_encode([
                    'token' => $token,
                    'user_id' => $userId,
                ], JSON_PRETTY_PRINT));
                return Command::SUCCESS;
            }
            
            $io->success('Token generated successfully!');
            $io->newLine();
            
            $io->definitionList(
                ['Token' => substr($token, 0, 20) . '...' . substr($token, -10)],
                ['User ID' => $userId ?? 'N/A']
            );
            
            $io->newLine();
            $io->section('📝 Update your .env file:');
            
            $io->text([
                '<comment>IRIS_API_KEY=' . $token . '</comment>',
                '<comment>IRIS_USER_ID=' . $userId . '</comment>',
            ]);
            
            $io->newLine();
            
            if ($io->confirm('Would you like to automatically update .env?', false)) {
                $envFile = dirname(__DIR__, 3) . '/.env';
                $envContent = file_get_contents($envFile);
                
                // Update or add IRIS_API_KEY
                if (preg_match('/^IRIS_API_KEY=.*$/m', $envContent)) {
                    $envContent = preg_replace('/^IRIS_API_KEY=.*$/m', 'IRIS_API_KEY=' . $token, $envContent);
                } else {
                    $envContent .= "\nIRIS_API_KEY=" . $token;
                }
                
                // Update or add IRIS_USER_ID
                if (preg_match('/^IRIS_USER_ID=.*$/m', $envContent)) {
                    $envContent = preg_replace('/^IRIS_USER_ID=.*$/m', 'IRIS_USER_ID=' . $userId, $envContent);
                } else {
                    $envContent .= "\nIRIS_USER_ID=" . $userId;
                }
                
                file_put_contents($envFile, $envContent);
                
                $io->success('.env file updated! SDK is ready to use.');
            }
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $io->error('Token generation failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
    
    protected function validateToken(InputInterface $input, OutputInterface $output, SymfonyStyle $io): int
    {
        $token = $input->getOption('token');
        $jsonOutput = $input->getOption('json');
        
        if (!$token) {
            // Use token from .env if not provided
            $envFile = dirname(__DIR__, 3) . '/.env';
            if (file_exists($envFile)) {
                $envContent = file_get_contents($envFile);
                if (preg_match('/^IRIS_API_KEY=(.*)$/m', $envContent, $matches)) {
                    $token = trim($matches[1]);
                }
            }
        }
        
        if (!$token) {
            $io->error('No token provided. Use --token or set IRIS_API_KEY in .env');
            return Command::FAILURE;
        }
        
        $io->title('✅ Validating Token');
        $io->text('Testing token against FL-API...');
        
        try {
            // Test the token with a simple API call
            $ch = curl_init('https://apiv2.heyiris.io/api/v1/user/me');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $token,
                    'Accept: application/json',
                ],
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $valid = ($httpCode === 200);
            $data = json_decode($response, true);
            
            if ($jsonOutput) {
                $output->writeln(json_encode([
                    'valid' => $valid,
                    'http_code' => $httpCode,
                    'user' => $data['data'] ?? null,
                ], JSON_PRETTY_PRINT));
                return Command::SUCCESS;
            }
            
            if ($valid) {
                $io->success('Token is valid!');
                
                if (isset($data['data'])) {
                    $user = $data['data'];
                    $io->newLine();
                    $io->section('👤 User Information');
                    $io->definitionList(
                        ['User ID' => $user['id'] ?? 'N/A'],
                        ['Name' => $user['full_name'] ?? $user['name'] ?? 'N/A'],
                        ['Email' => $user['email'] ?? 'N/A']
                    );
                }
                
                return Command::SUCCESS;
            } else {
                $io->error('Token is invalid or expired');
                $io->text('HTTP Code: ' . $httpCode);
                return Command::FAILURE;
            }
            
        } catch (\Exception $e) {
            $io->error('Validation failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
