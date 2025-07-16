<?php declare(strict_types=1);

namespace Cognesy\Schema\Contracts;

use Cognesy\Schema\Data\TypeDetails;

interface CanGetPropertyType
{
    public function getPropertyTypeDetails(): TypeDetails;
    public function isPropertyNullable(): bool;
}