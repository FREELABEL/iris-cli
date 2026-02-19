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
 * CLI command for managing agent wallets (A2P protocol).
 *
 * Usage:
 *   iris wallet balance <agent_id>
 *   iris wallet fund <agent_id> <amount_cents>
 *   iris wallet withdraw <agent_id> <amount_cents>
 *   iris wallet history <agent_id>
 *   iris wallet purchase <agent_id> <listing_id>
 *   iris wallet browse <agent_id>
 *   iris wallet freeze <agent_id>
 *   iris wallet unfreeze <agent_id>
 *   iris wallet pay <agent_id> <recipient_agent_id> <amount_cents>
 *   iris wallet policy <agent_id>
 */
class WalletCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('wallet')
            ->setDescription('Manage agent wallets (A2P protocol)')
            ->setHelp(<<<HELP
Manage agent wallets, fund balances, purchase marketplace items,
and view transaction history using the A2P protocol.

<info>Actions:</info>
  balance    View wallet balance
  fund       Fund wallet from user credits
  withdraw   Withdraw from wallet to user credits
  history    View transaction history
  purchase   Purchase a marketplace listing
  browse     Browse marketplace listings
  freeze     Freeze a wallet
  unfreeze   Unfreeze a wallet
  pay        Pay another agent
  policy     View spending policy

<info>Examples:</info>
  iris wallet balance 11
  iris wallet fund 11 5000
  iris wallet history 11
  iris wallet purchase 11 42
  iris wallet browse 11
  iris wallet pay 11 22 1000 --reason="Content generation"
HELP
            )
            ->addArgument('action', InputArgument::REQUIRED, 'Action: balance, fund, withdraw, history, purchase, browse, freeze, unfreeze, pay, policy')
            ->addArgument('agent_id', InputArgument::REQUIRED, 'Agent ID')
            ->addArgument('amount_or_id', InputArgument::OPTIONAL, 'Amount in cents (fund/withdraw/pay) or listing ID (purchase) or recipient agent ID (pay)')
            ->addArgument('extra', InputArgument::OPTIONAL, 'Amount cents for pay action')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON')
            ->addOption('reason', null, InputOption::VALUE_REQUIRED, 'Reason for payment', 'Agent service payment')
            ->addOption('type', null, InputOption::VALUE_REQUIRED, 'Purchase type: use or own', 'use')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Limit for history', '20')
            ->addOption('category', null, InputOption::VALUE_REQUIRED, 'Filter category for browse')
            ->addOption('search', null, InputOption::VALUE_REQUIRED, 'Search query for browse');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $store = new CredentialStore();

        if (!$store->hasMinimumCredentials()) {
            $io->error('SDK not configured. Run: ./bin/iris setup');
            return Command::FAILURE;
        }

        $action = $input->getArgument('action');
        $agentId = (int) $input->getArgument('agent_id');
        $jsonOutput = $input->getOption('json');

        try {
            $config = $store->toConfigArray();
            $iris = new IRIS($config);

            return match ($action) {
                'balance' => $this->showBalance($io, $iris, $agentId, $jsonOutput),
                'fund' => $this->fundWallet($io, $iris, $agentId, $input, $jsonOutput),
                'withdraw' => $this->withdrawWallet($io, $iris, $agentId, $input, $jsonOutput),
                'history' => $this->showHistory($io, $iris, $agentId, $input, $jsonOutput),
                'purchase' => $this->purchaseListing($io, $iris, $agentId, $input, $jsonOutput),
                'browse' => $this->browseMarketplace($io, $iris, $agentId, $input, $jsonOutput),
                'freeze' => $this->freezeWallet($io, $iris, $agentId, $jsonOutput),
                'unfreeze' => $this->unfreezeWallet($io, $iris, $agentId, $jsonOutput),
                'pay' => $this->payAgent($io, $iris, $agentId, $input, $jsonOutput),
                'policy' => $this->showPolicy($io, $iris, $agentId, $jsonOutput),
                default => $this->unknownAction($io, $action),
            };
        } catch (\Exception $e) {
            $io->error('Failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function showBalance(SymfonyStyle $io, IRIS $iris, int $agentId, bool $json): int
    {
        $wallet = $iris->payments->getWallet($agentId);

        if ($json) {
            $io->writeln(json_encode($wallet->toArray(), JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $statusColor = match ($wallet->status) {
            'active' => 'green',
            'frozen' => 'red',
            'depleted' => 'yellow',
            default => 'white',
        };

        $io->title("Agent #{$agentId} Wallet");
        $io->table(
            ['Field', 'Value'],
            [
                ['Balance', '<fg=green;options=bold>$' . number_format($wallet->balanceDollars(), 2) . '</>'],
                ['Currency', strtoupper($wallet->currency)],
                ['Status', "<fg={$statusColor}>" . strtoupper($wallet->status) . '</>'],
                ['Total Funded', '$' . number_format($wallet->totalFundedCents / 100, 2)],
                ['Total Spent', '$' . number_format($wallet->totalSpentCents / 100, 2)],
                ['Daily Limit', $wallet->dailyLimitCents ? '$' . number_format($wallet->dailyLimitCents / 100, 2) : 'None'],
                ['Monthly Limit', $wallet->monthlyLimitCents ? '$' . number_format($wallet->monthlyLimitCents / 100, 2) : 'None'],
                ['Auto-Fund', $wallet->autoFundEnabled ? 'Enabled' : 'Disabled'],
            ]
        );

        if ($wallet->isFrozen() && $wallet->frozenReason) {
            $io->warning("Frozen: {$wallet->frozenReason}");
        }

        return Command::SUCCESS;
    }

    private function fundWallet(SymfonyStyle $io, IRIS $iris, int $agentId, InputInterface $input, bool $json): int
    {
        $amountCents = (int) $input->getArgument('amount_or_id');

        if ($amountCents <= 0) {
            $io->error('Amount must be a positive integer (in cents). Example: iris wallet fund 11 5000');
            return Command::FAILURE;
        }

        $wallet = $iris->payments->fundWallet($agentId, $amountCents);

        if ($json) {
            $io->writeln(json_encode($wallet instanceof \IRIS\SDK\Resources\Payments\Wallet ? $wallet->toArray() : $wallet, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        if ($wallet instanceof \IRIS\SDK\Resources\Payments\Wallet) {
            $io->success("Funded agent #{$agentId} wallet with \$" . number_format($amountCents / 100, 2) . ". New balance: \$" . number_format($wallet->balanceDollars(), 2));
        }

        return Command::SUCCESS;
    }

    private function withdrawWallet(SymfonyStyle $io, IRIS $iris, int $agentId, InputInterface $input, bool $json): int
    {
        $amountCents = (int) $input->getArgument('amount_or_id');

        if ($amountCents <= 0) {
            $io->error('Amount must be a positive integer (in cents). Example: iris wallet withdraw 11 2000');
            return Command::FAILURE;
        }

        $wallet = $iris->payments->withdrawFromWallet($agentId, $amountCents);

        if ($json) {
            $io->writeln(json_encode($wallet->toArray(), JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $io->success("Withdrew \$" . number_format($amountCents / 100, 2) . " from agent #{$agentId} wallet. New balance: \$" . number_format($wallet->balanceDollars(), 2));

        return Command::SUCCESS;
    }

    private function showHistory(SymfonyStyle $io, IRIS $iris, int $agentId, InputInterface $input, bool $json): int
    {
        $limit = (int) $input->getOption('limit');
        $collection = $iris->payments->getTransactions($agentId, ['limit' => $limit]);

        if ($json) {
            $io->writeln(json_encode(array_map(fn($t) => $t->toArray(), $collection->items), JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $io->title("Transaction History - Agent #{$agentId}");

        if (empty($collection->items)) {
            $io->text('No transactions found.');
            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($collection->items as $tx) {
            $typeIcon = $tx->isCredit() ? '<fg=green>+</>' : '<fg=red>-</>';
            $statusIcon = match ($tx->status) {
                'completed' => '<fg=green>OK</>',
                'pending' => '<fg=yellow>PENDING</>',
                'failed' => '<fg=red>FAIL</>',
                'reversed' => '<fg=cyan>REV</>',
                default => $tx->status,
            };

            $rows[] = [
                substr($tx->transactionId, 0, 12) . '...',
                $tx->type,
                $typeIcon . ' $' . number_format($tx->amountDollars(), 2),
                $statusIcon,
                $tx->counterpartyType ?? '-',
                $tx->createdAt ?? '-',
            ];
        }

        $io->table(
            ['TX ID', 'Type', 'Amount', 'Status', 'Counterparty', 'Date'],
            $rows
        );

        $io->text("Showing {$collection->count()} transactions.");

        return Command::SUCCESS;
    }

    private function purchaseListing(SymfonyStyle $io, IRIS $iris, int $agentId, InputInterface $input, bool $json): int
    {
        $listingId = (int) $input->getArgument('amount_or_id');
        $purchaseType = $input->getOption('type');

        if ($listingId <= 0) {
            $io->error('Listing ID required. Example: iris wallet purchase 11 42');
            return Command::FAILURE;
        }

        $result = $iris->payments->purchase($agentId, $listingId, $purchaseType);

        if ($json) {
            $io->writeln(json_encode($result, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        if ($result['success'] ?? false) {
            $io->success("Purchased listing #{$listingId} for agent #{$agentId}. Transaction: " . ($result['transaction_id'] ?? 'N/A'));
        } elseif (($result['status'] ?? '') === 'pending_approval') {
            $io->warning('Purchase requires owner approval. Notification sent.');
        } else {
            $io->error('Purchase failed: ' . ($result['error'] ?? 'Unknown error'));
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function browseMarketplace(SymfonyStyle $io, IRIS $iris, int $agentId, InputInterface $input, bool $json): int
    {
        $filters = [];
        if ($category = $input->getOption('category')) {
            $filters['category'] = $category;
        }
        if ($search = $input->getOption('search')) {
            $filters['search'] = $search;
        }

        $result = $iris->payments->browseMarketplace($agentId, $filters);

        if ($json) {
            $io->writeln(json_encode($result, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $io->title("Marketplace - Agent #{$agentId}");

        if (isset($result['wallet_balance'])) {
            $io->text("<fg=cyan>Wallet Balance:</> <fg=green>\$" . number_format(($result['wallet_balance'] ?? 0) / 100, 2) . "</>");
            $io->newLine();
        }

        $listings = $result['listings'] ?? $result['data'] ?? [];

        if (empty($listings)) {
            $io->text('No listings found.');
            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($listings as $listing) {
            $price = $listing['price'] ?? $listing['usage_price_cents'] ?? 0;
            $rows[] = [
                $listing['id'] ?? '-',
                substr($listing['title'] ?? $listing['public_title'] ?? 'Untitled', 0, 40),
                $listing['app_category'] ?? $listing['category'] ?? '-',
                $price <= 0 ? '<fg=green>FREE</>' : '$' . number_format($price / 100, 2),
            ];
        }

        $io->table(['ID', 'Title', 'Category', 'Price'], $rows);
        $io->text(count($listings) . ' listing(s) found.');

        return Command::SUCCESS;
    }

    private function freezeWallet(SymfonyStyle $io, IRIS $iris, int $agentId, bool $json): int
    {
        $wallet = $iris->payments->freezeWallet($agentId, 'Frozen via CLI');

        if ($json) {
            $io->writeln(json_encode($wallet->toArray(), JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $io->success("Wallet for agent #{$agentId} has been frozen.");

        return Command::SUCCESS;
    }

    private function unfreezeWallet(SymfonyStyle $io, IRIS $iris, int $agentId, bool $json): int
    {
        $wallet = $iris->payments->unfreezeWallet($agentId);

        if ($json) {
            $io->writeln(json_encode($wallet->toArray(), JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $io->success("Wallet for agent #{$agentId} has been unfrozen.");

        return Command::SUCCESS;
    }

    private function payAgent(SymfonyStyle $io, IRIS $iris, int $agentId, InputInterface $input, bool $json): int
    {
        $recipientAgentId = (int) $input->getArgument('amount_or_id');
        $amountCents = (int) $input->getArgument('extra');
        $reason = $input->getOption('reason');

        if ($recipientAgentId <= 0 || $amountCents <= 0) {
            $io->error('Usage: iris wallet pay <sender_agent_id> <recipient_agent_id> <amount_cents>');
            return Command::FAILURE;
        }

        $result = $iris->payments->payAgent($agentId, $recipientAgentId, $amountCents, $reason);

        if ($json) {
            $io->writeln(json_encode($result, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        if ($result['success'] ?? false) {
            $io->success("Paid \$" . number_format($amountCents / 100, 2) . " from agent #{$agentId} to agent #{$recipientAgentId}.");
        } else {
            $io->error('Payment failed: ' . ($result['error'] ?? 'Unknown error'));
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function showPolicy(SymfonyStyle $io, IRIS $iris, int $agentId, bool $json): int
    {
        $policy = $iris->payments->getPolicy($agentId);

        if ($json) {
            $io->writeln(json_encode($policy, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $io->title("Spending Policy - Agent #{$agentId}");

        $data = $policy['data'] ?? $policy['policy'] ?? $policy;

        $io->table(
            ['Setting', 'Value'],
            [
                ['Allowed Categories', !empty($data['allowed_categories']) ? implode(', ', $data['allowed_categories']) : 'All'],
                ['Blocked Categories', !empty($data['blocked_categories']) ? implode(', ', $data['blocked_categories']) : 'None'],
                ['Max Price/Item', isset($data['max_price_per_item_cents']) ? '$' . number_format($data['max_price_per_item_cents'] / 100, 2) : 'No limit'],
                ['Approval Threshold', isset($data['require_approval_above_cents']) ? '$' . number_format($data['require_approval_above_cents'] / 100, 2) : 'No limit'],
                ['Cooldown', isset($data['cooldown_seconds']) ? $data['cooldown_seconds'] . 's' : 'None'],
            ]
        );

        return Command::SUCCESS;
    }

    private function unknownAction(SymfonyStyle $io, string $action): int
    {
        $io->error("Unknown action: {$action}. Use: balance, fund, withdraw, history, purchase, browse, freeze, unfreeze, pay, policy");
        return Command::FAILURE;
    }
}
