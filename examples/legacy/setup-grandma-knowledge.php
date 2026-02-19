<?php
/**
 * Setup Grandma's Care Knowledge Base
 * 
 * This script populates the knowledge base with essential elderly care information.
 */

require_once __DIR__ . '/vendor/autoload.php';

use IRIS\SDK\IRIS;

// Initialize the IRIS SDK
$iris = new IRIS([
    'api_key' => $_ENV['IRIS_API_KEY'] ?? 'your_api_key_here',
    'user_id' => $_ENV['IRIS_USER_ID'] ?? 'your_user_id_here',
]);

echo "📚 Setting up Grandma's Care Knowledge Base...\n\n";

try {
    // Use the knowledge base ID from the previous script
    $knowledgeBaseId = 40; // Update with actual ID from agent creation
    $agentId = 11; // Update with actual agent ID

    // Upload the care guide
    echo "📄 Uploading care guide...\n";
    $uploadResult = $iris->agents->uploadAndAttachFiles($agentId, [
        __DIR__ . '/grandma-care-guide.md'
    ], $knowledgeBaseId);

    echo "✅ Care guide uploaded successfully!\n\n";

    // Create additional knowledge items programmatically
    echo "📝 Creating additional knowledge items...\n";

    // Emergency procedures
    $emergencyProcedures = [
        'title' => 'Emergency Response Guide',
        'content' => 'Chest pain or difficulty breathing: Call 911 immediately and contact family
Fall with injury: Do not move, call for help immediately
Confusion or disorientation: Contact family or doctor
Medication side effects: Stop medication and contact doctor'
    ];

    // Daily reminder template
    $dailyReminders = [
        'title' => 'Daily Reminder Template',
        'content' => '8 AM: Check blood pressure, eat breakfast, check family messages
12 PM: Lunch, rest for 30 minutes
3 PM: Water break, light activity
6 PM: Dinner, gentle walk
9 PM: Check doors and windows, prepare for bed'
    ];

    // Upload these as items to the knowledge base
    foreach ([$emergencyProcedures, $dailyReminders] as $item) {
        $tempFile = tempnam(sys_get_temp_dir(), 'grandma_care_');
        file_put_contents($tempFile, $item['content']);
        
        $iris->agents->uploadAndAttachFiles($agentId, [$tempFile], $knowledgeBaseId);
        unlink($tempFile);
    }

    echo "✅ Additional knowledge items created!\n\n";

    // Test the knowledge base
    echo "🧪 Testing knowledge base...\n";
    $testQueries = [
        'What should I do if I fall?',
        'When should I take my medications daily?',
        'I feel dizzy, what should I do?',
        'Help me contact my family'
    ];

    foreach ($testQueries as $query) {
        $response = $iris->agents->chat($agentId, [
            ['role' => 'user', 'content' => $query]
        ]);
        
        echo "   Q: {$query}\n";
        echo "   A: " . substr($response->content, 0, 100) . "...\n\n";
    }

    echo "🎉 Knowledge base setup complete!\n\n";
    echo "💡 Features now available:\n";
    echo "   • Emergency guidance in English\n";
    echo "   • Daily routine reminders\n";
    echo "   • Medication management tips\n";
    echo "   • Safety checklists\n";
    echo "   • Contact information storage\n\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "\n💡 Make sure you have:\n";
    echo "   1. Created the agent first\n";
    echo "   2. Updated the agent ID and knowledge base ID\n";
    echo "   3. Configured your .env file\n\n";
}