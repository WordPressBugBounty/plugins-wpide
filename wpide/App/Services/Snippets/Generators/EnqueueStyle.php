<?php

namespace WPIDE\App\Services\Snippets\Generators;

/**
 * Registers and/or enqueues stylesheets the WordPress way.
 */
class EnqueueStyle extends EnqueueScript
{
    public function id(): string
    {
        return 'enqueue_style';
    }

    public function info(): array
    {
        return [
            'title' => __('Register Stylesheets', 'wpide'),
            'description' => __('Load CSS files with wp_register_style() / wp_enqueue_style(), including dependencies, versioning and media queries.', 'wpide'),
            'category' => 'wordpress',
            'tags' => ['styles', 'css', 'enqueue', 'assets'],
        ];
    }

    public function fields(): array
    {
        return [
            'general' => [
                'label' => __('General', 'wpide'),
                'fields' => [
                    $this->functionNameField('wpide_enqueue_styles'),
                    $this->hookField(),
                ],
            ],
            'styles' => [
                'label' => __('Stylesheets', 'wpide'),
                'fields' => [
                    [
                        'id' => 'style',
                        'type' => 'repeater',
                        'label' => __('Stylesheets', 'wpide'),
                        'description' => __('Relative paths (e.g. css/custom.css) are loaded from the active theme folder.', 'wpide'),
                        'default' => [
                            ['style_name' => 'custom-style', 'style_url' => 'css/custom.css', 'style_dependencies' => '', 'style_version' => '1.0.0', 'style_media' => 'all', 'style_deregister' => 'false', 'style_enqueue' => 'true'],
                        ],
                        'fields' => [
                            $this->text('style_name', __('Handle', 'wpide'), '', __('Unique stylesheet name.', 'wpide'), 'custom-style'),
                            $this->text('style_url', __('Stylesheet URL', 'wpide'), '', __('Absolute URL, site path starting with /, or a path relative to the active theme.', 'wpide'), 'https://example.com/style.css'),
                            $this->text('style_dependencies', __('Dependencies', 'wpide'), '', __('Comma separated handles that must load first.', 'wpide'), 'wp-block-library'),
                            $this->text('style_version', __('Version', 'wpide'), '1.0.0', __('Appended as ?ver= for cache busting. Leave empty to use the WordPress version.', 'wpide')),
                            $this->text('style_media', __('Media', 'wpide'), 'all', __('Media type or query, e.g. all, print, (max-width: 640px).', 'wpide'), 'all'),
                            $this->toggle('style_deregister', __('Deregister first', 'wpide'), false, __('Remove a stylesheet already registered with this handle (e.g. to replace it).', 'wpide')),
                            $this->toggle('style_enqueue', __('Enqueue', 'wpide'), true, __('Choose No to only register the stylesheet so it can be enqueued later.', 'wpide')),
                        ],
                    ],
                ],
            ],
        ];
    }

    public function generate(array $values): string
    {
        $function = $this->fn($values['function_name'], 'wpide_enqueue_styles');
        $body = [];

        foreach ($values['style'] as $row) {
            $handle = $this->handle($row['style_name']);

            if ($handle === '') {
                continue;
            }

            $lines = [];

            if ($this->on($row['style_deregister'])) {
                $lines[] = 'wp_deregister_style( ' . $this->str($handle) . ' );';
            }

            if (trim($row['style_url']) !== '') {
                $deps = $this->strList(array_map([$this, 'handle'], $this->csv($row['style_dependencies'])));
                $media = trim($row['style_media']) !== '' ? trim($row['style_media']) : 'all';

                $lines[] = 'wp_register_style( ' . $this->str($handle) . ', ' . $this->assetUrl($row['style_url']) . ', ' . $deps . ', ' . $this->version($row['style_version']) . ', ' . $this->str($media) . ' );';
            }

            if ($this->on($row['style_enqueue'])) {
                $lines[] = 'wp_enqueue_style( ' . $this->str($handle) . ' );';
            }

            $body[] = implode("\n", $lines);
        }

        return "// Loads custom stylesheets.\n"
            . $this->guardedFunction($function, '', $body ? implode("\n\n", $body) : '// Add at least one stylesheet.')
            . 'add_action( ' . $this->str($values['hook']) . ', ' . $this->str($function) . " );\n";
    }
}
