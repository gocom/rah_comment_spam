<?php

/*
 * rah_comment_spam - Anti-spam plugin for Textpattern CMS
 * https://github.com/gocom/rah_comment_spam
 *
 * Copyright (C) 2023 Jukka Svahn
 *
 * This file is part of rah_comment_spam.
 *
 * rah_comment_spam is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation, version 2.
 *
 * rah_comment_spam is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with rah_comment_spam. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Get needle count action.
 */
final class Rah_Comment_Spam_GetNeedleCountAction
{
    /**
     * Finds the number of matching needles from the haystack string.
     *
     * @param array|string $needle
     * @param string $string
     * @param int $count
     *
     * @return int
     */
    public function execute($needle, string $string, int $count = 0): int
    {
        if (!$needle || !$string) {
            return $count;
        }

        $mb = function_exists('mb_strtolower')
            && function_exists('mb_substr_count');

        $string = $mb
            ? mb_strtolower(' '.$string.' ', 'UTF-8')
            : strtolower(' '.$string.' ');

        if (!is_array($needle)) {
            $needle = $mb
                ? mb_strtolower($needle, 'UTF-8')
                : strtolower($needle);

            $needle = do_list($needle);
        }

        foreach ($needle as $find) {
            if (!empty($find)) {
                $count += $mb
                    ? mb_substr_count($string, $find, 'UTF-8')
                    : substr_count($string, $find);
            }
        }

        return $count;
    }
}
