<?php
namespace Cognesy\Instructor\Extras\Signature\Traits;

use Cognesy\Instructor\Extras\Structure\Structure;
use Cognesy\Instructor\Schema\Utils\ClassInfo;

trait HandlesAutoConfig
{
    use GetsFieldsFromClass;

    public function isConfigured(): bool {
        return isset($this->inputs) && isset($this->outputs);
    }

    protected function autoConfigure() : static {
        $classInfo = new ClassInfo(static::class);
        $classDescription = $classInfo->getClassDescription();
        $fields = self::getFields($classInfo);
        $this->inputs = Structure::define('inputs', $fields['inputs']);
        $this->outputs = Structure::define('outputs', $fields['outputs']);
        $this->description = $classDescription;
        $this->copyValuesFromInstance($fields);
        return $this;
    }

    protected function copyValuesFromInstance(array $fields): static {
        foreach($fields['inputs'] as $field) {
            $name = $field->name();
            $this->inputs->set($name, $this->$name);
        }
        foreach($fields['outputs'] as $field) {
            $name = $field->name();
            $this->outputs->set($name, $this->$name);
        }
        return $this;
    }
}
