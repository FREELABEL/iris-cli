<?php

declare(strict_types=1);

namespace IRIS\SDK\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use IRIS\SDK\IRIS;

/**
 * Atlas OS CLI — business operations from the command line.
 *
 * Usage:
 *   iris atlas inventory list               List inventory items
 *   iris atlas budget log-expense           Log an expense
 *   iris atlas staff list                   List staff members
 *   iris atlas events create               Create an event
 *   iris atlas calendar schedule            Schedule a social post
 *   iris atlas research query              Research a topic
 *   iris atlas docs store                  Store a document
 */
class AtlasOsCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('atlas')
            ->setAliases(['atlas-os'])
            ->setDescription('Atlas OS — manage inventory, budget, staff, events, calendar, research, docs')
            ->setHelp(<<<'HELP'
Atlas OS business operations CLI.

Functions:
  inventory   Manage inventory items (create, list, search, update, delete)
  budget      Log expenses/revenue, get summaries
  staff       Manage staff, assign to events, create tasks
  events      Plan events, manage vendors, tickets, RSVPs
  calendar    Schedule and manage social media posts
  research    Web-powered market research with RAG storage
  docs        Store and search documents with vector embedding
  contracts   Contractor agreements, vendor terms, compliance tracking

Examples:
  atlas inventory list --bloq-id=217
  atlas budget log-expense --description="Office supplies" --amount=150
  atlas events create --name="Launch Party" --date="2026-04-15"
  atlas research query --query="AI market trends"
  atlas docs store --title="Meeting Notes" --content="..."
HELP
            )
            ->addArgument('function', InputArgument::OPTIONAL, 'Function: inventory, budget, staff, events, calendar, research, docs, contracts', 'help')
            ->addArgument('action', InputArgument::OPTIONAL, 'Action (function-specific)', 'list')
            ->addArgument('id', InputArgument::OPTIONAL, 'Item/event/staff/post/task ID')
            // Global options
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON')
            ->addOption('bloq-id', null, InputOption::VALUE_REQUIRED, 'Bloq (knowledge base) ID')
            ->addOption('api-key', null, InputOption::VALUE_REQUIRED, 'API key override')
            ->addOption('user-id', null, InputOption::VALUE_REQUIRED, 'User ID override')
            ->addOption('env', 'e', InputOption::VALUE_REQUIRED, 'Environment: local, production')
            // Inventory options
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Item/staff/event name')
            ->addOption('quantity', null, InputOption::VALUE_REQUIRED, 'Quantity')
            ->addOption('unit-cost', null, InputOption::VALUE_REQUIRED, 'Unit cost')
            ->addOption('supplier', null, InputOption::VALUE_REQUIRED, 'Supplier name')
            ->addOption('category', null, InputOption::VALUE_REQUIRED, 'Category')
            ->addOption('sku', null, InputOption::VALUE_REQUIRED, 'SKU code')
            ->addOption('reorder-point', null, InputOption::VALUE_REQUIRED, 'Reorder point quantity')
            // Budget options
            ->addOption('description', null, InputOption::VALUE_REQUIRED, 'Description')
            ->addOption('amount', null, InputOption::VALUE_REQUIRED, 'Amount')
            ->addOption('period', null, InputOption::VALUE_REQUIRED, 'Period: week, month, quarter, year, all')
            // Staff options
            ->addOption('role', null, InputOption::VALUE_REQUIRED, 'Staff role')
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Email address')
            ->addOption('phone', null, InputOption::VALUE_REQUIRED, 'Phone number')
            ->addOption('department', null, InputOption::VALUE_REQUIRED, 'Department')
            ->addOption('hourly-rate', null, InputOption::VALUE_REQUIRED, 'Hourly rate')
            ->addOption('staff-id', null, InputOption::VALUE_REQUIRED, 'Staff member ID')
            ->addOption('event-id', null, InputOption::VALUE_REQUIRED, 'Event ID')
            ->addOption('deliverables', null, InputOption::VALUE_REQUIRED, 'Deliverables description')
            ->addOption('title', null, InputOption::VALUE_REQUIRED, 'Task/document title')
            ->addOption('due', null, InputOption::VALUE_REQUIRED, 'Due date')
            ->addOption('task-id', null, InputOption::VALUE_REQUIRED, 'Task ID')
            ->addOption('item-id', null, InputOption::VALUE_REQUIRED, 'BloqItem ID (direct projection link)')
            // Event options
            ->addOption('date', null, InputOption::VALUE_REQUIRED, 'Event date')
            ->addOption('time', null, InputOption::VALUE_REQUIRED, 'Event time')
            ->addOption('type', null, InputOption::VALUE_REQUIRED, 'Event type: conference, workshop, party, launch, meeting, wedding, concert, fundraiser, networking, retreat')
            ->addOption('location', null, InputOption::VALUE_REQUIRED, 'Event location/venue')
            ->addOption('guest-count', null, InputOption::VALUE_REQUIRED, 'Expected guest count')
            ->addOption('budget-amount', null, InputOption::VALUE_REQUIRED, 'Event budget')
            ->addOption('status', null, InputOption::VALUE_REQUIRED, 'Status: planning, confirmed, in_progress, completed, cancelled')
            ->addOption('vendor-name', null, InputOption::VALUE_REQUIRED, 'Vendor name')
            ->addOption('vendor-group', null, InputOption::VALUE_REQUIRED, 'Vendor group/category')
            ->addOption('vendor-contact', null, InputOption::VALUE_REQUIRED, 'Vendor contact info')
            ->addOption('vendor-price', null, InputOption::VALUE_REQUIRED, 'Vendor price')
            ->addOption('vendor-id', null, InputOption::VALUE_REQUIRED, 'Vendor ID')
            ->addOption('ticket-name', null, InputOption::VALUE_REQUIRED, 'Ticket tier name')
            ->addOption('ticket-price', null, InputOption::VALUE_REQUIRED, 'Ticket price')
            ->addOption('ticket-quantity', null, InputOption::VALUE_REQUIRED, 'Ticket quantity available')
            ->addOption('ticket-id', null, InputOption::VALUE_REQUIRED, 'Ticket ID')
            ->addOption('rsvp-status', null, InputOption::VALUE_REQUIRED, 'RSVP status: attending, maybe, not attending')
            // Calendar options
            ->addOption('content', null, InputOption::VALUE_REQUIRED, 'Post content / document content')
            ->addOption('platforms', null, InputOption::VALUE_REQUIRED, 'Platforms: twitter,instagram,facebook,tiktok,linkedin')
            ->addOption('scheduled-at', null, InputOption::VALUE_REQUIRED, 'Schedule date/time: YYYY-MM-DD HH:MM')
            ->addOption('media-urls', null, InputOption::VALUE_REQUIRED, 'Media URLs (comma-separated)')
            ->addOption('post-id', null, InputOption::VALUE_REQUIRED, 'Social post ID')
            // Research options
            ->addOption('query', null, InputOption::VALUE_REQUIRED, 'Search/research query')
            ->addOption('competitor', null, InputOption::VALUE_REQUIRED, 'Competitor name')
            // Document options
            ->addOption('doc-type', null, InputOption::VALUE_REQUIRED, 'Document type: sop, meeting_notes, policy, plan, research, website_copy, general')
            ->addOption('tags', null, InputOption::VALUE_REQUIRED, 'Tags (comma-separated)')
            ->addOption('notes', null, InputOption::VALUE_REQUIRED, 'Notes')
            // Contract options
            ->addOption('contract-type', null, InputOption::VALUE_REQUIRED, 'Contract type: 1099, w2, vendor, performance, retainer')
            ->addOption('start-date', null, InputOption::VALUE_REQUIRED, 'Contract start date (YYYY-MM-DD)')
            ->addOption('end-date', null, InputOption::VALUE_REQUIRED, 'Contract end date (YYYY-MM-DD)')
            ->addOption('total-value', null, InputOption::VALUE_REQUIRED, 'Total contract value in dollars')
            ->addOption('scope-of-work', null, InputOption::VALUE_REQUIRED, 'Scope of work description')
            ->addOption('payment-terms', null, InputOption::VALUE_REQUIRED, 'Payment terms: net_30, 50_upfront_50_completion, on_delivery')
            ->addOption('payment-schedule', null, InputOption::VALUE_REQUIRED, 'Payment schedule details')
            ->addOption('nda-signed', null, InputOption::VALUE_NONE, 'NDA has been signed')
            ->addOption('w9-on-file', null, InputOption::VALUE_NONE, 'W9 is on file')
            ->addOption('insurance-verified', null, InputOption::VALUE_NONE, 'Insurance has been verified')
            ->addOption('insurance-expiry', null, InputOption::VALUE_REQUIRED, 'Insurance expiry date (YYYY-MM-DD)')
            ->addOption('certifications', null, InputOption::VALUE_REQUIRED, 'Certifications (comma-separated)')
            ->addOption('background-check', null, InputOption::VALUE_REQUIRED, 'Background check status: pending, cleared, failed')
            ->addOption('opportunity-id', null, InputOption::VALUE_REQUIRED, 'Link contract to a marketplace opportunity ID')
            // Deliverable options
            ->addOption('verdict', null, InputOption::VALUE_REQUIRED, 'Review verdict: approved or revision_needed')
            ->addOption('reviewer-notes', null, InputOption::VALUE_REQUIRED, 'Review notes for the deliverable');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $function = $input->getArgument('function');
        $action = $input->getArgument('action');
        $id = $input->getArgument('id');
        $jsonOutput = $input->getOption('json');

        if ($function === 'help' || !$function) {
            $io->title('Atlas OS — Business Operations CLI');
            $io->table(
                ['Function', 'Description', 'Actions'],
                [
                    ['inventory', 'Manage inventory items', 'list, create, show, update, delete, search'],
                    ['budget', 'Track expenses & revenue', 'log-expense, log-revenue, summary, list, search, delete'],
                    ['staff', 'Manage team members', 'list, create, show, update, delete, search, assign, create-task, tasks, complete-task, submit-deliverable, review-deliverable'],
                    ['events', 'Plan & manage events', 'list, create, show, update, delete, search, add-vendor, vendors, add-ticket, tickets, rsvp, rsvps'],
                    ['calendar', 'Social media scheduling', 'schedule, list, update, publish, delete'],
                    ['research', 'Market research + RAG', 'query, list, search'],
                    ['docs', 'Document management', 'store, list, search, update, delete'],
                    ['contracts', 'Agreements & compliance', 'create, list, show, update-status, compliance, check, vendor-terms, committed-budget, send'],
                ]
            );
            $io->text('Usage: iris atlas <function> <action> [options]');
            $io->text('Global: --bloq-id=N (or IRIS_BLOQ_ID env) --json');
            return Command::SUCCESS;
        }

        // Handle --env
        $env = $input->getOption('env');
        if ($env) {
            putenv("IRIS_ENV={$env}");
            $_ENV['IRIS_ENV'] = $env;
        }

        // Initialize SDK
        $configOptions = [];
        if ($apiKey = $input->getOption('api-key')) {
            $configOptions['api_key'] = $apiKey;
        }
        if ($userId = $input->getOption('user-id')) {
            $configOptions['user_id'] = (int) $userId;
        }

        try {
            $iris = new IRIS($configOptions);
        } catch (\Exception $e) {
            $io->error('SDK initialization failed: ' . $e->getMessage());
            $io->text('Run: iris setup');
            return Command::FAILURE;
        }

        // Resolve bloq ID
        $bloqId = $this->resolveBloqId($input, $io);
        if (!$bloqId) {
            $io->error('Bloq ID is required. Use --bloq-id=N or set IRIS_BLOQ_ID environment variable.');
            return Command::FAILURE;
        }

        try {
            switch ($function) {
                case 'inventory':
                case 'inv':
                    return $this->handleInventory($iris, $io, $input, $action, $id, $bloqId, $jsonOutput);
                case 'budget':
                case 'bud':
                    return $this->handleBudget($iris, $io, $input, $action, $id, $bloqId, $jsonOutput);
                case 'staff':
                    return $this->handleStaff($iris, $io, $input, $action, $id, $bloqId, $jsonOutput);
                case 'events':
                case 'event':
                    return $this->handleEvents($iris, $io, $input, $action, $id, $bloqId, $jsonOutput);
                case 'calendar':
                case 'cal':
                    return $this->handleCalendar($iris, $io, $input, $action, $id, $bloqId, $jsonOutput);
                case 'research':
                case 'res':
                    return $this->handleResearch($iris, $io, $input, $action, $id, $bloqId, $jsonOutput);
                case 'docs':
                case 'doc':
                case 'documents':
                    return $this->handleDocuments($iris, $io, $input, $action, $id, $bloqId, $jsonOutput);
                case 'contracts':
                case 'contract':
                    return $this->handleContracts($iris, $io, $input, $action, $id, $bloqId, $jsonOutput);
                default:
                    $io->error("Unknown function: {$function}");
                    $io->text('Available: inventory, budget, staff, events, calendar, research, docs');
                    return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
    }

    // ========================================================================
    // Bloq ID Resolution
    // ========================================================================

    private function resolveBloqId(InputInterface $input, SymfonyStyle $io): ?int
    {
        $bloqId = $input->getOption('bloq-id');
        if ($bloqId) {
            return (int) $bloqId;
        }

        $envBloqId = getenv('IRIS_BLOQ_ID');
        if ($envBloqId) {
            return (int) $envBloqId;
        }

        $bloqId = $io->ask('Bloq ID (knowledge base)');
        return $bloqId ? (int) $bloqId : null;
    }

    // ========================================================================
    // INVENTORY
    // ========================================================================

    private function handleInventory(IRIS $iris, SymfonyStyle $io, InputInterface $input, string $action, ?string $id, int $bloqId, bool $jsonOutput): int
    {
        switch ($action) {
            case 'list':
            case 'ls':
                $result = $iris->atlasOs->inventory('list', ['_bloq_id' => $bloqId]);
                return $this->outputList($io, $result, $jsonOutput, ['item_id', 'name', 'quantity', 'unit_cost', 'supplier', 'category'], 'Inventory');

            case 'create':
            case 'add':
                $name = $input->getOption('name') ?? $io->ask('Item name');
                if (!$name) {
                    $io->error('--name is required');
                    return Command::FAILURE;
                }
                $params = ['_bloq_id' => $bloqId, 'name' => $name];
                $this->addOptional($params, $input, ['quantity', 'unit-cost' => 'unit_cost', 'supplier', 'category', 'sku', 'reorder-point' => 'reorder_point', 'notes']);
                $result = $iris->atlasOs->inventory('create', $params);
                return $this->outputSuccess($io, $result, $jsonOutput, "Inventory item '{$name}' created");

            case 'show':
            case 'read':
            case 'get':
                $itemId = $id ?? $input->getOption('id') ?? $io->ask('Item ID');
                $result = $iris->atlasOs->inventory('read', ['_bloq_id' => $bloqId, 'item_id' => (int) $itemId]);
                return $this->outputDetail($io, $result, $jsonOutput);

            case 'update':
                $itemId = $id ?? $io->ask('Item ID');
                $params = ['_bloq_id' => $bloqId, 'item_id' => (int) $itemId];
                $this->addOptional($params, $input, ['name', 'quantity', 'unit-cost' => 'unit_cost', 'supplier', 'category', 'sku', 'reorder-point' => 'reorder_point', 'notes']);
                $result = $iris->atlasOs->inventory('update', $params);
                return $this->outputSuccess($io, $result, $jsonOutput, "Item #{$itemId} updated");

            case 'delete':
            case 'rm':
                $itemId = $id ?? $io->ask('Item ID');
                if (!$this->confirmDelete($io, $input, "inventory item #{$itemId}")) {
                    return Command::SUCCESS;
                }
                $result = $iris->atlasOs->inventory('delete', ['_bloq_id' => $bloqId, 'item_id' => (int) $itemId]);
                return $this->outputSuccess($io, $result, $jsonOutput, "Item #{$itemId} deleted");

            case 'search':
            case 'find':
                $query = $input->getOption('query') ?? $io->ask('Search query');
                $result = $iris->atlasOs->inventory('search', ['_bloq_id' => $bloqId, 'query' => $query]);
                return $this->outputList($io, $result, $jsonOutput, ['item_id', 'name', 'quantity', 'unit_cost', 'supplier'], "Inventory search: {$query}");

            default:
                $io->error("Unknown inventory action: {$action}");
                $io->text('Available: list, create, show, update, delete, search');
                return Command::FAILURE;
        }
    }

    // ========================================================================
    // BUDGET
    // ========================================================================

    private function handleBudget(IRIS $iris, SymfonyStyle $io, InputInterface $input, string $action, ?string $id, int $bloqId, bool $jsonOutput): int
    {
        switch ($action) {
            case 'log-expense':
            case 'expense':
                $desc = $input->getOption('description') ?? $io->ask('Description');
                $amount = $input->getOption('amount') ?? $io->ask('Amount');
                if (!$desc || !$amount) {
                    $io->error('--description and --amount are required');
                    return Command::FAILURE;
                }
                $params = ['_bloq_id' => $bloqId, 'description' => $desc, 'amount' => (float) $amount];
                $this->addOptional($params, $input, ['category', 'date']);
                $result = $iris->atlasOs->budget('log_expense', $params);
                return $this->outputSuccess($io, $result, $jsonOutput, "Expense logged: {$desc} — \${$amount}");

            case 'log-revenue':
            case 'revenue':
                $desc = $input->getOption('description') ?? $io->ask('Description');
                $amount = $input->getOption('amount') ?? $io->ask('Amount');
                if (!$desc || !$amount) {
                    $io->error('--description and --amount are required');
                    return Command::FAILURE;
                }
                $params = ['_bloq_id' => $bloqId, 'description' => $desc, 'amount' => (float) $amount];
                $this->addOptional($params, $input, ['category', 'date']);
                $result = $iris->atlasOs->budget('log_revenue', $params);
                return $this->outputSuccess($io, $result, $jsonOutput, "Revenue logged: {$desc} — \${$amount}");

            case 'summary':
                $period = $input->getOption('period') ?? 'month';
                $result = $iris->atlasOs->budget('get_summary', ['_bloq_id' => $bloqId, 'period' => $period]);
                if ($jsonOutput) {
                    $io->writeln(json_encode($result, JSON_PRETTY_PRINT));
                    return Command::SUCCESS;
                }
                $data = $result['data'] ?? $result;
                $io->title("Budget Summary — {$period}");
                $io->definitionList(
                    ['Revenue' => '$' . number_format((float) ($data['total_revenue'] ?? 0), 2)],
                    ['Expenses' => '$' . number_format((float) ($data['total_expenses'] ?? 0), 2)],
                    ['Profit/Loss' => '$' . number_format((float) ($data['profit_loss'] ?? 0), 2)],
                    ['Transactions' => $data['transaction_count'] ?? 0],
                    ['Period' => $data['period'] ?? $period]
                );
                return Command::SUCCESS;

            case 'list':
            case 'ls':
                $result = $iris->atlasOs->budget('list', ['_bloq_id' => $bloqId]);
                return $this->outputList($io, $result, $jsonOutput, ['item_id', 'title', 'content.type', 'content.amount', 'content.category'], 'Budget');

            case 'search':
            case 'find':
                $query = $input->getOption('query') ?? $io->ask('Search query');
                $result = $iris->atlasOs->budget('search', ['_bloq_id' => $bloqId, 'query' => $query]);
                return $this->outputList($io, $result, $jsonOutput, ['item_id', 'title', 'content.amount', 'content.category'], "Budget search: {$query}");

            case 'delete':
            case 'rm':
                $itemId = $id ?? $input->getOption('id') ?? $io->ask('Transaction ID');
                if (!$this->confirmDelete($io, $input, "transaction #{$itemId}")) {
                    return Command::SUCCESS;
                }
                $result = $iris->atlasOs->budget('delete', ['_bloq_id' => $bloqId, 'item_id' => (int) $itemId]);
                return $this->outputSuccess($io, $result, $jsonOutput, "Transaction #{$itemId} deleted");

            default:
                $io->error("Unknown budget action: {$action}");
                $io->text('Available: log-expense, log-revenue, summary, list, search, delete');
                return Command::FAILURE;
        }
    }

    // ========================================================================
    // STAFF
    // ========================================================================

    private function handleStaff(IRIS $iris, SymfonyStyle $io, InputInterface $input, string $action, ?string $id, int $bloqId, bool $jsonOutput): int
    {
        switch ($action) {
            case 'list':
            case 'ls':
                $result = $iris->atlasOs->staff('list', ['_bloq_id' => $bloqId]);
                return $this->outputList($io, $result, $jsonOutput, ['staff_id', 'name', 'role', 'department', 'hourly_rate', 'email'], 'Staff');

            case 'create':
            case 'add':
                $name = $input->getOption('name') ?? $io->ask('Staff name');
                if (!$name) {
                    $io->error('--name is required');
                    return Command::FAILURE;
                }
                $params = ['_bloq_id' => $bloqId, 'name' => $name];
                $this->addOptional($params, $input, ['role', 'email', 'phone', 'department', 'hourly-rate' => 'hourly_rate', 'notes']);
                $result = $iris->atlasOs->staff('create', $params);
                return $this->outputSuccess($io, $result, $jsonOutput, "Staff member '{$name}' created");

            case 'show':
            case 'read':
            case 'get':
                $staffId = $id ?? $input->getOption('staff-id') ?? $io->ask('Staff ID');
                $result = $iris->atlasOs->staff('read', ['_bloq_id' => $bloqId, 'staff_id' => (int) $staffId]);
                return $this->outputDetail($io, $result, $jsonOutput);

            case 'update':
                $staffId = $id ?? $input->getOption('staff-id') ?? $io->ask('Staff ID');
                $params = ['_bloq_id' => $bloqId, 'staff_id' => (int) $staffId];
                $this->addOptional($params, $input, ['name', 'role', 'email', 'phone', 'department', 'hourly-rate' => 'hourly_rate', 'notes']);
                $result = $iris->atlasOs->staff('update', $params);
                return $this->outputSuccess($io, $result, $jsonOutput, "Staff #{$staffId} updated");

            case 'delete':
            case 'rm':
                $staffId = $id ?? $input->getOption('staff-id') ?? $io->ask('Staff ID');
                if (!$this->confirmDelete($io, $input, "staff member #{$staffId}")) {
                    return Command::SUCCESS;
                }
                $result = $iris->atlasOs->staff('delete', ['_bloq_id' => $bloqId, 'staff_id' => (int) $staffId]);
                return $this->outputSuccess($io, $result, $jsonOutput, "Staff #{$staffId} deleted");

            case 'search':
            case 'find':
                $query = $input->getOption('query') ?? $io->ask('Search query');
                $result = $iris->atlasOs->staff('search', ['_bloq_id' => $bloqId, 'query' => $query]);
                return $this->outputList($io, $result, $jsonOutput, ['staff_id', 'name', 'role', 'department'], "Staff search: {$query}");

            case 'assign':
                $staffName = $input->getOption('name') ?? $io->ask('Staff name');
                $role = $input->getOption('role') ?? $io->ask('Role at event');
                $eventId = $input->getOption('event-id') ?? $io->ask('Event ID');
                if (!$staffName || !$role || !$eventId) {
                    $io->error('--name, --role, and --event-id are required');
                    return Command::FAILURE;
                }
                $params = ['_bloq_id' => $bloqId, 'name' => $staffName, 'role' => $role, 'event_id' => (int) $eventId];
                $this->addOptional($params, $input, ['email', 'phone', 'hourly-rate' => 'hourly_rate', 'deliverables', 'notes']);
                $result = $iris->atlasOs->staff('assign_to_event', $params);
                return $this->outputSuccess($io, $result, $jsonOutput, "Assigned '{$staffName}' to event #{$eventId}");

            case 'create-task':
                $staffId = $input->getOption('staff-id');
                $itemId = $input->getOption('item-id');
                $taskTitle = $input->getOption('title') ?? $io->ask('Task title');
                if (!$staffId && !$itemId) {
                    $staffId = $io->ask('Staff ID (or use --item-id for direct BloqItem link)');
                }
                if ((!$staffId && !$itemId) || !$taskTitle) {
                    $io->error('--staff-id (or --item-id) and --title are required');
                    return Command::FAILURE;
                }
                $params = ['_bloq_id' => $bloqId, 'task_title' => $taskTitle];
                if ($itemId) {
                    $params['item_id'] = (int) $itemId;
                }
                if ($staffId) {
                    $params['staff_id'] = (int) $staffId;
                }
                if ($desc = $input->getOption('description')) {
                    $params['task_description'] = $desc;
                }
                if ($due = $input->getOption('due')) {
                    $params['task_due_date'] = $due;
                }
                $result = $iris->atlasOs->staff('create_task', $params);
                return $this->outputSuccess($io, $result, $jsonOutput, "Task '{$taskTitle}' created for staff #{$staffId}");

            case 'tasks':
                $staffId = $id ?? $input->getOption('staff-id');
                $itemId = $input->getOption('item-id');
                $eventId = $input->getOption('event-id');
                $params = ['_bloq_id' => $bloqId];
                if ($itemId) {
                    $params['item_id'] = (int) $itemId;
                }
                if ($staffId) {
                    $params['staff_id'] = (int) $staffId;
                }
                if ($eventId) {
                    $params['event_id'] = (int) $eventId;
                }
                $result = $iris->atlasOs->staff('list_tasks', $params);
                return $this->outputList($io, $result, $jsonOutput, ['task_id', 'title', 'due_date', 'status'], 'Tasks');

            case 'complete-task':
                $taskId = $id ?? $input->getOption('task-id') ?? $io->ask('Task ID');
                $result = $iris->atlasOs->staff('complete_task', ['_bloq_id' => $bloqId, 'task_id' => (int) $taskId]);
                return $this->outputSuccess($io, $result, $jsonOutput, "Task #{$taskId} completed");

            case 'submit-deliverable':
            case 'submit':
                $taskId = $input->getOption('task-id') ?? $id ?? $io->ask('Task ID to submit');
                if (!$taskId) {
                    $io->error('--task-id is required');
                    return Command::FAILURE;
                }
                $result = $iris->atlasOs->staff('submit_deliverable', ['_bloq_id' => $bloqId, 'task_id' => (int) $taskId]);
                return $this->outputSuccess($io, $result, $jsonOutput, "Deliverable submitted for task #{$taskId}");

            case 'review-deliverable':
            case 'review':
                $taskId = $input->getOption('task-id') ?? $id ?? $io->ask('Task ID to review');
                $verdict = $input->getOption('verdict') ?? $io->ask('Verdict (approved/revision_needed)');
                if (!$taskId || !$verdict) {
                    $io->error('--task-id and --verdict are required');
                    return Command::FAILURE;
                }
                $params = ['_bloq_id' => $bloqId, 'task_id' => (int) $taskId, 'verdict' => $verdict];
                if ($input->getOption('reviewer-notes')) {
                    $params['reviewer_notes'] = $input->getOption('reviewer-notes');
                }
                $result = $iris->atlasOs->staff('review_deliverable', $params);
                return $this->outputSuccess($io, $result, $jsonOutput, "Deliverable for task #{$taskId}: {$verdict}");

            default:
                $io->error("Unknown staff action: {$action}");
                $io->text('Available: list, create, show, update, delete, search, assign, create-task, tasks, complete-task, submit-deliverable, review-deliverable');
                return Command::FAILURE;
        }
    }

    // ========================================================================
    // EVENTS
    // ========================================================================

    private function handleEvents(IRIS $iris, SymfonyStyle $io, InputInterface $input, string $action, ?string $id, int $bloqId, bool $jsonOutput): int
    {
        switch ($action) {
            case 'list':
            case 'ls':
                $result = $iris->atlasOs->events('list', ['_bloq_id' => $bloqId]);
                return $this->outputList($io, $result, $jsonOutput, ['event_id', 'title', 'event_type', 'start_date', 'venue_name', 'status'], 'Events');

            case 'create':
            case 'add':
                $name = $input->getOption('name') ?? $io->ask('Event name');
                if (!$name) {
                    $io->error('--name is required');
                    return Command::FAILURE;
                }
                $params = ['_bloq_id' => $bloqId, 'name' => $name];
                $this->addOptional($params, $input, [
                    'type' => 'event_type', 'date', 'time', 'location',
                    'guest-count' => 'guest_count', 'budget-amount' => 'budget',
                    'description', 'status', 'notes',
                ]);
                $result = $iris->atlasOs->events('create', $params);
                return $this->outputSuccess($io, $result, $jsonOutput, "Event '{$name}' created");

            case 'show':
            case 'read':
            case 'get':
                $eventId = $id ?? $input->getOption('event-id') ?? $io->ask('Event ID');
                $result = $iris->atlasOs->events('read', ['_bloq_id' => $bloqId, 'event_id' => (int) $eventId]);
                return $this->outputDetail($io, $result, $jsonOutput);

            case 'update':
                $eventId = $id ?? $input->getOption('event-id') ?? $io->ask('Event ID');
                $params = ['_bloq_id' => $bloqId, 'event_id' => (int) $eventId];
                $this->addOptional($params, $input, [
                    'name', 'type' => 'event_type', 'date', 'time', 'location',
                    'guest-count' => 'guest_count', 'budget-amount' => 'budget',
                    'description', 'status', 'notes',
                ]);
                $result = $iris->atlasOs->events('update', $params);
                return $this->outputSuccess($io, $result, $jsonOutput, "Event #{$eventId} updated");

            case 'delete':
            case 'rm':
                $eventId = $id ?? $input->getOption('event-id') ?? $io->ask('Event ID');
                if (!$this->confirmDelete($io, $input, "event #{$eventId}")) {
                    return Command::SUCCESS;
                }
                $result = $iris->atlasOs->events('delete', ['_bloq_id' => $bloqId, 'event_id' => (int) $eventId]);
                return $this->outputSuccess($io, $result, $jsonOutput, "Event #{$eventId} deleted");

            case 'search':
            case 'find':
                $query = $input->getOption('query') ?? $io->ask('Search query');
                $result = $iris->atlasOs->events('search', ['_bloq_id' => $bloqId, 'query' => $query]);
                return $this->outputList($io, $result, $jsonOutput, ['event_id', 'title', 'event_type', 'start_date'], "Event search: {$query}");

            case 'add-vendor':
                $eventId = $input->getOption('event-id') ?? $io->ask('Event ID');
                $vendorName = $input->getOption('vendor-name') ?? $io->ask('Vendor name');
                if (!$eventId || !$vendorName) {
                    $io->error('--event-id and --vendor-name are required');
                    return Command::FAILURE;
                }
                $params = ['_bloq_id' => $bloqId, 'event_id' => (int) $eventId, 'vendor_name' => $vendorName];
                $this->addOptional($params, $input, ['vendor-group' => 'vendor_group', 'vendor-contact' => 'vendor_contact', 'vendor-price' => 'vendor_price']);
                $result = $iris->atlasOs->events('add_vendor', $params);
                return $this->outputSuccess($io, $result, $jsonOutput, "Vendor '{$vendorName}' added to event #{$eventId}");

            case 'remove-vendor':
                $vendorId = $id ?? $input->getOption('vendor-id') ?? $io->ask('Vendor ID');
                $result = $iris->atlasOs->events('remove_vendor', ['_bloq_id' => $bloqId, 'vendor_id' => (int) $vendorId]);
                return $this->outputSuccess($io, $result, $jsonOutput, "Vendor #{$vendorId} removed");

            case 'vendors':
                $eventId = $id ?? $input->getOption('event-id') ?? $io->ask('Event ID');
                $result = $iris->atlasOs->events('list_vendors', ['_bloq_id' => $bloqId, 'event_id' => (int) $eventId]);
                return $this->outputResult($io, $result, $jsonOutput, "Vendors for event #{$eventId}");

            case 'add-ticket':
                $eventId = $input->getOption('event-id') ?? $io->ask('Event ID');
                $ticketName = $input->getOption('ticket-name') ?? $io->ask('Ticket name');
                if (!$eventId || !$ticketName) {
                    $io->error('--event-id and --ticket-name are required');
                    return Command::FAILURE;
                }
                $params = ['_bloq_id' => $bloqId, 'event_id' => (int) $eventId, 'ticket_name' => $ticketName];
                $this->addOptional($params, $input, ['ticket-price' => 'ticket_price', 'ticket-quantity' => 'ticket_quantity']);
                $result = $iris->atlasOs->events('add_ticket', $params);
                return $this->outputSuccess($io, $result, $jsonOutput, "Ticket '{$ticketName}' added to event #{$eventId}");

            case 'remove-ticket':
                $ticketId = $id ?? $input->getOption('ticket-id') ?? $io->ask('Ticket ID');
                $result = $iris->atlasOs->events('remove_ticket', ['_bloq_id' => $bloqId, 'ticket_id' => (int) $ticketId]);
                return $this->outputSuccess($io, $result, $jsonOutput, "Ticket #{$ticketId} removed");

            case 'tickets':
                $eventId = $id ?? $input->getOption('event-id') ?? $io->ask('Event ID');
                $result = $iris->atlasOs->events('list_tickets', ['_bloq_id' => $bloqId, 'event_id' => (int) $eventId]);
                return $this->outputResult($io, $result, $jsonOutput, "Tickets for event #{$eventId}");

            case 'rsvp':
                $eventId = $input->getOption('event-id') ?? $io->ask('Event ID');
                $rsvpStatus = $input->getOption('rsvp-status') ?? $io->ask('Status (attending/maybe/not attending)', 'attending');
                $result = $iris->atlasOs->events('rsvp', ['_bloq_id' => $bloqId, 'event_id' => (int) $eventId, 'rsvp_status' => $rsvpStatus]);
                return $this->outputSuccess($io, $result, $jsonOutput, "RSVP '{$rsvpStatus}' for event #{$eventId}");

            case 'rsvps':
                $eventId = $id ?? $input->getOption('event-id') ?? $io->ask('Event ID');
                $result = $iris->atlasOs->events('list_rsvps', ['_bloq_id' => $bloqId, 'event_id' => (int) $eventId]);
                return $this->outputResult($io, $result, $jsonOutput, "RSVPs for event #{$eventId}");

            default:
                $io->error("Unknown events action: {$action}");
                $io->text('Available: list, create, show, update, delete, search, add-vendor, remove-vendor, vendors, add-ticket, remove-ticket, tickets, rsvp, rsvps');
                return Command::FAILURE;
        }
    }

    // ========================================================================
    // CALENDAR
    // ========================================================================

    private function handleCalendar(IRIS $iris, SymfonyStyle $io, InputInterface $input, string $action, ?string $id, int $bloqId, bool $jsonOutput): int
    {
        switch ($action) {
            case 'schedule':
            case 'create':
            case 'add':
                $content = $input->getOption('content') ?? $io->ask('Post content');
                if (!$content) {
                    $io->error('--content is required');
                    return Command::FAILURE;
                }
                $params = ['_bloq_id' => $bloqId, 'content' => $content];
                if ($platforms = $input->getOption('platforms')) {
                    $params['platforms'] = $platforms;
                }
                if ($scheduledAt = $input->getOption('scheduled-at')) {
                    $params['scheduled_at'] = $scheduledAt;
                }
                if ($mediaUrls = $input->getOption('media-urls')) {
                    $params['media_urls'] = $mediaUrls;
                }
                if ($eventId = $input->getOption('event-id')) {
                    $params['event_id'] = (int) $eventId;
                }
                $result = $iris->atlasOs->calendar('schedule_post', $params);
                return $this->outputSuccess($io, $result, $jsonOutput, 'Post scheduled');

            case 'list':
            case 'ls':
                $params = ['_bloq_id' => $bloqId];
                if ($period = $input->getOption('period')) {
                    $params['period'] = $period;
                }
                if ($status = $input->getOption('status')) {
                    $params['status'] = $status;
                }
                $result = $iris->atlasOs->calendar('list_posts', $params);
                return $this->outputList($io, $result, $jsonOutput, ['post_id', 'content', 'platforms', 'scheduled_at', 'status'], 'Content Calendar');

            case 'update':
                $postId = $id ?? $input->getOption('post-id') ?? $io->ask('Post ID');
                $params = ['_bloq_id' => $bloqId, 'post_id' => (int) $postId];
                if ($content = $input->getOption('content')) {
                    $params['content'] = $content;
                }
                if ($platforms = $input->getOption('platforms')) {
                    $params['platforms'] = $platforms;
                }
                if ($scheduledAt = $input->getOption('scheduled-at')) {
                    $params['scheduled_at'] = $scheduledAt;
                }
                $result = $iris->atlasOs->calendar('update_post', $params);
                return $this->outputSuccess($io, $result, $jsonOutput, "Post #{$postId} updated");

            case 'publish':
                $postId = $id ?? $input->getOption('post-id') ?? $io->ask('Post ID');
                $result = $iris->atlasOs->calendar('publish_now', ['_bloq_id' => $bloqId, 'post_id' => (int) $postId]);
                return $this->outputSuccess($io, $result, $jsonOutput, "Post #{$postId} publishing now");

            case 'delete':
            case 'rm':
                $postId = $id ?? $input->getOption('post-id') ?? $io->ask('Post ID');
                if (!$this->confirmDelete($io, $input, "post #{$postId}")) {
                    return Command::SUCCESS;
                }
                $result = $iris->atlasOs->calendar('delete_post', ['_bloq_id' => $bloqId, 'post_id' => (int) $postId]);
                return $this->outputSuccess($io, $result, $jsonOutput, "Post #{$postId} deleted");

            default:
                $io->error("Unknown calendar action: {$action}");
                $io->text('Available: schedule, list, update, publish, delete');
                return Command::FAILURE;
        }
    }

    // ========================================================================
    // RESEARCH
    // ========================================================================

    private function handleResearch(IRIS $iris, SymfonyStyle $io, InputInterface $input, string $action, ?string $id, int $bloqId, bool $jsonOutput): int
    {
        switch ($action) {
            case 'query':
            case 'research':
                $query = $input->getOption('query') ?? $io->ask('Research query');
                if (!$query) {
                    $io->error('--query is required');
                    return Command::FAILURE;
                }
                $params = ['_bloq_id' => $bloqId, 'query' => $query];
                if ($competitor = $input->getOption('competitor')) {
                    $params['competitor'] = $competitor;
                }
                if ($category = $input->getOption('category')) {
                    $params['category'] = $category;
                }
                $result = $iris->atlasOs->research('research', $params);
                return $this->outputResult($io, $result, $jsonOutput, "Research: {$query}");

            case 'list':
            case 'ls':
                $result = $iris->atlasOs->research('list', ['_bloq_id' => $bloqId]);
                return $this->outputList($io, $result, $jsonOutput, ['item_id', 'title', 'content.category'], 'Research');

            case 'search':
            case 'find':
                $query = $input->getOption('query') ?? $io->ask('Search query');
                $result = $iris->atlasOs->research('search_stored', ['_bloq_id' => $bloqId, 'query' => $query]);
                return $this->outputList($io, $result, $jsonOutput, ['item_id', 'title', 'content.category'], "Research search: {$query}");

            default:
                $io->error("Unknown research action: {$action}");
                $io->text('Available: query, list, search');
                return Command::FAILURE;
        }
    }

    // ========================================================================
    // DOCUMENTS
    // ========================================================================

    private function handleDocuments(IRIS $iris, SymfonyStyle $io, InputInterface $input, string $action, ?string $id, int $bloqId, bool $jsonOutput): int
    {
        switch ($action) {
            case 'store':
            case 'create':
            case 'add':
                $title = $input->getOption('title') ?? $io->ask('Document title');
                $content = $input->getOption('content') ?? $io->ask('Document content');
                if (!$title || !$content) {
                    $io->error('--title and --content are required');
                    return Command::FAILURE;
                }
                $params = ['_bloq_id' => $bloqId, 'title' => $title, 'content' => $content];
                if ($docType = $input->getOption('doc-type')) {
                    $params['doc_type'] = $docType;
                }
                if ($tags = $input->getOption('tags')) {
                    $params['tags'] = $tags;
                }
                $result = $iris->atlasOs->documents('store', $params);
                return $this->outputSuccess($io, $result, $jsonOutput, "Document '{$title}' stored");

            case 'list':
            case 'ls':
                $result = $iris->atlasOs->documents('list', ['_bloq_id' => $bloqId]);
                return $this->outputList($io, $result, $jsonOutput, ['item_id', 'title', 'content.doc_type'], 'Documents');

            case 'search':
            case 'find':
                $query = $input->getOption('query') ?? $io->ask('Search query');
                $result = $iris->atlasOs->documents('search', ['_bloq_id' => $bloqId, 'query' => $query]);
                return $this->outputList($io, $result, $jsonOutput, ['item_id', 'title', 'content.doc_type'], "Document search: {$query}");

            case 'update':
                $itemId = $id ?? $io->ask('Document ID');
                $params = ['_bloq_id' => $bloqId, 'item_id' => (int) $itemId];
                if ($title = $input->getOption('title')) {
                    $params['title'] = $title;
                }
                if ($content = $input->getOption('content')) {
                    $params['content'] = $content;
                }
                if ($docType = $input->getOption('doc-type')) {
                    $params['doc_type'] = $docType;
                }
                if ($tags = $input->getOption('tags')) {
                    $params['tags'] = $tags;
                }
                $result = $iris->atlasOs->documents('update', $params);
                return $this->outputSuccess($io, $result, $jsonOutput, "Document #{$itemId} updated");

            case 'delete':
            case 'rm':
                $itemId = $id ?? $io->ask('Document ID');
                if (!$this->confirmDelete($io, $input, "document #{$itemId}")) {
                    return Command::SUCCESS;
                }
                $result = $iris->atlasOs->documents('delete', ['_bloq_id' => $bloqId, 'item_id' => (int) $itemId]);
                return $this->outputSuccess($io, $result, $jsonOutput, "Document #{$itemId} deleted");

            default:
                $io->error("Unknown docs action: {$action}");
                $io->text('Available: store, list, search, update, delete');
                return Command::FAILURE;
        }
    }

    // ========================================================================
    // OUTPUT HELPERS
    // ========================================================================

    // ========================================================================
    // CONTRACTS
    // ========================================================================

    private function handleContracts(IRIS $iris, SymfonyStyle $io, InputInterface $input, string $action, ?string $id, int $bloqId, bool $jsonOutput): int
    {
        switch ($action) {
            case 'list':
            case 'ls':
                $result = $iris->atlasOs->contracts('list_contracts', ['_bloq_id' => $bloqId]);
                $data = $result['data'] ?? $result;
                if ($jsonOutput) {
                    $io->writeln(json_encode($data, JSON_PRETTY_PRINT));
                    return Command::SUCCESS;
                }
                $contracts = $data['contracts'] ?? [];
                $io->title('Contracts (' . count($contracts) . ')');
                if (empty($contracts)) {
                    $io->info('No contracts found.');
                    return Command::SUCCESS;
                }
                $rows = [];
                foreach ($contracts as $c) {
                    $rows[] = [
                        $c['staff_id'] ?? '-',
                        $c['name'] ?? '-',
                        $c['role'] ?? '-',
                        $c['contract_type'] ?? '-',
                        $c['contract_status'] ?? '-',
                        isset($c['contract_value']) ? '$' . number_format($c['contract_value']) : '-',
                        ($c['start_date'] ?? '-') . ' → ' . ($c['end_date'] ?? '-'),
                        ($c['has_compliance'] ?? false) ? 'Yes' : 'No',
                    ];
                }
                $io->table(['Staff ID', 'Name', 'Role', 'Type', 'Status', 'Value', 'Period', 'Compliant'], $rows);
                return Command::SUCCESS;

            case 'create':
            case 'add':
                $staffId = $id ?? $input->getOption('staff-id') ?? $io->ask('Staff member ID');
                if (!$staffId) {
                    $io->error('Staff ID is required. Use --staff-id=N or pass as argument.');
                    return Command::FAILURE;
                }
                $params = ['_bloq_id' => $bloqId, 'staff_id' => (int) $staffId];
                $this->addOptional($params, $input, [
                    'contract-type' => 'contract_type',
                    'start-date' => 'start_date',
                    'end-date' => 'end_date',
                    'total-value' => 'total_value',
                    'scope-of-work' => 'scope_of_work',
                    'payment-terms' => 'payment_terms',
                    'payment-schedule' => 'payment_schedule',
                    'event-id' => 'event_id',
                    'opportunity-id' => 'opportunity_id',
                ]);
                if ($input->getOption('nda-signed')) {
                    $params['nda_signed'] = true;
                }
                if ($input->getOption('w9-on-file')) {
                    $params['w9_on_file'] = true;
                }
                $result = $iris->atlasOs->contracts('create_agreement', $params);
                return $this->outputSuccess($io, $result, $jsonOutput, "Contract created for staff #{$staffId}");

            case 'show':
            case 'get':
                $staffId = $id ?? $input->getOption('staff-id') ?? $io->ask('Staff member ID');
                $result = $iris->atlasOs->contracts('get_contract', ['_bloq_id' => $bloqId, 'staff_id' => (int) $staffId]);
                return $this->outputDetail($io, $result, $jsonOutput);

            case 'update-status':
                $staffId = $id ?? $input->getOption('staff-id') ?? $io->ask('Staff member ID');
                $status = $input->getOption('status') ?? $io->ask('Status (draft, pending_signature, active, completed, terminated)');
                if (!$staffId || !$status) {
                    $io->error('--staff-id and --status are required');
                    return Command::FAILURE;
                }
                $result = $iris->atlasOs->contracts('update_status', ['_bloq_id' => $bloqId, 'staff_id' => (int) $staffId, 'status' => $status]);
                return $this->outputSuccess($io, $result, $jsonOutput, "Contract for staff #{$staffId} updated to {$status}");

            case 'compliance':
            case 'set-compliance':
                $staffId = $id ?? $input->getOption('staff-id') ?? $io->ask('Staff member ID');
                if (!$staffId) {
                    $io->error('--staff-id is required');
                    return Command::FAILURE;
                }
                $params = ['_bloq_id' => $bloqId, 'staff_id' => (int) $staffId];
                if ($input->getOption('insurance-verified')) {
                    $params['insurance_verified'] = true;
                }
                $this->addOptional($params, $input, [
                    'insurance-expiry' => 'insurance_expiry',
                    'certifications' => 'certifications',
                    'background-check' => 'background_check',
                ]);
                if ($input->getOption('nda-signed')) {
                    $params['nda_signed'] = true;
                }
                if ($input->getOption('w9-on-file')) {
                    $params['w9_on_file'] = true;
                }
                $result = $iris->atlasOs->contracts('set_compliance', $params);
                return $this->outputSuccess($io, $result, $jsonOutput, "Compliance updated for staff #{$staffId}");

            case 'check':
            case 'check-compliance':
                $result = $iris->atlasOs->contracts('check_compliance', ['_bloq_id' => $bloqId]);
                return $this->outputResult($io, $result, $jsonOutput, 'Compliance Check');

            case 'vendor-terms':
                $supplier = $input->getOption('supplier') ?? $io->ask('Supplier name');
                if (!$supplier) {
                    $io->error('--supplier is required');
                    return Command::FAILURE;
                }
                $params = ['_bloq_id' => $bloqId, 'supplier' => $supplier];
                $this->addOptional($params, $input, [
                    'payment-terms' => 'payment_terms',
                    'total-value' => 'total_value',
                ]);
                $result = $iris->atlasOs->contracts('create_vendor_terms', $params);
                return $this->outputSuccess($io, $result, $jsonOutput, "Vendor terms created for '{$supplier}'");

            case 'committed-budget':
            case 'budget':
                $result = $iris->atlasOs->contracts('get_committed_budget', ['_bloq_id' => $bloqId]);
                return $this->outputResult($io, $result, $jsonOutput, 'Committed Budget');

            case 'send':
            case 'generate-url':
                $staffId = $id ?? $input->getOption('staff-id') ?? $io->ask('Staff member ID');
                if (!$staffId) {
                    $io->error('Staff ID is required.');
                    return Command::FAILURE;
                }
                $result = $iris->atlasOs->contracts('generate_signing_url', [
                    '_bloq_id' => $bloqId,
                    'staff_id' => (int) $staffId,
                ]);
                $data = $result['data'] ?? $result;
                if (isset($data['error'])) {
                    $io->error($data['error']);
                    return Command::FAILURE;
                }
                if ($jsonOutput) {
                    $io->writeln(json_encode($data, JSON_PRETTY_PRINT));
                    return Command::SUCCESS;
                }
                $contractorName = $data['contractor_name'] ?? 'staff #' . $staffId;
                $signingUrl = $data['signing_url'] ?? 'N/A';
                $io->success("Signing URL generated for '{$contractorName}'");
                $io->newLine();
                $io->text($signingUrl);
                $io->newLine();
                $io->note('Share this URL with the contractor. No login required. Status: pending_signature');
                return Command::SUCCESS;

            default:
                $io->error("Unknown contracts action: {$action}");
                $io->text('Available: list, create, show, update-status, compliance, check, vendor-terms, committed-budget, send');
                return Command::FAILURE;
        }
    }

    // ========================================================================
    // HELPERS
    // ========================================================================

    /**
     * Add optional CLI options to params array, mapping option names to param keys.
     */
    private function addOptional(array &$params, InputInterface $input, array $options): void
    {
        foreach ($options as $key => $value) {
            // If key is numeric, option name = param name
            $optionName = is_string($key) ? $key : $value;
            $paramName = is_string($key) ? $value : $value;

            $optionValue = $input->getOption($optionName);
            if ($optionValue !== null) {
                $params[$paramName] = $optionValue;
            }
        }
    }

    /**
     * Output a list result as table or JSON.
     */
    private function outputList(SymfonyStyle $io, array $result, bool $jsonOutput, array $columns, string $title): int
    {
        if ($jsonOutput) {
            $io->writeln(json_encode($result, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $data = $result['data'] ?? $result;
        $items = $data['items'] ?? $data['events'] ?? $data['posts'] ?? $data['results'] ?? $data['tasks'] ?? [];

        $io->title($title);

        if (empty($items)) {
            $io->info('No items found.');
            $count = $data['count'] ?? 0;
            if ($count > 0) {
                $io->text("({$count} total)");
            }
            return Command::SUCCESS;
        }

        // Build table headers from column keys
        $headers = array_map(function ($col) {
            $parts = explode('.', $col);
            return ucwords(str_replace('_', ' ', end($parts)));
        }, $columns);

        $rows = [];
        foreach ($items as $item) {
            $row = [];
            foreach ($columns as $col) {
                $row[] = $this->extractValue($item, $col);
            }
            $rows[] = $row;
        }

        $io->table($headers, $rows);
        $count = $data['count'] ?? count($items);
        $io->text("{$count} item(s)");

        return Command::SUCCESS;
    }

    /**
     * Output a detail/show result.
     */
    private function outputDetail(SymfonyStyle $io, array $result, bool $jsonOutput): int
    {
        if ($jsonOutput) {
            $io->writeln(json_encode($result, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $data = $result['data'] ?? $result;
        $io->title($data['name'] ?? $data['title'] ?? 'Detail');

        $definitions = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_SLASHES);
            }
            $label = ucwords(str_replace('_', ' ', $key));
            $definitions[] = [$label => (string) ($value ?? '-')];
        }

        $io->definitionList(...$definitions);

        return Command::SUCCESS;
    }

    /**
     * Output a success message with optional data.
     */
    private function outputSuccess(SymfonyStyle $io, array $result, bool $jsonOutput, string $message): int
    {
        if ($jsonOutput) {
            $io->writeln(json_encode($result, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $data = $result['data'] ?? $result;
        $io->success($result['message'] ?? $message);

        // Show key data points
        $interesting = ['item_id', 'event_id', 'staff_id', 'post_id', 'task_id', 'vendor_id', 'ticket_id', 'rsvp_id', 'transaction_id'];
        $shown = [];
        foreach ($interesting as $key) {
            if (isset($data[$key])) {
                $shown[] = [$key => $data[$key]];
            }
        }
        if (!empty($shown)) {
            $io->definitionList(...$shown);
        }

        return Command::SUCCESS;
    }

    /**
     * Output a generic result (vendors, tickets, rsvps, research).
     */
    private function outputResult(SymfonyStyle $io, array $result, bool $jsonOutput, string $title): int
    {
        if ($jsonOutput) {
            $io->writeln(json_encode($result, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $data = $result['data'] ?? $result;
        $io->title($title);
        $io->writeln(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return Command::SUCCESS;
    }

    /**
     * Extract a possibly nested value from an item using dot notation.
     */
    private function extractValue(array $item, string $path): string
    {
        $parts = explode('.', $path);
        $value = $item;
        foreach ($parts as $part) {
            if (is_array($value) && isset($value[$part])) {
                $value = $value[$part];
            } else {
                return '-';
            }
        }

        if (is_array($value)) {
            return json_encode($value);
        }

        return (string) ($value ?? '-');
    }

    /**
     * Confirm a delete operation.
     */
    private function confirmDelete(SymfonyStyle $io, InputInterface $input, string $what): bool
    {
        if ($input->getOption('no-interaction')) {
            return true;
        }

        return $io->confirm("Delete {$what}? This cannot be undone.", false);
    }
}
