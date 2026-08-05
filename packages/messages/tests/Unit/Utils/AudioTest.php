<?php

use Cognesy\Messages\Utils\Audio;
use Cognesy\Messages\ContentPart;

describe('Audio', function () {
    describe('construction', function () {
        it('creates audio with format and base64 data', function () {
            $audio = new Audio('wav', 'UklGRiQAAABXQVZFZm10IBAAAAABAAEARKwAAIhYAQACABAAZGF0YQAAAAA=');
            expect($audio->format())->toBe('wav');
            expect($audio->getBase64Bytes())->toBe('UklGRiQAAABXQVZFZm10IBAAAAABAAEARKwAAIhYAQACABAAZGF0YQAAAAA=');
        });

        it('supports mp3 format', function () {
            $audio = new Audio('mp3', 'SUQzBAAAAAAAI1RTU0UAAAAPAAADAAAAAFNTT0RFAA==');
            expect($audio->format())->toBe('mp3');
        });
    });

    describe('OpenAI content part generation', function () {
        it('generates correct input_audio content part structure', function () {
            $audio = new Audio('wav', 'UklGRiQAAABXQVZFZm10IBAAAAABAAEARKwAAIhYAQACABAAZGF0YQAAAAA=');
            $contentPart = $audio->toContentPart();
            
            expect($contentPart->type())->toBe('input_audio');
            expect($contentPart->toArray())->toBe([
                'type' => 'input_audio',
                'input_audio' => [
                    'format' => 'wav',
                    'data' => 'UklGRiQAAABXQVZFZm10IBAAAAABAAEARKwAAIhYAQACABAAZGF0YQAAAAA='
                ]
            ]);
        });

        it('generates correct mp3 content part structure', function () {
            $audio = new Audio('mp3', 'SUQzBAAAAAAAI1RTU0UAAAAPAAADAAAAAFNTT0RFAA==');
            $contentPart = $audio->toContentPart();
            
            expect($contentPart->toArray())->toBe([
                'type' => 'input_audio',
                'input_audio' => [
                    'format' => 'mp3',
                    'data' => 'SUQzBAAAAAAAI1RTU0UAAAAPAAADAAAAAFNTT0RFAA=='
                ]
            ]);
        });

        it('creates content part that works with ContentPart factory methods', function () {
            $audio = new Audio('wav', 'testdata');
            $contentPart = $audio->toContentPart();
            $fromAnyPart = ContentPart::fromAny($audio);
            
            expect($fromAnyPart->toArray())->toBe($contentPart->toArray());
        });
    });

    describe('OpenAI API compliance', function () {
        it('matches OpenAI input_audio specification', function () {
            // According to OpenAI docs, input_audio should have this structure:
            // {
            //   "type": "input_audio",  
            //   "input_audio": {
            //     "data": "base64_encoded_audio",
            //     "format": "wav" | "mp3"
            //   }
            // }
            
            $audio = new Audio('wav', 'SGVsbG8gV29ybGQ=');
            $structure = $audio->toContentPart()->toArray();
            
            expect($structure)->toHaveKey('type', 'input_audio');
            expect($structure)->toHaveKey('input_audio');
            expect($structure['input_audio'])->toHaveKey('data');
            expect($structure['input_audio'])->toHaveKey('format'); 
            expect($structure['input_audio']['format'])->toBeIn(['wav', 'mp3']);
        });

        it('produces valid structure for Chat Completions API', function () {
            $audio = new Audio('wav', 'dGVzdCBhdWRpbyBkYXRh');
            $contentPart = $audio->toContentPart();
            
            // Verify it can be used in a message structure like:
            // "messages": [{"role": "user", "content": [contentPart.toArray()]}]
            $messageContent = [$contentPart->toArray()];
            
            expect($messageContent[0]['type'])->toBe('input_audio');
            expect($messageContent[0]['input_audio']['data'])->toBe('dGVzdCBhdWRpbyBkYXRh');
            expect($messageContent[0]['input_audio']['format'])->toBe('wav');
        });
    });

    describe('edge cases', function () {
        it('handles empty audio data', function () {
            // regression: instructor-r50t.19 - empty input_audio.data is omitted, not sent as ""
            $audio = new Audio('wav', '');
            $contentPart = $audio->toContentPart();

            expect($contentPart->toArray()['input_audio'])->not->toHaveKey('data');
            expect($contentPart->toArray()['input_audio']['format'])->toBe('wav');
        });

        it('handles various audio formats', function () {
            $formats = ['wav', 'mp3', 'flac', 'ogg'];

            foreach ($formats as $format) {
                $audio = new Audio($format, 'dGVzdA==');
                $contentPart = $audio->toContentPart();

                expect($contentPart->toArray()['input_audio']['format'])->toBe($format);
            }
        });

        it('omits the input_audio payload entirely when format and data are both empty (regression: instructor-r50t.19)', function () {
            $audio = new Audio('', '');

            expect($audio->toContentPart()->toArray())->toBe(['type' => 'input_audio']);
        });
    });

    describe('deprecated alias removal', function () {
        it('no longer exposes getByte64Bytes() (regression: instructor-r50t.19)', function () {
            expect(method_exists(Audio::class, 'getByte64Bytes'))->toBeFalse();
        });
    });
});
