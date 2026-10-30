<?php

namespace OrganiseYou\McpServer;

use Closure;
use InvalidArgumentException;
use ReflectionFunction;
use ReflectionFunctionAbstract;

class Tool
{
    private string $name;
    private string $description = '';
    /** @var array<string, mixed> */
    private array $schema = ['type' => 'object', 'properties' => []];
    /** @var array<string, bool> */
    private array $annotations = [];
    /** @var callable|null */
    private $handler = null;
    private bool $handlerAcceptsContext = false;

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
     * Tool annotations for tools/list: readOnlyHint, destructiveHint,
     * idempotentHint, openWorldHint. Omitted from the definition when empty.
     *
     * @param array<string, bool> $annotations
     */
    public function annotations(array $annotations): self
    {
        $this->annotations = $annotations;
        return $this;
    }

    /**
     * Set the handler and auto-register with the registry.
     *
     * The handler receives a ToolContext as its second argument only when it
     * declares a second parameter.
     */
    public function handle(callable $handler): self
    {
        $this->handler = $handler;
        $this->handlerAcceptsContext = self::acceptsContext(new ReflectionFunction(Closure::fromCallable($handler)));
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

    /** @return array<string, bool> */
    public function getAnnotations(): array
    {
        return $this->annotations;
    }

    /** @return array<string, mixed> */
    public function toDefinition(): array
    {
        $definition = [
            'name'        => $this->name,
            'description' => $this->description,
            'inputSchema' => $this->schema,
        ];

        if ($this->annotations !== []) {
            $definition['annotations'] = $this->annotations;
        }

        return $definition;
    }

    /** @param array<string, mixed> $input */
    public function call(array $input, ?ToolContext $context = null): mixed
    {
        if ($this->handler === null) {
            throw new InvalidArgumentException("Tool [{$this->name}] has no handler.");
        }

        return $this->handlerAcceptsContext
            ? ($this->handler)($input, $context ?? new ToolContext())
            : ($this->handler)($input);
    }

    /**
     * Whether a handler declares a second parameter (or a variadic one).
     *
     * @internal
     */
    public static function acceptsContext(ReflectionFunctionAbstract $handler): bool
    {
        if ($handler->getNumberOfParameters() >= 2) {
            return true;
        }

        return $handler->isVariadic();
    }
}
