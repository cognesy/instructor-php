<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Traits\Chat;

use Cognesy\Addons\Chat\Contracts\ScriptProcessor;
use Cognesy\Template\Script\Script;

trait HandlesScriptProcessors
{
    /** @var ScriptProcessor[] */
    protected array $scriptProcessors = [];

    public function withScriptProcessors(ScriptProcessor ...$processors) : self {
        foreach ($processors as $p) { $this->scriptProcessors[] = $p; }
        return $this;
    }

    protected function applyScriptProcessors(Script $script) : Script {
        $result = $script;
        foreach ($this->scriptProcessors as $p) {
            if ($p->shouldProcess($result)) { $result = $p->process($result); }
        }
        return $result;
    }
}

