<?php
namespace WPIDE\App\Controllers;

use WPIDE\App\Classes\Parsedown;
use WPIDE\App\Kernel\Response;
use const WPIDE\Constants\DIR;

class ChangelogController {

    public function get(Response $response) {

        $parsedown = new Parsedown;

        $changelog = '';

        $data = file_get_contents( DIR.'readme.txt' );

        if ( ! empty( $data ) ) {
            $data = explode( '== Changelog ==', $data );
            if ( ! empty( $data[1] ) ) {

                $changelog = $data[1];
                // The directory readme keeps a short changelog; the archive holds
                // the full history shown inside WPIDE. Add readme releases that
                // are not archived yet, without duplicating older versions.
                $archive_path = DIR.'changelog.txt';
                if ( is_readable( $archive_path ) ) {
                    $archive = file_get_contents( $archive_path );
                    $heading = '/^####\s+V\.([^\s]+)/m';
                    if ( ! empty( $archive ) && preg_match_all( $heading, $archive, $archived, PREG_OFFSET_CAPTURE ) && preg_match_all( $heading, $changelog, $entries, PREG_OFFSET_CAPTURE ) ) {
                        $versions = array_column( $archived[1], 0 );
                        $new_entries = array();
                        foreach ( $entries[0] as $index => $entry ) {
                            if ( in_array( $entries[1][$index][0], $versions, true ) ) {
                                continue;
                            }
                            $end = isset( $entries[0][$index + 1] ) ? $entries[0][$index + 1][1] : strlen( $changelog );
                            $new_entries[] = trim( substr( $changelog, $entry[1], $end - $entry[1] ) );
                        }
                        $new_entries[] = substr( $archive, $archived[0][0][1] );
                        $changelog = implode( "\n\n", $new_entries );
                    }
                }
                $changelog = preg_replace(
                    array(
                        '/\[\/\/\]\: \# fs_.+?_only_begin/',
                        '/\[\/\/\]\: \# fs_.+?_only_end/',
                    ),
                    '',
                    $changelog
                );

                $changelog = $parsedown->text( $changelog );

                $changelog = preg_replace(
                    array(
                        '/\<strong\>(.+?)\<\/strong>\:(.+?)\n/i',
                        '/\<p\>/',
                        '/\<\/p\>/',
                        '/\<a/',
                    ),
                    array(
                        '<span class="update-type $1">$1</span><span class="update-txt">$2</span>',
                        '',
                        '',
                        '<a target="_blank"',
                    ),
                    $changelog
                );

            }
        }

        return $response->html($changelog);

    }
}
