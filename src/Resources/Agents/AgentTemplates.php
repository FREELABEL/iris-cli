<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Agents;

/**
 * Agent Template System
 * 
 * Provides pre-configured templates for common agent use cases.
 */
class AgentTemplates
{
    /**
     * Get all available templates.
     *
     * @return array Template definitions
     */
    public static function all(): array
    {
        return [
            'elderly-care' => self::elderlyCare(),
            'customer-support' => self::customerSupport(),
            'sales-assistant' => self::salesAssistant(),
            'research-agent' => self::researchAgent(),
            'leadership-coach' => self::leadershipCoach(),
        ];
    }

    /**
     * Get a specific template by name.
     *
     * @param string $name Template name
     * @return array Template configuration
     * @throws \InvalidArgumentException If template doesn't exist
     */
    public static function get(string $name): array
    {
        $templates = self::all();
        
        if (!isset($templates[$name])) {
            throw new \InvalidArgumentException("Template '{$name}' not found. Available: " . implode(', ', array_keys($templates)));
        }
        
        return $templates[$name];
    }

    /**
     * Elderly care assistant template.
     */
    public static function elderlyCare(): array
    {
        return [
            'name' => 'Elderly Care Assistant',
            'type' => 'content',
            'icon' => 'fas fa-heart',
            'description' => 'Caring assistant for elderly individuals with medication reminders and safety monitoring',
            'initial_prompt' => <<<PROMPT
You are a gentle, patient, and caring assistant specially designed to help elderly individuals. Your responsibilities include:

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
            'config' => [
                'model' => 'gpt-4o-mini',
                'temperature' => 0.7,
                'maxTokens' => 2048,
            ],
            'settings' => [
                'schedule' => [
                    'enabled' => true,
                    'timezone' => 'America/New_York',
                    'frequency' => 'always_on',
                    'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
                    'active_hours' => [
                        'start' => '07:00',
                        'end' => '22:00'
                    ],
                    'recurring_tasks' => [
                        [
                            'name' => 'Morning Medication',
                            'time' => '08:00',
                            'message' => 'Good morning! Time for your morning medications'
                        ],
                        [
                            'name' => 'Lunch Medication',
                            'time' => '12:00',
                            'message' => 'It\'s lunchtime. Remember your medications'
                        ],
                        [
                            'name' => 'Afternoon Water Reminder',
                            'time' => '15:00',
                            'message' => 'Time to drink some water to stay hydrated'
                        ],
                        [
                            'name' => 'Evening Medication',
                            'time' => '18:00',
                            'message' => 'Good evening! Time for your dinner medications'
                        ],
                        [
                            'name' => 'Bedtime Medication',
                            'time' => '21:00',
                            'message' => 'Time for your bedtime medications. Sleep well!'
                        ]
                    ]
                ],
                'agentIntegrations' => [
                    'gmail' => true,
                    'google-calendar' => true,
                    'slack' => false,
                    'google-drive' => false
                ],
                'enabledFunctions' => [
                    'manageLeads' => false,
                    'deepResearch' => false,
                    'marketResearch' => false,
                ],
                'responseMode' => 'balanced',
                'communicationStyle' => 'professional',
                'responseLength' => 'concise',
                'memoryPersistence' => true,
                'useKnowledgeBase' => true,
            ]
        ];
    }

    /**
     * Customer support assistant template.
     */
    public static function customerSupport(): array
    {
        return [
            'name' => 'Customer Support Assistant',
            'type' => 'content',
            'icon' => 'fas fa-headset',
            'description' => 'Professional customer support agent with knowledge base integration',
            'initial_prompt' => <<<PROMPT
You are a professional customer support assistant. Your role is to:

📞 **Customer Assistance:**
- Answer product questions clearly and accurately
- Troubleshoot common issues
- Guide customers through processes
- Escalate complex issues appropriately

💼 **Professional Communication:**
- Be friendly, patient, and helpful
- Use clear, jargon-free language
- Acknowledge customer frustration with empathy
- Provide step-by-step guidance

📚 **Knowledge Management:**
- Reference documentation and FAQs
- Stay updated on product changes
- Document recurring issues
- Suggest improvements to help resources

🎯 **Goal:**
Resolve customer issues quickly and leave them satisfied with their experience.
PROMPT,
            'config' => [
                'model' => 'gpt-4o-mini',
                'temperature' => 0.7,
            ],
            'settings' => [
                'agentIntegrations' => [
                    'gmail' => true,
                    'slack' => true,
                    'google-drive' => true,
                ],
                'enabledFunctions' => [
                    'manageLeads' => true,
                    'deepResearch' => true,
                ],
                'responseMode' => 'balanced',
                'communicationStyle' => 'professional',
                'useKnowledgeBase' => true,
            ]
        ];
    }

    /**
     * Sales assistant template.
     */
    public static function salesAssistant(): array
    {
        return [
            'name' => 'Sales Assistant',
            'type' => 'content',
            'icon' => 'fas fa-chart-line',
            'description' => 'Sales and lead qualification assistant',
            'initial_prompt' => <<<PROMPT
You are a sales assistant focused on qualifying leads and supporting sales processes.

💼 **Lead Qualification:**
- Ask discovery questions to understand needs
- Identify decision makers and budget
- Assess timeline and urgency
- Qualify based on ideal customer profile

📊 **Sales Support:**
- Provide product information
- Schedule meetings and demos
- Follow up on proposals
- Track pipeline progress

🎯 **Communication Style:**
- Professional and consultative
- Focus on value and ROI
- Build rapport naturally
- Listen actively to needs

Your goal is to move qualified leads through the sales process efficiently.
PROMPT,
            'config' => [
                'model' => 'gpt-4o-mini',
                'temperature' => 0.8,
            ],
            'settings' => [
                'agentIntegrations' => [
                    'gmail' => true,
                    'google-calendar' => true,
                    'slack' => true,
                ],
                'enabledFunctions' => [
                    'manageLeads' => true,
                    'marketResearch' => true,
                ],
                'responseMode' => 'balanced',
                'communicationStyle' => 'professional',
            ]
        ];
    }

    /**
     * Research agent template.
     */
    public static function researchAgent(): array
    {
        return [
            'name' => 'Research Agent',
            'type' => 'content',
            'icon' => 'fas fa-search',
            'description' => 'Deep research and analysis assistant',
            'initial_prompt' => <<<PROMPT
You are a research assistant specialized in gathering, analyzing, and synthesizing information.

🔍 **Research Capabilities:**
- Conduct comprehensive web research
- Analyze multiple sources
- Identify trends and patterns
- Verify information accuracy

📊 **Analysis & Reporting:**
- Synthesize findings clearly
- Provide evidence-based insights
- Create structured reports
- Highlight key takeaways

🎯 **Research Standards:**
- Be thorough and methodical
- Cite sources when possible
- Acknowledge limitations
- Present balanced perspectives

Your goal is to provide reliable, well-researched information that supports decision-making.
PROMPT,
            'config' => [
                'model' => 'gpt-4o',
                'temperature' => 0.3,
            ],
            'settings' => [
                'agentIntegrations' => [
                    'google-drive' => true,
                ],
                'enabledFunctions' => [
                    'deepResearch' => true,
                    'marketResearch' => true,
                ],
                'responseMode' => 'detailed',
                'communicationStyle' => 'professional',
                'responseLength' => 'detailed',
            ]
        ];
    }

    /**
     * Leadership coach template.
     */
    public static function leadershipCoach(): array
    {
        return [
            'name' => 'Leadership Coach',
            'type' => 'content',
            'icon' => 'fas fa-user-tie',
            'description' => 'Executive and leadership coach for professional development and team management',
            'initial_prompt' => <<<'PROMPT'
You are an experienced leadership coach specializing in executive development and organizational growth.

Your coaching focuses on:
- Strategic thinking and decision-making
- Team management and delegation
- Communication and influence skills
- Emotional intelligence and self-awareness
- Conflict resolution and difficult conversations
- Time management and prioritization
- Building high-performing teams
- Change management and organizational culture

Coaching approach:
- Ask powerful, thought-provoking questions
- Listen actively and identify patterns
- Challenge limiting beliefs constructively
- Provide frameworks and models for thinking
- Hold leaders accountable to their commitments
- Celebrate progress and learning moments
- Create actionable development plans

Your role is to help leaders discover their own insights and solutions, not to provide all the answers. 
Guide them to think deeply, reflect honestly, and commit to meaningful action.
PROMPT,
            'config' => [
                'model' => 'gpt-4o-mini',
                'temperature' => 0.7,
            ],
            'settings' => [
                'agentIntegrations' => [
                    'google-calendar' => true,
                    'gmail' => true,
                    'slack' => false,
                ],
                'enabledFunctions' => [
                    'deepResearch' => true,
                ],
                'responseMode' => 'reflective',
                'communicationStyle' => 'thought-provoking',
                'memoryPersistence' => true,
                'useKnowledgeBase' => true,
            ]
        ];
    }
}
