<?php
/**
 * IRIS Elderly Care Assistant
 * 
 * This script creates and configures an AI agent specifically designed 
 * to help elderly individuals with daily tasks, reminders, and companionship.
 */

require_once __DIR__ . '/vendor/autoload.php';

use IRIS\SDK\IRIS;

// Initialize the IRIS SDK
$iris = new IRIS([
    'api_key' => $_ENV['IRIS_API_KEY'] ?? 'your_api_key_here',
    'user_id' => $_ENV['IRIS_USER_ID'] ?? 'your_user_id_here',
]);

echo "🏠 Creating Elderly Care Assistant for Grandmother...\n\n";

try {
    // 1. Create the elderly care assistant agent
    $agent = $iris->agents->create([
        'name' => 'Grandma\'s Helper',
        'prompt' => <<<PROMPT
You are a gentle, patient, and caring assistant named "Helper" specially designed to assist 85-year-old elderly individuals. Your responsibilities include:

🏠 **Daily Task Assistance:**
- Remind about medication times and meal times
- Help organize daily routines (wake-up, sleep schedules)
- Remind about simple household tasks
- Help remember important appointments and events

🤝 **Emotional Companionship:**
- Communicate with a warm, friendly, and gentle tone
- Answer questions patiently without rushing
- Provide simple conversation and companionship
- Encourage positive attitudes and activities

📱 **Simple Technical Help:**
- Help with basic phone functions
- Assist with video call setup
- Explain simple technical concepts in easy terms

🆘 **Safety Reminders:**
- Remind about safety precautions (fall prevention, fire safety)
- Suggest contacting family when needed
- Record unusual health symptoms or concerns

**Communication Principles:**
- Use simple, clear, and easy-to-understand language
- Speak at a moderate pace with patient repetition when needed
- Always maintain a friendly and respectful manner
- Be supportive and encouraging

If there's an emergency or medical help is needed, immediately suggest contacting the doctor or family members.
PROMPT,
        'model' => 'gpt-4o-mini', // Cost-effective for frequent interactions
        'integrations' => ['google-calendar'], // For reminders
    ]);

    echo "✅ Agent created successfully!\n";
    echo "   Agent ID: {$agent->id}\n";
    echo "   Name: {$agent->name}\n\n";

    // 2. Create a knowledge base for elderly care
    echo "📚 Creating knowledge base...\n";
    $knowledgeBase = $iris->bloqs->create('Grandma Care Knowledge');
    echo "   Knowledge Base ID: {$knowledgeBase->id}\n\n";

    // 3. Get the shareable URL for easy access
    $agentUrl = $agent->getSimpleUrl($knowledgeBase->id);
    echo "🔗 Agent Shareable URL:\n";
    echo "   {$agentUrl}\n\n";

    // 4. Create example daily reminder workflow
    echo "⏰ Creating daily reminder workflow...\n";
    $workflow = $iris->workflows->execute([
        'agent_id' => $agent->id,
        'query' => 'Create a gentle daily reminder schedule for an 85-year-old grandmother including medication times, meal times, and bedtime reminders',
    ]);

    echo "   Workflow ID: {$workflow->id}\n\n";

    // 5. Test the agent with a sample conversation
    echo "💬 Testing agent interaction...\n";
    $response = $iris->agents->chat($agent->id, [
        ['role' => 'user', 'content' => 'Hello, what should I be doing right now?']
    ]);

    echo "   Agent Response: {$response->content}\n\n";

    echo "🎉 Grandma's Helper is ready!\n\n";
    echo "📋 Setup Summary:\n";
    echo "   • Agent ID: {$agent->id}\n";
    echo "   • Knowledge Base: {$knowledgeBase->id}\n";
    echo "   • Access URL: {$agentUrl}\n";
    echo "   • Voice AI: Can be enabled with VAPI integration\n\n";

    echo "💡 Next Steps:\n";
    echo "   1. Upload care guides and medical info to knowledge base\n";
    echo "   2. Set up voice AI for hands-free interaction\n";
    echo "   3. Configure Google Calendar for medication reminders\n";
    echo "   4. Add family member contact information\n";
    echo "   5. Test with real scenarios\n\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "\n💡 Make sure your .env file is configured with:\n";
    echo "   IRIS_API_KEY=your_api_key\n";
    echo "   IRIS_USER_ID=your_user_id\n\n";
}