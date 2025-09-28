<?php declare(strict_types=1);

namespace Cognesy\Schema\Data\Schema;

use Cognesy\Schema\Contracts\CanVisitSchema;

readonly class ObjectRefSchema extends Schema
{
    public function accept(CanVisitSchema $visitor): void {
        $visitor->visitObjectRefSchema($this);
    }
}