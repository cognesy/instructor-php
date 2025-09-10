<?php declare(strict_types=1);

namespace Cognesy\Messages\MessageStore;

use Cognesy\Messages\Messages;

class SectionAccessor {
    private MessageStore $store;
    private string $sectionName;

    public function __construct(
        MessageStore $store,
        string $sectionName,
    ) {
        $this->store = $store;
        $this->sectionName = $sectionName;
    }

    public function name(): string {
        return $this->sectionName;
    }

    public function get(): Section {
        if (!$this->store->sections()->has($this->sectionName)) {
            return Section::empty($this->sectionName);
        }
        return $this->store->sections()->get($this->sectionName);
    }

    public function exists(): bool {
        return $this->store->sections()->has($this->sectionName);
    }

    public function isEmpty(): bool {
        return $this->get()->isEmpty();
    }

    public function isNotEmpty(): bool {
        return !$this->isEmpty();
    }

    public function messages(): Messages {
        return $this->get()->messages();
    }
}