<?php

namespace WPIDE\App\Services\Snippets\Generators;

use WPIDE\App\Services\Snippets\Generators\Support\SchemaGenerator;

/**
 * VideoObject structured data.
 */
class SchemaVideo extends SchemaGenerator
{
    public function id(): string
    {
        return 'schema-video';
    }

    public function info(): array
    {
        return [
            'title' => __('Video Schema', 'wpide'),
            'description' => __('Help search engines index a video with its thumbnail, duration, upload date and player URL.', 'wpide'),
            'category' => 'schema',
            'tags' => ['schema', 'video', 'youtube', 'json-ld'],
        ];
    }

    public function fields(): array
    {
        return [
            'video' => [
                'label' => __('Video', 'wpide'),
                'fields' => [
                    $this->text('video_title', __('Title', 'wpide'), '{title}'),
                    $this->textarea('video_description', __('Description', 'wpide'), '{excerpt}'),
                    $this->text('video_thumbnail', __('Thumbnail URL', 'wpide'), '{featured_image}'),
                    $this->date('upload_date', __('Upload date', 'wpide'), '{date_published}'),
                    $this->text('video_duration', __('Duration', 'wpide'), '', 'PT4M30S', __('Seconds (e.g. 270) or an ISO 8601 duration.', 'wpide')),
                    $this->text('video_url', __('Video file URL', 'wpide'), '', 'https://example.com/video.mp4', __('Direct link to the media file (contentUrl).', 'wpide')),
                    $this->text('video_embed_url', __('Embed / player URL', 'wpide'), '', 'https://www.youtube.com/embed/VIDEO_ID'),
                ],
            ],
            'creator' => [
                'label' => __('Creator', 'wpide'),
                'fields' => [
                    $this->text('creator_name', __('Creator name', 'wpide'), '{author_name}'),
                    $this->text('creator_url', __('Creator URL', 'wpide'), '{author_url}'),
                ],
            ],
        ];
    }

    public function generate(array $values): string
    {
        return $this->jsonLd([
            '@type' => 'VideoObject',
            'name' => $values['video_title'],
            'description' => $values['video_description'],
            'thumbnailUrl' => $values['video_thumbnail'] !== '' ? [$values['video_thumbnail']] : [],
            'uploadDate' => $values['upload_date'],
            'duration' => $this->duration($values['video_duration'], 'S'),
            'contentUrl' => $values['video_url'],
            'embedUrl' => $values['video_embed_url'],
            'creator' => $this->node('Person', $values['creator_name'], ['url' => $values['creator_url']]),
        ]);
    }
}
