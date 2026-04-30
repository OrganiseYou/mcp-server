# Laravel MCP

A [Model Context Protocol (MCP)](https://modelcontextprotocol.io) server for Laravel built around **runtime tool registration**. Tools are registered programmatically at request time rather than declared upfront as static classes, making this package the right choice when your tool surface is dynamic — multi-tenant platforms, schema-driven APIs, feature-flagged environments, or anything else where you don't know your tools at boot time.

If your tools are static and known at boot time, [laravel/mcp](https://laravel.com/ai/mcp) (the official first-party package) is likely the better fit.

## Requirements

- PHP 8.2+
- Laravel 11+

## Installation

```bash

composer require organiseyou/mcp-server
```

The service provider and `Mcp` facade are registered automatically via Laravel's package discovery.

Publish the config file:

```bash
php artisan vendor:publish --tag=mcp-config
```

## Configuration

`config/mcp.php`:

```php
return [
    'server' => [
        'name'    => env('APP_NAME', 'Laravel'),
        'version' => '1.0.0',
    ],

    'transport' => [
        'http'            => true,   // expose POST /mcp
        'http_path'       => '/mcp',
        'http_middleware' => [],     // e.g. ['auth:sanctum']
    ],

    'tools' => [
        // App\Mcp\Tools\StaticTool::class,
    ],
];
```

## Registering Tools

### Inline via the Mcp facade (dynamic)

Register tools anywhere that runs before the request is handled — a service provider, middleware, or a tenant boot hook:

```php
use OrganiseYou\McpServer\Facades\Mcp;

// Single tool
Mcp::tool('add_numbers')
    ->description('Add two numbers together and return the sum.')
    ->inputSchema([
        'type'       => 'object',
        'properties' => [
            'a' => ['type' => 'number', 'description' => 'First operand.'],
            'b' => ['type' => 'number', 'description' => 'Second operand.'],
        ],
        'required' => ['a', 'b'],
    ])
    ->handle(fn (array $input) => $input['a'] + $input['b']);
```

A multi-tenant example where tools are generated from tenant configuration:

```php
// In a tenant boot hook or middleware
foreach ($tenant->enabledDataSources() as $source) {
    Mcp::tool("query_{$source->slug}")
        ->description("Query the {$source->name} dataset. " . $source->description)
        ->inputSchema($source->buildInputSchema())
        ->handle(fn (array $input) => $source->query($input));
}
```

### Class-based tools (static)

For tools that are the same across all tenants or contexts, implement `OrganiseYou\McpServer\Contracts\McpTool` and register the class in `config/mcp.php`:

```php
<?php

namespace App\Mcp\Tools;

use App\Models\Order;
use OrganiseYou\McpServer\Contracts\McpTool;

class GetOrderTool implements McpTool
{
    public function __construct(private OrderRepository $orders) {}

    public function name(): string
    {
        return 'get_order';
    }

    public function description(): string
    {
        return 'Retrieve an order by its ID, including line items and shipping status.';
    }

    public function inputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'order_id' => [
                    'type'        => 'integer',
                    'description' => 'The ID of the order to retrieve.',
                ],
            ],
            'required' => ['order_id'],
        ];
    }

    public function __invoke(array $input): mixed
    {
        $order = Order::with('items')->findOrFail($input['order_id']);

        return [
            'id'     => $order->id,
            'status' => $order->status,
            'total'  => $order->total,
            'items'  => $order->items->map(fn ($i) => [
                'sku'      => $i->sku,
                'quantity' => $i->quantity,
            ])->all(),
        ];
    }
}
```

```php
// config/mcp.php
'tools' => [
    App\Mcp\Tools\GetOrderTool::class,
],
```

Tools are resolved from the Laravel container, so constructor dependencies are injected automatically.

## Logging

Logging is **off by default**. Enable it in `.env`:

```env
MCP_LOGGING_ENABLED=true
MCP_LOG_CHANNEL=stack   # optional — defaults to your app's default channel
MCP_LOG_LEVEL=debug     # optional — defaults to debug
```

Or publish the config (`php artisan vendor:publish --tag=mcp-config`) and set:

```php
'logging' => [
    'enabled' => env('MCP_LOGGING_ENABLED', false),
    'channel' => env('MCP_LOG_CHANNEL', null),
    'level'   => env('MCP_LOG_LEVEL', 'debug'),
],
```

When enabled, the following is logged automatically:

| Event | Message | Context keys |
|---|---|---|
| Request received | `MCP request` | `method`, `id` |
| Request completed | `MCP response` | `method`, `id`, `duration_ms` |
| Tool invoked | `MCP tool call` | `tool`, `input` |
| Tool returned | `MCP tool result` | `tool`, `duration_ms` |
| Any error | `MCP error` | `code`, `message`, `id` |

Errors always log at `warning` level regardless of `MCP_LOG_LEVEL`.

### Logging inside tools

**Class-based tools** — inject `McpLogger` and call `logActivity()`:

```php
use OrganiseYou\McpServer\Logging\McpLogger;

class GetOrderTool implements McpTool
{
    public function __construct(
        private OrderRepository $orders,
        private McpLogger $logger,
    ) {}

    public function __invoke(array $input): mixed
    {
        $this->logger->logActivity('Fetching order', ['order_id' => $input['order_id']]);

        return $this->orders->find($input['order_id']);
    }
}
```

`logActivity()` respects the same channel, level, and any extended context (e.g. tenant ID) as the rest of the logger.

**Closure-based tools** — use Laravel's `Log` facade directly:

```php
use Illuminate\Support\Facades\Log;

Mcp::tool('ping')
    ->description('Health check')
    ->inputSchema(['type' => 'object', 'properties' => []])
    ->handle(function (array $input) {
        Log::debug('ping tool called', $input);
        return 'pong';
    });
```

### Multi-tenant: adding tenant context to every log entry

Extend `McpLogger`, override `context()`, and rebind the singleton:

```php
// app/Mcp/TenantMcpLogger.php
namespace App\Mcp;

use OrganiseYou\McpServer\Logging\McpLogger;

class TenantMcpLogger extends McpLogger
{
    protected function context(): array
    {
        return ['tenant_id' => tenant()?->id];
    }
}
```

```php
// AppServiceProvider::register()
$this->app->singleton(\OrganiseYou\McpServer\Logging\McpLogger::class, \App\Mcp\TenantMcpLogger::class);
```

Every log entry — from the server, from tool calls, and from `logActivity()` inside your tools — will automatically include `tenant_id`. No changes required to individual tools.

## Transports

### HTTP (default)

When `mcp.transport.http` is `true`, the package registers a `POST /mcp` route. Any MCP client that supports HTTP can point to `https://your-app.test/mcp`.

To secure the endpoint, add middleware in `config/mcp.php`:

```php
'http_middleware' => ['auth:sanctum'],
```

### Stdio (Claude Desktop / local clients)

Run the artisan command to start an stdio MCP server:

```bash
php artisan mcp:serve
```

Add it to your `claude_desktop_config.json`:

```json
{
    "mcpServers": {
        "my-laravel-app": {
            "command": "php",
            "args": ["/path/to/your/app/artisan", "mcp:serve"]
        }
    }
}
```

## Protocol

The server speaks [JSON-RPC 2.0](https://www.jsonrpc.org/specification) and implements the MCP `2024-11-05` protocol version. Supported methods:

| Method | Description |
|---|---|
| `initialize` | Handshake, returns server info and capabilities |
| `ping` | Health check |
| `tools/list` | List all registered tools |
| `tools/call` | Invoke a tool by name |

### Example HTTP request

```bash
curl -s -X POST https://your-app.test/mcp \
  -H 'Content-Type: application/json' \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/list","params":{}}'
```

```json
{
    "jsonrpc": "2.0",
    "id": 1,
    "result": {
        "tools": [
            {
                "name": "get_order",
                "description": "Retrieve an order by its ID, including line items and shipping status.",
                "inputSchema": {
                    "type": "object",
                    "properties": {
                        "order_id": { "type": "integer", "description": "The ID of the order to retrieve." }
                    },
                    "required": ["order_id"]
                }
            }
        ]
    }
}
```

Calling a tool:

```bash
curl -s -X POST https://your-app.test/mcp \
  -H 'Content-Type: application/json' \
  -d '{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"get_order","arguments":{"order_id":42}}}'
```

```json
{
    "jsonrpc": "2.0",
    "id": 2,
    "result": {
        "content": [
            {
                "type": "text",
                "text": "{\"id\":42,\"status\":\"shipped\",\"total\":99.95,\"items\":[{\"sku\":\"WIDGET-1\",\"quantity\":2}]}"
            }
        ],
        "isError": false
    }
}
```

Batch requests are also supported — send an array of JSON-RPC objects and receive an array of responses.

## Testing

```bash
docker run --rm -v $(pwd):/app -w /app composer install
./vendor/bin/phpunit
```

Or with Docker (no local PHP required):

```bash
docker run --rm -v $(pwd):/app -w /app php:8.4-cli php vendor/bin/phpunit
docker run --rm -v $(pwd):/app -w /app php:8.3-cli php vendor/bin/phpunit
```

## License

MIT
