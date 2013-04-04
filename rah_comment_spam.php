<?php

/**
 * Rah_comment_spam plugin for Textpattern CMS
 *
 * @author Jukka Svahn
 * @date 2008-
 * @license GNU GPLv2
 * @link http://rahforum.biz/plugins/rah_comment_spam
 *
 * Copyright (C) 2012 Jukka Svahn <http://rahforum.biz>
 * Licensed under GNU Genral Public License version 2
 * http://www.gnu.org/licenses/gpl-2.0.html
 */

	rah_comment_spam::get();

class rah_comment_spam
{
	static public $version = '0.7';
	
	/**
	 * @var obj Stores an instance of the class
	 */
	
	static public $instance = null;
	
	/**
	 * @var array Stores the form
	 */
	
	public $form = array();

	/**
	 * Installer
	 * @param string $event Admin-side event.
	 * @param string $step Admin-side, plugin-lifecycle step.
	 */

	static public function install($event='', $step='')
	{
		global $prefs;

		if ($step == 'deleted')
		{
			safe_delete(
				'txp_prefs',
				"name like 'rah\_comment\_spam\_%'"
			);
			return;
		}

		if ((string) get_pref(__CLASS__.'_version') === self::$version)
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
			$n = __CLASS__.'_'.$name;

			if (!isset($prefs[$n]))
			{
				set_pref($n, $val[1], 'comments', PREF_BASIC, $val[0], $position);
				$prefs[$n] = $val[1];
			}

			$position++;
		}

		set_pref(__CLASS__.'_version', self::$version, 'rah_cspam', 2, '', 0);
		$prefs[__CLASS__.'_version'] = self::$version;
	}

	/**
	 * Gets an instance.
	 *
	 * @return obj
	 */

	static public function get()
	{
		if (self::$instance === null)
		{
			self::$instance = new rah_comment_spam();
		}

		return self::$instance;
	}

	/**
	 * Constructor
	 */
	
	public function __construct()
	{
		add_privs('plugin_prefs.'.__CLASS__, '1,2');
		register_callback(array(__CLASS__, 'install'), 'plugin_lifecycle.'.__CLASS__);
		register_callback(array($this, 'prefs'), 'plugin_prefs.'.__CLASS__);
		register_callback(array($this, 'comment_save'), 'comment.save');
		register_callback(array($this, 'comment_form'), 'comment.form');
	}

	/**
	 * Adds fields to the comment form
	 * @return string HTML
	 */

	public function comment_form()
	{
		global $prefs;

		$out = array();

		if (!empty($prefs['rah_comment_spam_use_type_detect']))
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

		if (!empty($prefs['rah_comment_spam_field']))
		{
			$out[] = 
				'<div style="display:none">'.
					fInput('text', htmlspecialchars($prefs['rah_comment_spam_field']), ps($prefs['rah_comment_spam_field'])).
				'</div>';
		}

		return implode(n, $out);
	}

	/**
	 * Hook to commoent form callback events
	 */

	public function comment_save()
	{
		global $prefs;

		$this->form = getComment();

		if (!$this->is_spam())
		{
			return;
		}

		$evaluator =& get_comment_evaluator();

		switch ($prefs['rah_comment_spam_method'])
		{
			case 'block' :
				$evaluator->add_estimate(RELOAD, 1, gTxt($prefs['rah_comment_spam_message']));
				break;
			case 'moderate' :
				$evaluator->add_estimate(MODERATE, 0.75);
				break;
			default :
				$evaluator->add_estimate(SPAM, 0.75);
		}
	}

	/**
	 * Filters comment
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
	 * Validates hidden input
	 * @return bool
	 */
	
	protected function valid_trap()
	{
		global $prefs;
		return !$prefs['rah_comment_spam_field'] || !trim(ps($prefs['rah_comment_spam_field']));
	}

	/**
	 * Finds needles from haystack.
	 * @param mixed $needle Needle to search for. Either a comma-separated string or an array.
	 * @param string $string String to search.
	 * @param int $count Starting value.
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
	 * Counts characters
	 * @return bool
	 */

	protected function valid_charcount()
	{
		global $prefs;

		$string = $this->form['message'];

		if (!$string)
		{
			return true;
		}

		$chars = function_exists('mb_strlen') ? mb_strlen($string, 'UTF-8') : strlen($string);

		return
			(!$prefs['rah_comment_spam_maxchars'] || $prefs['rah_comment_spam_maxchars'] >= $chars) && 
			(!$prefs['rah_comment_spam_minchars'] || $prefs['rah_comment_spam_minchars'] <= $chars)
		;
	}

	/**
	 * Checks for blacklisted words
	 * @return bool
	 */

	protected function valid_spamwords()
	{	
		global $prefs;

		$stack = array();

		foreach (do_list($prefs['rah_comment_spam_check']) as $f)
		{
			if ($f && isset($this->form[$f]))
			{
				$stack[$f] = (string) $this->form[$f];
			}
		}

		return 
			$this->search(
				$prefs['rah_comment_spam_spamwords'],
				implode(' ', $stack)
			) <= $prefs['rah_comment_spam_maxspamwords'];
	}
	
	/**
	 * Chekcs link count
	 * @return bool
	 */
	
	protected function valid_linkcount()
	{
		global $prefs;
		return 
			$this->search(
				array('https://', 'http://', 'ftp://', 'ftps://'),
				$this->form['message']
			) <= $prefs['rah_comment_spam_urlcount'];
	}

	/**
	 * Count words in a string
	 * @return bool
	 */

	protected function valid_wordcount()
	{
		global $prefs;

		$string = trim($this->form['message']);

		if (!$string)
		{
			return true;
		}

		$words = count(preg_split('/[^\p{L}\p{N}\']+/u', $string));

		return
			(!$prefs['rah_comment_spam_maxwords'] || $prefs['rah_comment_spam_maxwords'] >= $words) && 
			(!$prefs['rah_comment_spam_minwords'] || $prefs['rah_comment_spam_minwords'] <= $words)
		;
	}

	/**
	 * Limit user's comment posting.
	 * @return bool
	 */

	protected function valid_commentquota()
	{
		global $thisarticle, $prefs;
		
		if (
			!$prefs['rah_comment_spam_commentuse'] || 
			!$prefs['rah_comment_spam_commentlimit'] ||
			($prefs['rah_comment_spam_commentin'] == 'this' && !isset($thisarticle['thisid'])) ||
			(($ip = doSlash(remote_addr())) && !$ip)
		)
		{
			return true;
		}

		$preriod = (int) $prefs['rah_comment_spam_commenttime'];

		return
			safe_count(
				'txp_discuss',
				"ip='$ip' and UNIX_TIMESTAMP(posted) > (UNIX_TIMESTAMP(now())-$preriod)".
				($prefs['rah_comment_spam_commentin'] == 'this' ? " and parentid='".doSlash($thisarticle['thisid'])."'" : '')
			) < $prefs['rah_comment_spam_commentlimit'];
	}

	/**
	 * Check typing speed, make sure the user fidled with the comment form.
	 * @return bool
	 */

	protected function valid_typespeed()
	{
		global $prefs;

		if (!$prefs['rah_comment_spam_use_type_detect'])
		{
			return true;
		}

		$type_interval = (int) $prefs['rah_comment_spam_type_interval'];
		$time = (int) ps('rah_comment_spam_time');

		@$barrier = strtotime('now')-$type_interval;
		$md5 = md5(get_pref('blog_uid').$time);

		return $md5 === ps('rah_comment_spam_nonce') && $time <= $barrier;
	}

	/**
	 * Check DNS records for the email address.
	 * @return bool
	 */

	protected function valid_emaildns()
	{
		global $prefs;

		if (!$prefs['rah_comment_spam_emaildns'] || !trim($this->form['email']) || !function_exists('checkdnsrr'))
		{
			return true;
		}

		$domain = trim(end(explode('@', $this->form['email'])));
		return !$domain || (!checkdnsrr($domain, 'MX') && !checkdnsrr($domain, 'A'));
	}

	/**
	 * Redirect to preferences panel
	 */

	public function prefs() {
		header('Location: ?event=prefs#prefs-rah_comment_spam_method');
		echo 
			'<p>'.n.
			'	<a href="?event=prefs#prefs-rah_comment_spam_method">'.gTxt('continue').'</a>'.n.
			'</p>';
	}

}

/**
 * Spam protection method option
 * @param string $name Field name.
 * @param string $val Current value.
 * @return string HTML select field.
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
 * Comment count range option
 * @param string $name Field name.
 * @param string $val Current value.
 * @return string HTML select field.
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
 * Textarea for preferences panel
 * @param string $name Field name.
 * @param string $val Current value.
 * @return string HTML textarea.
 */

	function rah_comment_spam_textarea($name, $val)
	{
		return text_area($name, 100, 300, $val, $name);
	}
?>