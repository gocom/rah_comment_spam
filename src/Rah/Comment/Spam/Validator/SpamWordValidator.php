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
 * Checks comment for spam words.
 */
final class Rah_Comment_Spam_Validator_SpamWordValidator implements Rah_Comment_Spam_ValidatorInterface
{
    private Rah_Comment_Spam_GetNeedleCountAction $getNeedleCountAction;

    public function __construct(
        ?Rah_Comment_Spam_GetNeedleCountAction $getNeedleCountAction = null
    ) {
        $this->getNeedleCountAction = $getNeedleCountAction ?? new Rah_Comment_Spam_GetNeedleCountAction();
    }

    /**
     * {@inheritdoc}
     */
    public function validate(Rah_Comment_Spam_FormInterface $form): bool
    {
        $checkFields = do_list(get_pref('rah_comment_spam_check'));

        $haystack = [];

        if (in_array('name', $checkFields)) {
            $haystack[] = $form->getName();
        }

        if (in_array('email', $checkFields)) {
            $haystack[] = $form->getEmail();
        }

        if (in_array('web', $checkFields)) {
            $haystack[] = $form->getWeb();
        }

        if (in_array('message', $checkFields)) {
            $haystack[] = $form->getMessage();
        }

        $totalCount = $this->getNeedleCountAction->execute(
            get_pref('rah_comment_spam_spamwords'),
            implode(' ', $haystack)
        );

        return $totalCount <= (int) get_pref('rah_comment_spam_maxspamwords');
    }
}
