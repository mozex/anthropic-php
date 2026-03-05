<?php

/**
 * @return array{id: string, type: string, created_at: string, display_name: string}
 */
function modelRetrieve(): array
{
    return [
        'id' => 'claude-sonnet-4-6',
        'type' => 'model',
        'created_at' => '2025-05-14T00:00:00Z',
        'display_name' => 'Claude Sonnet 4.6',
    ];
}

/**
 * @return array{data: array<int, array{id: string, type: string, created_at: string, display_name: string}>, first_id: string, last_id: string, has_more: bool}
 */
function modelList(): array
{
    return [
        'data' => [
            modelRetrieve(),
            [
                'id' => 'claude-haiku-4-5',
                'type' => 'model',
                'created_at' => '2025-10-01T00:00:00Z',
                'display_name' => 'Claude Haiku 4.5',
            ],
        ],
        'first_id' => 'claude-sonnet-4-6',
        'last_id' => 'claude-haiku-4-5',
        'has_more' => true,
    ];
}
