<?php

namespace OrganiseYou\McpServer\Tests;

use OrganiseYou\McpServer\Facades\Mcp;
use OrganiseYou\McpServer\McpToolRegistry;
use OrganiseYou\McpServer\Server\McpServer;
use OrganiseYou\McpServer\Transport\HttpTransport;

class ScopedBindingsTest extends TestCase
{
    public function test_sequential_requests_see_disjoint_tools(): void
    {
        Mcp::tool('first')->handle(fn (array $input) => 'first');
        $firstServer = $this->app->make(McpServer::class);

        $this->assertSame(['first'], $this->listedToolNames());

        // What Laravel/Octane does between requests
        $this->app->forgetScopedInstances();
        Mcp::clearResolvedInstances();

        Mcp::tool('second')->handle(fn (array $input) => 'second');

        $this->assertSame(['second'], $this->listedToolNames());
        $this->assertNotSame($firstServer, $this->app->make(McpServer::class));
        $this->assertNotSame($firstServer, $this->app->make(HttpTransport::class));
    }

    public function test_scoped_server_uses_the_new_registry(): void
    {
        $this->app->make(McpServer::class);
        $this->app->forgetScopedInstances();

        $registry = $this->app->make(McpToolRegistry::class);
        $registry->register(\OrganiseYou\McpServer\Tool::make('fresh')->description('')); // no handle(): no auto-register

        $response = json_decode($this->app->make(McpServer::class)->handle(
            json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])
        ), true);

        $this->assertSame(['fresh'], array_column($response['result']['tools'], 'name'));
    }

    private function listedToolNames(): array
    {
        $tools = $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])
            ->json('result.tools');

        return array_column($tools, 'name');
    }
}
