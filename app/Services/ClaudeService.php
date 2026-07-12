<?php

namespace App\Services;

use Anthropic\Client;

class ClaudeService
{
    private Client $client;
    private string $model;

    public function __construct()
    {
        $this->client = new Client(apiKey: config('services.anthropic.api_key'));
        $this->model  = config('services.anthropic.model', 'claude-opus-4-8');
    }

    public function interpretarDocumento(
        string $base64,
        string $mediaType,
        string $prompt,
        int    $maxTokens = 2048
    ): string {
        $contentType = str_starts_with($mediaType, 'image/') ? 'image' : 'document';

        $message = $this->client->messages->create(
            model: $this->model,
            maxTokens: $maxTokens,
            messages: [
                [
                    'role'    => 'user',
                    'content' => [
                        [
                            'type'   => $contentType,
                            'source' => [
                                'type'       => 'base64',
                                'media_type' => $mediaType,
                                'data'       => $base64,
                            ],
                        ],
                        [
                            'type' => 'text',
                            'text' => $prompt,
                        ],
                    ],
                ],
            ],
        );

        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                return $block->text;
            }
        }

        return '';
    }

    public function preguntar(string $prompt, int $maxTokens = 1024): string
    {
        $message = $this->client->messages->create(
            model: $this->model,
            maxTokens: $maxTokens,
            messages: [
                ['role' => 'user', 'content' => $prompt],
            ],
        );

        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                return $block->text;
            }
        }

        return '';
    }
}
