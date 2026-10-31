<?php

namespace OrganiseYou\McpServer\Transport;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OrganiseYou\McpServer\Server\McpServer;

class HttpTransport
{
    public function __construct(private McpServer $server) {}

    public function handle(Request $request): JsonResponse
    {
        $raw = $request->getContent();

        $decoded = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return response()->json([
                'jsonrpc' => '2.0',
                'id'      => null,
                'error'   => ['code' => -32700, 'message' => 'Parse error'],
            ], 200);
        }

        // Batch request
        if (array_is_list($decoded)) {
            $responses = [];
            foreach ($decoded as $item) {
                $response = $this->server->handle(json_encode($item));
                if ($response !== null) {
                    $responses[] = json_decode($response, true);
                }
            }
            if ($responses === []) {
                return $this->accepted();
            }
            return response()->json($responses);
        }

        // Single request
        $response = $this->server->handle($raw);

        if ($response === null) {
            return $this->accepted();
        }

        return response()->json(json_decode($response, true));
    }

    /**
     * Notifications are acknowledged with 202 Accepted and no body
     * (MCP Streamable HTTP transport).
     */
    private function accepted(): JsonResponse
    {
        return new JsonResponse('', 202, [], 0, true);
    }
}
