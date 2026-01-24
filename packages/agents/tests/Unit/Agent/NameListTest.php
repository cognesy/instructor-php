<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Agent;

use Cognesy\Agents\Agent\Collections\NameList;

describe('NameList', function () {
    it('normalizes and deduplicates names', function () {
        $list = NameList::fromArray(['alpha', 'beta', 'alpha', '', 123]);

        expect($list->toArray())->toBe(['alpha', 'beta']);
        expect($list->count())->toBe(2);
        expect($list->has('alpha'))->toBeTrue();
        expect($list->has('gamma'))->toBeFalse();
    });

    it('merges two lists', function () {
        $a = new NameList('alpha');
        $b = new NameList('beta', 'alpha');

        $merged = $a->merge($b);

        expect($merged->toArray())->toBe(['alpha', 'beta']);
        expect($merged->count())->toBe(2);
    });
});
