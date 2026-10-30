<?php

namespace OrganiseYou\McpServer;

use OrganiseYou\McpServer\Contracts\McpTool;
use ReflectionMethod;
use RuntimeException;

class McpToolRegistry
{
    /** @var array<string, Tool|McpTool> */
    private array $tools = [];

    /** @var array<string, bool> Class tools whose __invoke() takes a ToolContext */
    private array $acceptsContext = [];

    public function tool(string $name): Tool
    {
        return new Tool($name);
    }

    public function register(Tool|McpTool $tool): void
    {
        $name = $tool instanceof Tool ? $tool->getName() : $tool->name();
        $this->tools[$name] = $tool;

        if ($tool instanceof McpTool) {
            $this->acceptsContext[$name] = Tool::acceptsContext(new ReflectionMethod($tool, '__invoke'));
        } else {
            unset($this->acceptsContext[$name]);
        }
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

            $definition = [
                'name'        => $tool->name(),
                'description' => $tool->description(),
                'inputSchema' => $tool->inputSchema(),
            ];

            $annotations = method_exists($tool, 'annotations') ? $tool->annotations() : [];

            if ($annotations !== []) {
                $definition['annotations'] = $annotations;
            }

            return $definition;
        }, $this->tools));
    }

    /** @param array<string, mixed> $input */
    public function call(string $name, array $input, ?ToolContext $context = null): mixed
    {
        if (! isset($this->tools[$name])) {
            throw new RuntimeException("Tool [{$name}] not found.", -32601);
        }

        $tool = $this->tools[$name];

        if ($tool instanceof Tool) {
            return $tool->call($input, $context);
        }

        return $this->acceptsContext[$name]
            ? $tool($input, $context ?? new ToolContext())
            : $tool($input);
    }

    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }
}
