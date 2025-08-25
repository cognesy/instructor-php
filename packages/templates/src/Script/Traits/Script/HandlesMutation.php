<?php declare(strict_types=1);

namespace Cognesy\Template\Script\Traits\Script;

use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Template\Script\Script;
use Cognesy\Template\Script\ScriptParameters;
use Cognesy\Template\Script\Section;
use Exception;

trait HandlesMutation
{
    public function createSection(string $name) : Section {
        if ($this->hasSection($name)) {
            throw new Exception("Section with name '{$name}' already exists - use mergeSection() instead.");
        }
        // Note: This creates the section but doesn't add it to the script
        // Use appendSection() separately to add it to the script
        return new Section($name);
    }

    public function withSection(string $name) : static {
        if ($this->hasSection($name)) {
            return $this;
        }
        return $this->appendSection(new Section($name));
    }

    public function appendSection(Section $section) : static {
        $name = $section->name;
        if ($this->hasSection($name)) {
            throw new Exception("Section with name '{$name}' already exists - use mergeSection() instead.");
        }
        return $this->forceAppendSection($section);
    }

    protected function forceAppendSection(Section $section) : static {
        $newSections = $this->sections;
        $newSections[] = $section;
        return new static(
            sections: $newSections,
            parameters: $this->parameters,
        );
    }

    public function mergeSection(Section $section) : static {
        if ($this->hasSection($section->name)) {
            $existingSection = $this->section($section->name);
            $mergedSection = $existingSection->mergeSection($section);
            return $this->replaceSection($section->name, $mergedSection);
        } else {
            return $this->appendSection($section);
        }
    }

    public function overrideScript(Script $script) : static {
        $result = $this;
        foreach($script->sections as $section) {
            if ($result->hasSection($section->name)) {
                $result = $result->removeSection($section->name);
            }
            $result = $result->appendSection($section);
        }
        return $result->mergeParameters($script->parameters);
    }

    public function mergeScript(Script $script) : static {
        $result = $this;
        foreach($script->sections as $section) {
            $result = $result->mergeSection($section);
        }
        return $result->mergeParameters($script->parameters);
    }

    public function mergeParameters(array|ScriptParameters $parameters) : static {
        return new static(
            sections: $this->sections,
            parameters: $this->parameters->merge($parameters),
        );
    }

    public function removeSection(string $name) : static {
        $newSections = array_filter($this->sections, fn($section) => $section->name !== $name);
        return new static(
            sections: array_values($newSections), // Re-index array
            parameters: $this->parameters,
        );
    }

    public function replaceSection(string $name, Section $newSection) : static {
        $newSections = [];
        foreach ($this->sections as $section) {
            if ($section->name === $name) {
                $newSections[] = $newSection;
            } else {
                $newSections[] = $section;
            }
        }
        return new static(
            sections: $newSections,
            parameters: $this->parameters,
        );
    }

    public function withSectionMessages(string $sectionName, Messages $messages) : static {
        $script = $this->withSection($sectionName);
        foreach ($messages->each() as $message) {
            $script = $script->appendMessageToSection($sectionName, $message);
        }
        return $script;
    }

    public function withSectionMessage(string $sectionName, array|Message $message) : static {
        $messageObj = is_array($message) ? Message::fromAny($message) : $message;
        if ($messageObj->isEmpty()) {
            return $this;
        }
        return $this->withSection($sectionName)->appendMessageToSection($sectionName, $message);
    }

    public function withSectionMessageIfEmpty(string $sectionName, array|Message $message) : static {
        $script = $this->withSection($sectionName);
        if ($script->getSection($sectionName)->isEmpty()) {
            return $script->appendMessageToSection($sectionName, $message);
        }
        return $script;
    }

    public function withConditionalSectionMessage(string $sectionName, array|Message $message, string $conditionSectionName) : static {
        $script = $this->withSection($sectionName)->withSection($conditionSectionName);
        if ($script->getSection($conditionSectionName)->notEmpty()) {
            return $script->withSectionMessageIfEmpty($sectionName, $message);
        }
        return $script;
    }

    public function appendMessageToSection(string $sectionName, array|Message $message) : static {
        $section = $this->section($sectionName);
        $updatedSection = $section->appendMessage($message);
        return $this->replaceSection($sectionName, $updatedSection);
    }

    public function prependMessageToSection(string $sectionName, array|Message $message) : static {
        $section = $this->section($sectionName);
        $updatedSection = $section->prependMessage($message);
        return $this->replaceSection($sectionName, $updatedSection);
    }

    // INTERNAL ////////////////////////////////////////////////////

    private function insert(array $array, int $index, array $new) : array {
        return array_merge(
            array_slice($array, 0, $index),
            $new,
            array_slice($array, $index)
        );
    }

    private function appendSections(array $array) : array {
        return array_merge($this->sections, $array);
    }

    private function prependSections(array $array) {
        return array_merge($array, $this->sections);
    }
}