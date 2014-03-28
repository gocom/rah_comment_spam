<?php

/*
 * rah_comment_spam - Anti-spam plugin for Textpattern CMS
 * https://github.com/gocom/rah_comment_spam
 *
 * Copyright (C) 2014 Jukka Svahn
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
 * Collection of preferences fields.
 *
 * These functions renders inputs on the
 * preferences panel.
 */

/**
 * Spam protection method option.
 *
 * @param  string $name Field name
 * @param  string $val  Current value
 * @return string HTML select field
 */

function rah_comment_spam_select_method($name, $val)
{
    foreach (array('block', 'moderate', 'spam') as $opt) {
        $out[$opt] = gTxt('rah_comment_spam_method_'.$opt);
    }

    return selectInput($name, $out, $val, '', '', $name);
}

/**
 * Comment count range option.
 *
 * @param  string $name Field name
 * @param  string $val  Current value
 * @return string HTML select field
 */

function rah_comment_spam_select_commentin($name, $val)
{
    foreach (array('all', 'this') as $opt) {
        $out[$opt] = gTxt('rah_comment_spam_commentin_'.$opt);
    }

    return selectInput($name, $out, $val, '', '', $name);
}

/**
 * Textarea for preferences panel.
 *
 * @param  string $name Field name
 * @param  string $val  Current value
 * @return string HTML textarea
 */

function rah_comment_spam_textarea($name, $val)
{
    return text_area($name, 100, 300, $val, $name);
}
