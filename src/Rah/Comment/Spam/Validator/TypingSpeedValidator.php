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
 * Typing speed validator.
 */
final class Rah_Comment_Spam_Validator_TypingSpeedValidator implements Rah_Comment_Spam_ValidatorInterface
{
    /**
     * {@inheritdoc}
     */
    public function validate(Rah_Comment_Spam_FormInterface $form): bool
    {
        if (!get_pref('rah_comment_spam_use_type_detect')) {
            return true;
        }

        $interval = (int) get_pref('rah_comment_spam_type_interval');
        $time = (int) ps('rah_comment_spam_time');

        $barrier = (int) strtotime('now') - $interval;
        $md5 = md5(get_pref('blog_uid') . $time);

        return $md5 === ps('rah_comment_spam_nonce') && $time <= $barrier;
    }
}
