<?php

declare(strict_types=1);

/**
 * An endpoint the client has no resource for yet, for raw requests.
 *
 * https://platform.claude.com/docs/en/api/skills/list
 *
 * @return array{data: array<int, array<string, mixed>>, next_page: string|null}
 */
function skillsListDocsExample(): array
{
    return [
        'data' => [
            [
                'id' => 'skill_01JAbcdefghijklmnopqrstuvw',
                'created_at' => '2024-10-30T23:58:27.427722Z',
                'display_name' => 'display_name',
                'latest_version_id' => 'latest_version_id',
                'source' => [
                    'type' => 'custom',
                ],
                'type' => 'skill',
                'updated_at' => '2024-10-30T23:58:27.427722Z',
            ],
        ],
        'next_page' => 'next_page',
    ];
}
