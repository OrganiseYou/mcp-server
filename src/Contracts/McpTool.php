<?php

namespace OrganiseYou\McpServer\Contracts;

interface McpTool
{
    public function name(): string;

    public function description(): string;

    /**
     * JSON Schema object describing the tool's input parameters.
     *
     * @return array<string, mixed>
     */
    public function inputSchema(): array;

    /**
     * Execute the tool with the given validated input.
     *
     * Implementations may add an optional second parameter
     * `?\OrganiseYou\McpServer\ToolContext $context = null` to receive the
     * per-call context, and may define `annotations(): array` to declare
     * readOnlyHint, destructiveHint, idempotentHint and openWorldHint.
     *
     * @param  array<string, mixed>  $input
     */
    public function __invoke(array $input): mixed;
}
