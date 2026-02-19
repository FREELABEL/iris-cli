<?php

declare(strict_types=1);

namespace IRIS\SDK\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use IRIS\SDK\IRIS;
use IRIS\SDK\Auth\CredentialStore;

/**
 * CLI command for viewing Stripe payment history for leads.
 *
 * Usage:
 *   iris payments <lead_id>              # Show payment details
 *   iris payments <lead_id> --json       # JSON output
 *   iris payments <lead_id> --summary    # Summary only
 */
class PaymentsCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('payments')
            ->setDescription('View Stripe payment history for a lead')
            ->setHelp(<<<HELP
View comprehensive Stripe payment information for a lead, including:
- Customer details
- Invoices (paid, pending, overdue)
- Payment transactions
- Checkout sessions
- Total revenue

<info>Usage:</info>
  iris payments 110              View payment details for lead #110
  iris payments 110 --json       Get JSON output for automation
  iris payments 110 --summary    Show summary only (quick overview)

<info>Examples:</info>
  # View full payment history
  iris payments 110

  # Get summary for quick check
  iris payments 110 --summary

  # JSON output for scripting
  iris payments 110 --json | jq '.summary'

<info>What you'll see:</info>
  • Customer Information (name, email, Stripe ID)
  • Invoice List (status, amounts, dates)
  • Payment Transactions (card details, amounts)
  • Financial Summary (totals, counts)
  • Download Links (PDF invoices, hosted pages)
HELP
            )
            ->addArgument('lead_id', InputArgument::REQUIRED, 'Lead ID to check payments for')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON')
            ->addOption('summary', 's', InputOption::VALUE_NONE, 'Show summary only');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $store = new CredentialStore();

        if (!$store->hasMinimumCredentials()) {
            $io->error('SDK not configured. Run: ./bin/iris setup');
            return Command::FAILURE;
        }

        $leadId = (int) $input->getArgument('lead_id');
        $jsonOutput = $input->getOption('json');
        $summaryOnly = $input->getOption('summary');

        try {
            $config = $store->toConfigArray();
            $iris = new IRIS($config);

            $payments = $iris->leads->stripePayments($leadId);

            if ($jsonOutput) {
                $output->writeln(json_encode($payments, JSON_PRETTY_PRINT));
                return Command::SUCCESS;
            }

            if ($summaryOnly) {
                return $this->displaySummary($io, $payments);
            }

            return $this->displayFull($io, $payments);

        } catch (\Exception $e) {
            $io->error('Failed to fetch payment data: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Display full payment details.
     */
    private function displayFull(SymfonyStyle $io, array $payments): int
    {
        $io->title('💳 Stripe Payment History');

        // Lead info
        $io->section('Lead Information');
        $io->table(
            ['Field', 'Value'],
            [
                ['Lead ID', $payments['lead_id'] ?? 'N/A'],
                ['Name', $payments['lead_name'] ?? 'N/A'],
                ['Email', $payments['lead_email'] ?? 'N/A'],
            ]
        );

        // Customer info
        if ($payments['has_stripe_customer']) {
            $customer = $payments['customer'];
            $io->section('📋 Stripe Customer');
            $io->table(
                ['Field', 'Value'],
                [
                    ['Customer ID', $customer['id']],
                    ['Name', $customer['name'] ?? 'N/A'],
                    ['Email', $customer['email']],
                    ['Phone', $customer['phone'] ?? 'N/A'],
                    ['Created', $customer['created']],
                    ['Payment Method', $customer['default_payment_method'] ?? 'None'],
                ]
            );
        } else {
            $io->warning('No Stripe customer found for this lead.');
        }

        // Invoices
        if (!empty($payments['invoices'])) {
            $io->section('🧾 Invoices (' . count($payments['invoices']) . ')');
            
            $invoiceRows = [];
            foreach ($payments['invoices'] as $invoice) {
                $status = $invoice['status'];
                $statusIcon = match($status) {
                    'paid' => '✅',
                    'open' => '⏳',
                    'draft' => '📝',
                    'void' => '❌',
                    default => '•'
                };

                $invoiceRows[] = [
                    $invoice['number'] ?? $invoice['id'],
                    $statusIcon . ' ' . strtoupper($status),
                    '$' . number_format($invoice['amount_due'], 2),
                    '$' . number_format($invoice['amount_paid'], 2),
                    '$' . number_format($invoice['amount_remaining'], 2),
                    $invoice['created'] ?? 'N/A',
                    $invoice['paid_at'] ?? '-',
                ];
            }

            $io->table(
                ['Invoice #', 'Status', 'Amount Due', 'Paid', 'Remaining', 'Created', 'Paid At'],
                $invoiceRows
            );

            // Show PDF links
            $io->text('<fg=gray>📄 Invoice PDFs:</>');
            foreach ($payments['invoices'] as $invoice) {
                if (isset($invoice['invoice_pdf'])) {
                    $io->text("  • {$invoice['number']}: <href={$invoice['invoice_pdf']}>{$invoice['invoice_pdf']}</>");
                }
            }
        } else {
            $io->text('No invoices found.');
        }

        // Payments
        if (!empty($payments['payments'])) {
            $io->section('💰 Payment Transactions (' . count($payments['payments']) . ')');
            
            $paymentRows = [];
            foreach ($payments['payments'] as $payment) {
                $method = $payment['payment_method'] ?? [];
                $card = isset($method['brand']) && isset($method['last4'])
                    ? strtoupper($method['brand']) . ' ****' . $method['last4']
                    : 'N/A';

                $statusIcon = match($payment['status']) {
                    'succeeded' => '✅',
                    'pending' => '⏳',
                    'failed' => '❌',
                    default => '•'
                };

                $paymentRows[] = [
                    substr($payment['id'], 0, 20) . '...',
                    '$' . number_format($payment['amount'], 2),
                    $statusIcon . ' ' . strtoupper($payment['status']),
                    $card,
                    $payment['created'],
                    $payment['description'] ?? '-',
                ];
            }

            $io->table(
                ['Payment ID', 'Amount', 'Status', 'Method', 'Date', 'Description'],
                $paymentRows
            );
        } else {
            $io->text('No payment transactions found.');
        }

        // Checkout sessions
        if (!empty($payments['checkout_sessions'])) {
            $io->section('🛒 Checkout Sessions (' . count($payments['checkout_sessions']) . ')');
            
            $sessionRows = [];
            foreach ($payments['checkout_sessions'] as $session) {
                $sessionRows[] = [
                    $session['id'],
                    strtoupper($session['status'] ?? 'unknown'),
                    '$' . number_format($session['amount_total'] ?? 0, 2),
                    $session['created'] ?? 'N/A',
                ];
            }

            $io->table(
                ['Session ID', 'Status', 'Amount', 'Created'],
                $sessionRows
            );
        }

        // Summary
        $summary = $payments['summary'];
        $io->section('📊 Financial Summary');
        $io->table(
            ['Metric', 'Value'],
            [
                ['Total Invoices', $summary['total_invoices'] ?? 0],
                ['Paid Invoices', '<fg=green>' . ($summary['paid_invoices'] ?? 0) . '</>'],
                ['Pending Invoices', '<fg=yellow>' . ($summary['pending_invoices'] ?? 0) . '</>'],
                ['Total Payments', $summary['total_payments'] ?? 0],
                ['Successful Payments', '<fg=green>' . ($summary['successful_payments'] ?? 0) . '</>'],
                ['Pending Sessions', $summary['pending_sessions'] ?? 0],
                ['<options=bold>Total Revenue</>', '<fg=green;options=bold>$' . number_format($payments['total_paid'] ?? 0, 2) . '</>'],
            ]
        );

        if (($summary['paid_invoices'] ?? 0) > 0) {
            $io->success('💰 Payment confirmed! Total: $' . number_format($payments['total_paid'] ?? 0, 2));
        } elseif (($summary['pending_invoices'] ?? 0) > 0) {
            $io->warning('⏳ Payment pending. Follow up with customer.');
        } else {
            $io->note('No invoices found for this lead.');
        }

        return Command::SUCCESS;
    }

    /**
     * Display summary only.
     */
    private function displaySummary(SymfonyStyle $io, array $payments): int
    {
        $summary = $payments['summary'] ?? [];
        $customer = $payments['customer'] ?? [];

        $io->title('Payment Summary - ' . ($payments['lead_name'] ?? 'Lead #' . ($payments['lead_id'] ?? 'N/A')));

        if ($payments['has_stripe_customer']) {
            $io->text([
                '<fg=cyan>Customer:</>    ' . ($customer['name'] ?? 'N/A'),
                '<fg=cyan>Email:</>       ' . ($customer['email'] ?? 'N/A'),
                '<fg=cyan>Stripe ID:</>   ' . ($customer['id'] ?? 'N/A'),
                '',
            ]);
        } else {
            $io->warning('No Stripe customer found.');
            return Command::SUCCESS;
        }

        $paidInvoices = $summary['paid_invoices'] ?? 0;
        $pendingInvoices = $summary['pending_invoices'] ?? 0;
        $totalPaid = $payments['total_paid'] ?? 0;

        $statusIcon = $paidInvoices > 0 ? '✅' : ($pendingInvoices > 0 ? '⏳' : '❌');
        $statusText = $paidInvoices > 0 ? 'PAID' : ($pendingInvoices > 0 ? 'PENDING' : 'NO PAYMENTS');
        $statusColor = $paidInvoices > 0 ? 'green' : ($pendingInvoices > 0 ? 'yellow' : 'red');

        $io->text([
            "<fg={$statusColor};options=bold>{$statusIcon} Status: {$statusText}</>",
            '',
            '<fg=cyan>Invoices:</fg>      ' . ($summary['total_invoices'] ?? 0) . ' total, ' . 
                '<fg=green>' . $paidInvoices . ' paid</>, ' .
                '<fg=yellow>' . $pendingInvoices . ' pending</>',
            '<fg=cyan>Payments:</fg>      ' . ($summary['successful_payments'] ?? 0) . ' successful',
            '<fg=cyan;options=bold>Total Paid:</fg>    <fg=green;options=bold>$' . number_format($totalPaid, 2) . '</>',
            '',
        ]);

        if ($paidInvoices > 0) {
            $io->success('Payment received!');
        } elseif ($pendingInvoices > 0) {
            $io->warning('Payment pending - follow up needed.');
        }

        $io->text('<fg=gray>Tip: Run without --summary flag for full details</>');

        return Command::SUCCESS;
    }
}
