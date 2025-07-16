<?php declare(strict_types=1);

namespace Cognesy\Schema\Data\Schema;

use Cognesy\Schema\Contracts\CanVisitSchema;

class OptionSchema extends Schema
{
    public function accept(CanVisitSchema $visitor): void {
        $visitor->visitOptionSchema($this);
    }
}