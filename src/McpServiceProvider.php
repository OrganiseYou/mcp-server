<?php

namespace OrganiseYou\McpServer;

use Illuminate\Support\ServiceProvider;
use OrganiseYou\McpServer\Commands\ServeCommand;
use OrganiseYou\McpServer\Contracts\McpTool;
use OrganiseYou\McpServer\Http\McpController;
use OrganiseYou\McpServer\Logging\McpLogger;
use OrganiseYou\McpServer\Server\McpServer;
use OrganiseYou\McpServer\Transport\HttpTransport;
use OrganiseYou\McpServer\Transport\StdioTransport;

class McpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/mcp.php', 'mcp');

        $this->app->singleton(McpToolRegistry::class);
        $this->app->singleton(McpLogger::class, fn () => new McpLogger());
        $this->app->singleton(McpServer::class);
        $this->app->singleton(StdioTransport::class);
        $this->app->singleton(HttpTransport::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/mcp.php' => config_path('mcp.php'),
        ], 'mcp-config');

        $this->registerClassBasedTools();

        if (config('mcp.transport.http', true)) {
            $this->loadHttpRoutes();
        }

        if ($this->app->runningInConsole()) {
            $this->commands([ServeCommand::class]);
        }
    }

    private function registerClassBasedTools(): void
    {
        $registry = $this->app->make(McpToolRegistry::class);

        foreach (config('mcp.tools', []) as $toolClass) {
            $tool = $this->app->make($toolClass);

            if (! $tool instanceof McpTool) {
                throw new \InvalidArgumentException(
                    "Tool class [{$toolClass}] must implement " . McpTool::class
                );
            }

            $registry->register($tool);
        }
    }

    private function loadHttpRoutes(): void
    {
        $path       = config('mcp.transport.http_path', '/mcp');
        $middleware = config('mcp.transport.http_middleware', []);

        $this->app['router']
            ->post($path, McpController::class)
            ->middleware($middleware);
    }
}
