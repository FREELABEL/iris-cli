<?php
/**
 * Grandma's Daily Reminder System
 * 
 * This script creates automated workflows and reminders for elderly care,
 * including medication schedules, meal times, and safety check-ins.
 */

require_once __DIR__ . '/vendor/autoload.php';

use IRIS\SDK\IRIS;

// Initialize the IRIS SDK
$iris = new IRIS([
    'api_key' => $_ENV['IRIS_API_KEY'] ?? 'your_api_key_here',
    'user_id' => $_ENV['IRIS_USER_ID'] ?? 'your_user_id_here',
]);

echo "⏰ Creating Grandma's Daily Reminder System...\n\n";

try {
    $agentId = 11; // Update with your agent ID
    $knowledgeBaseId = 40; // Update with your knowledge base ID

    // 1. Create daily schedule workflow
    echo "📅 Creating daily schedule workflow...\n";
    $dailySchedule = [
        'morning_routine' => [
            'time' => '08:00',
            'activities' => [
                'Morning greeting: "Good morning dear! How are you feeling today?"',
                'Blood pressure reminder: "Time to check your blood pressure"',
                'Breakfast reminder: "It\'s time for breakfast, remember to eat slowly"',
                'Morning medication reminder: "Time for your morning medications"'
            ]
        ],
        'midday_check' => [
            'time' => '12:00',
            'activities' => [
                'Lunch reminder: "It\'s lunchtime now"',
                'Noon medication reminder: "Time for your lunch medications"',
                'Rest reminder: "Remember to rest for 30 minutes after eating"'
            ]
        ],
        'afternoon_activities' => [
            'time' => '15:00',
            'activities' => [
                'Water reminder: "Time to drink some water to stay hydrated"',
                'Light activity: "Would you like to take a short walk around the room?"',
                'Family contact reminder: "Would you like to call your family?"'
            ]
        ],
        'evening_routine' => [
            'time' => '18:00',
            'activities' => [
                'Dinner reminder: "It\'s dinner time now"',
                'Evening medication reminder: "Time for your dinner medications"',
                'Safety check: "Let me help you check the doors and windows"'
            ]
        ],
        'bedtime_preparation' => [
            'time' => '21:00',
            'activities' => [
                'Bedtime reminder: "It\'s time to get ready for bed"',
                'Bedtime medication reminder: "Time for your bedtime medications"',
                'Safety confirmation: "Are the doors and windows locked? Is your phone charging?"',
                'Good night greeting: "Good night, sleep well and have sweet dreams"'
            ]
        ]
    ];

    // Save schedule to knowledge base
    $scheduleFile = tempnam(sys_get_temp_dir(), 'daily_schedule_');
    file_put_contents($scheduleFile, json_encode($dailySchedule, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $iris->agents->uploadAndAttachFiles($agentId, [$scheduleFile], $knowledgeBaseId);
    unlink($scheduleFile);

    echo "✅ Daily schedule workflow created!\n\n";

    // 2. Create medication management workflow
    echo "💊 Creating medication management workflow...\n";
    $medicationWorkflow = $iris->workflows->execute([
        'agent_id' => $agentId,
        'query' => <<<QUERY
Create an elderly medication management system with the following features:

📋 **Medication List Management:**
- Record all medication names, dosages, and times
- Set medication reminders (morning, noon, evening)
- Track medication side effects
- Remind about medication refills

⏰ **Smart Reminder Features:**
- Timely medication reminders with gentle tone
- Confirm medication completion
- Record medication history
- Missed dose alerts and handling

📞 **Exception Handling:**
- Identify medication side effects
- Suggest contacting doctor
- Family notification system
- Emergency response protocols

📊 **Medication Records:**
- Daily medication completion status
- Side effect tracking
- Doctor follow-up reminders
- Medication inventory management

Please create this system in English, specifically designed for 85-year-old elderly individuals.
QUERY
    ]);

    echo "✅ Medication workflow created!\n";
    echo "   Workflow ID: {$medicationWorkflow->id}\n\n";

    // 3. Create safety check-in workflow
    echo "🛡️ Creating safety check-in workflow...\n";
    $safetyWorkflow = $iris->workflows->execute([
        'agent_id' => $agentId,
        'query' => <<<QUERY
Create an elderly safety check system:

🏠 **Daily Safety Checks:**
- Morning wake-up safety confirmation
- Bedtime safety checklist
- Home safety assessment
- Abnormal situation monitoring

🚨 **Emergency Response Plans:**
- Fall detection and response
- Quick help for physical discomfort
- Automatic family contact process
- Medical emergency guidance

📞 **Regular Health Checks:**
- Daily health status inquiries
- Symptom change monitoring
- Medical appointment reminders and advice
- Doctor follow-up records

📱 **Family Notification System:**
- Daily safety reports
- Immediate notification of unusual situations
- Regular health status updates
- Emergency contact management

Please create in English, ensuring simplicity and ease of use.
QUERY
    ]);

    echo "✅ Safety workflow created!\n";
    echo "   Workflow ID: {$safetyWorkflow->id}\n\n";

    // 4. Create family connection workflow
    echo "👨‍👩‍👧‍👦 Creating family connection workflow...\n";
    $familyWorkflow = $iris->workflows->execute([
        'agent_id' => $agentId,
        'query' => <<<QUERY
Create an elderly family connection system:

📞 **Regular Contact Reminders:**
- Daily video call suggestions
- Weekend family gathering reminders
- Birthday and holiday greetings
- Regular health report sending

🎵 **Entertainment Activity Suggestions:**
- Play elderly-friendly music
- News and weather updates
- Simple voice games
- Reminiscing and memory sharing conversations

📸 **Life Record Sharing:**
- Daily activity photo recording
- Health status updates
- Share life moments with family
- Convey family responses

💌 **Emotional Companionship Features:**
- Warm daily greetings
- Patient listening responses
- Positive emotional support
- Appropriate encouragement and comfort

Please create in English, focusing on emotional care and connection.
QUERY
    ]);

    echo "✅ Family workflow created!\n";
    echo "   Workflow ID: {$familyWorkflow->id}\n\n";

    // 5. Create integration with Google Calendar
    echo "📆 Setting up Google Calendar integration...\n";
    $calendarWorkflow = $iris->workflows->execute([
        'agent_id' => $agentId,
        'query' => <<<QUERY
Set up Google Calendar integration to manage elderly individual's schedule:

📅 **Schedule Management:**
- Automatic doctor appointment reminders
- Family visitor time recording
- Automatic medication time setting
- Daily activity scheduling

⏰ **Smart Reminders:**
- 30-minute advance reminders
- Multiple repeat reminder mechanism
- Confirm reminders are received
- Family synchronization notifications

🔄 **Schedule Synchronization:**
- Share calendar with family
- Auto-add medical appointments
- Automatic recurring event setup
- Instant change notifications

Please create a simple calendar system suitable for elderly users.
QUERY
    ]);

    echo "✅ Calendar integration created!\n";
    echo "   Workflow ID: {$calendarWorkflow->id}\n\n";

    // 6. Test the reminder system
    echo "🧪 Testing reminder system...\n";
    $testQueries = [
        'What time is it? What should I be doing?',
        'Remind me to take my medication',
        'Help me contact my family',
        'I feel a bit dizzy today',
        'What do I have scheduled for 9 PM?'
    ];

    foreach ($testQueries as $query) {
        $response = $iris->agents->chat($agentId, [
            ['role' => 'user', 'content' => $query]
        ]);
        
        echo "   💬 Q: {$query}\n";
        echo "   🤖 A: " . substr($response->content, 0, 100) . "...\n\n";
    }

    echo "🎉 Grandma's Daily Reminder System is ready!\n\n";
    echo "📋 System Features:\n";
    echo "   ⏰ Automated daily schedules\n";
    echo "   💊 Smart medication management\n";
    echo "   🛡️ 24/7 safety monitoring\n";
    echo "   👨‍👩‍👧‍👦 Family connection tools\n";
    echo "   📆 Google Calendar integration\n";
    echo "   🎙️ Voice-activated assistance\n\n";

    echo "🚀 Next Steps:\n";
    echo "   1. Configure Google Calendar credentials\n";
    echo "   2. Set up family contact information\n";
    echo "   3. Customize medication schedule\n";
    echo "   4. Test voice commands\n";
    echo "   5. Enable SMS/email notifications\n\n";

    echo "💡 Usage Examples:\n";
    echo "   • 'Helper, what should I be doing now?'\n";
    echo "   • 'Remind me to take my medication'\n";
    echo "   • 'Help me contact my family'\n";
    echo "   • 'I\'m not feeling well'\n";
    echo "   • 'What\'s my schedule for today?'\n\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "\n💡 Make sure you have:\n";
    echo "   1. Created the basic agent and knowledge base\n";
    echo "   2. Updated agent ID and knowledge base ID\n";
    echo "   3. Enabled integrations in your plan\n\n";
}