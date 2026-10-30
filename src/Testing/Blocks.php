<?php

declare(strict_types=1);

namespace Anthropic\Testing;

/**
 * Content blocks for a fake's `content`, in the shapes the API sends, so a test builds a tool call, a thinking block
 * or a web search without writing them out: `CreateResponse::fake(['content' => [Blocks::thinking('...'),
 * Blocks::toolUse('get_weather', ['location' => 'Paris'])], 'stop_reason' => 'tool_use'])`. The default IDs and
 * values come from the API docs' examples.
 */
class Blocks
{
    /**
     * @param  list<array<string, mixed>>|null  $citations
     * @return array{type: 'text', text: string, citations?: list<array<string, mixed>>}
     */
    public static function text(string $text, ?array $citations = null): array
    {
        return $citations === null ? ['type' => 'text', 'text' => $text] : ['type' => 'text', 'text' => $text, 'citations' => $citations];
    }

    /**
     * A call of one of your tools.
     *
     * @param  array<string, mixed>  $input
     * @return array{type: 'tool_use', id: string, name: string, input: array<string, mixed>}
     */
    public static function toolUse(string $name, array $input = [], string $id = 'toolu_01T1x1fJ34qAmk2tNTrN7Up6'): array
    {
        return ['type' => 'tool_use', 'id' => $id, 'name' => $name, 'input' => $input];
    }

    /**
     * @return array{type: 'thinking', thinking: string, signature: string}
     */
    public static function thinking(string $thinking, string $signature = 'EqQBCgIYAhIM1gbcDa9GJwZA2b3hGgxBdjrkzLoky3dl1pkiMOYds'): array
    {
        return ['type' => 'thinking', 'thinking' => $thinking, 'signature' => $signature];
    }

    /**
     * A call of a server tool, such as web search, which the API runs itself.
     *
     * @param  array<string, mixed>  $input
     * @return array{type: 'server_tool_use', id: string, name: string, input: array<string, mixed>}
     */
    public static function serverToolUse(array $input = ['query' => 'weather NYC today'], string $name = 'web_search', string $id = 'srvtoolu_014hJH82Qum7Td6UV8gDXThB'): array
    {
        return ['type' => 'server_tool_use', 'id' => $id, 'name' => $name, 'input' => $input];
    }

    /**
     * The results of a web search, each a `webSearchResult()`; the call's ID ties them to its `serverToolUse()`.
     *
     * @param  list<array<string, mixed>>|null  $results
     * @return array{type: 'web_search_tool_result', tool_use_id: string, content: list<array<string, mixed>>}
     */
    public static function webSearchToolResult(?array $results = null, string $toolUseId = 'srvtoolu_014hJH82Qum7Td6UV8gDXThB'): array
    {
        return ['type' => 'web_search_tool_result', 'tool_use_id' => $toolUseId, 'content' => $results ?? [self::webSearchResult()]];
    }

    /**
     * @return array{type: 'web_search_result', title: string, url: string, encrypted_content: string, page_age: ?string}
     */
    public static function webSearchResult(
        string $title = 'Weather in New York City in May 2025 (New York) - detailed Weather Forecast for a month',
        string $url = 'https://world-weather.info/forecast/usa/new_york/may-2025/',
        string $encryptedContent = 'Ev0DCioIAxgCIiQ3NmU4ZmI4OC1k',
        ?string $pageAge = null,
    ): array {
        return ['type' => 'web_search_result', 'title' => $title, 'url' => $url, 'encrypted_content' => $encryptedContent, 'page_age' => $pageAge];
    }
}
