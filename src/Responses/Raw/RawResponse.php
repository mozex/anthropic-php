<?php

declare(strict_types=1);

namespace Anthropic\Responses\Raw;

use Anthropic\Contracts\ResponseHasMetaInformationContract;
use Anthropic\Responses\Concerns\HasMetaInformation;
use Anthropic\Responses\Meta\MetaInformation;
use Anthropic\Testing\Responses\Concerns\Raw\Fakeable;

/**
 * The answer to a request sent with `Client::request()`: the HTTP status, the body (decoded when it's a JSON object
 * or array, the text otherwise) and the meta information from the headers.
 */
class RawResponse implements ResponseHasMetaInformationContract
{
    use Fakeable;
    use HasMetaInformation;

    /**
     * @param  array<array-key, mixed>|string  $data
     */
    private function __construct(
        public readonly int $status,
        public readonly array|string $data,
        private readonly MetaInformation $meta,
    ) {}

    /**
     * Acts as static factory, and returns a new Response instance.
     *
     * @param  array<array-key, mixed>|string  $data
     */
    public static function from(int $status, array|string $data, MetaInformation $meta): self
    {
        return new self($status, $data, $meta);
    }
}
