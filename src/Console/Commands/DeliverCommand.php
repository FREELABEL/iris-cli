<?php

namespace IRIS\SDK\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use IRIS\SDK\IRIS;
use IRIS\SDK\Config;

/**
 * Deliver Command
 *
 * Execute a workflow and deliver the result to a lead in one command.
 * This is the unified delivery command that chains workflow execution,
 * deliverable creation, and email notification.
 *
 * Usage:
 *   iris deliver <lead_id> <workflow> [--input={}] [--no-email] [--subject="..."]
 *
 * Examples:
 *   iris deliver 522 newsletter-generator --input='{"topic":"AI for Law Firms"}'
 *   iris deliver 123 social-media-post --input='{"platform":"linkedin","topic":"Q4 Results"}' --no-email
 *   iris deliver 456 contract-review --subject="Your Contract Review is Ready"
 */
class DeliverCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('deliver')
            ->setDescription('Execute a workflow and deliver result to a lead')
            ->setHelp(<<<'HELP'
Execute a callable workflow and deliver the result to a lead.

This command chains together:
1. Execute the callable workflow
2. Create a deliverable from the result
3. Send a beautiful email notification (optional)

Examples:
  <info>iris deliver 522 newsletter-generator --input='{"topic":"AI for Law"}'</info>
  <info>iris deliver 123 social-media-post --no-email</info>
  <info>iris deliver 456 contract-review --subject="Your Review is Ready"</info>

The command returns a summary showing:
- Workflow execution result
- Created deliverable ID and URL
- Email delivery status
- Time to value (how long the whole process took)
HELP
            )
            ->addArgument('lead_id', InputArgument::REQUIRED, 'Lead ID to deliver to')
            ->addArgument('workflow', InputArgument::REQUIRED, 'Callable workflow name (e.g., newsletter-generator)')
            ->addOption('input', 'i', InputOption::VALUE_REQUIRED, 'Workflow input as JSON', '{}')
            ->addOption('no-email', null, InputOption::VALUE_NONE, 'Skip email notification')
            ->addOption('subject', 's', InputOption::VALUE_REQUIRED, 'Custom email subject')
            ->addOption('recipients', 'r', InputOption::VALUE_REQUIRED, 'Override recipient emails (comma-separated)')
            ->addOption('title', 't', InputOption::VALUE_REQUIRED, 'Custom deliverable title')
            ->addOption('context', 'c', InputOption::VALUE_REQUIRED, 'Custom context for AI email generation')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON')
            ->addOption('api-key', null, InputOption::VALUE_REQUIRED, 'API key')
            ->addOption('user-id', null, InputOption::VALUE_REQUIRED, 'User ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            // Build config options
            $configOptions = [];
            if ($apiKey = $input->getOption('api-key')) {
                $configOptions['api_key'] = $apiKey;
            }
            if ($userId = $input->getOption('user-id')) {
                $configOptions['user_id'] = (int)$userId;
            }

            $iris = new IRIS($configOptions);

            // Parse arguments
            $leadId = (int) $input->getArgument('lead_id');
            $workflowName = $input->getArgument('workflow');

            // Parse input JSON
            $inputJson = $input->getOption('input');
            $workflowInput = json_decode($inputJson, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \InvalidArgumentException("Invalid JSON for --input: " . json_last_error_msg());
            }

            // Build options
            $options = [
                'send_email' => !$input->getOption('no-email'),
                'message_mode' => 'ai',
                'include_project_context' => true,
            ];

            if ($subject = $input->getOption('subject')) {
                $options['email_subject'] = $subject;
            }

            if ($recipients = $input->getOption('recipients')) {
                $options['recipient_emails'] = array_map('trim', explode(',', $recipients));
            }

            if ($title = $input->getOption('title')) {
                $options['deliverable_title'] = $title;
            }

            if ($context = $input->getOption('context')) {
                $options['custom_context'] = $context;
            }

            // Show progress
            if (!$input->getOption('json')) {
                $io->title('IRIS Workflow Delivery');
                $io->text([
                    "Lead ID: <info>{$leadId}</info>",
                    "Workflow: <info>{$workflowName}</info>",
                    "Send Email: <info>" . ($options['send_email'] ? 'Yes' : 'No') . "</info>",
                ]);
                $io->newLine();
                $io->text('Executing workflow and delivering...');
            }

            // Execute the delivery
            $result = $iris->workflows->deliverToLead(
                $leadId,
                $workflowName,
                $workflowInput,
                $options
            );

            // Output result
            if ($input->getOption('json')) {
                $output->writeln(json_encode($result->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            } else {
                $this->renderResult($result, $io, $output);
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            if ($input->getOption('json')) {
                $output->writeln(json_encode([
                    'success' => false,
                    'error' => $e->getMessage(),
                ], JSON_PRETTY_PRINT));
            } else {
                $io->error($e->getMessage());
                if ($output->isVerbose()) {
                    $io->text($e->getTraceAsString());
                }
            }
            return Command::FAILURE;
        }
    }

    private function renderResult($result, SymfonyStyle $io, OutputInterface $output): void
    {
        $io->newLine();

        if ($result->success) {
            $io->success('Delivery completed successfully!');
        } else {
            $io->error('Delivery failed');
            return;
        }

        // Summary table
        $io->section('Delivery Summary');

        $rows = [
            ['Workflow', $result->workflowName],
            ['Lead ID', "#{$result->leadId}"],
            ['Execution ID', $result->executionId ?? 'N/A'],
            ['Deliverable ID', $result->deliverableId ? "#{$result->deliverableId}" : 'N/A'],
            ['Deliverable URL', $result->deliverableUrl],
        ];

        if ($result->emailSent) {
            $rows[] = ['Email Sent', 'Yes'];
            $rows[] = ['Recipients', implode(', ', $result->emailSentTo)];
        } else {
            $rows[] = ['Email Sent', 'No'];
        }

        $rows[] = ['Time to Value', "{$result->timeToValueSeconds}s"];

        $io->table(['Field', 'Value'], $rows);

        // Show workflow output preview if available
        if ($result->workflowOutput) {
            $io->section('Workflow Output Preview');

            $preview = is_string($result->workflowOutput)
                ? $result->workflowOutput
                : json_encode($result->workflowOutput, JSON_PRETTY_PRINT);

            if (strlen($preview) > 500) {
                $preview = substr($preview, 0, 500) . '...';
            }

            $io->text($preview);
        }

        // Next steps
        $io->newLine();
        $io->text('<fg=yellow>Next steps:</>');
        $io->listing([
            "View deliverable: <info>{$result->deliverableUrl}</info>",
            "View lead: <info>iris sdk:call leads.get {$result->leadId} --json</info>",
            "List deliverables: <info>iris sdk:call leads.deliverables.list {$result->leadId}</info>",
        ]);
    }
}
