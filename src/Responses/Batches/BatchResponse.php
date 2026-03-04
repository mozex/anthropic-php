<?php

declare(strict_types=1);

namespace Anthropic\Responses\Batches;

use Anthropic\Contracts\ResponseContract;
use Anthropic\Contracts\ResponseHasMetaInformationContract;
use Anthropic\Responses\Concerns\ArrayAccessible;
use Anthropic\Responses\Concerns\HasMetaInformation;
use Anthropic\Responses\Meta\MetaInformation;
use Anthropic\Testing\Responses\Concerns\Batches\Fakeable;

/**
 * @implements ResponseContract<array{id: string, type: string, processing_status: string, request_counts: array{processing: int, succeeded: int, errored: int, canceled: int, expired: int}, created_at: string, expires_at: string, ended_at: ?string, cancel_initiated_at: ?string, archived_at: ?string, results_url: ?string}>
 */
final class BatchResponse implements ResponseContract, ResponseHasMetaInformationContract
{
    /**
     * @use ArrayAccessible<array{id: string, type: string, processing_status: string, request_counts: array{processing: int, succeeded: int, errored: int, canceled: int, expired: int}, created_at: string, expires_at: string, ended_at: ?string, cancel_initiated_at: ?string, archived_at: ?string, results_url: ?string}>
     */
    use ArrayAccessible;

    use Fakeable;
    use HasMetaInformation;

    private function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly string $processingStatus,
        public readonly BatchResponseRequestCounts $requestCounts,
        public readonly string $createdAt,
        public readonly string $expiresAt,
        public readonly ?string $endedAt,
        public readonly ?string $cancelInitiatedAt,
        public readonly ?string $archivedAt,
        public readonly ?string $resultsUrl,
        private readonly MetaInformation $meta,
    ) {}

    /**
     * Acts as static factory, and returns a new Response instance.
     *
     * @param  array{id: string, type: string, processing_status: string, request_counts: array{processing: int, succeeded: int, errored: int, canceled: int, expired: int}, created_at: string, expires_at: string, ended_at: ?string, cancel_initiated_at: ?string, archived_at: ?string, results_url: ?string}  $attributes
     */
    public static function from(array $attributes, MetaInformation $meta): self
    {
        return new self(
            $attributes['id'],
            $attributes['type'],
            $attributes['processing_status'],
            BatchResponseRequestCounts::from($attributes['request_counts']),
            $attributes['created_at'],
            $attributes['expires_at'],
            $attributes['ended_at'] ?? null,
            $attributes['cancel_initiated_at'] ?? null,
            $attributes['archived_at'] ?? null,
            $attributes['results_url'] ?? null,
            $meta,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'processing_status' => $this->processingStatus,
            'request_counts' => $this->requestCounts->toArray(),
            'created_at' => $this->createdAt,
            'expires_at' => $this->expiresAt,
            'ended_at' => $this->endedAt,
            'cancel_initiated_at' => $this->cancelInitiatedAt,
            'archived_at' => $this->archivedAt,
            'results_url' => $this->resultsUrl,
        ];
    }
}
