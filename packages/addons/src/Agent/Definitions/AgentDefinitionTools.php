<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Definitions;

final readonly class AgentDefinitionTools
{
    /**
     * @param array<int, string>|null $allow
     * @param array<int, string>|null $deny
     */
    public function __construct(
        public ?array $allow = null,
        public ?array $deny = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'allow' => $this->allow,
            'deny' => $this->deny,
        ];
    }
}
