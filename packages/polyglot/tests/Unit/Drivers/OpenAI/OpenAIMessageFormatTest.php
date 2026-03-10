<?php declare(strict_types=1);

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIMessageFormat;

it('preserves composite image content for openai chat completions', function () {
    $messages = Messages::fromArray([
        [
            'role' => 'user',
            'content' => [
                ['type' => 'text', 'text' => 'What is in this image?'],
                ['type' => 'image_url', 'image_url' => ['url' => 'data:image/png;base64,abc123']],
            ],
        ],
    ]);

    $result = (new OpenAIMessageFormat())->map($messages);

    expect($result)->toHaveCount(1)
        ->and($result[0]['role'])->toBe('user')
        ->and($result[0]['content'])->toHaveCount(2)
        ->and($result[0]['content'][0])->toBe([
            'type' => 'text',
            'text' => 'What is in this image?',
        ])
        ->and($result[0]['content'][1])->toBe([
            'type' => 'image_url',
            'image_url' => ['url' => 'data:image/png;base64,abc123'],
        ]);
});

it('merges image detail into openai image_url payloads', function () {
    $messages = Messages::fromArray([
        [
            'role' => 'user',
            'content' => [
                ['type' => 'image_url', 'url' => 'https://example.com/image.jpg', 'detail' => 'high'],
            ],
        ],
    ]);

    $result = (new OpenAIMessageFormat())->map($messages);

    expect($result[0]['content'][0])->toBe([
        'type' => 'image_url',
        'image_url' => [
            'url' => 'https://example.com/image.jpg',
            'detail' => 'high',
        ],
    ]);
});
