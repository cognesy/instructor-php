<?php

use Cognesy\Messages\Content;
use Cognesy\Messages\ContentPart;
use Cognesy\Messages\Message;

describe('Content', function () {
    describe('construction', function () {
        it('creates empty content with null', function () {
            $content = new Content(null);
            expect($content->isNull())->toBeTrue();
            expect($content->isEmpty())->toBeTrue();
        });

        it('creates content with string', function () {
            $content = new Content('Hello world');
            expect($content->isNull())->toBeFalse();
            expect($content->isEmpty())->toBeFalse();
            expect($content->toString())->toBe('Hello world');
        });

        it('creates content with array of strings', function () {
            $content = new Content(['Hello', 'world']);
            expect($content->parts())->toHaveCount(2);
            expect($content->toString())->toBe("Hello\nworld");
        });

        it('creates content with array of ContentPart objects', function () {
            $parts = [
                ContentPart::text('Hello'),
                ContentPart::text('world')
            ];
            $content = new Content($parts);
            expect($content->parts())->toHaveCount(2);
            expect($content->toString())->toBe("Hello\nworld");
        });

        it('creates content with single ContentPart object', function () {
            $part = ContentPart::text('Hello');
            $content = new Content($part);
            expect($content->parts())->toHaveCount(1);
            expect($content->toString())->toBe('Hello');
        });
    });

    describe('fromAny static method', function () {
        it('creates content from null', function () {
            $content = Content::fromAny(null);
            expect($content->isNull())->toBeTrue();
        });

        it('creates content from string', function () {
            $content = Content::fromAny('Hello');
            expect($content->toString())->toBe('Hello');
        });

        it('creates content from array of strings', function () {
            $content = Content::fromAny(['Hello', 'world']);
            expect($content->parts())->toHaveCount(2);
        });

        it('creates content from Content instance', function () {
            $original = new Content('Hello');
            $content = Content::fromAny($original);
            expect($content->toString())->toBe('Hello');
        });

        it('creates content from ContentPart instance', function () {
            $part = ContentPart::text('Hello');
            $content = Content::fromAny($part);
            expect($content->toString())->toBe('Hello');
        });

        it('creates content from message array', function () {
            $messageArray = ['role' => 'user', 'content' => 'Hello'];
            $content = Content::fromAny($messageArray);
            expect($content->toString())->toBe('Hello');
        });

    });

    describe('content part management', function () {
        it('adds content part', function () {
            $content = new Content();
            $part = ContentPart::text('Hello');
            $content->addContentPart($part);
            expect($content->parts())->toHaveCount(1);
            expect($content->toString())->toBe('Hello');
        });

        it('returns first content part', function () {
            $content = new Content(['Hello', 'world']);
            $firstPart = $content->firstContentPart();
            expect($firstPart)->toBeInstanceOf(ContentPart::class);
            expect($firstPart->toString())->toBe('Hello');
        });

        it('returns last content part', function () {
            $content = new Content(['Hello', 'world']);
            $lastPart = $content->lastContentPart();
            expect($lastPart)->toBeInstanceOf(ContentPart::class);
            expect($lastPart->toString())->toBe('world');
        });

        it('returns null for first/last part when empty', function () {
            $content = new Content();
            expect($content->firstContentPart())->toBeNull();
            expect($content->lastContentPart())->toBeNull();
        });
    });

    describe('state checking', function () {
        it('detects null content', function () {
            $content = new Content();
            expect($content->isNull())->toBeTrue();
        });

        it('detects empty content', function () {
            $content = new Content('');
            expect($content->isEmpty())->toBeTrue();
        });

        it('detects simple content', function () {
            $content = new Content('Hello');
            expect($content->isSimple())->toBeTrue();
        });

        it('detects composite content', function () {
            $content = new Content(['Hello', 'world']);
            expect($content->isComposite())->toBeTrue();
        });

        it('detects non-composite single text part', function () {
            $content = new Content('Hello');
            expect($content->isComposite())->toBeFalse();
        });
    });

    describe('conversion methods', function () {
        it('converts to array', function () {
            $content = new Content(['Hello', 'world']);
            $array = $content->toArray();
            expect($array)->toHaveCount(2);
            expect($array[0])->toHaveKey('type', 'text');
            expect($array[0])->toHaveKey('text', 'Hello');
        });

        it('converts empty content to empty array', function () {
            $content = new Content();
            expect($content->toArray())->toBe([]);
        });

        it('normalizes simple content to string', function () {
            $content = new Content('Hello');
            expect($content->normalized())->toBe('Hello');
        });

        it('normalizes composite content to array', function () {
            $content = new Content(['Hello', 'world']);
            $normalized = $content->normalized();
            expect($normalized)->toBeArray();
            expect($normalized)->toHaveCount(2);
        });

        it('normalizes null content to empty string', function () {
            $content = new Content();
            expect($content->normalized())->toBe('');
        });

        it('filters out empty parts in toString', function () {
            $content = new Content('Hello');
            $content->addContentPart(ContentPart::text(''));
            expect($content->toString())->toBe('Hello');
        });

        it('handles mixed empty and non-empty parts in toString', function () {
            $content = new Content();
            $content->addContentPart(ContentPart::text('Hello'));
            $content->addContentPart(ContentPart::text(''));
            $content->addContentPart(ContentPart::text('World'));
            $content->addContentPart(ContentPart::text(''));
            expect($content->toString())->toBe("Hello\nWorld");
        });
    });

    describe('cloning', function () {
        it('creates deep clone', function () {
            $content = new Content('Hello');
            $clone = $content->clone();
            expect($clone)->not->toBe($content);
            expect($clone->toString())->toBe('Hello');
        });

        it('clones with multiple parts', function () {
            $content = new Content(['Hello', 'world']);
            $clone = $content->clone();
            expect($clone->parts())->toHaveCount(2);
            expect($clone->toString())->toBe("Hello\nworld");
        });
    });

    describe('content field manipulation', function () {
        it('appends content field to last part', function () {
            $content = new Content('Hello');
            $content->appendContentField('custom', 'value');
            $lastPart = $content->lastContentPart();
            expect($lastPart->get('custom'))->toBe('value');
        });

        it('appends multiple content fields', function () {
            $content = new Content('Hello');
            $content->appendContentFields(['field1' => 'value1', 'field2' => 'value2']);
            $lastPart = $content->lastContentPart();
            expect($lastPart->get('field1'))->toBe('value1');
            expect($lastPart->get('field2'))->toBe('value2');
        });

        it('does not append to empty content', function () {
            $content = new Content();
            $result = $content->appendContentField('custom', 'value');
            expect($result)->toBe($content);
            expect($content->isNull())->toBeTrue();
        });
    });
});