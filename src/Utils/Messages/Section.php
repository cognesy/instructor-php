<?php
namespace Cognesy\Instructor\Utils\Messages;

class Section {
    use Traits\Section\HandlesAccess;
    use Traits\Section\HandlesConversion;
    use Traits\Section\HandlesHeaderFooter;
    use Traits\Section\HandlesMutation;
    use Traits\Section\HandlesTransformation;

    public const MARKER = '@';
    private Messages $messages;
    private Messages $header;
    private Messages $footer;
    private bool $isTemplate = false;

    public function __construct(
        public string $name,
        public string $description = '',
    ) {
        if (str_starts_with($name, self::MARKER)) {
            $this->isTemplate = true;
        }
        $this->messages = new Messages();
        $this->header = new Messages();
        $this->footer = new Messages();
    }
}
