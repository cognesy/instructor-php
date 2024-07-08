<?php
namespace Cognesy\Instructor\Extras\Module\Modules\Markdown\Utils;

/**
 * Class for splitting markdown into an array of sections
 * Source: https://github.com/diversen/markdown-split-by-header/
 * Author: Dennis Iversen
 * @license MIT
 */

class MarkdownSplitter
{
    /**
     * setext regex
     * @var string
     */
    var $setextRegex = '{ ^(.+?)[ ]*\n(=+|-+)[ ]*\n+ }mx';

    /**
     * atx regex
     * @var string
     */
    var $atxRegex = '{
				^(\#{1,6})	# $1 = string of #\'s
				[ ]*
				(.+?)		# $2 = Header text
				[ ]*
				\#*			# optional closing #\'s (not counted)
				\n+
			}xm';

    /**
     * Change setext headers to atx
     * @param  string $text
     * @return string $text
     */
    public function normalize($text) {
        $text = preg_replace_callback($this->setextRegex, array($this, '_doHeaders_callback_setext'), $text);
        return $text;
    }

    /**
     * Transform setext to atx headers callback
     * @param string $matches
     * @return string
     */
    protected function _doHeaders_callback_setext($matches) {
        // Terrible hack to check we haven't found an empty list item.
        if ($matches[2] === '-' && preg_match('{^-(?: |$)}', $matches[1])) {
            return $matches[0];
        }

        $level = $matches[2][0] === '=' ? '#' : '##';
        // ID attribute generation
        return ($level. ' ' . $matches[1] . "\n\n");
        // return "\n" . $this->hashBlock($block) . "\n\n";
    }

    /**
     * Split markdown string into an array by headers
     * @param string $text
     * @param boolean $setext Use and transform setext headers to atx headers
     * @return array $ret array of sub-arrays containing ['header', 'header_md', 'body', 'level']
     */
    public function splitMarkdown($text, $setext = false) {
        if ($setext) {
            $text = $this->normalize($text);
        }

        $headers = $sections = [];

        preg_match_all($this->atxRegex, $text, $headers);
        $headers_md = $headers[0];
        $headers_level = $headers[1];
        $headers_names = $headers[2];

        $sections = preg_split($this->atxRegex, $text);

        // Before any headers
        $ret = [];
        $ret[0]['header'] = '';
        $ret[0]['header_md'] = '';
        $ret[0]['body'] = '';

        $i = 1;

        foreach ($sections as $key => $section) {
            if ($key == 0) {
                continue;
            }

            $current_level = strlen($headers_level[$key - 1]);

            $ret[$i]['header'] = $headers_names[$key - 1];
            $ret[$i]['header_md'] = $headers_md[$key - 1];
            $ret[$i]['level'] = $current_level;
            $ret[$i]['body'] = $section;
            $i++;
        }

        return $ret;
    }

    /**
     * Split a markdown text into an array. You can specify header level to split at.
     * E.g. 3 as split level means that a header has to be at least level 3 (###) in order
     * to by placed in it as own array. E.g. 1: Then only top level headers get an array
     * All subsequent headers will be placed under the array of the parent header
     * @param string $text
     * @param boolean $setext Use and transform setext headers to atx headers
     * @param int $split_level 1-6
     * @return array $ret array of sub-arrays containing ['header', 'header_md', 'body', 'level']
     */
    public function splitMarkdownAtLevel($text, $setext = false, $split_level = 1) {
        $ary = $this->splitMarkdown($text, $setext);

        $i = 0;
        $ret = [];
        $ret[] = $ary[0];
        foreach ($ary as $key => $val) {
            if ($key == 0) {
                continue;
            }


            if ($val['level'] == $split_level) {
                $i++;
                $ret[$i] = $val;
                continue;
            }

            // Current level is bigger than split level - attach
            if ($val['level'] > $split_level) {
                $ret[$i]['body'].= $val['header_md'];
                $ret[$i]['body'].= $val['body'];
                continue;
            }

            $i++;
            $ret[$i] = $val;

        }
        return $ret;
    }
}