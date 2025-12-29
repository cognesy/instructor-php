<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Common\Value;

final readonly class PathList
{
    /** @var list<string> */
    private array $paths;

    /**
     * @param list<string> $paths
     */
    private function __construct(array $paths) {
        $this->paths = array_values($paths);
    }

    public static function none() : self {
        return new self([]);
    }

    /**
     * @param list<string> $paths
     */
    public static function of(array $paths) : self {
        return new self($paths);
    }

    /**
     * @return list<string>
     */
    public function toArray() : array {
        return $this->paths;
    }

    public function isEmpty() : bool {
        return count($this->paths) === 0;
    }
}
