<?php

namespace Anthropic\Contracts\Resources;

use Anthropic\Responses\Audio\SpeechStreamResponse;
use Anthropic\Responses\Audio\TranscriptionResponse;
use Anthropic\Responses\Audio\TranslationResponse;

interface AudioContract
{
    /**
     * Generates audio from the input text.
     *
     * @see https://platform.openai.com/docs/api-reference/audio/createSpeech
     *
     * @param  array<string, mixed>  $parameters
     */
    public function speech(array $parameters): string;

    /**
     * Generates streamed audio from the input text.
     *
     * @see https://platform.openai.com/docs/api-reference/audio/createSpeech
     *
     * @param  array<string, mixed>  $parameters
     */
    public function speechStreamed(array $parameters): SpeechStreamResponse;

    /**
     * Transcribes audio into the input language.
     *
     * @see https://platform.openai.com/docs/api-reference/audio/createTranscription
     *
     * @param  array<string, mixed>  $parameters
     */
    public function transcribe(array $parameters): TranscriptionResponse;

    /**
     * Translates audio into English.
     *
     * @see https://platform.openai.com/docs/api-reference/audio/createTranslation
     *
     * @param  array<string, mixed>  $parameters
     */
    public function translate(array $parameters): TranslationResponse;
}
