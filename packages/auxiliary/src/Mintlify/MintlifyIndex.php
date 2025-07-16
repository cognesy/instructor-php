<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Mintlify;

class MintlifyIndex
{
    public string $name = '';
    public array $logo = [];
    public string $favicon = '';
    public array $colors = [];
    public array $topbarLinks = [];
    public array $topbarCtaButton = [];
    public array $primaryTab = [];
    public array $tabs = [];
    public array $anchors = [];
    public Navigation $navigation;
    public array $footerSocials = [];
    public array $analytics = [];

    public function __construct() {}

    public static function fromFile(string $path) : static {
        if (!file_exists($path)) {
            throw new \Exception("Failed to Mintlify index file");
        }
        $json = file_get_contents($path);
        $data = json_decode($json, true);
        if ($data === null) {
            throw new \Exception("Failed to decode Mintlify index file");
        }
        return static::fromJson($data);
    }

    public function saveFile(string $path) : false|int {
        $json = json_encode($this->toArray(), JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
        return file_put_contents($path, $json);
    }

    public static function fromJson(array $data) : static {
        $index = new static();
        $index->name = $data['name'] ?? '';
        $index->logo = $data['logo'] ?? [];
        $index->favicon = $data['favicon'] ?? '';
        $index->colors = $data['colors'] ?? [];
        $index->topbarLinks = $data['topbarLinks'] ?? [];
        $index->topbarCtaButton = $data['topbarCtaButton'] ?? [];
        $index->primaryTab = $data['primaryTab'] ?? [];
        $index->tabs = $data['tabs'] ?? [];
        $index->anchors = $data['anchors'] ?? [];
        $index->navigation = Navigation::fromArray($data['navigation'] ?? []);
        $index->footerSocials = $data['footerSocials'] ?? [];
        $index->analytics = $data['analytics'] ?? [];
        return $index;
    }

    public function toArray() : array {
        return array_filter([
            'name' => $this->name,
            'logo' => $this->logo,
            'favicon' => $this->favicon,
            'colors' => $this->colors,
            'topbarLinks' => $this->topbarLinks,
            'topbarCtaButton' => $this->topbarCtaButton,
            'primaryTab' => $this->primaryTab,
            'tabs' => $this->tabs,
            'anchors' => $this->anchors,
            'navigation' => array_values($this->navigation->toArray()),
            'footerSocials' => $this->footerSocials,
            'analytics' => $this->analytics,
        ], fn($v) => !empty($v));
    }
}
