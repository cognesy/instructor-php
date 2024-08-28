<?php
namespace Cognesy\Instructor\Data\Traits\Request;

trait HandlesModel
{
    private string $model;

    public function model() : string {
        return $this->model ?: $this->client->defaultModel();
    }

    // INTERNAL ///////////////////////////////////////////////////////////////

    protected function withModel(string $model) : self {
        $this->model = $model;
        return $this;
    }
}