<?php
/**
 * Enable Voice AI for Grandma's Helper
 * 
 * This script configures VAPI integration for voice capabilities,
 * allowing hands-free interaction with the elderly care assistant.
 */

require_once __DIR__ . '/vendor/autoload.php';

use IRIS\SDK\IRIS;

// Initialize the IRIS SDK
$iris = new IRIS([
    'api_key' => $_ENV['IRIS_API_KEY'] ?? 'your_api_key_here',
    'user_id' => $_ENV['IRIS_USER_ID'] ?? 'your_user_id_here',
]);

echo "🎙️ Setting up Voice AI for Grandma's Helper...\n\n";

try {
    $agentId = 11; // Update with your agent ID

    // 1. Enable VAPI integration on the agent
    echo "🔌 Enabling VAPI voice integration...\n";
    $updatedAgent = $iris->agents->patch($agentId, [
        'integrations' => ['gmail', 'google-calendar', 'vapi'],
        'voice_enabled' => true,
        'voice_settings' => [
            'language' => 'en-US', // English for customers
            'voice_type' => 'female', // Gentle female voice
            'speaking_rate' => 0.9, // Slightly slower for elderly
            'pitch' => 'medium'
        ]
    ]);

    echo "✅ Voice integration enabled!\n\n";

    // 2. Create voice-optimized prompts
    echo "📝 Creating voice interaction prompts...\n";
    $voicePrompt = <<<VOICE_PROMPT
You are "Helper", the voice assistant for elderly care. Please respond in the following manner:

🗣️ **Voice Communication Principles:**
- Respond slowly, clearly, and warmly in English
- Speak in 1-2 simple sentences at a time
- Use friendly terms like "Dear" or "Sweetie"
- Give clear, actionable instructions

🎯 **Common Response Patterns:**
- "It's time for your medication now"
- "Alright dear, let me contact your family for you"
- "Please take your time, there's no rush"
- "Let me help you remember that"

📞 **Phone Handling:**
- "You have an incoming call, would you like to answer it?"
- "Would you like me to call someone back for you?"
- "I'll make a note of the caller's information"

🆘 **Emergency Situations:**
- "Don't worry dear, I'm contacting your family right away"
- "If this is serious, we need to call emergency services"
- "Please stay calm, help is on the way"

**Remember:** Always be patient, gentle, and respectful. Elderly individuals may need more time to understand and respond.
VOICE_PROMPT;

    // Update the agent with voice-optimized prompt
    $iris->agents->patch($agentId, [
        'prompt' => $voicePrompt
    ]);

    echo "✅ Voice prompts configured!\n\n";

    // 3. Create voice command scenarios
    echo "🎮 Setting up voice command scenarios...\n";
    
    $voiceCommands = [
        'emergency_contacts' => [
            'trigger' => ['not feeling well', 'emergency', 'help me', 'uncomfortable'],
            'action' => 'ask_to_call_family'
        ],
        'medication_reminder' => [
            'trigger' => ['medication', 'medicine', 'when to take medicine'],
            'action' => 'provide_medication_schedule'
        ],
        'family_contact' => [
            'trigger' => ['call someone', 'contact family', 'need help'],
            'action' => 'offer_to_call_family'
        ],
        'daily_assist' => [
            'trigger' => ['what should I do', 'help me', 'what\'s next'],
            'action' => 'suggest_current_activity'
        ]
    ];

    // Store voice commands in knowledge base
    $knowledgeBaseId = 40; // Update with your knowledge base ID
    $commandsFile = tempnam(sys_get_temp_dir(), 'voice_commands_');
    file_put_contents($commandsFile, json_encode($voiceCommands, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    $iris->agents->uploadAndAttachFiles($agentId, [$commandsFile], $knowledgeBaseId);
    unlink($commandsFile);

    echo "✅ Voice command scenarios created!\n\n";

    // 4. Create sample voice interactions
    echo "💬 Creating voice interaction examples...\n";
    $exampleInteractions = [
        [
            'user_says' => 'I feel a bit dizzy',
            'ai_responds' => 'Please don\'t worry, sit down and rest. Would you like me to contact your family?'
        ],
        [
            'user_says' => 'What time is it? What should I be doing?',
            'ai_responds' => 'It\'s 3 PM now. Time for some water, let me remind you to have a glass of water.'
        ],
        [
            'user_says' => 'I can\'t find my glasses',
            'ai_responds' => 'Don\'t worry, let me help you think. Where do you last remember having your glasses?'
        ]
    ];

    foreach ($exampleInteractions as $example) {
    echo "   👤 User: {$example['user_says']}\n";
    echo "   🤖 Helper: {$example['ai_responds']}\n\n";
    }

    echo "🎉 Voice AI setup complete!\n\n";
    echo "📋 Voice Features Enabled:\n";
    echo "   • English language support\n";
    echo "   • Gentle female voice\n";
    echo "   • Slower speaking rate\n";
    echo "   • Emergency response protocols\n";
    echo "   • Family contact automation\n";
    echo "   • Medication reminders\n\n";

    echo "📞 Next Steps:\n";
    echo "   1. Configure VAPI phone number\n";
    echo "   2. Set up family contact list\n";
    echo "   3. Test voice calls\n";
    echo "   4. Create emergency call procedures\n\n";

    echo "💡 To enable voice calling:\n";
    echo "   1. Get VAPI credentials from your IRIS dashboard\n";
    echo "   2. Assign a phone number to the agent\n";
    echo "   3. Grandma can call the number for instant assistance\n\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "\n💡 Make sure you have:\n";
    echo "   1. Created the basic agent first\n";
    echo "   2. Updated the agent ID in this script\n";
    echo "   3. VAPI integration available in your plan\n\n";
}