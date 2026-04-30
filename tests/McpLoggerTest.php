<?php

namespace OrganiseYou\McpServer\Tests;

use OrganiseYou\McpServer\Logging\McpLogger;
use Psr\Log\AbstractLogger;

class ArrayLogger extends AbstractLogger
{
    public array $records = [];

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
    }

    public function hasRecord(string $level, string $message): bool
    {
        foreach ($this->records as $r) {
            if ($r['level'] === $level && $r['message'] === $message) {
                return true;
            }
        }
        return false;
    }

    public function hasContextKey(string $key, mixed $value): bool
    {
        foreach ($this->records as $r) {
            if (array_key_exists($key, $r['context']) && $r['context'][$key] === $value) {
                return true;
            }
        }
        return false;
    }

    public function isEmpty(): bool
    {
        return empty($this->records);
    }

    public function firstWithMessage(string $message): ?array
    {
        foreach ($this->records as $r) {
            if ($r['message'] === $message) {
                return $r;
            }
        }
        return null;
    }
}

class McpLoggerTest extends TestCase
{
    private ArrayLogger $driver;
    private McpLogger $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->driver = new ArrayLogger();
        $this->logger = new McpLogger($this->driver);
    }

    // -------------------------------------------------------------------------
    // disabled by default
    // -------------------------------------------------------------------------

    public function test_nothing_is_logged_when_disabled(): void
    {
        $this->logger->logRequest('tools/list', 1);
        $this->logger->logResponse('tools/list', 1, 5.0);
        $this->logger->logToolCall('my_tool', ['a' => 1]);
        $this->logger->logToolResult('my_tool', 12.5);
        $this->logger->logError(-32601, 'Method not found', 1);
        $this->logger->logActivity('custom message');

        $this->assertTrue($this->driver->isEmpty());
    }

    // -------------------------------------------------------------------------
    // logRequest
    // -------------------------------------------------------------------------

    public function test_log_request_writes_method_and_id(): void
    {
        config(['mcp.logging.enabled' => true]);

        $this->logger->logRequest('tools/call', 42);

        $record = $this->driver->firstWithMessage('MCP request');
        $this->assertNotNull($record);
        $this->assertSame('tools/call', $record['context']['method']);
        $this->assertSame(42, $record['context']['id']);
    }

    // -------------------------------------------------------------------------
    // logResponse
    // -------------------------------------------------------------------------

    public function test_log_response_includes_duration_ms(): void
    {
        config(['mcp.logging.enabled' => true]);

        $this->logger->logResponse('tools/call', 1, 123.456);

        $record = $this->driver->firstWithMessage('MCP response');
        $this->assertNotNull($record);
        $this->assertArrayHasKey('duration_ms', $record['context']);
        $this->assertIsFloat($record['context']['duration_ms']);
    }

    // -------------------------------------------------------------------------
    // logToolCall
    // -------------------------------------------------------------------------

    public function test_log_tool_call_includes_tool_and_input(): void
    {
        config(['mcp.logging.enabled' => true]);

        $this->logger->logToolCall('my_tool', ['param' => 'value']);

        $record = $this->driver->firstWithMessage('MCP tool call');
        $this->assertNotNull($record);
        $this->assertSame('my_tool', $record['context']['tool']);
        $this->assertSame(['param' => 'value'], $record['context']['input']);
    }

    // -------------------------------------------------------------------------
    // logToolResult
    // -------------------------------------------------------------------------

    public function test_log_tool_result_includes_tool_and_duration(): void
    {
        config(['mcp.logging.enabled' => true]);

        $this->logger->logToolResult('my_tool', 99.9);

        $record = $this->driver->firstWithMessage('MCP tool result');
        $this->assertNotNull($record);
        $this->assertSame('my_tool', $record['context']['tool']);
        $this->assertArrayHasKey('duration_ms', $record['context']);
    }

    // -------------------------------------------------------------------------
    // logError
    // -------------------------------------------------------------------------

    public function test_log_error_always_uses_warning_level(): void
    {
        config(['mcp.logging.enabled' => true, 'mcp.logging.level' => 'info']);

        $this->logger->logError(-32601, 'Method not found', 5);

        $this->assertTrue($this->driver->hasRecord('warning', 'MCP error'));
        $record = $this->driver->firstWithMessage('MCP error');
        $this->assertSame(-32601, $record['context']['code']);
        $this->assertSame(5, $record['context']['id']);
    }

    public function test_log_error_silent_when_disabled(): void
    {
        $this->logger->logError(-32700, 'Parse error');

        $this->assertTrue($this->driver->isEmpty());
    }

    // -------------------------------------------------------------------------
    // custom level
    // -------------------------------------------------------------------------

    public function test_custom_level_is_used_for_non_error_logs(): void
    {
        config(['mcp.logging.enabled' => true, 'mcp.logging.level' => 'info']);

        $this->logger->logRequest('ping', 1);

        $this->assertTrue($this->driver->hasRecord('info', 'MCP request'));
    }

    // -------------------------------------------------------------------------
    // logActivity
    // -------------------------------------------------------------------------

    public function test_log_activity_is_logged_with_context(): void
    {
        config(['mcp.logging.enabled' => true]);

        $this->logger->logActivity('custom step', ['key' => 'val']);

        $record = $this->driver->firstWithMessage('custom step');
        $this->assertNotNull($record);
        $this->assertSame('val', $record['context']['key']);
    }

    // -------------------------------------------------------------------------
    // context() override — multi-tenant enrichment
    // -------------------------------------------------------------------------

    public function test_context_is_merged_into_every_log_entry(): void
    {
        config(['mcp.logging.enabled' => true]);

        $logger = new class($this->driver) extends McpLogger {
            protected function context(): array
            {
                return ['tenant_id' => 42];
            }
        };

        $logger->logRequest('tools/list', 1);

        $this->assertTrue($this->driver->hasContextKey('tenant_id', 42));
    }

    public function test_log_activity_merges_tenant_context(): void
    {
        config(['mcp.logging.enabled' => true]);

        $logger = new class($this->driver) extends McpLogger {
            protected function context(): array
            {
                return ['tenant_id' => 7];
            }
        };

        $logger->logActivity('step', ['key' => 'val']);

        $record = $this->driver->firstWithMessage('step');
        $this->assertNotNull($record);
        $this->assertSame('val', $record['context']['key']);
        $this->assertSame(7, $record['context']['tenant_id']);
    }

    public function test_log_error_merges_tenant_context(): void
    {
        config(['mcp.logging.enabled' => true]);

        $logger = new class($this->driver) extends McpLogger {
            protected function context(): array
            {
                return ['tenant_id' => 99];
            }
        };

        $logger->logError(-32603, 'Internal error', 3);

        $this->assertTrue($this->driver->hasRecord('warning', 'MCP error'));
        $this->assertTrue($this->driver->hasContextKey('tenant_id', 99));
    }
}
