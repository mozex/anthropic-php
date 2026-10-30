<?php

declare(strict_types=1);

namespace Anthropic\Resources\Concerns;

use Anthropic\Exceptions\InvalidArgumentException;
use Anthropic\Responses\Batches\BatchResponse;
use Closure;

trait WaitsForBatches
{
    abstract public function retrieve(string $id): BatchResponse;

    /**
     * Retrieves a Message Batch every `$interval` seconds until it has ended, and returns it. With a `$timeout` in
     * seconds, the last poll comes at the timeout, and the batch is returned as it was then: check `isEnded()`.
     *
     * @param  (Closure(int): void)|null  $sleep  sleeps the given seconds between polls; `sleep()` without it
     */
    public function wait(string $id, int $interval = 60, ?int $timeout = null, ?Closure $sleep = null): BatchResponse
    {
        if ($interval < 1) {
            throw new InvalidArgumentException("The interval between polls must be at least one second, {$interval} given.");
        }

        if ($timeout !== null && $timeout < 0) {
            throw new InvalidArgumentException("The timeout can't be negative, {$timeout} given.");
        }

        $sleep ??= static function (int $seconds): void {
            sleep($seconds);
        };
        $waited = 0;

        while (true) {
            $batch = $this->retrieve($id);

            if ($batch->isEnded() || ($timeout !== null && $waited >= $timeout)) {
                return $batch;
            }

            // The last sleep is cut short, so the last poll comes at the timeout.
            $seconds = $timeout === null ? $interval : min($interval, $timeout - $waited);
            $sleep($seconds);
            $waited += $seconds;
        }
    }
}
