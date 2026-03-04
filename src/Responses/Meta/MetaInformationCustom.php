<?php

declare(strict_types=1);

namespace Anthropic\Responses\Meta;

final class MetaInformationCustom
{
    /**
     * @param  array<string, string>  $headers
     */
    private function __construct(
        public readonly array $headers,
    ) {}

    /**
     * @param  array<string, string[]>  $headers
     * @param  array<int, string>  $knownHeaders
     */
    public static function from(array $headers, array $knownHeaders): self
    {
        $custom = [];

        foreach ($headers as $name => $values) {
            if (in_array($name, $knownHeaders, true)) {
                continue;
            }

            $custom[$name] = $values[0] ?? '';
        }

        return new self($custom);
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return $this->headers;
    }
}
