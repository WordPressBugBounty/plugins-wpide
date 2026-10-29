<?php

namespace WPIDE\App\Services\Snippets\Registry;

use WPIDE\App\Services\Snippets\Plans;

/**
 * Snippet code types.
 *
 * IDs match the WPCode reference so imports map one to one. Each type declares how it is
 * edited (ACE mode), which capability edits it and whether it executes PHP.
 */
class Types
{
    const PHP = 'php';
    const UNIVERSAL = 'universal';
    const HTML = 'html';
    const TEXT = 'text';
    const BLOCKS = 'blocks';
    const CSS = 'css';
    const SCSS = 'scss';
    const JS = 'js';

    /** Types WPCode Lite shows with a PRO badge. */
    const PRO = [self::SCSS, self::BLOCKS];

    public static function all(): array
    {
        return [
            self::PHP => [
                'label' => __('PHP', 'wpide'),
                'description' => __('Runs PHP code on your site, like a small plugin. Write it without the opening <?php tag.', 'wpide'),
                'mode' => 'php',
                'capability' => 'wpide_snippets_edit_php',
                'executes_php' => true,
            ],
            self::UNIVERSAL => [
                'label' => __('Universal', 'wpide'),
                'description' => __('HTML with embedded <?php ?> blocks, rendered wherever it is inserted.', 'wpide'),
                'mode' => 'php',
                'capability' => 'wpide_snippets_edit_php',
                'executes_php' => true,
            ],
            self::HTML => [
                'label' => __('HTML', 'wpide'),
                'description' => __('Markup, embeds and third-party scripts printed as they are.', 'wpide'),
                'mode' => 'html',
                'capability' => 'wpide_snippets_edit_markup',
                'executes_php' => false,
            ],
            self::JS => [
                'label' => __('JavaScript', 'wpide'),
                'description' => __('Script printed inside a <script> tag or loaded as a file.', 'wpide'),
                'mode' => 'javascript',
                'capability' => 'wpide_snippets_edit_markup',
                'executes_php' => false,
            ],
            self::CSS => [
                'label' => __('CSS', 'wpide'),
                'description' => __('Styles printed inside a <style> tag or loaded as a file.', 'wpide'),
                'mode' => 'css',
                'capability' => 'wpide_snippets_edit_markup',
                'executes_php' => false,
            ],
            self::SCSS => [
                'label' => __('SCSS', 'wpide'),
                'description' => __('SCSS compiled to CSS when saved, then output like a CSS snippet.', 'wpide'),
                'mode' => 'scss',
                'capability' => 'wpide_snippets_edit_markup',
                'executes_php' => false,
            ],
            self::TEXT => [
                'label' => __('Text', 'wpide'),
                'description' => __('Formatted text content for people who should not write code.', 'wpide'),
                'mode' => 'html',
                'capability' => 'wpide_snippets_edit_text',
                'executes_php' => false,
            ],
            self::BLOCKS => [
                'label' => __('Blocks', 'wpide'),
                'description' => __('Block editor content rendered with its blocks wherever it is inserted.', 'wpide'),
                'mode' => 'html',
                'capability' => 'wpide_snippets_edit_text',
                'executes_php' => false,
            ],
        ];
    }

    public static function isPro(string $type): bool
    {
        return in_array($type, self::PRO, true);
    }

    /**
     * A Pro type on a site without Pro: it can't be chosen and its snippets don't run.
     */
    public static function isLocked(string $type): bool
    {
        return self::isPro($type) && !Plans::pro();
    }

    public static function ids(): array
    {
        return array_keys(self::all());
    }

    public static function exists($type): bool
    {
        return is_string($type) && isset(self::all()[$type]);
    }

    public static function get(string $type): ?array
    {
        $all = self::all();

        return $all[$type] ?? null;
    }

    public static function executesPhp(string $type): bool
    {
        return in_array($type, [self::PHP, self::UNIVERSAL], true);
    }

    public static function capability(string $type): string
    {
        $definition = self::get($type);

        return $definition ? $definition['capability'] : 'wpide_snippets_edit_php';
    }
}
