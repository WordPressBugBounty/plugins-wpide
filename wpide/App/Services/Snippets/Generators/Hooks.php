<?php

namespace WPIDE\App\Services\Snippets\Generators;

use WPIDE\App\Services\Snippets\Generators\Support\PhpCode;

/**
 * Hooks a callback to any WordPress action or filter.
 */
class Hooks extends Generator
{
    use PhpCode;

    public function id(): string
    {
        return 'hooks';
    }

    public function info(): array
    {
        return [
            'title' => __('Hooks', 'wpide'),
            'description' => __('Attach your own code to any action or filter, with the right priority and number of arguments.', 'wpide'),
            'category' => 'wordpress',
            'tags' => ['action', 'filter', 'hook', 'callback'],
        ];
    }

    public function fields(): array
    {
        return [
            'general' => [
                'label' => __('Hook', 'wpide'),
                'fields' => [
                    [
                        'id' => 'hook_type',
                        'type' => 'select',
                        'label' => __('Hook type', 'wpide'),
                        'description' => __('Actions run code at a given moment, filters receive a value and must return it.', 'wpide'),
                        'default' => 'add_action',
                        'options' => ['add_action' => __('Action (add_action)', 'wpide'), 'add_filter' => __('Filter (add_filter)', 'wpide')],
                    ],
                    $this->text('hook_name', __('Hook name', 'wpide'), 'init', __('Name of the action or filter, e.g. init, wp_footer, the_content.', 'wpide'), 'init'),
                    $this->text('callback', __('Callback function', 'wpide'), 'wpide_hook_callback_' . $this->uid(), __('Unique PHP function name for your code.', 'wpide')),
                    $this->text('priority', __('Priority', 'wpide'), '10', __('Lower numbers run earlier. WordPress uses 10 by default.', 'wpide'), '10'),
                    $this->text('arguments', __('Arguments', 'wpide'), '', __('Comma separated parameter names the hook passes, e.g. $post_id, $post. Filters need at least one.', 'wpide'), '$value'),
                ],
            ],
            'code' => [
                'label' => __('Code', 'wpide'),
                'fields' => [
                    ['id' => 'code', 'type' => 'textarea', 'label' => __('PHP code', 'wpide'), 'description' => __('Body of the callback, without the opening PHP tag. Filters should return the (modified) first argument.', 'wpide'), 'default' => '', 'placeholder' => '// Your code here'],
                ],
            ],
        ];
    }

    public function generate(array $values): string
    {
        $isFilter = $values['hook_type'] === 'add_filter';
        $function = $this->fn($values['callback'], 'wpide_hook_callback');
        $hook = trim(preg_replace('/[^A-Za-z0-9_\-\/.:\[\]{}$]/', '', $values['hook_name']));
        $hook = $hook === '' ? 'init' : $hook;
        $priority = is_numeric($values['priority']) ? (int) $values['priority'] : 10;

        $args = [];

        foreach ($this->csv($values['arguments']) as $arg) {
            $name = $this->varName($arg, '');

            if ($name !== '' && !in_array('$' . $name, $args, true)) {
                $args[] = '$' . $name;
            }
        }

        if ($isFilter && !$args) {
            $args[] = '$value';
        }

        $fallback = $isFilter ? "// Change the value here.\nreturn " . $args[0] . ';' : '// Your code here.';
        $code = $this->userCode($values['code'], $fallback);

        if ($isFilter && stripos($code, 'return') === false) {
            $code .= "\n\nreturn " . $args[0] . ';';
        }

        $call = ($isFilter ? 'add_filter' : 'add_action') . '( ' . $this->str($hook) . ', ' . $this->str($function);

        if ($priority !== 10 || count($args) > 1) {
            $call .= ', ' . $priority;
        }

        if (count($args) > 1) {
            $call .= ', ' . count($args);
        }

        return $this->guardedFunction($function, implode(', ', $args), $code) . $call . " );\n";
    }
}
