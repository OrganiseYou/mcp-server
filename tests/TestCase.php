<?php

namespace OrganiseYou\McpServer\Tests;

use OrganiseYou\McpServer\McpServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [McpServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Mcp' => \OrganiseYou\McpServer\Facades\Mcp::class,
        ];
    }
}
