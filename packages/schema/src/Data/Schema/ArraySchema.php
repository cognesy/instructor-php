<?php declare(strict_types=1);

namespace Cognesy\Schema\Data\Schema;

use Cognesy\Schema\Contracts\CanVisitSchema;

readonly class ArraySchema extends Schema
{
    #[\Override]
    public function accept(CanVisitSchema $visitor): void {
        $visitor->visitArraySchema($this);
    }
}
