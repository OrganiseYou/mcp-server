<?php

namespace OrganiseYou\McpServer\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OrganiseYou\McpServer\Transport\HttpTransport;

class McpController
{
    // The transport is method-injected: the router caches controller
    // instances, and a constructor-held transport would outlive its scope.
    public function __invoke(Request $request, HttpTransport $transport): JsonResponse
    {
        return $transport->handle($request);
    }
}
