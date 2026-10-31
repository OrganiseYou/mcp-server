<?php

namespace OrganiseYou\McpServer\Tests;

use OrganiseYou\McpServer\Facades\Mcp;

class HttpTransportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Mcp::tool('multiply')
            ->description('Multiplies two numbers')
            ->inputSchema([
                'type'       => 'object',
                'properties' => [
                    'x' => ['type' => 'number'],
                    'y' => ['type' => 'number'],
                ],
                'required' => ['x', 'y'],
            ])
            ->handle(fn (array $input) => $input['x'] * $input['y']);
    }

    public function test_tools_list_over_http(): void
    {
        $response = $this->postMcp(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list', 'params' => []]);

        $response->assertOk();
        $tools = $response->json('result.tools');
        $this->assertCount(1, $tools);
        $this->assertSame('multiply', $tools[0]['name']);
    }

    public function test_tools_call_over_http(): void
    {
        $response = $this->postMcp([
            'jsonrpc' => '2.0',
            'id'      => 2,
            'method'  => 'tools/call',
            'params'  => ['name' => 'multiply', 'arguments' => ['x' => 6, 'y' => 7]],
        ]);

        $response->assertOk();
        $this->assertFalse($response->json('result.isError'));
        $this->assertSame('42', $response->json('result.content.0.text'));
    }

    public function test_batch_request_over_http(): void
    {
        $response = $this->postMcp([
            ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping', 'params' => []],
            ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list', 'params' => []],
        ]);

        $response->assertOk();
        $body = $response->json();
        $this->assertIsArray($body);
        $this->assertCount(2, $body);
    }

    public function test_invalid_json_over_http_returns_parse_error(): void
    {
        $response = $this->postJson('/mcp', [], ['CONTENT_TYPE' => 'application/json']);

        // Raw invalid JSON — send via call()
        $response = $this->call('POST', '/mcp', [], [], [], ['CONTENT_TYPE' => 'application/json'], 'not json {{');

        $this->assertSame(-32700, $response->json('error.code'));
    }

    public function test_notification_returns_202_with_empty_body(): void
    {
        // A notification has no id — the server answers 202 Accepted with no body
        $response = $this->postMcp(['jsonrpc' => '2.0', 'method' => 'notifications/initialized']);

        $response->assertStatus(202);
        $this->assertSame('', $response->getContent());
    }

    public function test_batch_of_only_notifications_returns_202_with_empty_body(): void
    {
        $response = $this->postMcp([
            ['jsonrpc' => '2.0', 'method' => 'notifications/initialized'],
            ['jsonrpc' => '2.0', 'method' => 'notifications/cancelled', 'params' => ['requestId' => 1]],
        ]);

        $response->assertStatus(202);
        $this->assertSame('', $response->getContent());
    }

    // -------------------------------------------------------------------------

    private function postMcp(array $payload): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/mcp', $payload);
    }
}
