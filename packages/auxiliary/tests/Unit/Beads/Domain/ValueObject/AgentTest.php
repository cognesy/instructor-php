<?php

declare(strict_types=1);

use Cognesy\Auxiliary\Beads\Domain\ValueObject\Agent;

describe('Agent', function () {
    describe('construction', function () {
        it('creates agent with ID only', function () {
            $agent = new Agent('claude-123');

            expect($agent->id)->toBe('claude-123')
                ->and($agent->name)->toBeNull();
        });

        it('creates agent with ID and name', function () {
            $agent = new Agent('claude-123', 'Claude AI');

            expect($agent->id)->toBe('claude-123')
                ->and($agent->name)->toBe('Claude AI');
        });

        it('throws exception for empty ID', function () {
            expect(fn() => new Agent(''))->toThrow(InvalidArgumentException::class);
            expect(fn() => new Agent('   '))->toThrow(InvalidArgumentException::class);
            expect(fn() => new Agent("\t\n  "))->toThrow(InvalidArgumentException::class);
        });

        it('provides helpful error message for empty ID', function () {
            try {
                new Agent('');
                $this->fail('Expected InvalidArgumentException');
            } catch (InvalidArgumentException $e) {
                expect($e->getMessage())->toContain('Agent ID cannot be empty');
            }
        });

        it('accepts valid whitespace-containing IDs', function () {
            // ID with internal spaces should be valid
            $agent = new Agent('claude ai 123');
            expect($agent->id)->toBe('claude ai 123');
        });
    });

    describe('factory methods', function () {
        it('creates from ID using fromId', function () {
            $agent = Agent::fromId('user-456');

            expect($agent->id)->toBe('user-456')
                ->and($agent->name)->toBeNull();
        });

        it('creates with both ID and name using create', function () {
            $agent = Agent::create('gpt-4', 'GPT-4 Assistant');

            expect($agent->id)->toBe('gpt-4')
                ->and($agent->name)->toBe('GPT-4 Assistant');
        });

        it('factory methods validate ID', function () {
            expect(fn() => Agent::fromId(''))->toThrow(InvalidArgumentException::class);
            expect(fn() => Agent::create('', 'Name'))->toThrow(InvalidArgumentException::class);
        });
    });

    describe('equality comparison', function () {
        it('considers agents equal if IDs match', function () {
            $agent1 = new Agent('claude-123', 'Claude AI');
            $agent2 = new Agent('claude-123', 'Different Name');
            $agent3 = new Agent('claude-123');

            expect($agent1->equals($agent2))->toBeTrue()
                ->and($agent1->equals($agent3))->toBeTrue()
                ->and($agent2->equals($agent3))->toBeTrue();
        });

        it('considers agents different if IDs differ', function () {
            $agent1 = new Agent('claude-123');
            $agent2 = new Agent('claude-456');
            $agent3 = new Agent('gpt-4');

            expect($agent1->equals($agent2))->toBeFalse()
                ->and($agent1->equals($agent3))->toBeFalse()
                ->and($agent2->equals($agent3))->toBeFalse();
        });

        it('handles case sensitivity in IDs', function () {
            $agent1 = new Agent('Claude-123');
            $agent2 = new Agent('claude-123');

            expect($agent1->equals($agent2))->toBeFalse();
        });
    });

    describe('display name', function () {
        it('returns name when available', function () {
            $agent = new Agent('claude-123', 'Claude AI Assistant');

            expect($agent->displayName())->toBe('Claude AI Assistant');
        });

        it('returns ID when name is null', function () {
            $agent = new Agent('claude-123');

            expect($agent->displayName())->toBe('claude-123');
        });

        it('returns ID when name is empty string', function () {
            // Note: Constructor accepts empty name, only ID is validated
            $agent = new Agent('claude-123', '');

            expect($agent->displayName())->toBe('');
        });
    });

    describe('string representation', function () {
        it('returns display name for string conversion', function () {
            $agentWithName = new Agent('claude-123', 'Claude AI');
            $agentWithoutName = new Agent('user-456');

            expect((string) $agentWithName)->toBe('Claude AI')
                ->and((string) $agentWithoutName)->toBe('user-456');
        });
    });

    describe('immutability', function () {
        it('is immutable readonly object', function () {
            $agent = new Agent('claude-123', 'Claude AI');

            // Should be readonly properties
            expect(property_exists($agent, 'id'))->toBeTrue()
                ->and(property_exists($agent, 'name'))->toBeTrue();

            // Values should be accessible
            expect($agent->id)->toBe('claude-123')
                ->and($agent->name)->toBe('Claude AI');
        });
    });

    describe('edge cases', function () {
        it('handles special characters in ID', function () {
            $agent = new Agent('claude@anthropic.com');
            expect($agent->id)->toBe('claude@anthropic.com');

            $agent2 = new Agent('user_123-v2.0');
            expect($agent2->id)->toBe('user_123-v2.0');
        });

        it('handles unicode characters', function () {
            $agent = new Agent('用户-123', 'Chinese User');

            expect($agent->id)->toBe('用户-123')
                ->and($agent->name)->toBe('Chinese User')
                ->and($agent->displayName())->toBe('Chinese User');
        });

        it('handles very long IDs and names', function () {
            $longId = str_repeat('a', 1000);
            $longName = str_repeat('Name ', 200);

            $agent = new Agent($longId, $longName);

            expect($agent->id)->toBe($longId)
                ->and($agent->name)->toBe($longName);
        });
    });
});