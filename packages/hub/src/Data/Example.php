<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Data;

use Cognesy\Auxiliary\Mintlify\NavigationItem;

class Example
{
    public function __construct(
        public int    $index = 0,
        public string $tab = '',
        public string $group = '',
        public string $groupTitle = '',
        public string $name = '',
        public bool   $hasTitle = false,
        public string $title = '',
        public string $docName = '',
        public string $id = '',
        public string $content = '',
        public string $directory = '',
        public string $relativePath = '',
        public string $runPath = '',
        public ?int   $order = null,
    ) {}

    /**
     * @return static
     */
    public static function fromFile(
        string $baseDir,
        string $path,
        int $index = 0,
        ?ExampleGroupAssignment $assignment = null,
    ) : static {
        /** @var static */
        return self::loadExample($baseDir, $path, $index, $assignment);
    }

    public function toNavigationItem() : NavigationItem {
        return NavigationItem::fromString('cookbook' . $this->toDocPath());
    }

    public function toDocPath() : string {
        return '/' . $this->tab . '/' . $this->group . '/' . $this->docName;
    }

    // INTERNAL ////////////////////////////////////////////////////////////////////

    private static function loadExample(
        string $baseDir,
        string $path,
        int $index = 0,
        ?ExampleGroupAssignment $assignment = null,
    ) : self {
        [$group, $name] = explode('/', $path, 2);

        $info = ExampleInfo::fromFile($baseDir . $path . '/run.php', $name);

        $tab = $assignment?->tab ?? 'examples';
        $groupName = $assignment?->group ?? $group;
        $groupTitle = $assignment?->groupTitle ?? $group;
        return new Example(
            index: $index,
            tab: $tab,
            group: $groupName,
            groupTitle: $groupTitle,
            name: $name,
            hasTitle: $info->hasTitle(),
            title: $info->title,
            docName: $info->docName,
            id: $info->id,
            content: $info->content,
            directory: $baseDir . $path,
            relativePath: './' . $tab . '/' . $path . '/run.php',
            runPath: $baseDir . $path . '/run.php',
            order: $info->order,
        );
    }
}
