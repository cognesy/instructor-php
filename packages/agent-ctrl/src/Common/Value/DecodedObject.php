<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Common\Value;

final readonly class DecodedObject
{
    /**
     * @param array<string,mixed> $data
     */
    public function __construct(private array $data) {}

    /**
     * @return array<string,mixed>
     */
    public function data() : array {
        return $this->data;
    }
}
