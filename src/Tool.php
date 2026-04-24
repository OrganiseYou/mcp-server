<?php

namespace OrganiseYou\McpServer;

use InvalidArgumentException;

class Tool
{
    private string $name;
    private string $description = '';
    /** @var array<string, mixed> */
    private array $schema = ['type' => 'object', 'properties' => []];
    /** @var callable|null */
    private $handler = null;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public static function make(string $name): self
    {
        return new self($name);
    }

    public function description(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    /** @param array<string, mixed> $schema */
    public function inputSchema(array $schema): self
    {
        $this->schema = $schema;
        return $this;
    }

    /**
     * Set the handler and auto-register with the registry.
     */
    public function handle(callable $handler): self
    {
        $this->handler = $handler;
        app(McpToolRegistry::class)->register($this);
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    /** @return array<string, mixed> */
    public function getInputSchema(): array
    {
        return $this->schema;
    }

    /** @return array<string, mixed> */
    public function toDefinition(): array
    {
        return [
            'name'        => $this->name,
            'description' => $this->description,
            'inputSchema' => $this->schema,
        ];
    }

    /** @param array<string, mixed> $input */
    public function call(array $input): mixed
    {
        if ($this->handler === null) {
            throw new InvalidArgumentException("Tool [{$this->name}] has no handler.");
        }

        return ($this->handler)($input);
    }
}
