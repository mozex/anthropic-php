<?php

declare(strict_types=1);

namespace Anthropic\Responses\Models;

use Anthropic\Contracts\ResponseContract;
use Anthropic\Contracts\ResponseHasMetaInformationContract;
use Anthropic\Responses\Concerns\ArrayAccessible;
use Anthropic\Responses\Concerns\HasMetaInformation;
use Anthropic\Responses\Meta\MetaInformation;
use Anthropic\Testing\Responses\Concerns\Models\Fakeable;

/**
 * @implements ResponseContract<array{data: array<int, array{id: string, type: string, created_at: string, display_name: string}>, first_id: string, last_id: string, has_more: bool}>
 */
final class ListResponse implements ResponseContract, ResponseHasMetaInformationContract
{
    /**
     * @use ArrayAccessible<array{data: array<int, array{id: string, type: string, created_at: string, display_name: string}>, first_id: string, last_id: string, has_more: bool}>
     */
    use ArrayAccessible;

    use Fakeable;
    use HasMetaInformation;

    /**
     * @param  array<int, RetrieveResponse>  $data
     */
    private function __construct(
        public readonly array $data,
        public readonly string $firstId,
        public readonly string $lastId,
        public readonly bool $hasMore,
        private readonly MetaInformation $meta,
    ) {}

    /**
     * Acts as static factory, and returns a new Response instance.
     *
     * @param  array{data: array<int, array{id: string, type: string, created_at: string, display_name: string}>, first_id: string, last_id: string, has_more: bool}  $attributes
     */
    public static function from(array $attributes, MetaInformation $meta): self
    {
        $data = array_map(
            fn (array $result): RetrieveResponse => RetrieveResponse::from($result, $meta),
            $attributes['data'],
        );

        return new self(
            $data,
            $attributes['first_id'],
            $attributes['last_id'],
            $attributes['has_more'],
            $meta,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return [
            'data' => array_map(
                static fn (RetrieveResponse $response): array => $response->toArray(),
                $this->data,
            ),
            'first_id' => $this->firstId,
            'last_id' => $this->lastId,
            'has_more' => $this->hasMore,
        ];
    }
}
