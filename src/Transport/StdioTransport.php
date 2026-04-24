<?php

namespace OrganiseYou\McpServer\Transport;

use OrganiseYou\McpServer\Server\McpServer;

class StdioTransport
{
    public function __construct(private McpServer $server) {}

    public function run(): void
    {
        // Disable output buffering so responses are sent immediately
        if (ob_get_level()) {
            ob_end_clean();
        }

        while (($line = fgets(STDIN)) !== false) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $response = $this->server->handle($line);

            if ($response !== null) {
                fwrite(STDOUT, $response . "\n");
                fflush(STDOUT);
            }
        }
    }
}
