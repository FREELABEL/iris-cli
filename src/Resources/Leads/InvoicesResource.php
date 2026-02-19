<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Leads;

use IRIS\SDK\Config;
use IRIS\SDK\Http\Client;

/**
 * Lead Invoices Resource
 *
 * Manage invoices for leads - create, list, and track payment status.
 *
 * @example
 * ```php
 * // List invoices for a lead
 * $invoices = $iris->leads->invoices(16)->list();
 *
 * // Create an invoice
 * $invoice = $iris->leads->invoices(16)->create(['price' => 25000]);
 *
 * // Get invoice details
 * $invoice = $iris->leads->invoices(16)->get($invoiceId);
 * ```
 */
class InvoicesResource
{
    protected Client $http;
    protected Config $config;
    protected int $leadId;

    public function __construct(Client $http, Config $config, int $leadId)
    {
        $this->http = $http;
        $this->config = $config;
        $this->leadId = $leadId;
    }

    /**
     * List all invoices for the lead.
     *
     * @return array List of invoices
     *
     * @example
     * ```php
     * $invoices = $iris->leads->invoices(16)->list();
     *
     * foreach ($invoices as $invoice) {
     *     echo "Invoice #{$invoice['id']}: ${$invoice['amount']} - {$invoice['status']}\n";
     * }
     * ```
     */
    public function list(): array
    {
        $response = $this->http->get("/api/v1/leads/{$this->leadId}/invoices");

        return $response['invoices'] ?? $response['data'] ?? $response;
    }

    /**
     * Create a new invoice for the lead.
     *
     * @param array{
     *     price: int|float,
     *     description?: string,
     *     due_date?: string,
     *     items?: array
     * } $data Invoice data
     * @return array Created invoice
     *
     * @example
     * ```php
     * // Simple invoice with just price
     * $invoice = $iris->leads->invoices(16)->create([
     *     'price' => 25000,  // $250.00 (in cents)
     * ]);
     *
     * // Invoice with details
     * $invoice = $iris->leads->invoices(16)->create([
     *     'price' => 500000,  // $5,000.00
     *     'description' => 'AI Agent Development - Phase 1',
     *     'due_date' => '2025-01-15',
     *     'items' => [
     *         ['name' => 'Agent Setup', 'amount' => 300000],
     *         ['name' => 'Training & Customization', 'amount' => 200000],
     *     ],
     * ]);
     *
     * echo "Invoice created: #{$invoice['id']}\n";
     * echo "Payment link: {$invoice['payment_url']}\n";
     * ```
     */
    public function create(array $data): array
    {
        $response = $this->http->post("/api/v1/leads/{$this->leadId}/invoice/create", $data);

        return $response['invoice'] ?? $response['data'] ?? $response;
    }

    /**
     * Get a specific invoice.
     *
     * @param int $invoiceId Invoice ID
     * @return array Invoice details
     *
     * @example
     * ```php
     * $invoice = $iris->leads->invoices(16)->get(123);
     *
     * echo "Amount: ${$invoice['amount']}\n";
     * echo "Status: {$invoice['status']}\n";
     * echo "Due: {$invoice['due_date']}\n";
     * ```
     */
    public function get(int $invoiceId): array
    {
        $response = $this->http->get("/api/v1/leads/{$this->leadId}/invoices/{$invoiceId}");

        return $response['invoice'] ?? $response['data'] ?? $response;
    }

    /**
     * Update an invoice.
     *
     * @param int $invoiceId Invoice ID
     * @param array $data Update data
     * @return array Updated invoice
     *
     * @example
     * ```php
     * $invoice = $iris->leads->invoices(16)->update(123, [
     *     'description' => 'Updated description',
     *     'due_date' => '2025-02-01',
     * ]);
     * ```
     */
    public function update(int $invoiceId, array $data): array
    {
        $response = $this->http->put("/api/v1/leads/{$this->leadId}/invoices/{$invoiceId}", $data);

        return $response['invoice'] ?? $response['data'] ?? $response;
    }

    /**
     * Delete an invoice.
     *
     * @param int $invoiceId Invoice ID
     * @return bool
     *
     * @example
     * ```php
     * $success = $iris->leads->invoices(16)->delete(123);
     * ```
     */
    public function delete(int $invoiceId): bool
    {
        $this->http->delete("/api/v1/leads/{$this->leadId}/invoices/{$invoiceId}");

        return true;
    }

    /**
     * Mark an invoice as paid.
     *
     * @param int $invoiceId Invoice ID
     * @param array{
     *     payment_method?: string,
     *     transaction_id?: string,
     *     paid_at?: string
     * } $options Payment options
     * @return array Updated invoice
     *
     * @example
     * ```php
     * $invoice = $iris->leads->invoices(16)->markPaid(123, [
     *     'payment_method' => 'stripe',
     *     'transaction_id' => 'ch_xxxxx',
     * ]);
     * ```
     */
    public function markPaid(int $invoiceId, array $options = []): array
    {
        $response = $this->http->post(
            "/api/v1/leads/{$this->leadId}/invoices/{$invoiceId}/mark-paid",
            $options
        );

        return $response['invoice'] ?? $response['data'] ?? $response;
    }

    /**
     * Send invoice to the lead via email.
     *
     * @param int $invoiceId Invoice ID
     * @param array{
     *     subject?: string,
     *     message?: string,
     *     recipient_email?: string
     * } $options Email options
     * @return array Send result
     *
     * @example
     * ```php
     * $result = $iris->leads->invoices(16)->send(123, [
     *     'subject' => 'Invoice for AI Agent Development',
     *     'message' => 'Please find attached your invoice for the completed work.',
     * ]);
     *
     * if ($result['success']) {
     *     echo "Invoice sent to {$result['sent_to']}\n";
     * }
     * ```
     */
    public function send(int $invoiceId, array $options = []): array
    {
        $response = $this->http->post(
            "/api/v1/leads/{$this->leadId}/invoices/{$invoiceId}/send",
            $options
        );

        return $response['data'] ?? $response;
    }

    /**
     * Get payment link for an invoice.
     *
     * @param int $invoiceId Invoice ID
     * @return string Payment URL
     *
     * @example
     * ```php
     * $paymentUrl = $iris->leads->invoices(16)->getPaymentLink(123);
     * echo "Pay here: {$paymentUrl}\n";
     * ```
     */
    public function getPaymentLink(int $invoiceId): string
    {
        $response = $this->http->get("/api/v1/leads/{$this->leadId}/invoices/{$invoiceId}/payment-link");

        return $response['url'] ?? $response['payment_url'] ?? '';
    }

    /**
     * Void/cancel an invoice.
     *
     * @param int $invoiceId Invoice ID
     * @param string|null $reason Reason for voiding
     * @return array Voided invoice
     *
     * @example
     * ```php
     * $invoice = $iris->leads->invoices(16)->void(123, 'Client cancelled project');
     * ```
     */
    public function void(int $invoiceId, ?string $reason = null): array
    {
        $response = $this->http->post(
            "/api/v1/leads/{$this->leadId}/invoices/{$invoiceId}/void",
            $reason ? ['reason' => $reason] : []
        );

        return $response['invoice'] ?? $response['data'] ?? $response;
    }
}
