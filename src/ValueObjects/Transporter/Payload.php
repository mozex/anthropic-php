<?php

declare(strict_types=1);

namespace Anthropic\ValueObjects\Transporter;

use Anthropic\Contracts\Request;
use Anthropic\Enums\Transporter\ContentType;
use Anthropic\Enums\Transporter\Method;
use Anthropic\Exceptions\InvalidArgumentException;
use Anthropic\ValueObjects\ResourceUri;
use Http\Discovery\Psr17Factory;
use Http\Message\MultipartStream\MultipartStreamBuilder;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;

/**
 * @internal
 */
class Payload
{
    /**
     * Creates a new Request value object.
     *
     * @param  array<string, mixed>  $parameters
     * @param  list<string>  $betas
     */
    private function __construct(
        private readonly ContentType $contentType,
        private readonly Method $method,
        private readonly ResourceUri $uri,
        private readonly array $parameters = [],
        private readonly array $betas = [],
    ) {
        // ..
    }

    /**
     * Creates a new Payload value object from the given parameters.
     *
     * @param  array<string, mixed>  $parameters
     */
    public static function list(string $resource, array $parameters = []): self
    {
        [$parameters, $betas] = self::splitBetas($parameters);

        return new self(ContentType::JSON, Method::GET, ResourceUri::list($resource), $parameters, $betas);
    }

    /**
     * Creates a new Payload value object from the given parameters.
     *
     * @param  array<string, mixed>  $parameters
     */
    public static function retrieve(string $resource, string $id, string $suffix = '', array $parameters = []): self
    {
        [$parameters, $betas] = self::splitBetas($parameters);

        return new self(ContentType::JSON, Method::GET, ResourceUri::retrieve($resource, $id, $suffix), $parameters, $betas);
    }

    /**
     * Creates a new Payload value object from the given parameters.
     *
     * @param  array<string, mixed>  $parameters
     */
    public static function modify(string $resource, string $id, array $parameters = []): self
    {
        [$parameters, $betas] = self::splitBetas($parameters);

        return new self(ContentType::JSON, Method::POST, ResourceUri::modify($resource, $id), $parameters, $betas);
    }

    /**
     * Creates a new Payload value object from the given parameters.
     */
    public static function retrieveContent(string $resource, string $id): self
    {
        return new self(ContentType::JSON, Method::GET, ResourceUri::retrieveContent($resource, $id));
    }

    /**
     * Creates a new Payload value object from the given parameters.
     *
     * @param  array<string, mixed>  $parameters
     */
    public static function create(string $resource, array $parameters): self
    {
        [$parameters, $betas] = self::splitBetas($parameters);

        return new self(ContentType::JSON, Method::POST, ResourceUri::create($resource), $parameters, $betas);
    }

    /**
     * Creates a new Payload value object from the given parameters.
     *
     * @param  array<string, mixed>  $parameters
     */
    public static function upload(string $resource, array $parameters): self
    {
        [$parameters, $betas] = self::splitBetas($parameters);

        return new self(ContentType::MULTIPART, Method::POST, ResourceUri::upload($resource), $parameters, $betas);
    }

    /**
     * Creates a new Payload value object from the given parameters.
     */
    public static function cancel(string $resource, string $id): self
    {
        return new self(ContentType::JSON, Method::POST, ResourceUri::cancel($resource, $id));
    }

    /**
     * Creates a new Payload value object from the given parameters.
     */
    public static function delete(string $resource, string $id): self
    {
        return new self(ContentType::JSON, Method::DELETE, ResourceUri::delete($resource, $id));
    }

    /**
     * Creates a new Payload value object for any path: the parameters go in the query for GET and DELETE, and in a
     * JSON body for POST and PUT. A `betas` parameter becomes the request's `anthropic-beta` header, as elsewhere.
     *
     * @param  array<string, mixed>  $parameters
     */
    public static function raw(string $method, string $path, array $parameters = []): self
    {
        $httpMethod = Method::tryFrom(strtoupper(trim($method)));

        if ($httpMethod === null) {
            throw new InvalidArgumentException("The method [{$method}] isn't one of GET, POST, PUT or DELETE.");
        }

        [$parameters, $betas] = self::splitBetas($parameters);

        return new self(ContentType::JSON, $httpMethod, ResourceUri::raw($path), $parameters, $betas);
    }

    /**
     * Returns a new Payload with the given betas merged into this one.
     *
     * Resources use this to auto-inject the beta header they need (e.g. `files-api-2025-04-14`) while keeping any betas the user already passed via the `betas` parameter.
     *
     * @param  list<string>  $betas
     */
    public function withBetas(array $betas): self
    {
        return new self(
            $this->contentType,
            $this->method,
            $this->uri,
            $this->parameters,
            self::dedupeBetas([...$this->betas, ...$betas]),
        );
    }

    /**
     * Creates a new Psr 7 Request instance.
     */
    public function toRequest(BaseUri $baseUri, Headers $headers, QueryParams $queryParams): RequestInterface
    {
        $psr17Factory = new Psr17Factory;

        $body = null;

        $uri = $baseUri->toString().$this->uri->toString();

        $queryParams = $queryParams->toArray();
        if ($this->method === Method::GET || $this->method === Method::DELETE) {
            $queryParams = [...$queryParams, ...$this->parameters];
        }

        $query = self::buildQuery($queryParams);

        if ($query !== '') {
            // A raw request's path may carry a query of its own.
            $uri .= (str_contains($uri, '?') ? '&' : '?').$query;
        }

        $headers = $headers->withContentType($this->contentType);

        if ($this->method === Method::POST || $this->method === Method::PUT) {
            if ($this->contentType === ContentType::MULTIPART) {
                $streamBuilder = new MultipartStreamBuilder($psr17Factory);

                /** @var array<string, StreamInterface|resource|string|int|float|bool|array<int, string>> $parameters */
                $parameters = $this->parameters;

                foreach ($parameters as $key => $value) {
                    if (is_int($value) || is_float($value) || is_bool($value)) {
                        $value = (string) $value;
                    }

                    if (is_array($value)) {
                        foreach ($value as $nestedValue) {
                            $streamBuilder->addResource($key.'[]', $nestedValue);
                        }

                        continue;
                    }

                    $streamBuilder->addResource($key, $value);
                }

                $body = $streamBuilder->build();

                $headers = $headers->withContentType($this->contentType, '; boundary='.$streamBuilder->getBoundary());
            } else {
                $body = $psr17Factory->createStream(json_encode($this->parameters, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
            }
        }

        $request = $psr17Factory->createRequest($this->method->value, $uri);

        if ($body instanceof StreamInterface) {
            $request = $request->withBody($body);
        }

        foreach ($headers->toArray() as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        if ($this->betas !== []) {
            $request = $request->withHeader('anthropic-beta', self::mergeBetaHeader($request->getHeaderLine('anthropic-beta'), $this->betas));
        }

        return $request;
    }

    /**
     * Builds the query string, sending a top-level list (an array with int keys, gaps included, as `array_filter()`
     * leaves them) as `key[]=` once per value: the API ignores the indexed `key[0]=` that `http_build_query()` writes
     * for one. Other values keep `http_build_query()`'s form.
     *
     * @param  array<array-key, mixed>  $parameters
     */
    private static function buildQuery(array $parameters): string
    {
        $pairs = [];

        foreach ($parameters as $key => $value) {
            if (is_array($value) && array_filter(array_keys($value), is_string(...)) === []) {
                foreach ($value as $item) {
                    $pairs[] = http_build_query([$key.'[]' => $item]);
                }

                continue;
            }

            $pairs[] = http_build_query([$key => $value]);
        }

        return implode('&', array_filter($pairs, fn (string $pair): bool => $pair !== ''));
    }

    /**
     * Splits out the `betas` parameter from the request body, turning it into a per-request header.
     *
     * @param  array<string, mixed>  $parameters
     * @return array{0: array<string, mixed>, 1: list<string>}
     */
    private static function splitBetas(array $parameters): array
    {
        if (! isset($parameters['betas']) || ! is_array($parameters['betas'])) {
            return [$parameters, []];
        }

        /** @var array<array-key, mixed> $rawBetas */
        $rawBetas = $parameters['betas'];
        unset($parameters['betas']);

        $betas = [];
        foreach ($rawBetas as $beta) {
            if (is_string($beta) && trim($beta) !== '') {
                $betas[] = trim($beta);
            }
        }

        return [$parameters, self::dedupeBetas($betas)];
    }

    /**
     * Merges a comma-separated beta header string with additional betas, de-duplicating in order of appearance.
     *
     * @param  list<string>  $betas
     */
    private static function mergeBetaHeader(string $existing, array $betas): string
    {
        $existingList = [];
        if ($existing !== '') {
            foreach (explode(',', $existing) as $item) {
                $trimmed = trim($item);
                if ($trimmed !== '') {
                    $existingList[] = $trimmed;
                }
            }
        }

        return implode(',', self::dedupeBetas([...$existingList, ...$betas]));
    }

    /**
     * @param  list<string>  $betas
     * @return list<string>
     */
    private static function dedupeBetas(array $betas): array
    {
        return array_values(array_unique($betas));
    }
}
