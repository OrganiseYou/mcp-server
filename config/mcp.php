<?php

return [

    /*
    |--------------------------------------------------------------------------
    | MCP Server Info
    |--------------------------------------------------------------------------
    */
    'server' => [
        'name'    => env('APP_NAME', 'Laravel'),
        'version' => '1.0.0',
    ],

    /*
    |--------------------------------------------------------------------------
    | Transport
    |--------------------------------------------------------------------------
    | http  - expose a POST /mcp endpoint (requires routes to be loaded)
    | stdio - use `php artisan mcp:serve` for stdio transport
    */
    'transport' => [
        'http'      => true,
        'http_path' => '/mcp',
        'http_middleware' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Class-based Tools
    |--------------------------------------------------------------------------
    | Register tool classes that implement OrganiseYou\McpServer\Contracts\McpTool.
    | They will be resolved from the container, so you can type-hint dependencies.
    */
    'tools' => [
        // App\Mcp\Tools\MyTool::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    | Off by default. Set MCP_LOGGING_ENABLED=true to start logging requests,
    | tool calls, and errors through Laravel's Log facade.
    |
    | channel - null uses the application's default log channel
    | level   - any PSR-3 level: debug, info, warning, error, …
    | Errors are always logged at 'warning' regardless of 'level'.
    */
    'logging' => [
        'enabled' => env('MCP_LOGGING_ENABLED', false),
        'channel' => env('MCP_LOG_CHANNEL', null),
        'level'   => env('MCP_LOG_LEVEL', 'debug'),
    ],

];
