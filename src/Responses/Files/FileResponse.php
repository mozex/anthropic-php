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
 * @implements ResponseContract<array{id: string, type: string, filename: string, mime_type: string, size_bytes: int, created_at: string, downloadable?: bool, scope?: array{id: string, type: string}}>
 */
final class FileResponse implements ResponseContract, ResponseHasMetaInformationContract
{
    /**
     * @use ArrayAccessible<array{id: string, type: string, filename: string, mime_type: string, size_bytes: int, created_at: string, downloadable?: bool, scope?: array{id: string, type: string}}>
     */
    use ArrayAccessible;

    use Fakeable;
    use HasMetaInformation;

    private function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly string $filename,
        public readonly string $mimeType,
        public readonly int $sizeBytes,
        public readonly string $createdAt,
        public readonly ?bool $downloadable,
        public readonly ?FileResponseScope $scope,
        private readonly MetaInformation $meta,
    ) {}

    /**
     * Acts as static factory, and returns a new Response instance.
     *
     * @param  array{id: string, type: string, filename: string, mime_type: string, size_bytes: int, created_at: string, downloadable?: bool, scope?: array{id: string, type: string}}  $attributes
     */
    public static function from(array $attributes, MetaInformation $meta): self
    {
        return new self(
            $attributes['id'],
            $attributes['type'],
            $attributes['filename'],
            $attributes['mime_type'],
            $attributes['size_bytes'],
            $attributes['created_at'],
            $attributes['downloadable'] ?? null,
            isset($attributes['scope']) ? FileResponseScope::from($attributes['scope']) : null,
            $meta,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        $result = [
            'id' => $this->id,
            'type' => $this->type,
            'filename' => $this->filename,
            'mime_type' => $this->mimeType,
            'size_bytes' => $this->sizeBytes,
            'created_at' => $this->createdAt,
        ];

        if ($this->downloadable !== null) {
            $result['downloadable'] = $this->downloadable;
        }

        if ($this->scope !== null) {
            $result['scope'] = $this->scope->toArray();
        }

        return $result;
    }
}
