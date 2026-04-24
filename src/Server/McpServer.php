<?php

namespace OrganiseYou\McpServer\Server;

use OrganiseYou\McpServer\McpToolRegistry;
use Throwable;

class McpServer
{
    private const PROTOCOL_VERSION = '2024-11-05';

    public function __construct(private McpToolRegistry $registry) {}

    /**
     * Handle a raw JSON string and return a JSON string response.
     * Returns null for notifications (requests without id).
     */
    public function handle(string $raw): ?string
    {
        $request = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->error(null, -32700, 'Parse error');
        }

        // Notification — no id, no response expected
        if (! array_key_exists('id', $request)) {
            $this->dispatch($request);
            return null;
        }

        $id = $request['id'];

        try {
            $result = $this->dispatch($request);
            return $this->success($id, $result);
        } catch (Throwable $e) {
            $code    = (is_int($e->getCode()) && $e->getCode() !== 0) ? $e->getCode() : -32603;
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
            'tools/call'  => $this->toolsCall($params),
            default       => throw new \RuntimeException("Method not found: {$method}", -32601),
        };
    }

    /** @param array<string, mixed> $params */
    private function initialize(array $params): array
    {
        return [
            'protocolVersion' => self::PROTOCOL_VERSION,
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
    private function toolsCall(array $params): array
    {
        $name  = $params['name'] ?? null;
        $input = $params['arguments'] ?? [];

        if (! $name) {
            throw new \InvalidArgumentException('Missing required parameter: name', -32602);
        }

        if (! $this->registry->has($name)) {
            throw new \RuntimeException("Tool not found: {$name}", -32601);
        }

        $result = $this->registry->call($name, $input);

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
