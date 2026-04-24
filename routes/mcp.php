<?php

use OrganiseYou\McpServer\Http\McpController;

/*
 * This file is loaded automatically by McpServiceProvider when
 * mcp.transport.http is true. You do not need to include it manually.
 */
Route::post(config('mcp.transport.http_path', '/mcp'), McpController::class);
