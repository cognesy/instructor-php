<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Contracts;

use Cognesy\Messages\Script\Script;

interface ScriptProcessor
{
    public function process(Script $script): Script;
    public function shouldProcess(Script $script): bool;
}
