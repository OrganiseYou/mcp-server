<?php

namespace OrganiseYou\McpServer\Tests;

use OrganiseYou\McpServer\McpToolRegistry;
use OrganiseYou\McpServer\Server\McpServer;
use OrganiseYou\McpServer\Tool;

class McpServerTest extends TestCase
{
    private McpServer $server;
    private McpToolRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = new McpToolRegistry();
        $this->server   = new McpServer($this->registry);
    }

    // -------------------------------------------------------------------------
    // initialize
    // -------------------------------------------------------------------------

    public function test_initialize_returns_protocol_version_and_capabilities(): void
    {
        $response = $this->send(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => []]);

        $this->assertSame('2.0', $response['jsonrpc']);
        $this->assertSame(1, $response['id']);
        $this->assertSame('2024-11-05', $response['result']['protocolVersion']);
        $this->assertArrayHasKey('tools', $response['result']['capabilities']);
        $this->assertArrayHasKey('name', $response['result']['serverInfo']);
    }

    // -------------------------------------------------------------------------
    // ping
    // -------------------------------------------------------------------------

    public function test_ping_returns_empty_result(): void
    {
        $response = $this->send(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'ping', 'params' => []]);

        $this->assertArrayHasKey('result', $response);
    }

    // -------------------------------------------------------------------------
    // tools/list
    // -------------------------------------------------------------------------

    public function test_tools_list_returns_empty_array_when_no_tools(): void
    {
        $response = $this->send(['jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/list', 'params' => []]);

        $this->assertSame([], $response['result']['tools']);
    }

    public function test_tools_list_returns_registered_tools(): void
    {
        $this->registerEchoTool();

        $response = $this->send(['jsonrpc' => '2.0', 'id' => 4, 'method' => 'tools/list', 'params' => []]);

        $tools = $response['result']['tools'];
        $this->assertCount(1, $tools);
        $this->assertSame('echo', $tools[0]['name']);
        $this->assertArrayHasKey('inputSchema', $tools[0]);
    }

    // -------------------------------------------------------------------------
    // tools/call
    // -------------------------------------------------------------------------

    public function test_tools_call_returns_text_content(): void
    {
        $this->registerEchoTool();

        $response = $this->send([
            'jsonrpc' => '2.0',
            'id'      => 5,
            'method'  => 'tools/call',
            'params'  => ['name' => 'echo', 'arguments' => ['msg' => 'hello']],
        ]);

        $this->assertFalse($response['result']['isError']);
        $this->assertSame('text', $response['result']['content'][0]['type']);
        $this->assertSame('hello', $response['result']['content'][0]['text']);
    }

    public function test_tools_call_json_encodes_non_string_result(): void
    {
        $tool = Tool::make('info')
            ->description('Returns structured data')
            ->inputSchema(['type' => 'object', 'properties' => []])
            ->handle(fn ($i) => ['status' => 'ok', 'count' => 3]);

        $this->registry->register($tool);

        $response = $this->send([
            'jsonrpc' => '2.0',
            'id'      => 6,
            'method'  => 'tools/call',
            'params'  => ['name' => 'info', 'arguments' => []],
        ]);

        $text = $response['result']['content'][0]['text'];
        $this->assertSame(['status' => 'ok', 'count' => 3], json_decode($text, true));
    }

    public function test_tools_call_unknown_tool_returns_error(): void
    {
        $response = $this->send([
            'jsonrpc' => '2.0',
            'id'      => 7,
            'method'  => 'tools/call',
            'params'  => ['name' => 'missing', 'arguments' => []],
        ]);

        $this->assertArrayHasKey('error', $response);
        $this->assertSame(-32601, $response['error']['code']);
    }

    public function test_tools_call_missing_name_returns_error(): void
    {
        $response = $this->send([
            'jsonrpc' => '2.0',
            'id'      => 8,
            'method'  => 'tools/call',
            'params'  => ['arguments' => []],
        ]);

        $this->assertArrayHasKey('error', $response);
    }

    // -------------------------------------------------------------------------
    // error handling
    // -------------------------------------------------------------------------

    public function test_invalid_json_returns_parse_error(): void
    {
        $raw      = $this->server->handle('not json {{{');
        $response = json_decode($raw, true);

        $this->assertSame(-32700, $response['error']['code']);
    }

    public function test_unknown_method_returns_method_not_found_error(): void
    {
        $response = $this->send([
            'jsonrpc' => '2.0',
            'id'      => 9,
            'method'  => 'nonexistent/method',
            'params'  => [],
        ]);

        $this->assertSame(-32601, $response['error']['code']);
    }

    public function test_notification_without_id_returns_null(): void
    {
        $raw = json_encode(['jsonrpc' => '2.0', 'method' => 'ping', 'params' => []]);

        $result = $this->server->handle($raw);

        $this->assertNull($result);
    }

    // -------------------------------------------------------------------------
    // helpers
    // -------------------------------------------------------------------------

    private function send(array $payload): array
    {
        $raw      = json_encode($payload);
        $response = $this->server->handle($raw);

        return json_decode($response, true);
    }

    private function registerEchoTool(): void
    {
        $tool = Tool::make('echo')
            ->description('Echoes the msg parameter')
            ->inputSchema([
                'type'       => 'object',
                'properties' => ['msg' => ['type' => 'string']],
                'required'   => ['msg'],
            ])
            ->handle(fn (array $input) => $input['msg']);

        $this->registry->register($tool);
    }
}
