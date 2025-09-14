<?php declare(strict_types=1);

namespace Cognesy\Addons\Core\StateContracts;

use Cognesy\Utils\Result\Result;

interface HasResult
{
    public function result() : Result;
    public function withResult(Result $result) : static;
}
