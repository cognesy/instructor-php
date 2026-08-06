<?php declare(strict_types=1);

use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Messages\MessageStore\Collections\Sections;
use Cognesy\Messages\MessageStore\Section;

describe('Sections', function () {
    describe('construction', function () {
        it('is empty by default', function () {
            $sections = new Sections();

            expect($sections->count())->toBe(0);
            expect($sections->all())->toBe([]);
        });

        it('accepts a variadic list of sections', function () {
            $a = Section::empty('a');
            $b = Section::empty('b');

            $sections = new Sections($a, $b);

            expect($sections->count())->toBe(2);
            expect($sections->all())->toBe([$a, $b]);
        });

        it('builds sections from a plain array via fromArray', function () {
            $sections = Sections::fromArray([
                ['name' => 'system', 'messages' => [['role' => 'system', 'content' => 'be helpful']]],
                ['name' => 'chat'],
            ]);

            expect($sections->count())->toBe(2);
            expect($sections->names())->toBe(['system', 'chat']);
            expect($sections->get('system')->messages()->count())->toBe(1);
            expect($sections->get('chat')->isEmpty())->toBeTrue();
        });

        it('builds an empty collection from an empty array', function () {
            $sections = Sections::fromArray([]);

            expect($sections->count())->toBe(0);
        });

        // add() has always rejected a duplicate name; the constructor did not, so any other
        // route in (fromSections, set, deserialization) could build a collection where the
        // second section under a name is unreachable by get()/set() yet still contributes to
        // toMessages().
        it('rejects two sections sharing a name', function () {
            expect(fn() => new Sections(
                new Section('a', Messages::fromString('first')),
                new Section('a', Messages::fromString('second')),
            ))->toThrow(InvalidArgumentException::class, "Section with name 'a' already exists.");
        });

        // Deserialization is the one path that must not reject data a previous version was
        // able to write, so it merges instead - the same policy merge() applies.
        it('merges duplicate names in fromArray instead of throwing', function () {
            $sections = Sections::fromArray([
                ['name' => 'a', 'messages' => [['role' => 'user', 'content' => 'first']]],
                ['name' => 'b', 'messages' => [['role' => 'user', 'content' => 'other']]],
                ['name' => 'a', 'messages' => [['role' => 'user', 'content' => 'second']]],
            ]);

            expect($sections->count())->toBe(2);
            expect($sections->names())->toBe(['a', 'b']);
            expect($sections->get('a')->messages()->map(fn(Message $m) => $m->content()->toString()))
                ->toBe(['first', 'second']);
            expect($sections->toMessages()->count())->toBe(3);
        });
    });

    describe('iteration (Countable + IteratorAggregate)', function () {
        it('iterates yielding the same sections in the same order as all()', function () {
            $a = Section::empty('a');
            $b = Section::empty('b');
            $sections = new Sections($a, $b);

            $iterated = [];
            foreach ($sections as $section) {
                $iterated[] = $section;
            }

            expect($iterated)->toBe($sections->all());
            expect(count($sections))->toBe(2);
        });

        it('iterates zero times over an empty collection', function () {
            $sections = new Sections();

            $iterated = [];
            foreach ($sections as $section) {
                $iterated[] = $section;
            }

            expect($iterated)->toBe([]);
            expect(count($sections))->toBe(0);
        });
    });

    describe('has / get', function () {
        it('finds a section by name', function () {
            $sections = new Sections(Section::empty('chat'));

            expect($sections->has('chat'))->toBeTrue();
            expect($sections->get('chat')->name)->toBe('chat');
        });

        it('reports missing sections', function () {
            $sections = new Sections();

            expect($sections->has('missing'))->toBeFalse();
            expect($sections->get('missing'))->toBeNull();
        });
    });

    describe('names', function () {
        it('returns section names preserving order', function () {
            $sections = new Sections(Section::empty('b'), Section::empty('a'));

            expect($sections->names())->toBe(['b', 'a']);
        });

        it('returns an empty array for an empty collection', function () {
            expect((new Sections())->names())->toBe([]);
        });
    });

    describe('add', function () {
        it('appends new sections immutably', function () {
            $original = new Sections(Section::empty('a'));

            $result = $original->add(Section::empty('b'));

            expect($original->count())->toBe(1);
            expect($result->count())->toBe(2);
            expect($result->names())->toBe(['a', 'b']);
        });

        it('throws when a section with the same name already exists', function () {
            $sections = new Sections(Section::empty('a'));

            expect(fn() => $sections->add(Section::empty('a')))
                ->toThrow(InvalidArgumentException::class, "Section with name 'a' already exists.");
        });

        it('leaves the original collection untouched after a failed add', function () {
            $sections = new Sections(Section::empty('a'));

            try {
                $sections->add(Section::empty('a'));
            } catch (InvalidArgumentException) {
                // expected
            }

            expect($sections->count())->toBe(1);
        });
    });

    describe('set', function () {
        it('replaces an existing section by name', function () {
            $original = new Sections(Section::empty('chat'));
            $replacement = new Section('chat', Messages::fromArray([['role' => 'user', 'content' => 'hi']]));

            $result = $original->set($replacement);

            expect($result->count())->toBe(1);
            expect($result->get('chat')->messages()->count())->toBe(1);
            expect($original->get('chat')->isEmpty())->toBeTrue();
        });

        it('appends a section when no existing section matches the name', function () {
            $original = new Sections(Section::empty('a'));

            $result = $original->set(Section::empty('b'));

            expect($result->names())->toBe(['a', 'b']);
        });

        it('keeps the last of several same-named sections passed in one call', function () {
            $first = new Section('x', Messages::fromArray([['role' => 'user', 'content' => 'first']]));
            $second = new Section('x', Messages::fromArray([['role' => 'user', 'content' => 'second']]));

            $result = (new Sections())->set($first, $second);

            expect($result->count())->toBe(1);
            expect($result->get('x')->messages()->first()->content()->toString())->toBe('second');
        });
    });

    describe('select', function () {
        it('returns all sections when names is empty', function () {
            $sections = new Sections(Section::empty('a'), Section::empty('b'));

            $result = $sections->select([]);

            expect($result->names())->toBe(['a', 'b']);
        });

        it('selects sections in the requested order, not the original order', function () {
            $sections = new Sections(Section::empty('a'), Section::empty('b'), Section::empty('c'));

            $result = $sections->select(['c', 'a']);

            expect($result->names())->toBe(['c', 'a']);
        });

        it('silently skips names that do not exist', function () {
            $sections = new Sections(Section::empty('a'));

            $result = $sections->select(['a', 'missing']);

            expect($result->names())->toBe(['a']);
        });

        it('returns an empty collection when none of the requested names exist', function () {
            $sections = new Sections(Section::empty('a'));

            $result = $sections->select(['missing']);

            expect($result->count())->toBe(0);
        });

        // Callers pass a name list they did not necessarily dedupe; selecting the same
        // section twice would emit its messages twice and now hit the constructor's guard.
        it('collapses a repeated name to a single section', function () {
            $sections = new Sections(
                new Section('a', Messages::fromString('x')),
                new Section('b', Messages::fromString('y')),
            );

            $result = $sections->select(['a', 'a', 'b']);

            expect($result->names())->toBe(['a', 'b']);
            expect($result->toMessages()->count())->toBe(2);
        });
    });

    describe('remove', function () {
        it('removes sections matching the predicate', function () {
            $sections = new Sections(Section::empty('a'), Section::empty('b'));

            $result = $sections->remove(fn(Section $s) => $s->name === 'a');

            expect($result->names())->toBe(['b']);
        });

        it('keeps all sections when the predicate matches nothing', function () {
            $sections = new Sections(Section::empty('a'));

            $result = $sections->remove(fn(Section $s) => $s->name === 'missing');

            expect($result->names())->toBe(['a']);
        });

        it('does not mutate the original collection', function () {
            $sections = new Sections(Section::empty('a'), Section::empty('b'));

            $sections->remove(fn(Section $s) => true);

            expect($sections->count())->toBe(2);
        });
    });

    describe('merge', function () {
        it('adds sections that do not exist yet', function () {
            $left = new Sections(Section::empty('a'));
            $right = new Sections(Section::empty('b'));

            $result = $left->merge($right);

            expect($result->names())->toBe(['a', 'b']);
        });

        it('appends messages onto a section with a matching name, existing messages first', function () {
            $left = new Sections(new Section('chat', Messages::fromArray([
                ['role' => 'user', 'content' => 'first'],
            ])));
            $right = new Sections(new Section('chat', Messages::fromArray([
                ['role' => 'assistant', 'content' => 'second'],
            ])));

            $result = $left->merge($right);

            expect($result->count())->toBe(1);
            $messages = $result->get('chat')->messages();
            expect($messages->count())->toBe(2);
            expect($messages->first()->content()->toString())->toBe('first');
            expect($messages->last()->content()->toString())->toBe('second');
        });

        it('does not mutate either input collection', function () {
            $left = new Sections(Section::empty('a'));
            $right = new Sections(Section::empty('b'));

            $left->merge($right);

            expect($left->names())->toBe(['a']);
            expect($right->names())->toBe(['b']);
        });
    });

    describe('withoutEmpty', function () {
        it('drops sections whose messages are all empty', function () {
            $sections = new Sections(
                new Section('empty', Messages::fromArray([['role' => 'user', 'content' => '']])),
                new Section('chat', Messages::fromArray([['role' => 'user', 'content' => 'hi']])),
            );

            $result = $sections->withoutEmpty();

            expect($result->names())->toBe(['chat']);
        });

        it('keeps a section but trims its empty messages when it has a mix', function () {
            $sections = new Sections(
                new Section('chat', Messages::fromArray([
                    ['role' => 'user', 'content' => ''],
                    ['role' => 'user', 'content' => 'hi'],
                ])),
            );

            $result = $sections->withoutEmpty();

            expect($result->count())->toBe(1);
            expect($result->get('chat')->messages()->count())->toBe(1);
            expect($result->get('chat')->messages()->first()->content()->toString())->toBe('hi');
        });

        it('drops every section from an all-empty collection', function () {
            $sections = new Sections(
                new Section('a', Messages::fromArray([['role' => 'user', 'content' => '']])),
                new Section('b'),
            );

            $result = $sections->withoutEmpty();

            expect($result->count())->toBe(0);
        });
    });

    describe('map / filter / reduce', function () {
        it('maps sections to an arbitrary array', function () {
            $sections = new Sections(Section::empty('a'), Section::empty('b'));

            $result = $sections->map(fn(Section $s) => strtoupper($s->name));

            expect($result)->toBe(['A', 'B']);
        });

        it('filters sections by predicate, returning a new Sections', function () {
            $sections = new Sections(Section::empty('a'), Section::empty('b'));

            $result = $sections->filter(fn(Section $s) => $s->name === 'b');

            expect($result)->toBeInstanceOf(Sections::class);
            expect($result->names())->toBe(['b']);
        });

        it('reduces sections to a single value', function () {
            $sections = new Sections(Section::empty('a'), Section::empty('bb'));

            $result = $sections->reduce(fn(int $carry, Section $s) => $carry + strlen($s->name), 0);

            expect($result)->toBe(3);
        });
    });

    describe('toMessages', function () {
        it('flattens messages from every section in order', function () {
            $sections = new Sections(
                new Section('system', Messages::fromArray([['role' => 'system', 'content' => 'sys']])),
                new Section('chat', Messages::fromArray([['role' => 'user', 'content' => 'hi']])),
            );

            $messages = $sections->toMessages();

            expect($messages->count())->toBe(2);
            expect($messages->first()->content()->toString())->toBe('sys');
            expect($messages->last()->content()->toString())->toBe('hi');
        });

        it('skips empty messages while flattening', function () {
            $sections = new Sections(
                new Section('chat', Messages::fromArray([
                    ['role' => 'user', 'content' => ''],
                    ['role' => 'user', 'content' => 'hi'],
                ])),
            );

            $messages = $sections->toMessages();

            expect($messages->count())->toBe(1);
            expect($messages->first()->content()->toString())->toBe('hi');
        });

        it('returns an empty Messages for an empty collection', function () {
            $messages = (new Sections())->toMessages();

            expect($messages->isEmpty())->toBeTrue();
        });
    });
});
