<?php

declare(strict_types=1);

/**
 * Adds the keys `toArray()` always emits that a docs example may leave out.
 */
function docsExampleWithAlwaysEmittedKeys(stdClass $message): stdClass
{
    $message->stop_sequence ??= null;
    $message->usage->cache_creation_input_tokens ??= 0;
    $message->usage->cache_read_input_tokens ??= 0;

    foreach ($message->usage->iterations ?? [] as $iteration) {
        $iteration->cache_read_input_tokens ??= 0;
        $iteration->cache_creation_input_tokens ??= 0;
    }

    return $message;
}

/**
 * Sorts object keys at every depth, so two decoded JSON values compare exactly apart from key order.
 */
function docsExampleWithSortedKeys(mixed $value): mixed
{
    if ($value instanceof stdClass) {
        $properties = get_object_vars($value);
        ksort($properties);

        return (object) array_map(docsExampleWithSortedKeys(...), $properties);
    }

    return is_array($value) ? array_map(docsExampleWithSortedKeys(...), $value) : $value;
}

/**
 * Encodes a `toArray()` result, a decoded docs example or its JSON text as key-sorted JSON, so `{}`,
 * `[]`, `0` and `null` stay apart and only key order is ignored.
 */
function docsExampleJson(array|string|stdClass $value): string
{
    $decoded = match (true) {
        is_string($value) => json_decode($value, flags: JSON_THROW_ON_ERROR),
        is_array($value) => json_decode(json_encode($value, JSON_THROW_ON_ERROR), flags: JSON_THROW_ON_ERROR),
        default => $value,
    };

    return json_encode(docsExampleWithSortedKeys($decoded), JSON_THROW_ON_ERROR);
}
