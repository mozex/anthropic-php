<?php

namespace Anthropic\Contracts;

use Anthropic\Contracts\Resources\AssistantsContract;
use Anthropic\Contracts\Resources\AudioContract;
use Anthropic\Contracts\Resources\ChatContract;
use Anthropic\Contracts\Resources\CompletionsContract;
use Anthropic\Contracts\Resources\EditsContract;
use Anthropic\Contracts\Resources\EmbeddingsContract;
use Anthropic\Contracts\Resources\FilesContract;
use Anthropic\Contracts\Resources\FineTunesContract;
use Anthropic\Contracts\Resources\FineTuningContract;
use Anthropic\Contracts\Resources\ImagesContract;
use Anthropic\Contracts\Resources\ModelsContract;
use Anthropic\Contracts\Resources\ModerationsContract;
use Anthropic\Contracts\Resources\ThreadsContract;

interface ClientContract
{
    /**
     * Given a prompt, the model will return one or more predicted completions, and can also return the probabilities
     * of alternative tokens at each position.
     *
     * @see https://platform.openai.com/docs/api-reference/completions
     */
    public function completions(): CompletionsContract;

    /**
     * Given a chat conversation, the model will return a chat completion response.
     *
     * @see https://platform.openai.com/docs/api-reference/chat
     */
    public function chat(): ChatContract;

    /**
     * Get a vector representation of a given input that can be easily consumed by machine learning models and algorithms.
     *
     * @see https://platform.openai.com/docs/api-reference/embeddings
     */
    public function embeddings(): EmbeddingsContract;

    /**
     * Learn how to turn audio into text.
     *
     * @see https://platform.openai.com/docs/api-reference/audio
     */
    public function audio(): AudioContract;

    /**
     * Given a prompt and an instruction, the model will return an edited version of the prompt.
     *
     * @see https://platform.openai.com/docs/api-reference/edits
     * @deprecated Anthropic has deprecated this endpoint and will stop working by January 4, 2024.
     * https://openai.com/blog/gpt-4-api-general-availability#deprecation-of-the-edits-api
     */
    public function edits(): EditsContract;

    /**
     * Files are used to upload documents that can be used with features like Fine-tuning.
     *
     * @see https://platform.openai.com/docs/api-reference/files
     */
    public function files(): FilesContract;

    /**
     * List and describe the various models available in the API.
     *
     * @see https://platform.openai.com/docs/api-reference/models
     */
    public function models(): ModelsContract;

    /**
     * Manage fine-tuning jobs to tailor a model to your specific training data.
     *
     * @see https://platform.openai.com/docs/api-reference/fine-tuning
     */
    public function fineTuning(): FineTuningContract;

    /**
     * Manage fine-tuning jobs to tailor a model to your specific training data.
     *
     * @see https://platform.openai.com/docs/api-reference/fine-tunes
     * @deprecated Anthropic has deprecated this endpoint and will stop working by January 4, 2024.
     * https://openai.com/blog/gpt-3-5-turbo-fine-tuning-and-api-updates#updated-gpt-3-models
     */
    public function fineTunes(): FineTunesContract;

    /**
     * Given a input text, outputs if the model classifies it as violating Anthropic's content policy.
     *
     * @see https://platform.openai.com/docs/api-reference/moderations
     */
    public function moderations(): ModerationsContract;

    /**
     * Given a prompt and/or an input image, the model will generate a new image.
     *
     * @see https://platform.openai.com/docs/api-reference/images
     */
    public function images(): ImagesContract;

    /**
     * Build assistants that can call models and use tools to perform tasks.
     *
     * @see https://platform.openai.com/docs/api-reference/assistants
     */
    public function assistants(): AssistantsContract;

    /**
     * Create threads that assistants can interact with.
     *
     * @see https://platform.openai.com/docs/api-reference/threads
     */
    public function threads(): ThreadsContract;
}
