<?php declare(strict_types=1);

namespace Cognesy\Agents\Zero\Hooks;

final readonly class HookRegistry
{
    /** @var list<HookRegistration> */
    private array $registrations;

    /**
     * @param list<HookRegistration> $registrations
     */
    public function __construct(array $registrations = [])
    {
        $this->registrations = $registrations;
    }

    public static function empty(): self
    {
        return new self([]);
    }

    public function with(Hook $hook, int $priority, int $order): self
    {
        $next = [...$this->registrations, new HookRegistration($hook, $priority, $order)];
        return new self($next);
    }

    /**
     * @return list<HookRegistration>
     */
    public function sorted(): array
    {
        $sorted = $this->registrations;

        usort($sorted, static function (HookRegistration $left, HookRegistration $right): int {
            $priority = $right->priority <=> $left->priority;
            if ($priority !== 0) {
                return $priority;
            }
            return $left->order <=> $right->order;
        });

        return $sorted;
    }
}
