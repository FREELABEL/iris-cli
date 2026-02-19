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
 * Users Management Command
 * 
 * Search, view, and create users in the FL-API system.
 */
class UsersCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('users')
            ->setDescription('Manage users in FL-API')
            ->setHelp('Search for users, view user details, and create new user accounts')
            ->addArgument('action', InputArgument::OPTIONAL, 'Action: search, get, create', 'search')
            ->addArgument('query', InputArgument::OPTIONAL, 'Search query or user ID')
            ->addOption('email', 'e', InputOption::VALUE_REQUIRED, 'User email')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Full name')
            ->addOption('phone', null, InputOption::VALUE_REQUIRED, 'Phone number')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Password for new user')
            ->addOption('account-type', null, InputOption::VALUE_REQUIRED, 'Account type', 'business')
            ->addOption('api-key', null, InputOption::VALUE_REQUIRED, 'API key for authentication')
            ->addOption('user-id', null, InputOption::VALUE_REQUIRED, 'User ID')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON');
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getArgument('action') ?? 'search';
        
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
            $jsonOutput = $input->getOption('json');
            
            switch ($action) {
                case 'search':
                    return $this->searchUsers($iris, $input, $output, $io, $jsonOutput);
                    
                case 'get':
                    return $this->getUser($iris, $input, $output, $io, $jsonOutput);
                    
                case 'create':
                    return $this->createUser($iris, $input, $output, $io, $jsonOutput);
                    
                default:
                    $io->error("Unknown action: {$action}. Use: search, get, or create");
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
    
    protected function searchUsers(IRIS $iris, InputInterface $input, OutputInterface $output, SymfonyStyle $io, bool $jsonOutput): int
    {
        $query = $input->getArgument('query');
        
        if (!$query) {
            $io->error('Please provide a search query');
            return Command::FAILURE;
        }
        
        $io->title("🔍 Searching Users");
        $io->text("Query: <info>{$query}</info>");
        $io->newLine();
        
        // Call the SDK to search users
        $users = $iris->users->search($query);
        
        if (empty($users)) {
            $io->warning('No users found');
            return Command::SUCCESS;
        }
        
        if ($jsonOutput) {
            $output->writeln(json_encode($users, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }
        
        // Display results
        $io->success(sprintf('Found %d user(s)', count($users)));
        $io->newLine();
        
        foreach ($users as $user) {
            $this->displayUser($user, $io);
            $io->newLine();
        }
        
        return Command::SUCCESS;
    }
    
    protected function getUser(IRIS $iris, InputInterface $input, OutputInterface $output, SymfonyStyle $io, bool $jsonOutput): int
    {
        $userId = $input->getArgument('query');
        
        if (!$userId) {
            $io->error('Please provide a user ID');
            return Command::FAILURE;
        }
        
        $io->title("👤 User Details");
        
        $user = $iris->users->get((int)$userId);
        
        if ($jsonOutput) {
            $output->writeln(json_encode($user, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }
        
        $this->displayUser($user, $io);
        
        return Command::SUCCESS;
    }
    
    protected function createUser(IRIS $iris, InputInterface $input, OutputInterface $output, SymfonyStyle $io, bool $jsonOutput): int
    {
        $email = $input->getOption('email');
        $name = $input->getOption('name');
        $phone = $input->getOption('phone');
        $password = $input->getOption('password');
        $accountType = $input->getOption('account-type');
        
        if (!$email) {
            $io->error('Email is required (use --email)');
            return Command::FAILURE;
        }
        
        if (!$name) {
            $io->error('Name is required (use --name)');
            return Command::FAILURE;
        }
        
        $io->title("✨ Creating New User");
        
        $userData = [
            'email' => $email,
            'full_name' => $name,
            'account_type' => $accountType,
        ];
        
        if ($phone) {
            $userData['phone'] = $phone;
        }
        
        if ($password) {
            $userData['password'] = $password;
        }
        
        $io->definitionList(
            ['Email' => $email],
            ['Name' => $name],
            ['Phone' => $phone ?? 'Not provided'],
            ['Account Type' => $accountType]
        );
        
        $io->newLine();
        
        if (!$io->confirm('Create this user?', false)) {
            $io->note('User creation cancelled');
            return Command::SUCCESS;
        }
        
        $user = $iris->users->create($userData);
        
        if ($jsonOutput) {
            $output->writeln(json_encode($user, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }
        
        $io->success('User created successfully!');
        $io->newLine();
        
        $this->displayUser($user, $io);
        $this->displayLoginInstructions($user, $io);
        
        return Command::SUCCESS;
    }
    
    protected function displayUser(array $user, SymfonyStyle $io): void
    {
        $io->section('User Information');
        
        $info = [
            ['ID' => $user['id'] ?? 'N/A'],
            ['Name' => $user['full_name'] ?? $user['user_name'] ?? 'N/A'],
            ['Email' => $user['email'] ?? 'N/A'],
            ['Phone' => $user['phone'] ?? 'Not set'],
            ['Username' => $user['user_name'] ?? 'N/A'],
            ['Account Type' => $user['account_type'] ?? 'N/A'],
            ['Status' => $user['status'] ?? 'N/A'],
            ['Created' => isset($user['date_created']) ? date('M j, Y', strtotime($user['date_created'])) : 'N/A'],
        ];
        
        if (isset($user['last_login'])) {
            $info[] = ['Last Login' => date('M j, Y g:i A', strtotime($user['last_login']))];
        }
        
        $io->definitionList(...$info);
    }
    
    protected function displayLoginInstructions(array $user, SymfonyStyle $io): void
    {
        $io->section('📋 Login Instructions');
        
        $io->text([
            '<info>Dashboard Access:</info>',
            '',
            '1. Go to: <href=https://app.heyiris.io/login>https://app.heyiris.io/login</>',
            '',
            '2. Login with:',
            '   Email: <comment>' . ($user['email'] ?? 'N/A') . '</comment>',
            '   Password: <comment>[provided separately]</comment>',
            '',
            '<info>View Call Logs:</info>',
            '',
            '1. After login, click on "Call Logs" in the sidebar',
            '2. Or visit: <href=https://app.heyiris.io/call-logs>https://app.heyiris.io/call-logs</>',
            '',
            '3. Filter by:',
            '   • Date range',
            '   • Agent/Phone number',
            '   • Call status (completed, missed, etc.)',
            '',
            '<info>Features Available:</info>',
            '',
            '• View all inbound/outbound calls',
            '• Listen to call recordings',
            '• Read call transcripts',
            '• See call duration and costs',
            '• Export call data (CSV/Excel)',
            '• Search calls by keywords',
        ]);
    }
}
