<?php

namespace Cognesy\Instructor\ApiClient\Contracts;

use Cognesy\Instructor\Data\Messages\Script;

interface CanMaterializeMessages
{
    public function toMessages(Script $script) : array;
    public function toSystem(Script $script) : array;
}