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
 * The plugin main class.
 *
 * @internal
 */

class Rah_Comment_Spam
{
    /**
     * Stores the form.
     *
     * @var array
     */

    public $form = array();

    /**
     * Constructor.
     */

    public function __construct()
    {
        add_privs('plugin_prefs.rah_comment_spam', '1,2');
        register_callback(array($this, 'install'), 'plugin_lifecycle.rah_comment_spam', 'installed');
        register_callback(array($this, 'uninstall'), 'plugin_lifecycle.rah_backup', 'deleted');
        register_callback(array($this, 'prefs'), 'plugin_prefs.rah_comment_spam');
        register_callback(array($this, 'comment_save'), 'comment.save');
        register_callback(array($this, 'comment_form'), 'comment.form');
    }

    /**
     * Installer.
     */

    public function install()
    {
        $opt = array(
            'method'          => array('rah_comment_spam_select_method', 'moderate'),
            'message'         => array('text_input', 'Your comment was marked as spam.'),
            'spamwords'       => array('rah_comment_spam_textarea', ''),
            'maxspamwords'    => array('text_input', 3),
            'check'           => array('text_input', 'name, email, web, message'),
            'urlcount'        => array('text_input', 6),
            'minwords'        => array('text_input', 1),
            'maxwords'        => array('text_input', 10000),
            'minchars'        => array('text_input', 1),
            'maxchars'        => array('text_input', 65535),
            'field'           => array('text_input', 'phone'),
            'commentuse'      => array('yesnoradio', 1),
            'commentlimit'    => array('text_input', 10),
            'commentin'       => array('rah_comment_spam_select_commentin', 'this'),
            'commenttime'     => array('text_input', 300),
            'emaildns'        => array('yesnoradio', 0),
            'use_type_detect' => array('yesnoradio', 0),
            'type_interval'   => array('text_input', 5),
        );

        @$rs = safe_rows('name, value', 'rah_comment_spam', '1 = 1');

        if ($rs) {
            foreach ($rs as $a) {
                if (!isset($opt[$a['name']])) {
                    continue;
                }

                if (in_array($a['name'], array(
                    'use_type_detect',
                    'emaildns',
                    'commentuse',
                ))) {
                    $a['value'] = $a['value'] == 'no' ? 0 : 1;
                }

                $opt[$a['name']][1] = $a['value'];
            }

            @safe_query('DROP TABLE IF EXISTS '.safe_pfx('rah_comment_spam'));
        }

        $position = 250;

        foreach ($opt as $name => $val) {
            $n = 'rah_comment_spam_'.$name;

            if (get_pref($n, false) === false) {
                set_pref($n, $val[1], 'comments', PREF_BASIC, $val[0], $position);
            }

            $position++;
        }
    }

    /**
     * Uninstaller.
     */

    public function uninstall()
    {
        safe_delete(
            'txp_prefs',
            "name like 'rah\_comment\_spam\_%'"
        );
    }

    /**
     * Adds fields to the comment form.
     *
     * @return string HTML
     */

    public function comment_form()
    {
        $out = array();

        if (get_pref('rah_comment_spam_use_type_detect')) {
            $nonce = ps('rah_comment_spam_nonce');
            $time = ps('rah_comment_spam_time');

            if (!$nonce && !$time) {
                @$time = strtotime('now');
                $nonce = md5(get_pref('blog_uid').$time);
            }

            $out[] = 
                hInput('rah_comment_spam_nonce', $nonce).
                hInput('rah_comment_spam_time', $time);
        }

        if (get_pref('rah_comment_spam_field')) {
            $out[] = 
                '<div style="display:none">'.
                    fInput('text', txpspecialchars(get_pref('rah_comment_spam_field')), ps(get_pref('rah_comment_spam_field'))).
                '</div>';
        }

        return implode(n, $out);
    }

    /**
     * Hooks to comment form callback events.
     */

    public function comment_save()
    {
        $this->form = getComment();

        if (!$this->isSpam()) {
            return;
        }

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

    /**
     * Whether the comment is spam.
     *
     * This method validates the $this->form contents.
     *
     * @return bool
     */

    public function isSpam()
    {
        foreach ((array) get_class_methods($this) as $method) {
            if (strpos($method, 'isValid') === 0 && $this->$method() === false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validates the hidden spam-trap input.
     *
     * @return bool
     */

    protected function isValidTrap()
    {
        return !get_pref('rah_comment_spam_field') || !trim(ps(get_pref('rah_comment_spam_field')));
    }

    /**
     * Finds needles from haystack.
     *
     * @param  string|array $needle Either a comma-separated string of values or an array
     * @param  string       $string String to search
     * @param  int          $count  Starting value
     * @return int
     */

    protected function search($needle, $string, $count = 0)
    {
        if (!$needle || !$string) {
            return $count;
        }

        $mb = function_exists('mb_strtolower') && function_exists('mb_substr_count');
        $string = $mb ? mb_strtolower(' '.$string.' ', 'UTF-8') : strtolower(' '.$string.' ');

        if (!is_array($needle)) {
            $needle = $mb ? mb_strtolower($needle, 'UTF-8') : strtolower($needle);
            $needle = do_list($needle);
        }

        foreach ($needle as $find) {
            if (!empty($find)) {
                $count += $mb ? mb_substr_count($string, $find, 'UTF-8') : substr_count($string, $find);
            }
        }

        return $count;
    }

    /**
     * Counts characters.
     *
     * @return bool
     */

    protected function isValidCharCount()
    {
        $string = $this->form['message'];

        if (!$string) {
            return true;
        }

        $chars = function_exists('mb_strlen') ? mb_strlen($string, 'UTF-8') : strlen($string);
        $max = (int) get_pref('rah_comment_spam_maxchars');
        $min = (int) get_pref('rah_comment_spam_minchars');

        return (!$max || $max >= $chars) && $min <= $chars;
    }

    /**
     * Checks for blacklisted words.
     *
     * @return bool
     */

    protected function isValidSpamWords()
    {    
        $stack = array();

        foreach (do_list(get_pref('rah_comment_spam_check')) as $f) {
            if ($f && isset($this->form[$f])) {
                $stack[$f] = (string) $this->form[$f];
            }
        }

        return $this->search(
            get_pref('rah_comment_spam_spamwords'),
            implode(' ', $stack)
        ) <= get_pref('rah_comment_spam_maxspamwords');
    }

    /**
     * Chekcs link count.
     *
     * @return bool
     */

    protected function isValidLinkCount()
    {
        return $this->search(
            array('https://', 'http://', 'ftp://', 'ftps://'),
            $this->form['message']
        ) <= get_pref('rah_comment_spam_urlcount');
    }

    /**
     * Checks words in the message.
     *
     * @return bool
     */

    protected function isValidWordCount()
    {
        $string = trim($this->form['message']);

        if (!$string) {
            return true;
        }

        $words = count(preg_split('/[^\p{L}\p{N}\']+/u', $string));
        $max = (int) get_pref('rah_comment_spam_maxwords');
        $min = (int) get_pref('rah_comment_spam_minwords');

        return (!$max || $max >= $words) && $min <= $words;
    }

    /**
     * Limits users' comment posting activity.
     *
     * @return bool
     */

    protected function isValidCommentQuota()
    {
        global $thisarticle;

        if (
            !get_pref('rah_comment_spam_commentuse') || 
            !get_pref('rah_comment_spam_commentlimit') ||
            (get_pref('rah_comment_spam_commentin') == 'this' && !isset($thisarticle['thisid'])) ||
            (($ip = doSlash(remote_addr())) && !$ip)
        ) {
            return true;
        }

        $preriod = (int) get_pref('rah_comment_spam_commenttime');

        return safe_count(
            'txp_discuss',
            "ip='$ip' and UNIX_TIMESTAMP(posted) > (UNIX_TIMESTAMP(now())-$preriod)".
            (get_pref('rah_comment_spam_commentin') == 'this' ? " and parentid='".doSlash($thisarticle['thisid'])."'" : '')
        ) < get_pref('rah_comment_spam_commentlimit');
    }

    /**
     * Check typing speed, making sure the user interacted with the comment form.
     *
     * @return bool
     */

    protected function isValidTypingSpeed()
    {
        if (!get_pref('rah_comment_spam_use_type_detect')) {
            return true;
        }

        $type_interval = (int) get_pref('rah_comment_spam_type_interval');
        $time = (int) ps('rah_comment_spam_time');

        @$barrier = strtotime('now')-$type_interval;
        $md5 = md5(get_pref('blog_uid').$time);

        return $md5 === ps('rah_comment_spam_nonce') && $time <= $barrier;
    }

    /**
     * Checks DNS records for the email address.
     *
     * @return bool
     */

    protected function isValidEmailDns()
    {
        if (!get_pref('rah_comment_spam_emaildns') || !trim($this->form['email']) || !function_exists('checkdnsrr')) {
            return true;
        }

        $domain = trim(end(explode('@', $this->form['email'])));
        return !$domain || (!checkdnsrr($domain, 'MX') && !checkdnsrr($domain, 'A'));
    }

    /**
     * Redirects to preferences panel.
     */

    public function prefs()
    {
        header('Location: ?event=prefs#prefs-rah_comment_spam_method');
        echo '<p><a href="?event=prefs#prefs-rah_comment_spam_method">'.gTxt('continue').'</a></p>';
    }
}

new Rah_Comment_Spam();
