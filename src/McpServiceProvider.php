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

        // Scoped, so a long-lived worker (Octane, queue) starts every request
        // with a fresh registry and a server/transports bound to it.
        $this->app->scoped(McpToolRegistry::class, fn ($app) => $this->makeRegistry($app));
        $this->app->singleton(McpLogger::class, fn () => new McpLogger());
        $this->app->scoped(McpServer::class);
        $this->app->scoped(StdioTransport::class);
        $this->app->scoped(HttpTransport::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/mcp.php' => config_path('mcp.php'),
        ], 'mcp-config');

        if (config('mcp.transport.http', true)) {
            $this->loadHttpRoutes();
        }

        if ($this->app->runningInConsole()) {
            $this->commands([ServeCommand::class]);
        }
    }

    /**
     * Build a registry pre-filled with the class-based tools from config.
     */
    private function makeRegistry($app): McpToolRegistry
    {
        $registry = new McpToolRegistry();

        foreach ($app['config']->get('mcp.tools', []) as $toolClass) {
            $tool = $app->make($toolClass);

            if (! $tool instanceof McpTool) {
                throw new \InvalidArgumentException(
                    "Tool class [{$toolClass}] must implement " . McpTool::class
                );
            }

            $registry->register($tool);
        }

        return $registry;
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
