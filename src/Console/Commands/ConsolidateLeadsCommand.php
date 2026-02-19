<?php

namespace IRIS\SDK\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use IRIS\SDK\IRIS;

class ConsolidateLeadsCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('leads:consolidate')
            ->setDescription('Consolidate duplicate leads into a single primary lead')
            ->addArgument('email', InputArgument::REQUIRED, 'Email of duplicate leads')
            ->addArgument('primary-id', InputArgument::REQUIRED, 'ID of primary lead to keep');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = $input->getArgument('email');
        $primaryId = $input->getArgument('primary-id');

        $iris = new IRIS([
            'api_key' => $_ENV['IRIS_API_KEY'] ?? null,
            'user_id' => $_ENV['IRIS_USER_ID'] ?? null,
            'environment' => $_ENV['IRIS_ENV'] ?? 'production'
        ]);

        $output->writeln("<info>🔍 Finding duplicate leads for: {$email}</info>");
        $output->writeln("<info>📌 Primary lead ID: {$primaryId}</info>");

        // Find all leads with the email
        $allLeads = $iris->leads->search([
            'search' => $email,
            'per_page' => 100
        ]);

        if (empty($allLeads)) {
            $output->writeln("<error>❌ No leads found for email: {$email}</error>");
            return 1;
        }

        $duplicates = [];
        foreach ($allLeads as $lead) {
            if ($lead->id != $primaryId) {
                $duplicates[] = $lead;
            }
        }

        if (empty($duplicates)) {
            $output->writeln("<info>✅ No duplicate leads found</info>");
            return 0;
        }

        $output->writeln("<info>📋 Found " . count($duplicates) . " duplicate leads to consolidate:</info>");
        
        foreach ($duplicates as $duplicate) {
            $output->writeln("   - Lead #{$duplicate->id}: {$duplicate->nickname}");
        }

        // Create consolidation note for primary lead
        $consolidationNote = "🔗 LEAD CONSOLIDATION SUMMARY\n\n" .
                             "Date: " . date('Y-m-d H:i:s') . "\n" .
                             "Primary Lead: #{$primaryId}\n" .
                             "Duplicates Consolidated: " . count($duplicates) . "\n\n" .
                             "Duplicate Lead Details:\n";
        
        foreach ($duplicates as $duplicate) {
            $consolidationNote .= "- Lead #{$duplicate->id}: {$duplicate->nickname} (Status: {$duplicate->status})\n";
        }

        try {
            $iris->leads->addNote($primaryId, $consolidationNote);
            $output->writeln("<info>✅ Consolidation note added to primary lead</info>");
        } catch (\Exception $e) {
            $output->writeln("<error>❌ Failed to add consolidation note: {$e->getMessage()}</error>");
            return 1;
        }

        $output->writeln("<info>🎯 Consolidation complete!</info>");
        $output->writeln("<info>📊 Primary Lead #{$primaryId} now contains consolidation history</info>");
        
        return 0;
    }
}