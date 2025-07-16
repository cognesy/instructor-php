<?php declare(strict_types=1);
namespace Cognesy\InstructorHub\Utils;

use cebe\markdown\GithubMarkdown;
use Cognesy\InstructorHub\Core\Cli;
use Cognesy\Utils\Cli\Color as IColor;
use Toolkit\Cli\Color;
use Toolkit\Cli\Color\ColorTag;
use Toolkit\Cli\Util\Highlighter;
use function array_merge;
use function array_sum;
use function count;
use function explode;
use function implode;
use function ltrim;
use function mb_strlen;
use function sprintf;
use function str_pad;
use function str_repeat;
use function str_replace;
use function substr;
use function trim;
use function ucwords;

/**
 * Class CliMarkdown
 *
 * @package PhpPkg\CliMarkdown
 * @link    https://github.com/charmbracelet/glow color refer
 * @license MIT
 */
class CliMarkdown extends GithubMarkdown
{
    public const NL = "\n";
    public const NL2 = "\n\n";
    public const POINT = '•●○◦◉◎⦿✓✔︎✕✖︎✗';
    public const LANG_EN = 'en';
    public const GITHUB_HOST = 'https://github.com/';
    public const THEME_DEFAULT = [
        'headline' => IColor::WHITE,
        'paragraph' => '',
        'list' => '',
        'image' => 'info',
        'link' => IColor::BLUE,
        'code' => 'brown',
        'quote' => IColor::DARK_YELLOW,
        'strong' => 'bold',
        'inlineCode' => IColor::YELLOW,
    ];
    private string $lang;
    private array $theme = self::THEME_DEFAULT;

    public function __construct(string $lang = '') {
        $this->lang = $lang;
    }

    public function parse($text): string {
        $parsed = parent::parse($text);

        return str_replace(["\n\n\n", "\n\n\n\n"], "\n\n", ltrim($parsed));
    }

    public function render(string $text): string {
        $parsed = $this->parse($text);

        return Color::parseTag($parsed);
    }

    protected function renderHeadline($block): string {
        $level = (int)$block['level'];

        $prefix = str_repeat('#', $level);
        $title = $this->renderAbsy($block['content']);

        if ($this->lang === self::LANG_EN) {
            $title = ucwords($title);
        }

        $hlText = $prefix . ' ' . $title;

        $out = [
            Cli::strln(),
            Cli::strln(),
            Cli::strln($hlText, $this->theme['headline']),
            Cli::strln(),
        ];

        return implode('', $out);
        //return self::NL . ColorTag::add($hlText, $this->theme['headline']) . self::NL2;
    }

    protected function renderParagraph($block): string {
        return self::NL . $this->renderAbsy($block['content']) . self::NL;
    }

    protected function renderList($block): string {
        $output = self::NL;

        foreach ($block['items'] as $itemLines) {
            $output .= ' • ' . $this->renderAbsy($itemLines) . "\n";
        }

        return $output . self::NL2;
    }

    protected function renderTable($block): string {
        $head = $body = '';

        $tabInfo = ['width' => 60];
        $colWidths = [];
        foreach ($block['rows'] as $row) {
            foreach ($row as $c => $cell) {
                $cellLen = $this->getCellWith($cell);

                if (!isset($tabInfo[$c])) {
                    $colWidths[$c] = 16;
                }

                $colWidths[$c] = $this->compareMax($cellLen, $colWidths[$c]);
            }
        }

        $colCount = count($colWidths);
        $tabWidth = (int)array_sum($colWidths);

        $first = true;
        $splits = [];
        foreach ($block['rows'] as $row) {
            $tds = [];
            foreach ($row as $c => $cell) {
                $cellLen = $colWidths[$c];

                // ︱｜｜—―￣==＝＝▪▪▭▭▃▃▄▄▁▁▕▏▎┇╇══
                if ($first) {
                    $splits[] = str_pad('=', $cellLen + 1, '=');
                }

                $lastIdx = count($cell) - 1;
                // padding space to last item contents.
                foreach ($cell as $idx => &$item) {
                    if ($lastIdx === $idx) {
                        $item[1] = str_pad($item[1], $cellLen);
                    } else {
                        $cellLen -= mb_strlen($item[1]);
                    }
                }
                unset($item);

                $tds[] = trim($this->renderAbsy($cell), "\n\r");
            }

            $tdsStr = implode(' | ', $tds);
            if ($first) {
                $head .= implode('=', $splits) . "\n$tdsStr\n" . implode('|', $splits) . "\n";
            } else {
                $body .= "$tdsStr\n";
            }
            $first = false;
        }

        return $head . $body . str_pad('=', $tabWidth + $colCount + 1, '=') . self::NL;
    }

    protected function getCellWith(array $cellElems): int {
        $width = 0;
        foreach ($cellElems as $elem) {
            $width += mb_strlen($elem[1] ?? '');
        }

        return $width;
    }

    protected function renderLink($block): string {
        return Cli::str($block['orig'], $this->theme['link']);
        //return ColorTag::add('♆ ' . $block['orig'], $this->theme['link']);
    }

    protected function renderUrl($block): string {
        return parent::renderUrl($block);
    }

    protected function renderAutoUrl($block): string {
        $tag = $this->theme['link'];
        $url = $text = $block[1];

        if (str_contains($url, self::GITHUB_HOST)) {
            $text = substr($text, 19);
        }

        return sprintf('<%s>[%s]%s</%s>', $tag, $text, $url, $tag);
    }

    protected function renderImage($block): string {
        return self::NL . Color::addTag('▨ ' . $block['orig'], $this->theme['image']);
    }

    protected function renderQuote($block): string {
        // ¶ §
        //$prefix = Color::render('¶ ', [Color::FG_GREEN, Color::BOLD]);
        //$content = ltrim($this->renderAbsy($block['content']));
        //return self::NL . $prefix . ColorTag::add($content, $this->theme['quote']);
        $content = $block['content'][0]['content'][0][1] ?? '';
        $color = $this->theme['quote'];
        return implode('', [
            Cli::strln(),
            Cli::smargin($content, 6, $color, $color),
            Cli::strln(),
        ]);
    }

    protected function oldRenderCode($block): string {
        $lines = explode(self::NL, $block['content']);
        $text = implode("\n    ", $lines);

        return "\n    " . ColorTag::add($text, $this->theme['code']) . self::NL2;
    }

    protected function renderCode($block): string {
        $highlighted = Highlighter::create()->highlight($block['content']);
        $lines = explode("\n", $highlighted);
        // add line numbers to each line
        // 1. get the number of lines
        $lineCount = count($lines);
        // 2. calculate the number of digits in the line number
        $digits = strlen((string)$lineCount) + 1;
        // 3. add the line number to each line
        foreach ($lines as $i => $line) {
            $number = $i + 1;
            $number = sprintf("%{$digits}d", $number);
            $lines[$i] = implode("",[
                "   ",
                Cli::str("{$number}", IColor::DARK_GRAY),
                Cli::str(" | ", IColor::DARK_BLUE),
                $line,
            ]);
        }
        $code = implode("\n", $lines);

        $out = [
            Cli::strln(),
            $code,
            Cli::strln(),
            Cli::strln(),
        ];
        return implode('', $out);
    }

    protected function renderInlineCode($block): string {
        return Cli::str($block[1], $this->theme['inlineCode']);
        //return ColorTag::add($block[1], $this->theme['inlineCode']);
    }

    protected function renderStrong($block): string {
        $text = $this->renderAbsy($block[1]);

        return ColorTag::add("**$text**", $this->theme['strong']);
    }

    protected function renderText($text): string {
        return $text[1];
    }

    public function getTheme(): array {
        return $this->theme;
    }

    public function setTheme(array $theme): void
    {
        $this->theme = array_merge($this->theme, $theme);
    }

    private function compareMax(int $len1, int $len2): int {
        return $len1 > $len2 ? $len1 : $len2;
    }
}