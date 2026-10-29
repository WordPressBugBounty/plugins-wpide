<?php

namespace WPIDE\App\Services\Snippets\Generators;

use WPIDE\App\Services\Snippets\Generators\Support\SchemaGenerator;

/**
 * JobPosting structured data with one or more work locations, remote options and salary.
 */
class SchemaJobPosting extends SchemaGenerator
{
    public function id(): string
    {
        return 'schema-job-posting';
    }

    public function info(): array
    {
        return [
            'title' => __('Job Posting Schema', 'wpide'),
            'description' => __('Make a job opening eligible for job search features: hiring company, location or remote work, salary and dates.', 'wpide'),
            'category' => 'schema',
            'tags' => ['schema', 'job', 'career', 'hiring', 'json-ld'],
        ];
    }

    public function fields(): array
    {
        return [
            'job' => [
                'label' => __('Job', 'wpide'),
                'fields' => [
                    $this->text('job_title', __('Job title', 'wpide'), '{title}', 'Senior PHP Developer'),
                    $this->textarea('job_description', __('Description', 'wpide'), '{excerpt}', '', __('Full description of the role. Basic HTML is allowed.', 'wpide')),
                    $this->text('job_industry', __('Industry', 'wpide'), '', 'Information Technology'),
                    $this->select('job_employment_type', __('Employment type', 'wpide'), [
                        'FULL_TIME' => __('Full time', 'wpide'),
                        'PART_TIME' => __('Part time', 'wpide'),
                        'CONTRACTOR' => __('Contractor', 'wpide'),
                        'TEMPORARY' => __('Temporary', 'wpide'),
                        'INTERN' => __('Internship', 'wpide'),
                        'VOLUNTEER' => __('Volunteer', 'wpide'),
                        'PER_DIEM' => __('Per diem', 'wpide'),
                        'OTHER' => __('Other', 'wpide'),
                    ], 'FULL_TIME'),
                    $this->text('job_work_hours', __('Work hours', 'wpide'), '', '40 hours per week'),
                    $this->date('job_date_posted', __('Date posted', 'wpide'), '{date_published}'),
                    $this->date('job_valid_through', __('Apply before', 'wpide'), '', __('Leave empty if the posting does not expire.', 'wpide')),
                ],
            ],
            'organization' => [
                'label' => __('Hiring organization', 'wpide'),
                'fields' => [
                    $this->text('org_name', __('Organization name', 'wpide'), '{site_name}'),
                    $this->text('org_website', __('Website', 'wpide'), '{site_url}'),
                    $this->text('org_logo', __('Logo URL', 'wpide'), '', 'https://example.com/logo.png'),
                ],
            ],
            'location' => [
                'label' => __('Location', 'wpide'),
                'fields' => [
                    $this->select('job_location_type', __('Workplace', 'wpide'), [
                        'ONSITE' => __('On site', 'wpide'),
                        'TELECOMMUTE' => __('Fully remote', 'wpide'),
                        'HYBRID' => __('Hybrid (on site and remote)', 'wpide'),
                    ], 'ONSITE'),
                    $this->text('remote_countries', __('Remote: allowed countries', 'wpide'), '', 'US, CA', __('Comma separated countries where remote applicants may live.', 'wpide')),
                    [
                        'id' => 'job_locations',
                        'type' => 'repeater',
                        'label' => __('Work locations', 'wpide'),
                        'description' => __('Physical offices for on-site and hybrid jobs.', 'wpide'),
                        'default' => [
                            ['location_street' => '', 'location_city' => 'New York', 'location_region' => 'NY', 'location_postal' => '', 'location_country' => 'US'],
                        ],
                        'fields' => [
                            $this->text('location_street', __('Street address', 'wpide'), '', '123 Main St'),
                            $this->text('location_city', __('City', 'wpide'), '', 'New York'),
                            $this->text('location_region', __('Region / state', 'wpide'), '', 'NY'),
                            $this->text('location_postal', __('Postal code', 'wpide'), '', '10001'),
                            $this->text('location_country', __('Country code', 'wpide'), '', 'US'),
                        ],
                    ],
                ],
            ],
            'salary' => [
                'label' => __('Salary', 'wpide'),
                'fields' => [
                    $this->text('salary_base', __('Salary (or minimum)', 'wpide'), '', '60000'),
                    $this->text('salary_max', __('Maximum salary', 'wpide'), '', '80000', __('Optional, for a salary range.', 'wpide')),
                    $this->currencyField('salary_currency'),
                    $this->select('salary_unit', __('Per', 'wpide'), ['HOUR' => __('Hour', 'wpide'), 'DAY' => __('Day', 'wpide'), 'WEEK' => __('Week', 'wpide'), 'MONTH' => __('Month', 'wpide'), 'YEAR' => __('Year', 'wpide')], 'YEAR'),
                ],
            ],
        ];
    }

    public function generate(array $values): string
    {
        $type = $values['job_location_type'];
        $locations = [];

        if ($type !== 'TELECOMMUTE') {
            foreach ($values['job_locations'] as $row) {
                $address = $this->postalAddress([
                    'street' => $row['location_street'],
                    'city' => $row['location_city'],
                    'region' => $row['location_region'],
                    'postal' => $row['location_postal'],
                    'country' => $row['location_country'],
                ]);

                if ($address) {
                    $locations[] = ['@type' => 'Place', 'address' => $address];
                }
            }
        }

        $salary = [];

        if ($values['salary_base'] !== '') {
            $amount = ['@type' => 'QuantitativeValue', 'unitText' => $values['salary_unit']];

            if ($values['salary_max'] !== '') {
                $amount += ['minValue' => $this->number($this->price($values['salary_base'])), 'maxValue' => $this->number($this->price($values['salary_max']))];
            } else {
                $amount += ['value' => $this->number($this->price($values['salary_base']))];
            }

            $salary = ['@type' => 'MonetaryAmount', 'currency' => $values['salary_currency'], 'value' => $amount];
        }

        $remote = $type !== 'ONSITE';

        return $this->jsonLd([
            '@type' => 'JobPosting',
            'title' => $values['job_title'],
            'description' => $values['job_description'],
            'industry' => $values['job_industry'],
            'employmentType' => $values['job_employment_type'],
            'workHours' => $values['job_work_hours'],
            'datePosted' => $values['job_date_posted'],
            'validThrough' => $values['job_valid_through'],
            'hiringOrganization' => $this->node('Organization', $values['org_name'], ['sameAs' => $values['org_website'], 'logo' => $values['org_logo']]),
            'jobLocation' => $this->oneOrMany($locations),
            'jobLocationType' => $remote ? 'TELECOMMUTE' : '',
            'applicantLocationRequirements' => $remote ? $this->oneOrMany(array_map(function ($country) {
                return ['@type' => 'Country', 'name' => $country];
            }, $this->items($values['remote_countries']))) : [],
            'baseSalary' => $salary,
        ]);
    }
}
