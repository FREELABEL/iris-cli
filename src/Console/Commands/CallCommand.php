<?php

declare(strict_types=1);

namespace IRIS\SDK\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use IRIS\SDK\IRIS;

/**
 * Call Command
 *
 * Initiate and manage outbound phone calls via VAPI (AI voice agent) or Twilio.
 *
 * Usage:
 *   iris call make <lead_id>                    # Call lead using default provider
 *   iris call make <lead_id> --agent=335        # Call with specific agent (uses VAPI)
 *   iris call make <lead_id> --provider=twilio  # Force Twilio (simple call)
 *   iris call make --phone=+15125200221         # Call arbitrary number
 *   iris call list                              # Recent calls
 *   iris call list --lead=9805                  # Calls for specific lead
 *   iris call status <call_sid>                 # Check call status
 */
class CallCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('outreach:call')
            ->setDescription('Initiate and manage outbound phone calls for outreach')
            ->setHelp('Make calls via VAPI (AI voice agent) or Twilio as part of the outreach pipeline.')
            ->addArgument('action', InputArgument::OPTIONAL, 'Action: make, list, status', 'list')
            ->addArgument('target', InputArgument::OPTIONAL, 'Lead ID (for make) or Call SID (for status)')
            ->addOption('agent', 'a', InputOption::VALUE_REQUIRED, 'Agent ID (for VAPI AI calls)')
            ->addOption('phone', null, InputOption::VALUE_REQUIRED, 'Phone number to call (E.164 format)')
            ->addOption('provider', 'p', InputOption::VALUE_REQUIRED, 'Provider: vapi or twilio')
            ->addOption('purpose', null, InputOption::VALUE_REQUIRED, 'Purpose/goal of the call')
            ->addOption('script', null, InputOption::VALUE_REQUIRED, 'Call script (for Twilio TTS)')
            ->addOption('lead', null, InputOption::VALUE_REQUIRED, 'Filter calls by lead ID (for list)')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Number of calls to list', '20')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON')
            ->addOption('api-key', null, InputOption::VALUE_REQUIRED, 'API key')
            ->addOption('user-id', null, InputOption::VALUE_REQUIRED, 'User ID')
            ->addOption('env', null, InputOption::VALUE_REQUIRED, 'Environment: local or production');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getArgument('action') ?? 'list';

        try {
            $configOptions = [];
            if ($apiKey = $input->getOption('api-key')) {
                $configOptions['api_key'] = $apiKey;
            }
            if ($userId = $input->getOption('user-id')) {
                $configOptions['user_id'] = (int) $userId;
            }
            if ($env = $input->getOption('env')) {
                $configOptions['environment'] = $env;
            }

            $iris = new IRIS($configOptions);

            switch ($action) {
                case 'make':
                case 'call':
                    return $this->makeCall($iris, $io, $input);

                case 'list':
                    return $this->listCalls($iris, $io, $input);

                case 'status':
                case 'get':
                    return $this->getCallStatus($iris, $io, $input);

                default:
                    $io->error("Unknown action: {$action}");
                    $io->text('Available actions: make, list, status');
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

    private function makeCall(IRIS $iris, SymfonyStyle $io, InputInterface $input): int
    {
        $target = $input->getArgument('target');
        $phone = $input->getOption('phone');
        $agentId = $input->getOption('agent');
        $provider = $input->getOption('provider');
        $purpose = $input->getOption('purpose');
        $script = $input->getOption('script');
        $isJson = $input->getOption('json');

        if (!$target && !$phone) {
            $io->error('Either a lead ID or --phone=NUMBER is required.');
            $io->text('Usage: iris call make <lead_id>');
            $io->text('       iris call make --phone=+15125200221');
            return Command::FAILURE;
        }

        // If target is a lead ID, fetch the lead first to show info
        $leadId = $target ? (int) $target : null;
        $leadName = null;
        $leadPhone = null;

        if ($leadId) {
            $lead = $iris->leads->get($leadId);
            // Lead::get() returns a Lead object with typed properties
            if (is_object($lead)) {
                $leadName = $lead->name ?? $lead->nickname ?? "Lead #{$leadId}";
                $leadPhone = $lead->phone ?? null;
            } else {
                $leadData = $lead['data'] ?? $lead;
                $leadName = $leadData['name'] ?? $leadData['nickname'] ?? "Lead #{$leadId}";
                $leadPhone = $leadData['phone'] ?? $leadData['contact_info']['phone'] ?? null;
            }

            if (!$leadPhone && !$phone) {
                $io->error("Lead #{$leadId} ({$leadName}) has no phone number.");
                $io->text('Provide one manually: iris call make ' . $leadId . ' --phone=+1XXXXXXXXXX');
                return Command::FAILURE;
            }

            $dialNumber = $phone ?? $leadPhone;
        } else {
            $dialNumber = $phone;
        }

        // Show call preview
        $io->newLine();
        $io->writeln('  <fg=white;options=bold>Call Preview</>');
        $io->writeln("  To: <info>{$dialNumber}</info>" . ($leadName ? " ({$leadName})" : ''));

        if ($agentId) {
            $providerLabel = $provider ?? 'vapi';
            $io->writeln("  Agent: <info>#{$agentId}</info> (Provider: {$providerLabel})");
        } else {
            $providerLabel = $provider ?? 'twilio';
            $io->writeln("  Provider: <info>{$providerLabel}</info>");
        }

        if ($purpose) {
            $io->writeln("  Purpose: {$purpose}");
        }
        if ($script) {
            $preview = strlen($script) > 100 ? substr($script, 0, 100) . '...' : $script;
            $io->writeln("  Script: <fg=gray>{$preview}</>");
        }

        $io->newLine();

        // Confirm before dialing
        $helper = $this->getHelper('question');
        $confirm = new ConfirmationQuestion("  Initiate call to {$dialNumber}? (y/N) ", false);
        if (!$helper->ask($input, $io, $confirm)) {
            $io->writeln('  <fg=gray>Cancelled.</>');
            return Command::SUCCESS;
        }

        // Make the call
        $params = [];
        if ($leadId) {
            $params['lead_id'] = $leadId;
        }
        if ($phone) {
            $params['phone_number'] = $phone;
        }
        if ($agentId) {
            $params['agent_id'] = (int) $agentId;
        }
        if ($provider) {
            $params['provider'] = $provider;
        }
        if ($purpose) {
            $params['purpose'] = $purpose;
        }
        if ($script) {
            $params['script'] = $script;
        }

        $result = $iris->calls->make($params);

        if ($isJson) {
            $io->writeln(json_encode($result, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        if ($result['success'] ?? false) {
            $data = $result['data'] ?? [];
            $callSid = $data['call_sid'] ?? 'dispatched';
            $callProvider = $data['provider'] ?? $providerLabel;

            $io->newLine();
            $io->success("Call initiated via {$callProvider}!");
            $io->definitionList(
                ['Call SID' => $callSid],
                ['Provider' => $callProvider],
                ['Status' => $data['status'] ?? 'initiated'],
                ['To' => $data['to'] ?? $dialNumber]
            );

            if ($callProvider === 'vapi') {
                $io->text('  <fg=gray>The AI voice agent will call shortly.</>');
            }

            return Command::SUCCESS;
        }

        $io->error($result['error'] ?? $result['message'] ?? 'Failed to initiate call.');
        return Command::FAILURE;
    }

    private function listCalls(IRIS $iris, SymfonyStyle $io, InputInterface $input): int
    {
        $leadId = $input->getOption('lead');
        $limit = (int) ($input->getOption('limit') ?? 20);
        $isJson = $input->getOption('json');

        $io->section('Recent Calls');

        $filters = ['limit' => $limit];
        if ($leadId) {
            $filters['lead_id'] = (int) $leadId;
            $io->writeln("  Filtering by lead #{$leadId}");
            $io->newLine();
        }

        $result = $iris->calls->list($filters);

        if ($isJson) {
            $io->writeln(json_encode($result, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $calls = $result['data'] ?? [];

        if (empty($calls)) {
            $io->writeln('  <fg=gray>No calls found.</>');
            $io->newLine();
            $io->text('  Make a call: <info>iris call make <lead_id></info>');
            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($calls as $call) {
            if (is_array($call)) {
                $rows[] = [
                    $call['sid'] ?? $call['call_sid'] ?? 'N/A',
                    $call['to'] ?? 'N/A',
                    $call['from'] ?? 'N/A',
                    $call['status'] ?? 'N/A',
                    $call['duration'] ?? '-',
                    $call['date_created'] ?? $call['start_time'] ?? 'N/A',
                ];
            }
        }

        $io->table(
            ['SID', 'To', 'From', 'Status', 'Duration', 'Date'],
            $rows
        );

        $io->text(sprintf('  Total: <info>%d</info> call(s)', count($calls)));

        return Command::SUCCESS;
    }

    private function getCallStatus(IRIS $iris, SymfonyStyle $io, InputInterface $input): int
    {
        $callSid = $input->getArgument('target');
        $isJson = $input->getOption('json');

        if (!$callSid) {
            $io->error('Call SID is required.');
            $io->text('Usage: iris call status <call_sid>');
            return Command::FAILURE;
        }

        $io->section("Call Status: {$callSid}");

        $result = $iris->calls->get($callSid);

        if ($isJson) {
            $io->writeln(json_encode($result, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        if ($result['success'] ?? false) {
            $data = $result['data'] ?? [];
            $io->definitionList(
                ['Call SID' => $data['call_sid'] ?? $callSid],
                ['Status' => $data['status'] ?? 'unknown'],
                ['To' => $data['to'] ?? 'N/A'],
                ['From' => $data['from'] ?? 'N/A'],
                ['Duration' => ($data['duration'] ?? '-') . 's'],
                ['Direction' => $data['direction'] ?? 'outbound']
            );
            return Command::SUCCESS;
        }

        $io->error($result['error'] ?? 'Failed to get call status.');
        return Command::FAILURE;
    }
}
