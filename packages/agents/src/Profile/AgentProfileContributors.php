<?php declare(strict_types=1);

namespace Cognesy\Agents\Profile;

use Cognesy\Agents\Profile\Contracts\CanContributeToAgentProfile;

final readonly class AgentProfileContributors
{
    /** @var list<CanContributeToAgentProfile> */
    private array $contributors;

    public function __construct(CanContributeToAgentProfile ...$contributors) {
        $this->contributors = $contributors;
    }

    public static function empty(): self {
        return new self();
    }

    public function with(CanContributeToAgentProfile $contributor): self {
        return new self(...[...$this->contributors, $contributor]);
    }

    public function contribute(AgentProfile $profile): AgentProfile {
        $resolved = $profile;
        foreach ($this->contributors as $contributor) {
            $resolved = $contributor->contributeToAgentProfile($resolved);
        }
        return $resolved;
    }
}
