<?php

use Cognesy\Utils\Metadata;

describe('Metadata', function () {
    describe('construction', function () {
        it('creates empty metadata', function () {
            $metadata = Metadata::empty();
            expect($metadata->isEmpty())->toBeTrue();
            expect($metadata->toArray())->toBe([]);
        });

        it('creates from array', function () {
            $data = ['key1' => 'value1', 'key2' => 'value2'];
            $metadata = Metadata::fromArray($data);
            
            expect($metadata->isEmpty())->toBeFalse();
            expect($metadata->toArray())->toBe($data);
        });

        it('creates with constructor', function () {
            $data = ['custom' => 'field', 'another' => 123];
            $metadata = new Metadata($data);
            
            expect($metadata->toArray())->toBe($data);
        });
    });

    describe('immutable operations', function () {
        it('adds key-value pairs immutably', function () {
            $original = Metadata::fromArray(['existing' => 'value']);
            $updated = $original->withKeyValue('new', 'data');
            
            expect($original->toArray())->toBe(['existing' => 'value']);
            expect($updated->toArray())->toBe(['existing' => 'value', 'new' => 'data']);
        });

        it('removes keys immutably', function () {
            $original = Metadata::fromArray(['keep' => 'this', 'remove' => 'this']);
            $updated = $original->withoutKey('remove');
            
            expect($original->toArray())->toBe(['keep' => 'this', 'remove' => 'this']);
            expect($updated->toArray())->toBe(['keep' => 'this']);
        });

        it('overwrites existing keys', function () {
            $metadata = Metadata::fromArray(['key' => 'old_value']);
            $updated = $metadata->withKeyValue('key', 'new_value');
            
            expect($updated->toArray())->toBe(['key' => 'new_value']);
        });
    });

    describe('data access', function () {
        it('gets values by key', function () {
            $metadata = Metadata::fromArray(['name' => 'John', 'age' => 30]);
            
            expect($metadata->get('name'))->toBe('John');
            expect($metadata->get('age'))->toBe(30);
        });

        it('returns default for missing keys', function () {
            $metadata = Metadata::empty();
            
            expect($metadata->get('missing'))->toBeNull();
            expect($metadata->get('missing', 'default'))->toBe('default');
        });

        it('checks key existence', function () {
            $metadata = Metadata::fromArray(['exists' => null, 'present' => 'value']);
            
            expect($metadata->hasKey('exists'))->toBeTrue();
            expect($metadata->hasKey('present'))->toBeTrue();
            expect($metadata->hasKey('missing'))->toBeFalse();
        });

        it('gets all keys', function () {
            $metadata = Metadata::fromArray(['a' => 1, 'b' => 2, 'c' => 3]);
            
            expect($metadata->keys())->toBe(['a', 'b', 'c']);
        });
    });

    describe('state checking', function () {
        it('detects empty metadata', function () {
            $empty = Metadata::empty();
            $notEmpty = Metadata::fromArray(['key' => 'value']);
            
            expect($empty->isEmpty())->toBeTrue();
            expect($notEmpty->isEmpty())->toBeFalse();
        });
    });

    describe('OpenAI API usage patterns', function () {
        it('stores OpenAI-style image details', function () {
            $imageMetadata = Metadata::fromArray([
                'detail' => 'high',
                'alt_text' => 'A beautiful landscape'
            ]);
            
            expect($imageMetadata->get('detail'))->toBe('high');
            expect($imageMetadata->get('alt_text'))->toBe('A beautiful landscape');
        });

        it('stores audio transcription metadata', function () {
            $audioMetadata = Metadata::fromArray([
                'transcription' => 'Hello world',
                'language' => 'en',
                'confidence' => 0.95
            ]);
            
            expect($audioMetadata->get('transcription'))->toBe('Hello world');
            expect($audioMetadata->get('language'))->toBe('en');
            expect($audioMetadata->get('confidence'))->toBe(0.95);
        });

        it('stores file processing metadata', function () {
            $fileMetadata = Metadata::fromArray([
                'page_count' => 10,
                'processing_status' => 'completed',
                'extracted_text_length' => 5420
            ]);
            
            expect($fileMetadata->get('page_count'))->toBe(10);
            expect($fileMetadata->get('processing_status'))->toBe('completed');
        });

        it('can be chained to build complex metadata', function () {
            $metadata = Metadata::empty()
                ->withKeyValue('type', 'analysis')
                ->withKeyValue('confidence', 0.98)
                ->withKeyValue('tags', ['important', 'review'])
                ->withKeyValue('timestamp', '2025-01-15T10:30:00Z');
            
            expect($metadata->toArray())->toBe([
                'type' => 'analysis',
                'confidence' => 0.98, 
                'tags' => ['important', 'review'],
                'timestamp' => '2025-01-15T10:30:00Z'
            ]);
        });
    });

    describe('data types handling', function () {
        it('handles various data types', function () {
            $metadata = Metadata::fromArray([
                'string' => 'text',
                'integer' => 42,
                'float' => 3.14,
                'boolean' => true,
                'null' => null,
                'array' => ['a', 'b', 'c'],
                'nested' => ['key' => 'value']
            ]);
            
            expect($metadata->get('string'))->toBe('text');
            expect($metadata->get('integer'))->toBe(42);
            expect($metadata->get('float'))->toBe(3.14);
            expect($metadata->get('boolean'))->toBeTrue();
            expect($metadata->get('null'))->toBeNull();
            expect($metadata->get('array'))->toBe(['a', 'b', 'c']);
            expect($metadata->get('nested'))->toBe(['key' => 'value']);
        });
    });

    describe('edge cases', function () {
        it('handles removing non-existent keys gracefully', function () {
            $metadata = Metadata::fromArray(['keep' => 'this']);
            $result = $metadata->withoutKey('nonexistent');
            
            expect($result->toArray())->toBe(['keep' => 'this']);
        });

        it('handles empty string keys', function () {
            $metadata = Metadata::fromArray(['' => 'empty_key_value']);
            
            expect($metadata->get(''))->toBe('empty_key_value');
            expect($metadata->hasKey(''))->toBeTrue();
        });

        it('maintains order of keys', function () {
            $metadata = Metadata::fromArray(['z' => 1, 'a' => 2, 'm' => 3]);
            
            expect($metadata->keys())->toBe(['z', 'a', 'm']);
        });
    });

    describe('real-world usage scenarios', function () {
        it('supports content part enhancement workflow', function () {
            // Simulate enhancing a content part with metadata
            $baseMetadata = Metadata::fromArray(['type' => 'image_url']);
            
            $enhancedMetadata = $baseMetadata
                ->withKeyValue('detail', 'high')
                ->withKeyValue('analyzed', true)
                ->withKeyValue('objects_detected', ['person', 'car', 'building']);
            
            expect($enhancedMetadata->toArray())->toBe([
                'type' => 'image_url',
                'detail' => 'high',
                'analyzed' => true,
                'objects_detected' => ['person', 'car', 'building']
            ]);
        });

        it('supports conditional metadata building', function () {
            $metadata = Metadata::empty();

            $hasConfidence = true;
            $hasTimestamp = false;

            /** @phpstan-ignore-next-line */
            if ($hasConfidence) {
                $metadata = $metadata->withKeyValue('confidence', 0.87);
            }

            /** @phpstan-ignore-next-line */
            if ($hasTimestamp) {
                $metadata = $metadata->withKeyValue('timestamp', time());
            }

            expect($metadata->toArray())->toBe(['confidence' => 0.87]);
        });
    });
});