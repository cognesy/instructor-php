<?php

namespace Cognesy\Instructor\ApiClient\Traits;

use Cognesy\Instructor\ApiClient\ModelFactory;
use Cognesy\Instructor\ApiClient\ModelParams;

trait HandlesModelParams
{
    private ModelParams $modelParams;
    private ModelFactory $modelFactory;

    public function withModelFactory(ModelFactory $modelFactory) : static {
        $this->modelFactory = $modelFactory;
        return $this;
    }

    public function modelFactory() : ModelFactory {
        return $this->modelFactory;
    }

    public function withModelConfig(ModelParams $modelParams) : static {
        $this->modelParams = $modelParams;
        return $this;
    }

    public function modelConfig() : ModelParams {
        return $this->modelParams;
    }
}