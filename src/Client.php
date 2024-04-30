<?php

declare(strict_types=1);

namespace Anthropic;

use Anthropic\Contracts\ClientContract;
use Anthropic\Contracts\Resources\ThreadsContract;
use Anthropic\Contracts\TransporterContract;
use Anthropic\Resources\Assistants;
use Anthropic\Resources\Audio;
use Anthropic\Resources\Chat;
use Anthropic\Resources\Completions;
use Anthropic\Resources\Edits;
use Anthropic\Resources\Embeddings;
use Anthropic\Resources\Files;
use Anthropic\Resources\FineTunes;
use Anthropic\Resources\FineTuning;
use Anthropic\Resources\Images;
use Anthropic\Resources\Models;
use Anthropic\Resources\Moderations;
use Anthropic\Resources\Threads;

final class Client implements ClientContract
{
    /**
     * Creates a Client instance with the given API token.
     */
    public function __construct(private readonly TransporterContract $transporter)
    {
        // ..
    }

    /**
     * Given a prompt, the model will return one or more predicted completions, and can also return the probabilities
     * of alternative tokens at each position.
     *
     * @see https://platform.openai.com/docs/api-reference/completions
     */
    public function completions(): Completions
    {
        return new Completions($this->transporter);
    }

    /**
     * Given a chat conversation, the model will return a chat completion response.
     *
     * @see https://platform.openai.com/docs/api-reference/chat
     */
    public function chat(): Chat
    {
        return new Chat($this->transporter);
    }

    /**
     * Get a vector representation of a given input that can be easily consumed by machine learning models and algorithms.
     *
     * @see https://platform.openai.com/docs/api-reference/embeddings
     */
    public function embeddings(): Embeddings
    {
        return new Embeddings($this->transporter);
    }

    /**
     * Learn how to turn audio into text.
     *
     * @see https://platform.openai.com/docs/api-reference/audio
     */
    public function audio(): Audio
    {
        return new Audio($this->transporter);
    }

    /**
     * Given a prompt and an instruction, the model will return an edited version of the prompt.
     *
     * @see https://platform.openai.com/docs/api-reference/edits
     */
    public function edits(): Edits
    {
        return new Edits($this->transporter);
    }

    /**
     * Files are used to upload documents that can be used with features like Fine-tuning.
     *
     * @see https://platform.openai.com/docs/api-reference/files
     */
    public function files(): Files
    {
        return new Files($this->transporter);
    }

    /**
     * List and describe the various models available in the API.
     *
     * @see https://platform.openai.com/docs/api-reference/models
     */
    public function models(): Models
    {
        return new Models($this->transporter);
    }

    /**
     * Manage fine-tuning jobs to tailor a model to your specific training data.
     *
     * @see https://platform.openai.com/docs/api-reference/fine-tuning
     */
    public function fineTuning(): FineTuning
    {
        return new FineTuning($this->transporter);
    }

    /**
     * Manage fine-tuning jobs to tailor a model to your specific training data.
     *
     * @see https://platform.openai.com/docs/api-reference/fine-tunes
     * @deprecated Anthropic has deprecated this endpoint and will stop working by January 4, 2024.
     * https://openai.com/blog/gpt-3-5-turbo-fine-tuning-and-api-updates#updated-gpt-3-models
     */
    public function fineTunes(): FineTunes
    {
        return new FineTunes($this->transporter);
    }

    /**
     * Given a input text, outputs if the model classifies it as violating Anthropic's content policy.
     *
     * @see https://platform.openai.com/docs/api-reference/moderations
     */
    public function moderations(): Moderations
    {
        return new Moderations($this->transporter);
    }

    /**
     * Given a prompt and/or an input image, the model will generate a new image.
     *
     * @see https://platform.openai.com/docs/api-reference/images
     */
    public function images(): Images
    {
        return new Images($this->transporter);
    }

    /**
     * Build assistants that can call models and use tools to perform tasks.
     *
     * @see https://platform.openai.com/docs/api-reference/assistants
     */
    public function assistants(): Assistants
    {
        return new Assistants($this->transporter);
    }

    /**
     * Create threads that assistants can interact with.
     *
     * @see https://platform.openai.com/docs/api-reference/threads
     */
    public function threads(): ThreadsContract
    {
        return new Threads($this->transporter);
    }
}
