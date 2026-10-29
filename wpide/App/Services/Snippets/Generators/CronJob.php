<?php

namespace WPIDE\App\Services\Snippets\Generators;

use WPIDE\App\Services\Snippets\Generators\Support\PhpCode;

/**
 * Schedules a recurring WP-Cron event, optionally on a custom interval.
 */
class CronJob extends Generator
{
    use PhpCode;

    public function id(): string
    {
        return 'cronjob';
    }

    public function info(): array
    {
        return [
            'title' => __('Schedule a Cron Job', 'wpide'),
            'description' => __('Run PHP code on a recurring schedule with WP-Cron, using a built-in interval or one you define.', 'wpide'),
            'category' => 'wordpress',
            'tags' => ['cron', 'schedule', 'recurring', 'wp-cron'],
        ];
    }

    public function fields(): array
    {
        return [
            'general' => [
                'label' => __('Schedule', 'wpide'),
                'fields' => [
                    $this->functionNameField('wpide_cron'),
                    $this->textDomainField(),
                    [
                        'id' => 'recurrence',
                        'type' => 'select',
                        'label' => __('Recurrence', 'wpide'),
                        'default' => 'hourly',
                        'options' => [
                            'hourly' => __('Every hour', 'wpide'),
                            'twicedaily' => __('Twice a day', 'wpide'),
                            'daily' => __('Once a day', 'wpide'),
                            'weekly' => __('Once a week', 'wpide'),
                            'custom' => __('Custom interval', 'wpide'),
                        ],
                    ],
                    $this->text('recurrence_name', __('Custom interval key', 'wpide'), 'every_two_weeks', __('Internal name of the custom interval.', 'wpide'), 'every_two_weeks'),
                    $this->text('recurrence_label', __('Custom interval label', 'wpide'), 'Every two weeks', __('Readable name shown by cron tools.', 'wpide')),
                    ['id' => 'recurrence_interval', 'type' => 'number', 'label' => __('Custom interval (seconds)', 'wpide'), 'description' => __('e.g. 3600 = one hour, 86400 = one day, 1209600 = two weeks.', 'wpide'), 'default' => 1209600],
                    $this->text('hook_name', __('Event hook name', 'wpide'), 'wpide_cron_event_' . $this->uid(), __('Unique action fired each time the event runs.', 'wpide')),
                ],
            ],
            'code' => [
                'label' => __('Code', 'wpide'),
                'fields' => [
                    ['id' => 'code', 'type' => 'textarea', 'label' => __('PHP code', 'wpide'), 'description' => __('Runs on every occurrence, without the opening PHP tag.', 'wpide'), 'default' => '', 'placeholder' => '// Your scheduled task'],
                ],
            ],
        ];
    }

    public function generate(array $values): string
    {
        $function = $this->fn($values['function_name'], 'wpide_cron');
        $hook = $this->key($values['hook_name'], $function . '_event', 0, 'a-z0-9_');
        $recurrence = $values['recurrence'];
        $code = "// Event: {$hook}\n";

        if ($recurrence === 'custom') {
            $recurrence = $this->key($values['recurrence_name'], 'every_two_weeks', 0, 'a-z0-9_');
            $interval = max(60, (int) $values['recurrence_interval']);
            $label = $values['recurrence_label'] !== '' ? $values['recurrence_label'] : $recurrence;

            $code .= "\n// Adds the custom interval.\n"
                . $this->guardedFunction($function . '_interval', '$schedules', '$schedules[' . $this->str($recurrence) . '] = ' . $this->phpArray([
                    'interval' => (string) $interval,
                    'display' => $this->tr($label, $values['text_domain']),
                ]) . ";\n\nreturn \$schedules;")
                . "add_filter( 'cron_schedules', " . $this->str($function . '_interval') . " );\n";
        }

        $schedule = '$event = wp_get_scheduled_event( ' . $this->str($hook) . " );\n\n"
            . "// Reschedule when the recurrence was changed.\n"
            . 'if ( $event && $event->schedule !== ' . $this->str($recurrence) . " ) {\n"
            . '    wp_clear_scheduled_hook( ' . $this->str($hook) . " );\n"
            . "    \$event = false;\n"
            . "}\n\n"
            . "if ( ! \$event ) {\n"
            . '    wp_schedule_event( time(), ' . $this->str($recurrence) . ', ' . $this->str($hook) . " );\n"
            . '}';

        $code .= "\n// Makes sure the event is scheduled.\n"
            . $this->guardedFunction($function . '_schedule', '', $schedule)
            . "add_action( 'init', " . $this->str($function . '_schedule') . " );\n"
            . "\n// Runs on every occurrence.\n"
            . $this->guardedFunction($function, '', $this->userCode($values['code'], "// Your scheduled task here, e.g.:\n// error_log( 'Cron event ran at ' . current_time( 'mysql' ) );"))
            . 'add_action( ' . $this->str($hook) . ', ' . $this->str($function) . " );\n";

        return $code;
    }
}
