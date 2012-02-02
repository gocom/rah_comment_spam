<?php	##################
	#
	#	rah_comment_spam-plugin for Textpattern
	#	version 0.4
	#	by Jukka Svahn
	#	http://rahforum.biz
	#
	###################

	if(@txpinterface == 'admin') {
		add_privs('rah_comment_spam','1,2');
		register_tab('extensions','rah_comment_spam','Comment Antispam');
		register_callback('rah_comment_spam_page','rah_comment_spam');
		register_callback('rah_comment_spam_head','admin_side','head_end');
	} else {
		register_callback('rah_comment_spam','comment.save');
		register_callback('rah_comment_spam_form','comment.form');
	}

/**
	Check for spam
*/

	function rah_comment_spam() {
		$form = getComment();
		$evaluator =& get_comment_evaluator();
		
		@$rs = rah_comment_spam_fetch();
		
		if(!isset($rs['version'])) {
			rah_comment_spam_install();
			$rs = rah_comment_spam_fetch();
		}
		
		$check = explode(',',$rs['check']);
		
		$where = 
			((in_array('message',$check)) ? $form['message'].' ' : '').
			((in_array('web',$check)) ? $form['web'].' ' : '').
			((in_array('email',$check)) ? $form['email'].' ' : '').
			((in_array('name',$check)) ? $form['name'].' ' : '');
		
		if(
			($rs['field'] && trim(ps($rs['field']))) ||
			
			rah_comment_spam_words(
				$where,
				$rs
			) ||
			
			rah_comment_spam_chars(
				$form['message'],
				$rs
			) ||
			
			rah_comment_spam_find(
				$rs['spamwords'],
				$form['message'],
				$rs['maxspamwords']
			) ||
			
			rah_comment_spam_find(
				array(
					'https://',
					'http://',
					'ftp://',
					'ftps://'
				),
				$form['message'],
				$rs['urlcount']
			) ||
			
			rah_comment_spam_limitcomments(
				$rs
			) ||
			
			rah_comment_spam_dns($form['email'],$rs)
		
		) {
			switch($rs['method']) {
				case 'block' :
					$evaluator -> add_estimate(RELOAD,1,$rs['message']);
				break;
				case 'moderate' :
					$evaluator -> add_estimate(MODERATE,0.75);
				break;
				default :
					$evaluator -> add_estimate(SPAM,0.75);
			}
		}
	}

/**
	Outputs hidden spam trap
*/

	function rah_comment_spam_form() {
		$field = 
			fetch(
				'value',
				'rah_comment_spam',
				'name',
				'field'
			);

		if(!empty($field))
			return 
				n.
				'<div style="display:none;">'.
					'<input type="text" value="'.htmlspecialchars(ps($field)).'" name="'.htmlspecialchars($field).'" />'.
				'</div>'
				.n
			;
	}

/**
	Finds needle from haystack
*/

	function rah_comment_spam_find($needle,$string,$max=0,$count=0) {
		
		if(!$needle || !$string)
			return;
		
		$string = strtolower(' '.$string.' ');
		
		if(!is_array($needle))
			$needle = explode(',',strtolower($needle));
		
		foreach($needle as $find) {
			$find = trim($find);
			if(!empty($find))
				$count += substr_count($string,$find);
		}
		
		if($count > $max)
			return 1;
	}

/**
	Count characters
*/

	function rah_comment_spam_chars($string,$rs) {
		extract($rs);
		
		if(!$string || (!$minchars && !$maxchars))
			return;
		
		$chars = strlen($string);
		
		if(($maxchars && $maxchars < $chars) || ($minchars && $chars <= $minchars))
			return 1;
		
	}

/**
	Count words
*/

	function rah_comment_spam_words($string,$rs) {
		extract($rs);
		
		if(!$string || (!$maxwords && !$minwords))
			return;
		
		$words = count(explode(chr(32),$string));
		
		if(($maxwords && $maxwords < $words) || ($minwords && $words <= $minwords))
			return 1;
	}

/**
	Limit comment posting
*/

	function rah_comment_spam_limitcomments($rs) {
		global $thisarticle;
		extract($rs);
		
		if(
			$commentuse != 'yes' || 
			!$commenttime || 
			!$commentlimit || 
			!isset($_SERVER['REMOTE_ADDR']) || 
			($commentin == 'this' && !isset($thisarticle['id']))
		)
			return;
		
		if(
			safe_count(
				'txp_discuss',
				"ip='".doSlash($_SERVER['REMOTE_ADDR'])."' and ".
				"UNIX_TIMESTAMP(posted) > (UNIX_TIMESTAMP(now())-$commenttime)".
				(($commentin == 'this') ? " and parentid='".doSlash($thisarticle['id'])."'" : '')
			) >= $commentlimit
		)
			return 1;
	}

/**
	Check DNS
*/

	function rah_comment_spam_dns($checking,$rs) {
		
		extract($rs);
		
		if($emaildns == 'no' || !trim($checking))
			return;
		
		$checking = explode('@',$checking);
		
		if(count($checking) != 2)
			return 1;
		
		$domain = trim($checking[1]);

		if(!$domain)
			return 1;
		
		if(substr_count($domain,'.') == 0)
			return 1;
		
		if(!function_exists('checkdnsrr'))
			return;
		
		if(!(checkdnsrr($domain,'MX') || checkdnsrr($domain,'A')))
			return 1;

	}

/**
	Delivers the panels
*/

	function rah_comment_spam_page() {
		global $step;
		require_privs('rah_comment_spam');
		rah_comment_spam_install();
		if($step == 'rah_comment_spam_save')
			$step();
		else
			rah_comment_spam_edit();
	}

/**
	Adds CSS/JS to <head> for the panel
*/

	function rah_comment_spam_head() {
		
		global $event;
		
		if($event != 'rah_comment_spam')
			return;
		
		echo <<<EOF
			<style type="text/css">
				#rah_comment_spam_container {
					width: 950px;
					margin: 0 auto;
				}
				#rah_comment_spam_container input.edit,
				#rah_comment_spam_container textarea {
					width: 940px;
					padding: 0;
				}
				#rah_comment_spam_container select {
					width: 640px;
					padding: 0;
				}
				#rah_comment_spam_container .rah_comment_spam_more {
					overflow: hidden;
				}
				#rah_comment_spam_limitations {
					margin: 0 0 20px 0;
				}
				#rah_comment_spam_limitations label {
					width: 190px;
					float: left;
					margin: 0;
					padding: 0;
				}
				#rah_comment_spam_container #rah_comment_spam_limitations input.edit {
					width: 170px;
				}
				#rah_comment_spam_container .rah_comment_spam_heading {
					font-weight: 900;
					padding: 5px 0;
					margin: 0 0 10px 0;
					border-top: 1px solid #ccc;
					border-bottom: 1px solid #ccc;
				}
				#rah_comment_spam_container .rah_comment_spam_heading span {
					cursor: pointer;
					color: #963;
				}
				#rah_comment_spam_container .rah_comment_spam_heading span:hover {
					text-decoration: underline;
				}
			</style>
			<script type="text/javascript">
				$(document).ready(function(){
					$('.rah_comment_spam_more').hide();
					$('.rah_comment_spam_heading').click(function(){
						$(this).next('div.rah_comment_spam_more').slideToggle();
					});
				});
			</script>
EOF;
	}
	
/**
	Preferences panel
*/

	function rah_comment_spam_edit($message='') {

		pagetop('Comment Antispam',$message);

		extract(
			rah_comment_spam_fetch(1)
		);

		$check = explode(',',$check);

		global $event;

		echo n.
				'	<form method="post" action="index.php" id="rah_comment_spam_container">'.n.
				'		<h1><strong>rah_comment_spam</strong> | Comment anti-spam tools</h1>'.n.
				'		<p>&#187; <a href="?event=plugin&amp;step=plugin_help&amp;name=rah_comment_spam">Documentation</a></p>'.n.
				'		<p>'.n.
				'			<strong>Preferences:</strong>'.n.
				'		</p>'.n.
				'		<p title="Click to expand" class="rah_comment_spam_heading">'.n.
				'			+ <span>Protection method and messages</span>'.n.
				'		</p>'.n.
				'		<div class="rah_comment_spam_more">'.n.
				'			<p>'.n.
				'				<label>'.n.
				'					Protection method:<br />'.n.
				'					<select name="method">'.n.
				'						<option value="block"'.(($method == 'block') ? ' selected="selected"' : '').'>Block and don\'t save</option>'.n.
				'						<option value="moderate"'.(($method == 'moderate') ? ' selected="selected"' : '').'>Moderate spam</option>'.n.
				'						<option value="spam"'.(($method == 'spam') ? ' selected="selected"' : '').'>Flag as spam</option>'.n.
				'					</select>'.n.
				'				</label>'.n.
				'			</p>'.n.
				'			<p>'.n.
				'				<label>'.n.
				'					Error message displayed to the commentator:<br />'.n.
				'					<input class="edit" type="text" name="message" value="'.$message.'" />'.n.
				'				</label>'.n.
				'			</p>'.n.
				'		</div>'.n.
				'		<p title="Click to expand" class="rah_comment_spam_heading">'.n.
				'			+ <span>Set comment message lenght limitations</span>'.n.
				'		</p>'.n.
				'		<div id="rah_comment_spam_limitations" class="rah_comment_spam_more">'.n.
				'			<p>'.n.
				'				Fill every field only with even numbers greater than 0. Minimal value is 1. '.n.
				'				If field is left empty or value is set to 0 (zero), the limit will be disabled.'.n.
				'			</p>'.n.
				'			<label>'.n.
				'				Max words:<br />'.n.
				'				<input class="edit" type="text" name="maxwords" value="'.$maxwords.'" />'.n.
				'			</label>'.n.
				'			<label>'.n.
				'				Min words:<br />'.n.
				'				<input class="edit" type="text" name="minwords" value="'.$minwords.'" />'.n.
				'			</label>'.n.
				'			<label>'.n.
				'				Max characters:<br />'.n.
				'				<input class="edit" type="text" name="maxchars" value="'.$maxchars.'" />'.n.
				'			</label>'.n.
				'			<label>'.n.
				'				Min characters:<br />'.n.
				'				<input class="edit" type="text" name="minchars" value="'.$minchars.'" />'.n.
				'			</label>'.n.
				'		</div>'.n.
				'		<p title="Click to expand" class="rah_comment_spam_heading">'.n.
				'			+ <span>URL and link limitations</span>'.n.
				'		</p>'.n.
				'		<div class="rah_comment_spam_more">'.n.
				'			<p>'.n.
				'				<label>'.n.
				'					Maximum amount of URLs? If set to zero (0) no URLs are allowed in comments.<br />'.n.
				'					<input class="edit" type="text" name="urlcount" value="'.$urlcount.'" />'.n.
				'				</label>'.n.
				'			</p>'.n.
				'		</div>'.n.
				'		<p title="Click to expand" class="rah_comment_spam_heading">'.n.
				'			+ <span>Define spam word filters</span>'.n.
				'		</p>'.n.
				'		<div class="rah_comment_spam_more">'.n.
				'			<p>'.n.
				'				<label>'.n.
				'					List of spam words. Comma separated if multiple:<br />'.n.
				'					<textarea name="spamwords" cols="30" rows="8">'.$spamwords.'</textarea>'.n.
				'				</label>'.n.
				'			</p>'.n.
				'			<p>'.n.
				'				<label>'.n.
				'					Max amount of spam words until comment becomes a spam (zero is instant):<br />'.n.
				'					<input type="text" class="edit" name="maxspamwords" size="4" value="'.$maxspamwords.'" />'.n.
				'				</label>'.n.
				'			</p>'.n.
				'			<p>Search spam words from:</p>'.n.
				'			<p>'.n.
				'				<label>'.n.
				'					<input type="checkbox" name="check[]" value="message"'.((in_array('message',$check)) ? ' checked="checked"' : '').' />'.n.
				'					Comment message'.n.
				'				</label>'.n.
				'				<label>'.n.
				'					<input type="checkbox" name="check[]" value="web"'.((in_array('web',$check)) ? ' checked="checked"' : '').' />'.n.
				'					Comment web input'.n.
				'				</label>'.n.
				'				<label>'.n.
				'					<input type="checkbox" name="check[]" value="email"'.((in_array('email',$check)) ? ' checked="checked"' : '').' />'.n.
				'					Comment email'.n.
				'				</label>'.n.
				'				<label>'.n.
				'					<input type="checkbox" name="check[]" value="name"'.((in_array('name',$check)) ? ' checked="checked"' : '').' />'.n.
				'					Commentator name'.n.
				'				</label>'.n.
				'			</p>'.n.
				'		</div>'.n.
				'		<p title="Click to expand" class="rah_comment_spam_heading">'.n.
				'			+ <span>Timeintervals and posting limits</span>'.n.
				'		</p>'.n.
				'		<div class="rah_comment_spam_more">'.n.
				'			<p>'.n.
				'				Commentators will be detected based on the IP address. If the commentator exceeds the set activity '.n.
				'				limit, the comment will be treated as spam.'.n.
				'			</p>'.n.
				'			<p>'.n.
				'				<label>'.n.
				'					Use the feature and limit posting?<br />'.n.
				'					<select name="commentuse">'.n.
				'						<option value="yes"'.(($commentuse == 'yes') ? ' selected="selected"' : '').'>Yes</option>'.n.
				'						<option value="no"'.(($commentuse == 'no') ? ' selected="selected"' : '').'>No</option>'.n.
				'					</select>'.n.
				'				</label>'.n.
				'			</p>'.n.
				'			<p>'.n.
				'				<label>'.n.
				'					Count comments in articles:<br />'.n.
				'					<select name="commentin">'.n.
				'						<option value="all"'.(($commentin == 'all') ? ' selected="selected"' : '').'>All articles</option>'.n.
				'						<option value="this"'.(($commentin == 'this') ? ' selected="selected"' : '').'>User currently viewing and commenting</option>'.n.
				'					</select>'.n.
				'				</label>'.n.
				'			</p>'.n.
				'			<p>'.n.
				'				<label>'.n.
				'					Maximum number of posts:<br />'.n.
				'					<input class="edit" type="text" name="commentlimit" value="'.$commentlimit.'" />'.n.
				'				</label>'.n.
				'				<label>'.n.
				'					Within last x seconds:<br />'.n.
				'					<input class="edit" type="text" name="commenttime" value="'.$commenttime.'" />'.n.
				'				</label>'.n.
				'			</p>'.n.
				'		</div>'.n.
				'		<p title="Click to expand" class="rah_comment_spam_heading">'.n.
				'			+ <span>Hidden spam trap</span>'.n.
				'		</p>'.n.
				'		<div class="rah_comment_spam_more">'.n.	
				'			<p>'.n.
				'				Adds a hidden spam trap field to the comment form. The field is made invisible to normal users with '.n.
				'				<abbr title="Cascading Style Sheets">CSS</abbr>. If the field is filled, by bot usually, the comment is '.n.
				'				marked as spam. Leave the field below empty if you don\'t want to use this feature.'.n.
				'			</p>'.n.
				'			<p>'.n.
				'				<label>'.n.
				'					Name of the field (if empty not used):'.n.
				'					<input class="edit" type="text" name="field" size="80" value="'.$field.'" />'.n.
				'				</label>'.n.
				'			</p>'.n.
				'		</div>'.n.
				'		<p title="Click to expand" class="rah_comment_spam_heading">'.n.
				'			+ <span>DNS validation</span>'.n.
				'		</p>'.n.
				'		<div class="rah_comment_spam_more">'.n.
				'			<p>'.n.
				'				Checks the email address\' <abbr title="Domain Name System">DNS</abbr> records.'.n.
				'				If the email\'s domain is found to be non-existent, the comment will be treated as a spam.'.n.
				'				The feature requires <a href="http://php.net/manual/en/function.checkdnsrr.php">checkdnsrr()</a> '.n.
				'				and server must be allowed to make outgoing connections.'.n.
				'			</p>'.n.
				'			<p>'.n.
				'				<label>'.n.
				'					Use DNS validation?<br />'.n.
				'					<select id="emaildns" name="emaildns">'.n.
				'						<option value="no"'.(($emaildns == 'no') ? ' selected="selected"' : '').'>No</option>'.n.
				'						<option value="yes"'.(($emaildns == 'yes') ? ' selected="selected"' : '').'>Yes</option>'.n.
				'					</select>'.n.
				'				</label>'.n.
				'			</p>'.n.
				'		</div>'.n.
				'		<p><input type="submit" value="Save settings" class="publish" /></p>'.n.
				'		<input type="hidden" name="event" value="'.$event.'" />'.n.
				'		<input type="hidden" name="step" value="rah_comment_spam_save" />'.n.
				'	</form>'.n;
	}

/**
	Installation script
*/

	function rah_comment_spam_install() {
		safe_query(
			"CREATE TABLE IF NOT EXISTS ".safe_pfx('rah_comment_spam')." (
				`name` VARCHAR(255) NOT NULL ,
				`value` LONGTEXT NOT NULL,
			PRIMARY KEY(`name`))"
		);
		
		foreach(rah_comment_spam_vars() as $key => $val) {
			if(
				safe_count(
					'rah_comment_spam',
					"name='".doSlash($key)."'"
				) == 0
			) 
				safe_insert(
					"rah_comment_spam",
					"name='".doSlash($key)."',value='".doSlash($val)."'"
				);
		}
	}

/**
	Default preferences
*/

	function rah_comment_spam_vars() {
		return  
			array(
				'spamwords' => '[URL], viagra, penis',
				'method' => 'block',
				'message' => 'Your comment was marked as spam.',
				'field' => 'phone',
				'urlcount' => '3',
				'maxspamwords' => '0',
				'maxwords' => '200',
				'minwords' => '1',
				'maxchars' => '2000',
				'minchars' => '2',
				'check' => 'message,web,name',
				'commentlimit' => '10',
				'commentin' => 'all',
				'commentuse' => 'yes',
				'commenttime' => '300',
				'emaildns' => 'no',
				'version' => '0.4'
			)
		;
	}

/**
	Saves preferences
*/

	function rah_comment_spam_save() {
		
		foreach(rah_comment_spam_vars() as $key => $value) {
			$val = ps($key);
			if(is_array($val))
				$val = implode(',',$val);
			
			safe_update(
				'rah_comment_spam',
				"value='".doSlash($val)."'",
				"name='".doSlash($key)."'"
			);
		}
		
		rah_comment_spam_edit('Spam preferences saved.');
	}

/**
	Fetch preferences into array
*/

	function rah_comment_spam_fetch($escape=0) {
		$out = array();

		$rs = 
			safe_rows(
				'*',
				'rah_comment_spam',
				'1=1'
			)
		;

		foreach($rs as $a) {
			if($escape == 1) 
				$a['value'] = htmlspecialchars($a['value']);
			$out[$a['name']] = $a['value'];
		}

		return $out;
	}