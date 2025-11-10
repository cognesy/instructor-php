<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Instructor\Events\Request\SequenceUpdated;
use Cognesy\Instructor\Extras\Sequence\Sequence;
use Cognesy\Instructor\ResponseIterators\Streaming\SequenceGen\SequenceableEmitter;

class Item { public function __construct(public string $name) {} }

describe('SequenceableHandler', function () {
    it('dispatches updates only for complete items and in order', function () {
        $events = Mockery::mock(EventDispatcher::class);
        $updates = [];
        $events->shouldReceive('dispatch')->andReturnUsing(function($event) use (&$updates) {
            if ($event instanceof SequenceUpdated) { $updates[] = $event->sequence->toArray(); }
            return $event;
        });

        $handler = new SequenceableEmitter($events);
        $seq = Sequence::of(Item::class);

        // simulate streaming growth
        $seq->push(new Item('a'));
        $handler->update($seq); // no event yet (currentLength=1, previous=0 => 1>0+1 is false)

        $seq->push(new Item('b'));
        $handler->update($seq); // now 2>0+1 => dispatch up to 1

        $seq->push(new Item('c'));
        $seq->push(new Item('d'));
        $handler->update($seq); // 4>1+1 => dispatch up to 3

        $handler->finalize(); // finalize remaining

        // Expect updates of sizes 1,2,3,4 in order
        expect($updates)->toHaveCount(4)
            ->and(count($updates[0]))->toBe(1)
            ->and(count($updates[1]))->toBe(2)
            ->and(count($updates[2]))->toBe(3)
            ->and(count($updates[3]))->toBe(4)
            ->and($updates[0][0]->name)->toBe('a')
            ->and($updates[1][1]->name)->toBe('b')
            ->and($updates[2][2]->name)->toBe('c')
            ->and($updates[3][3]->name)->toBe('d');
    });
});
