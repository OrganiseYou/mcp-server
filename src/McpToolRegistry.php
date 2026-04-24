<?php

namespace OrganiseYou\McpServer;

use OrganiseYou\McpServer\Contracts\McpTool;
use RuntimeException;

class McpToolRegistry
{
    /** @var array<string, Tool|McpTool> */
    private array $tools = [];

    public function tool(string $name): Tool
    {
        return new Tool($name);
    }

    public function register(Tool|McpTool $tool): void
    {
        $name = $tool instanceof Tool ? $tool->getName() : $tool->name();
        $this->tools[$name] = $tool;
    }

    /**
     * All tool definitions for tools/list.
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return array_values(array_map(function (Tool|McpTool $tool) {
            if ($tool instanceof Tool) {
                return $tool->toDefinition();
            }

            return [
                'name'        => $tool->name(),
                'description' => $tool->description(),
                'inputSchema' => $tool->inputSchema(),
            ];
        }, $this->tools));
    }

    /** @param array<string, mixed> $input */
    public function call(string $name, array $input): mixed
    {
        if (! isset($this->tools[$name])) {
            throw new RuntimeException("Tool [{$name}] not found.", -32601);
        }

        $tool = $this->tools[$name];

        return $tool instanceof Tool ? $tool->call($input) : $tool($input);
    }

    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }
}
