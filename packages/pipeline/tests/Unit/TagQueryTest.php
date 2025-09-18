<?php declare(strict_types=1);

use Cognesy\Pipeline\ProcessingState;
use Cognesy\Utils\TagMap\Contracts\TagInterface;

class TagQueryTestTag implements TagInterface {
    public function __construct(public readonly string $name) {}
}

class AnotherTestTag implements TagInterface {
    public function __construct(public readonly string $value) {}
}

describe('TagQuery', function () {
    
    describe('filtering operations', function () {
        it('filters by type with ofType()', function () {
            $tag1 = new TagQueryTestTag('first');
            $tag2 = new AnotherTestTag('second');
            $tag3 = new TagQueryTestTag('third');
            
            $state = ProcessingState::with(null, [$tag1, $tag2, $tag3]);
            $filtered = $state->tags()->ofType(TagQueryTestTag::class)->all();
            
            expect($filtered)->toHaveCount(2);
            expect($filtered[0])->toBe($tag1);
            expect($filtered[1])->toBe($tag3);
        });

        it('filters with where() predicate', function () {
            $tag1 = new TagQueryTestTag('match');
            $tag2 = new TagQueryTestTag('nomatch');
            $tag3 = new TagQueryTestTag('match');
            
            $state = ProcessingState::with(null, [$tag1, $tag2, $tag3]);
            $filtered = $state->tags()->filter(fn($tag) => $tag->name === 'match')->all();
            
            expect($filtered)->toHaveCount(2);
            expect($filtered[0])->toBe($tag1);
            expect($filtered[1])->toBe($tag3);
        });

        it('filters with only() for specific classes', function () {
            $tag1 = new TagQueryTestTag('test');
            $tag2 = new AnotherTestTag('another');
            $tag3 = new TagQueryTestTag('test2');
            
            $state = ProcessingState::with(null, [$tag1, $tag2, $tag3]);
            $filtered = $state->tags()->only(TagQueryTestTag::class)->all();
            
            expect($filtered)->toHaveCount(2);
            expect($filtered[0])->toBe($tag1);
            expect($filtered[1])->toBe($tag3);
        });

        it('excludes classes with without()', function () {
            $tag1 = new TagQueryTestTag('test');
            $tag2 = new AnotherTestTag('another');
            $tag3 = new TagQueryTestTag('test2');
            
            $state = ProcessingState::with(null, [$tag1, $tag2, $tag3]);
            $filtered = $state->tags()->without(AnotherTestTag::class)->all();
            
            expect($filtered)->toHaveCount(2);
            expect($filtered[0])->toBe($tag1);
            expect($filtered[1])->toBe($tag3);
        });
    });

    describe('slicing operations', function () {
        it('limits results with limit()', function () {
            $tags = [
                new TagQueryTestTag('first'),
                new TagQueryTestTag('second'),
                new TagQueryTestTag('third'),
            ];
            
            $state = ProcessingState::with(null, $tags);
            $limited = $state->tags()->limit(2)->all();
            
            expect($limited)->toHaveCount(2);
            expect($limited[0])->toBe($tags[0]);
            expect($limited[1])->toBe($tags[1]);
        });

        it('skips elements with skip()', function () {
            $tags = [
                new TagQueryTestTag('first'),
                new TagQueryTestTag('second'),
                new TagQueryTestTag('third'),
            ];
            
            $state = ProcessingState::with(null, $tags);
            $skipped = $state->tags()->skip(1)->all();
            
            expect($skipped)->toHaveCount(2);
            expect($skipped[0])->toBe($tags[1]);
            expect($skipped[1])->toBe($tags[2]);
        });
    });

    describe('terminal operations', function () {
        it('counts elements with count()', function () {
            $tags = [
                new TagQueryTestTag('first'),
                new AnotherTestTag('second'),
            ];
            
            $state = ProcessingState::with(null, $tags);
            
            expect($state->tags()->count())->toBe(2);
            expect($state->tags()->ofType(TagQueryTestTag::class)->count())->toBe(1);
        });

        it('checks if empty with empty()', function () {
            $emptyState = ProcessingState::with(null, []);
            $taggedState = ProcessingState::with(null, [new TagQueryTestTag('test')]);
            
            expect($emptyState->tags()->isEmpty())->toBeTrue();
            expect($taggedState->tags()->isEmpty())->toBeFalse();
        });

        it('gets first element with first()', function () {
            $tags = [
                new TagQueryTestTag('first'),
                new TagQueryTestTag('second'),
            ];
            
            $state = ProcessingState::with(null, $tags);
            
            expect($state->tags()->first())->toBe($tags[0]);
            expect(ProcessingState::with(null, [])->tags()->first())->toBeNull();
        });

        it('gets last element with last()', function () {
            $tags = [
                new TagQueryTestTag('first'),
                new TagQueryTestTag('second'),
            ];
            
            $state = ProcessingState::with(null, $tags);
            
            expect($state->tags()->last())->toBe($tags[1]);
            expect(ProcessingState::with(null, [])->tags()->last())->toBeNull();
        });
    });

    describe('existence checks', function () {
        it('checks tag existence with has()', function () {
            $tag = new TagQueryTestTag('test');
            $state = ProcessingState::with(null, [$tag]);
            
            expect($state->tags()->has(TagQueryTestTag::class))->toBeTrue();
            expect($state->tags()->has(AnotherTestTag::class))->toBeFalse();
        });

        it('checks if all tag types exist with hasAll()', function () {
            $tag1 = new TagQueryTestTag('test');
            $state = ProcessingState::with(null, [$tag1]);
            
            expect($state->tags()->hasAll($tag1))->toBeTrue();
            expect($state->tags()->hasAll(new TagQueryTestTag('different')))->toBeTrue(); // Same class type
            expect($state->tags()->hasAll($tag1, new AnotherTestTag('missing')))->toBeFalse(); // Missing class type
        });

        it('checks if any tag exists with hasAny()', function () {
            $tag1 = new TagQueryTestTag('test');
            $state = ProcessingState::with(null, [$tag1]);
            
            expect($state->tags()->hasAny($tag1, new AnotherTestTag('missing')))->toBeTrue();
            expect($state->tags()->hasAny(new AnotherTestTag('missing')))->toBeFalse();
        });
    });

    describe('transformation operations', function () {
        it('transforms elements with map()', function () {
            $tags = [
                new TagQueryTestTag('first'),
                new TagQueryTestTag('second'),
            ];
            
            $state = ProcessingState::with(null, $tags);
            $names = $state->tags()->mapTo(fn($tag) => $tag->name);
            
            expect($names)->toBe(['first', 'second']);
        });

        it('reduces elements with reduce()', function () {
            $tags = [
                new TagQueryTestTag('a'),
                new TagQueryTestTag('b'),
            ];
            
            $state = ProcessingState::with(null, $tags);
            $result = $state->tags()->reduce(fn($carry, $tag) => $carry . $tag->name, '');
            
            expect($result)->toBe('ab');
        });
    });

    describe('utility methods', function () {
        it('gets unique class names with classes()', function () {
            $tags = [
                new TagQueryTestTag('first'),
                new AnotherTestTag('second'),
                new TagQueryTestTag('third'),
            ];
            
            $state = ProcessingState::with(null, $tags);
            $classes = $state->tags()->classes();
            
            expect($classes)->toHaveCount(2);
            expect($classes)->toContain(TagQueryTestTag::class);
            expect($classes)->toContain(AnotherTestTag::class);
        });

        it('checks predicates with any()', function () {
            $tags = [
                new TagQueryTestTag('match'),
                new TagQueryTestTag('nomatch'),
            ];
            
            $state = ProcessingState::with(null, $tags);
            
            expect($state->tags()->any(fn($tag) => $tag->name === 'match'))->toBeTrue();
            expect($state->tags()->any(fn($tag) => $tag->name === 'missing'))->toBeFalse();
        });

        it('checks all elements with every()', function () {
            $tags = [
                new TagQueryTestTag('good'),
                new TagQueryTestTag('good'),
            ];
            
            $state = ProcessingState::with(null, $tags);
            
            expect($state->tags()->every(fn($tag) => $tag instanceof TagQueryTestTag))->toBeTrue();
            expect($state->tags()->every(fn($tag) => $tag->name === 'good'))->toBeTrue();
            expect($state->tags()->every(fn($tag) => $tag->name === 'bad'))->toBeFalse();
        });
    });
});