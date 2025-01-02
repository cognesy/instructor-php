<?php

namespace Cognesy\Instructor\Extras\Chat\Contracts;

use Cognesy\Instructor\Utils\Messages\Script;

interface ScriptProcessor
{
    public function process(Script $script): Script;
    public function shouldProcess(Script $script): bool;
}
