<?php

declare(strict_types=1);

namespace Anthropic\Responses\Concerns;

use Generator;
use Psr\Http\Message\StreamInterface;

/**
 * @internal
 */
trait ReadsStreams
{
    /**
     * What was read from the stream but not yet returned as lines. It lives on the instance, so
     * iterating again continues where the last iteration stopped, as the stream's position does.
     */
    private string $unread = '';

    private int $unreadOffset = 0;

    /**
     * Reads the stream line by line, 64 KiB at a time, without the line endings.
     *
     * @return Generator<int, string>
     */
    private function readLines(StreamInterface $stream): Generator
    {
        while (true) {
            $end = strpos($this->unread, "\n", $this->unreadOffset);

            if ($end !== false) {
                $line = substr($this->unread, $this->unreadOffset, $end - $this->unreadOffset);
                $this->unreadOffset = $end + 1;

                yield rtrim($line, "\r");

                continue;
            }

            if ($stream->eof()) {
                break;
            }

            $this->unread = substr($this->unread, $this->unreadOffset).$stream->read(65536);
            $this->unreadOffset = 0;
        }

        $line = substr($this->unread, $this->unreadOffset);
        $this->unread = '';
        $this->unreadOffset = 0;

        if ($line !== '') {
            yield rtrim($line, "\r");
        }
    }

    /**
     * Reads the data of each server-sent event: its `data:` lines joined with newlines, dispatched
     * at a blank line or at the end of the stream. Comments and other fields are skipped.
     *
     * @return Generator<int, string>
     */
    private function readEvents(StreamInterface $stream): Generator
    {
        $data = [];

        foreach ($this->readLines($stream) as $line) {
            if ($line !== '') {
                [$field, $value] = array_pad(explode(':', $line, 2), 2, '');

                if ($field === 'data') {
                    $data[] = str_starts_with($value, ' ') ? substr($value, 1) : $value;
                }

                continue;
            }

            if (($event = implode("\n", $data)) !== '') {
                yield $event;
            }

            $data = [];
        }

        if (($event = implode("\n", $data)) !== '') {
            yield $event;
        }
    }
}
