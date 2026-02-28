<?php declare(strict_types=1);

namespace Cognesy\Instructor\Tests\Examples\RefsCollision;

use Cognesy\Instructor\Tests\Examples\RefsCollision\NA\User as NAUser;
use Cognesy\Instructor\Tests\Examples\RefsCollision\NB\User as NBUser;

class Root
{
    public NAUser $naUser;
    public NBUser $nbUser;
}

