<?php declare(strict_types=1);

use Cognesy\Pipeline\Tag\TagInterface;
use Cognesy\Pipeline\Tag\TagMap;

// Test tag implementations for isolated testing
class TestTagA implements TagInterface
{
    public function __construct(public readonly string $value) {}
}

class TestTagB implements TagInterface
{
    public function __construct(public readonly int $number) {}
}

class TestTagC implements TagInterface
{
    public function __construct(public readonly array $data) {}
}

describe('TagMap Unit Tests', function () {
    describe('Creation and Basic Operations', function () {
        it('creates empty TagMap', function () {
            $map = TagMap::empty();
            
            expect($map->isEmpty())->toBeTrue();
            expect($map->count())->toBe(0);
            expect($map->classes())->toBe([]);
        });

        it('creates TagMap from array of tags', function () {
            $tags = [
                new TestTagA('first'),
                new TestTagB(42),
                new TestTagA('second')
            ];
            
            $map = TagMap::create($tags);
            
            expect($map->count())->toBe(3);
            expect($map->count(TestTagA::class))->toBe(2);
            expect($map->count(TestTagB::class))->toBe(1);
            expect($map->has(TestTagA::class))->toBeTrue();
            expect($map->has(TestTagB::class))->toBeTrue();
        });

        it('indexes tags by class name correctly', function () {
            $map = TagMap::create([
                new TestTagA('value1'),
                new TestTagA('value2'),
                new TestTagB(100)
            ]);
            
            $tagAs = $map->all(TestTagA::class);
            expect(count($tagAs))->toBe(2);
            expect($tagAs[0]->value)->toBe('value1');
            expect($tagAs[1]->value)->toBe('value2');
        });
    });

    describe('Immutable Operations', function () {
        beforeEach(function () {
            $this->baseMap = TagMap::create([
                new TestTagA('original'),
                new TestTagB(10)
            ]);
        });

        it('adds tags immutably with with()', function () {
            $newTag = new TestTagC(['new' => 'data']);
            $newMap = $this->baseMap->with($newTag);
            
            // Original unchanged
            expect($this->baseMap->count())->toBe(2);
            expect($this->baseMap->has(TestTagC::class))->toBeFalse();
            
            // New map has addition
            expect($newMap->count())->toBe(3);
            expect($newMap->has(TestTagC::class))->toBeTrue();
        });

        it('removes tags immutably with without()', function () {
            $newMap = $this->baseMap->without(TestTagB::class);
            
            // Original unchanged
            expect($this->baseMap->has(TestTagB::class))->toBeTrue();
            expect($this->baseMap->count())->toBe(2);
            
            // New map has removal
            expect($newMap->has(TestTagB::class))->toBeFalse();
            expect($newMap->count())->toBe(1);
            expect($newMap->has(TestTagA::class))->toBeTrue();
        });

        it('handles multiple class removals', function () {
            $map = TagMap::create([
                new TestTagA('keep'),
                new TestTagB(123),
                new TestTagC(['remove' => 'me'])
            ]);
            
            $filtered = $map->without(TestTagB::class, TestTagC::class);
            
            expect($filtered->count())->toBe(1);
            expect($filtered->has(TestTagA::class))->toBeTrue();
            expect($filtered->has(TestTagB::class))->toBeFalse();
        });
    });

    describe('Tag Retrieval', function () {
        beforeEach(function () {
            $this->map = TagMap::create([
                new TestTagA('first'),
                new TestTagB(10),
                new TestTagA('middle'),
                new TestTagB(20),
                new TestTagA('last')
            ]);
        });

        it('retrieves first tag of type', function () {
            $first = $this->map->first(TestTagA::class);
            
            expect($first)->toBeInstanceOf(TestTagA::class);
            expect($first->value)->toBe('first');
        });

        it('retrieves last tag of type', function () {
            $last = $this->map->last(TestTagA::class);
            
            expect($last)->toBeInstanceOf(TestTagA::class);
            expect($last->value)->toBe('last');
        });

        it('returns null for non-existent tag types', function () {
            expect($this->map->first(TestTagC::class))->toBeNull();
            expect($this->map->last(TestTagC::class))->toBeNull();
        });

        it('retrieves all tags of specific type', function () {
            $tagAs = $this->map->all(TestTagA::class);
            
            expect(count($tagAs))->toBe(3);
            expect($tagAs[0]->value)->toBe('first');
            expect($tagAs[2]->value)->toBe('last');
        });

        it('retrieves all tags across all types', function () {
            $allTags = $this->map->all();
            
            expect(count($allTags))->toBe(5);
            
            // Arrays::flatten returns all TestTagA first, then all TestTagB
            // This is expected behavior since tags are grouped by class
            expect($allTags[0]->value)->toBe('first');
            expect($allTags[1]->value)->toBe('middle');
            expect($allTags[2]->value)->toBe('last');
            expect($allTags[3]->number)->toBe(10);
            expect($allTags[4]->number)->toBe(20);
        });
    });

    describe('Advanced Operations', function () {
        it('merges TagMaps correctly', function () {
            $map1 = TagMap::create([new TestTagA('from1'), new TestTagB(1)]);
            $map2 = TagMap::create([new TestTagA('from2'), new TestTagC(['data'])]);
            
            $merged = $map1->merge($map2);
            
            expect($merged->count())->toBe(4);
            expect($merged->count(TestTagA::class))->toBe(2);
            
            // Tags from map2 should be appended
            $tagAs = $merged->all(TestTagA::class);
            expect($tagAs[0]->value)->toBe('from1');
            expect($tagAs[1]->value)->toBe('from2');
        });

        it('filters tags with only()', function () {
            $map = TagMap::create([
                new TestTagA('keep'),
                new TestTagB(123),
                new TestTagC(['remove'])
            ]);
            
            $filtered = $map->only(TestTagA::class, TestTagB::class);
            
            expect($filtered->count())->toBe(2);
            expect($filtered->has(TestTagA::class))->toBeTrue();
            expect($filtered->has(TestTagB::class))->toBeTrue();
            expect($filtered->has(TestTagC::class))->toBeFalse();
        });

        it('transforms tags with map()', function () {
            $map = TagMap::create([
                new TestTagA('original1'),
                new TestTagA('original2')
            ]);
            
            $mapped = $map->map(TestTagA::class, fn($tag) => new TestTagA(strtoupper($tag->value)));
            
            $tags = $mapped->all(TestTagA::class);
            expect($tags[0]->value)->toBe('ORIGINAL1');
            expect($tags[1]->value)->toBe('ORIGINAL2');
        });

        it('filters tags with predicate', function () {
            $map = TagMap::create([
                new TestTagB(5),
                new TestTagB(15),
                new TestTagB(25)
            ]);
            
            $filtered = $map->filter(TestTagB::class, fn($tag) => $tag->number > 10);
            
            $tags = $filtered->all(TestTagB::class);
            expect(count($tags))->toBe(2);
            expect($tags[0]->number)->toBe(15);
            expect($tags[1]->number)->toBe(25);
        });
    });

    describe('Edge Cases and Error Conditions', function () {
        it('handles empty operations gracefully', function () {
            $empty = TagMap::empty();
            
            expect($empty->with()->isEmpty())->toBeTrue();
            expect($empty->without('NonExistent')->isEmpty())->toBeTrue();
            expect($empty->merge(TagMap::empty())->isEmpty())->toBeTrue();
        });

        it('handles operations on non-existent tag types', function () {
            $map = TagMap::create([new TestTagA('test')]);
            
            expect($map->without('NonExistent')->count())->toBe(1);
            expect($map->only('NonExistent')->isEmpty())->toBeTrue();
            expect($map->map('NonExistent', fn($x) => $x))->toBe($map);
            expect($map->filter('NonExistent', fn($x) => true))->toBe($map);
        });

        it('preserves insertion order within same type', function () {
            $tags = [
                new TestTagA('first'),
                new TestTagA('second'), 
                new TestTagA('third')
            ];
            
            $map = TagMap::create($tags);
            $retrieved = $map->all(TestTagA::class);
            
            expect($retrieved[0]->value)->toBe('first');
            expect($retrieved[1]->value)->toBe('second');
            expect($retrieved[2]->value)->toBe('third');
        });

        it('handles filter that removes all tags of a type', function () {
            $map = TagMap::create([
                new TestTagB(5),
                new TestTagB(10)
            ]);
            
            $filtered = $map->filter(TestTagB::class, fn($tag) => $tag->number > 20);
            
            expect($filtered->isEmpty())->toBeTrue();
            expect($filtered->has(TestTagB::class))->toBeFalse();
        });
    });

    describe('Array Conversion and Debugging', function () {
        it('converts to array correctly', function () {
            $map = TagMap::create([
                new TestTagA('test'),
                new TestTagB(42)
            ]);
            
            $array = $map->toArray();
            
            expect($array)->toHaveKey(TestTagA::class);
            expect($array)->toHaveKey(TestTagB::class);
            expect(count($array[TestTagA::class]))->toBe(1);
            expect($array[TestTagA::class][0]->value)->toBe('test');
        });

        it('returns correct class list', function () {
            $map = TagMap::create([
                new TestTagA('test'),
                new TestTagB(42),
                new TestTagA('another')
            ]);
            
            $classes = $map->classes();
            
            expect(count($classes))->toBe(2);
            expect($classes)->toContain(TestTagA::class);
            expect($classes)->toContain(TestTagB::class);
        });
    });
});