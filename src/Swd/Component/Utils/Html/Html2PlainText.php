<?php

namespace Swd\Component\Utils\Html;

use DOMDocument, DOMXPath, DOMNode;

use function sprintf;

/**
 * Зачистка HTML до состояния теста
 *
 * @packaged default
 * @author skoryukin
 **/
class Html2PlainText
{
    protected $template = "<html><meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\" /><body>%s</body></html>";

    const BLOCK_BEGIN = 1;
    const BLOCK_END = 2;
    /**
     * Теги которые не обрабатываются
     *
     * @var array
     **/
    protected $ignoreTags = array(
        "form",
        "meta",
    );

    protected $options;

    public function __construct(array $options = [])
    {
        $this->options = array_merge([
            'merge_whitespace' => false,
            'ignore_newline' => false,
            'depth_limit' => 20,
            'tags_limit' => 1000,
            'block_tags' => ['tr', 'br', 'div', 'p', 'ol', 'ul', 'h1', 'h2', 'h3', 'h4', 'h5'],
        ], $options);
    }

    /**
     * Вернет строковое представление (видимый контент) для html
     *
     * Особая опасность может быть в экранированном тексте, xss фильтрация должна проходить
     * ДО очистки от тегов иначе "&lt;" превратится в "<" и c entities также "&#128540;" => "😜"
     *
     *
     * @param string $html
     * @return string
     */
    public function process(string $html): string
    {
        return $this->processDom($this->wrapHtml($html));
    }

    /**
     * @param string $html
     *
     * @return DOMDocument
     */
    public function wrapHtml(string $html)
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;

        /** @noinspection PhpUsageOfSilenceOperatorInspection */
        @$dom->loadHTML(sprintf($this->template, $html));

        return $dom;
    }

    /**
     * @param DOMDocument $doc
     * @return string
     */
    public function processDom(\DOMDocument $doc): string
    {
        $result = '';
        $doc->normalizeDocument();
        $xpath = new DOMXPath($doc);
        $roots = $xpath->query("/html/body");
        $root = ($roots->item($roots->length - 1));

        $nodesList = $root->childNodes;
        $listPos = 0;
        $listLen = $root->childNodes->length;


        $stack = [];

        $tLimit = $this->options['tags_limit'];
        $sLimit = $this->options['depth_limit'];

        $i = 0;
        $lastText = false;
        while ($listPos < $listLen) {
            $i++;

            if ($tLimit !== false && ($i >= $tLimit)) {
                break;
            }

            /** @var DOMNode $node */
            $node = $nodesList->item($listPos);
            $listPos++;
            $closeBlock = false;
            if ($listPos >= $listLen && !empty($stack)) {
                list($nodesList, $listPos, $listLen, $closeBlock) = array_pop($stack);
                $lastText = false;
            }


            if ($node->nodeType == XML_TEXT_NODE) {
                $node->normalize();
                $text = $node->nodeValue;

                $textTest = trim($text);
                if (!empty($textTest)) {
                    if ($this->options['merge_whitespace']) {
                        $text = $this->mergeWhiteSpace($text);
                    }
                    if ($this->options['ignore_newline']) {
                        $text = str_replace("\n", ' ', $text);
                    }
                    if (!$lastText) {
                        $text = ltrim($text);
                    }
                    $lastText = true;
                    $result .= $text;
                }
            } elseif ($node->nodeType === XML_ELEMENT_NODE) {
                if ($node->nodeName == 'br') {
                    $result .= "\n";
                    $lastText = false;
                }
            }


            $blockTag = in_array($node->nodeName, $this->options['block_tags']);
            if ($blockTag && ($lastText || in_array($node->nodeName, ['tr']))) {
                $result .= "\n";
            }

            if (in_array($node->nodeName, ['th', 'td']) && $node !== $node->parentNode->firstChild) {
                $result .= " ";
            }


            if ($node->hasChildNodes()) {
                if ($sLimit !== false && (count($stack) >= $sLimit)) {
                    break;
                }

                array_push($stack, [$nodesList, $listPos, $listLen, $blockTag]);

                $lastText = false;
                $listPos = 0;
                $listLen = $node->childNodes->length;
                $nodesList = $node->childNodes;
            }

            if ($closeBlock && $lastText) {
                $result .= "\n";
                $lastText = false;
            }
        }

        return rtrim($result);
    }

    public function extractNodes(DOMNode $root, array &$ar, $inBeginning = false)
    {
        if ($root->childNodes->length <= 0) {
            return;
        }

        if ($inBeginning) {
            $len = $root->childNodes->length;
            //add in reverse order
            for ($i = ($len - 1); $i >= 0; $i--) {
                $node = $root->childNodes->item($i);
                array_unshift($ar, $node);
            }
        } else {
            foreach ($root->childNodes as $node) {
                array_push($ar, $node);
            }
        }
    }

    protected function getTextNodeValue($node)
    {
    }

    protected function mergeWhiteSpace($text)
    {
        return preg_replace("/[[:space:]][[:space:]]{1,}/mu", ' ', $text);
    }
}
