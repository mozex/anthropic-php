<?php

declare(strict_types=1);

namespace Anthropic\Resources\Concerns;

use Anthropic\Contracts\ResponseContract;
use Closure;
use Generator;

trait Paginates
{
    /**
     * Yields every item of a list, calling it page by page: a page's `next_page` becomes the next call's `page`;
     * otherwise, while `has_more` is true, its `last_id` becomes `after_id`, or its `first_id` becomes `before_id`
     * when the first call paged backwards. The other parameters carry over, and nothing is requested until the
     * caller loops.
     *
     * @template TPage of ResponseContract
     * @template TItem
     *
     * @param  array<string, mixed>  $parameters
     * @param  Closure(array<string, mixed>): TPage  $list
     * @param  Closure(TPage): array<int, TItem>  $items
     * @return Generator<int, TItem, mixed, void>
     */
    private function paginate(array $parameters, Closure $list, Closure $items): Generator
    {
        $backwards = is_string($parameters['before_id'] ?? null) && $parameters['before_id'] !== '';
        [$cursor, $nextIdKey] = $backwards ? ['before_id', 'first_id'] : ['after_id', 'last_id'];

        while (true) {
            $page = $list($parameters);
            $pageItems = $items($page);

            // One item at a time, so the keys run on across pages.
            foreach ($pageItems as $item) {
                yield $item;
            }

            $attributes = $page->toArray();
            $nextPage = $attributes['next_page'] ?? null;

            // A cursor the last call already sent would fetch the same page forever.
            if (is_string($nextPage) && $nextPage !== '' && $nextPage !== ($parameters['page'] ?? null)) {
                $parameters['page'] = $nextPage;

                continue;
            }

            if (($attributes['has_more'] ?? false) !== true || $pageItems === []) {
                return;
            }

            $nextId = $attributes[$nextIdKey] ?? null;

            if (! is_string($nextId) || $nextId === '' || $nextId === ($parameters[$cursor] ?? null)) {
                return;
            }

            $parameters[$cursor] = $nextId;
        }
    }
}
