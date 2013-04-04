<?php

/**
 * Rah_comment_spam plugin for Textpattern CMS
 *
 * @author  Jukka Svahn
 * @date    2008-
 * @license GNU GPLv2
 * @link    http://rahforum.biz/plugins/rah_comment_spam
 *
 * Copyright (C) 2013 Jukka Svahn http://rahforum.biz
 * Licensed under GNU Genral Public License version 2
 * http://www.gnu.org/licenses/gpl-2.0.html
 */

class rah_comment_spam
{
	/**
	 * Version number.
	 *
	 * @var string
	 */

	static public $version = '0.7';

	/**
	 * Stores the form.
	 *
	 * @var array
	 */

	public $form = array();

	/**
	 * Installer.
	 *
	 * @param string $event Admin-side event
	 * @param string $step  Admin-side, plugin-lifecycle step
	 */

	public function install($event = '', $step = '')
	{
		if ($step == 'deleted')
		{
			safe_delete(
				'txp_prefs',
				"name like 'rah\_comment\_spam\_%'"
			);
			return;
		}

		if ((string) get_pref('rah_comment_spam_version') === self::$version)
		{
			return;
		}

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

		if ($rs)
		{
			foreach ($rs as $a)
			{
				if (!isset($opt[$a['name']]))
				{
					continue;
				}

				if (in_array($a['name'], array(
					'use_type_detect',
					'emaildns',
					'commentuse',
				)))
				{
					$a['value'] = $a['value'] == 'no' ? 0 : 1;
				}
	
				$opt[$a['name']][1] = $a['value'];
			}

			@safe_query('DROP TABLE IF EXISTS '.safe_pfx('rah_comment_spam'));
		}

		$position = 250;

		foreach ($opt as $name => $val)
		{
			$n = 'rah_comment_spam_'.$name;

			if (get_pref($n, false) === false)
			{
				set_pref($n, $val[1], 'comments', PREF_BASIC, $val[0], $position);
			}

			$position++;
		}

		set_pref('rah_comment_spam_version', self::$version, 'rah_cspam', 2, '', 0);
	}

	/**
	 * Constructor.
	 */

	public function __construct()
	{
		add_privs('plugin_prefs.rah_comment_spam', '1,2');
		register_callback(array($this, 'install'), 'plugin_lifecycle.rah_comment_spam');
		register_callback(array($this, 'prefs'), 'plugin_prefs.rah_comment_spam');
		register_callback(array($this, 'comment_save'), 'comment.save');
		register_callback(array($this, 'comment_form'), 'comment.form');
	}

	/**
	 * Adds fields to the comment form.
	 *
	 * @return string HTML
	 */

	public function comment_form()
	{
		$out = array();

		if (get_pref('rah_comment_spam_use_type_detect'))
		{
			$nonce = ps('rah_comment_spam_nonce');
			$time = ps('rah_comment_spam_time');

			if (!$nonce && !$time)
			{
				@$time = strtotime('now');
				$nonce = md5(get_pref('blog_uid').$time);
			}

			$out[] = 
				hInput('rah_comment_spam_nonce', $nonce).
				hInput('rah_comment_spam_time', $time);
		}

		if (get_pref('rah_comment_spam_field'))
		{
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

		if (!$this->is_spam())
		{
			return;
		}

		$evaluator =& get_comment_evaluator();

		switch (get_pref('rah_comment_spam_method'))
		{
			case 'block' :
				$evaluator->add_estimate(RELOAD, 1, gTxt(get_pref('rah_comment_spam_message')));
				break;
			case 'moderate' :
				$evaluator->add_estimate(MODERATE, 0.75);
				break;
			default :
				$evaluator->add_estimate(SPAM, 0.75);
		}
	}

	/**
	 * Filters a comment.
	 *
	 * @return bool
	 */

	public function is_spam()
	{
		foreach ((array) get_class_methods($this) as $method)
		{
			if (strpos($method, 'valid_') === 0 && $this->$method() === false)
			{
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

	protected function valid_trap()
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
		if (!$needle || !$string)
		{
			return $count;
		}

		$mb = function_exists('mb_strtolower') && function_exists('mb_substr_count');
		$string = $mb ? mb_strtolower(' '.$string.' ', 'UTF-8') : strtolower(' '.$string.' ');

		if (!is_array($needle))
		{
			$needle = $mb ? mb_strtolower($needle, 'UTF-8') : strtolower($needle);
			$needle = do_list($needle);
		}

		foreach ($needle as $find)
		{
			if (!empty($find))
			{
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

	protected function valid_charcount()
	{
		$string = $this->form['message'];

		if (!$string)
		{
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

	protected function valid_spamwords()
	{	
		$stack = array();

		foreach (do_list(get_pref('rah_comment_spam_check')) as $f)
		{
			if ($f && isset($this->form[$f]))
			{
				$stack[$f] = (string) $this->form[$f];
			}
		}

		return 
			$this->search(
				get_pref('rah_comment_spam_spamwords'),
				implode(' ', $stack)
			) <= get_pref('rah_comment_spam_maxspamwords');
	}

	/**
	 * Chekcs link count.
	 *
	 * @return bool
	 */

	protected function valid_linkcount()
	{
		return 
			$this->search(
				array('https://', 'http://', 'ftp://', 'ftps://'),
				$this->form['message']
			) <= get_pref('rah_comment_spam_urlcount');
	}

	/**
	 * Checks words in the message.
	 *
	 * @return bool
	 */

	protected function valid_wordcount()
	{
		$string = trim($this->form['message']);

		if (!$string)
		{
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

	protected function valid_commentquota()
	{
		global $thisarticle;

		if (
			!get_pref('rah_comment_spam_commentuse') || 
			!get_pref('rah_comment_spam_commentlimit') ||
			(get_pref('rah_comment_spam_commentin') == 'this' && !isset($thisarticle['thisid'])) ||
			(($ip = doSlash(remote_addr())) && !$ip)
		)
		{
			return true;
		}

		$preriod = (int) get_pref('rah_comment_spam_commenttime');

		return
			safe_count(
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

	protected function valid_typespeed()
	{
		if (!get_pref('rah_comment_spam_use_type_detect'))
		{
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

	protected function valid_emaildns()
	{
		if (!get_pref('rah_comment_spam_emaildns') || !trim($this->form['email']) || !function_exists('checkdnsrr'))
		{
			return true;
		}

		$domain = trim(end(explode('@', $this->form['email'])));
		return !$domain || (!checkdnsrr($domain, 'MX') && !checkdnsrr($domain, 'A'));
	}

	/**
	 * Redirects to preferences panel.
	 */

	public function prefs() {
		header('Location: ?event=prefs#prefs-rah_comment_spam_method');
		echo 
			'<p>'.n.
			'	<a href="?event=prefs#prefs-rah_comment_spam_method">'.gTxt('continue').'</a>'.n.
			'</p>';
	}

}

new rah_comment_spam();

/**
 * Spam protection method option.
 *
 * @param  string $name Field name
 * @param  string $val  Current value
 * @return string HTML select field
 */

	function rah_comment_spam_select_method($name, $val)
	{
		foreach (array('block', 'moderate', 'spam') as $opt)
		{
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
		foreach (array('all', 'this') as $opt)
		{
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
