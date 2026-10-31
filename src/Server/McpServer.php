<?php

namespace OrganiseYou\McpServer\Server;

use OrganiseYou\McpServer\Exceptions\ToolError;
use OrganiseYou\McpServer\Logging\McpLogger;
use OrganiseYou\McpServer\McpToolRegistry;
use OrganiseYou\McpServer\ToolContext;
use Throwable;

class McpServer
{
    /** Newest first; the first entry is offered when the client asks for an unknown version. */
    private const PROTOCOL_VERSIONS = ['2025-03-26', '2024-11-05'];

    public function __construct(
        private McpToolRegistry $registry,
        private ?McpLogger $logger = null,
    ) {}

    /**
     * Handle a raw JSON string and return a JSON string response.
     * Returns null for notifications (requests without id).
     */
    public function handle(string $raw): ?string
    {
        $start   = microtime(true);
        $request = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger?->logError(-32700, 'Parse error');
            return $this->error(null, -32700, 'Parse error');
        }

        $method = $request['method'] ?? 'unknown';

        // Notification — no response is sent, not even an error (JSON-RPC 2.0 §4.1).
        // Unknown notification methods are ignored.
        if (! array_key_exists('id', $request)) {
            return null;
        }

        $id = $request['id'];
        $this->logger?->logRequest($method, $id);

        try {
            $result = $this->dispatch($request);
            $this->logger?->logResponse($method, $id, (microtime(true) - $start) * 1000);
            return $this->success($id, $result);
        } catch (Throwable $e) {
            $code = (is_int($e->getCode()) && $e->getCode() !== 0) ? $e->getCode() : -32603;
            $this->logger?->logError($code, $e->getMessage(), $id);
            return $this->error($id, $code, $e->getMessage());
        }
    }

    /** @param array<string, mixed> $request */
    private function dispatch(array $request): mixed
    {
        $method = $request['method'] ?? null;
        $params = $request['params'] ?? [];

        return match ($method) {
            'initialize'  => $this->initialize($params),
            'ping'        => new \stdClass(),
            'tools/list'  => ['tools' => $this->registry->all()],
            'tools/call'  => $this->toolsCall($params, $request['id'] ?? null),
            default       => throw new \RuntimeException("Method not found: {$method}", -32601),
        };
    }

    /** @param array<string, mixed> $params */
    private function initialize(array $params): array
    {
        $requested = $params['protocolVersion'] ?? null;

        return [
            'protocolVersion' => in_array($requested, self::PROTOCOL_VERSIONS, true)
                ? $requested
                : self::PROTOCOL_VERSIONS[0],
            'capabilities'    => [
                'tools' => ['listChanged' => false],
            ],
            'serverInfo' => [
                'name'    => config('mcp.server.name', 'Laravel'),
                'version' => config('mcp.server.version', '1.0.0'),
            ],
        ];
    }

    /** @param array<string, mixed> $params */
    private function toolsCall(array $params, mixed $id): array
    {
        $name  = $params['name'] ?? null;
        $input = $params['arguments'] ?? [];

        if (! $name) {
            throw new \InvalidArgumentException('Missing required parameter: name', -32602);
        }

        $context = $this->baseContext();

        if ($context->requestId === null && (is_string($id) || is_int($id))) {
            $context = $context->withRequestId($id);
        }

        return $this->callTool($name, $input, $context);
    }

    /**
     * Call a tool in-process and return the same result array as tools/call.
     *
     * A ToolError becomes an isError: true result; any other exception is
     * rethrown (and becomes a JSON-RPC error when called through handle()).
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function callTool(string $name, array $input, ToolContext $context): array
    {
        if (! $this->registry->has($name)) {
            throw new \RuntimeException("Tool not found: {$name}", -32601);
        }

        $toolStart = microtime(true);
        $this->logger?->logToolCall($name, $input);

        try {
            $result = $this->registry->call($name, $input, $context);
        } catch (ToolError $e) {
            $this->logger?->logToolError($name, $e->reason, $e->getMessage());

            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => json_encode(['reason' => $e->reason, 'message' => $e->getMessage()]),
                    ],
                ],
                'isError' => true,
            ];
        }

        $this->logger?->logToolResult($name, (microtime(true) - $toolStart) * 1000);

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => is_string($result) ? $result : json_encode($result),
                ],
            ],
            'isError' => false,
        ];
    }

    /**
     * The host app supplies actor/scopes by binding ToolContext in the
     * container (scoped, so it is rebuilt per request).
     */
    private function baseContext(): ToolContext
    {
        return app()->bound(ToolContext::class) ? app(ToolContext::class) : new ToolContext();
    }

    private function success(mixed $id, mixed $result): string
    {
        return json_encode([
            'jsonrpc' => '2.0',
            'id'      => $id,
            'result'  => $result,
        ]);
    }

    private function error(mixed $id, int $code, string $message): string
    {
        return json_encode([
            'jsonrpc' => '2.0',
            'id'      => $id,
            'error'   => ['code' => $code, 'message' => $message],
        ]);
    }
}
