<?php

declare(strict_types=1);

namespace Anthropic\ValueObjects;

use Anthropic\Contracts\StringableContract;
use Anthropic\Exceptions\InvalidArgumentException;

/**
 * @internal
 */
class ResourceUri implements StringableContract
{
    /**
     * Creates a new ResourceUri value object.
     */
    private function __construct(private readonly string $uri)
    {
        // ..
    }

    /**
     * Creates a new ResourceUri value object that creates the given resource.
     */
    public static function create(string $resource): self
    {
        return new self($resource);
    }

    /**
     * Creates a new ResourceUri value object that uploads to the given resource.
     */
    public static function upload(string $resource): self
    {
        return new self($resource);
    }

    /**
     * Creates a new ResourceUri value object that lists the given resource.
     */
    public static function list(string $resource): self
    {
        return new self($resource);
    }

    /**
     * Creates a new ResourceUri value object that retrieves the given resource.
     */
    public static function retrieve(string $resource, string $id, string $suffix): self
    {
        return new self("{$resource}/{$id}{$suffix}");
    }

    /**
     * Creates a new ResourceUri value object that modifies the given resource.
     */
    public static function modify(string $resource, string $id): self
    {
        return new self("{$resource}/{$id}");
    }

    /**
     * Creates a new ResourceUri value object that retrieves the given resource content.
     */
    public static function retrieveContent(string $resource, string $id): self
    {
        return new self("{$resource}/{$id}/content");
    }

    /**
     * Creates a new ResourceUri value object that cancels the given resource.
     */
    public static function cancel(string $resource, string $id): self
    {
        return new self("{$resource}/{$id}/cancel");
    }

    /**
     * Creates a new ResourceUri value object that deletes the given resource.
     */
    public static function delete(string $resource, string $id): self
    {
        return new self("{$resource}/{$id}");
    }

    /**
     * Creates a new ResourceUri value object for any path below the API root. A leading slash and `v1/`, as the
     * API reference prints paths (`/v1/models`), are dropped, since the base URI ends in `/v1`. A full URL throws:
     * the request would go to the base URI with the URL appended.
     */
    public static function raw(string $path): self
    {
        if (str_contains($path, '://')) {
            throw new InvalidArgumentException("The path [{$path}] is a URL; pass the part after the API root, such as `models`.");
        }

        $path = ltrim(trim($path), '/');

        return new self((string) preg_replace('#^v1(/|$)#', '', $path));
    }

    /**
     * {@inheritDoc}
     */
    public function toString(): string
    {
        return $this->uri;
    }
}
