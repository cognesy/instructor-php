<?php

use Cognesy\Messages\Content;
use Cognesy\Messages\ContentPart;

describe('Content', function () {
    describe('construction', function () {
        it('creates empty content with null', function () {
            $content = Content::fromAny(null);
            expect($content->isEmpty())->toBeTrue();
        });

        it('creates content with string', function () {
            $content = Content::text('Hello world');
            expect($content->isEmpty())->toBeFalse();
            expect($content->toString())->toBe('Hello world');
        });

        it('creates content with array of strings', function () {
            $content = Content::texts(...['Hello', 'world']);
            expect($content->partsList()->count())->toBe(2);
            expect($content->toString())->toBe("Hello\nworld");
        });

        it('creates content with array of ContentPart objects', function () {
            $parts = [
                ContentPart::text('Hello'),
                ContentPart::text('world')
            ];
            $content = Content::fromAny($parts);
            expect($content->partsList()->count())->toBe(2);
            expect($content->toString())->toBe("Hello\nworld");
        });

        it('creates content with single ContentPart object', function () {
            $part = ContentPart::text('Hello');
            $content = new Content($part);
            expect($content->partsList()->count())->toBe(1);
            expect($content->toString())->toBe('Hello');
        });

        it('creates content from ContentParts', function () {
            $parts = \Cognesy\Messages\ContentParts::fromArray([
                ContentPart::text('Hello'),
                ContentPart::text('World'),
            ]);
            $content = Content::fromParts($parts);
            expect($content->toString())->toBe("Hello\nWorld");
        });
    });

    describe('accessors', function () {
        it('returns content parts list', function () {
            $content = Content::texts(...['Hello', 'world']);
            $parts = $content->partsList();
            expect($parts)->toBeInstanceOf(\Cognesy\Messages\ContentParts::class)
                ->and($parts->count())->toBe(2);
        });
    });

    describe('fromAny static method', function () {
        it('creates content from string', function () {
            $content = Content::fromAny('Hello');
            expect($content->toString())->toBe('Hello');
        });

        it('creates content from array of strings', function () {
            $content = Content::fromAny(['Hello', 'world']);
            expect($content->partsList()->count())->toBe(2);
        });

        it('creates content from Content instance', function () {
            $original = Content::text('Hello');
            $content = Content::fromAny($original);
            expect($content->toString())->toBe('Hello');
        });

        it('creates content from ContentPart instance', function () {
            $part = ContentPart::text('Hello');
            $content = Content::fromAny($part);
            expect($content->toString())->toBe('Hello');
        });

        it('creates content from ContentParts instance', function () {
            $parts = \Cognesy\Messages\ContentParts::fromArray([
                ContentPart::text('Hello'),
                ContentPart::text('World'),
            ]);
            $content = Content::fromAny($parts);
            expect($content->toString())->toBe("Hello\nWorld");
        });

        it('creates content from message array', function () {
            $messageArray = ['role' => 'user', 'content' => 'Hello'];
            $content = Content::fromAny($messageArray);
            expect($content->toString())->toBe('Hello');
        });

        it('creates composite content from message array', function () {
            $messageArray = [
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => 'Hello'],
                    ['type' => 'text', 'text' => 'World'],
                ],
            ];
            $content = Content::fromAny($messageArray);
            expect($content->isComposite())->toBeTrue()
                ->and($content->toArray())->toBe([
                    ['type' => 'text', 'text' => 'Hello'],
                    ['type' => 'text', 'text' => 'World'],
                ]);
        });

    });

    describe('content part management', function () {
        it('adds content part', function () {
            $content = Content::empty();
            $part = ContentPart::text('Hello');
            $content = $content->addContentPart($part);
            expect($content->partsList()->count())->toBe(1);
            expect($content->toString())->toBe('Hello');
        });
    });

    describe('state checking', function () {
        it('detects empty content', function () {
            $content = Content::text('');
            expect($content->isEmpty())->toBeTrue();
        });

        it('detects composite content', function () {
            $content = Content::texts(...['Hello', 'world']);
            expect($content->isComposite())->toBeTrue();
        });

        it('detects non-composite single text part', function () {
            $content = Content::text('Hello');
            expect($content->isComposite())->toBeFalse();
        });
    });

    describe('conversion methods', function () {
        it('converts to array', function () {
            $content = Content::texts(...['Hello', 'world']);
            $array = $content->toArray();
            expect($array)->toHaveCount(2);
            expect($array[0])->toHaveKey('type', 'text');
            expect($array[0])->toHaveKey('text', 'Hello');
        });

        it('converts empty content to empty array', function () {
            $content = Content::empty();
            expect($content->toArray())->toBe([]);
        });

        it('normalizes simple content to string', function () {
            $content = Content::text('Hello');
            expect($content->normalized())->toBe('Hello');
        });

        it('normalizes composite content to array', function () {
            $content = Content::texts(...['Hello', 'world']);
            $normalized = $content->normalized();
            expect($normalized)->toBeArray();
            expect($normalized)->toHaveCount(2);
        });

        it('normalizes null content to empty string', function () {
            $content = Content::empty();
            expect($content->normalized())->toBe('');
        });

        it('filters out empty parts in toString', function () {
            $content = Content::text('Hello');
            $content->addContentPart(ContentPart::text(''));
            expect($content->toString())->toBe('Hello');
        });

        it('handles mixed empty and non-empty parts in toString', function () {
            $content = Content::empty();
            $content = $content->addContentPart(ContentPart::text('Hello'));
            $content = $content->addContentPart(ContentPart::text(''));
            $content = $content->addContentPart(ContentPart::text('World'));
            $content = $content->addContentPart(ContentPart::text(''));
            expect($content->toString())->toBe("Hello\nWorld");
        });
    });

    describe('reconstruction', function () {
        it('rebuilds content from existing parts', function () {
            $content = Content::text('Hello');
            $rebuilt = Content::fromParts($content->partsList());
            expect($rebuilt)->not->toBe($content);
            expect($rebuilt->toString())->toBe('Hello');
        });

        it('rebuilds content with multiple parts', function () {
            $content = Content::texts(...['Hello', 'world']);
            $rebuilt = Content::fromParts($content->partsList());
            expect($rebuilt->partsList()->count())->toBe(2);
            expect($rebuilt->toString())->toBe("Hello\nworld");
        });
    });

    describe('content field manipulation', function () {
        it('appends content field to last part', function () {
            $content = Content::text('Hello');
            $content = $content->appendContentField('custom', 'value');
            expect($content->toArray())->toBe([
                ['type' => 'text', 'text' => 'Hello', 'custom' => 'value']
            ]);
        });

        it('appends field to empty content', function () {
            $content = Content::empty();
            $result = $content->appendContentField('custom', 'value');
            expect($result->toArray())->toBe([
                ['type' => 'text', 'custom' => 'value']
            ]);
        });

        it('appends to text content part', function () {
            $content = Content::text('Hello');
            $result = $content->appendContentField('custom', 'world');
            expect($result->toArray())->toBe([
                ['type' => 'text', 'text' => 'Hello', 'custom' => 'world']
            ]);
        });

        it('appends to image_url content part with url field', function () {
            $content = new Content(ContentPart::imageUrl('https://example.com/image.jpg'));
            $result = $content->appendContentField('detail', 'high');
            expect($result->toArray())->toBe([
                [
                    'type' => 'image_url',
                    'image_url' => ['url' => 'https://example.com/image.jpg'],
                    'detail' => 'high'
                ]
            ]);
        });

        it('handles nested image_url structure like OpenAI API', function () {
            $part = new ContentPart('image_url', [
                'image_url' => [
                    'url' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/d/dd/Gfp-wisconsin-madison-the-nature-boardwalk.jpg/2560px-Gfp-wisconsin-madison-the-nature-boardwalk.jpg',
                    'detail' => 'auto'
                ]
            ]);
            $content = new Content($part);
            $result = $content->appendContentField('alt_text', 'Nature boardwalk');
            
            expect($result->toArray())->toBe([
                [
                    'type' => 'image_url',
                    'image_url' => [
                        'url' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/d/dd/Gfp-wisconsin-madison-the-nature-boardwalk.jpg/2560px-Gfp-wisconsin-madison-the-nature-boardwalk.jpg',
                        'detail' => 'auto'
                    ],
                    'alt_text' => 'Nature boardwalk'
                ]
            ]);
        });

        it('handles audio content parts like OpenAI input_audio type', function () {
            $audioPart = new ContentPart('input_audio', [
                'input_audio' => [
                    'data' => 'UklGRiQAAABXQVZFZm10IBAAAAABAAEARKwAAIhYAQACABAAZGF0YQAAAAA=',
                    'format' => 'wav'
                ]
            ]);
            $content = new Content($audioPart);
            $result = $content->appendContentField('transcription', 'Hello world');
            
            expect($result->toArray())->toBe([
                [
                    'type' => 'input_audio',
                    'input_audio' => [
                        'data' => 'UklGRiQAAABXQVZFZm10IBAAAAABAAEARKwAAIhYAQACABAAZGF0YQAAAAA=',
                        'format' => 'wav'
                    ],
                    'transcription' => 'Hello world'
                ]
            ]);
        });

        it('handles file content parts with file_data', function () {
            $filePart = new ContentPart('file', [
                'file' => [
                    'file_data' => 'base64encodedfiledata',
                    'file_name' => 'report.pdf'
                ]
            ]);
            $content = new Content($filePart);
            $result = $content->appendContentField('page_count', 42);
            
            expect($result->toArray())->toBe([
                [
                    'type' => 'file',
                    'file' => [
                        'file_data' => 'base64encodedfiledata',
                        'file_name' => 'report.pdf'
                    ],
                    'page_count' => 42
                ]
            ]);
        });

        it('handles file content parts with file_id', function () {
            $filePart = new ContentPart('file', [
                'file' => [
                    'file_id' => 'file-BK7bzQj3FfUp6VNGYLssxKcE',
                    'file_name' => 'document.pdf'
                ]
            ]);
            $content = new Content($filePart);
            $result = $content->appendContentField('processing_status', 'completed');
            
            expect($result->toArray())->toBe([
                [
                    'type' => 'file',
                    'file' => [
                        'file_id' => 'file-BK7bzQj3FfUp6VNGYLssxKcE',
                        'file_name' => 'document.pdf'
                    ],
                    'processing_status' => 'completed'
                ]
            ]);
        });

        it('handles complex multipart content with mixed OpenAI types', function () {
            $content = new Content(
                ContentPart::text('What is in this image?'),
                new ContentPart('image_url', [
                    'image_url' => [
                        'url' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/d/dd/Gfp-wisconsin-madison-the-nature-boardwalk.jpg/2560px-Gfp-wisconsin-madison-the-nature-boardwalk.jpg',
                        'detail' => 'high'
                    ]
                ]),
                new ContentPart('input_audio', [
                    'input_audio' => [
                        'data' => 'UklGRiQAAABXQVZFZm10IBAAAAABAAEARKwAAIhYAQACABAAZGF0YQAAAAA=',
                        'format' => 'wav'
                    ]
                ])
            );
            $result = $content->appendContentField('analysis_complete', true);
            
            $expected = [
                ['type' => 'text', 'text' => 'What is in this image?'],
                [
                    'type' => 'image_url',
                    'image_url' => [
                        'url' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/d/dd/Gfp-wisconsin-madison-the-nature-boardwalk.jpg/2560px-Gfp-wisconsin-madison-the-nature-boardwalk.jpg',
                        'detail' => 'high'
                    ]
                ],
                [
                    'type' => 'input_audio',
                    'input_audio' => [
                        'data' => 'UklGRiQAAABXQVZFZm10IBAAAAABAAEARKwAAIhYAQACABAAZGF0YQAAAAA=',
                        'format' => 'wav'
                    ],
                    'analysis_complete' => true
                ]
            ];
            expect($result->toArray())->toBe($expected);
        });
    });
});
