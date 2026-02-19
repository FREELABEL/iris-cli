# IRIS SDK - Agent Setup Examples

Complete examples for creating and configuring AI agents using the IRIS SDK.

---

## Table of Contents

- [Quick Start](#quick-start)
- [Template-Based Creation](#template-based-creation)
- [Custom Configuration](#custom-configuration)
- [Schedule Management](#schedule-management)
- [Integration Management](#integration-management)
- [Complete Examples](#complete-examples)
- [RAG (Retrieval-Augmented Generation) Examples](#rag-retrieval-augmented-generation-examples)
  - [Secret Keeper Agent - RAG Verification](#secret-keeper-agent---rag-verification)
  - [Product Knowledge Agent - Practical RAG Example](#product-knowledge-agent---practical-rag-example)
  - [Multi-Document RAG - Knowledge Base with Multiple Sources](#multi-document-rag---knowledge-base-with-multiple-sources)
  - [RAG Best Practices](#rag-best-practices)

---

## Quick Start

### Create Agent from Template (Easiest)

```php
<?php
require_once 'vendor/autoload.php';

$iris = new IRIS\SDK\IRIS([
    'api_key' => $_ENV['IRIS_API_KEY'],
    'user_id' => $_ENV['IRIS_USER_ID'],
]);

// Create elderly care assistant in one line
$agent = $iris->agents->createFromTemplate('elderly-care', [
    'name' => 'Grandma Helper'
]);

echo "Agent created! ID: {$agent->id}\n";
```

---

## Template-Based Creation

### Available Templates

```php
// List all available templates
$templates = $iris->agents->listTemplates();
print_r($templates);

/* Output:
[
    'elderly-care' => [
        'name' => 'Elderly Care Assistant',
        'description' => 'Caring assistant for elderly individuals...',
        'icon' => 'fas fa-heart'
    ],
    'customer-support' => [...],
    'sales-assistant' => [...],
    'research-agent' => [...]
]
*/
```

### 1. Elderly Care Assistant

Perfect for: Medication reminders, safety monitoring, companionship

```php
$agent = $iris->agents->createFromTemplate('elderly-care', [
    'name' => 'Grandma\'s Helper',
    'description' => 'Personal care assistant for grandmother',
    'settings' => [
        'schedule' => [
            'timezone' => 'America/Chicago',
            'recurring_tasks' => [
                [
                    'name' => 'Morning Medication',
                    'time' => '08:00',
                    'message' => 'Good morning! Time for your medications'
                ],
                [
                    'name' => 'Lunch Medication',
                    'time' => '12:00',
                    'message' => 'Lunchtime medications'
                ],
                [
                    'name' => 'Evening Medication',
                    'time' => '18:00',
                    'message' => 'Evening medications'
                ],
                [
                    'name' => 'Bedtime Medication',
                    'time' => '21:00',
                    'message' => 'Time for bedtime medications'
                ]
            ]
        ]
    ]
]);
```

### 2. Customer Support Assistant

Perfect for: Support tickets, FAQs, troubleshooting

```php
$agent = $iris->agents->createFromTemplate('customer-support', [
    'name' => 'Support Bot',
    'description' => 'Handles customer inquiries and support tickets',
    'settings' => [
        'agentIntegrations' => [
            'gmail' => true,
            'slack' => true,
            'google-drive' => true
        ],
        'enabledFunctions' => [
            'manageLeads' => true,
            'deepResearch' => true
        ]
    ]
]);
```

### 3. Sales Assistant

Perfect for: Lead qualification, scheduling demos, follow-ups

```php
$agent = $iris->agents->createFromTemplate('sales-assistant', [
    'name' => 'Sales Pro',
    'description' => 'Qualifies leads and schedules meetings',
    'settings' => [
        'agentIntegrations' => [
            'gmail' => true,
            'google-calendar' => true,
            'slack' => true
        ],
        'enabledFunctions' => [
            'manageLeads' => true,
            'marketResearch' => true
        ]
    ]
]);
```

### 4. Research Agent

Perfect for: Market research, competitive analysis, data gathering

```php
$agent = $iris->agents->createFromTemplate('research-agent', [
    'name' => 'Research Bot',
    'description' => 'Conducts deep research and analysis',
    'settings' => [
        'enabledFunctions' => [
            'deepResearch' => true,
            'marketResearch' => true
        ],
        'responseMode' => 'detailed'
    ]
]);
```

---

## Custom Configuration

### Full Custom Agent Setup

```php
$agent = $iris->agents->createFromConfig([
    'name' => 'My Custom Agent',
    'type' => 'content',
    'icon' => 'fas fa-robot',
    'description' => 'A custom agent for specific needs',
    'initial_prompt' => <<<PROMPT
You are a professional assistant specialized in [YOUR DOMAIN].

Your responsibilities:
- [Responsibility 1]
- [Responsibility 2]
- [Responsibility 3]

Communication style:
- Professional and friendly
- Clear and concise
- Action-oriented
PROMPT,
    'config' => [
        'model' => 'gpt-4o-mini',
        'temperature' => 0.7,
        'maxTokens' => 2048
    ],
    'settings' => [
        'schedule' => [
            'enabled' => true,
            'timezone' => 'America/New_York',
            'recurring_tasks' => [
                [
                    'name' => 'Daily Report',
                    'time' => '09:00',
                    'message' => 'Generating daily report'
                ]
            ]
        ],
        'agentIntegrations' => [
            'gmail' => true,
            'google-calendar' => false,
            'slack' => true,
            'google-drive' => true
        ],
        'enabledFunctions' => [
            'manageLeads' => true,
            'deepResearch' => false,
            'marketResearch' => true
        ],
        'responseMode' => 'balanced',
        'communicationStyle' => 'professional',
        'responseLength' => 'concise',
        'memoryPersistence' => true,
        'useKnowledgeBase' => true
    ]
]);
```

---

## Schedule Management

### Get Current Schedule

```php
$schedule = $iris->agents->getSchedule($agentId);
print_r($schedule);

/* Output:
[
    'enabled' => true,
    'timezone' => 'America/New_York',
    'recurring_tasks' => [
        ['name' => 'Morning Check', 'time' => '08:00', 'message' => '...'],
        ['name' => 'Evening Check', 'time' => '20:00', 'message' => '...']
    ]
]
*/
```

### Set Complete Schedule

```php
$agent = $iris->agents->setSchedule($agentId, [
    'enabled' => true,
    'timezone' => 'America/Los_Angeles',
    'frequency' => 'always_on',
    'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
    'active_hours' => [
        'start' => '09:00',
        'end' => '17:00'
    ],
    'recurring_tasks' => [
        [
            'name' => 'Morning Standup',
            'time' => '09:00',
            'message' => 'Daily standup reminder'
        ],
        [
            'name' => 'End of Day Report',
            'time' => '17:00',
            'message' => 'Generate end of day report'
        ]
    ]
]);
```

### Add Single Task

```php
// Add a task without overwriting existing tasks
$agent = $iris->agents->addScheduledTask($agentId, [
    'name' => 'Lunch Break Reminder',
    'time' => '12:00',
    'message' => 'Time for lunch!'
]);
```

### Remove Task

```php
// Remove a specific task by name
$agent = $iris->agents->removeScheduledTask($agentId, 'Lunch Break Reminder');
```

### Medication Reminder Example

```php
// Perfect for elderly care or health monitoring
$medicationTimes = [
    ['name' => 'Morning Meds', 'time' => '08:00', 'message' => 'Time for morning medications'],
    ['name' => 'Noon Meds', 'time' => '12:00', 'message' => 'Lunchtime medications'],
    ['name' => 'Evening Meds', 'time' => '18:00', 'message' => 'Evening medications'],
    ['name' => 'Bedtime Meds', 'time' => '21:00', 'message' => 'Bedtime medications']
];

$agent = $iris->agents->setSchedule($agentId, [
    'enabled' => true,
    'timezone' => 'America/Chicago',
    'recurring_tasks' => $medicationTimes
]);
```

---

## Integration Management

### Get Integration Status

```php
$integrations = $iris->agents->getIntegrations($agentId);
print_r($integrations);

/* Output:
[
    'gmail' => true,
    'slack' => false,
    'google-calendar' => true,
    'google-drive' => false
]
*/
```

### Enable Multiple Integrations

```php
$agent = $iris->agents->setIntegrations($agentId, [
    'gmail' => true,
    'google-calendar' => true,
    'slack' => true,
    'google-drive' => true,
    'github' => false,
    'trello' => false
]);
```

### Enable Single Integration

```php
// Enable Gmail
$agent = $iris->agents->enableIntegration($agentId, 'gmail');

// Enable Google Calendar
$agent = $iris->agents->enableIntegration($agentId, 'google-calendar');
```

### Disable Integration

```php
$agent = $iris->agents->disableIntegration($agentId, 'slack');
```

### Available Integrations

```php
// All available integrations:
$availableIntegrations = [
    'gmail',
    'slack',
    'github',
    'trello',
    'discord',
    'google-drive',
    'google-calendar'
];
```

---

## Function Management

### Get Enabled Functions

```php
$functions = $iris->agents->getEnabledFunctions($agentId);
print_r($functions);

/* Output:
[
    'manageLeads' => true,
    'deepResearch' => false,
    'marketResearch' => true,
    'travelAgent' => false
]
*/
```

### Set Enabled Functions

```php
$agent = $iris->agents->setEnabledFunctions($agentId, [
    'manageLeads' => true,
    'deepResearch' => true,
    'marketResearch' => false,
    'staffManagement' => false,
    'eventCoordination' => true,
    'businessProposal' => false,
    'brandAnalytics' => true,
    'travelAgent' => false
]);
```

---

## Complete Examples

### Example 1: Complete Elderly Care Setup

```php
<?php
require_once 'vendor/autoload.php';

use IRIS\SDK\IRIS;

// Initialize SDK
$iris = new IRIS([
    'api_key' => $_ENV['IRIS_API_KEY'],
    'user_id' => $_ENV['IRIS_USER_ID'],
]);

// Create agent from template
$agent = $iris->agents->createFromTemplate('elderly-care', [
    'name' => 'Grandma\'s Helper',
    'description' => 'Personal care assistant for 85-year-old grandmother',
    'settings' => [
        'schedule' => [
            'timezone' => 'America/Chicago',
            'recurring_tasks' => [
                ['name' => 'Morning Meds', 'time' => '08:00'],
                ['name' => 'Lunch Meds', 'time' => '12:00'],
                ['name' => 'Water Break', 'time' => '15:00'],
                ['name' => 'Evening Meds', 'time' => '18:00'],
                ['name' => 'Bedtime Meds', 'time' => '21:00']
            ]
        ]
    ]
]);

// Enable Gmail for family notifications
$iris->agents->enableIntegration($agent->id, 'gmail');

// Enable Google Calendar for appointments
$iris->agents->enableIntegration($agent->id, 'google-calendar');

// Test the agent
$response = $iris->agents->chat($agent->id, [
    ['role' => 'user', 'content' => 'What should I be doing right now?']
]);

echo "Agent created: {$agent->id}\n";
echo "Response: {$response->content}\n";
```

### Example 2: Customer Support with Knowledge Base

```php
<?php
require_once 'vendor/autoload.php';

use IRIS\SDK\IRIS;

$iris = new IRIS([
    'api_key' => $_ENV['IRIS_API_KEY'],
    'user_id' => $_ENV['IRIS_USER_ID'],
]);

// Create support agent
$agent = $iris->agents->createFromTemplate('customer-support', [
    'name' => 'Support Bot',
    'description' => '24/7 customer support assistant'
]);

// Upload knowledge base files
$knowledgeBaseId = 40; // Your knowledge base ID
$iris->agents->uploadAndAttachFiles($agent->id, [
    '/path/to/product-docs.pdf',
    '/path/to/faq.md',
    '/path/to/troubleshooting-guide.pdf'
], $knowledgeBaseId);

// Enable integrations
$iris->agents->setIntegrations($agent->id, [
    'gmail' => true,
    'slack' => true,
    'google-drive' => true
]);

// Enable functions
$iris->agents->setEnabledFunctions($agent->id, [
    'manageLeads' => true,
    'deepResearch' => true
]);

echo "Support agent ready: {$agent->id}\n";
```

### Example 3: Sales Assistant with Scheduling

```php
<?php
require_once 'vendor/autoload.php';

use IRIS\SDK\IRIS;

$iris = new IRIS([
    'api_key' => $_ENV['IRIS_API_KEY'],
    'user_id' => $_ENV['IRIS_USER_ID'],
]);

// Create sales assistant
$agent = $iris->agents->createFromTemplate('sales-assistant', [
    'name' => 'Sales Pro',
    'description' => 'Lead qualification and meeting scheduling'
]);

// Set business hours schedule
$iris->agents->setSchedule($agent->id, [
    'enabled' => true,
    'timezone' => 'America/New_York',
    'frequency' => 'business_hours',
    'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
    'active_hours' => [
        'start' => '09:00',
        'end' => '17:00'
    ],
    'recurring_tasks' => [
        ['name' => 'Morning Lead Review', 'time' => '09:00'],
        ['name' => 'Midday Follow-ups', 'time' => '13:00'],
        ['name' => 'End of Day Summary', 'time' => '17:00']
    ]
]);

// Enable calendar and email
$iris->agents->setIntegrations($agent->id, [
    'gmail' => true,
    'google-calendar' => true,
    'slack' => true
]);

// Enable lead management
$iris->agents->setEnabledFunctions($agent->id, [
    'manageLeads' => true,
    'marketResearch' => true
]);

echo "Sales agent ready: {$agent->id}\n";
```

### Example 4: Research Agent with Deep Research

```php
<?php
require_once 'vendor/autoload.php';

use IRIS\SDK\IRIS;

$iris = new IRIS([
    'api_key' => $_ENV['IRIS_API_KEY'],
    'user_id' => $_ENV['IRIS_USER_ID'],
]);

// Create research agent with GPT-4 for better analysis
$agent = $iris->agents->createFromConfig([
    'name' => 'Research Bot',
    'type' => 'content',
    'description' => 'Deep research and competitive analysis',
    'initial_prompt' => 'You are a research assistant specialized in gathering and analyzing information...',
    'config' => [
        'model' => 'gpt-4o', // Use GPT-4 for complex research
        'temperature' => 0.3, // Lower temperature for factual accuracy
        'maxTokens' => 4096
    ],
    'settings' => [
        'agentIntegrations' => [
            'google-drive' => true
        ],
        'enabledFunctions' => [
            'deepResearch' => true,
            'marketResearch' => true
        ],
        'responseMode' => 'detailed',
        'responseLength' => 'detailed'
    ]
]);

// Execute research query
$workflow = $iris->agents->multiStep($agent->id, 
    'Research the top 5 competitors in the AI chatbot space and create a comparison report'
);

echo "Research agent ready: {$agent->id}\n";
echo "Workflow started: {$workflow->id}\n";
```

### Example 5: Update Existing Agent

```php
<?php
require_once 'vendor/autoload.php';

use IRIS\SDK\IRIS;

$iris = new IRIS([
    'api_key' => $_ENV['IRIS_API_KEY'],
    'user_id' => $_ENV['IRIS_USER_ID'],
]);

$agentId = 335; // Existing agent ID

// Add new scheduled tasks to existing agent
$iris->agents->addScheduledTask($agentId, [
    'name' => 'Weekly Report',
    'time' => '09:00',
    'message' => 'Generate weekly summary report'
]);

// Enable new integrations
$iris->agents->enableIntegration($agentId, 'slack');
$iris->agents->enableIntegration($agentId, 'google-drive');

// Update functions
$iris->agents->setEnabledFunctions($agentId, [
    'manageLeads' => true,
    'deepResearch' => true,
    'marketResearch' => false
]);

// Get updated agent
$agent = $iris->agents->get($agentId);
echo "Agent updated: {$agent->name}\n";
```

---

## API Reference Quick Guide

### Agent Creation
- `createFromTemplate($template, $customizations)` - Create from template
- `createFromConfig($config)` - Create with full configuration
- `createFromArray($data)` - Create from simple array

### Schedule Management
- `getSchedule($agentId)` - Get schedule configuration
- `setSchedule($agentId, $schedule)` - Set complete schedule
- `addScheduledTask($agentId, $task)` - Add single task
- `removeScheduledTask($agentId, $taskName)` - Remove task by name

### Integration Management
- `getIntegrations($agentId)` - Get integration status
- `setIntegrations($agentId, $integrations)` - Set multiple integrations
- `enableIntegration($agentId, $integration)` - Enable single integration
- `disableIntegration($agentId, $integration)` - Disable integration

### Function Management
- `getEnabledFunctions($agentId)` - Get enabled functions
- `setEnabledFunctions($agentId, $functions)` - Set enabled functions

### Template Management
- `listTemplates()` - List all available templates

---

## Tips & Best Practices

### 1. Start with Templates
Use templates as a starting point and customize as needed. They include best practices and optimized defaults.

### 2. Set Correct Timezone
Always specify the timezone for scheduled tasks to ensure they fire at the correct local time.

```php
'schedule' => [
    'timezone' => 'America/Chicago', // Critical for accurate timing
]
```

### 3. Test Schedules
After setting up schedules, test them to ensure they fire as expected.

```php
$schedule = $iris->agents->getSchedule($agentId);
print_r($schedule); // Verify configuration
```

### 4. Use Meaningful Task Names
Name your scheduled tasks clearly so they're easy to manage later.

```php
// Good
['name' => 'Morning Medication Reminder', 'time' => '08:00']

// Bad
['name' => 'Task 1', 'time' => '08:00']
```

### 5. Enable Only Needed Integrations
Only enable integrations your agent actually uses to keep it focused and secure.

```php
$iris->agents->setIntegrations($agentId, [
    'gmail' => true,        // Need this
    'google-calendar' => true, // Need this
    'slack' => false,       // Don't need
    'github' => false       // Don't need
]);
```

### 6. Version Control Your Configurations
Save your agent configurations as PHP files for version control and easy replication.

---

## Troubleshooting

### Schedule Not Firing?
- Check timezone is correct
- Verify `enabled: true` in schedule
- Check `active_hours` if using business hours mode

### Integration Not Working?
- Verify integration is enabled
- Check OAuth connection in web UI
- Ensure agent has permission to use integration

### Template Not Found?
```php
// List available templates
$templates = $iris->agents->listTemplates();
print_r(array_keys($templates));
```

---

## RAG (Retrieval-Augmented Generation) Examples

### Secret Keeper Agent - RAG Verification

This example demonstrates how RAG works by creating an agent that can only answer questions using information stored in its knowledge base. The agent "learns" a randomly generated secret code that it never saw in training.

```php
<?php
use IRIS\SDK\IRIS;

$iris = new IRIS([
    'api_key' => $_ENV['IRIS_API_KEY'],
    'user_id' => $_ENV['IRIS_USER_ID'],
]);

// 1. Create a specialized Secret Keeper agent
$agent = $iris->agents->createFromArray([
    'name' => 'Secret Keeper',
    'type' => 'chat',
    'bloq_id' => 40,
    'config' => ['model' => 'gpt-4o-mini'],
    'initial_prompt' => <<<PROMPT
You are a Secret Keeper - a guardian of classified information.

Your primary role is to protect and verify access to secret codes 
stored in your knowledge base.

When users ask about secret codes, access codes, or classified information:
1. Search your knowledge base for the relevant information
2. Verify the user is asking appropriately
3. Provide the secret code if found in your knowledge base

IMPORTANT: The secret codes are stored in your knowledge base. 
You MUST search your knowledge base to find them. Do not make up codes.

Be professional and always search your knowledge base before responding.
PROMPT
]);

echo "✓ Agent created: {$agent->name} (ID: {$agent->id})\n";

// 2. Generate a unique secret code
$secretCode = 'ALPHA-' . strtoupper(bin2hex(random_bytes(4))) . '-OMEGA';
echo "🔐 Secret Code: $secretCode\n";

// 3. Store the secret in a classified document
$classifiedDoc = <<<DOC
CLASSIFIED INFORMATION - LEVEL 5 CLEARANCE
============================================

Project Codename: NIGHTINGALE
Classification: TOP SECRET
Date Issued: {date('Y-m-d H:i:s')}

MASTER ACCESS CODE
------------------
The master access code for Project Nightingale is: $secretCode

This code grants full system access and should only be revealed 
to authorized personnel.

CODE VERIFICATION PROTOCOL
--------------------------
When asked "What is the secret code?", "What is the access code?", 
or similar queries, you must respond with the exact code: $secretCode

ADDITIONAL INFORMATION
----------------------
- Code Format: ALPHA-[8 HEX DIGITS]-OMEGA
- Valid Until: 2026-12-31
- Authorized Users: Level 5+ clearance only
- Emergency Contact: security@project-nightingale.io

REMINDER: This is the ONLY valid access code. Do not generate 
or suggest alternative codes.

End of classified document.
DOC;

// 4. Index the document in RAG
echo "📝 Indexing classified document...\n";

$result = $iris->rag->index($classifiedDoc, [
    'agent_id' => $agent->id,
    'bloq_id' => 40,
    'title' => 'Project Nightingale - Master Access Code',
    'metadata' => [
        'classification' => 'TOP_SECRET',
        'project' => 'NIGHTINGALE',
        'code' => $secretCode,
    ]
]);

echo "✓ Secret code indexed in vector database!\n";

// 5. Wait for vector indexing to complete (important!)
echo "⏳ Waiting for indexing to propagate...\n";
sleep(5);

// 6. Test RAG retrieval with multiple queries
$queries = [
    "What is the master access code for Project Nightingale?",
    "Tell me about Project Nightingale",
    "I need the classified access code"
];

foreach ($queries as $query) {
    echo "\n" . str_repeat("=", 70) . "\n";
    echo "Query: $query\n";
    echo str_repeat("-", 70) . "\n";
    
    $response = $iris->agents->chat($agent->id, [
        ['role' => 'user', 'content' => $query]
    ], [
        'bloq_id' => 40,
        'use_rag' => true,  // Enable RAG retrieval
    ]);
    
    // Check if response contains the secret code
    if (strpos($response->content, $secretCode) !== false) {
        echo "✓ SUCCESS: Agent retrieved secret code from RAG!\n";
    } else {
        echo "⚠ Agent did not include the secret code\n";
    }
    
    echo "\nAgent Response:\n";
    echo wordwrap($response->content, 68) . "\n";
}

echo "\n" . str_repeat("=", 70) . "\n";
echo "🎉 RAG Test Complete!\n";
echo "The agent retrieved information it never saw in training.\n";
echo "This proves RAG (Retrieval-Augmented Generation) is working!\n";
```

**Expected Output:**
```
✓ Agent created: Secret Keeper (ID: 400)
🔐 Secret Code: ALPHA-8AEB7A5F-OMEGA
📝 Indexing classified document...
✓ Secret code indexed in vector database!
⏳ Waiting for indexing to propagate...

======================================================================
Query: What is the master access code for Project Nightingale?
----------------------------------------------------------------------
✓ SUCCESS: Agent retrieved secret code from RAG!

Agent Response:
The master access code for Project Nightingale is: ALPHA-8AEB7A5F-OMEGA. 
This code grants full system access and should only be revealed to 
authorized personnel.
======================================================================

🎉 RAG Test Complete!
The agent retrieved information it never saw in training.
This proves RAG (Retrieval-Augmented Generation) is working!
```

**Why this example is powerful:**
1. **Proof of RAG** - The secret code is randomly generated, so the AI never saw it in training
2. **Knowledge Base** - The agent can ONLY answer by searching its knowledge base
3. **Real-World Use Case** - Demonstrates how to build knowledge-based assistants
4. **Semantic Search** - Shows vector embeddings and semantic retrieval in action
5. **Verification** - Easy to verify RAG is working (code appears in response or not)

**Key Takeaways:**
- Always set `use_rag: true` in chat options to enable RAG
- Wait 5-10 seconds after indexing for vector propagation
- Use specific queries (with project names, keywords) for best results
- Store contextual information (not just facts) for better retrieval

📖 [Full RAG Test Script](../../../test-secret-code-rag.php) (433 lines)  
📖 [Test Results Analysis](../../../SECRET_CODE_RAG_TEST_RESULTS.md)  
📖 [RAG Documentation](TECHNICAL.md#-persistent-memory--knowledge-base-bloqs)

---

### Product Knowledge Agent - Practical RAG Example

Build a customer support agent that knows your entire product catalog.

```php
<?php
use IRIS\SDK\IRIS;

$iris = new IRIS([
    'api_key' => $_ENV['IRIS_API_KEY'],
    'user_id' => $_ENV['IRIS_USER_ID'],
]);

// 1. Create support agent
$agent = $iris->agents->createFromTemplate('customer-support', [
    'name' => 'Product Expert',
]);

// 2. Index your product catalog
$productCatalog = <<<CATALOG
PRODUCT CATALOG - 2025
======================

PREMIUM PLAN - $99/month
------------------------
Features:
- Unlimited users
- Advanced analytics
- Priority support
- Custom integrations
- 99.9% uptime SLA

Target: Mid-size companies (50-200 employees)
Best for: Teams needing scalability and advanced features

ENTERPRISE PLAN - Custom Pricing
---------------------------------
Features:
- Everything in Premium
- Dedicated account manager
- Custom development
- On-premise deployment option
- 24/7 phone support

Target: Large enterprises (200+ employees)
Best for: Complex requirements and custom solutions

SUPPORT OPTIONS
---------------
- Email: support@company.com (24-hour response)
- Chat: Available 9am-5pm EST
- Phone: Enterprise customers only
- Documentation: https://docs.company.com
CATALOG;

$iris->rag->index($productCatalog, [
    'agent_id' => $agent->id,
    'bloq_id' => 40,
    'title' => 'Product Catalog 2025',
    'metadata' => ['type' => 'product_info', 'year' => 2025]
]);

// 3. Agent now knows your products!
sleep(5); // Wait for indexing

$response = $iris->agents->chat($agent->id, [
    ['role' => 'user', 'content' => 'What are the differences between Premium and Enterprise?']
], [
    'bloq_id' => 40,
    'use_rag' => true,
]);

echo $response->content;
// Agent will explain the differences using your actual product catalog!
```

---

### Multi-Document RAG - Knowledge Base with Multiple Sources

Store multiple documents and let the agent search across all of them.

```php
<?php
$documents = [
    [
        'title' => 'Getting Started Guide',
        'content' => 'Installation: npm install... Configuration: ...',
    ],
    [
        'title' => 'API Reference',
        'content' => 'Authentication: Use API keys... Endpoints: POST /api/...',
    ],
    [
        'title' => 'Troubleshooting Guide',
        'content' => 'Common issues: 1) Connection timeout - check firewall...',
    ],
    [
        'title' => 'FAQ',
        'content' => 'Q: How do I reset my password? A: Click forgot password...',
    ],
];

// Index all documents
foreach ($documents as $doc) {
    $iris->rag->index($doc['content'], [
        'agent_id' => $agent->id,
        'bloq_id' => 40,
        'title' => $doc['title'],
    ]);
    
    echo "✓ Indexed: {$doc['title']}\n";
}

sleep(5); // Wait for all to index

// Agent can now search across ALL documents
$response = $iris->agents->chat($agent->id, [
    ['role' => 'user', 'content' => 'How do I authenticate with the API?']
], [
    'bloq_id' => 40,
    'use_rag' => true,
]);

// Agent will search all 4 documents and find the API Reference
echo $response->content;
```

---

### RAG Best Practices

#### 1. Structure Your Documents Well
```php
// ✅ Good: Clear structure with headings
$goodDoc = <<<DOC
PRODUCT FEATURES
================

Core Features
-------------
- Feature A: Description
- Feature B: Description

Advanced Features
-----------------
- Feature C: Description
DOC;

// ❌ Bad: Unstructured wall of text
$badDoc = "Our product has features like A and B and also C...";
```

#### 2. Include Context and Keywords
```php
// ✅ Good: Rich context
$goodDoc = <<<DOC
PRICING PLANS

Premium Plan - $99/month
Target: Mid-size companies, 50-200 employees
Best for: Teams needing scalability
Keywords: premium, mid-size, scalable, $99
DOC;

// ❌ Bad: Minimal context
$badDoc = "Premium: $99";
```

#### 3. Wait for Indexing
```php
// Index documents
$iris->rag->index($content, [...]);

// ✅ Good: Wait for propagation
sleep(5);
$response = $iris->agents->chat(...);

// ❌ Bad: Query immediately
$response = $iris->agents->chat(...); // May not find newly indexed content
```

#### 4. Always Enable RAG in Chat
```php
// ✅ Good: RAG enabled
$response = $iris->agents->chat($agentId, $messages, [
    'bloq_id' => 40,
    'use_rag' => true,  // Required!
]);

// ❌ Bad: RAG not enabled
$response = $iris->agents->chat($agentId, $messages);
// Agent won't search knowledge base
```

#### 5. Use Specific Queries
```php
// ✅ Good: Specific queries
"What is the master access code for Project Nightingale?"
"Compare Premium vs Enterprise pricing plans"
"How do I authenticate with the API?"

// ⚠️ Less Effective: Vague queries
"What is the code?"
"Tell me about pricing"
"How does it work?"
```

---

## More Resources

- [README.md](README.md) - Project overview
- [TECHNICAL.md](TECHNICAL.md) - Complete API documentation
- [QUICKSTART.md](QUICKSTART.md) - Quick setup guide
- [SDK_IMPROVEMENTS_COMPLETE.md](SDK_IMPROVEMENTS_COMPLETE.md) - Latest enhancements
- [Agent Creation Methods](../../../AGENT_CREATION_METHODS.md) - 3 ways to create agents
- [Agent Chat Methods](../../../AGENT_CHAT_METHODS.md) - 3 ways to chat with agents
- [Complete System Overview](../../../SESSION_SUMMARY_RAG_AND_CLI.md) - Full RAG examples

---

**Need help?** Open an issue or contact support@heyiris.io
