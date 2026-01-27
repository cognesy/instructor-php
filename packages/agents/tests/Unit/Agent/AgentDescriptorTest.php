<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Agent;

use Cognesy\Agents\Core\Collections\NameList;
use Cognesy\Agents\AgentBuilder\Data\AgentDescriptor;
use InvalidArgumentException;

describe('AgentDescriptor', function () {
    it('requires a non-empty name', function () {
        $make = fn() => new AgentDescriptor(
            name: '',
            description: 'desc',
            tools: new NameList(),
            capabilities: new NameList(),
        );

        expect($make)->toThrow(InvalidArgumentException::class);
    });

    it('requires a non-empty description', function () {
        $make = fn() => new AgentDescriptor(
            name: 'agent',
            description: '',
            tools: new NameList(),
            capabilities: new NameList(),
        );

        expect($make)->toThrow(InvalidArgumentException::class);
    });

    it('serializes to array', function () {
        $descriptor = new AgentDescriptor(
            name: 'agent',
            description: 'desc',
            tools: NameList::fromArray(['tool_a']),
            capabilities: NameList::fromArray(['cap_a']),
        );

        expect($descriptor->toArray())->toBe([
            'name' => 'agent',
            'description' => 'desc',
            'tools' => ['tool_a'],
            'capabilities' => ['cap_a'],
        ]);
    });
});
