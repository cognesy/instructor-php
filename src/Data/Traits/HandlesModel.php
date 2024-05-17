<?php

namespace Cognesy\Instructor\Data\Traits;

use Cognesy\Instructor\ApiClient\ModelParams;
use Cognesy\Instructor\Core\Factories\ModelFactory;

trait HandlesModel
{
    private ?ModelFactory $modelFactory;
    private ModelParams $modelParams;
    private string $model;

    public function model() : string {
        return $this->model;
    }

    public function modelName() : string {
        if (isset($this->modelParams)) {
            return $this->modelParams->name;
        }
        if ($this->modelFactory?->has($this->model)) {
            return $this->modelFactory->get($this->model)->name;
        }
        return $this->model;
    }

    public function withModel(string|ModelParams $model) : self {
        if ($model instanceof ModelParams) {
            $this->modelParams = $model;
            $this->model = $model->name;
        } else {
            $this->model = $model;
        }
        return $this;
    }
}