<?php
namespace Cognesy\Instructor\Data\Messages;

class Script {
    use Traits\Script\HandlesAccess;
    use Traits\Script\HandlesContext;
    use Traits\Script\HandlesMutation;
    use Traits\Script\HandlesReordering;
    use Traits\Script\HandlesTransformation;
    use Traits\RendersTemplates;

    /** @var Section[] */
    private array $sections;

    public function __construct(Section ...$sections) {
        $this->sections = $sections;
    }

    /**
     * @param array<string, string|array> $sections
     * @return static
     */
    static public function fromArray(array $sections) : self {
        $sectionList = [];
        foreach ($sections as $name => $content) {
            $sectionList[] = (new Section($name))->appendMessages(
                match(true) {
                    is_string($content) => Messages::fromString('user', $content),
                    is_array($content) => Messages::fromArray($content),
                }
            );
        }
        return new self(...$sectionList);
    }
}
