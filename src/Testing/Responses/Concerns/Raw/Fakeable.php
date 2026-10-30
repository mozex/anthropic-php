<?php

declare(strict_types=1);

namespace Anthropic\Testing\Responses\Concerns\Raw;

use Anthropic\Responses\Meta\MetaInformation;

trait Fakeable
{
    /**
     * A raw response has no fixture: the fake carries the whole body and the status it's given. The meta
     * information comes second, as on every other fake.
     *
     * @param  array<array-key, mixed>|string  $data
     */
    public static function fake(array|string $data = [], ?MetaInformation $meta = null, int $status = 200): self
    {
        return self::from($status, $data, $meta ?? self::fakeResponseMetaInformation());
    }

    public static function fakeResponseMetaInformation(): MetaInformation
    {
        return MetaInformation::from([
            'request-id' => ['req_011CSHoEeqs5C35K2UUqR7Fy'],
        ]);
    }
}
