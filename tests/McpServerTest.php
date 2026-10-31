<?php

namespace OrganiseYou\McpServer\Tests;

use OrganiseYou\McpServer\Contracts\McpTool;
use OrganiseYou\McpServer\Exceptions\ToolError;
use OrganiseYou\McpServer\McpToolRegistry;
use OrganiseYou\McpServer\Server\McpServer;
use OrganiseYou\McpServer\Tool;
use OrganiseYou\McpServer\ToolContext;

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
        $this->assertSame('2025-03-26', $response['result']['protocolVersion']);
        $this->assertArrayHasKey('tools', $response['result']['capabilities']);
        $this->assertArrayHasKey('name', $response['result']['serverInfo']);
    }

    public function test_initialize_echoes_supported_requested_version(): void
    {
        $response = $this->send([
            'jsonrpc' => '2.0',
            'id'      => 1,
            'method'  => 'initialize',
            'params'  => ['protocolVersion' => '2024-11-05'],
        ]);

        $this->assertSame('2024-11-05', $response['result']['protocolVersion']);
    }

    public function test_initialize_offers_latest_for_unknown_version(): void
    {
        $response = $this->send([
            'jsonrpc' => '2.0',
            'id'      => 1,
            'method'  => 'initialize',
            'params'  => ['protocolVersion' => '1999-01-01'],
        ]);

        $this->assertSame('2025-03-26', $response['result']['protocolVersion']);
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
        $this->assertArrayNotHasKey('annotations', $tools[0]);
    }

    public function test_tools_list_includes_annotations_only_when_declared(): void
    {
        $this->registerEchoTool();
        $this->registry->register(
            Tool::make('read')->annotations(['readOnlyHint' => true])->handle(fn ($i) => 'ok')
        );
        $this->registry->register(new class implements McpTool {
            public function name(): string        { return 'delete'; }
            public function description(): string { return 'Deletes'; }
            public function inputSchema(): array  { return ['type' => 'object', 'properties' => []]; }
            public function annotations(): array  { return ['destructiveHint' => true, 'idempotentHint' => true]; }
            public function __invoke(array $input): mixed { return 'gone'; }
        });

        $tools = array_column(
            $this->send(['jsonrpc' => '2.0', 'id' => 4, 'method' => 'tools/list'])['result']['tools'],
            null,
            'name',
        );

        $this->assertArrayNotHasKey('annotations', $tools['echo']);
        $this->assertSame(['readOnlyHint' => true], $tools['read']['annotations']);
        $this->assertSame(['destructiveHint' => true, 'idempotentHint' => true], $tools['delete']['annotations']);
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

    public function test_tool_error_returns_is_error_result_with_reason(): void
    {
        $this->registry->register(
            Tool::make('guarded')->handle(fn ($i) => throw new ToolError('forbidden', 'x'))
        );

        $response = $this->send([
            'jsonrpc' => '2.0',
            'id'      => 10,
            'method'  => 'tools/call',
            'params'  => ['name' => 'guarded', 'arguments' => []],
        ]);

        $this->assertArrayNotHasKey('error', $response);
        $this->assertTrue($response['result']['isError']);
        $this->assertSame(
            ['reason' => 'forbidden', 'message' => 'x'],
            json_decode($response['result']['content'][0]['text'], true),
        );
    }

    public function test_runtime_exception_in_tool_still_returns_json_rpc_error(): void
    {
        $this->registry->register(
            Tool::make('broken')->handle(fn ($i) => throw new \RuntimeException('boom'))
        );

        $response = $this->send([
            'jsonrpc' => '2.0',
            'id'      => 11,
            'method'  => 'tools/call',
            'params'  => ['name' => 'broken', 'arguments' => []],
        ]);

        $this->assertArrayNotHasKey('result', $response);
        $this->assertSame(-32603, $response['error']['code']);
        $this->assertSame('boom', $response['error']['message']);
    }

    // -------------------------------------------------------------------------
    // context
    // -------------------------------------------------------------------------

    public function test_two_argument_closure_receives_given_context(): void
    {
        $received = null;
        $this->registry->register(
            Tool::make('ctx')->handle(function (array $input, ToolContext $context) use (&$received) {
                $received = $context;
                return 'ok';
            })
        );

        $context = new ToolContext('req-1', actor: 'user-7', scopes: ['metadata:read']);
        $this->server->callTool('ctx', [], $context);

        $this->assertSame($context, $received);
    }

    public function test_class_tool_with_optional_context_receives_context(): void
    {
        $tool = new class implements McpTool {
            public ?ToolContext $received = null;
            public function name(): string        { return 'ctx_class'; }
            public function description(): string { return ''; }
            public function inputSchema(): array  { return ['type' => 'object', 'properties' => []]; }
            public function __invoke(array $input, ?ToolContext $context = null): mixed
            {
                $this->received = $context;
                return 'ok';
            }
        };
        $this->registry->register($tool);

        $context = new ToolContext(actor: 'user-7');
        $this->server->callTool('ctx_class', [], $context);

        $this->assertSame($context, $tool->received);
    }

    public function test_one_argument_closure_is_called_with_input_only(): void
    {
        $args = null;
        $this->registry->register(
            Tool::make('one')->handle(function (array $input) use (&$args) {
                $args = func_get_args();
                return 'ok';
            })
        );

        $this->server->callTool('one', ['a' => 1], new ToolContext());

        $this->assertSame([['a' => 1]], $args);
    }

    public function test_tools_call_uses_bound_context_and_json_rpc_id(): void
    {
        $this->app->scoped(ToolContext::class, fn () => new ToolContext(actor: 'user-9', scopes: ['a']));

        $received = null;
        $this->registry->register(
            Tool::make('ctx')->handle(function (array $input, ToolContext $context) use (&$received) {
                $received = $context;
                return 'ok';
            })
        );

        $this->send(['jsonrpc' => '2.0', 'id' => 'abc', 'method' => 'tools/call', 'params' => ['name' => 'ctx']]);

        $this->assertSame('abc', $received->requestId);
        $this->assertSame('user-9', $received->actor);
        $this->assertTrue($received->hasScope('a'));
    }

    public function test_call_tool_matches_tools_call_result(): void
    {
        $this->registerEchoTool();

        $viaRpc = $this->send([
            'jsonrpc' => '2.0',
            'id'      => 12,
            'method'  => 'tools/call',
            'params'  => ['name' => 'echo', 'arguments' => ['msg' => 'hi']],
        ])['result'];

        $this->assertSame($viaRpc, $this->server->callTool('echo', ['msg' => 'hi'], new ToolContext()));
    }

    public function test_call_tool_throws_for_unknown_tool(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(-32601);

        $this->server->callTool('missing', [], new ToolContext());
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

    public function test_initialized_notification_returns_null_without_throwing(): void
    {
        $raw = json_encode(['jsonrpc' => '2.0', 'method' => 'notifications/initialized']);

        $result = $this->server->handle($raw);

        $this->assertNull($result);
    }

    public function test_unknown_notification_returns_null_without_throwing(): void
    {
        $raw = json_encode(['jsonrpc' => '2.0', 'method' => 'notifications/never-heard-of', 'params' => []]);

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
