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
 * Comment quota validator.
 */
final class Rah_Comment_Spam_Validator_CommentQuotaValidator implements Rah_Comment_Spam_ValidatorInterface
{
    /**
     * {@inheritdoc}
     */
    public function validate(Rah_Comment_Spam_FormInterface $form): bool
    {
        global $thisarticle;

        $inThisArticle = get_pref('rah_comment_spam_commentin') === 'this';
        $articleId = $thisarticle['thisid'] ?? null;
        $email = $form->getEmail();

        if (!get_pref('rah_comment_spam_commentuse') ||
            !get_pref('rah_comment_spam_commentlimit') ||
            ($inThisArticle && !$articleId) ||
            !$email
        ) {
            return true;
        }

        $period = get_pref('rah_comment_spam_commenttime');

        $totalCount = safe_count(
            'txp_discuss',
            "email = '" . doSlash($email) . "' ".
            "and UNIX_TIMESTAMP(posted) > (UNIX_TIMESTAMP(now())-". intval($period) .")".
            ($inThisArticle ? " and parentid = " . intval($articleId) : '')
        );

        return $totalCount < get_pref('rah_comment_spam_commentlimit');
    }
}
