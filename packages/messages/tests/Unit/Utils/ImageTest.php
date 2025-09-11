<?php

use Cognesy\Messages\Utils\Image;
use Cognesy\Messages\ContentPart;
use Cognesy\Messages\Content;

describe('Image', function () {
    describe('construction', function () {
        it('creates image from URL', function () {
            $image = new Image('https://example.com/image.jpg', 'image/jpeg');
            expect($image->toImageUrl())->toBe('https://example.com/image.jpg');
            expect($image->getMimeType())->toBe('image/jpeg');
        });

        it('creates image from base64 data', function () {
            $base64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/5+hHgAHggJ/PchI7wAAAABJRU5ErkJggg==';
            $image = new Image($base64, 'image/png');
            expect($image->toImageUrl())->toBe($base64);
            expect($image->getMimeType())->toBe('image/png');
        });
    });

    describe('factory methods', function () {
        it('creates from URL', function () {
            $image = Image::fromUrl('https://example.com/test.png', 'image/png');
            expect($image->toImageUrl())->toBe('https://example.com/test.png');
            expect($image->getMimeType())->toBe('image/png');
        });

        it('creates from base64 with proper validation', function () {
            $base64 = 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQE=';
            $image = Image::fromBase64($base64, 'image/jpeg');
            expect($image->toImageUrl())->toBe($base64);
        });

        it('throws exception for invalid base64 format', function () {
            expect(fn() => Image::fromBase64('invalidbase64', 'image/jpeg'))
                ->toThrow(Exception::class);
        });
    });

    describe('OpenAI content part generation', function () {
        it('generates correct image_url content part structure from URL', function () {
            $image = Image::fromUrl('https://example.com/image.jpg', 'image/jpeg');
            $contentPart = $image->toContentPart();
            
            expect($contentPart->type())->toBe('image_url');
            expect($contentPart->toArray())->toBe([
                'type' => 'image_url',
                'image_url' => [
                    'url' => 'https://example.com/image.jpg'
                ]
            ]);
        });

        it('generates correct image_url content part structure from base64', function () {
            $base64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/5+hHgAHggJ/PchI7wAAAABJRU5ErkJggg==';
            $image = Image::fromBase64($base64, 'image/png');
            $contentPart = $image->toContentPart();
            
            expect($contentPart->toArray())->toBe([
                'type' => 'image_url',
                'image_url' => [
                    'url' => $base64
                ]
            ]);
        });

        it('creates content that works with Content class', function () {
            $image = Image::fromUrl('https://example.com/test.jpg', 'image/jpeg');
            $content = $image->toContent();
            
            expect($content->parts())->toHaveCount(1);
            expect($content->toArray()[0]['type'])->toBe('image_url');
        });
    });

    describe('message generation', function () {
        it('generates OpenAI compatible message array', function () {
            $image = Image::fromUrl('https://example.com/image.jpg', 'image/jpeg');
            $messageArray = $image->toArray();
            
            expect($messageArray)->toBe([
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'image_url',
                        'image_url' => ['url' => 'https://example.com/image.jpg']
                    ]
                ]
            ]);
        });

        it('generates message and messages objects', function () {
            $image = Image::fromUrl('https://example.com/test.png', 'image/png');
            
            $message = $image->toMessage();
            expect($message->role()->value)->toBe('user');
            expect($message->content()->parts())->toHaveCount(1);
            
            $messages = $image->toMessages();
            expect($messages->all())->toHaveCount(1);
        });
    });

    describe('OpenAI API compliance', function () {
        it('matches OpenAI image_url specification for URLs', function () {
            // According to OpenAI docs, image_url should have this structure:
            // {
            //   "type": "image_url",
            //   "image_url": {
            //     "url": "https://example.com/image.jpg",
            //     "detail": "auto" | "low" | "high"  // optional
            //   }
            // }
            
            $image = Image::fromUrl('https://upload.wikimedia.org/wikipedia/commons/thumb/d/dd/Gfp-wisconsin-madison-the-nature-boardwalk.jpg/2560px-Gfp-wisconsin-madison-the-nature-boardwalk.jpg', 'image/jpeg');
            $structure = $image->toContentPart()->toArray();
            
            expect($structure)->toHaveKey('type', 'image_url');
            expect($structure)->toHaveKey('image_url');
            expect($structure['image_url'])->toHaveKey('url');
            expect($structure['image_url']['url'])->toStartWith('https://');
        });

        it('matches OpenAI specification for base64 images', function () {
            $base64 = 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQE=';
            $image = Image::fromBase64($base64, 'image/jpeg');
            $structure = $image->toContentPart()->toArray();
            
            expect($structure['image_url']['url'])->toStartWith('data:image/');
            expect($structure['image_url']['url'])->toContain(';base64,');
        });

        it('produces valid structure for Chat Completions API multimodal messages', function () {
            $image = Image::fromUrl('https://example.com/chart.png', 'image/png');
            
            // Simulate a multimodal message like:
            // {
            //   "role": "user", 
            //   "content": [
            //     {"type": "text", "text": "What's in this image?"},
            //     {"type": "image_url", "image_url": {"url": "https://example.com/chart.png"}}
            //   ]
            // }
            
            $textPart = ['type' => 'text', 'text' => "What's in this image?"];
            $imagePart = $image->toContentPart()->toArray();
            
            $multimodalContent = [$textPart, $imagePart];
            
            expect($multimodalContent[1]['type'])->toBe('image_url');
            expect($multimodalContent[1]['image_url']['url'])->toBe('https://example.com/chart.png');
        });
    });

    describe('edge cases', function () {
        it('handles various image formats', function () {
            $formats = [
                'image/jpeg' => 'https://example.com/image.jpg',
                'image/png' => 'https://example.com/image.png', 
                'image/gif' => 'https://example.com/image.gif',
                'image/webp' => 'https://example.com/image.webp'
            ];
            
            foreach ($formats as $mimeType => $url) {
                $image = Image::fromUrl($url, $mimeType);
                expect($image->getMimeType())->toBe($mimeType);
                expect($image->toContentPart()->type())->toBe('image_url');
            }
        });

        it('handles empty URLs gracefully', function () {
            $image = new Image('', 'image/png');
            $contentPart = $image->toContentPart();
            
            expect($contentPart->toArray()['image_url']['url'])->toBe('');
        });
    });
});