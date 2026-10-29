<?php

namespace WPIDE\App\Services\Snippets;

/**
 * Pinned snippets: each user's own short list, shown first in the snippets list and under a "Pinned" tab.
 */
class Pins
{
    const USER_META = 'wpide_snippets_pinned';

    /**
     * @return int[]
     */
    public static function all(int $userId = 0): array
    {
        $stored = get_user_meta($userId ?: get_current_user_id(), self::USER_META, true);

        return is_array($stored) ? array_values(array_filter(array_map('intval', $stored))) : [];
    }

    /**
     * @return int[]
     */
    public static function set(int $id, bool $pinned): array
    {
        $pins = array_values(array_diff(self::all(), [$id]));

        if ($pinned && Repository::find($id)) {
            array_unshift($pins, $id);
        }

        update_user_meta(get_current_user_id(), self::USER_META, array_slice($pins, 0, 200));

        return $pins;
    }
}
