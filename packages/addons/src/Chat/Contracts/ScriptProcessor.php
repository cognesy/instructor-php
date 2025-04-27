<?php

namespace Cognesy\Addons\Chat\Contracts;

use Cognesy\Template\Script\Script;

interface ScriptProcessor
{
    public function process(Script $script): Script;
    public function shouldProcess(Script $script): bool;
}
