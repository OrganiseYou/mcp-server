# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## [1.1.0] - Unreleased

### Added

- `ToolContext` (`requestId`, `actor`, `scopes`, `attributes`). A closure or class tool gets it as a second argument only when it declares one. Bind `ToolContext` in the container to supply actor and scopes.
- `ToolError`. Throwing it from a tool returns a `tools/call` result with `isError: true` and `{"reason", "message"}`, instead of a JSON-RPC error.
- Tool annotations: `Tool::annotations()` and an optional `annotations()` method on class tools. They appear in `tools/list` only when set.
- `McpServer::callTool()` calls a tool in-process and returns the same array as `tools/call`.
- `mcp.logging.redact` sets a callback that filters tool input before it is logged.
- Protocol version negotiation. The server supports `2025-03-26` and `2024-11-05`, echoes the client's version when it supports it, and otherwise offers `2025-03-26`.

### Changed

- `McpToolRegistry`, `McpServer`, `HttpTransport` and `StdioTransport` are bound with `scoped()` instead of `singleton()`, so long-lived workers don't keep the first request's tools. Class tools from `mcp.tools` are registered each time a registry is built.
- `McpController` receives `HttpTransport` by method injection, because the router caches controller instances.
- `initialize` without a `protocolVersion` now answers `2025-03-26` (was `2024-11-05`).

### Fixed

- Notifications (messages without an `id`, e.g. `notifications/initialized`) are no longer dispatched as requests and no longer throw `Method not found` (issue #3). `handle()` returns `null` for any notification, known or unknown, per JSON-RPC 2.0. The HTTP transport now answers `202 Accepted` with no body for a notification and for a batch made only of notifications (was `204` / a JSON `[]` body).

## [1.0.0]

- Initial release.
