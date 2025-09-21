<?php declare(strict_types=1);

namespace Cognesy\Addons\Core\State\Traits;

use Cognesy\Messages\Messages;
use Cognesy\Messages\MessageStore\MessageStore;

trait HandlesMessageStore
{
    const DEFAULT_SECTION = 'messages';

    protected readonly MessageStore $store;

    public function messages(): Messages {
        return $this->store->section(self::DEFAULT_SECTION)->get()?->messages()
            ?? Messages::empty();
    }

    public function store(): MessageStore {
        return $this->store;
    }

    public function withStore(MessageStore $store): static {
        return $this->with(store: $store);
    }

    public function withMessages(Messages $messages) : static {
        return $this->with(store: $this->store->section(self::DEFAULT_SECTION)->setMessages($messages));
    }
}