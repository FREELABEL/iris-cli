<?php

namespace IRIS\SDK\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Style\SymfonyStyle;
use IRIS\SDK\IRIS;
use IRIS\SDK\Config;

class SDKCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('sdk:call')
            ->setDescription('Dynamic proxy to SDK resources and methods')
            ->setHelp(<<<'EOT'
Usage: iris sdk:call <resource>.<method> [args] [--options]

Examples:
  iris sdk:call leads.list search=Dima
  iris sdk:call leads.search query=Martinez
  iris sdk:call agents.chat id=123 message="Hello"
  iris sdk:call bloqs.get 42

Sub-Resource Shortcuts (all require lead_id):
  leads.addTask / leads.createTask    → leads.tasks.create
  leads.listTasks / leads.getTasks    → leads.tasks.all
  leads.createNote / leads.listNotes  → leads.notes.create / .all
  leads.createInvoice / .sendInvoice  → leads.invoices.create / .send
  leads.generateEmail / .sendEmail    → leads.outreach.generateEmail / .sendEmail
  leads.listDeliverables              → leads.deliverables.list
  leads.createOutreachStep            → leads.outreachSteps.create

Top-Level Aliases (shorthand, require lead_id):
  tasks.create lead_id=525 title="Do thing"
  tasks.list lead_id=525
  notes.create lead_id=525 content="Note text"
  invoices.create lead_id=525 price=25000

Bloq Sub-Resource Shortcuts:
  bloqs.listLists / bloqs.getLists    → bloqs.lists.all
  bloqs.createList                    → bloqs.lists.create
  bloqs.listItems / bloqs.getItems   → bloqs.items.list
  bloqs.createItem / bloqs.addItem   → bloqs.items.create
  bloqs.addText / bloqs.addMarkdown  → bloqs.items.addText / .addMarkdown

Top-Level Bloq Aliases (shorthand):
  bloqLists.list bloqId=256           → bloqs.lists.all
  bloqLists.create bloqId=256 title="My List"
  bloqItems.list listId=123           → bloqs.items.list
  bloqItems.create listId=123 title="Item" content="..."

Parameter Aliases:
  query => search (for leads.list, leads.search)
EOT
            )
            ->addArgument('endpoint', InputArgument::OPTIONAL, 'Resource.method (e.g., leads.list, agents.chat)')
            ->addArgument('params', InputArgument::IS_ARRAY, 'Parameters as key=value pairs')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON')
            ->addOption('raw', null, InputOption::VALUE_NONE, 'Raw output (no formatting)')
            ->addOption('list', null, InputOption::VALUE_NONE, 'List all available resources and methods')
            ->addOption('api-key', null, InputOption::VALUE_REQUIRED, 'API key')
            ->addOption('user-id', null, InputOption::VALUE_REQUIRED, 'User ID');
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Handle --list flag
        if ($input->getOption('list') || !$input->getArgument('endpoint')) {
            $this->listResources($io, $input);
            return Command::SUCCESS;
        }

        $endpoint = $input->getArgument('endpoint');

        try {
            // Build config options from CLI flags (override .env if provided)
            $configOptions = [];

            if ($apiKey = $input->getOption('api-key')) {
                $configOptions['api_key'] = $apiKey;
            }
            if ($userId = $input->getOption('user-id')) {
                $configOptions['user_id'] = (int)$userId;
            }

            // Config auto-loads from .env file, CLI options override
            $sdkConfig = new Config($configOptions);
            $userId = $sdkConfig->userId;

            $iris = new IRIS($configOptions);

            // Parse parameters first (needed for alias resolution)
            $params = $this->parseParams($input->getArgument('params'));

            // Resolve endpoint aliases BEFORE parsing parts
            // This maps shorthands like "leads.addTask", "tasks.create" to proper sub-resource paths
            [$endpoint, $params] = $this->resolveEndpointAlias($endpoint, $params);

            // Parse endpoint (resource.method or resource.subresource.method)
            $parts = explode('.', $endpoint);
            if (count($parts) < 2) {
                throw new \InvalidArgumentException("Invalid endpoint format. Use: resource.method (e.g., leads.list)");
            }

            // Apply parameter aliases (e.g., query => search)
            $params = $this->applyParameterAliases($endpoint, $params);

            // Auto-inject user_id if not provided (needed for many API calls)
            // BUT skip for global search endpoints that search across all users
            //
            // IMPORTANT: Search endpoints like leads.search and leads.aggregation.list
            // are designed to search across ALL leads in the system, not just leads
            // belonging to the current user. Auto-injecting user_id would incorrectly
            // filter results to only that user's leads, causing search misses.
            //
            // Example: Lead "Lisa Martinez" (ID: 67) has no user_id assignment.
            // - With user_id injection: search returns 0 results (filtered out)
            // - Without user_id injection: search correctly finds Lisa
            //
            // See tests: testUserIdNotInjectedForGlobalSearchEndpoints()
            $globalSearchEndpoints = [
                'leads.search',
                'leads.aggregation.list',
                'leads.aggregation.getRecentLeads',
                'leads.aggregation.statistics',
            ];

            if (!isset($params['user_id']) && $userId && !in_array($endpoint, $globalSearchEndpoints)) {
                $params['user_id'] = (int)$userId;
            }

            // Execute dynamic call
            $result = $this->executeDynamicCall($iris, $parts, $params);

            // Format output
            $this->renderOutput($result, $input, $output, $io);

            return Command::SUCCESS;

        } catch (\Throwable $e) {
            if ($input->getOption('json')) {
                $output->writeln(json_encode([
                    'error' => true,
                    'message' => $e->getMessage(),
                    'type' => get_class($e),
                ], JSON_PRETTY_PRINT));
            } else {
                $io->error($e->getMessage());
                if ($output->isVerbose()) {
                    $io->text($e->getTraceAsString());
                }
            }
            return Command::FAILURE;
        }
    }

    private function listResources(SymfonyStyle $io, InputInterface $input): void
    {
        $resources = [
            'agents' => [
                'methods' => ['list', 'get <id>', 'create name=... prompt=...', 'update <id>', 'delete <id>', 'chat <id> message=...', 'getUrl <id>'],
                'sub' => ['skills(<agentId>).list/create/delete', 'schedules(<agentId>).list/create'],
            ],
            'bloqs' => [
                'methods' => ['list', 'get <id>', 'create name=...', 'update <id>', 'delete <id>', 'count', 'share <bloqId> ...', 'getSharedUsers <bloqId>'],
                'sub' => ['lists(<bloqId>).all/create/get/update/delete', 'items(<listId>).list/create/get/update/delete/addText/addMarkdown/addSpreadsheet'],
            ],
            'leads' => [
                'methods' => ['list', 'get <id>', 'create', 'update <id>', 'delete <id>', 'search query=...', 'stats'],
                'sub' => ['notes(<leadId>).all/create/update/delete', 'tasks(<leadId>).all/create/update/delete', 'invoices(<leadId>).list/create/send', 'deliverables(<leadId>).list/create', 'outreach(<leadId>).generateEmail/sendEmail'],
            ],
            'chat' => [
                'methods' => ['execute agentId=... query=...'],
            ],
            'workflows' => [
                'methods' => ['list', 'get <id>', 'run <id> input=...', 'cancel <id>'],
            ],
            'pages' => [
                'methods' => ['list', 'get <slug>', 'create', 'update <slug>', 'publish <slug>', 'unpublish <slug>'],
            ],
            'memory' => [
                'methods' => ['list <agentId>', 'add <agentId> content=...', 'get <id>', 'delete <id>'],
            ],
            'diary' => [
                'methods' => ['today <agentId>', 'list <agentId>', 'add <agentId> content=...'],
            ],
            'integrations' => [
                'methods' => ['list', 'get <id>'],
            ],
            'users' => [
                'methods' => ['list', 'get <id>'],
            ],
            'models' => [
                'methods' => ['list'],
            ],
            'schedules' => [
                'methods' => ['list', 'create', 'update <id>', 'delete <id>'],
            ],
            'payments' => [
                'methods' => ['list', 'create'],
            ],
            'voice' => [
                'methods' => ['list', 'get <agentId>', 'update <agentId>'],
            ],
            'phone' => [
                'methods' => ['list', 'get <agentId>'],
            ],
            'calls' => [
                'methods' => ['initiate agentId=... phoneNumber=...'],
            ],
            'profiles' => [
                'methods' => ['get <id>', 'update <id>'],
            ],
            'videos' => [
                'methods' => ['list', 'get <id>'],
            ],
            'articles' => [
                'methods' => ['list', 'get <id>', 'generate'],
            ],
            'marketplace' => [
                'methods' => ['list', 'get <id>', 'publish', 'install'],
            ],
        ];

        if ($input->getOption('json')) {
            echo json_encode($resources, JSON_PRETTY_PRINT) . "\n";
            return;
        }

        $io->title('IRIS SDK — Available Resources');
        $io->text('Usage: <info>iris sdk:call resource.method [params]</info>');
        $io->newLine();

        foreach ($resources as $name => $info) {
            $io->section($name);
            foreach ($info['methods'] as $method) {
                $io->text("  <info>{$name}.{$method}</info>");
            }
            if (!empty($info['sub'])) {
                $io->text('');
                $io->text('  <comment>Sub-resources:</comment>');
                foreach ($info['sub'] as $sub) {
                    $io->text("    {$name}.{$sub}");
                }
            }
        }

        $io->newLine();
        $io->text('<comment>Shortcuts:</comment> leads.addTask, leads.createNote, bloqs.addItem, bloqs.addText');
        $io->text('<comment>Aliases:</comment>  tasks.create lead_id=X, notes.create lead_id=X, bloqItems.create listId=X');
        $io->text('<comment>Search:</comment>   leads.search query=... (searches across all leads)');
    }
    
    private function executeDynamicCall(IRIS $iris, array $parts, array $params)
    {
        $resource = array_shift($parts);
        $method = array_pop($parts);
        
        // Navigate to resource (handle nested resources like leads.aggregation or leads.deliverables)
        if (!property_exists($iris, $resource)) {
            $available = array_filter(
                array_keys(get_object_vars($iris)),
                fn($k) => !in_array($k, ['config', 'http']) && is_object($iris->{$k})
            );
            throw new \InvalidArgumentException(
                "Unknown resource '{$resource}'. Available: " . implode(', ', $available) .
                "\nRun: iris sdk:call --list"
            );
        }
        $target = $iris->{$resource};
        foreach ($parts as $sub) {
            if (method_exists($target, $sub)) {
                // Use reflection to check if this sub-resource method requires parameters
                $reflection = new \ReflectionMethod($target, $sub);
                $requiredParams = 0;
                foreach ($reflection->getParameters() as $param) {
                    if (!$param->isOptional()) {
                        $requiredParams++;
                    }
                }
                
                // Only extract positional params if the method requires them
                if ($requiredParams > 0) {
                    // Get the parameter names from reflection
                    $methodParams = $reflection->getParameters();
                    $subResourceParams = [];

                    // First, try to match named params to method parameter names
                    foreach ($methodParams as $methodParam) {
                        $paramName = $methodParam->getName();
                        // Check for exact match (e.g., 'leadId')
                        if (isset($params[$paramName])) {
                            $subResourceParams[] = $params[$paramName];
                            unset($params[$paramName]);
                        }
                        // Check for snake_case version (e.g., 'lead_id')
                        $snakeName = strtolower(preg_replace('/([A-Z])/', '_$1', $paramName));
                        $snakeName = ltrim($snakeName, '_');
                        if (isset($params[$snakeName])) {
                            $subResourceParams[] = $params[$snakeName];
                            unset($params[$snakeName]);
                        }
                    }

                    // Fall back to positional params (numeric keys) if no named matches
                    if (empty($subResourceParams)) {
                        foreach ($params as $key => $value) {
                            if (is_int($key) && count($subResourceParams) < $requiredParams) {
                                $subResourceParams[] = $value;
                                unset($params[$key]);
                            }
                        }
                    }

                    if (!empty($subResourceParams)) {
                        // Call sub-resource method with params
                        $target = $target->{$sub}(...$subResourceParams);
                    } else {
                        // Call without params
                        $target = $target->{$sub}();
                    }
                } else {
                    // Method doesn't require params, call it directly
                    $target = $target->{$sub}();
                }
            } else {
                $target = $target->{$sub};
            }
        }
        
        // Call method with params
        if (!method_exists($target, $method)) {
            throw new \BadMethodCallException("Method '{$method}' not found on resource");
        }
        
        // Special handling for agents.create - use createFromArray for CLI
        if ($method === 'create' && get_class($target) === 'IRIS\SDK\Resources\Agents\AgentsResource') {
            if (!empty($params)) {
                // Extract positional and named params
                $positionalParams = [];
                $namedParams = [];
                
                foreach ($params as $key => $value) {
                    if (is_int($key)) {
                        $positionalParams[] = $value;
                    } else {
                        $namedParams[$key] = $value;
                    }
                }
                
                // If we have named params, use createFromArray
                if (!empty($namedParams)) {
                    return $target->createFromArray($namedParams);
                }
            }
        }
        
        // For methods that expect a single array argument, pass params as-is
        // Otherwise spread the params as individual arguments
        if (empty($params)) {
            return $target->{$method}();
        }

        // Special handling for search methods: convert string argument to search filter
        // This allows: `leads.search "Tha Juan"` instead of `leads.search search="Tha Juan"`
        if ($method === 'search' && count($params) === 1 && isset($params[0]) && is_string($params[0])) {
            return $target->{$method}(['search' => $params[0]]);
        }
        
        // Separate positional and named parameters
        $positionalParams = [];
        $namedParams = [];
        
        foreach ($params as $key => $value) {
            if (is_int($key)) {
                $positionalParams[] = $value;
            } else {
                $namedParams[$key] = $value;
            }
        }
        
        // Use reflection to intelligently map parameters to method signature
        try {
            $reflection = new \ReflectionMethod($target, $method);
            $methodParams = $reflection->getParameters();
            $args = $positionalParams;

            // Start from the position after positional params
            $startIndex = count($positionalParams);

            // Check if method expects a single array parameter (like register(array $data))
            // If so, pass all named params as that array
            $singleArrayParamHandled = false;
            if (!empty($namedParams) && count($methodParams) === 1 + $startIndex) {
                $firstUnfilledParam = $methodParams[$startIndex] ?? null;
                if ($firstUnfilledParam) {
                    $paramType = $firstUnfilledParam->getType();
                    if ($paramType instanceof \ReflectionNamedType && $paramType->getName() === 'array') {
                        // Method expects single array param - pass all named params as that array
                        $args[] = $namedParams;
                        $namedParams = []; // Clear named params since we used them all
                        $singleArrayParamHandled = true;
                    }
                }
            }

            // Map named parameters to method signature (skip if we already handled single array param)
            if (!$singleArrayParamHandled) {
                for ($i = $startIndex; $i < count($methodParams); $i++) {
                $param = $methodParams[$i];
                $paramName = $param->getName();

                if (isset($namedParams[$paramName])) {
                    // Found matching named parameter (exact match)
                    $args[] = $namedParams[$paramName];
                    unset($namedParams[$paramName]);
                } else {
                    // Try to find a short name that maps to this param
                    $found = false;
                    foreach ($namedParams as $namedKey => $namedValue) {
                        $mappedName = $this->mapParamName($namedKey, [$param]);
                        if ($mappedName === $paramName) {
                            $args[] = $namedValue;
                            unset($namedParams[$namedKey]);
                            $found = true;
                            break;
                        }
                    }

                    if (!$found) {
                        if (!$param->isOptional()) {
                            // Required param not provided - throw clear error
                            $providedParams = array_keys($namedParams);
                            $providedParamsStr = empty($providedParams) ? 'none' : implode(', ', $providedParams);
                            throw new \InvalidArgumentException(
                                "Missing required parameter '{$paramName}' for method '{$method}'. " .
                                "Provided parameters: {$providedParamsStr}. " .
                                "Use the exact parameter name or check method signature."
                            );
                        } else {
                            // Optional param not provided - use default value
                            if ($param->isDefaultValueAvailable()) {
                                $args[] = $param->getDefaultValue();
                            }
                            break;
                        }
                    }
                }
                }
            }
            
            // If there are leftover named params and the last method param accepts an array, merge them
            if (!$singleArrayParamHandled && !empty($namedParams) && !empty($methodParams)) {
                $lastParam = $methodParams[count($methodParams) - 1];
                $lastParamType = $lastParam->getType();

                // If last param is array type, merge remaining named params into it
                // Note: ReflectionUnionType (int|string) doesn't have getName(), only ReflectionNamedType does
                if ($lastParamType instanceof \ReflectionNamedType && $lastParamType->getName() === 'array') {
                    $args[] = $namedParams;
                }
            }
            
            return $target->{$method}(...$args);
        } catch (\ReflectionException $e) {
            // Fallback: spread positional, then pass named as array if any
            if (!empty($namedParams)) {
                return $target->{$method}(...array_merge($positionalParams, [$namedParams]));
            } else {
                return $target->{$method}(...$positionalParams);
            }
        }
    }
    
    private function parseParams(array $params): array
    {
        $parsed = [];
        foreach ($params as $param) {
            if (strpos($param, '=') !== false) {
                [$key, $value] = explode('=', $param, 2);
                // Auto-detect type
                $parsed[$key] = $this->castValue($value);
            } else {
                $parsed[] = $this->castValue($param);
            }
        }
        return $parsed;
    }

    /**
     * Apply parameter aliases for common use cases.
     * This allows users to use intuitive names like 'query' that get mapped to 'search'.
     *
     * @param string $endpoint The endpoint being called (e.g., 'leads.list')
     * @param array $params The parsed parameters
     * @return array The parameters with aliases applied
     */
    private function applyParameterAliases(string $endpoint, array $params): array
    {
        // Define aliases for specific endpoints
        $aliasMap = [
            'leads.list' => ['query' => 'search'],
            'leads.search' => ['query' => 'search'],
            'leads.listForUser' => ['query' => 'search'],
        ];

        // If this endpoint has alias mappings
        if (isset($aliasMap[$endpoint])) {
            foreach ($aliasMap[$endpoint] as $alias => $canonical) {
                // If alias is used but canonical isn't, map it
                if (isset($params[$alias]) && !isset($params[$canonical])) {
                    $params[$canonical] = $params[$alias];
                    unset($params[$alias]);
                }
            }
        }

        return $params;
    }

    /**
     * Resolve endpoint aliases to their canonical sub-resource paths.
     *
     * Handles three patterns:
     * 1. Flat methods on leads: leads.addTask → leads.tasks.create
     * 2. Top-level pseudo-resources: tasks.create → leads.tasks.create
     * 3. Method name aliases: leads.tasks.all ↔ leads.tasks.list
     *
     * @param string $endpoint The user-provided endpoint
     * @param array $params The parsed parameters
     * @return array [resolvedEndpoint, params]
     */
    private function resolveEndpointAlias(string $endpoint, array $params): array
    {
        // ──────────────────────────────────────────────────────────
        // 1. Flat method aliases on leads resource
        //    leads.addTask → leads.tasks.create
        // ──────────────────────────────────────────────────────────
        $flatAliases = [
            // Tasks
            'leads.addTask'           => 'leads.tasks.create',
            'leads.createTask'        => 'leads.tasks.create',
            'leads.listTasks'         => 'leads.tasks.all',
            'leads.getTasks'          => 'leads.tasks.all',
            'leads.updateTask'        => 'leads.tasks.update',
            'leads.deleteTask'        => 'leads.tasks.delete',
            'leads.reorderTasks'      => 'leads.tasks.reorder',

            // Notes (addNote/updateNote/deleteNote exist on LeadsResource, but add aliases too)
            'leads.createNote'        => 'leads.notes.create',
            'leads.listNotes'         => 'leads.notes.all',
            'leads.getNotes'          => 'leads.notes.all',

            // Invoices
            'leads.createInvoice'     => 'leads.invoices.create',
            'leads.listInvoices'      => 'leads.invoices.list',
            'leads.getInvoice'        => 'leads.invoices.get',
            'leads.updateInvoice'     => 'leads.invoices.update',
            'leads.deleteInvoice'     => 'leads.invoices.delete',
            'leads.sendInvoice'       => 'leads.invoices.send',
            'leads.markInvoicePaid'   => 'leads.invoices.markPaid',
            'leads.voidInvoice'       => 'leads.invoices.void',

            // Deliverables
            'leads.createDeliverable' => 'leads.deliverables.create',
            'leads.listDeliverables'  => 'leads.deliverables.list',
            'leads.updateDeliverable' => 'leads.deliverables.update',
            'leads.deleteDeliverable' => 'leads.deliverables.delete',
            'leads.sendDeliverables'  => 'leads.deliverables.send',

            // Outreach
            'leads.generateEmail'     => 'leads.outreach.generateEmail',
            'leads.sendEmail'         => 'leads.outreach.sendEmail',
            'leads.generateAndSend'   => 'leads.outreach.generateAndSend',

            // Outreach Steps
            'leads.createOutreachStep'=> 'leads.outreachSteps.create',
            'leads.listOutreachSteps' => 'leads.outreachSteps.list',
            'leads.completeStep'      => 'leads.outreachSteps.complete',
            'leads.initOutreach'      => 'leads.outreachSteps.initializeDefault',

            // Activities
            'leads.addActivity'       => 'leads.activities.create',
            'leads.listActivities'    => 'leads.activities.all',
            'leads.deleteActivity'    => 'leads.activities.delete',

            // Status shorthand
            'leads.updateStatus'      => 'leads.update',

            // ── Bloq Lists ──
            'bloqs.listLists'         => 'bloqs.lists.all',
            'bloqs.getLists'          => 'bloqs.lists.all',
            'bloqs.createList'        => 'bloqs.lists.create',
            'bloqs.getList'           => 'bloqs.lists.get',
            'bloqs.updateList'        => 'bloqs.lists.update',
            'bloqs.deleteList'        => 'bloqs.lists.delete',

            // ── Bloq Items ──
            'bloqs.listItems'         => 'bloqs.items.list',
            'bloqs.getItems'          => 'bloqs.items.list',
            'bloqs.createItem'        => 'bloqs.items.create',
            'bloqs.addItem'           => 'bloqs.items.create',
            'bloqs.getItem'           => 'bloqs.items.get',
            'bloqs.updateItem'        => 'bloqs.items.update',
            'bloqs.deleteItem'        => 'bloqs.items.delete',
            'bloqs.addText'           => 'bloqs.items.addText',
            'bloqs.addMarkdown'       => 'bloqs.items.addMarkdown',
            'bloqs.addSpreadsheet'    => 'bloqs.items.addSpreadsheet',
            'bloqs.makePublic'        => 'bloqs.items.makePublic',
            'bloqs.makePrivate'       => 'bloqs.items.makePrivate',
            'bloqs.publishItem'       => 'bloqs.items.makePublic',
        ];

        if (isset($flatAliases[$endpoint])) {
            $endpoint = $flatAliases[$endpoint];
        }

        // ──────────────────────────────────────────────────────────
        // 2. Top-level pseudo-resources (require lead_id param)
        //    tasks.create → leads.tasks.create
        //    notes.list   → leads.notes.all
        // ──────────────────────────────────────────────────────────
        $topLevelRewrites = [
            'tasks'          => 'leads.tasks',
            'notes'          => 'leads.notes',
            'invoices'       => 'leads.invoices',
            'deliverables'   => 'leads.deliverables',
            'outreachSteps'  => 'leads.outreachSteps',
            'outreach_steps' => 'leads.outreachSteps',
            'activities'     => 'leads.activities',
        ];

        // ── Bloq sub-resource top-level aliases ──
        // bloqLists requires bloqId, bloqItems requires listId
        $bloqTopLevelRewrites = [
            'bloqLists'      => ['target' => 'bloqs.lists',  'requiredParam' => 'bloqId',  'label' => 'bloq_id'],
            'bloqlists'      => ['target' => 'bloqs.lists',  'requiredParam' => 'bloqId',  'label' => 'bloq_id'],
            'bloqItems'      => ['target' => 'bloqs.items',  'requiredParam' => 'listId',  'label' => 'list_id'],
            'bloqitems'      => ['target' => 'bloqs.items',  'requiredParam' => 'listId',  'label' => 'list_id'],
            'bloq_lists'     => ['target' => 'bloqs.lists',  'requiredParam' => 'bloqId',  'label' => 'bloq_id'],
            'bloq_items'     => ['target' => 'bloqs.items',  'requiredParam' => 'listId',  'label' => 'list_id'],
        ];

        $parts = explode('.', $endpoint);
        if (count($parts) === 2 && isset($bloqTopLevelRewrites[$parts[0]])) {
            $rewrite = $bloqTopLevelRewrites[$parts[0]];
            $endpoint = $rewrite['target'] . '.' . $parts[1];

            // Ensure required parent ID is present
            $paramName = $rewrite['requiredParam'];
            $snakeName = $rewrite['label'];
            if (!isset($params[$paramName]) && !isset($params[$snakeName])) {
                throw new \InvalidArgumentException(
                    "'{$parts[0]}.{$parts[1]}' requires {$snakeName} parameter. " .
                    "Usage: iris sdk:call {$parts[0]}.{$parts[1]} {$snakeName}=123 ..."
                );
            }
        }

        $parts = explode('.', $endpoint);
        if (count($parts) === 2 && isset($topLevelRewrites[$parts[0]])) {
            $endpoint = $topLevelRewrites[$parts[0]] . '.' . $parts[1];

            // Ensure lead_id is present (required for sub-resources)
            if (!isset($params['lead_id']) && !isset($params['leadId'])) {
                throw new \InvalidArgumentException(
                    "'{$parts[0]}.{$parts[1]}' requires lead_id parameter. " .
                    "Usage: iris sdk:call {$parts[0]}.{$parts[1]} lead_id=123 ..."
                );
            }
        }

        // ──────────────────────────────────────────────────────────
        // 3. Method name normalization
        //    .list → .all (for sub-resources that use 'all' instead of 'list')
        //    .add  → .create
        // ──────────────────────────────────────────────────────────
        $methodNormalization = [
            // Sub-resources use 'all' not 'list' for their listing method
            'leads.tasks.list'         => 'leads.tasks.all',
            'leads.notes.list'         => 'leads.notes.all',
            'leads.activities.list'    => 'leads.activities.all',
            // 'add' variants
            'leads.tasks.add'          => 'leads.tasks.create',
            'leads.notes.add'          => 'leads.notes.create',
            'leads.invoices.add'       => 'leads.invoices.create',
            'leads.deliverables.add'   => 'leads.deliverables.create',
            'leads.activities.add'     => 'leads.activities.create',

            // Bloq sub-resource normalization
            // ListsResource uses all() not list()
            'bloqs.lists.list'         => 'bloqs.lists.all',
            'bloqs.lists.add'          => 'bloqs.lists.create',
            // ItemsResource uses list() not all()
            'bloqs.items.all'          => 'bloqs.items.list',
            'bloqs.items.add'          => 'bloqs.items.create',
        ];

        if (isset($methodNormalization[$endpoint])) {
            $endpoint = $methodNormalization[$endpoint];
        }

        return [$endpoint, $params];
    }

    /**
     * Map common short parameter names to method parameter names.
     * This allows CLI users to use intuitive names like 'id' instead of 'agentId'.
     */
    private function mapParamName(string $shortName, array $methodParams): ?string
    {
        // Common mappings: short name => possible full names
        $mappings = [
            'id' => ['agentId', 'leadId', 'bloqId', 'userId', 'noteId', 'taskId', 'id'],
            'agent' => ['agentId', 'agent_id'],
            'lead' => ['leadId', 'lead_id'],
            'bloq' => ['bloqId', 'bloq_id'],
            'user' => ['userId', 'user_id'],
        ];

        // Get the list of possible full names for this short name
        $possibleNames = $mappings[$shortName] ?? [];

        // Also add camelCase and snake_case variants
        $camelCase = lcfirst(str_replace('_', '', ucwords($shortName, '_')));
        $snakeCase = strtolower(preg_replace('/([A-Z])/', '_$1', $shortName));
        $snakeCase = ltrim($snakeCase, '_');

        $possibleNames[] = $camelCase;
        $possibleNames[] = $snakeCase;
        $possibleNames[] = $shortName;

        // Check if any method param matches
        foreach ($methodParams as $methodParam) {
            $paramName = $methodParam->getName();
            if (in_array($paramName, $possibleNames, true)) {
                return $paramName;
            }
        }

        return null;
    }
    
    private function castValue(string $value)
    {
        if ($value === 'true') return true;
        if ($value === 'false') return false;
        if ($value === 'null') return null;
        if (is_numeric($value)) return $value + 0; // Cast to int or float
        if ($value[0] === '{' || $value[0] === '[') {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) return $decoded;
        }
        if (is_string($value) && strpos($value, '@') === 0) {
            $filePath = substr($value, 1);
            // Handle both absolute and relative paths
            if (!file_exists($filePath)) {
                // Try relative to current working directory
                $relative = getcwd() . '/' . $filePath;
                if (file_exists($relative)) {
                    $filePath = $relative;
                }
            }
            
            if (file_exists($filePath) && is_readable($filePath)) {
                return file_get_contents($filePath);
            }
        }
        return $value;
    }
    
    private function renderOutput($result, InputInterface $input, OutputInterface $output, SymfonyStyle $io): void
    {
        // Raw output
        if ($input->getOption('raw')) {
            if (is_string($result)) {
                $output->writeln($result);
            } else {
                $output->writeln(print_r($result, true));
            }
            return;
        }
        
        // JSON output
        if ($input->getOption('json')) {
            // Convert objects to arrays for JSON serialization
            if (is_object($result)) {
                if (method_exists($result, 'toArray')) {
                    $result = $result->toArray();
                } elseif (method_exists($result, 'jsonSerialize')) {
                    $result = $result->jsonSerialize();
                } else {
                    $result = json_decode(json_encode($result), true);
                }
            }
            $output->writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return;
        }
        
        // Smart formatting based on result type
        if (is_array($result)) {
            $this->renderArray($result, $output, $io);
        } elseif (is_object($result)) {
            $this->renderObject($result, $output, $io);
        } else {
            $io->success((string)$result);
        }
    }
    
    private function renderArray(array $data, OutputInterface $output, SymfonyStyle $io): void
    {
        // Handle empty arrays
        if (empty($data)) {
            $io->note('No results found');
            return;
        }
        
        // Check if it's a list of items (numeric keys) or single item (assoc keys)
        if (isset($data[0]) && is_array($data[0])) {
            // List of items - use compact format for large datasets
            $columnCount = count($data[0]);
            
            if ($columnCount > 10) {
                // Compact list format for wide tables
                $this->renderCompactList($data, $output, $io);
            } else {
                // Regular table for narrow datasets
                $table = new Table($output);
                $table->setHeaders(array_keys($data[0]));
                foreach ($data as $row) {
                    $table->addRow(array_map(fn($v) => $this->formatValue($v), array_values($row)));
                }
                $table->render();
            }
        } elseif ($this->isAssoc($data)) {
            // Single item - check for special fields that need full display
            $specialFields = ['notes', 'tasks', 'deliverables', 'activities'];
            $regularData = [];
            $specialData = [];
            
            foreach ($data as $key => $value) {
                if (in_array($key, $specialFields) && is_array($value) && !empty($value)) {
                    $specialData[$key] = $value;
                } else {
                    $regularData[$key] = $value;
                }
            }
            
            // Render regular fields in table
            if (!empty($regularData)) {
                $table = new Table($output);
                $table->setStyle('compact');
                $table->setHeaders(['Key', 'Value']);
                $table->setColumnMaxWidth(1, 80);
                foreach ($regularData as $key => $value) {
                    $table->addRow([$key, $this->formatValue($value, 80)]);
                }
                $table->render();
            }
            
            // Render special fields (notes, etc.) in clean format
            foreach ($specialData as $key => $items) {
                $this->renderSpecialField($key, $items, $output);
            }
        } else {
            // Simple list
            $io->listing($data);
        }
    }
    
    private function renderCompactList(array $data, OutputInterface $output, SymfonyStyle $io): void
    {
        // Determine key fields to show based on available data
        $firstItem = $data[0];
        $keyFields = $this->selectKeyFields($firstItem);
        
        $output->writeln('');
        $output->writeln('<fg=cyan>━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━</>');
        
        foreach ($data as $index => $item) {
            $header = $this->formatCompactItem($item, $keyFields);
            $output->writeln($header);
            
            // Show selected fields with colors and icons
            foreach ($keyFields as $field) {
                if (isset($item[$field]) && $item[$field] !== null && $item[$field] !== '') {
                    $icon = $this->getFieldIcon($field);
                    $color = $this->getFieldColor($field);
                    $value = $this->formatColoredValue($item[$field], $field, 100);
                    $output->writeln(sprintf('  %s <fg=%s>%s:</> %s', $icon, $color, $field, $value));
                }
            }
            
            if ($index < count($data) - 1) {
                $output->writeln('<fg=cyan>  ┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄┄</>');
            }
        }
        
        $output->writeln('<fg=cyan>━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━</>');
        $output->writeln(sprintf('<fg=green>✓ Total: %d items</>', count($data)));
        $output->writeln('<fg=yellow>💡 Tip: Use --json flag for full data</>');
        $output->writeln('');
    }
    
    private function getFieldIcon(string $field): string
    {
        return match($field) {
            'id' => '🔑',
            'title', 'name', 'nickname' => '👤',
            'email' => '📧',
            'phone' => '📱',
            'company' => '🏢',
            'status' => '📊',
            'type', 'lead_type' => '🏷️',
            'url', 'external_url' => '🔗',
            'note_count' => '📝',
            'tasks_count' => '✅',
            'created_at' => '🕐',
            'updated_at' => '🔄',
            'contact_info' => '📇',
            default => '•'
        };
    }
    
    private function getFieldColor(string $field): string
    {
        return match($field) {
            'id' => 'cyan',
            'title', 'name', 'nickname' => 'bright-blue',
            'email', 'phone', 'contact_info' => 'magenta',
            'company' => 'blue',
            'status' => 'yellow',
            'type', 'lead_type' => 'cyan',
            'url', 'external_url' => 'green',
            'note_count', 'tasks_count' => 'yellow',
            'created_at', 'updated_at' => 'gray',
            default => 'white'
        };
    }
    
    private function formatColoredValue($value, string $field, int $maxLength = 100): string
    {
        $formatted = $this->formatValue($value, $maxLength);
        
        // Special formatting for specific fields
        if ($field === 'status') {
            return $this->colorizeStatus($formatted);
        }
        
        if ($field === 'type' || $field === 'lead_type') {
            return "<fg=bright-cyan>{$formatted}</>";
        }
        
        if ($field === 'url' || $field === 'external_url') {
            return "<fg=bright-green;options=underscore>{$formatted}</>";
        }
        
        if (($field === 'note_count' || $field === 'tasks_count') && is_numeric($value)) {
            $color = $value > 0 ? 'bright-yellow' : 'gray';
            return "<fg={$color}>{$formatted}</>";
        }
        
        return $formatted;
    }
    
    private function colorizeStatus(string $status): string
    {
        return match(strtolower($status)) {
            'won' => '<fg=bright-green;options=bold>✓ Won</>',
            'lost' => '<fg=red>✗ Lost</>',
            'negotiation' => '<fg=yellow>⚡ Negotiation</>',
            'proposal' => '<fg=cyan>📄 Proposal</>',
            'qualified' => '<fg=blue>⭐ Qualified</>',
            'interested' => '<fg=magenta>👀 Interested</>',
            'contacted' => '<fg=bright-blue>📞 Contacted</>',
            'new' => '<fg=bright-cyan>✨ New</>',
            default => "<fg=white>{$status}</>"
        };
    }
    
    private function selectKeyFields(array $item): array
    {
        // Priority fields to show (in order of preference)
        $priorityFields = [
            'id', 'title', 'name', 'nickname', 'email', 'status', 'type', 'lead_type',
            'company', 'phone', 'url', 'external_url', 
            'note_count', 'tasks_count', 'contact_info',
            'updated_at', 'created_at'
        ];
        
        $selectedFields = [];
        foreach ($priorityFields as $field) {
            if (array_key_exists($field, $item)) {
                $selectedFields[] = $field;
                if (count($selectedFields) >= 10) break; // Limit to 10 fields
            }
        }
        
        return $selectedFields;
    }
    
    private function formatCompactItem(array $item, array $keyFields): string
    {
        // Create a one-line summary with colors
        $parts = [];
        
        if (isset($item['id'])) {
            $parts[] = "<fg=bright-cyan>#{$item['id']}</>";
        }
        
        $nameField = $item['name'] ?? $item['title'] ?? $item['nickname'] ?? null;
        if ($nameField) {
            $name = $this->formatValue($nameField, 50);
            $parts[] = "<fg=bright-white;options=bold>{$name}</>";
        }
        
        if (isset($item['status'])) {
            $parts[] = $this->colorizeStatus($item['status']);
        }
        
        return '  ' . implode(' <fg=gray>│</> ', $parts);
    }
    
    private function renderObject($obj, OutputInterface $output, SymfonyStyle $io): void
    {
        if (method_exists($obj, 'toArray')) {
            $this->renderArray($obj->toArray(), $output, $io);
        } else {
            $this->renderArray((array)$obj, $output, $io);
        }

        // Show pagination context for collections
        if (method_exists($obj, 'getMeta')) {
            $meta = $obj->getMeta();
            if (! empty($meta) && isset($meta['total'])) {
                $output->writeln(sprintf(
                    '<fg=cyan>📊 Showing page %d of %d (%d of %d total)</> | per_page: %d',
                    $meta['current_page'] ?? 1,
                    $meta['last_page'] ?? 1,
                    $obj->count(),
                    $meta['total'],
                    $meta['per_page'] ?? 10
                ));
                if (($meta['current_page'] ?? 1) < ($meta['last_page'] ?? 1)) {
                    $output->writeln('<fg=yellow>Use page=N to see more results</>');
                }
                $output->writeln('');
            }
        }
    }
    
    private function formatValue($value, int $maxLength = 50): string
    {
        if (is_array($value)) {
            // For arrays, show count or compact representation
            if (empty($value)) return '[]';
            $count = count($value);
            // If it's a simple array and short, show it
            $json = json_encode($value);
            if (strlen($json) <= $maxLength) return $json;
            // Otherwise show count
            return "[Array: {$count} items]";
        }
        if (is_object($value)) return method_exists($value, '__toString') ? (string)$value : get_class($value);
        if (is_bool($value)) return $value ? 'true' : 'false';
        if (is_null($value)) return 'null';
        
        $str = (string)$value;
        return strlen($str) > $maxLength ? substr($str, 0, $maxLength - 3) . '...' : $str;
    }
    
    private function renderSpecialField(string $fieldName, array $items, OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln("<fg=cyan>═══ {$fieldName} ({" . count($items) . "} items) ═══</>");
        $output->writeln('');
        
        foreach ($items as $index => $item) {
            $num = $index + 1;
            $output->writeln("<fg=yellow>── #{$num} ──</>");
            
            if (is_array($item)) {
                foreach ($item as $key => $value) {
                    if ($key === 'content' && is_string($value)) {
                        // Clean and display content
                        $cleaned = $this->cleanDecorativeFormatting($value);
                        $output->writeln("<fg=green>{$key}:</>");
                        $output->writeln($cleaned);
                    } elseif (!in_array($key, ['metadata', 'activity_data', 'lead_id'])) {
                        // Show other fields except metadata/bloat
                        $formatted = $this->formatValue($value, 100);
                        $output->writeln("<fg=green>{$key}:</> {$formatted}");
                    }
                }
            } else {
                $output->writeln($this->formatValue($item, 200));
            }
            
            if ($index < count($items) - 1) {
                $output->writeln('');
            }
        }
        
        $output->writeln('');
    }
    
    private function cleanDecorativeFormatting(string $text): string
    {
        // Strip Unicode box-drawing characters and decorative elements
        $text = preg_replace('/[\x{2500}-\x{257F}]/u', '', $text); // Box drawing
        $text = preg_replace('/[\x{2580}-\x{259F}]/u', '', $text); // Block elements
        $text = preg_replace('/[\x{25A0}-\x{25FF}]/u', '', $text); // Geometric shapes
        
        // Remove excessive repeated equal signs, dashes, underscores used as dividers
        $text = preg_replace('/[=\-_]{10,}/u', '', $text);
        
        // Clean up multiple consecutive newlines (more than 2)
        $text = preg_replace('/\n{3,}/u', "\n\n", $text);
        
        // Trim whitespace from each line
        $lines = explode("\n", $text);
        $lines = array_map('trim', $lines);
        $text = implode("\n", $lines);
        
        return trim($text);
    }
    
    private function isAssoc(array $arr): bool
    {
        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}
