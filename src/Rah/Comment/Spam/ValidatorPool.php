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
final class Rah_Comment_Spam_ValidatorPool
{
    /**
     * Gets validators.
     *
     * @return Rah_Comment_Spam_ValidatorInterface[]
     */
    public function getValidators(): array
    {
        return [
            new Rah_Comment_Spam_Validator_CharacterCountValidator(),
            new Rah_Comment_Spam_Validator_CommentQuotaValidator(),
            new Rah_Comment_Spam_Validator_EmailDnsValidator(),
            new Rah_Comment_Spam_Validator_LinkCountValidator(),
            new Rah_Comment_Spam_Validator_SpamTrapValidator(),
            new Rah_Comment_Spam_Validator_SpamWordValidator(),
            new Rah_Comment_Spam_Validator_TypingSpeedValidator(),
            new Rah_Comment_Spam_Validator_WordCountValidator(),
        ];
    }
}
