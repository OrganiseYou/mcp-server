<?php

namespace OrganiseYou\McpServer\Tests;

use OrganiseYou\McpServer\Contracts\McpTool;
use OrganiseYou\McpServer\Facades\Mcp;
use OrganiseYou\McpServer\McpToolRegistry;
use OrganiseYou\McpServer\Tool;
use RuntimeException;

class McpToolRegistryTest extends TestCase
{
    private McpToolRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new McpToolRegistry();
    }

    public function test_registers_and_lists_fluent_tool(): void
    {
        $tool = Tool::make('echo')
            ->description('Echoes input')
            ->inputSchema([
                'type'       => 'object',
                'properties' => ['msg' => ['type' => 'string']],
                'required'   => ['msg'],
            ])
            ->handle(fn (array $input) => $input['msg'] ?? '');

        // handle() auto-registers via app(), so use the registry directly here
        $this->registry->register($tool);

        $this->assertTrue($this->registry->has('echo'));

        $definitions = $this->registry->all();
        $this->assertCount(1, $definitions);
        $this->assertSame('echo', $definitions[0]['name']);
        $this->assertSame('Echoes input', $definitions[0]['description']);
    }

    public function test_registers_and_lists_class_based_tool(): void
    {
        $tool = new class implements McpTool {
            public function name(): string        { return 'greet'; }
            public function description(): string { return 'Says hello'; }
            public function inputSchema(): array  { return ['type' => 'object', 'properties' => []]; }
            public function __invoke(array $input): mixed { return 'hello'; }
        };

        $this->registry->register($tool);

        $this->assertTrue($this->registry->has('greet'));
        $definitions = $this->registry->all();
        $this->assertSame('greet', $definitions[0]['name']);
    }

    public function test_calls_fluent_tool(): void
    {
        $tool = Tool::make('add')
            ->description('Adds two numbers')
            ->inputSchema(['type' => 'object', 'properties' => []])
            ->handle(fn (array $input) => $input['a'] + $input['b']);

        $this->registry->register($tool);

        $result = $this->registry->call('add', ['a' => 3, 'b' => 4]);
        $this->assertSame(7, $result);
    }

    public function test_calls_class_based_tool(): void
    {
        $tool = new class implements McpTool {
            public function name(): string        { return 'double'; }
            public function description(): string { return 'Doubles a number'; }
            public function inputSchema(): array  { return ['type' => 'object', 'properties' => []]; }
            public function __invoke(array $input): mixed { return $input['n'] * 2; }
        };

        $this->registry->register($tool);

        $this->assertSame(10, $this->registry->call('double', ['n' => 5]));
    }

    public function test_has_returns_false_for_unknown_tool(): void
    {
        $this->assertFalse($this->registry->has('unknown'));
    }

    public function test_call_throws_for_unknown_tool(): void
    {
        $this->expectException(RuntimeException::class);
        $this->registry->call('missing', []);
    }

    public function test_later_registration_overwrites_same_name(): void
    {
        $first = Tool::make('ping')
            ->description('First')
            ->inputSchema(['type' => 'object', 'properties' => []])
            ->handle(fn ($i) => 'first');

        $second = Tool::make('ping')
            ->description('Second')
            ->inputSchema(['type' => 'object', 'properties' => []])
            ->handle(fn ($i) => 'second');

        $this->registry->register($first);
        $this->registry->register($second);

        $this->assertSame('second', $this->registry->call('ping', []));
    }
}
