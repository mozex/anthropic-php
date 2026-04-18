<?php

declare(strict_types=1);

namespace Anthropic\Responses\Files;

use Anthropic\Contracts\ResponseContract;
use Anthropic\Contracts\ResponseHasMetaInformationContract;
use Anthropic\Responses\Concerns\ArrayAccessible;
use Anthropic\Responses\Concerns\HasMetaInformation;
use Anthropic\Responses\Meta\MetaInformation;
use Anthropic\Testing\Responses\Concerns\Files\Fakeable;

/**
 * @implements ResponseContract<array{data: array<int, array{id: string, type: string, filename: string, mime_type: string, size_bytes: int, created_at: string, downloadable?: bool, scope?: array{id: string, type: string}}>, first_id: ?string, last_id: ?string, has_more: bool}>
 */
final class FileListResponse implements ResponseContract, ResponseHasMetaInformationContract
{
    /**
     * @use ArrayAccessible<array{data: array<int, array{id: string, type: string, filename: string, mime_type: string, size_bytes: int, created_at: string, downloadable?: bool, scope?: array{id: string, type: string}}>, first_id: ?string, last_id: ?string, has_more: bool}>
     */
    use ArrayAccessible;

    use Fakeable;
    use HasMetaInformation;

    /**
     * @param  array<int, FileResponse>  $data
     */
    private function __construct(
        public readonly array $data,
        public readonly ?string $firstId,
        public readonly ?string $lastId,
        public readonly bool $hasMore,
        private readonly MetaInformation $meta,
    ) {}

    /**
     * Acts as static factory, and returns a new Response instance.
     *
     * @param  array{data: array<int, array{id: string, type: string, filename: string, mime_type: string, size_bytes: int, created_at: string, downloadable?: bool, scope?: array{id: string, type: string}}>, first_id?: ?string, last_id?: ?string, has_more?: bool}  $attributes
     */
    public static function from(array $attributes, MetaInformation $meta): self
    {
        $data = array_map(
            fn (array $result): FileResponse => FileResponse::from($result, $meta),
            $attributes['data'],
        );

        return new self(
            $data,
            $attributes['first_id'] ?? null,
            $attributes['last_id'] ?? null,
            $attributes['has_more'] ?? false,
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
                static fn (FileResponse $response): array => $response->toArray(),
                $this->data,
            ),
            'first_id' => $this->firstId,
            'last_id' => $this->lastId,
            'has_more' => $this->hasMore,
        ];
    }
}
