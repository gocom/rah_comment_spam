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
 * Checks that email address domain has DNS records.
 */
final class Rah_Comment_Spam_Validator_EmailDnsValidator implements Rah_Comment_Spam_ValidatorInterface
{
    /**
     * {@inheritdoc}
     */
    public function validate(Rah_Comment_Spam_FormInterface $form): bool
    {
        $email = $form->getEmail();

        if (!get_pref('rah_comment_spam_emaildns')
            || !function_exists('checkdnsrr')
        ) {
            return true;
        }

        $parts = explode('@', $email);
        $domain = trim(end($parts));

        return $domain && (checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A'));
    }
}
