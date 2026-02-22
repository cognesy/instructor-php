<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Session;

use Cognesy\Agents\Session\AgentSessionInfo;
use Cognesy\Agents\Session\SessionId;
use Cognesy\Agents\Session\SessionStatus;
use DateTimeImmutable;

it('creates fresh info with defaults', function () {
    $info = AgentSessionInfo::fresh(new SessionId('s1'), 'test-agent', 'Test Agent');

    expect($info->sessionId())->toBe('s1');
    expect($info->parentId())->toBeNull();
    expect($info->status())->toBe(SessionStatus::Active);
    expect($info->version())->toBe(0);
    expect($info->agentName())->toBe('test-agent');
    expect($info->agentLabel())->toBe('Test Agent');
    expect($info->createdAt())->toBeInstanceOf(DateTimeImmutable::class);
    expect($info->updatedAt())->toBeInstanceOf(DateTimeImmutable::class);
});

it('creates fresh info with parent id', function () {
    $info = AgentSessionInfo::fresh(new SessionId('s2'), 'agent', 'Agent', new SessionId('parent-1'));

    expect($info->parentId())->toBe('parent-1');
    expect($info->parentIdValue())->toBeInstanceOf(SessionId::class);
    expect($info->parentIdValue()?->toString())->toBe('parent-1');
});

it('with(status:) returns new instance', function () {
    $info = AgentSessionInfo::fresh(new SessionId('s1'), 'agent', 'Agent');
    $suspended = $info->with(status: SessionStatus::Suspended);

    expect($suspended->status())->toBe(SessionStatus::Suspended);
    expect($info->status())->toBe(SessionStatus::Active);
    expect($suspended)->not->toBe($info);
});

it('withParentId returns new instance', function () {
    $info = AgentSessionInfo::fresh(new SessionId('s1'), 'agent', 'Agent');
    $withParent = $info->withParentId(new SessionId('p1'));

    expect($withParent->parentId())->toBe('p1');
    expect($withParent->parentIdValue())->toBeInstanceOf(SessionId::class);
    expect($info->parentId())->toBeNull();
});

it('round-trips through toArray/fromArray', function () {
    $info = AgentSessionInfo::fresh(new SessionId('s1'), 'test-agent', 'Test Agent', new SessionId('parent-1'));
    $restored = AgentSessionInfo::fromArray($info->toArray());

    expect($restored->sessionId())->toBe($info->sessionId());
    expect($restored->parentId())->toBe($info->parentId());
    expect($restored->status())->toBe($info->status());
    expect($restored->version())->toBe($info->version());
    expect($restored->agentName())->toBe($info->agentName());
    expect($restored->agentLabel())->toBe($info->agentLabel());
    expect($restored->createdAt()->format(DateTimeImmutable::ATOM))
        ->toBe($info->createdAt()->format(DateTimeImmutable::ATOM));
    expect($restored->updatedAt()->format(DateTimeImmutable::ATOM))
        ->toBe($info->updatedAt()->format(DateTimeImmutable::ATOM));
});
