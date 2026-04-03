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
 * CLI command for managing invoices for leads.
 *
 * Usage:
 *   iris invoices list <lead_id>          List all invoices for a lead
 *   iris invoices create <lead_id>        Create an invoice (--price, --title flags)
 *   iris invoices subscribe <lead_id>     Create a recurring subscription (--price, --interval, --title)
 *   iris invoices show <lead_id>          Show the latest invoice for a lead
 *   iris invoices checkout <invoice_id>   Generate Stripe checkout payment link
 *   iris invoices send <invoice_id>       Send payment email to the lead
 */
class InvoicesCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('invoices')
            ->setDescription('Create, view, and send invoices for leads')
            ->setHelp(<<<HELP
Manage invoices for your leads — create, generate payment links, and send.

<info>Subcommands:</info>
  list <lead_id>        List all invoices for a lead
  create <lead_id>      Create a new invoice for a lead
  subscribe <lead_id>   Create a recurring subscription for a lead
  show <lead_id>        Show the latest invoice for a lead
  checkout <invoice_id> Generate a Stripe checkout payment link
  send <invoice_id>     Send payment email to the lead

<info>Full workflow example:</info>
  iris invoices list 412                                           View existing invoices
  iris invoices create 412 --price=5000 --title="SXSW Event"     Create invoice
  iris invoices checkout 88                                        Get payment link
  iris invoices send 88                                            Email it to the lead

<info>Subscription example:</info>
  iris invoices subscribe 50 --price=550 --interval=month --title="IRIS Monthly"
  iris invoices send 88                                            Email the checkout link

<info>Flags for create:</info>
  --price=<amount>       Amount in dollars (e.g. 5000 = $5,000.00)
  --title=<title>        Invoice title
  --description=<text>   Optional description / notes

<info>Flags for subscribe:</info>
  --price=<amount>       Base recurring amount in dollars
  --interval=<period>    Billing interval: week, month, or year
  --fee=<percent>        Platform fee percentage (e.g. --fee=10 adds 10% as separate line item)
  --title=<title>        Subscription title
  --description=<text>   Optional description

<info>All subcommands support:</info>
  --json                 Machine-readable JSON output
HELP
            )
            ->addArgument('subcommand', InputArgument::REQUIRED, 'Subcommand: list, create, show, checkout, send')
            ->addArgument('id', InputArgument::REQUIRED, 'Lead ID (for list/create/show) or Invoice ID (for checkout/send)')
            ->addOption('price', null, InputOption::VALUE_REQUIRED, 'Invoice amount in dollars (e.g. 5000)')
            ->addOption('title', null, InputOption::VALUE_REQUIRED, 'Invoice title')
            ->addOption('description', null, InputOption::VALUE_REQUIRED, 'Invoice description / notes')
            ->addOption('interval', null, InputOption::VALUE_REQUIRED, 'Subscription interval: week, month, or year')
            ->addOption('fee', null, InputOption::VALUE_REQUIRED, 'Platform fee percentage (e.g. 10 for 10%)')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON')
            ->addOption('api-key', null, InputOption::VALUE_REQUIRED, 'API key override')
            ->addOption('user-id', null, InputOption::VALUE_REQUIRED, 'User ID override');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $store = new CredentialStore();

        if (!$store->hasMinimumCredentials()) {
            $io->error('SDK not configured. Run: php bin/iris setup');
            return Command::FAILURE;
        }

        $subcommand = strtolower(trim($input->getArgument('subcommand')));
        $id = (int) $input->getArgument('id');
        $jsonOutput = (bool) $input->getOption('json');

        try {
            $config = $store->toConfigArray();

            $apiKeyOverride = $input->getOption('api-key');
            $userIdOverride = $input->getOption('user-id');
            if ($apiKeyOverride) {
                $config['api_key'] = $apiKeyOverride;
            }
            if ($userIdOverride) {
                $config['user_id'] = (int) $userIdOverride;
            }

            $iris = new IRIS($config);

            return match ($subcommand) {
                'list'      => $this->runList($io, $iris, $id, $jsonOutput),
                'create'    => $this->runCreate($io, $iris, $input, $id, $jsonOutput),
                'subscribe' => $this->runSubscribe($io, $iris, $input, $id, $jsonOutput),
                'show'      => $this->runShow($io, $iris, $id, $jsonOutput),
                'checkout'  => $this->runCheckout($io, $iris, $id, $jsonOutput),
                'send'      => $this->runSend($io, $iris, $id, $jsonOutput),
                default     => $this->showSubcommandHelp($io),
            };
        } catch (\Exception $e) {
            $io->error('Failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    // ─── Subcommands ────────────────────────────────────────────────────────────

    private function runList(SymfonyStyle $io, IRIS $iris, int $leadId, bool $json): int
    {
        $io->text('<fg=gray>Fetching invoices for lead #' . $leadId . '...</>');
        $invoices = $iris->leads->invoices($leadId)->list();

        if ($json) {
            echo json_encode($invoices, JSON_PRETTY_PRINT) . "\n";
            return Command::SUCCESS;
        }

        if (empty($invoices)) {
            $io->note('No invoices found for lead #' . $leadId . '.');
            $io->text('<fg=gray>Create one: iris invoices create ' . $leadId . ' --price=1000 --title="Invoice"</>');
            return Command::SUCCESS;
        }

        $io->title('🧾 Invoices for Lead #' . $leadId . ' (' . count($invoices) . ' total)');

        $rows = [];
        foreach ($invoices as $invoice) {
            $paid = !empty($invoice['paid_at']);
            $rows[] = [
                '#' . ($invoice['id'] ?? '?'),
                $invoice['title'] ?? 'Untitled',
                '$' . number_format((float) ($invoice['price'] ?? 0), 2),
                $paid ? '<fg=green>✅ PAID</>' : '<fg=yellow>⏳ UNPAID</>',
                $invoice['created_at'] ?? 'N/A',
            ];
        }

        $io->table(['ID', 'Title', 'Amount', 'Status', 'Created'], $rows);
        $io->text('<fg=gray>Tip: iris invoices checkout <invoice_id>   Generate a payment link</>');

        return Command::SUCCESS;
    }

    private function runCreate(SymfonyStyle $io, IRIS $iris, InputInterface $input, int $leadId, bool $json): int
    {
        $price = $input->getOption('price');
        $title = $input->getOption('title') ?? 'Invoice';
        $description = $input->getOption('description') ?? '';

        if (!$price) {
            $io->error([
                '--price is required.',
                'Example: iris invoices create ' . $leadId . ' --price=5000 --title="SXSW Autopilot Event"',
            ]);
            return Command::FAILURE;
        }

        $data = [
            'price' => (float) $price,
            'title' => $title,
        ];
        if ($description) {
            $data['description'] = $description;
        }

        $io->text('<fg=gray>Creating invoice for lead #' . $leadId . '...</>');
        $invoice = $iris->leads->invoices($leadId)->create($data);

        if ($json) {
            echo json_encode($invoice, JSON_PRETTY_PRINT) . "\n";
            return Command::SUCCESS;
        }

        $invoiceId = $invoice['id'] ?? null;
        $paid = !empty($invoice['paid_at']);

        $io->success('Invoice created!');
        $io->table(['Field', 'Value'], [
            ['Invoice ID', '#' . ($invoiceId ?? 'N/A')],
            ['Lead ID',    '#' . $leadId],
            ['Title',      $invoice['title'] ?? $title],
            ['Amount',     '$' . number_format((float) ($invoice['price'] ?? (float) $price), 2)],
            ['Status',     $paid ? '✅ PAID' : '⏳ UNPAID'],
        ]);

        if ($invoiceId) {
            $io->text([
                '',
                '<fg=cyan>Next steps:</>',
                '  <fg=gray>iris invoices checkout ' . $invoiceId . '</>   → Generate Stripe payment link',
                '  <fg=gray>iris invoices send ' . $invoiceId . '</>       → Email the payment link to the lead',
            ]);
        }

        return Command::SUCCESS;
    }

    private function runSubscribe(SymfonyStyle $io, IRIS $iris, InputInterface $input, int $leadId, bool $json): int
    {
        $price = $input->getOption('price');
        $interval = $input->getOption('interval') ?? 'month';
        $title = $input->getOption('title');
        $description = $input->getOption('description') ?? '';
        $feePercent = $input->getOption('fee');

        if (!$price) {
            $io->error([
                '--price is required.',
                'Example: iris invoices subscribe ' . $leadId . ' --price=500 --interval=month --fee=10 --title="IRIS Monthly"',
            ]);
            return Command::FAILURE;
        }

        if (!in_array($interval, ['week', 'month', 'year'])) {
            $io->error('--interval must be one of: week, month, year');
            return Command::FAILURE;
        }

        $basePrice = (float) $price;
        $totalPrice = $basePrice;

        $data = [
            'interval' => $interval,
        ];

        // If fee percentage provided, create itemized line items
        if ($feePercent) {
            $feeAmount = round($basePrice * ((float) $feePercent / 100), 2);
            $totalPrice = $basePrice + $feeAmount;
            $data['amount'] = $totalPrice;
            $data['line_items'] = [
                ['title' => $title ?? 'Subscription', 'amount' => $basePrice],
                ['title' => 'Platform Fee (' . $feePercent . '%)', 'amount' => $feeAmount],
            ];
            $io->text('<fg=gray>Base: $' . number_format($basePrice, 2) . ' + Fee: $' . number_format($feeAmount, 2) . ' = $' . number_format($totalPrice, 2) . '/' . $interval . '</>');
        } else {
            $data['amount'] = $basePrice;
        }

        if ($title) {
            $data['title'] = $title;
        }
        if ($description) {
            $data['description'] = $description;
        }

        $io->text('<fg=gray>Creating ' . $interval . 'ly subscription for lead #' . $leadId . '...</>');

        $http = $iris->getHttpClient();
        $response = $http->post("/api/v1/leads/{$leadId}/subscription/create", $data);

        if ($json) {
            echo json_encode($response, JSON_PRETTY_PRINT) . "\n";
            return Command::SUCCESS;
        }

        $checkoutUrl = $response['checkout_url'] ?? $response['data']['checkout_url'] ?? null;
        $customRequest = $response['custom_request'] ?? $response['data']['custom_request'] ?? [];
        $invoiceId = $customRequest['id'] ?? null;

        $io->success('Subscription created!');
        $rows = [
            ['Invoice ID',    '#' . ($invoiceId ?? 'N/A')],
            ['Lead ID',       '#' . $leadId],
            ['Title',         $customRequest['title'] ?? $title ?? 'Subscription'],
        ];

        if ($feePercent) {
            $feeAmount = round($basePrice * ((float) $feePercent / 100), 2);
            $rows[] = ['Base Amount', '$' . number_format($basePrice, 2) . '/' . $interval];
            $rows[] = ['Platform Fee (' . $feePercent . '%)', '$' . number_format($feeAmount, 2) . '/' . $interval];
            $rows[] = ['Total',       '$' . number_format($totalPrice, 2) . '/' . $interval];
        } else {
            $rows[] = ['Amount', '$' . number_format($totalPrice, 2) . '/' . $interval];
        }

        $rows[] = ['Interval', ucfirst($interval) . 'ly'];
        $rows[] = ['Checkout URL', $checkoutUrl ? substr($checkoutUrl, 0, 80) . '...' : 'N/A'];

        $io->table(['Field', 'Value'], $rows);

        if ($checkoutUrl) {
            $io->text([
                '',
                '<fg=cyan>💳 Subscription Checkout Link:</>',
                '  <options=bold;fg=green>' . $checkoutUrl . '</>',
                '',
                '<fg=gray>Share this link with the lead to start their subscription.</>',
            ]);
            if ($invoiceId) {
                $io->text('<fg=gray>Tip: iris invoices send ' . $invoiceId . '   → Email this link to the lead</>');
            }
        }

        return Command::SUCCESS;
    }

    private function runShow(SymfonyStyle $io, IRIS $iris, int $leadId, bool $json): int
    {
        $io->text('<fg=gray>Fetching latest invoice for lead #' . $leadId . '...</>');
        $http = $iris->getHttpClient();
        $response = $http->get("/api/v1/leads/{$leadId}/invoice");
        $invoice = $response['request'] ?? $response['invoice'] ?? $response['data'] ?? $response;

        if ($json) {
            echo json_encode($invoice, JSON_PRETTY_PRINT) . "\n";
            return Command::SUCCESS;
        }

        if (empty($invoice) || empty($invoice['id'])) {
            $io->note('No invoice found for lead #' . $leadId . '.');
            $io->text('<fg=gray>Create one: iris invoices create ' . $leadId . ' --price=1000 --title="Invoice"</>');
            return Command::SUCCESS;
        }

        $paid = !empty($invoice['paid_at']);
        $invoiceId = $invoice['id'];

        $io->title('🧾 Latest Invoice for Lead #' . $leadId);
        $io->table(['Field', 'Value'], [
            ['Invoice ID',   '#' . $invoiceId],
            ['Title',        $invoice['title'] ?? 'Untitled'],
            ['Description',  $invoice['description'] ?? '-'],
            ['Amount',       '$' . number_format((float) ($invoice['price'] ?? 0), 2)],
            ['Tax Rate',     ($invoice['tax_rate'] ?? 0) . '%'],
            ['Status',       $paid ? '✅ PAID on ' . $invoice['paid_at'] : '⏳ UNPAID'],
            ['Payment Link', $invoice['vendor_url'] ?? 'Not generated yet'],
            ['Created',      $invoice['created_at'] ?? 'N/A'],
        ]);

        if (!$paid && !empty($invoice['vendor_url'])) {
            $io->text('<fg=gray>Tip: iris invoices send ' . $invoiceId . '   → Email this link to the lead</>');
        } elseif (!$paid) {
            $io->text([
                '<fg=gray>Tip: iris invoices checkout ' . $invoiceId . '   → Generate payment link</>',
                '<fg=gray>     iris invoices send ' . $invoiceId . '       → Email it to the lead</>',
            ]);
        }

        return Command::SUCCESS;
    }

    private function runCheckout(SymfonyStyle $io, IRIS $iris, int $invoiceId, bool $json): int
    {
        $io->text('<fg=gray>Generating Stripe checkout URL for invoice #' . $invoiceId . '...</>');
        $http = $iris->getHttpClient();
        $response = $http->post("/api/v1/custom-requests/{$invoiceId}/generate-checkout");

        if ($json) {
            echo json_encode($response, JSON_PRETTY_PRINT) . "\n";
            return Command::SUCCESS;
        }

        $url = $response['url']
            ?? $response['checkout_url']
            ?? $response['vendor_url']
            ?? $response['data']['url']
            ?? null;

        if ($url) {
            $io->success('Stripe checkout URL generated!');
            $io->text([
                '',
                '<fg=cyan>💳 Payment Link:</>',
                '  <options=bold;fg=green>' . $url . '</>',
                '',
                '<fg=gray>Share this link with the lead to collect payment.</>',
                '<fg=gray>Tip: iris invoices send ' . $invoiceId . '   → Email this link directly to the lead</>',
            ]);
        } else {
            $io->warning('Checkout URL not returned in response.');
            $io->text(json_encode($response, JSON_PRETTY_PRINT));
        }

        return Command::SUCCESS;
    }

    private function runSend(SymfonyStyle $io, IRIS $iris, int $invoiceId, bool $json): int
    {
        $io->text('<fg=gray>Sending invoice #' . $invoiceId . ' to lead...</>');
        $http = $iris->getHttpClient();
        $response = $http->post("/api/v1/custom-requests/{$invoiceId}/send-reminder");

        if ($json) {
            echo json_encode($response, JSON_PRETTY_PRINT) . "\n";
            return Command::SUCCESS;
        }

        $failed = isset($response['success']) && $response['success'] === false;

        if (!$failed) {
            $io->success('Invoice sent!');
            $emails = $response['emails'] ?? [];
            if (!empty($emails)) {
                $io->text('<fg=cyan>Sent to:</> ' . implode(', ', (array) $emails));
            }
            $io->text([
                '',
                '<fg=gray>The lead will receive an email with a payment link.</>',
                '<fg=gray>Reminder count tracked — follow-ups are recorded.</>',
            ]);
        } else {
            $io->error('Failed to send: ' . ($response['message'] ?? 'Unknown error'));
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function showSubcommandHelp(SymfonyStyle $io): int
    {
        $io->title('🧾 iris invoices — Invoice Management');
        $io->text('Usage: <info>iris invoices <subcommand> <id> [options]</info>');
        $io->newLine();
        $io->table(
            ['Subcommand', 'Args', 'Description'],
            [
                ['list',      '<lead_id>',    'List all invoices for a lead'],
                ['create',    '<lead_id>',    'Create invoice (use --price, --title flags)'],
                ['subscribe', '<lead_id>',    'Create recurring subscription (--price, --interval, --title)'],
                ['show',      '<lead_id>',    'Show the latest invoice for a lead'],
                ['checkout',  '<invoice_id>', 'Generate Stripe checkout payment link'],
                ['send',      '<invoice_id>', 'Send payment email to the lead'],
            ]
        );
        $io->text('<fg=gray>Quick workflow: list → create → checkout → send</>');
        return Command::SUCCESS;
    }
}
