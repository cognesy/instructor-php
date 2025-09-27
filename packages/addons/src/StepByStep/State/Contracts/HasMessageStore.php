<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\State\Contracts;

use Cognesy\Messages\Messages;
use Cognesy\Messages\MessageStore\MessageStore;

interface HasMessageStore
{
    public function messages(): Messages;
    public function store(): MessageStore;
    public function withMessageStore(MessageStore $store): static;
    public function withMessages(Messages $messages): static;
}