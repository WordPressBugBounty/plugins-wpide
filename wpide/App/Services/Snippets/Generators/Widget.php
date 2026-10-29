<?php

namespace WPIDE\App\Services\Snippets\Generators;

use WPIDE\App\Services\Snippets\Generators\Support\PhpCode;

/**
 * Generates a classic WP_Widget class with its settings form, sanitization and output.
 */
class Widget extends Generator
{
    use PhpCode;

    public function id(): string
    {
        return 'widget';
    }

    public function info(): array
    {
        return [
            'title' => __('Widget', 'wpide'),
            'description' => __('Build a classic widget with its own settings form (text, select, checkboxes...) and custom output code.', 'wpide'),
            'category' => 'wordpress',
            'tags' => ['widget', 'wp_widget', 'sidebar'],
        ];
    }

    public function fields(): array
    {
        return [
            'general' => [
                'label' => __('General', 'wpide'),
                'fields' => [
                    $this->text('class_name', __('Class name', 'wpide'), 'WPIDE_Custom_Widget_' . $this->uid(), __('Unique PHP class name for the widget.', 'wpide'), 'My_Custom_Widget'),
                    $this->text('prefix', __('Function prefix', 'wpide'), 'wpide_' . $this->uid() . '_', __('Prefix for the helper function that registers the widget.', 'wpide'), 'my_prefix_'),
                    $this->textDomainField(),
                    $this->text('widget_id', __('Widget ID (base)', 'wpide'), 'custom_widget', __('Lowercase identifier stored with each widget instance.', 'wpide'), 'custom_widget'),
                    $this->text('widget_title', __('Widget name', 'wpide'), 'Custom Widget', __('Name shown in the widgets list.', 'wpide')),
                    $this->text('description', __('Description', 'wpide'), 'A widget created with WPIDE.'),
                    $this->text('css_class', __('CSS class', 'wpide'), 'custom-widget', __('Class added to the widget wrapper.', 'wpide')),
                ],
            ],
            'fields' => [
                'label' => __('Settings', 'wpide'),
                'fields' => [
                    [
                        'id' => 'fields',
                        'type' => 'repeater',
                        'label' => __('Settings fields', 'wpide'),
                        'description' => __('A "Title" field is always included. Each field value is available in the output code as $instance[\'field_id\'].', 'wpide'),
                        'default' => [
                            ['field_type' => 'textarea', 'field_id' => 'text', 'field_label' => 'Content', 'field_description' => '', 'field_default' => '', 'field_options' => ''],
                        ],
                        'fields' => [
                            [
                                'id' => 'field_type',
                                'type' => 'select',
                                'label' => __('Type', 'wpide'),
                                'default' => 'text',
                                'options' => [
                                    'text' => __('Text', 'wpide'),
                                    'textarea' => __('Textarea', 'wpide'),
                                    'email' => __('Email', 'wpide'),
                                    'url' => __('URL', 'wpide'),
                                    'number' => __('Number', 'wpide'),
                                    'select' => __('Dropdown', 'wpide'),
                                    'radio' => __('Radio buttons', 'wpide'),
                                    'checkbox' => __('Checkbox(es)', 'wpide'),
                                ],
                            ],
                            $this->text('field_id', __('Field ID', 'wpide'), '', __('Lowercase key, e.g. subtitle.', 'wpide')),
                            $this->text('field_label', __('Label', 'wpide')),
                            $this->text('field_description', __('Help text', 'wpide')),
                            $this->text('field_default', __('Default value', 'wpide'), '', __('For checkboxes with options, a comma separated list of checked values.', 'wpide')),
                            ['id' => 'field_options', 'type' => 'textarea', 'label' => __('Options', 'wpide'), 'description' => __('Dropdown, radio and checkbox fields: one option per line, as "value : Label" or just "value". A checkbox without options is a single on/off box.', 'wpide'), 'default' => ''],
                        ],
                    ],
                ],
            ],
            'output' => [
                'label' => __('Output', 'wpide'),
                'fields' => [
                    ['id' => 'code', 'type' => 'textarea', 'label' => __('Output code', 'wpide'), 'description' => __('PHP printed between the widget wrapper and title and the closing wrapper. Settings are in $instance.', 'wpide'), 'default' => "if ( ! empty( \$instance['text'] ) ) {\n    echo wp_kses_post( wpautop( \$instance['text'] ) );\n}", 'placeholder' => "echo esc_html( \$instance['text'] );"],
                ],
            ],
        ];
    }

    /**
     * Normalized field definitions (title first).
     */
    protected function widgetFields(array $values): array
    {
        $fields = [];

        foreach ($values['fields'] as $row) {
            $id = $this->key($row['field_id'], '', 0, 'a-z0-9_');
            $id = $id !== '' && ctype_digit($id[0]) ? 'field_' . $id : $id;

            if ($id === '' || isset($fields[$id])) {
                continue;
            }

            $options = [];

            foreach (preg_split('/\r?\n/', (string) $row['field_options']) as $line) {
                if (trim($line) === '') {
                    continue;
                }

                $parts = array_map('trim', explode(':', $line, 2));
                $options[$parts[0]] = $parts[1] ?? $parts[0];
            }

            $fields[$id] = ['type' => $row['field_type'], 'label' => $row['field_label'] !== '' ? $row['field_label'] : ucfirst($id), 'description' => $row['field_description'], 'default' => $row['field_default'], 'options' => $options];
        }

        if (!isset($fields['title'])) {
            $fields = ['title' => ['type' => 'text', 'label' => 'Title', 'description' => '', 'default' => '', 'options' => []]] + $fields;
        }

        return $fields;
    }

    protected function isMulti(array $field): bool
    {
        return $field['type'] === 'checkbox' && $field['options'];
    }

    protected function hasChoices(array $field): bool
    {
        return in_array($field['type'], ['select', 'radio'], true) || $this->isMulti($field);
    }

    protected function defaultValue(array $field): string
    {
        if ($this->isMulti($field)) {
            return $this->strList(array_values(array_intersect($this->csv($field['default']), array_map('strval', array_keys($field['options'])))));
        }

        if ($field['type'] === 'checkbox') {
            return $this->on($field['default']) || $field['default'] === 'yes' ? '1' : '0';
        }

        if ($field['type'] === 'number') {
            return is_numeric($field['default']) ? (string) ($field['default'] + 0) : "''";
        }

        if ($this->hasChoices($field) && !isset($field['options'][$field['default']])) {
            return $field['options'] ? $this->str((string) array_keys($field['options'])[0]) : "''";
        }

        return $this->str($field['default']);
    }

    public function generate(array $values): string
    {
        $class = $this->fn($values['class_name'], 'WPIDE_Custom_Widget');
        $register = $this->fn($values['prefix'], 'wpide_') . 'register_widget';
        $domain = $values['text_domain'];
        $fields = $this->widgetFields($values);
        $base = $this->key($values['widget_id'], 'custom_widget', 0, 'a-z0-9_\-');

        $defaults = [];
        $choices = [];

        foreach ($fields as $id => $field) {
            $defaults[$id] = $this->defaultValue($field);

            if ($this->hasChoices($field)) {
                $options = [];

                // Rendered as "'value' => label" list items so numeric option values keep their keys.
                foreach ($field['options'] as $value => $label) {
                    $options[] = $this->str((string) $value) . ' => ' . $this->tr($label, $domain);
                }

                $choices[$id] = $options ?: 'array()';
            }
        }

        $widgetOptions = ['classname' => $this->str($values['css_class'] !== '' ? $values['css_class'] : $base)];

        if ($values['description'] !== '') {
            $widgetOptions['description'] = $this->tr($values['description'], $domain);
        }

        $methods = [];

        $methods[] = "public function __construct() {\n"
            . "    parent::__construct(\n"
            . '        ' . $this->str($base) . ",\n"
            . '        ' . $this->tr($values['widget_title'] !== '' ? $values['widget_title'] : $class, $domain) . ",\n"
            . '        ' . $this->phpArray($widgetOptions, 2) . "\n"
            . "    );\n"
            . '}';

        $methods[] = "/**\n * Default settings.\n */\nprotected function defaults() {\n    return " . $this->phpArray($defaults, 1) . ";\n}";

        if ($choices) {
            $methods[] = "/**\n * Allowed values of the choice fields.\n */\nprotected function choices( \$field ) {\n"
                . '    $choices = ' . $this->phpArray($choices, 1) . ";\n\n"
                . "    return isset( \$choices[ \$field ] ) ? \$choices[ \$field ] : array();\n}";
        }

        $methods[] = "/**\n * Frontend output.\n */\npublic function widget( \$args, \$instance ) {\n"
            . "    \$instance = wp_parse_args( (array) \$instance, \$this->defaults() );\n"
            . "    \$title    = apply_filters( 'widget_title', \$instance['title'], \$instance, \$this->id_base );\n\n"
            . "    echo \$args['before_widget'];\n\n"
            . "    if ( \$title ) {\n"
            . "        echo \$args['before_title'] . esc_html( \$title ) . \$args['after_title'];\n"
            . "    }\n\n"
            . $this->indent($this->userCode($values['code'], "// Output your widget content here, e.g. echo esc_html( \$instance['title'] );")) . "\n\n"
            . "    echo \$args['after_widget'];\n"
            . '}';

        $form = [];
        $update = [];

        foreach ($fields as $id => $field) {
            $form[] = $this->formField($id, $field, $domain);
            $update[] = $this->updateField($id, $field);
        }

        $methods[] = "/**\n * Settings form in the admin.\n */\npublic function form( \$instance ) {\n"
            . "    \$instance = wp_parse_args( (array) \$instance, \$this->defaults() );\n\n"
            . $this->indent(implode("\n\n", $form)) . "\n\n"
            . "    return '';\n"
            . '}';

        $methods[] = "/**\n * Sanitizes the submitted settings.\n */\npublic function update( \$new_instance, \$old_instance ) {\n"
            . "    \$instance = array();\n\n"
            . $this->indent(implode("\n", $update)) . "\n\n"
            . "    return \$instance;\n"
            . '}';

        $classCode = "class {$class} extends WP_Widget {\n\n" . $this->indent(implode("\n\n", $methods)) . "\n}";

        return "// Defines and registers a custom widget.\n"
            . "if ( ! class_exists( " . $this->str($class) . " ) ) {\n"
            . $this->indent($classCode) . "\n"
            . "}\n\n"
            . $this->guardedFunction($register, '', 'register_widget( ' . $this->str($class) . ' );')
            . "add_action( 'widgets_init', " . $this->str($register) . " );\n";
    }

    protected function formField(string $id, array $field, string $domain): string
    {
        $key = $this->str($id);
        $fieldId = "esc_attr( \$this->get_field_id( {$key} ) )";
        $fieldName = "esc_attr( \$this->get_field_name( {$key} ) )";
        $label = 'esc_html__( ' . $this->str($field['label']) . ', ' . $this->str($domain) . ' )';
        $help = $field['description'] !== '' ? "echo '<br><small class=\"description\">' . esc_html__( " . $this->str($field['description']) . ', ' . $this->str($domain) . " ) . '</small>';\n" : '';

        switch ($field['type']) {
            case 'textarea':
                return "echo '<p><label for=\"' . {$fieldId} . '\">' . {$label} . '</label>';\n"
                    . "echo '<textarea class=\"widefat\" rows=\"5\" id=\"' . {$fieldId} . '\" name=\"' . {$fieldName} . '\">' . esc_textarea( \$instance[{$key}] ) . '</textarea>';\n"
                    . $help
                    . "echo '</p>';";
            case 'select':
                return "echo '<p><label for=\"' . {$fieldId} . '\">' . {$label} . '</label>';\n"
                    . "echo '<select class=\"widefat\" id=\"' . {$fieldId} . '\" name=\"' . {$fieldName} . '\">';\n"
                    . "foreach ( \$this->choices( {$key} ) as \$value => \$text ) {\n"
                    . "    printf( '<option value=\"%s\"%s>%s</option>', esc_attr( \$value ), selected( (string) \$instance[{$key}], (string) \$value, false ), esc_html( \$text ) );\n"
                    . "}\n"
                    . "echo '</select>';\n"
                    . $help
                    . "echo '</p>';";
            case 'radio':
                return "echo '<p>' . {$label} . '<br>';\n"
                    . "foreach ( \$this->choices( {$key} ) as \$value => \$text ) {\n"
                    . "    printf( '<label><input type=\"radio\" name=\"%s\" value=\"%s\"%s> %s</label><br>', {$fieldName}, esc_attr( \$value ), checked( (string) \$instance[{$key}], (string) \$value, false ), esc_html( \$text ) );\n"
                    . "}\n"
                    . $help
                    . "echo '</p>';";
            case 'checkbox':
                if ($this->isMulti($field)) {
                    return "echo '<p>' . {$label} . '<br>';\n"
                        . "foreach ( \$this->choices( {$key} ) as \$value => \$text ) {\n"
                        . "    printf( '<label><input type=\"checkbox\" name=\"%s[]\" value=\"%s\"%s> %s</label><br>', {$fieldName}, esc_attr( \$value ), checked( in_array( (string) \$value, array_map( 'strval', (array) \$instance[{$key}] ), true ), true, false ), esc_html( \$text ) );\n"
                        . "}\n"
                        . $help
                        . "echo '</p>';";
                }

                return "echo '<p><input type=\"checkbox\" value=\"1\" id=\"' . {$fieldId} . '\" name=\"' . {$fieldName} . '\"' . checked( ! empty( \$instance[{$key}] ), true, false ) . '> ';\n"
                    . "echo '<label for=\"' . {$fieldId} . '\">' . {$label} . '</label>';\n"
                    . $help
                    . "echo '</p>';";
        }

        $type = in_array($field['type'], ['email', 'url', 'number'], true) ? $field['type'] : 'text';

        return "echo '<p><label for=\"' . {$fieldId} . '\">' . {$label} . '</label>';\n"
            . "echo '<input class=\"widefat\" type=\"{$type}\" id=\"' . {$fieldId} . '\" name=\"' . {$fieldName} . '\" value=\"' . esc_attr( \$instance[{$key}] ) . '\">';\n"
            . $help
            . "echo '</p>';";
    }

    protected function updateField(string $id, array $field): string
    {
        $key = $this->str($id);
        $value = "\$new_instance[{$key}]";
        $set = "\$instance[{$key}] = ";

        switch ($field['type']) {
            case 'textarea':
                return $set . "isset( {$value} ) ? wp_kses_post( {$value} ) : '';";
            case 'email':
                return $set . "isset( {$value} ) ? sanitize_email( {$value} ) : '';";
            case 'url':
                return $set . "isset( {$value} ) ? esc_url_raw( {$value} ) : '';";
            case 'number':
                return $set . "isset( {$value} ) && is_numeric( {$value} ) ? {$value} + 0 : '';";
            case 'select':
            case 'radio':
                return $set . "isset( {$value} ) && array_key_exists( {$value}, \$this->choices( {$key} ) ) ? sanitize_text_field( {$value} ) : '';";
            case 'checkbox':
                if ($this->isMulti($field)) {
                    return $set . "isset( {$value} ) ? array_values( array_intersect( array_map( 'strval', (array) {$value} ), array_map( 'strval', array_keys( \$this->choices( {$key} ) ) ) ) ) : array();";
                }

                return $set . "empty( {$value} ) ? 0 : 1;";
        }

        return $set . "isset( {$value} ) ? sanitize_text_field( {$value} ) : '';";
    }
}
