<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Session;

use Cognesy\Agents\Session\AgentSessionInfo;
use Cognesy\Agents\Session\SessionId;
use Cognesy\Agents\Session\SessionInfoList;
use Cognesy\Agents\Session\SessionStatus;

function makeInfo(string $id, string $name = 'agent', SessionStatus $status = SessionStatus::Active): AgentSessionInfo {
    return AgentSessionInfo::fresh(new SessionId($id), $name, $name)->with(status: $status);
}

it('constructs from variadic args', function () {
    $list = new SessionInfoList(makeInfo('s1'), makeInfo('s2'));

    expect($list->count())->toBe(2);
    expect($list->isEmpty())->toBeFalse();
});

it('empty returns empty list', function () {
    $list = SessionInfoList::empty();

    expect($list->count())->toBe(0);
    expect($list->isEmpty())->toBeTrue();
    expect($list->first())->toBeNull();
});

it('first returns first item', function () {
    $list = new SessionInfoList(makeInfo('s1'), makeInfo('s2'));

    expect($list->first()->sessionId())->toBe('s1');
});

it('all returns all items', function () {
    $list = new SessionInfoList(makeInfo('s1'), makeInfo('s2'));

    expect($list->all())->toHaveCount(2);
});

it('filterByStatus filters correctly', function () {
    $a = makeInfo('s1');
    $b = makeInfo('s2');
    $suspended = $b->with(status: SessionStatus::Suspended);
    $list = new SessionInfoList($a, $suspended);

    $active = $list->filterByStatus(SessionStatus::Active);
    expect($active->count())->toBe(1);
    expect($active->first()->sessionId())->toBe('s1');

    $susp = $list->filterByStatus(SessionStatus::Suspended);
    expect($susp->count())->toBe(1);
    expect($susp->first()->sessionId())->toBe('s2');
});

it('filterByAgentName filters correctly', function () {
    $list = new SessionInfoList(makeInfo('s1', 'alpha'), makeInfo('s2', 'beta'));

    $filtered = $list->filterByAgentName('alpha');
    expect($filtered->count())->toBe(1);
    expect($filtered->first()->agentName())->toBe('alpha');
});

it('is iterable', function () {
    $list = new SessionInfoList(makeInfo('s1'), makeInfo('s2'));
    $ids = [];
    foreach ($list as $info) {
        $ids[] = $info->sessionId();
    }

    expect($ids)->toBe(['s1', 's2']);
});

it('is countable', function () {
    $list = new SessionInfoList(makeInfo('s1'));

    expect(count($list))->toBe(1);
});

it('round-trips through toArray/fromArray', function () {
    $list = new SessionInfoList(makeInfo('s1'), makeInfo('s2'));
    $restored = SessionInfoList::fromArray($list->toArray());

    expect($restored->count())->toBe(2);
    expect($restored->first()->sessionId())->toBe('s1');
});
