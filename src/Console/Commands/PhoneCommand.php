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
 * Phone Number Management Command
 * 
 * Provides CLI tools for managing agent phone number assignments across providers.
 */
class PhoneCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('phone')
            ->setDescription('Manage agent phone numbers')
            ->setHelp('Configure phone number assignments for agents using various providers (VAPI, Twilio, Telnyx)')
            ->addArgument('action', InputArgument::OPTIONAL, 'Action: list, search, buy, delete, configure, release, get, providers, check', 'list')
            ->addOption('agent', 'a', InputOption::VALUE_REQUIRED, 'Agent ID')
            ->addOption('phone', null, InputOption::VALUE_REQUIRED, 'Phone number ID or phone number')
            ->addOption('provider', 'p', InputOption::VALUE_REQUIRED, 'Provider name (vapi, twilio, telnyx)')
            ->addOption('all-providers', null, InputOption::VALUE_NONE, 'List phone numbers from all connected providers')
            ->addOption('area-code', null, InputOption::VALUE_REQUIRED, 'Area code for search/buy')
            ->addOption('country-code', null, InputOption::VALUE_REQUIRED, 'Country code for search/buy')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Name for purchased phone number')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Limit results for search')
            ->addOption('dynamic', null, InputOption::VALUE_NONE, 'Use dynamic assistant (VAPI)')
            ->addOption('allow-override', null, InputOption::VALUE_NONE, 'Allow agent to override settings')
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
                    return $this->listPhones($iris, $io, $input);
                case 'search':
                    return $this->searchPhones($iris, $io, $input);
                case 'buy':
                case 'purchase':
                    return $this->buyPhone($iris, $io, $input);
                case 'delete':
                case 'remove':
                    return $this->deletePhone($iris, $io, $input);
                case 'configure':
                case 'assign':
                    return $this->configurePhone($iris, $io, $input);
                case 'release':
                case 'unassign':
                    return $this->releasePhone($iris, $io, $input);
                case 'get':
                    return $this->getPhone($iris, $io, $input);
                case 'providers':
                    return $this->showProviders($iris, $io);
                case 'check':
                    return $this->checkProvider($iris, $io, $input);
                default:
                    $io->error("Unknown action: {$action}");
                    $io->text("Available actions: list, search, buy, delete, configure, release, get, providers, check");
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
    
    private function listPhones(IRIS $iris, SymfonyStyle $io, InputInterface $input): int
    {
        $provider = $input->getOption('provider');
        $allProviders = $input->getOption('all-providers');
        
        // If --all-providers flag is set, use listAll()
        if ($allProviders) {
            return $this->listAllPhones($iris, $io);
        }
        
        $title = $provider ? "📞 Phone Numbers from {$provider}" : '📞 All Phone Numbers';
        $io->section($title);
        
        $result = $iris->phone->list($provider);
        
        if ($result['success'] ?? false) {
            $phones = $result['data']['phones'] ?? $result['data'] ?? [];
            
            if (empty($phones)) {
                $io->warning('No phone numbers available');
                return Command::SUCCESS;
            }
            
            $rows = [];
            foreach ($phones as $phone) {
                if (is_array($phone)) {
                    $rows[] = [
                        $phone['id'] ?? 'N/A',
                        $phone['number'] ?? $phone['phoneNumber'] ?? 'N/A',
                        $phone['provider'] ?? $provider ?? 'N/A',
                        $phone['assignedToAgentId'] ?? '-',
                        $phone['status'] ?? 'available',
                    ];
                }
            }
            
            $io->table(
                ['Phone ID', 'Number', 'Provider', 'Assigned To', 'Status'],
                $rows
            );
            
            $io->newLine();
            $io->text(sprintf('Total: <info>%d</info> phone number(s)', count($phones)));
            $io->newLine();
            $io->text('Configure a phone: <info>iris phone configure --agent 335 --phone PHONE_ID --provider vapi</info>');
            
            return Command::SUCCESS;
        }
        
        $io->error($result['message'] ?? 'Failed to list phone numbers');
        
        if (!empty($result['available']) && $result['available'] === false) {
            $io->warning("Provider is not connected");
            $io->text('Connect it first: <info>iris integrations connect ' . ($provider ?? 'vapi') . '</info>');
        }
        
        return Command::FAILURE;
    }
    
    private function listAllPhones(IRIS $iris, SymfonyStyle $io): int
    {
        $io->section('📞 Phone Numbers from All Providers');
        
        $result = $iris->phone->listAll();
        
        if ($result['success'] ?? false) {
            $allData = $result['data'] ?? [];
            $warnings = $result['warnings'] ?? [];
            
            if (empty($allData)) {
                $io->warning('No providers available or no phone numbers found');
                return Command::SUCCESS;
            }
            
            $totalPhones = 0;
            
            // Display phones grouped by provider
            foreach ($allData as $providerName => $phones) {
                if (empty($phones)) {
                    // Show warning if provider had issues
                    if (isset($warnings[$providerName])) {
                        $io->note("{$providerName}: {$warnings[$providerName]}");
                    }
                    continue;
                }
                
                $io->text("<comment>Provider: {$providerName}</comment>");
                
                $rows = [];
                foreach ($phones as $phone) {
                    if (is_array($phone)) {
                        $rows[] = [
                            $phone['id'] ?? 'N/A',
                            $phone['number'] ?? $phone['phoneNumber'] ?? 'N/A',
                            $phone['assignedToAgentId'] ?? '-',
                            $phone['status'] ?? 'available',
                        ];
                        $totalPhones++;
                    }
                }
                
                if (!empty($rows)) {
                    $io->table(
                        ['Phone ID', 'Number', 'Assigned To', 'Status'],
                        $rows
                    );
                }
                
                $io->newLine();
            }
            
            $io->text(sprintf('Total: <info>%d</info> phone number(s) across <info>%d</info> provider(s)', 
                $totalPhones, 
                count(array_filter($allData, fn($p) => !empty($p)))
            ));
            $io->newLine();
            $io->text('Configure a phone: <info>iris phone configure --agent 335 --phone PHONE_ID --provider PROVIDER</info>');
            
            return Command::SUCCESS;
        }
        
        $io->error($result['message'] ?? 'Failed to list phone numbers from all providers');
        return Command::FAILURE;
    }
    
    private function searchPhones(IRIS $iris, SymfonyStyle $io, InputInterface $input): int
    {
        $provider = $input->getOption('provider');
        $areaCode = $input->getOption('area-code');
        $countryCode = $input->getOption('country-code');
        $limit = $input->getOption('limit');
        
        $io->section('🔍 Searching Available Phone Numbers');
        
        $filters = [];
        if ($provider) {
            $filters['provider'] = $provider;
        }
        if ($areaCode) {
            $filters['area_code'] = $areaCode;
        }
        if ($countryCode) {
            $filters['country_code'] = $countryCode;
        }
        if ($limit) {
            $filters['limit'] = (int)$limit;
        }
        
        $result = $iris->phone->search($filters);
        
        if ($result['success'] ?? false) {
            $phones = $result['data'] ?? [];
            
            if (empty($phones)) {
                $io->warning('No phone numbers found matching criteria');
                return Command::SUCCESS;
            }
            
            $rows = [];
            foreach ($phones as $phone) {
                if (is_array($phone)) {
                    $rows[] = [
                        $phone['phoneNumber'] ?? $phone['number'] ?? 'N/A',
                        $phone['locality'] ?? $phone['city'] ?? 'N/A',
                        $phone['region'] ?? $phone['state'] ?? 'N/A',
                        $phone['country'] ?? $countryCode ?? 'N/A',
                    ];
                }
            }
            
            $io->table(
                ['Phone Number', 'City', 'State/Region', 'Country'],
                $rows
            );
            
            $io->newLine();
            $io->text(sprintf('Found: <info>%d</info> available phone number(s)', count($phones)));
            $io->newLine();
            $io->text('Purchase a phone: <info>iris phone buy --phone +14155551234 --provider vapi</info>');
            
            return Command::SUCCESS;
        }
        
        $io->error($result['message'] ?? 'Failed to search phone numbers');
        return Command::FAILURE;
    }
    
    private function buyPhone(IRIS $iris, SymfonyStyle $io, InputInterface $input): int
    {
        $phoneNumber = $input->getOption('phone');
        $provider = $input->getOption('provider');
        $areaCode = $input->getOption('area-code');
        $countryCode = $input->getOption('country-code');
        $name = $input->getOption('name');
        
        if (!$phoneNumber && !$areaCode && !$countryCode) {
            $io->error('Either phone number or area code/country code is required');
            $io->text('Usage: <info>iris phone buy --phone +14155551234 --provider vapi</info>');
            $io->text('   or: <info>iris phone buy --area-code 415 --country-code US --provider vapi</info>');
            return Command::FAILURE;
        }
        
        $io->section('💳 Purchasing Phone Number');
        
        $options = [];
        if ($provider) {
            $options['provider'] = $provider;
        }
        if ($areaCode) {
            $options['area_code'] = $areaCode;
        }
        if ($countryCode) {
            $options['country_code'] = $countryCode;
        }
        if ($name) {
            $options['name'] = $name;
        }
        
        // Use phone number or let API pick based on area/country code
        $phoneNumToBuy = $phoneNumber ?? 'auto';
        
        $result = $iris->phone->buy($phoneNumToBuy, $options);
        
        if ($result['success'] ?? false) {
            $io->success("Phone number purchased successfully!");
            
            if (!empty($result['data'])) {
                $data = $result['data'];
                $io->definitionList(
                    ['Phone Number' => $data['phoneNumber'] ?? $data['number'] ?? $phoneNumber],
                    ['Phone ID' => $data['id'] ?? 'N/A'],
                    ['Provider' => $provider ?? $data['provider'] ?? 'N/A'],
                    ['Status' => $data['status'] ?? 'active']
                );
            }
            
            $io->newLine();
            $io->text('Configure for agent: <info>iris phone configure --agent AGENT_ID --phone PHONE_ID --provider ' . ($provider ?? 'vapi') . '</info>');
            
            return Command::SUCCESS;
        }
        
        $io->error($result['message'] ?? 'Failed to purchase phone number');
        return Command::FAILURE;
    }
    
    private function deletePhone(IRIS $iris, SymfonyStyle $io, InputInterface $input): int
    {
        $phoneId = $input->getOption('phone');
        $provider = $input->getOption('provider');
        
        if (!$phoneId) {
            $io->error('Phone ID is required');
            $io->text('Usage: <info>iris phone delete --phone PHONE_ID --provider vapi</info>');
            return Command::FAILURE;
        }
        
        $io->section('🗑️  Deleting Phone Number');
        
        // Confirm deletion
        $io->warning("This will permanently delete phone number: {$phoneId}");
        if (!$io->confirm('Are you sure you want to proceed?', false)) {
            $io->text('Deletion cancelled');
            return Command::SUCCESS;
        }
        
        $result = $iris->phone->delete($phoneId, $provider);
        
        if ($result['success'] ?? false) {
            $io->success("Phone number deleted successfully!");
            
            if (!empty($result['data'])) {
                $data = $result['data'];
                $io->definitionList(
                    ['Phone ID' => $phoneId],
                    ['Provider' => $provider ?? $data['provider'] ?? 'N/A'],
                    ['Status' => 'Deleted']
                );
            }
            
            return Command::SUCCESS;
        }
        
        $io->error($result['message'] ?? 'Failed to delete phone number');
        return Command::FAILURE;
    }
    
    private function configurePhone(IRIS $iris, SymfonyStyle $io, InputInterface $input): int
    {
        $agentId = $input->getOption('agent');
        $phoneId = $input->getOption('phone');
        $provider = $input->getOption('provider');
        $useDynamic = $input->getOption('dynamic');
        $allowOverride = $input->getOption('allow-override');
        
        if (!$agentId) {
            $io->error('Agent ID is required');
            $io->text('Usage: <info>iris phone configure --agent 335 --phone PHONE_ID --provider vapi</info>');
            return Command::FAILURE;
        }
        
        if (!$phoneId) {
            $io->error('Phone ID is required');
            $io->text('Usage: <info>iris phone configure --agent 335 --phone PHONE_ID --provider vapi</info>');
            return Command::FAILURE;
        }
        
        $io->section("📞 Configuring phone for Agent #{$agentId}");
        
        $options = [];
        if ($provider) {
            $options['provider'] = $provider;
        }
        if ($useDynamic) {
            $options['use_dynamic_assistant'] = true;
        }
        if ($allowOverride) {
            $options['allow_override'] = true;
        }
        
        $result = $iris->phone->configure($phoneId, (int)$agentId, $options);
        
        if ($result['success'] ?? false) {
            $io->success("Phone number configured successfully!");
            
            if (!empty($result['data'])) {
                $data = $result['data'];
                $io->definitionList(
                    ['Phone ID' => $phoneId],
                    ['Agent ID' => $agentId],
                    ['Provider' => $provider ?? $data['provider'] ?? 'N/A'],
                    ['Dynamic Assistant' => $useDynamic ? 'Yes' : 'No']
                );
            }
            
            if (!empty($result['warnings'])) {
                $io->warning('Warnings:');
                foreach ($result['warnings'] as $warning) {
                    $io->text('  • ' . $warning);
                }
            }
            
            return Command::SUCCESS;
        }
        
        $io->error($result['message'] ?? 'Failed to configure phone');
        return Command::FAILURE;
    }
    
    private function releasePhone(IRIS $iris, SymfonyStyle $io, InputInterface $input): int
    {
        $agentId = $input->getOption('agent');
        $phoneId = $input->getOption('phone');
        $provider = $input->getOption('provider');
        
        if (!$agentId) {
            $io->error('Agent ID is required');
            $io->text('Usage: <info>iris phone release --agent 335 --phone PHONE_ID</info>');
            return Command::FAILURE;
        }
        
        if (!$phoneId) {
            $io->error('Phone ID is required');
            $io->text('Usage: <info>iris phone release --agent 335 --phone PHONE_ID</info>');
            return Command::FAILURE;
        }
        
        $io->section("📞 Releasing phone from Agent #{$agentId}");
        
        $result = $iris->phone->release($phoneId, (int)$agentId, $provider);
        
        if ($result['success'] ?? false) {
            $io->success("Phone number released successfully!");
            
            if (!empty($result['data'])) {
                $data = $result['data'];
                $io->definitionList(
                    ['Phone ID' => $phoneId],
                    ['Agent ID' => $agentId],
                    ['Status' => 'Released']
                );
            }
            
            return Command::SUCCESS;
        }
        
        $io->error($result['message'] ?? 'Failed to release phone');
        return Command::FAILURE;
    }
    
    private function getPhone(IRIS $iris, SymfonyStyle $io, InputInterface $input): int
    {
        $phoneId = $input->getOption('phone');
        $provider = $input->getOption('provider');
        
        if (!$phoneId) {
            $io->error('Phone ID is required');
            $io->text('Usage: <info>iris phone get --phone PHONE_ID</info>');
            return Command::FAILURE;
        }
        
        $io->section("📞 Phone Number Details");
        
        $result = $iris->phone->get($phoneId, $provider);
        
        if ($result['success'] ?? false) {
            $phone = $result['data'] ?? [];
            
            $io->definitionList(
                ['Phone ID' => $phone['id'] ?? $phoneId],
                ['Number' => $phone['number'] ?? $phone['phoneNumber'] ?? 'N/A'],
                ['Provider' => $phone['provider'] ?? $provider ?? 'N/A'],
                ['Assigned To' => $phone['assignedToAgentId'] ?? 'Not assigned'],
                ['Status' => $phone['status'] ?? 'N/A']
            );
            
            if (!empty($phone['settings'])) {
                $io->newLine();
                $io->text('<comment>Settings:</comment>');
                $io->text(json_encode($phone['settings'], JSON_PRETTY_PRINT));
            }
            
            return Command::SUCCESS;
        }
        
        $io->error($result['message'] ?? 'Failed to get phone details');
        return Command::FAILURE;
    }
    
    private function showProviders(IRIS $iris, SymfonyStyle $io): int
    {
        $io->section('📞 Available Phone Providers');
        
        $result = $iris->phone->getProviders();
        
        if ($result['success'] ?? false) {
            $providers = $result['data'] ?? [];
            
            $rows = [];
            foreach ($providers as $name => $info) {
                $supports = implode(', ', $info['supports'] ?? []);
                $rows[] = [
                    $name,
                    $info['integration_type'] ?? $name,
                    $supports,
                ];
            }
            
            $io->table(
                ['Provider', 'Integration Type', 'Supports'],
                $rows
            );
            
            $io->newLine();
            $io->text('Check provider status: <info>iris phone check --provider vapi</info>');
            $io->text('List phone numbers: <info>iris phone list --provider vapi</info>');
            
            return Command::SUCCESS;
        }
        
        $io->error('Failed to fetch providers');
        return Command::FAILURE;
    }
    
    private function checkProvider(IRIS $iris, SymfonyStyle $io, InputInterface $input): int
    {
        $provider = $input->getOption('provider');
        
        if (!$provider) {
            $io->error('Provider is required');
            $io->text('Usage: <info>iris phone check --provider vapi</info>');
            return Command::FAILURE;
        }
        
        $io->section("🔍 Checking {$provider} Provider");
        
        $available = $iris->phone->isProviderAvailable($provider);
        
        if ($available) {
            $io->success("✓ {$provider} is connected and available");
            
            $status = $iris->phone->getProviderStatus($provider);
            if (!empty($status['integration'])) {
                $io->definitionList(
                    ['Integration ID' => $status['integration']['id'] ?? 'N/A'],
                    ['Status' => $status['integration']['status'] ?? 'N/A']
                );
            }
            
            return Command::SUCCESS;
        }
        
        $io->warning("✗ {$provider} is not connected");
        $io->text('Connect it first: <info>iris integrations connect ' . $provider . '</info>');
        
        return Command::FAILURE;
    }
}
