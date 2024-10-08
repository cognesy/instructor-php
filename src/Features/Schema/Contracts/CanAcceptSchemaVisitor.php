<?php

namespace Cognesy\Instructor\Features\Schema\Contracts;

interface CanAcceptSchemaVisitor
{
    public function accept(CanVisitSchema $visitor): void;
}