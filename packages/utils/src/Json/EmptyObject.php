<?php declare(strict_types=1);

namespace Cognesy\Utils\Json;

final class EmptyObject implements \JsonSerializable
{
    #[\Override]
    public function jsonSerialize(): object {
        return (object) [];
    }
}
