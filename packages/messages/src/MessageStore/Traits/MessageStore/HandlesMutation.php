<?php declare(strict_types=1);

namespace Cognesy\Messages\MessageStore\Traits\MessageStore;

use Cognesy\Messages\Messages;
use Cognesy\Messages\MessageStore\Collections\Sections;
use Cognesy\Messages\MessageStore\MessageStore;
use Cognesy\Messages\MessageStore\Section;
use Exception;

trait HandlesMutation
{
    public function mergedWith(MessageStore $store) : static {
        $result = $this;
        foreach($store->sections->each() as $section) {
            $result = $result->mergeSection($section);
        }
        return $result->mergeParameters($store->parameters);
    }

    public function withSection(string $name) : static {
        if ($this->section($name)->exists()) {
            return $this;
        }
        return $this->withSectionAdded(new Section($name));
    }

    public function withSectionAdded(Section $section) : static {
        $name = $section->name;
        if ($this->section($name)->exists()) {
            throw new Exception("Section with name '{$name}' already exists - use mergeSection() instead.");
        }

        $newSections = $this->sections->add($section);
        return new static(
            sections: $newSections,
            parameters: $this->parameters,
        );
    }

    public function withSectionReplaced(string $name, Section $newSection) : static {
        $newSections = new Sections(...$this->sections->map(fn($section) =>
            $section->name === $name ? $newSection : $section
        ));
        return new static(
            sections: $newSections,
            parameters: $this->parameters,
        );
    }

    public function withSectionMessages(string $sectionName, Messages $messages) : static {
        $store = $this->withSection($sectionName);
        $newSection = new Section($sectionName, messages: $messages);
        return $store->withSectionReplaced($sectionName, $newSection);
    }

    public function withoutEmptyMessages() : static {
        $trimmedSections = [];
        foreach ($this->sections->each() as $section) {
            $trimmed = $section->trimmed();
            if ($trimmed->isEmpty()) {
                continue;
            }
            $trimmedSections[] = $trimmed;
        }
        return new static(
            sections: new Sections(...$trimmedSections),
            parameters: $this->parameters,
        );
    }

    // INTERNAL ////////////////////////////////////////////////////

    protected function mergeSection(Section $section) : static {
        if ($this->section($section->name)->exists()) {
            $existingSection = $this->section($section->name)->get();
            $mergedSection = $existingSection->mergeSection($section);
            return $this->withSectionReplaced($section->name, $mergedSection);
        }
        return $this->withSectionAdded($section);
    }
}