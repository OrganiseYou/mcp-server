<?php

namespace OrganiseYou\McpServer;

/**
 * Per-call context handed to tools that declare a second parameter.
 *
 * The package never interprets `actor` or `attributes`; they are whatever
 * the host application puts in (a user model, a tenant id, a trace id, …).
 */
final readonly class ToolContext
{
    /**
     * @param  array<int, string>  $scopes
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public string|int|null $requestId = null,
        public mixed $actor = null,
        public array $scopes = [],
        public array $attributes = [],
    ) {}

    public function withRequestId(string|int|null $requestId): self
    {
        return new self($requestId, $this->actor, $this->scopes, $this->attributes);
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes, true);
    }
}
