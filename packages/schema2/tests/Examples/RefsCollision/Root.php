<?php

namespace Cognesy\Schema\Tests\Examples\RefsCollision;

use Cognesy\Schema\Tests\Examples\RefsCollision\NA\User as NAUser;
use Cognesy\Schema\Tests\Examples\RefsCollision\NB\User as NBUser;

class Root
{
    public NAUser $naUser;
    public NBUser $nbUser;
}

