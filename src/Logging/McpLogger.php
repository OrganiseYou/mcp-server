<?php

namespace OrganiseYou\McpServer\Logging;

use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;

class McpLogger
{
    public function __construct(private ?LoggerInterface $driver = null) {}

    public function logRequest(string $method, mixed $id): void
    {
        $this->log('MCP request', ['method' => $method, 'id' => $id]);
    }

    public function logResponse(string $method, mixed $id, float $durationMs): void
    {
        $this->log('MCP response', ['method' => $method, 'id' => $id, 'duration_ms' => round($durationMs, 2)]);
    }

    public function logToolCall(string $tool, array $input): void
    {
        $this->log('MCP tool call', ['tool' => $tool, 'input' => $this->redact($input)]);
    }

    public function logToolResult(string $tool, float $durationMs): void
    {
        $this->log('MCP tool result', ['tool' => $tool, 'duration_ms' => round($durationMs, 2)]);
    }

    public function logToolError(string $tool, string $reason, string $message): void
    {
        $this->log('MCP tool error', ['tool' => $tool, 'reason' => $reason, 'message' => $message]);
    }

    public function logError(int $code, string $message, mixed $id = null): void
    {
        if (! $this->enabled()) {
            return;
        }

        $context = array_merge(['code' => $code, 'message' => $message, 'id' => $id], $this->context());
        $this->resolveDriver()->warning('MCP error', $context);
    }

    public function logActivity(string $message, array $context = []): void
    {
        $this->log($message, $context);
    }

    protected function context(): array
    {
        return [];
    }

    /**
     * Apply the configured mcp.logging.redact callback to tool input.
     */
    private function redact(array $input): array
    {
        $redactor = config('mcp.logging.redact');

        if (is_string($redactor) && class_exists($redactor)) {
            $redactor = app($redactor);
        }

        return is_callable($redactor) ? $redactor($input) : $input;
    }

    private function log(string $message, array $context = []): void
    {
        if (! $this->enabled()) {
            return;
        }

        $merged = array_merge($context, $this->context());
        $level  = config('mcp.logging.level', 'debug');
        $this->resolveDriver()->log($level, $message, $merged);
    }

    private function resolveDriver(): LoggerInterface
    {
        if ($this->driver !== null) {
            return $this->driver;
        }

        $channel = config('mcp.logging.channel');

        return $channel ? Log::channel($channel) : app('log');
    }

    private function enabled(): bool
    {
        return (bool) config('mcp.logging.enabled', false);
    }
}
