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
 * Word count validator.
 */
final class Rah_Comment_Spam_Validator_WordCountValidator implements Rah_Comment_Spam_ValidatorInterface
{
    /**
     * {@inheritdoc}
     */
    public function validate(Rah_Comment_Spam_FormInterface $form): bool
    {
        $string = trim((string) $form->getMessage());

        $words = $string === ''
            ? 0
            : count(preg_split('/[^\p{L}\p{N}\']+/u', $string));

        $max = (int) get_pref('rah_comment_spam_maxwords');
        $min = (int) get_pref('rah_comment_spam_minwords');

        return (!$max || $max >= $words) && $min <= $words;
    }
}
