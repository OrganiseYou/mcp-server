<?php

namespace OrganiseYou\McpServer\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OrganiseYou\McpServer\Transport\HttpTransport;

class McpController
{
    public function __construct(private HttpTransport $transport) {}

    public function __invoke(Request $request): JsonResponse
    {
        return $this->transport->handle($request);
    }
}
