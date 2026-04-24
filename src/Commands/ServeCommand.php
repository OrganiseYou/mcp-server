<?php

namespace OrganiseYou\McpServer\Commands;

use Illuminate\Console\Command;
use OrganiseYou\McpServer\Transport\StdioTransport;

class ServeCommand extends Command
{
    protected $signature   = 'mcp:serve';
    protected $description = 'Start the MCP server over stdio (for use with Claude Desktop and other MCP clients)';

    public function handle(StdioTransport $transport): int
    {
        $this->getOutput()->getErrorOutput()->writeln(
            sprintf('[MCP] Server started (%s)', config('mcp.server.name', 'Laravel'))
        );

        $transport->run();

        return self::SUCCESS;
    }
}
