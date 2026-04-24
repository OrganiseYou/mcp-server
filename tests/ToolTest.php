<?php

namespace OrganiseYou\McpServer\Tests;

use InvalidArgumentException;
use OrganiseYou\McpServer\Tool;

class ToolTest extends TestCase
{
    public function test_make_sets_name(): void
    {
        $tool = Tool::make('my_tool');
        $this->assertSame('my_tool', $tool->getName());
    }

    public function test_description_is_chainable(): void
    {
        $tool = Tool::make('t')->description('A description');
        $this->assertSame('A description', $tool->getDescription());
    }

    public function test_input_schema_is_chainable(): void
    {
        $schema = ['type' => 'object', 'properties' => ['x' => ['type' => 'string']]];
        $tool   = Tool::make('t')->inputSchema($schema);
        $this->assertSame($schema, $tool->getInputSchema());
    }

    public function test_to_definition_returns_correct_shape(): void
    {
        $schema = ['type' => 'object', 'properties' => []];

        $tool = Tool::make('greet')
            ->description('Says hello')
            ->inputSchema($schema);

        $definition = $tool->toDefinition();

        $this->assertSame('greet', $definition['name']);
        $this->assertSame('Says hello', $definition['description']);
        $this->assertSame($schema, $definition['inputSchema']);
    }

    public function test_call_invokes_handler_with_input(): void
    {
        $tool = Tool::make('add')
            ->description('')
            ->inputSchema(['type' => 'object', 'properties' => []])
            ->handle(fn (array $input) => $input['a'] + $input['b']);

        $this->assertSame(9, $tool->call(['a' => 4, 'b' => 5]));
    }

    public function test_call_throws_when_no_handler_set(): void
    {
        $tool = Tool::make('no_handler')
            ->description('')
            ->inputSchema(['type' => 'object', 'properties' => []]);

        $this->expectException(InvalidArgumentException::class);
        $tool->call([]);
    }

    public function test_default_schema_is_empty_object(): void
    {
        $tool = Tool::make('bare');
        $this->assertSame(['type' => 'object', 'properties' => []], $tool->getInputSchema());
    }
}
