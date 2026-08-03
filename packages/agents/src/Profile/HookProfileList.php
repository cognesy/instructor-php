<?php declare(strict_types=1);

namespace Cognesy\Agents\Profile;

use Cognesy\Agents\Hook\HookStack;
use Cognesy\Agents\Interception\CanInterceptAgentLifecycle;

final readonly class HookProfileList
{
    /** @var list<HookProfile> */
    private array $profiles;

    public function __construct(HookProfile ...$profiles) {
        $this->profiles = $profiles;
    }

    public static function fromInterceptor(CanInterceptAgentLifecycle $interceptor): self {
        if (!$interceptor instanceof HookStack) {
            return new self();
        }

        $profiles = [];
        foreach ($interceptor->hooks() as $registered) {
            $triggers = array_map(
                static fn ($trigger): string => $trigger->value,
                $registered->triggers()->triggers(),
            );
            $profiles[] = new HookProfile(
                class: $registered->hook()::class,
                triggers: $triggers,
                priority: $registered->priority(),
                name: $registered->name(),
            );
        }
        return new self(...$profiles);
    }

    /** @return list<HookProfile> */
    public function all(): array {
        return $this->profiles;
    }

    /** @return list<array<string, mixed>> */
    public function toArray(): array {
        return array_map(
            static fn (HookProfile $profile): array => $profile->toArray(),
            $this->profiles,
        );
    }
}
