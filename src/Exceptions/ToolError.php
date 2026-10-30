<?php

namespace OrganiseYou\McpServer\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Throw from a tool to report an expected failure ("forbidden", "not_found", …).
 *
 * The server turns it into a tools/call result with isError: true instead of
 * a JSON-RPC error, so the client can tell a refusal from a server fault.
 */
final class ToolError extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        string $message = '',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
