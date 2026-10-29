<?php

namespace WPIDE\App\Services\Snippets\Generators;

use WPIDE\App\Services\Snippets\Generators\Support\SchemaGenerator;

/**
 * Article / BlogPosting / NewsArticle structured data.
 */
class SchemaArticle extends SchemaGenerator
{
    public function id(): string
    {
        return 'schema-article';
    }

    public function info(): array
    {
        return [
            'title' => __('Article Schema', 'wpide'),
            'description' => __('Describe a blog post or news story so search engines can show its headline, image, author and dates.', 'wpide'),
            'category' => 'schema',
            'tags' => ['schema', 'article', 'blog', 'news', 'json-ld'],
        ];
    }

    public function fields(): array
    {
        return [
            'article' => [
                'label' => __('Article', 'wpide'),
                'fields' => [
                    $this->select('article_type', __('Article type', 'wpide'), [
                        'Article' => __('Article', 'wpide'),
                        'BlogPosting' => __('Blog post', 'wpide'),
                        'NewsArticle' => __('News article', 'wpide'),
                        'TechArticle' => __('Technical article', 'wpide'),
                        'ScholarlyArticle' => __('Scholarly article', 'wpide'),
                        'Report' => __('Report', 'wpide'),
                    ], 'BlogPosting'),
                    $this->text('article_title', __('Headline', 'wpide'), '{title}', 'My Article Title'),
                    $this->textarea('article_description', __('Description', 'wpide'), '{excerpt}'),
                    $this->text('article_url', __('URL', 'wpide'), '{permalink}', 'https://example.com/article'),
                    $this->text('article_image', __('Image URL', 'wpide'), '{featured_image}', 'https://example.com/image.jpg'),
                    $this->date('article_published_date', __('Published date', 'wpide'), '{date_published}'),
                    $this->date('article_modified_date', __('Modified date', 'wpide'), '{date_modified}'),
                ],
            ],
            'author' => [
                'label' => __('Author & publisher', 'wpide'),
                'fields' => [
                    $this->select('author_type', __('Author type', 'wpide'), ['Person' => __('Person', 'wpide'), 'Organization' => __('Organization', 'wpide')], 'Person'),
                    $this->text('author_name', __('Author name', 'wpide'), '{author_name}', 'Jane Doe'),
                    $this->text('author_url', __('Author URL', 'wpide'), '{author_url}', 'https://example.com/author/jane'),
                    $this->text('publisher_name', __('Publisher name', 'wpide'), '{site_name}'),
                    $this->text('publisher_logo', __('Publisher logo URL', 'wpide'), '', 'https://example.com/logo.png'),
                ],
            ],
        ];
    }

    public function generate(array $values): string
    {
        $url = $values['article_url'];

        return $this->jsonLd([
            '@type' => $values['article_type'],
            'mainEntityOfPage' => $url !== '' ? ['@type' => 'WebPage', '@id' => $url] : [],
            'headline' => $values['article_title'],
            'description' => $values['article_description'],
            'url' => $url,
            'image' => $values['article_image'] !== '' ? [$values['article_image']] : [],
            'datePublished' => $values['article_published_date'],
            'dateModified' => $values['article_modified_date'],
            'author' => $this->node($values['author_type'], $values['author_name'], ['url' => $values['author_url']]),
            'publisher' => $this->node('Organization', $values['publisher_name'], ['logo' => $this->imageObject($values['publisher_logo'])]),
        ]);
    }
}
