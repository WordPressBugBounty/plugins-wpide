<?php

namespace WPIDE\App\Services\Snippets\Generators;

use WPIDE\App\Services\Snippets\Generators\Support\PhpCode;

/**
 * Registers and/or enqueues JavaScript files the WordPress way.
 */
class EnqueueScript extends Generator
{
    use PhpCode;

    public function id(): string
    {
        return 'enqueue_script';
    }

    public function info(): array
    {
        return [
            'title' => __('Register Scripts', 'wpide'),
            'description' => __('Load JavaScript files with wp_register_script() / wp_enqueue_script(), including dependencies, versioning and loading strategy.', 'wpide'),
            'category' => 'wordpress',
            'tags' => ['scripts', 'javascript', 'enqueue', 'assets'],
        ];
    }

    public function fields(): array
    {
        return [
            'general' => [
                'label' => __('General', 'wpide'),
                'fields' => [
                    $this->functionNameField('wpide_enqueue_scripts'),
                    $this->hookField(),
                ],
            ],
            'scripts' => [
                'label' => __('Scripts', 'wpide'),
                'fields' => [
                    [
                        'id' => 'script',
                        'type' => 'repeater',
                        'label' => __('Scripts', 'wpide'),
                        'description' => __('Relative paths (e.g. js/custom.js) are loaded from the active theme folder.', 'wpide'),
                        'default' => [
                            ['script_name' => 'custom-script', 'script_url' => 'js/custom.js', 'script_dependencies' => '', 'script_version' => '1.0.0', 'script_location' => 'true', 'script_strategy' => 'defer', 'script_deregister' => 'false', 'script_enqueue' => 'true'],
                        ],
                        'fields' => [
                            $this->text('script_name', __('Handle', 'wpide'), '', __('Unique script name.', 'wpide'), 'custom-script'),
                            $this->text('script_url', __('Script URL', 'wpide'), '', __('Absolute URL, site path starting with /, or a path relative to the active theme.', 'wpide'), 'https://example.com/script.js'),
                            $this->text('script_dependencies', __('Dependencies', 'wpide'), '', __('Comma separated handles that must load first, e.g. jquery.', 'wpide'), 'jquery'),
                            $this->text('script_version', __('Version', 'wpide'), '1.0.0', __('Appended as ?ver= for cache busting. Leave empty to use the WordPress version.', 'wpide')),
                            [
                                'id' => 'script_location',
                                'type' => 'select',
                                'label' => __('Load in', 'wpide'),
                                'default' => 'true',
                                'options' => ['true' => __('Footer', 'wpide'), 'false' => __('Header', 'wpide')],
                            ],
                            [
                                'id' => 'script_strategy',
                                'type' => 'select',
                                'label' => __('Loading strategy', 'wpide'),
                                'description' => __('Requires WordPress 6.3 or newer.', 'wpide'),
                                'default' => '',
                                'options' => ['' => __('Default (blocking)', 'wpide'), 'defer' => 'defer', 'async' => 'async'],
                            ],
                            $this->toggle('script_deregister', __('Deregister first', 'wpide'), false, __('Remove a script already registered with this handle (e.g. to replace it).', 'wpide')),
                            $this->toggle('script_enqueue', __('Enqueue', 'wpide'), true, __('Choose No to only register the script so it can be enqueued later.', 'wpide')),
                        ],
                    ],
                ],
            ],
        ];
    }

    protected function hookField(): array
    {
        return [
            'id' => 'hook',
            'type' => 'select',
            'label' => __('Load on', 'wpide'),
            'default' => 'wp_enqueue_scripts',
            'options' => [
                'wp_enqueue_scripts' => __('Frontend (wp_enqueue_scripts)', 'wpide'),
                'admin_enqueue_scripts' => __('Admin (admin_enqueue_scripts)', 'wpide'),
                'login_enqueue_scripts' => __('Login page (login_enqueue_scripts)', 'wpide'),
                'enqueue_embed_scripts' => __('Embeds (enqueue_embed_scripts)', 'wpide'),
                'enqueue_block_assets' => __('Blocks, editor and frontend (enqueue_block_assets)', 'wpide'),
            ],
        ];
    }

    /**
     * PHP expression for an asset URL.
     */
    protected function assetUrl(string $url): string
    {
        $url = trim($url);

        if (preg_match('#^(https?:)?//#i', $url)) {
            return $this->str($url);
        }

        if (strpos($url, '/') === 0) {
            return 'home_url( ' . $this->str($url) . ' )';
        }

        return 'get_stylesheet_directory_uri() . ' . $this->str('/' . ltrim($url, './'));
    }

    protected function version(string $version): string
    {
        return trim($version) === '' ? 'false' : $this->str(trim($version));
    }

    protected function handle(string $handle): string
    {
        return $this->key($handle, '', 0, 'a-z0-9_\-.');
    }

    public function generate(array $values): string
    {
        $function = $this->fn($values['function_name'], 'wpide_enqueue_scripts');
        $body = [];

        foreach ($values['script'] as $row) {
            $handle = $this->handle($row['script_name']);

            if ($handle === '') {
                continue;
            }

            $lines = [];

            if ($this->on($row['script_deregister'])) {
                $lines[] = 'wp_deregister_script( ' . $this->str($handle) . ' );';
            }

            if (trim($row['script_url']) !== '') {
                $footer = $this->on($row['script_location']);
                $deps = $this->strList(array_map([$this, 'handle'], $this->csv($row['script_dependencies'])));
                $args = $row['script_strategy'] !== ''
                    ? 'array( \'in_footer\' => ' . $this->bool($footer) . ', \'strategy\' => ' . $this->str($row['script_strategy']) . ' )'
                    : $this->bool($footer);

                $lines[] = 'wp_register_script( ' . $this->str($handle) . ', ' . $this->assetUrl($row['script_url']) . ', ' . $deps . ', ' . $this->version($row['script_version']) . ', ' . $args . ' );';
            }

            if ($this->on($row['script_enqueue'])) {
                $lines[] = 'wp_enqueue_script( ' . $this->str($handle) . ' );';
            }

            $body[] = implode("\n", $lines);
        }

        return "// Loads custom scripts.\n"
            . $this->guardedFunction($function, '', $body ? implode("\n\n", $body) : '// Add at least one script.')
            . 'add_action( ' . $this->str($values['hook']) . ', ' . $this->str($function) . " );\n";
    }
}
