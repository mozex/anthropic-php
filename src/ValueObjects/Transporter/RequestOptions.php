<?php

declare(strict_types=1);

namespace Anthropic\ValueObjects\Transporter;

use Anthropic\Exceptions\InvalidArgumentException;

/**
 * The options `withOptions()` applies to every request of the client it returns.
 *
 * @internal
 */
class RequestOptions
{
    private const KEYS = ['headers', 'query', 'betas', 'api_version', 'workspace_id', 'user_profile_id'];

    /**
     * @param  array<string, string>  $headers
     * @param  array<string, string|int>  $query
     * @param  list<string>  $betas
     */
    private function __construct(
        public readonly array $headers,
        public readonly array $query,
        public readonly array $betas,
    ) {}

    /**
     * @param  array<array-key, mixed>  $options
     */
    public static function from(array $options): self
    {
        $unknown = array_diff(array_map(strval(...), array_keys($options)), self::KEYS);

        if ($unknown !== []) {
            throw new InvalidArgumentException('Unknown request options: '.implode(', ', $unknown).'. Use '.implode(', ', self::KEYS).'.');
        }

        $headers = self::stringMap($options['headers'] ?? [], 'headers');

        foreach (['api_version' => 'anthropic-version', 'workspace_id' => 'anthropic-workspace-id', 'user_profile_id' => 'anthropic-user-profile-id'] as $key => $header) {
            if (! array_key_exists($key, $options)) {
                continue;
            }

            if (! is_string($options[$key]) || trim($options[$key]) === '') {
                throw new InvalidArgumentException("The [{$key}] request option must be a non-empty string.");
            }

            $headers[$header] = trim($options[$key]);
        }

        $query = [];

        foreach (self::arrayOption($options['query'] ?? [], 'query') as $name => $value) {
            if (! is_string($name) || (! is_string($value) && ! is_int($value))) {
                throw new InvalidArgumentException('The [query] request option must map names to strings or integers.');
            }

            $query[$name] = $value;
        }

        $betas = [];

        foreach (self::arrayOption($options['betas'] ?? [], 'betas') as $beta) {
            if (! is_string($beta)) {
                throw new InvalidArgumentException('The [betas] request option must be a list of strings.');
            }

            if (trim($beta) !== '') {
                $betas[] = trim($beta);
            }
        }

        return new self($headers, $query, array_values(array_unique($betas)));
    }

    /**
     * Applies the options to a transporter's headers: the betas join any `anthropic-beta` header already set.
     */
    public function applyToHeaders(Headers $headers): Headers
    {
        foreach ($this->headers as $name => $value) {
            $headers = $headers->withCustomHeader($name, $value);
        }

        return $this->betas === [] ? $headers : $headers->withBetas($this->betas);
    }

    public function applyToQueryParams(QueryParams $queryParams): QueryParams
    {
        foreach ($this->query as $name => $value) {
            $queryParams = $queryParams->withParam($name, $value);
        }

        return $queryParams;
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function arrayOption(mixed $value, string $key): array
    {
        if (! is_array($value)) {
            throw new InvalidArgumentException("The [{$key}] request option must be an array.");
        }

        return $value;
    }

    /**
     * @return array<string, string>
     */
    private static function stringMap(mixed $value, string $key): array
    {
        $map = [];

        foreach (self::arrayOption($value, $key) as $name => $item) {
            if (! is_string($name) || ! is_string($item)) {
                throw new InvalidArgumentException("The [{$key}] request option must map names to strings.");
            }

            $map[$name] = $item;
        }

        return $map;
    }
}
