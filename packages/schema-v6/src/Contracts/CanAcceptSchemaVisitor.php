<?php

namespace Cognesy\Schema\Contracts;

interface CanAcceptSchemaVisitor
{
    public function accept(CanVisitSchema $visitor): void;
}