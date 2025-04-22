<?php

namespace Cognesy\Addons\Chat\Contracts;

use Cognesy\Utils\Messages\Script;

interface ScriptProcessor
{
    public function process(Script $script): Script;
    public function shouldProcess(Script $script): bool;
}
