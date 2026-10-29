<?php

namespace WPIDE\App\Services\Snippets\Generators;

use WPIDE\App\Services\Snippets\Generators\Support\PhpCode;

/**
 * Registers a custom post status.
 */
class PostStatus extends Generator
{
    use PhpCode;

    public function id(): string
    {
        return 'post-status';
    }

    public function info(): array
    {
        return [
            'title' => __('Post Status', 'wpide'),
            'description' => __('Create an extra publishing status (e.g. Archived or In Review) with its own label and visibility rules.', 'wpide'),
            'category' => 'wordpress',
            'tags' => ['post status', 'workflow', 'register_post_status'],
        ];
    }

    public function fields(): array
    {
        return [
            'general' => [
                'label' => __('General', 'wpide'),
                'fields' => [
                    $this->functionNameField('wpide_register_post_status'),
                    $this->textDomainField(),
                    $this->text('post_status', __('Status key', 'wpide'), 'archived', __('Up to 20 lowercase characters. Avoid core statuses such as publish, draft or pending.', 'wpide'), 'archived'),
                    $this->text('label', __('Label (singular)', 'wpide'), 'Archived', '', 'Archived'),
                    $this->text('label_count', __('Label (plural)', 'wpide'), 'Archived', __('Used in the status links above the posts list, e.g. "Archived (3)".', 'wpide'), 'Archived'),
                ],
            ],
            'visibility' => [
                'label' => __('Visibility', 'wpide'),
                'fields' => [
                    $this->toggle('public', __('Public', 'wpide'), true, __('Posts with this status can be viewed on the frontend.', 'wpide')),
                    $this->toggle('exclude_from_search', __('Exclude from search', 'wpide'), false),
                    $this->toggle('show_in_admin_all_list', __('Include in the "All" list', 'wpide'), true),
                    $this->toggle('show_in_admin_status_list', __('Show a status link with count', 'wpide'), true),
                ],
            ],
        ];
    }

    public function generate(array $values): string
    {
        $function = $this->fn($values['function_name'], 'wpide_register_post_status');
        $domain = $values['text_domain'];
        $status = $this->key($values['post_status'], 'archived', 20);
        $singular = $values['label'] !== '' ? $values['label'] : ucfirst($status);
        $plural = $values['label_count'] !== '' ? $values['label_count'] : $singular;
        $count = ' <span class="count">(%s)</span>';

        $args = [
            'label' => '_x( ' . $this->str($singular) . ', ' . $this->str('post status') . ', ' . $this->str($domain) . ' )',
            'label_count' => '_n_noop( ' . $this->str($singular . $count) . ', ' . $this->str($plural . $count) . ', ' . $this->str($domain) . ' )',
            'public' => $this->bool($this->on($values['public'])),
            'exclude_from_search' => $this->bool($this->on($values['exclude_from_search'])),
            'show_in_admin_all_list' => $this->bool($this->on($values['show_in_admin_all_list'])),
            'show_in_admin_status_list' => $this->bool($this->on($values['show_in_admin_status_list'])),
        ];

        return "// Registers the \"{$status}\" post status.\n"
            . $this->guardedFunction($function, '', 'register_post_status( ' . $this->str($status) . ', ' . $this->phpArray($args) . ' );')
            . "add_action( 'init', " . $this->str($function) . ", 0 );\n";
    }
}
