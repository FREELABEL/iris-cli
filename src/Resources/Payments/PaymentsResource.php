<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Payments;

use IRIS\SDK\Config;
use IRIS\SDK\Http\Client;

/**
 * Payments Resource (A2P Protocol)
 *
 * Manage agent wallets, transactions, marketplace purchases, and spending policies.
 * Part of the A2P (Agent-to-Payment) protocol.
 *
 * @example
 * ```php
 * // Get wallet balance
 * $wallet = $iris->payments->getWallet(11);
 * echo "Balance: $" . $wallet->balanceDollars() . "\n";
 *
 * // Fund wallet from user credits
 * $wallet = $iris->payments->fundWallet(11, 5000); // $50.00
 *
 * // Browse marketplace
 * $listings = $iris->payments->browseMarketplace(11);
 *
 * // Purchase a listing
 * $result = $iris->payments->purchase(11, 42);
 *
 * // Pay another agent
 * $result = $iris->payments->payAgent(11, 22, 1000, 'Service payment');
 * ```
 */
class PaymentsResource
{
    protected Client $http;
    protected Config $config;

    public function __construct(Client $http, Config $config)
    {
        $this->http = $http;
        $this->config = $config;
    }

    // =========================================================================
    // Wallet Management
    // =========================================================================

    /**
     * Create a wallet for an agent.
     *
     * @param int $agentId Agent ID
     * @param array{
     *     currency?: string,
     *     daily_limit_cents?: int,
     *     monthly_limit_cents?: int,
     *     per_transaction_limit_cents?: int,
     *     auto_fund_enabled?: bool,
     *     auto_fund_threshold_cents?: int,
     *     auto_fund_amount_cents?: int,
     *     metadata?: array
     * } $options Wallet configuration
     * @return Wallet
     */
    public function createWallet(int $agentId, array $options = []): Wallet
    {
        $response = $this->http->post('/api/v1/a2p/wallets', array_merge(
            ['agent_id' => $agentId],
            $options
        ));

        return new Wallet($response['data'] ?? $response['wallet'] ?? $response);
    }

    /**
     * Get wallet for an agent.
     *
     * @param int $agentId Agent ID
     * @return Wallet
     */
    public function getWallet(int $agentId): Wallet
    {
        $response = $this->http->get("/api/v1/a2p/wallets/{$agentId}");

        return new Wallet($response['data'] ?? $response['wallet'] ?? $response);
    }

    /**
     * Get wallet balance for an agent.
     *
     * @param int $agentId Agent ID
     * @return array{balance_cents: int, currency: string, status: string}
     */
    public function getBalance(int $agentId): array
    {
        return $this->http->get("/api/v1/a2p/wallets/{$agentId}/balance");
    }

    /**
     * Fund an agent wallet from user credit balance.
     *
     * @param int $agentId Agent ID
     * @param int $amountCents Amount in cents to transfer
     * @param string $source Funding source: 'credits' or 'stripe'
     * @return Wallet Updated wallet
     */
    public function fundWallet(int $agentId, int $amountCents, string $source = 'credits'): Wallet|array
    {
        $response = $this->http->post("/api/v1/a2p/wallets/{$agentId}/fund", [
            'amount_cents' => $amountCents,
            'source' => $source,
        ]);

        // Stripe funding returns a checkout URL instead of a wallet
        if ($source === 'stripe' && isset($response['checkout_url'])) {
            return $response;
        }

        return new Wallet($response['data'] ?? $response['wallet'] ?? $response);
    }

    /**
     * Withdraw from agent wallet back to user credit balance.
     *
     * @param int $agentId Agent ID
     * @param int $amountCents Amount in cents to withdraw
     * @return Wallet Updated wallet
     */
    public function withdrawFromWallet(int $agentId, int $amountCents): Wallet
    {
        $response = $this->http->post("/api/v1/a2p/wallets/{$agentId}/withdraw", [
            'amount_cents' => $amountCents,
        ]);

        return new Wallet($response['data'] ?? $response['wallet'] ?? $response);
    }

    /**
     * Freeze an agent wallet.
     *
     * @param int $agentId Agent ID
     * @param string $reason Reason for freezing
     * @return Wallet Updated wallet
     */
    public function freezeWallet(int $agentId, string $reason = ''): Wallet
    {
        $response = $this->http->post("/api/v1/a2p/wallets/{$agentId}/freeze", [
            'reason' => $reason,
        ]);

        return new Wallet($response['data'] ?? $response['wallet'] ?? $response);
    }

    /**
     * Unfreeze an agent wallet.
     *
     * @param int $agentId Agent ID
     * @return Wallet Updated wallet
     */
    public function unfreezeWallet(int $agentId): Wallet
    {
        $response = $this->http->post("/api/v1/a2p/wallets/{$agentId}/unfreeze");

        return new Wallet($response['data'] ?? $response['wallet'] ?? $response);
    }

    // =========================================================================
    // Payments
    // =========================================================================

    /**
     * Execute a payment from an agent wallet.
     *
     * @param int $agentId Agent ID
     * @param array{
     *     amount_cents: int,
     *     counterparty_type: string,
     *     counterparty_id: int|string,
     *     category?: string,
     *     metadata?: array
     * } $paymentRequest Payment details
     * @return array Payment result
     */
    public function pay(int $agentId, array $paymentRequest): array
    {
        return $this->http->post("/api/v1/a2p/wallets/{$agentId}/pay", $paymentRequest);
    }

    /**
     * Pay another agent.
     *
     * @param int $agentId Sender agent ID
     * @param int $recipientAgentId Recipient agent ID
     * @param int $amountCents Amount in cents
     * @param string $reason Reason for payment
     * @return array Payment result
     */
    public function payAgent(int $agentId, int $recipientAgentId, int $amountCents, string $reason = 'Agent service payment'): array
    {
        return $this->http->post("/api/v1/a2p/wallets/{$agentId}/pay-agent", [
            'recipient_agent_id' => $recipientAgentId,
            'amount_cents' => $amountCents,
            'reason' => $reason,
        ]);
    }

    // =========================================================================
    // Marketplace
    // =========================================================================

    /**
     * Purchase a marketplace listing using agent wallet.
     *
     * @param int $agentId Agent ID
     * @param int $listingId Marketplace listing ID
     * @param string $purchaseType 'use' or 'own'
     * @return array Purchase result
     */
    public function purchase(int $agentId, int $listingId, string $purchaseType = 'use'): array
    {
        return $this->http->post("/api/v1/a2p/wallets/{$agentId}/marketplace/purchase", [
            'listing_id' => $listingId,
            'purchase_type' => $purchaseType,
        ]);
    }

    /**
     * Browse marketplace listings.
     *
     * @param int $agentId Agent ID (for wallet context)
     * @param array{
     *     category?: string,
     *     search?: string,
     *     max_price?: int,
     *     free_only?: bool
     * } $filters Optional filters
     * @return array Listings with wallet balance
     */
    public function browseMarketplace(int $agentId, array $filters = []): array
    {
        return $this->http->get("/api/v1/a2p/wallets/{$agentId}/marketplace/browse", $filters);
    }

    // =========================================================================
    // Transactions
    // =========================================================================

    /**
     * Get transaction history for an agent wallet.
     *
     * @param int $agentId Agent ID
     * @param array{
     *     type?: string,
     *     status?: string,
     *     limit?: int,
     *     offset?: int
     * } $filters Optional filters
     * @return TransactionCollection
     */
    public function getTransactions(int $agentId, array $filters = []): TransactionCollection
    {
        $response = $this->http->get("/api/v1/a2p/wallets/{$agentId}/transactions", $filters);

        $transactions = $response['data'] ?? $response['transactions'] ?? $response;

        return new TransactionCollection(
            array_map(fn($data) => new Transaction($data), $transactions),
            $response['meta'] ?? []
        );
    }

    /**
     * Get a specific transaction.
     *
     * @param int $agentId Agent ID
     * @param string $transactionId Transaction UUID
     * @return Transaction
     */
    public function getTransaction(int $agentId, string $transactionId): Transaction
    {
        $response = $this->http->get("/api/v1/a2p/wallets/{$agentId}/transactions/{$transactionId}");

        return new Transaction($response['data'] ?? $response);
    }

    // =========================================================================
    // Spending Policy
    // =========================================================================

    /**
     * Get spending policy for an agent wallet.
     *
     * @param int $agentId Agent ID
     * @return array Policy configuration
     */
    public function getPolicy(int $agentId): array
    {
        return $this->http->get("/api/v1/a2p/wallets/{$agentId}/policy");
    }

    /**
     * Update spending policy for an agent wallet.
     *
     * @param int $agentId Agent ID
     * @param array{
     *     allowed_categories?: array,
     *     blocked_categories?: array,
     *     allowed_seller_ids?: array,
     *     max_price_per_item_cents?: int,
     *     require_approval_above_cents?: int,
     *     cooldown_seconds?: int
     * } $policy Policy configuration
     * @return array Updated policy
     */
    public function updatePolicy(int $agentId, array $policy): array
    {
        return $this->http->put("/api/v1/a2p/wallets/{$agentId}/policy", $policy);
    }

    /**
     * Check spending policy (dry-run).
     *
     * @param int $agentId Agent ID
     * @param int $amountCents Amount to check
     * @param string $category Spending category
     * @return array{allowed: bool, requires_approval: bool, reason: ?string}
     */
    public function checkPolicy(int $agentId, int $amountCents, string $category = 'general'): array
    {
        return $this->http->post("/api/v1/a2p/wallets/{$agentId}/policy/check", [
            'amount_cents' => $amountCents,
            'category' => $category,
        ]);
    }
}
