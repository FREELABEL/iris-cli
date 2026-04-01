<?php

namespace IRIS\SDK\Console;

use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use IRIS\SDK\Console\Commands\SDKCommand;
use IRIS\SDK\Console\Commands\ChatCommand;
use IRIS\SDK\Console\Commands\ConfigCommand;
use IRIS\SDK\Console\Commands\ToolsCommand;
use IRIS\SDK\Console\Commands\IntegrationsCommand;
use IRIS\SDK\Console\Commands\SkillsCommand;
use IRIS\SDK\Console\Commands\MemoryComposeCommand;
use IRIS\SDK\Console\Commands\MemoryListCommand;
use IRIS\SDK\Console\Commands\MemoryShowCommand;
use IRIS\SDK\Console\Commands\MemoryAddCommand;
use IRIS\SDK\Console\Commands\SetupCommand;
use IRIS\SDK\Console\Commands\AgentCreateCommand;
use IRIS\SDK\Console\Commands\ServisAiCommand;
use IRIS\SDK\Console\Commands\EvalCommand;
use IRIS\SDK\Console\Commands\DeliverCommand;
use IRIS\SDK\Console\Commands\ScheduleCommand;
use IRIS\SDK\Console\Commands\SopCommand;
use IRIS\SDK\Console\Commands\PaymentsCommand;
use IRIS\SDK\Console\Commands\AppCommand;
use IRIS\SDK\Console\Commands\BloqIngestCommand;
use IRIS\SDK\Console\Commands\BloqIngestionStatusCommand;
use IRIS\SDK\Console\Commands\BloqIngestionJobsCommand;
use IRIS\SDK\Console\Commands\BloqCancelIngestionCommand;
use IRIS\SDK\Console\Commands\VoiceCommand;
use IRIS\SDK\Console\Commands\PhoneCommand;
use IRIS\SDK\Console\Commands\AgentCommand;
use IRIS\SDK\Console\Commands\AutomationCommand;
use IRIS\SDK\Console\Commands\AutomationTestCommand;
use IRIS\SDK\Console\Commands\TokenCommand;
use IRIS\SDK\Console\Commands\UsersCommand;
use IRIS\SDK\Console\Commands\RemindRCommand;
use IRIS\SDK\Console\Commands\PagesCommand;
use IRIS\SDK\Console\Commands\PartialsCommand;
use IRIS\SDK\Console\Commands\ConsolidateLeadsCommand;
use IRIS\SDK\Console\Commands\DemoShowcaseCommand;
use IRIS\SDK\Console\Commands\ProfileCommand;
use IRIS\SDK\Console\Commands\WalletCommand;
use IRIS\SDK\Console\Commands\LeadgenCommand;
use IRIS\SDK\Console\Commands\LeadScrapeCommand;
use IRIS\SDK\Console\Commands\MarketplaceCommand;
use IRIS\SDK\Console\Commands\CloudUploadCommand;
use IRIS\SDK\Console\Commands\DiaryCommand;
use IRIS\SDK\Console\Commands\BloqsCommand;
use IRIS\SDK\Console\Commands\OutreachCommand;
use IRIS\SDK\Console\Commands\OutreachCampaignCommand;
use IRIS\SDK\Console\Commands\OutreachSendCommand;
use IRIS\SDK\Console\Commands\CallCommand;
use IRIS\SDK\Console\Commands\BloqMembersCommand;
use IRIS\SDK\Console\Commands\MonitorCommand;
use IRIS\SDK\Console\Commands\InvoicesCommand;
use IRIS\SDK\Console\Commands\PackagesCommand;
use IRIS\SDK\Console\Commands\LeadsCommand;
use IRIS\SDK\Console\Commands\LeadGraphCommand;
use IRIS\SDK\Console\Commands\LeadAssociationsCommand;
use IRIS\SDK\Console\Commands\OpportunitiesCommand;
use IRIS\SDK\Console\Commands\AtlasOsCommand;
use IRIS\SDK\Console\Commands\EventsCommand;
use IRIS\SDK\Console\Commands\ServicesCommand;
use IRIS\SDK\Console\Commands\RunCommand;

class Application extends BaseApplication
{
    public function __construct()
    {
        parent::__construct('IRIS SDK', '1.0.0');

        $this->addCommands([
            new SetupCommand(),
            new MarketplaceCommand(),
            new SDKCommand(),
            new ChatCommand(),
            new ConfigCommand(),
            new ToolsCommand(),
            new IntegrationsCommand(),
            new SkillsCommand(),
            new MemoryComposeCommand(),
            new MemoryListCommand(),
            new MemoryShowCommand(),
            new MemoryAddCommand(),
            new AgentCreateCommand(),
            new ServisAiCommand(),
            new EvalCommand(),
            new DeliverCommand(),
            new ScheduleCommand(),
            new SopCommand(),
            new PaymentsCommand(),
            new InvoicesCommand(),
            new AppCommand(),
            new BloqIngestCommand(),
            new BloqIngestionStatusCommand(),
            new BloqIngestionJobsCommand(),
            new BloqCancelIngestionCommand(),
            new VoiceCommand(),
            new PhoneCommand(),
            new AgentCommand(),
            new AutomationCommand(),
            new AutomationTestCommand(),
            new TokenCommand(),
            new UsersCommand(),
            new RemindRCommand(),
            new PagesCommand(),
            new PartialsCommand(),
            new ConsolidateLeadsCommand(),
            new DemoShowcaseCommand(),
            new ProfileCommand(),
            new WalletCommand(),
            new LeadgenCommand(),
            new LeadScrapeCommand(),
            new CloudUploadCommand(),
            new DiaryCommand(),
            new BloqsCommand(),
            new BloqMembersCommand(),
            new OutreachCommand(),
            new OutreachCampaignCommand(),
            new OutreachSendCommand(),
            new CallCommand(),
            new MonitorCommand(),
            new PackagesCommand(),
            new LeadsCommand(),
            new LeadGraphCommand(),
            new LeadAssociationsCommand(),
            new OpportunitiesCommand(),
            new AtlasOsCommand(),
            new EventsCommand(),
            new ServicesCommand(),
            new RunCommand(),
        ]);
    }

    public function getHelp(): string
    {
        return <<<'HELP'
<fg=cyan>██╗██████╗ ██╗███████╗</>    <fg=gray>███████╗██████╗ ██╗  ██╗</>
<fg=cyan>██║██╔══██╗██║██╔════╝</>    <fg=gray>██╔════╝██╔══██╗██║ ██╔╝</>
<fg=cyan>██║██████╔╝██║███████╗</>    <fg=gray>███████╗██║  ██║█████╔╝</>
<fg=cyan>██║██╔══██╗██║╚════██║</>    <fg=gray>╚════██║██║  ██║██╔═██╗</>
<fg=cyan>██║██║  ██║██║███████║</>    <fg=gray>███████║██████╔╝██║  ██╗</>
<fg=cyan>╚═╝╚═╝  ╚═╝╚═╝╚══════╝</>    <fg=gray>╚══════╝╚═════╝ ╚═╝  ╚═╝</>

<fg=white;options=bold>IRIS SDK — Build, deploy, and manage AI agents from the command line.</>

<fg=yellow;options=bold>Getting Started</>
  <fg=green>setup</>                           Configure credentials and environment
  <fg=green>chat</> <agent_id> "message"        Chat with an AI agent
  <fg=green>sdk</> <method> [params]            Call any SDK method directly

<fg=yellow;options=bold>Genesis</> <fg=gray>(Page Builder)</>
  <fg=green>pages</> | <fg=green>genesis</>                 Manage composable landing pages
  <fg=green>pages</> sync <slug>                Pull, diff, and push page changes
  <fg=green>pages</> set <slug> <path> <value>  Atomic dot-notation updates
  <fg=green>partials</>                         Manage reusable page components

<fg=yellow;options=bold>Reachr</> <fg=gray>(Outreach & Lead Gen)</>
  <fg=green>outreach:strategy</> | <fg=green>reachr:strategy</>  Manage outreach strategies
  <fg=green>outreach:campaign</> | <fg=green>reachr:campaign</>  Create and run campaigns
  <fg=green>outreach:send</> | <fg=green>reachr:send</>          Per-lead outreach steps
  <fg=green>leads</>                            Manage leads and CRM
  <fg=green>lead-graph</>                       View lead relationship graphs
  <fg=green>leadgen</>                          Lead generation and scraping

<fg=yellow;options=bold>Lexicon</> <fg=gray>(Knowledge Bases)</>
  <fg=green>bloqs</> | <fg=green>lexicon</>                 Manage knowledge bases, projects, boards
  <fg=green>bloq:ingest</>                      Ingest content into knowledge bases
  <fg=green>bloq:members</>                     Manage knowledge base members
  <fg=green>memory:compose</>                   AI-powered knowledge base creation
  <fg=green>memory:list</> | <fg=green>memory:show</> | <fg=green>memory:add</>  Working memory CRUD

<fg=yellow;options=bold>Echo</> <fg=gray>(Voice & Communication)</>
  <fg=green>voice</> | <fg=green>echo</>                    Manage agent voice settings
  <fg=green>phone</>                            Phone call management (VAPI/Twilio)
  <fg=green>call</>                             Make and manage calls

<fg=yellow;options=bold>Atlas</> <fg=gray>(Chief of Staff)</>
  <fg=green>atlas</> | <fg=green>atlas-os</>                Inventory, budget, staff, events, calendar

<fg=yellow;options=bold>Agents & Workflows</>
  <fg=green>agent</>                            Manage agents
  <fg=green>agent:create</>                     Create a new AI agent
  <fg=green>automation</>                       Build multi-agent workflows
  <fg=green>automation:test</>                  Test workflow execution
  <fg=green>schedule</> | <fg=green>heartbeat</>             Manage scheduled jobs and heartbeats
  <fg=green>monitor</> | <fg=green>health</>                Platform health and diagnostics
  <fg=green>eval</>                             Run agent evaluation tests

<fg=yellow;options=bold>Business</>
  <fg=green>payments</>                         Stripe billing and subscriptions
  <fg=green>invoices</>                         Invoice management
  <fg=green>packages</>                         Product packages and pricing
  <fg=green>opportunities</>                    Sales pipeline and deals
  <fg=green>profile</>                          Manage user profiles
  <fg=green>marketplace</>                      Browse and publish to marketplace

<fg=yellow;options=bold>Content & Storage</>
  <fg=green>cloud-upload</>                     Upload files to cloud storage
  <fg=green>deliver</>                          Deliver content to leads
  <fg=green>diary</>                            Daily diary and notes

<fg=yellow;options=bold>Platform</>
  <fg=green>config</>                           View and update configuration
  <fg=green>integrations</>                     Manage third-party integrations
  <fg=green>tools</>                            AI agent tools and functions
  <fg=green>skills</>                           Manage agent skills
  <fg=green>token</>                            Token and authentication management
  <fg=green>users</>                            User management
  <fg=green>run</>                              Execute arbitrary SDK commands

<fg=gray>Run</> iris <command> --help <fg=gray>for detailed usage of any command.</>
HELP;
    }
}
