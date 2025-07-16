<?php declare(strict_types=1);

namespace Cognesy\Schema\Contracts;

interface CanAcceptSchemaVisitor
{
    public function accept(CanVisitSchema $visitor): void;
}