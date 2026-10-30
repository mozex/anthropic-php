<?php

declare(strict_types=1);

namespace Anthropic\ValueObjects;

/**
 * What a client sends with every request, read back from it: never its key or token.
 */
class ClientSettings
{
    /**
     * @param  list<string>  $betas
     */
    public function __construct(
        public readonly string $baseUri,
        public readonly string $apiVersion,
        public readonly array $betas,
        public readonly ?string $workspaceId,
        public readonly int $maxRetries,
        public readonly ?float $timeout,
        public readonly ?float $connectTimeout,
    ) {}

    /**
     * @return array{base_uri: string, api_version: string, betas: list<string>, workspace_id: ?string, max_retries: int, timeout: ?float, connect_timeout: ?float}
     */
    public function toArray(): array
    {
        return [
            'base_uri' => $this->baseUri,
            'api_version' => $this->apiVersion,
            'betas' => $this->betas,
            'workspace_id' => $this->workspaceId,
            'max_retries' => $this->maxRetries,
            'timeout' => $this->timeout,
            'connect_timeout' => $this->connectTimeout,
        ];
    }
}
