<?php

/*
 * rah_comment_spam - Anti-spam plugin for Textpattern CMS
 * https://github.com/gocom/rah_comment_spam
 *
 * Copyright (C) 2019 Jukka Svahn
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
 * The plugin main class.
 *
 * @internal
 */
final class Rah_Comment_Spam
{
    /**
     * Options.
     */
    private const OPTIONS = [
        'rah_comment_spam_method' => ['rah_comment_spam_select_method', 'moderate'],
        'rah_comment_spam_message' => ['text_input', 'Your comment was marked as spam.'],
        'rah_comment_spam_spamwords' => ['rah_comment_spam_textarea', ''],
        'rah_comment_spam_maxspamwords' => ['text_input', 3],
        'rah_comment_spam_check' => ['text_input', 'name, email, web, message'],
        'rah_comment_spam_urlcount' => ['text_input', 6],
        'rah_comment_spam_minwords' => ['text_input', 1],
        'rah_comment_spam_maxwords' => ['text_input', 10000],
        'rah_comment_spam_minchars' => ['text_input', 1],
        'rah_comment_spam_maxchars' => ['text_input', 65535],
        'rah_comment_spam_field' => ['text_input', 'phone'],
        'rah_comment_spam_commentuse' => ['yesnoradio', 1],
        'rah_comment_spam_commentlimit' => ['text_input', 10],
        'rah_comment_spam_commentin' => ['rah_comment_spam_select_commentin', 'this'],
        'rah_comment_spam_commenttime' => ['text_input', 300],
        'rah_comment_spam_emaildns' => ['yesnoradio', 0],
        'rah_comment_spam_use_type_detect' => ['yesnoradio', 0],
        'rah_comment_spam_type_interval' => ['text_input', 5],
    ];

    /**
     * Constructor.
     */
    public function __construct()
    {
        add_privs('plugin_prefs.rah_comment_spam', '1,2');
        register_callback([$this, 'install'], 'plugin_lifecycle.rah_comment_spam', 'installed');
        register_callback([$this, 'uninstall'], 'plugin_lifecycle.rah_comment_spam', 'deleted');
        register_callback([$this, 'prefs'], 'plugin_prefs.rah_comment_spam');
        register_callback([$this, 'commentSave'], 'comment.save');
        register_callback([$this, 'commentForm'], 'comment.form');
    }

    /**
     * Installer.
     */
    public function install(): void
    {
        $position = 250;

        foreach (self::OPTIONS as $name => $value) {
            create_pref($name, $value[1], 'comments', PREF_PLUGIN, $value[0], $position++);
        }
    }

    /**
     * Uninstaller.
     */
    public function uninstall(): void
    {
        foreach (array_keys(self::OPTIONS) as $name) {
            remove_pref($name);
        }
    }

    /**
     * Adds fields to the comment form.
     *
     * @return string HTML
     */
    public function commentForm(): string
    {
        $out = [];

        if (get_pref('rah_comment_spam_use_type_detect')) {
            $nonce = ps('rah_comment_spam_nonce');
            $time = ps('rah_comment_spam_time');

            if (!$nonce && !$time) {
                $time = strtotime('now');
                $nonce = md5(get_pref('blog_uid') . $time);
            }

            $out[] =
                hInput('rah_comment_spam_nonce', $nonce).
                hInput('rah_comment_spam_time', $time);
        }

        if (get_pref('rah_comment_spam_field')) {
            $out[] =
                '<div style="display:none">'.
                    fInput(
                        'text',
                        txpspecialchars(get_pref('rah_comment_spam_field')),
                        ps(get_pref('rah_comment_spam_field'))
                    ).
                '</div>';
        }

        return implode(n, $out);
    }

    /**
     * Hooks to comment form callback events.
     */
    public function commentSave(): void
    {
        if (!$this->isValidComment()) {
            $evaluator =& get_comment_evaluator();

            switch (get_pref('rah_comment_spam_method')) {
                case 'block':
                    $evaluator->add_estimate(RELOAD, 1, gTxt(get_pref('rah_comment_spam_message')));
                    break;
                case 'moderate':
                    $evaluator->add_estimate(MODERATE, 0.75);
                    break;
                default:
                    $evaluator->add_estimate(SPAM, 0.75);
            }
        }
    }

    /**
     * Whether the comment is valid.
     *
     * @return bool
     */
    public function isValidComment(): bool
    {
        $comment = getComment();

        $form = new Rah_Comment_Spam_Form(
            (string) ($comment['name'] ?? ''),
            (string) ($comment['email'] ?? ''),
            (string) ($comment['web'] ?? ''),
            (string) ($comment['message'] ?? '')
        );

        $validators = new Rah_Comment_Spam_ValidatorPool();

        foreach ($validators->getValidators() as $validator) {
            if ($validator->validate($form) === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Redirects to preferences panel.
     *
     * @return void
     */
    public function prefs(): void
    {
        header('Location: ?event=prefs#prefs-rah_comment_spam_method');
        echo '<p><a href="?event=prefs#prefs-rah_comment_spam_method">'.gTxt('continue').'</a></p>';
    }
}
