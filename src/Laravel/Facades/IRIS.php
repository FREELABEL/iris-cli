<?php

declare(strict_types=1);

namespace IRIS\SDK\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use IRIS\SDK\IRIS as IRISClient;
use IRIS\SDK\Resources\Agents\AgentsResource;
use IRIS\SDK\Resources\Workflows\WorkflowsResource;
use IRIS\SDK\Resources\Bloqs\BloqsResource;
use IRIS\SDK\Resources\Leads\LeadsResource;
use IRIS\SDK\Resources\Integrations\IntegrationsResource;
use IRIS\SDK\Resources\RAG\RAGResource;

/**
 * IRIS Facade for Laravel
 *
 * Provides static access to the IRIS SDK.
 *
 * @method static AgentsResource agents()
 * @method static WorkflowsResource workflows()
 * @method static BloqsResource bloqs()
 * @method static LeadsResource leads()
 * @method static IntegrationsResource integrations()
 * @method static RAGResource rag()
 * @method static IRISClient asUser(int $userId)
 * @method static bool testConnection()
 * @method static array account()
 * @method static array usage()
 *
 * @see \IRIS\SDK\IRIS
 */
class IRIS extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return IRISClient::class;
    }
}
