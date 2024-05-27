<?php

namespace Cognesy\Instructor\Schema\Contracts;

interface CanAcceptSchemaVisitor
{
    public function accept(CanVisitSchema $visitor): void;
}