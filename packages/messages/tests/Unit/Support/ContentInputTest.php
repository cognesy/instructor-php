<?php

use Cognesy\Messages\Content;
use Cognesy\Messages\ContentPart;
use Cognesy\Messages\Message;

describe('ContentInput', function () {
    describe('single content part arrays (regression: instructor-r50t.1)', function () {
        it('treats a keyed text array as one part, not a list of parts', function () {
            $content = Content::fromAny(['type' => 'text', 'text' => 'hello']);

            expect($content->partsList()->count())->toBe(1);
            expect($content->partsList()->first()->type())->toBe('text');
            expect($content->toString())->toBe('hello');
        });

        it('treats a keyed image_url array as one image part', function () {
            $content = Content::fromAny([
                'type' => 'image_url',
                'image_url' => ['url' => 'https://example.com/y.png'],
            ]);

            expect($content->partsList()->count())->toBe(1);
            expect($content->toArray())->toBe([
                ['type' => 'image_url', 'image_url' => ['url' => 'https://example.com/y.png']],
            ]);
        });

        it('treats a keyed file array as one file part', function () {
            $content = Content::fromAny([
                'type' => 'file',
                'file' => ['file_data' => 'data:text/plain;base64,QQ==', 'file_name' => 'a.txt'],
            ]);

            expect($content->partsList()->count())->toBe(1);
            expect($content->partsList()->first()->type())->toBe('file');
        });

        it('treats a keyed input_audio array as one audio part', function () {
            $content = Content::fromAny([
                'type' => 'input_audio',
                'input_audio' => ['data' => 'QQ==', 'format' => 'wav'],
            ]);

            expect($content->partsList()->count())->toBe(1);
            expect($content->partsList()->first()->type())->toBe('input_audio');
        });

        it('routes a single part through Message::fromArray content too', function () {
            $message = Message::fromArray([
                'role' => 'user',
                'content' => ['type' => 'text', 'text' => 'hello'],
            ]);

            expect($message->content()->toString())->toBe('hello');
            expect($message->content()->partsList()->count())->toBe(1);
        });
    });

    describe('list forms still behave as before', function () {
        it('reads a list of part arrays as multiple parts', function () {
            $content = Content::fromAny([
                ['type' => 'text', 'text' => 'a'],
                ['type' => 'text', 'text' => 'b'],
            ]);

            expect($content->partsList()->count())->toBe(2);
            expect($content->toString())->toBe("a\nb");
        });

        it('reads a list of plain strings as text parts', function () {
            $content = Content::fromAny(['a', 'b']);

            expect($content->partsList()->count())->toBe(2);
            expect($content->toString())->toBe("a\nb");
        });

        it('reads a list of ContentPart objects', function () {
            $content = Content::fromAny([ContentPart::text('a'), ContentPart::text('b')]);

            expect($content->partsList()->count())->toBe(2);
        });

        it('reads an empty array as empty content', function () {
            expect(Content::fromAny([])->isEmpty())->toBeTrue();
        });
    });
});
