<?php

namespace WPIDE\App\Services\Snippets\Generators;

use WPIDE\App\Services\Snippets\Generators\Support\SchemaGenerator;

/**
 * Movie structured data.
 */
class SchemaMovie extends SchemaGenerator
{
    public function id(): string
    {
        return 'schema-movie';
    }

    public function info(): array
    {
        return [
            'title' => __('Movie Schema', 'wpide'),
            'description' => __('Describe a film with its poster, release date, runtime, director, cast and studio.', 'wpide'),
            'category' => 'schema',
            'tags' => ['schema', 'movie', 'film', 'json-ld'],
        ];
    }

    public function fields(): array
    {
        return [
            'movie' => [
                'label' => __('Movie', 'wpide'),
                'fields' => [
                    $this->text('movie_title', __('Title', 'wpide'), '{title}', 'The Great Adventure'),
                    $this->textarea('description', __('Description', 'wpide'), '{excerpt}'),
                    $this->text('movie_url', __('URL', 'wpide'), '{permalink}'),
                    $this->text('image_url', __('Poster image URL', 'wpide'), '{featured_image}'),
                    $this->date('release_date', __('Release date', 'wpide')),
                    $this->text('duration', __('Runtime', 'wpide'), '', '120', __('Minutes (e.g. 120) or an ISO 8601 duration (e.g. PT2H).', 'wpide')),
                    $this->text('genre', __('Genres', 'wpide'), '', 'Action, Adventure', __('Comma separated.', 'wpide')),
                    $this->text('rating', __('Content rating', 'wpide'), '', 'PG-13'),
                ],
            ],
            'credits' => [
                'label' => __('Credits', 'wpide'),
                'fields' => [
                    $this->text('director', __('Director', 'wpide'), '', 'Jane Doe', __('Comma separated for several directors.', 'wpide')),
                    $this->text('actors', __('Cast', 'wpide'), '', 'John Smith, Mary Major', __('Comma separated.', 'wpide')),
                    $this->text('production_company', __('Production company', 'wpide'), '', 'Example Studios'),
                ],
            ],
        ];
    }

    public function generate(array $values): string
    {
        $people = function ($text) {
            return $this->oneOrMany(array_map(function ($name) {
                return ['@type' => 'Person', 'name' => $name];
            }, $this->items($text)));
        };

        $genres = $this->items($values['genre']);

        return $this->jsonLd([
            '@type' => 'Movie',
            'name' => $values['movie_title'],
            'description' => $values['description'],
            'url' => $values['movie_url'],
            'image' => $values['image_url'],
            'dateCreated' => $values['release_date'],
            'duration' => $this->duration($values['duration']),
            'genre' => count($genres) === 1 ? $genres[0] : $genres,
            'contentRating' => $values['rating'],
            'director' => $people($values['director']),
            'actor' => $people($values['actors']),
            'productionCompany' => $this->node('Organization', $values['production_company']),
        ]);
    }
}
