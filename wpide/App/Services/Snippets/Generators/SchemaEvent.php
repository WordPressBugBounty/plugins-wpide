<?php

namespace WPIDE\App\Services\Snippets\Generators;

use WPIDE\App\Services\Snippets\Generators\Support\SchemaGenerator;

/**
 * Event structured data with venue / online location, performer, organizer and ticket offers.
 */
class SchemaEvent extends SchemaGenerator
{
    public function id(): string
    {
        return 'schema-event';
    }

    public function info(): array
    {
        return [
            'title' => __('Event Schema', 'wpide'),
            'description' => __('Promote a concert, conference or meetup with dates, venue or online link, performers and ticket types.', 'wpide'),
            'category' => 'schema',
            'tags' => ['schema', 'event', 'tickets', 'concert', 'json-ld'],
        ];
    }

    public function fields(): array
    {
        $availability = $this->availabilityField('offer_availability', 'https://schema.org/InStock');
        $availability['options'] = [];

        foreach (['InStock' => __('Available', 'wpide'), 'LimitedAvailability' => __('Few left', 'wpide'), 'PreOrder' => __('Pre-sale', 'wpide'), 'SoldOut' => __('Sold out', 'wpide'), 'OnlineOnly' => __('Online only', 'wpide')] as $key => $label) {
            $availability['options']['https://schema.org/' . $key] = $label;
        }

        return [
            'event' => [
                'label' => __('Event', 'wpide'),
                'fields' => [
                    $this->text('event_name', __('Event name', 'wpide'), '{title}', 'Summer Music Festival'),
                    $this->textarea('event_description', __('Description', 'wpide'), '{excerpt}'),
                    $this->text('event_image', __('Image URL', 'wpide'), '{featured_image}'),
                    $this->text('event_start_date', __('Start', 'wpide'), '', '2025-07-15T19:00:00+02:00', __('ISO 8601 date and time, ideally with the timezone offset.', 'wpide')),
                    $this->text('event_end_date', __('End', 'wpide'), '', '2025-07-15T23:00:00+02:00'),
                    $this->select('event_status', __('Status', 'wpide'), [
                        'https://schema.org/EventScheduled' => __('Scheduled', 'wpide'),
                        'https://schema.org/EventRescheduled' => __('Rescheduled', 'wpide'),
                        'https://schema.org/EventPostponed' => __('Postponed', 'wpide'),
                        'https://schema.org/EventMovedOnline' => __('Moved online', 'wpide'),
                        'https://schema.org/EventCancelled' => __('Cancelled', 'wpide'),
                    ], 'https://schema.org/EventScheduled'),
                    $this->select('event_attendance_mode', __('Attendance', 'wpide'), [
                        'https://schema.org/OfflineEventAttendanceMode' => __('In person', 'wpide'),
                        'https://schema.org/OnlineEventAttendanceMode' => __('Online', 'wpide'),
                        'https://schema.org/MixedEventAttendanceMode' => __('In person and online', 'wpide'),
                    ], 'https://schema.org/OfflineEventAttendanceMode'),
                ],
            ],
            'location' => [
                'label' => __('Location', 'wpide'),
                'fields' => [
                    $this->text('location_name', __('Venue name', 'wpide'), '', 'City Concert Hall'),
                    $this->text('location_street', __('Street address', 'wpide'), '', '123 Main Street'),
                    $this->text('location_city', __('City', 'wpide'), '', 'Springfield'),
                    $this->text('location_region', __('Region / state', 'wpide'), '', 'IL'),
                    $this->text('location_postal', __('Postal code', 'wpide'), '', '62701'),
                    $this->text('location_country', __('Country code', 'wpide'), '', 'US'),
                    $this->text('online_url', __('Online event URL', 'wpide'), '', 'https://example.com/live', __('Used for online and mixed attendance.', 'wpide')),
                ],
            ],
            'people' => [
                'label' => __('Performer & organizer', 'wpide'),
                'fields' => [
                    $this->select('performer_type', __('Performer type', 'wpide'), [
                        'PerformingGroup' => __('Performing group', 'wpide'),
                        'MusicGroup' => __('Band / music group', 'wpide'),
                        'DanceGroup' => __('Dance group', 'wpide'),
                        'TheaterGroup' => __('Theater group', 'wpide'),
                        'Person' => __('Person', 'wpide'),
                        'Organization' => __('Organization', 'wpide'),
                    ], 'PerformingGroup'),
                    $this->text('performer_name', __('Performer name', 'wpide'), ''),
                    $this->text('organizer_name', __('Organizer name', 'wpide'), '{site_name}'),
                    $this->text('organizer_url', __('Organizer URL', 'wpide'), '{site_url}'),
                ],
            ],
            'tickets' => [
                'label' => __('Tickets', 'wpide'),
                'fields' => [
                    [
                        'id' => 'offer_items',
                        'type' => 'repeater',
                        'label' => __('Ticket types', 'wpide'),
                        'default' => [],
                        'fields' => [
                            $this->text('offer_name', __('Ticket name', 'wpide'), '', 'General admission'),
                            $this->text('offer_price', __('Price', 'wpide'), '', '25.00'),
                            $this->currencyField('offer_currency'),
                            $this->date('offer_valid_from', __('On sale from', 'wpide')),
                            $this->text('offer_url', __('Ticket URL', 'wpide'), '', 'https://example.com/tickets'),
                            $availability,
                        ],
                    ],
                ],
            ],
        ];
    }

    public function generate(array $values): string
    {
        $mode = $values['event_attendance_mode'];
        $place = [];
        $virtual = [];

        if ($mode !== 'https://schema.org/OnlineEventAttendanceMode') {
            $address = $this->postalAddress([
                'street' => $values['location_street'],
                'city' => $values['location_city'],
                'region' => $values['location_region'],
                'postal' => $values['location_postal'],
                'country' => $values['location_country'],
            ]);

            if ($values['location_name'] !== '' || $address) {
                $place = ['@type' => 'Place', 'name' => $values['location_name'], 'address' => $address];
            }
        }

        if ($mode !== 'https://schema.org/OfflineEventAttendanceMode' && $values['online_url'] !== '') {
            $virtual = ['@type' => 'VirtualLocation', 'url' => $values['online_url']];
        }

        $offers = [];

        foreach ($values['offer_items'] as $row) {
            if ($row['offer_price'] === '' && $row['offer_url'] === '' && $row['offer_name'] === '') {
                continue;
            }

            $offers[] = [
                '@type' => 'Offer',
                'name' => $row['offer_name'],
                'price' => $this->price($row['offer_price']),
                'priceCurrency' => $row['offer_currency'],
                'validFrom' => $row['offer_valid_from'],
                'url' => $row['offer_url'],
                'availability' => $this->enum($row['offer_availability']),
            ];
        }

        return $this->jsonLd([
            '@type' => 'Event',
            'name' => $values['event_name'],
            'description' => $values['event_description'],
            'image' => $values['event_image'] !== '' ? [$values['event_image']] : [],
            'startDate' => $values['event_start_date'],
            'endDate' => $values['event_end_date'],
            'eventStatus' => $this->enum($values['event_status']),
            'eventAttendanceMode' => $this->enum($mode),
            'location' => $this->oneOrMany([$place, $virtual]),
            'performer' => $this->node($values['performer_type'], $values['performer_name']),
            'organizer' => $this->node('Organization', $values['organizer_name'], ['url' => $values['organizer_url']]),
            'offers' => $this->oneOrMany($offers),
        ]);
    }
}
