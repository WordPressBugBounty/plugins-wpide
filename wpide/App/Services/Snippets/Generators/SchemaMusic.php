<?php

namespace WPIDE\App\Services\Snippets\Generators;

use WPIDE\App\Services\Snippets\Generators\Support\SchemaGenerator;

/**
 * Music recording or album structured data.
 */
class SchemaMusic extends SchemaGenerator
{
    public function id(): string
    {
        return 'schema-music';
    }

    public function info(): array
    {
        return [
            'title' => __('Music Schema', 'wpide'),
            'description' => __('Describe a song or an album with its artist, release date, duration, genre and label.', 'wpide'),
            'category' => 'schema',
            'tags' => ['schema', 'music', 'song', 'album', 'json-ld'],
        ];
    }

    public function fields(): array
    {
        return [
            'music' => [
                'label' => __('Music', 'wpide'),
                'fields' => [
                    $this->select('music_type', __('Type', 'wpide'), ['MusicRecording' => __('Song / recording', 'wpide'), 'MusicAlbum' => __('Album', 'wpide')], 'MusicRecording'),
                    $this->text('music_title', __('Title', 'wpide'), '{title}', 'Midnight Drive'),
                    $this->textarea('description', __('Description', 'wpide'), '{excerpt}'),
                    $this->text('music_url', __('URL', 'wpide'), '{permalink}'),
                    $this->text('image_url', __('Cover image URL', 'wpide'), '{featured_image}'),
                    $this->date('release_date', __('Release date', 'wpide')),
                    $this->text('duration', __('Duration', 'wpide'), '', '215', __('Seconds (e.g. 215) or an ISO 8601 duration (e.g. PT3M35S).', 'wpide')),
                    $this->text('genre', __('Genres', 'wpide'), '', 'Rock, Pop', __('Comma separated.', 'wpide')),
                ],
            ],
            'credits' => [
                'label' => __('Credits', 'wpide'),
                'fields' => [
                    $this->text('artist', __('Artist', 'wpide'), '', 'The Example Band'),
                    $this->text('album', __('Album', 'wpide'), '', 'Night Sessions', __('Album the song belongs to (songs only).', 'wpide')),
                    $this->text('record_label', __('Record label', 'wpide'), '', 'Example Records'),
                ],
            ],
        ];
    }

    public function generate(array $values): string
    {
        $album = $values['music_type'] === 'MusicAlbum';
        $genres = $this->items($values['genre']);

        return $this->jsonLd([
            '@type' => $values['music_type'],
            'name' => $values['music_title'],
            'description' => $values['description'],
            'url' => $values['music_url'],
            'image' => $values['image_url'],
            'datePublished' => $values['release_date'],
            'duration' => $this->duration($values['duration'], 'S'),
            'genre' => count($genres) === 1 ? $genres[0] : $genres,
            'byArtist' => $this->node('MusicGroup', $values['artist']),
            'inAlbum' => $album ? [] : $this->node('MusicAlbum', $values['album']),
            'publisher' => $this->node('Organization', $values['record_label']),
        ]);
    }
}
