<?php

namespace Cognesy\Schema\Contracts;

use Cognesy\Schema\Data\TypeDetails;

interface CanGetPropertyType
{
    public function getPropertyTypeName(): string;
    public function getPropertyTypeDetails(): TypeDetails;
    public function getPropertyDescription(): string;
    public function isPropertyNullable(): bool;
}