<?php	##################
	#
	#	rah_comment_spam-plugin for Textpattern
	#	version 0.7
	#	by Jukka Svahn
	#	http://rahforum.biz
	#
	#	Copyright (C) 2011 Jukka Svahn <http://rahforum.biz>
	#	Licensed under GNU Genral Public License version 2
	#	http://www.gnu.org/licenses/gpl-2.0.html
	#
	###################

	if(@txpinterface == 'admin') {
		rah_comment_spam_install();
		add_privs('plugin_prefs.rah_comment_spam','1,2');
		register_callback('rah_comment_spam_prefs','plugin_prefs.rah_comment_spam');
		register_callback('rah_comment_spam_install','plugin_lifecycle.rah_comment_spam');
	} else {
		register_callback('rah_comment_spam_register','comment.save');
		register_callback('rah_comment_spam_register','comment.form');
	}

/**
	Installer
	@param $event string Admin-side event.
	@param $step string Admin-side, plugin-lifecycle step.
*/

	function rah_comment_spam_install($event='',$step='') {

		if($step == 'deleted') {
			safe_delete(
				'txp_prefs',
				"name like 'rah_comment_spam_%'"
			);
			return;
		}

		global $prefs, $textarray;

		/*
			Make sure language strings are set
		*/

		foreach(
			array(
				'method' => 'What to do comments detected as spam?',
				'method_block' => 'Ignore, do not save',
				'method_moderate' => 'Save to moderation queue',
				'method_spam' => 'Save and flag as spam',
				'message' => 'Message displayed to the user when the comment was blocked',
				'spamwords' => 'List of spam words (comma-separated)',
				'check' => 'Search spam words from following fields (comma-separated)',
				'maxspamwords' => 'Maximum number of spam words until the comment is considered as spam',
				'urlcount' => 'Number of allowed URLs',
				'minwords' => 'Minimum number of words',
				'maxwords' => 'Maximum number of words',
				'minchars' => 'Minimum number of characters',
				'maxchars' => 'Maximum number of characters',
				'field' => 'Name of hidden spam trap field (empty to disable the trap)',
				'commentuse' => 'Use quotas, limit users\' comment posting activity?',
				'commentlimit' => 'Users\' comment quota limit',
				'commentin' => 'Count users\' comments towards quota in',
				'commentin_all' => 'In all articles',
				'commentin_this' => 'In current article',
				'commenttime' => 'Quota resets every number of seconds',
				'emaildns' => 'Check email\'s DNS records?',
				'use_type_detect' => 'Check the time user spent writing the comment?',
				'type_interval' => 'Minimum time required to be spent writing a comment (in seconds)?'
			) as $string => $translation
		) {
			$name = strpos($string, 'rah_') === 0 ? $string : 'rah_comment_spam_'.$string;

			if(!isset($textarray[$name]))
				$textarray[$name] = $translation;
		}

		$version = '0.7';

		$current = isset($prefs['rah_comment_spam_version']) ? 
			$prefs['rah_comment_spam_version'] : 'base';

		if($version == $current)
			return;

		$ini = 
			array(
				'method' => 'moderate',
				'message' => 'Your comment was marked as spam.',
				'spamwords' => '',
				'maxspamwords' => 3,
				'check' => 'name, email, web, message',
				'urlcount' => 6,
				'minwords' => 1,
				'maxwords' => 10000,
				'minchars' => 1,
				'maxchars' => 65535,
				'field' => 'phone',
				'commentuse' => 1,
				'commentlimit' => 10,
				'commentin' => 'this',
				'commenttime' => 300,
				'emaildns' => 0,
				'use_type_detect' => 0,
				'type_interval' => 5,
				'nonce' => md5(uniqid(rand(),true))
			);

		/*
			Migrate preferences format from <= 0.5 to >= 0.6
		*/

		if($current == 'base') {
			@$rs = 
				safe_rows(
					'name, value',
					'rah_comment_spam',
					'1=1'
				);

			if(!empty($rs) && is_array($rs)) {
				foreach($rs as $a) {
					if(!isset($ini[$a['name']]))
						continue;
					
					if(in_array($a['name'], array(
						'use_type_detect',
						'emaildns',
						'commentuse',
					)))
						$a['value'] = $a['value'] == 'no' ? 0 : 1;
					
					$ini[$a['name']] = $a['value'];
				}

				@safe_query(
					'DROP TABLE IF EXISTS '.safe_pfx('rah_comment_spam')
				);
			}
		}

		$position = 250;

		/*
			Add preference strings
		*/
		
		foreach($ini as $name => $val) {

			$n = 'rah_comment_spam_' . $name;
			
			if(!isset($prefs[$n])) {
				
				if($name == 'nonce') {
					set_pref('rah_comment_spam_nonce',$val,'rah_cspam',2,'',0);
					continue;
				}
				
				switch($name) {
					case 'commentuse':
					case 'emaildns':
					case 'use_type_detect':
						$html = 'yesnoradio';
						break;
					case 'commentin':
						$html = 'rah_comment_spam_select_commentin';
						break;
					case 'method':
						$html = 'rah_comment_spam_select_method';
						break;
					case 'spamwords':
						$html = 'rah_comment_spam_textarea';
						break;
					default:
						$html = 'text_input';
				}

				safe_insert(
					'txp_prefs',
					"prefs_id=1,
					name='".doSlash($n)."',
					val='".doSlash($val)."',
					type=0,
					event='comments',
					html='$html',
					position=".$position
				);
				
				$prefs[$n] = $val;
			}
			
			$position++;
		}
		
		/*
			Set version
		*/
		
		set_pref('rah_comment_spam_version',$version,'rah_cspam',2,'',0);
		$prefs['rah_comment_spam_version'] = $version;
	}

/**
	Check for spam
	@param $event string Callback event.
*/

	function rah_comment_spam_register($event='') {
		global $prefs;

		rah_comment_spam_install();

		/*
			Add spam trap field to the comment form
		*/

		if($event == 'comment.form') {

			$out = array();

			if($prefs['rah_comment_spam_use_type_detect']) {
				
				$nonce = ps('rah_comment_spam_nonce');
				$time = ps('rah_comment_spam_time');
				
				if(!$nonce && !$time) {
					@$time = strtotime('now');
					$nonce = md5($prefs['rah_comment_spam_nonce'].$time);
				}
				
				$out[] =
					hInput('rah_comment_spam_nonce', $nonce).
					hInput('rah_comment_spam_time', $time);
			}

			if($prefs['rah_comment_spam_field'])
				$out[] = 
					'<div style="display:none;">'.
						fInput(
							'text',
							htmlspecialchars($prefs['rah_comment_spam_field']),
							ps($prefs['rah_comment_spam_field'])
						).
					'</div>';

			return n.implode('',$out).n;
		}

		/*
			Check sent comments before saving
		*/

		$comment = new rah_comment_spam();

		if($comment->is_spam()) {
			$evaluator =& get_comment_evaluator();
			switch($prefs['rah_comment_spam_method']) {
				case 'block' :
					$evaluator->add_estimate(RELOAD,1, gTxt($prefs['rah_comment_spam_message']));
				break;
				case 'moderate' :
					$evaluator->add_estimate(MODERATE,0.75);
				break;
				default :
					$evaluator->add_estimate(SPAM,0.75);
			}
		}
	}

/**
	Contains anti-spam tools
*/

class rah_comment_spam {

	private $form = array();

/**
	Filter comment
	@return bool TRUE if comment is detected as spam.
*/

	public function is_spam() {
		global $prefs;

		$this->form = getComment();

		foreach(do_list($prefs['rah_comment_spam_check']) as $f) 
			if(isset($this->form[$f]) && !isset($stack[$f]))
				$stack[$f] = $this->form[$f];

		$stack = isset($stack) ? implode(' ', $stack) : '';

		return 
			(
				(
					$prefs['rah_comment_spam_field'] && 
					trim(ps($prefs['rah_comment_spam_field']))
				) ||
				$this->wordcount($this->form['message']) ||
				$this->charcount($this->form['message']) ||
				$this->search(
					$prefs['rah_comment_spam_spamwords'],
					$stack,
					$prefs['rah_comment_spam_maxspamwords']
				) ||
				$this->search(
					array(
						'https://',
						'http://',
						'ftp://',
						'ftps://'
					),
					$this->form['message'],
					$prefs['rah_comment_spam_urlcount']
				) ||
				$this->commentquota() || 
				$this->typespeed() || 
				$this->emaildns()
			);
	}

/**
	Finds needles from haystack. If $max is exceeded returns TRUE.
	@param $needle mixed Needle to search for. Either comma-separated string or array.
	@param $string string String to search.
	@param $max int Maximum occurrences.
	@param $count int Starting value.
	@return bool
*/

	private function search($needle,$string,$max=0,$count=0) {

		if(!$needle || !$string)
			return false;
	
		/*
			Get the status of multibyte functions
		*/

		$mb = function_exists('mb_strtolower') && function_exists('mb_substr_count');

		/*
			Convert cases, turn needle to
			array if string was provided
		*/

		$string = $mb ? mb_strtolower(' '.$string.' ','UTF-8') : strtolower(' '.$string.' ');

		if(!is_array($needle)) {
			$needle = $mb ? mb_strtolower($needle,'UTF-8') : strtolower($needle);
			$needle = do_list($needle);
		}

		foreach($needle as $find)
			if(!empty($find))
				$count += $mb ? mb_substr_count($string,$find,'UTF-8') : substr_count($string,$find);

		if($count > $max)
			return true;
	}

/**
	Count characters, return TRUE if exceeds the limit.
	@param $string string String to count
	@return bool
*/

	private function charcount($string) {
		global $prefs;

		if(!$string || (!$prefs['rah_comment_spam_minchars'] && !$prefs['rah_comment_spam_maxchars']))
			return false;

		$chars = function_exists('mb_strlen') ? mb_strlen($string, 'UTF-8') : strlen($string);
		
		if(
			($prefs['rah_comment_spam_maxchars'] && $prefs['rah_comment_spam_maxchars'] < $chars) || 
			($prefs['rah_comment_spam_minchars'] && $chars <= $prefs['rah_comment_spam_minchars'])
		)
			return true;
	}

/**
	Count words in string, return TRUE if exeeds the limit.
	@param $string string String to count
	@return bool
*/

	private function wordcount($string) {
		global $prefs;
		
		if(!$string || (!$prefs['rah_comment_spam_maxwords'] && !$prefs['rah_comment_spam_minwords']))
			return false;
		
		$words = count(explode(chr(32),$string));
		
		if(
			($prefs['rah_comment_spam_maxwords'] && $prefs['rah_comment_spam_maxwords'] < $words) || 
			($prefs['rah_comment_spam_minwords'] && $words <= $prefs['rah_comment_spam_minwords'])
		)
			return true;
	}

/**
	Limit user's comment posting.
	@return bool TRUE when activity exceeds quota.
*/

	private function commentquota() {
		global $thisarticle, $prefs;
		
		if(
			!$prefs['rah_comment_spam_commentuse'] || 
			!$prefs['rah_comment_spam_commentlimit'] ||
			($prefs['rah_comment_spam_commentin'] == 'this' && !isset($thisarticle['thisid'])) ||
			(($ip = doSlash(remote_addr())) && !$ip)
		)
			return false;
		
		$preriod = (int) $prefs['rah_comment_spam_commenttime'];
		
		if(
			safe_count(
				'txp_discuss',
				"ip='$ip' and UNIX_TIMESTAMP(posted) > (UNIX_TIMESTAMP(now())-$preriod)".
				($prefs['rah_comment_spam_commentin'] == 'this' ? " and parentid='".doSlash($thisarticle['thisid'])."'" : '')
			) >= $prefs['rah_comment_spam_commentlimit']
		)
			return true;
	}

/**
	Check typing speed, make sure the user fidled with the comment form.
	@return bool TRUE when comment is considered as spam.
*/

	private function typespeed() {
		global $prefs;

		if(!$prefs['rah_comment_spam_use_type_detect'])
			return false;
		
		$type_interval = (int) $prefs['rah_comment_spam_type_interval'];
		$time = (int) ps('rah_comment_spam_time');

		@$barrier = strtotime('now')-$type_interval;
		$md5 = md5($prefs['rah_comment_spam_nonce'].$time);

		if($md5 != ps('rah_comment_spam_nonce') || $time >= $barrier)
			return true;
	}

/**
	Check DNS records of the email address.
	@return bool TRUE when comment is considered as spam.
*/

	private function emaildns() {
		global $prefs;
		
		if(!$prefs['rah_comment_spam_emaildns'] || !trim($this->form['email']))
			return false;
		
		$domain = trim(end(explode('@',$this->form['email'])));

		if(!$domain)
			return true;
		
		if(!function_exists('checkdnsrr'))
			return false;
		
		if(!(checkdnsrr($domain,'MX') || checkdnsrr($domain,'A')))
			return true;
	}
}

/**
	Spam protection method option
	@param $name string Field name.
	@param $val int Current value.
	@return string HTML select field.
*/

	function rah_comment_spam_select_method($name, $val) {

		foreach(array('block', 'moderate', 'spam') as $opt)
			$out[$opt] = gTxt('rah_comment_spam_method_'.$opt);

		return selectInput($name, $out, $val, '', '', $name);
	}

/**
	Comment count range option
	@param $name string Field name.
	@param $val int Current value.
	@return string HTML select field.
*/

	function rah_comment_spam_select_commentin($name, $val) {

		foreach(array('all', 'this') as $opt)
			$out[$opt] = gTxt('rah_comment_spam_commentin_'.$opt);
		
		return selectInput($name, $out, $val, '', '', $name);
	}

/**
	Textarea for preferences panel
	@param $name string Field name.
	@param $val int Current value.
	@return string HTML textarea.
*/

	function rah_comment_spam_textarea($name, $val) {
		return text_area($name, 100, 300, $val, $name);
	}

/**
	Redirect to preferences panel
*/

	function rah_comment_spam_prefs() {
		header('Location: ?event=prefs#prefs-rah_comment_spam_method');
		echo 
			'<p>'.n.
			'	<a href="?event=prefs#prefs-rah_comment_spam_method">'.gTxt('continue').'</a>'.n.
			'</p>';
	}
?>