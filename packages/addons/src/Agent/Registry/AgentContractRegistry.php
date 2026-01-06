<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Registry;

use Cognesy\Addons\Agent\Contracts\AgentContract;
use Cognesy\Addons\Agent\Contracts\AgentFactory;
use Cognesy\Addons\Agent\Core\Collections\NameList;
use Cognesy\Addons\Agent\Exceptions\AgentNotFoundException;
use Cognesy\Utils\Result\Result;
use InvalidArgumentException;

final readonly class AgentContractRegistry implements AgentFactory
{
    /** @var array<string, class-string<AgentContract>> */
    private array $definitions;

    /**
     * @param array<string, class-string<AgentContract>> $definitions
     */
    public function __construct(array $definitions = []) {
        $this->definitions = $definitions;
    }

    public function register(string $name, string $class): self {
        if (!is_subclass_of($class, AgentContract::class)) {
            throw new InvalidArgumentException("Agent '{$name}' must implement AgentContract.");
        }

        $newDefinitions = $this->definitions;
        $newDefinitions[$name] = $class;
        return new self($newDefinitions);
    }

    public function has(string $name): bool {
        return array_key_exists($name, $this->definitions);
    }

    public function names(): NameList {
        return NameList::fromArray(array_keys($this->definitions));
    }

    #[\Override]
    public function create(string $agentName, array $config = []): Result {
        if (!$this->has($agentName)) {
            return Result::failure(new AgentNotFoundException("Agent '{$agentName}' not found."));
        }
        $class = $this->definitions[$agentName];
        return $class::fromConfig($config);
    }
}
