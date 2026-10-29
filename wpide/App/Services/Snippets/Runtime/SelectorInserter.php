<?php

namespace WPIDE\App\Services\Snippets\Runtime;

/**
 * Server-side insertion relative to elements matched by a CSS selector.
 *
 * The page HTML is buffered once (only when selector snippets are active) and scanned with a lightweight
 * tokenizer. Supported selectors: tag, #id, .class, [attr], [attr=value], [attr*=value], [attr^=value], [attr$=value],
 * compound forms (div#main.wide) and descendant (space) or child (>) combinations, comma lists.
 */
class SelectorInserter
{
    const VOID = ['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr'];
    const RAW = ['script', 'style', 'textarea', 'title'];

    /** @var bool */
    protected static $buffering = false;

    public static function hooks()
    {
        add_action('template_redirect', [__CLASS__, 'start'], PHP_INT_MAX);
    }

    protected static function locations(): array
    {
        return ['before_css_selector', 'after_css_selector', 'start_css_selector', 'end_css_selector', 'replace_css_selector'];
    }

    public static function start()
    {
        if (self::$buffering || is_admin() || is_feed() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }

        foreach (self::locations() as $location) {
            if (Runtime::snippetsFor($location)) {
                self::$buffering = true;
                ob_start([__CLASS__, 'apply']);

                return;
            }
        }
    }

    public static function apply($html)
    {
        if (!is_string($html) || $html === '' || stripos($html, '<html') === false) {
            return $html;
        }

        foreach (headers_list() as $header) {
            if (stripos($header, 'content-type:') === 0 && stripos($header, 'html') === false) {
                return $html;
            }
        }

        try {
            return self::insert($html, self::collect());
        } catch (\Throwable $e) {
            return $html;
        }
    }

    protected static function collect(): array
    {
        $snippets = [];

        foreach (self::locations() as $location) {
            foreach (Runtime::snippetsFor($location) as $snippet) {
                if (Runtime::shouldRun($snippet)) {
                    $snippets[] = $snippet;
                }
            }
        }

        return $snippets;
    }

    public static function insert(string $html, array $snippets, ?callable $render = null): string
    {
        if (!$snippets) {
            return $html;
        }

        $elements = self::parse($html);
        $edits = [];

        foreach ($snippets as $snippet) {
            $extra = $snippet['location_extra'] ?? [];
            $selectors = self::compile((string) ($extra['css_selector'] ?? ''));

            if (!$selectors) {
                continue;
            }

            $matches = [];

            foreach ($elements as $i => $element) {
                if (self::matchesAny($elements, $i, $selectors)) {
                    $matches[] = $i;
                }
            }

            $index = isset($extra['index']) && $extra['index'] !== '' ? (int) $extra['index'] : 0;

            if ($index > 0) {
                $matches = isset($matches[$index - 1]) ? [$matches[$index - 1]] : [];
            }

            if (!$matches) {
                continue;
            }

            $output = $render ? $render($snippet) : Runtime::renderTracked($snippet, 'selector');

            if ($output === '') {
                continue;
            }

            foreach ($matches as $i) {
                $element = $elements[$i];

                switch ($snippet['location']) {
                    case 'before_css_selector':
                        $edits[] = [$element['start'], $element['start'], $output, count($edits)];
                        break;
                    case 'after_css_selector':
                        $edits[] = [$element['end'], $element['end'], $output, count($edits)];
                        break;
                    case 'start_css_selector':
                        $edits[] = [$element['open_end'], $element['open_end'], $output, count($edits)];
                        break;
                    case 'end_css_selector':
                        $edits[] = [$element['close_start'], $element['close_start'], $output, count($edits)];
                        break;
                    case 'replace_css_selector':
                        $edits[] = [$element['start'], $element['end'], $output, count($edits)];
                        break;
                }
            }
        }

        // Apply from the end; for equal offsets keep snippet order. Skip edits overlapping a replaced range.
        usort($edits, function ($a, $b) {
            return $b[0] <=> $a[0] ?: ($b[1] <=> $a[1] ?: $b[3] <=> $a[3]);
        });

        $guard = PHP_INT_MAX;

        foreach ($edits as $edit) {
            list($from, $to, $text) = $edit;

            if ($to > $guard) {
                continue;
            }

            $html = substr($html, 0, $from) . $text . substr($html, $to);

            if ($to > $from) {
                $guard = $from;
            }
        }

        return $html;
    }

    /**
     * Tokenize HTML into elements with offsets: start, open_end, close_start, end, parent.
     */
    public static function parse(string $html): array
    {
        $elements = [];
        $stack = [];
        $length = strlen($html);
        $pos = 0;

        while ($pos < $length && ($lt = strpos($html, '<', $pos)) !== false) {
            if (substr($html, $lt, 4) === '<!--') {
                $end = strpos($html, '-->', $lt + 4);
                $pos = $end === false ? $length : $end + 3;
                continue;
            }

            if (substr($html, $lt, 2) === '<!' || substr($html, $lt, 2) === '<?') {
                $end = strpos($html, '>', $lt);
                $pos = $end === false ? $length : $end + 1;
                continue;
            }

            if (!preg_match('/\G<(\/?)([a-zA-Z][a-zA-Z0-9:-]*)((?:[^>"\']|"[^"]*"|\'[^\']*\')*)>/A', $html, $m, 0, $lt)) {
                $pos = $lt + 1;
                continue;
            }

            $tagEnd = $lt + strlen($m[0]);
            $name = strtolower($m[2]);

            if ($m[1] === '/') {
                // Close the nearest open element with this name (and any unclosed children).
                for ($s = count($stack) - 1; $s >= 0; $s--) {
                    if ($elements[$stack[$s]]['tag'] === $name) {
                        for ($k = count($stack) - 1; $k >= $s; $k--) {
                            $open = $stack[$k];
                            $elements[$open]['close_start'] = $lt;
                            $elements[$open]['end'] = $k === $s ? $tagEnd : $lt;
                        }
                        array_splice($stack, $s);
                        break;
                    }
                }

                $pos = $tagEnd;
                continue;
            }

            $index = count($elements);
            $elements[] = [
                'tag' => $name,
                'attrs' => self::attributes($m[3]),
                'start' => $lt,
                'open_end' => $tagEnd,
                'close_start' => $tagEnd,
                'end' => $tagEnd,
                'parent' => $stack ? end($stack) : null,
            ];

            $selfClosing = substr(rtrim($m[3]), -1) === '/';

            if (in_array($name, self::RAW, true)) {
                $close = stripos($html, '</' . $name, $tagEnd);
                $closeEnd = $close === false ? $length : strpos($html, '>', $close);
                $elements[$index]['close_start'] = $close === false ? $length : $close;
                $elements[$index]['end'] = $closeEnd === false ? $length : $closeEnd + 1;
                $pos = $elements[$index]['end'];
                continue;
            }

            if (!$selfClosing && !in_array($name, self::VOID, true)) {
                $stack[] = $index;
            }

            $pos = $tagEnd;
        }

        foreach ($stack as $open) {
            $elements[$open]['close_start'] = $length;
            $elements[$open]['end'] = $length;
        }

        return $elements;
    }

    protected static function attributes(string $source): array
    {
        $attrs = [];

        if (preg_match_all('/([^\s=\/>"\']+)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+)))?/', $source, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $value = $match[2] ?? '';
                if ($value === '' && isset($match[3]) && $match[3] !== '') {
                    $value = $match[3];
                }
                if ($value === '' && isset($match[4]) && $match[4] !== '') {
                    $value = $match[4];
                }
                $attrs[strtolower($match[1])] = html_entity_decode($value, ENT_QUOTES);
            }
        }

        return $attrs;
    }

    public static function isSupportedSelector(string $selector): bool
    {
        return (bool) self::compile($selector);
    }

    /**
     * @return array[]|null List of selectors; each selector is a list of [combinator, compound] from left to right.
     */
    public static function compile(string $selector): ?array
    {
        $selector = trim($selector);

        if ($selector === '') {
            return null;
        }

        $list = [];

        foreach (explode(',', $selector) as $part) {
            $part = trim(preg_replace('/\s*>\s*/', ' > ', $part));

            if ($part === '') {
                return null;
            }

            $tokens = preg_split('/\s+/', $part);
            $steps = [];
            $combinator = ' ';

            foreach ($tokens as $token) {
                if ($token === '>') {
                    if (!$steps) {
                        return null;
                    }
                    $combinator = '>';
                    continue;
                }

                $compound = self::compound($token);

                if ($compound === null) {
                    return null;
                }

                $steps[] = [$combinator, $compound];
                $combinator = ' ';
            }

            if (!$steps || $combinator === '>') {
                return null;
            }

            $list[] = $steps;
        }

        return $list;
    }

    protected static function compound(string $token): ?array
    {
        $compound = ['tag' => null, 'id' => null, 'classes' => [], 'attrs' => []];
        $pattern = '/\G(?:(\*|[a-zA-Z][a-zA-Z0-9-]*)|#([\w-]+)|\.([\w-]+)|\[\s*([\w:-]+)\s*(?:([*^$~|]?=)\s*(?:"([^"]*)"|\'([^\']*)\'|([^\]\s]*)))?\s*\])/A';
        $pos = 0;
        $length = strlen($token);

        while ($pos < $length) {
            if (!preg_match($pattern, $token, $m, 0, $pos) || $m[0] === '') {
                return null;
            }

            if (isset($m[1]) && $m[1] !== '') {
                if ($pos !== 0) {
                    return null;
                }
                $compound['tag'] = $m[1] === '*' ? null : strtolower($m[1]);
            } elseif (isset($m[2]) && $m[2] !== '') {
                $compound['id'] = $m[2];
            } elseif (isset($m[3]) && $m[3] !== '') {
                $compound['classes'][] = $m[3];
            } elseif (isset($m[4]) && $m[4] !== '') {
                $value = '';
                foreach ([6, 7, 8] as $g) {
                    if (isset($m[$g]) && $m[$g] !== '') {
                        $value = $m[$g];
                    }
                }
                $compound['attrs'][] = [strtolower($m[4]), $m[5] ?? '', $value];
            }

            $pos += strlen($m[0]);
        }

        return $compound;
    }

    protected static function matchesAny(array $elements, int $index, array $selectors): bool
    {
        foreach ($selectors as $steps) {
            if (self::matchesSteps($elements, $index, $steps, count($steps) - 1)) {
                return true;
            }
        }

        return false;
    }

    protected static function matchesSteps(array $elements, int $index, array $steps, int $step): bool
    {
        if (!self::matchesCompound($elements[$index], $steps[$step][1])) {
            return false;
        }

        if ($step === 0) {
            return true;
        }

        $combinator = $steps[$step][0];
        $parent = $elements[$index]['parent'];

        while ($parent !== null) {
            if (self::matchesSteps($elements, $parent, $steps, $step - 1)) {
                return true;
            }

            if ($combinator === '>') {
                return false;
            }

            $parent = $elements[$parent]['parent'];
        }

        return false;
    }

    protected static function matchesCompound(array $element, array $compound): bool
    {
        if ($compound['tag'] !== null && $element['tag'] !== $compound['tag']) {
            return false;
        }

        if ($compound['id'] !== null && ($element['attrs']['id'] ?? null) !== $compound['id']) {
            return false;
        }

        if ($compound['classes']) {
            $classes = preg_split('/\s+/', trim($element['attrs']['class'] ?? ''));

            foreach ($compound['classes'] as $class) {
                if (!in_array($class, $classes, true)) {
                    return false;
                }
            }
        }

        foreach ($compound['attrs'] as $attr) {
            list($name, $operator, $value) = $attr;

            if (!array_key_exists($name, $element['attrs'])) {
                return false;
            }

            $actual = $element['attrs'][$name];

            switch ($operator) {
                case '':
                    break;
                case '=':
                    if ($actual !== $value) {
                        return false;
                    }
                    break;
                case '*=':
                    if ($value === '' || strpos($actual, $value) === false) {
                        return false;
                    }
                    break;
                case '^=':
                    if ($value === '' || strpos($actual, $value) !== 0) {
                        return false;
                    }
                    break;
                case '$=':
                    if ($value === '' || substr($actual, -strlen($value)) !== $value) {
                        return false;
                    }
                    break;
                case '~=':
                    if (!in_array($value, preg_split('/\s+/', trim($actual)), true)) {
                        return false;
                    }
                    break;
                case '|=':
                    if ($actual !== $value && strpos($actual, $value . '-') !== 0) {
                        return false;
                    }
                    break;
                default:
                    return false;
            }
        }

        return true;
    }
}
