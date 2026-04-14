<?php

declare(strict_types=1);

namespace Anthropic\Resources;

use Anthropic\Contracts\Resources\ModelsContract;
use Anthropic\Responses\Models\ListResponse;
use Anthropic\Responses\Models\RetrieveResponse;
use Anthropic\ValueObjects\Transporter\Payload;
use Anthropic\ValueObjects\Transporter\Response;

final class Models implements ModelsContract
{
    use Concerns\Transportable;

    /**
     * Lists the currently available models.
     *
     * @see https://platform.claude.com/docs/en/api/models/list
     *
     * @param  array<string, mixed>  $parameters
     */
    public function list(array $parameters = []): ListResponse
    {
        $payload = Payload::list('models', $parameters);

        /** @var Response<array{data: array<int, array{id: string, type: string, created_at: string, display_name: string, max_input_tokens: int, max_tokens: int, capabilities: array{batch: array{supported: bool}, citations: array{supported: bool}, code_execution: array{supported: bool}, context_management: array<string, bool|array{supported: bool}>, effort: array{supported: bool, low: array{supported: bool}, medium: array{supported: bool}, high: array{supported: bool}, max: array{supported: bool}}, image_input: array{supported: bool}, pdf_input: array{supported: bool}, structured_outputs: array{supported: bool}, thinking: array{supported: bool, types: array{adaptive: array{supported: bool}, enabled: array{supported: bool}}}}}>, first_id: string, last_id: string, has_more: bool}> $response */
        $response = $this->transporter->requestObject($payload);

        return ListResponse::from($response->data(), $response->meta());
    }

    /**
     * Gets information about a specific model.
     *
     * @see https://platform.claude.com/docs/en/api/models/retrieve
     */
    public function retrieve(string $model): RetrieveResponse
    {
        $payload = Payload::retrieve('models', $model);

        /** @var Response<array{id: string, type: string, created_at: string, display_name: string, max_input_tokens: int, max_tokens: int, capabilities: array{batch: array{supported: bool}, citations: array{supported: bool}, code_execution: array{supported: bool}, context_management: array<string, bool|array{supported: bool}>, effort: array{supported: bool, low: array{supported: bool}, medium: array{supported: bool}, high: array{supported: bool}, max: array{supported: bool}}, image_input: array{supported: bool}, pdf_input: array{supported: bool}, structured_outputs: array{supported: bool}, thinking: array{supported: bool, types: array{adaptive: array{supported: bool}, enabled: array{supported: bool}}}}}> $response */
        $response = $this->transporter->requestObject($payload);

        return RetrieveResponse::from($response->data(), $response->meta());
    }
}
