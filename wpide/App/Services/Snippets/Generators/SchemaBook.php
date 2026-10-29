<?php

namespace WPIDE\App\Services\Snippets\Generators;

use WPIDE\App\Services\Snippets\Generators\Support\SchemaGenerator;

/**
 * Book structured data.
 */
class SchemaBook extends SchemaGenerator
{
    public function id(): string
    {
        return 'schema-book';
    }

    public function info(): array
    {
        return [
            'title' => __('Book Schema', 'wpide'),
            'description' => __('Mark up a book with its author, ISBN, edition, format and publisher.', 'wpide'),
            'category' => 'schema',
            'tags' => ['schema', 'book', 'isbn', 'json-ld'],
        ];
    }

    public function fields(): array
    {
        return [
            'book' => [
                'label' => __('Book', 'wpide'),
                'fields' => [
                    $this->text('book_title', __('Title', 'wpide'), '{title}', 'My Book Title'),
                    $this->textarea('book_description', __('Description', 'wpide'), '{excerpt}'),
                    $this->text('book_url', __('URL', 'wpide'), '{permalink}', 'https://example.com/book'),
                    $this->text('book_image', __('Cover image URL', 'wpide'), '{featured_image}', 'https://example.com/cover.jpg'),
                    $this->date('book_date_published', __('Publication date', 'wpide')),
                    $this->text('book_isbn', __('ISBN', 'wpide'), '', '978-3-16-148410-0'),
                    $this->text('book_edition', __('Edition', 'wpide'), '', '2nd edition'),
                    $this->text('book_number_of_pages', __('Number of pages', 'wpide'), '', '320'),
                    $this->select('book_format', __('Format', 'wpide'), [
                        '' => __('Not specified', 'wpide'),
                        'Hardcover' => __('Hardcover', 'wpide'),
                        'Paperback' => __('Paperback', 'wpide'),
                        'EBook' => __('E-book', 'wpide'),
                        'AudiobookFormat' => __('Audiobook', 'wpide'),
                        'GraphicNovel' => __('Graphic novel', 'wpide'),
                    ], ''),
                    $this->text('book_language', __('Language', 'wpide'), '', 'en', __('Language code, e.g. en or fr-CA.', 'wpide')),
                ],
            ],
            'people' => [
                'label' => __('Author & publisher', 'wpide'),
                'fields' => [
                    $this->text('author_name', __('Author name', 'wpide'), '', 'Jane Doe'),
                    $this->text('author_url', __('Author URL', 'wpide'), '', 'https://example.com/jane'),
                    $this->text('publisher_name', __('Publisher name', 'wpide'), '', 'Example Press'),
                    $this->text('publisher_logo', __('Publisher logo URL', 'wpide'), '', 'https://example.com/logo.png'),
                ],
            ],
        ];
    }

    public function generate(array $values): string
    {
        return $this->jsonLd([
            '@type' => 'Book',
            'name' => $values['book_title'],
            'description' => $values['book_description'],
            'url' => $values['book_url'],
            'image' => $values['book_image'],
            'datePublished' => $values['book_date_published'],
            'isbn' => $values['book_isbn'],
            'bookEdition' => $values['book_edition'],
            'numberOfPages' => $this->number($values['book_number_of_pages']),
            'bookFormat' => $this->enum($values['book_format']),
            'inLanguage' => $values['book_language'],
            'author' => $this->node('Person', $values['author_name'], ['url' => $values['author_url']]),
            'publisher' => $this->node('Organization', $values['publisher_name'], ['logo' => $this->imageObject($values['publisher_logo'])]),
        ]);
    }
}
