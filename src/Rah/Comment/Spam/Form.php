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
 * Comment form data.
 */
final class Rah_Comment_Spam_Form implements Rah_Comment_Spam_FormInterface
{
    private ?string $name;
    private ?string $email;
    private ?string $web;
    private ?string $message;

    /**
     * Constructor.
     *
     * @param string|null $name
     * @param string|null $email
     * @param string|null $web
     * @param string|null $message
     */
    public function __construct(
        ?string $name,
        ?string $email,
        ?string $web,
        ?string $message
    ) {
        $this->name = $name;
        $this->email = $email;
        $this->web = $web;
        $this->message = $message;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * {@inheritdoc}
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * {@inheritdoc}
     */
    public function getWeb(): ?string
    {
        return $this->web;
    }

    /**
     * {@inheritdoc}
     */
    public function getMessage(): ?string
    {
        return $this->message;
    }
}
