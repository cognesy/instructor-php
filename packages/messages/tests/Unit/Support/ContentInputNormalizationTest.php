<?php declare(strict_types=1);

use Cognesy\Messages\Support\ContentInput;

describe('ContentInput::normalizeFields', function () {
    describe('unregistered / text type', function () {
        it('passes fields through unchanged for the text type', function () {
            $fields = ContentInput::normalizeFields('text', ['text' => 'hi']);

            expect($fields)->toBe(['text' => 'hi']);
        });

        it('passes fields through unchanged for an unrecognized type', function () {
            $fields = ContentInput::normalizeFields('custom', ['foo' => 'bar']);

            expect($fields)->toBe(['foo' => 'bar']);
        });
    });

    describe('image_url aliasing', function () {
        it('leaves fields unchanged when neither image_url nor url is present', function () {
            $fields = ContentInput::normalizeFields('image_url', ['detail' => 'high']);

            expect($fields)->toBe(['detail' => 'high']);
        });

        it('promotes a top-level url into an image_url object', function () {
            $fields = ContentInput::normalizeFields('image_url', ['url' => 'https://example.com/a.png']);

            expect($fields)->toBe(['image_url' => ['url' => 'https://example.com/a.png']]);
        });

        it('wraps a string image_url into an object form', function () {
            $fields = ContentInput::normalizeFields('image_url', ['image_url' => 'https://example.com/a.png']);

            expect($fields)->toBe(['image_url' => ['url' => 'https://example.com/a.png']]);
        });

        it('merges a top-level url into an image_url object that lacks one', function () {
            $fields = ContentInput::normalizeFields('image_url', [
                'image_url' => ['detail' => 'high'],
                'url' => 'https://example.com/b.png',
            ]);

            expect($fields)->toBe([
                'image_url' => ['detail' => 'high', 'url' => 'https://example.com/b.png'],
            ]);
        });

        it('keeps the image_url object url and strips a conflicting top-level url', function () {
            $fields = ContentInput::normalizeFields('image_url', [
                'image_url' => ['url' => 'https://example.com/own.png'],
                'url' => 'https://example.com/other.png',
            ]);

            expect($fields)->toBe([
                'image_url' => ['url' => 'https://example.com/own.png'],
            ]);
        });

        it('leaves an image_url object without url unchanged when there is no top-level url', function () {
            $fields = ContentInput::normalizeFields('image_url', [
                'image_url' => ['detail' => 'high'],
            ]);

            expect($fields)->toBe([
                'image_url' => ['detail' => 'high'],
            ]);
        });

        it('preserves unrelated keys already present on the image_url object', function () {
            $fields = ContentInput::normalizeFields('image_url', [
                'image_url' => ['url' => 'https://example.com/a.png', 'detail' => 'low'],
            ]);

            expect($fields)->toBe([
                'image_url' => ['url' => 'https://example.com/a.png', 'detail' => 'low'],
            ]);
        });
    });

    describe('file aliasing', function () {
        it('leaves fields unchanged when there is no file key or file_* alias', function () {
            $fields = ContentInput::normalizeFields('file', ['caption' => 'x']);

            expect($fields)->toBe(['caption' => 'x']);
        });

        it('builds a file object from top-level file_data/file_name aliases', function () {
            $fields = ContentInput::normalizeFields('file', [
                'file_data' => 'data:text/plain;base64,QQ==',
                'file_name' => 'a.txt',
            ]);

            expect($fields)->toBe([
                'file' => ['file_data' => 'data:text/plain;base64,QQ==', 'file_name' => 'a.txt'],
            ]);
        });

        it('accepts the filename alias for file_name', function () {
            $fields = ContentInput::normalizeFields('file', [
                'file_data' => 'd',
                'filename' => 'a.txt',
            ]);

            expect($fields)->toBe([
                'file' => ['file_data' => 'd', 'file_name' => 'a.txt'],
            ]);
        });

        it('merges a missing file_name from the filename alias into an existing file object', function () {
            $fields = ContentInput::normalizeFields('file', [
                'file' => ['file_data' => 'd'],
                'filename' => 'a.txt',
            ]);

            expect($fields)->toBe([
                'file' => ['file_data' => 'd', 'file_name' => 'a.txt'],
            ]);
        });

        it('merges a missing file_id from the top-level alias into an existing file object', function () {
            $fields = ContentInput::normalizeFields('file', [
                'file' => ['file_data' => 'd'],
                'file_id' => 'file-123',
            ]);

            expect($fields)->toBe([
                'file' => ['file_data' => 'd', 'file_id' => 'file-123'],
            ]);
        });

        it('keeps the file object own value over a conflicting top-level alias', function () {
            $fields = ContentInput::normalizeFields('file', [
                'file' => ['file_data' => 'own'],
                'file_data' => 'other',
            ]);

            expect($fields)->toBe([
                'file' => ['file_data' => 'own'],
            ]);
        });

        it('strips file_data/file_name/file_id/filename aliases from the top level in all cases', function () {
            $fields = ContentInput::normalizeFields('file', [
                'file' => ['file_data' => 'd'],
                'file_name' => 'ignored',
                'filename' => 'ignored-too',
                'file_id' => 'ignored-id',
            ]);

            expect(array_keys($fields))->toBe(['file']);
        });

        it('drops any key inside the file object other than file_data/file_name/file_id', function () {
            // ACTUAL BEHAVIOUR, likely a bug: mergeFilePayload() only ever extracts
            // file_data/file_name/file_id from the incoming payload and rebuilds a
            // fresh array from those three keys - any other key already present on
            // an existing 'file' object (e.g. a provider-specific field) is silently
            // dropped whenever normalizeFileFields() runs. See report for triage.
            $fields = ContentInput::normalizeFields('file', [
                'file' => ['file_data' => 'd', 'mime_type' => 'text/plain'],
            ]);

            expect($fields)->toBe([
                'file' => ['file_data' => 'd'],
            ]);
        });
    });

    describe('input_audio aliasing', function () {
        it('leaves fields unchanged when there is no input_audio key or data/format alias', function () {
            $fields = ContentInput::normalizeFields('input_audio', ['caption' => 'x']);

            expect($fields)->toBe(['caption' => 'x']);
        });

        it('builds an input_audio object from top-level data/format aliases', function () {
            $fields = ContentInput::normalizeFields('input_audio', [
                'data' => 'QQ==',
                'format' => 'wav',
            ]);

            expect($fields)->toBe([
                'input_audio' => ['data' => 'QQ==', 'format' => 'wav'],
            ]);
        });

        it('builds an input_audio object from data alone when format is absent', function () {
            $fields = ContentInput::normalizeFields('input_audio', ['data' => 'QQ==']);

            expect($fields)->toBe(['input_audio' => ['data' => 'QQ==']]);
        });

        it('merges a missing format from the top-level alias into an existing input_audio object', function () {
            $fields = ContentInput::normalizeFields('input_audio', [
                'input_audio' => ['data' => 'QQ=='],
                'format' => 'wav',
            ]);

            expect($fields)->toBe([
                'input_audio' => ['data' => 'QQ==', 'format' => 'wav'],
            ]);
        });

        it('keeps the input_audio object own value over a conflicting top-level alias', function () {
            $fields = ContentInput::normalizeFields('input_audio', [
                'input_audio' => ['data' => 'own'],
                'data' => 'other',
            ]);

            expect($fields)->toBe([
                'input_audio' => ['data' => 'own'],
            ]);
        });

        it('strips data/format aliases from the top level in all cases', function () {
            $fields = ContentInput::normalizeFields('input_audio', [
                'input_audio' => ['data' => 'QQ=='],
                'format' => 'ignored',
            ]);

            expect(array_keys($fields))->toBe(['input_audio']);
        });

        it('drops any key inside the input_audio object other than data/format', function () {
            // ACTUAL BEHAVIOUR, likely the same bug as the file case above:
            // mergeAudioPayload() only ever extracts data/format and rebuilds a
            // fresh array, silently dropping any other key already on an existing
            // 'input_audio' object. See report for triage.
            $fields = ContentInput::normalizeFields('input_audio', [
                'input_audio' => ['data' => 'QQ==', 'sample_rate' => 16000],
            ]);

            expect($fields)->toBe([
                'input_audio' => ['data' => 'QQ=='],
            ]);
        });
    });

    describe('integration via ContentPart::fromArray', function () {
        it('normalizes an image_url alias through the public ContentPart entry point', function () {
            $part = \Cognesy\Messages\ContentPart::fromArray([
                'type' => 'image_url',
                'url' => 'https://example.com/a.png',
            ]);

            expect($part->get('image_url'))->toBe(['url' => 'https://example.com/a.png']);
        });

        it('normalizes a file alias through the public ContentPart entry point', function () {
            $part = \Cognesy\Messages\ContentPart::fromArray([
                'type' => 'file',
                'file_data' => 'd',
                'file_name' => 'a.txt',
            ]);

            expect($part->get('file'))->toBe(['file_data' => 'd', 'file_name' => 'a.txt']);
        });
    });
});
