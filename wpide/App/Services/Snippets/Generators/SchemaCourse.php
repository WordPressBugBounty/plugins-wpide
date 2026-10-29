<?php

namespace WPIDE\App\Services\Snippets\Generators;

use WPIDE\App\Services\Snippets\Generators\Support\SchemaGenerator;

/**
 * Course structured data (with a course instance and offer).
 */
class SchemaCourse extends SchemaGenerator
{
    public function id(): string
    {
        return 'schema-course';
    }

    public function info(): array
    {
        return [
            'title' => __('Course Schema', 'wpide'),
            'description' => __('Present an online or in-person course with its provider, instructor, schedule mode and price.', 'wpide'),
            'category' => 'schema',
            'tags' => ['schema', 'course', 'education', 'lms', 'json-ld'],
        ];
    }

    public function fields(): array
    {
        return [
            'course' => [
                'label' => __('Course', 'wpide'),
                'fields' => [
                    $this->text('course_name', __('Course name', 'wpide'), '{title}', 'Introduction to Web Development'),
                    $this->textarea('course_description', __('Description', 'wpide'), '{excerpt}'),
                    $this->text('course_url', __('URL', 'wpide'), '{permalink}'),
                    $this->text('course_image', __('Image URL', 'wpide'), '{featured_image}'),
                    $this->text('course_code', __('Course code', 'wpide'), '', 'WEB101'),
                    $this->text('provider_name', __('Provider name', 'wpide'), '{site_name}'),
                    $this->text('provider_url', __('Provider URL', 'wpide'), '{site_url}'),
                ],
            ],
            'instance' => [
                'label' => __('Delivery', 'wpide'),
                'fields' => [
                    $this->select('course_mode', __('Course mode', 'wpide'), ['Online' => __('Online', 'wpide'), 'Onsite' => __('On site', 'wpide'), 'Blended' => __('Blended', 'wpide')], 'Online'),
                    $this->text('course_workload', __('Workload', 'wpide'), '', 'PT10H', __('Total time as an ISO 8601 duration, e.g. PT10H for ten hours.', 'wpide')),
                    $this->text('instructor_name', __('Instructor name', 'wpide'), '{author_name}'),
                    $this->text('instructor_url', __('Instructor URL', 'wpide'), ''),
                ],
            ],
            'offer' => [
                'label' => __('Offer', 'wpide'),
                'fields' => [
                    $this->select('course_type', __('Pricing', 'wpide'), ['paid' => __('Paid', 'wpide'), 'free' => __('Free', 'wpide')], 'free'),
                    $this->text('course_price', __('Price', 'wpide'), '', '99.00'),
                    $this->currencyField('course_currency'),
                    $this->availabilityField('availability'),
                    $this->date('valid_from', __('Available from', 'wpide')),
                    $this->date('valid_until', __('Available until', 'wpide')),
                ],
            ],
        ];
    }

    public function generate(array $values): string
    {
        $free = $values['course_type'] === 'free';

        return $this->jsonLd([
            '@type' => 'Course',
            'name' => $values['course_name'],
            'description' => $values['course_description'],
            'url' => $values['course_url'],
            'image' => $values['course_image'],
            'courseCode' => $values['course_code'],
            'provider' => $this->node('Organization', $values['provider_name'], ['sameAs' => $values['provider_url']]),
            'hasCourseInstance' => [
                '@type' => 'CourseInstance',
                'courseMode' => $values['course_mode'],
                'courseWorkload' => $values['course_workload'],
                'instructor' => $this->node('Person', $values['instructor_name'], ['url' => $values['instructor_url']]),
            ],
            'offers' => [
                '@type' => 'Offer',
                'category' => $free ? 'Free' : 'Paid',
                'price' => $free ? '0' : $this->price($values['course_price']),
                'priceCurrency' => $values['course_currency'],
                'availability' => $this->enum($values['availability']),
                'validFrom' => $values['valid_from'],
                'validThrough' => $values['valid_until'],
                'url' => $values['course_url'],
            ],
        ]);
    }
}
