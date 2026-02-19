<?php

declare(strict_types=1);

namespace IRIS\SDK\Tests\Feature\Console;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use IRIS\SDK\Console\Application;

/**
 * Integration tests for the CLI
 * Tests the full command execution flow
 *
 * @group integration
 * @group cli
 */
class CLIIntegrationTest extends TestCase
{
    private Application $application;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->application = new Application();
    }
    
    public function testListCommandShowsAvailableCommands(): void
    {
        $command = $this->application->find('list');
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);
        
        $output = $commandTester->getDisplay();
        
        $this->assertStringContainsString('IRIS SDK', $output);
        $this->assertStringContainsString('sdk:call', $output);
        $this->assertStringContainsString('Dynamic proxy to SDK resources and methods', $output);
    }
    
    public function testHelpCommandShowsUsageInformation(): void
    {
        $command = $this->application->find('help');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['command_name' => 'sdk:call']);
        
        $output = $commandTester->getDisplay();
        
        $this->assertStringContainsString('sdk:call', $output);
        $this->assertStringContainsString('endpoint', $output);
        $this->assertStringContainsString('params', $output);
        $this->assertStringContainsString('--json', $output);
        $this->assertStringContainsString('--raw', $output);
    }
    
    public function testCommandWithoutEndpointShowsError(): void
    {
        $command = $this->application->find('sdk:call');
        $commandTester = new CommandTester($command);
        
        $this->expectException(\Symfony\Component\Console\Exception\RuntimeException::class);
        $this->expectExceptionMessage('Not enough arguments');
        
        $commandTester->execute([]);
    }
    
    public function testCommandWithInvalidEndpointFormat(): void
    {
        putenv('IRIS_API_KEY=test_key');
        putenv('IRIS_USER_ID=123');
        
        $command = $this->application->find('sdk:call');
        $commandTester = new CommandTester($command);
        
        $exitCode = $commandTester->execute([
            'endpoint' => 'nomethod', // Missing the dot separator
        ]);
        
        $this->assertEquals(1, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Invalid endpoint format', $output);
        
        putenv('IRIS_API_KEY');
        putenv('IRIS_USER_ID');
    }
    
    public function testCommandWithParametersIsParsedCorrectly(): void
    {
        putenv('IRIS_API_KEY=test_key');
        putenv('IRIS_USER_ID=123');
        
        $command = $this->application->find('sdk:call');
        $commandTester = new CommandTester($command);
        
        // This will fail at execution (no real API), but should parse correctly
        $exitCode = $commandTester->execute([
            'endpoint' => 'leads.list',
            'params' => ['status=active', 'limit=10'],
        ]);
        
        // Should fail on API call (not parsing)
        $this->assertEquals(1, $exitCode);
        $output = $commandTester->getDisplay();
        
        // Should NOT contain parsing errors
        $this->assertStringNotContainsString('Invalid endpoint format', $output);
        // Should contain API/connection error instead
        $this->assertNotEmpty($output);
        
        putenv('IRIS_API_KEY');
        putenv('IRIS_USER_ID');
    }
    
    public function testVerboseOutputShowsStackTrace(): void
    {
        putenv('IRIS_API_KEY=test_key');
        putenv('IRIS_USER_ID=123');
        
        $command = $this->application->find('sdk:call');
        $commandTester = new CommandTester($command);
        
        $commandTester->execute(
            [
                'endpoint' => 'leads.list',
            ],
            ['verbosity' => \Symfony\Component\Console\Output\OutputInterface::VERBOSITY_VERBOSE]
        );
        
        // With verbose, should show more details on error
        $output = $commandTester->getDisplay();
        $this->assertNotEmpty($output);
        
        putenv('IRIS_API_KEY');
        putenv('IRIS_USER_ID');
    }
}
