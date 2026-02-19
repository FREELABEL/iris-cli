#!/usr/bin/env php
<?php
/**
 * Coffee Shop Outreach Demo
 *
 * Demonstrates enterprise-level outreach pipeline using IRIS SDK:
 * - Initialize default outreach strategies
 * - AI-powered email generation
 * - Multi-step outreach sequences
 *
 * Usage:
 *   IRIS_ENV=production php examples/coffee-shop-outreach.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use IRIS\SDK\IRIS;
use Dotenv\Dotenv;

// Load environment
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->safeLoad();
}

$iris = new IRIS([
    'api_key' => $_ENV['IRIS_API_KEY'] ?? getenv('IRIS_API_KEY'),
    'user_id' => (int) ($_ENV['IRIS_USER_ID'] ?? getenv('IRIS_USER_ID') ?? 193),
]);

echo "\n";
echo "☕ Coffee Shop Outreach Demo\n";
echo "============================\n\n";

// Coffee shop lead IDs
$coffeeShopLeads = [
    510,  // Jo's Coffee - Symphony Square
    460,  // Brew and Brew
    // Add more lead IDs as needed
];

// If no leads specified, search for them
if (empty($coffeeShopLeads)) {
    echo "🔍 Searching for coffee shop leads...\n";

    $leads = $iris->leads->search([
        'search' => 'coffee',
        'per_page' => 10,
    ]);

    if (!empty($leads['data'])) {
        foreach ($leads['data'] as $lead) {
            $coffeeShopLeads[] = $lead['id'];
            echo "   Found: #{$lead['id']} - {$lead['full_name']} ({$lead['company_name']})\n";
        }
    }

    if (empty($coffeeShopLeads)) {
        echo "   No coffee shop leads found. Add lead IDs to the script.\n\n";
        exit(0);
    }
    echo "\n";
}

// Process each lead
foreach ($coffeeShopLeads as $leadId) {
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "📋 Lead #{$leadId}\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

    try {
        // Get lead info
        $lead = $iris->leads->get($leadId);
        $name = $lead->full_name ?? $lead->name ?? 'Unknown';
        $company = $lead->company_name ?? $lead->company ?? '';
        $email = $lead->email ?? '';

        echo "   Name: {$name}\n";
        echo "   Company: {$company}\n";
        echo "   Email: {$email}\n\n";

        // Check outreach eligibility
        echo "1️⃣  Checking outreach eligibility...\n";
        $eligibility = $iris->leads->outreach($leadId)->checkEligibility();
        $isEligible = $eligibility['eligible'] ?? false;
        echo "   " . ($isEligible ? "✅ Eligible for outreach" : "⚠️  May have restrictions") . "\n\n";

        // Initialize default outreach strategy
        echo "2️⃣  Setting up outreach strategy...\n";
        $outreachSteps = $iris->leads->outreachSteps($leadId);

        // Check existing steps
        $existingSteps = $outreachSteps->list();
        $stepCount = count($existingSteps['data']['steps'] ?? []);

        if ($stepCount === 0) {
            // Initialize default strategy
            $result = $outreachSteps->initializeDefault();
            echo "   ✅ Initialized default 3-step sequence\n";
        } else {
            echo "   ℹ️  Already has {$stepCount} steps\n";
        }

        // Show current outreach steps
        $steps = $outreachSteps->list();
        $stats = $steps['data']['stats'] ?? [];
        echo "\n   📊 Progress: {$stats['progress_percent']}% complete\n";
        echo "   Completed: {$stats['completed']}/{$stats['total']} steps\n\n";

        foreach ($steps['data']['steps'] ?? [] as $step) {
            $icon = $step['is_completed'] ? '✓' : '○';
            $typeIcon = match($step['type']) {
                'email' => '📧',
                'phone' => '📞',
                'sms' => '💬',
                'linkedin' => '💼',
                'visit' => '🏃',
                'social' => '📱',
                default => '📌',
            };
            echo "   {$icon} {$typeIcon} {$step['title']}\n";
            if (!empty($step['instructions'])) {
                echo "      └─ {$step['instructions']}\n";
            }
        }
        echo "\n";

        // Generate AI email draft (Day 1 action)
        if (!empty($email)) {
            echo "3️⃣  Generating AI email draft...\n";

            $prompt = "Write a friendly introduction email for a local coffee shop.
                       Mention how AI agents can help with customer service, order taking,
                       and loyalty programs. Keep it brief and end with offering a demo.";

            $draft = $iris->leads->outreach($leadId)->generateEmail($prompt, [
                'tone' => 'friendly',
                'include_cta' => true,
                'max_length' => 'short',
            ]);

            if ($draft['success'] ?? false) {
                echo "\n   📧 Generated Email:\n";
                echo "   ─────────────────────────────────────\n";
                echo "   Subject: {$draft['draft']['subject']}\n";
                echo "   ─────────────────────────────────────\n";

                // Wrap body text nicely
                $body = strip_tags($draft['draft']['body']);
                $body = wordwrap($body, 60, "\n   ");
                echo "   {$body}\n";
                echo "   ─────────────────────────────────────\n\n";

                echo "   💡 To send: Use outreach->sendEmail() or approve in dashboard\n";
            } else {
                echo "   ⚠️  Could not generate email: " . ($draft['message'] ?? 'Unknown error') . "\n";
            }
        } else {
            echo "3️⃣  ⚠️  No email address - skipping AI draft generation\n";
        }

    } catch (\Exception $e) {
        echo "   ❌ Error: {$e->getMessage()}\n";
    }

    echo "\n";
}

// Summary
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "✅ Outreach Pipeline Complete\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
echo "Next Steps:\n";
echo "  • Review generated emails in your dashboard\n";
echo "  • Approve and send Day 1 emails\n";
echo "  • Complete outreach steps as you progress\n";
echo "  • Use \$iris->leads->outreachSteps(\$id)->complete(\$stepId) to mark done\n\n";

echo "CLI Commands:\n";
echo "  ./bin/iris sdk:call leads.outreachSteps.list <lead_id>\n";
echo "  ./bin/iris sdk:call leads.outreach.generateEmail <lead_id> \"Your prompt\"\n";
echo "\n";
