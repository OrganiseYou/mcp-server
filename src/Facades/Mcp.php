<?php

namespace OrganiseYou\McpServer\Facades;

use Illuminate\Support\Facades\Facade;
use OrganiseYou\McpServer\McpToolRegistry;
use OrganiseYou\McpServer\Tool;

/**
 * @method static Tool tool(string $name)
 * @method static void register(\OrganiseYou\McpServer\Contracts\McpTool|\OrganiseYou\McpServer\Tool $tool)
 * @method static array all()
 *
 * @see McpToolRegistry
 */
class Mcp extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return McpToolRegistry::class;
    }
}
