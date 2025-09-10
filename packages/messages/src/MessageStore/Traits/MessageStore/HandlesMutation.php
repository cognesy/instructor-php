<?php declare(strict_types=1);

namespace Cognesy\Messages\MessageStore\Traits\MessageStore;

use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Messages\MessageStore\Collections\Sections;
use Cognesy\Messages\MessageStore\MessageStore;
use Cognesy\Messages\MessageStore\MessageStoreParameters;
use Cognesy\Messages\MessageStore\Section;
use Exception;

trait HandlesMutation
{
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

        $newSections = $this->sections->add($section);
        return new static(
            sections: $newSections,
            parameters: $this->parameters,
        );
    }

    public function mergeSection(Section $section) : static {
        if ($this->hasSection($section->name)) {
            $existingSection = $this->section($section->name);
            $mergedSection = $existingSection->mergeSection($section);
            return $this->withSectionReplaced($section->name, $mergedSection);
        }

        return $this->appendSection($section);
    }

    public function mergeMessageStore(MessageStore $store) : static {
        $result = $this;
        foreach($store->sections->each() as $section) {
            $result = $result->mergeSection($section);
        }
        return $result->mergeParameters($store->parameters);
    }

    public function mergeParameters(array|MessageStoreParameters $parameters) : static {
        return new static(
            sections: $this->sections,
            parameters: $this->parameters->merge($parameters),
        );
    }

    public function withSectionRemoved(string $name) : static {
        $newSections = $this->sections->filter(fn($section) => $section->name !== $name);
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

    public function withSectionMessage(string $sectionName, array|Message $message) : static {
        $messageObj = is_array($message) ? Message::fromAny($message) : $message;
        if ($messageObj->isEmpty()) {
            return $this;
        }
        return $this->withSection($sectionName)->appendMessageToSection($sectionName, $message);
    }

    public function withSectionMessageIfEmpty(string $sectionName, array|Message $message) : static {
        $store = $this->withSection($sectionName);
        if ($store->getSection($sectionName)->isEmpty()) {
            return $store->appendMessageToSection($sectionName, $message);
        }
        return $store;
    }

    public function appendMessageToSection(string $sectionName, array|Message $message) : static {
        $section = $this->section($sectionName);
        $updatedSection = $section->appendMessage($message);
        return $this->withSectionReplaced($sectionName, $updatedSection);
    }
}