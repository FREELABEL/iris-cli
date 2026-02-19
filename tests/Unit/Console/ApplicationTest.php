<?php

declare(strict_types=1);

namespace IRIS\SDK\Tests\Unit\Console;

use PHPUnit\Framework\TestCase;
use IRIS\SDK\Console\Application;
use IRIS\SDK\Console\Commands\SDKCommand;

/**
 * Tests for the IRIS Console Application
 *
 * @covers \IRIS\SDK\Console\Application
 */
class ApplicationTest extends TestCase
{
    private Application $application;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->application = new Application();
    }
    
    public function testApplicationHasCorrectNameAndVersion(): void
    {
        $this->assertEquals('IRIS SDK', $this->application->getName());
        $this->assertEquals('1.0.0', $this->application->getVersion());
    }
    
    public function testApplicationRegistersSDKCommand(): void
    {
        $this->assertTrue($this->application->has('sdk:call'));
        
        $command = $this->application->get('sdk:call');
        $this->assertInstanceOf(SDKCommand::class, $command);
    }
    
    public function testApplicationCommandsAreAccessible(): void
    {
        $commands = $this->application->all();
        
        // Should have at least the sdk:call command plus built-in commands
        $this->assertArrayHasKey('sdk:call', $commands);
        $this->assertArrayHasKey('list', $commands);
        $this->assertArrayHasKey('help', $commands);
    }
}
