<?php

namespace WPIDE\App\Services\Snippets\Runtime;

/**
 * Inserts snippet output into post content, excerpts and post lists.
 *
 * Paragraph-based positions use closing </p> tags as boundaries. Requests for positions that do not exist
 * (paragraph 9 of a 6-paragraph post) are skipped and reported in the snippet diagnostics.
 * Post lists are handled per theme type: the loop for classic themes, the rendered Query Loop for block themes.
 */
class ContentInserter
{
    const CONTENT_LOCATIONS = [
        'before_content', 'after_content', 'after_post', 'before_paragraph', 'after_paragraph', 'after_words', 'every_words',
        'content_quarter', 'content_one_third', 'content_half', 'content_two_thirds', 'content_quarter_third', 'content_80',
    ];

    const FRACTIONS = [
        'content_quarter' => 0.25,
        'content_one_third' => 1 / 3,
        'content_half' => 0.5,
        'content_two_thirds' => 2 / 3,
        'content_quarter_third' => 0.75,
        'content_80' => 0.8,
    ];

    /** @var int Index of the post being shown in the current loop. */
    protected static $loopIndex = 0;

    /** @var bool */
    protected static $beforePostDone = false;

    /** @var array Positions that could not be honoured on this request: id => reason. */
    protected static $skipped = [];

    public static function hooks()
    {
        add_filter('the_content', [__CLASS__, 'filterContent'], 20);
        add_filter('the_excerpt', [__CLASS__, 'filterExcerpt'], 20);
        add_action('the_post', [__CLASS__, 'onThePost'], 10, 2);
        add_action('loop_end', [__CLASS__, 'onLoopEnd']);
        add_filter('render_block_core/post-template', [__CLASS__, 'filterQueryLoop'], 20, 2);
        add_filter('render_block_core/post-title', [__CLASS__, 'filterPostTitle'], 20, 2);
    }

    public static function skipped(): array
    {
        return self::$skipped;
    }

    protected static function isMainSingular(): bool
    {
        if (is_admin() || is_feed() || !is_singular() || !is_main_query() && !in_the_loop()) {
            return false;
        }

        $id = get_the_ID();

        return $id && (int) $id === (int) get_queried_object_id();
    }

    public static function filterContent($content)
    {
        if (!is_string($content) || !self::isMainSingular() || doing_action('wp_head')) {
            return $content;
        }

        $snippets = [];

        foreach (self::CONTENT_LOCATIONS as $location) {
            foreach (Runtime::snippetsFor($location) as $snippet) {
                $snippets[] = $snippet;
            }
        }

        if (!$snippets) {
            return $content;
        }

        return self::insert($content, $snippets);
    }

    /**
     * Apply content locations to an HTML string.
     */
    public static function insert(string $content, array $snippets): string
    {
        $boundaries = self::paragraphBoundaries($content);
        $inserts = []; // byte offset => [html, ...]
        $before = '';
        $after = '';
        $afterPost = '';

        foreach ($snippets as $snippet) {
            $location = $snippet['location'];

            if (!Runtime::shouldRun($snippet)) {
                continue;
            }

            $html = Runtime::renderTracked($snippet, 'content');

            if ($html === '') {
                continue;
            }

            switch ($location) {
                case 'before_content':
                    $before .= $html;
                    break;
                case 'after_content':
                    $after .= $html;
                    break;
                case 'after_post':
                    $afterPost .= $html;
                    break;
                case 'before_paragraph':
                case 'after_paragraph':
                    $n = (int) $snippet['insert_number'];
                    $paragraph = $boundaries[$n - 1] ?? null;

                    if (!$paragraph) {
                        self::$skipped[$snippet['id']] = sprintf(__('Paragraph %1$d does not exist (the content has %2$d).', 'wpide'), $n, count($boundaries));
                        break;
                    }

                    $offset = $location === 'before_paragraph' ? $paragraph['start'] : $paragraph['end'];
                    $inserts[$offset][] = $html;
                    break;
                case 'after_words':
                    $offset = self::offsetAfterWords($boundaries, (int) $snippet['insert_number']);

                    if ($offset === null) {
                        self::$skipped[$snippet['id']] = sprintf(__('The content has fewer than %d words.', 'wpide'), (int) $snippet['insert_number']);
                        break;
                    }

                    $inserts[$offset][] = $html;
                    break;
                case 'every_words':
                    $step = max(1, (int) $snippet['insert_number']);
                    $next = $step;
                    $last = count($boundaries) - 1;

                    foreach ($boundaries as $i => $paragraph) {
                        if ($i < $last && $paragraph['words'] >= $next) {
                            $inserts[$paragraph['end']][] = $html;

                            while ($next <= $paragraph['words']) {
                                $next += $step;
                            }
                        }
                    }
                    break;
                default:
                    if (isset(self::FRACTIONS[$location]) && $boundaries) {
                        $offset = self::offsetAtFraction($boundaries, self::FRACTIONS[$location]);
                        $inserts[$offset][] = $html;
                    }
            }
        }

        if ($inserts) {
            krsort($inserts);

            foreach ($inserts as $offset => $parts) {
                $content = substr($content, 0, $offset) . implode('', $parts) . substr($content, $offset);
            }
        }

        return $before . $content . $after . $afterPost;
    }

    /**
     * @return array[] For each paragraph: start offset of <p, end offset after </p>, cumulative word count.
     */
    public static function paragraphBoundaries(string $content): array
    {
        $boundaries = [];
        $words = 0;
        $offset = 0;

        while (($close = stripos($content, '</p>', $offset)) !== false) {
            $end = $close + 4;
            $open = strripos(substr($content, 0, $close), '<p');

            while ($open !== false && preg_match('/^<p[a-z]/i', substr($content, $open, 3))) {
                $open = $open > 0 ? strripos(substr($content, 0, $open), '<p') : false;
            }

            $start = $open !== false && $open >= $offset ? $open : $offset;
            $words += str_word_count(wp_strip_all_tags(substr($content, $start, $end - $start)));
            $boundaries[] = ['start' => $start, 'end' => $end, 'words' => $words];
            $offset = $end;
        }

        return $boundaries;
    }

    protected static function offsetAfterWords(array $boundaries, int $words): ?int
    {
        foreach ($boundaries as $paragraph) {
            if ($paragraph['words'] >= $words) {
                return $paragraph['end'];
            }
        }

        return null;
    }

    protected static function offsetAtFraction(array $boundaries, float $fraction): int
    {
        $total = end($boundaries)['words'];
        $target = $total * $fraction;
        $best = $boundaries[0]['end'];
        $bestDistance = PHP_INT_MAX;

        foreach ($boundaries as $paragraph) {
            $distance = abs($paragraph['words'] - $target);

            if ($distance < $bestDistance) {
                $best = $paragraph['end'];
                $bestDistance = $distance;
            }
        }

        return $best;
    }

    public static function filterExcerpt($excerpt)
    {
        if (!is_string($excerpt) || is_admin() || is_singular() || is_feed()) {
            return $excerpt;
        }

        return Runtime::outputFor('before_excerpt', 'archive') . $excerpt . Runtime::outputFor('after_excerpt', 'archive');
    }

    protected static function isClassicMainList(\WP_Query $query): bool
    {
        return !is_admin() && $query->is_main_query() && !$query->is_singular() && !wp_is_block_theme() && !is_feed();
    }

    public static function onThePost($post, $query = null)
    {
        if (!$query instanceof \WP_Query) {
            return;
        }

        // Single post: "before post" for classic themes.
        if ($query->is_main_query() && $query->is_singular() && !self::$beforePostDone && !wp_is_block_theme() && !is_admin()) {
            self::$beforePostDone = true;
            echo Runtime::outputFor('before_post', 'single'); // phpcs:ignore WordPress.Security.EscapeOutput
            return;
        }

        if (!self::isClassicMainList($query)) {
            return;
        }

        self::$loopIndex = $query->current_post + 1;
        $index = self::$loopIndex;

        if ($index > 1) {
            echo Runtime::outputFor('between_posts', 'archive'); // phpcs:ignore WordPress.Security.EscapeOutput
            echo self::numbered('archive_after_post', $index - 1); // phpcs:ignore WordPress.Security.EscapeOutput
        }

        echo self::numbered('archive_before_post', $index); // phpcs:ignore WordPress.Security.EscapeOutput
    }

    public static function onLoopEnd($query)
    {
        if ($query instanceof \WP_Query && self::isClassicMainList($query) && self::$loopIndex > 0) {
            echo self::numbered('archive_after_post', self::$loopIndex); // phpcs:ignore WordPress.Security.EscapeOutput
            self::$loopIndex = 0;
        }
    }

    protected static function numbered(string $location, int $index): string
    {
        $output = '';

        foreach (Runtime::snippetsFor($location) as $snippet) {
            if ((int) $snippet['insert_number'] === $index && Runtime::shouldRun($snippet)) {
                $output .= Runtime::renderTracked($snippet, 'archive');
            }
        }

        return $output;
    }

    /**
     * Block themes: place list snippets around the posts rendered by the main Query Loop.
     */
    public static function filterQueryLoop($html, $block)
    {
        if (!is_string($html) || is_admin() || is_singular() || !wp_is_block_theme()) {
            return $html;
        }

        $locations = ['between_posts', 'archive_before_post', 'archive_after_post'];
        $has = false;

        foreach ($locations as $location) {
            if (Runtime::snippetsFor($location)) {
                $has = true;
            }
        }

        if (!$has) {
            return $html;
        }

        // Top-level post items: <li class="wp-block-post ...">.
        if (!preg_match_all('/<li\b[^>]*class="[^"]*\bwp-block-post\b[^"]*"[^>]*>/i', $html, $matches, PREG_OFFSET_CAPTURE)) {
            return $html;
        }

        $starts = array_map(function ($match) {
            return $match[1];
        }, $matches[0]);
        $ends = [];

        foreach ($starts as $i => $start) {
            $limit = $starts[$i + 1] ?? strlen($html);
            $close = strrpos(substr($html, 0, $limit), '</li>');
            $ends[$i] = $close !== false && $close > $start ? $close + 5 : $limit;
        }

        $inserts = [];

        foreach ($starts as $i => $start) {
            $index = $i + 1;

            if ($index > 1) {
                $inserts[$start][] = self::wrapListItem(Runtime::outputFor('between_posts', 'archive'));
            }

            $inserts[$start][] = self::wrapListItem(self::numbered('archive_before_post', $index));
            $inserts[$ends[$i]][] = self::wrapListItem(self::numbered('archive_after_post', $index));
        }

        krsort($inserts);

        foreach ($inserts as $offset => $parts) {
            $html = substr($html, 0, $offset) . implode('', $parts) . substr($html, $offset);
        }

        return $html;
    }

    protected static function wrapListItem(string $html): string
    {
        return $html === '' ? '' : '<li class="wpide-snippet-list-item">' . $html . '</li>';
    }

    /**
     * Block themes: "before post" on singular pages goes before the main post title.
     */
    public static function filterPostTitle($html, $block)
    {
        if (!is_string($html) || self::$beforePostDone || !wp_is_block_theme() || !self::isMainSingular()) {
            return $html;
        }

        self::$beforePostDone = true;

        return Runtime::outputFor('before_post', 'single') . $html;
    }
}
