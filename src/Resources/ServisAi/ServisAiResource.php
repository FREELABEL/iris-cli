<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\ServisAi;

use IRIS\SDK\Config;
use IRIS\SDK\Http\Client;

/**
 * Servis.ai Dynamic Proxy Resource
 *
 * Provides dynamic access to all Servis.ai functions via magic methods.
 * Any function available in Servis.ai can be called directly.
 *
 * @example
 * ```php
 * // Call any function dynamically
 * $case = $iris->servisAi->getCaseDetails(['case_id' => 'CAS102377']);
 * $apps = $iris->servisAi->listApps();
 * $users = $iris->servisAi->listAccountUsers(['limit' => 10]);
 * $analysis = $iris->servisAi->analyzeCaseComprehensive(['case_id' => 'CAS102377']);
 *
 * // Or use execute() directly
 * $result = $iris->servisAi->execute('list_activities', ['limit' => 10]);
 * ```
 *
 * @method array listApps(array $params = [])
 * @method array listActivities(array $params = [])
 * @method array listAccountUsers(array $params = [])
 * @method array listChanges(array $params = [])
 * @method array listAppFields(array $params = [])
 * @method array listPhoneCalls(array $params = [])
 * @method array listServices(array $params = [])
 * @method array listEventLogs(array $params = [])
 * @method array getCaseDetails(array $params = [])
 * @method array getCaseFields(array $params = [])
 * @method array analyzeCaseComprehensive(array $params = [])
 * @method array summarizeCase(array $params = [])
 * @method array getUserProfile(array $params = [])
 * @method array searchGlobal(array $params = [])
 */
class ServisAiResource
{
    protected Client $http;
    protected Config $config;

    public function __construct(Client $http, Config $config)
    {
        $this->http = $http;
        $this->config = $config;
    }

    /**
     * Execute any Servis.ai function.
     *
     * @param string $function Function name (snake_case)
     * @param array $params Function parameters
     * @return array Result data
     */
    public function execute(string $function, array $params = []): array
    {
        $userId = $this->config->userId;

        return $this->http->post("/api/v1/users/{$userId}/integrations/execute", [
            'integration' => 'servis-ai',
            'action' => $function,
            'parameters' => $params,
        ]);
    }

    /**
     * Magic method to proxy any function call to Servis.ai.
     * Converts camelCase method names to snake_case function names.
     *
     * @param string $method Method name (camelCase)
     * @param array $arguments Arguments (first arg is params array)
     * @return array Result
     */
    public function __call(string $method, array $arguments): array
    {
        // Convert camelCase to snake_case
        $function = $this->toSnakeCase($method);
        $params = $arguments[0] ?? [];

        return $this->execute($function, $params);
    }

    /**
     * Convert camelCase to snake_case.
     */
    protected function toSnakeCase(string $input): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $input));
    }

    /**
     * Test the connection.
     */
    public function test(): array
    {
        try {
            $apps = $this->execute('list_apps', []);
            return [
                'success' => true,
                'message' => 'Connected',
                'app_count' => is_array($apps) ? count($apps) : 0,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Shortcut: Get case by ID.
     */
    public function case(string $caseId): array
    {
        return $this->execute('get_case_details', ['case_id' => $caseId]);
    }

    /**
     * Shortcut: Analyze case.
     */
    public function analyze(string $caseId): array
    {
        return $this->execute('analyze_case_comprehensive', ['case_id' => $caseId]);
    }

    /**
     * Shortcut: List apps/entities.
     */
    public function apps(): array
    {
        return $this->execute('list_apps', []);
    }

    /**
     * Shortcut: Get case fields.
     */
    public function fields(string $entity = 'case_record'): array
    {
        return $this->execute('get_case_fields', ['entity' => $entity]);
    }
}
