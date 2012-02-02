<?php	##################
	#
	#	rah_comment_spam-plugin for Textpattern
	#	version 0.3
	#	by Jukka Svahn
	#	http://rahforum.biz
	#
	###################

	if(@txpinterface == 'admin') {
		add_privs('rah_comment_spam', '1,2');
		register_tab('extensions','rah_comment_spam','Comment Antispam');
		register_callback('rah_comment_spam_page','rah_comment_spam');
		rah_comment_spam_install();
	}

	register_callback('rah_comment_spam','comment.save');
	register_callback('rah_comment_spam_form','comment.form');

	function rah_comment_spam() {
		$shot = '';
		$form = getComment();
		$evaluator =& get_comment_evaluator();
		$rs = rah_comment_spam_fetch();
		extract($rs);
		if($field) $shot = (trim(ps($field))) ? 1 : $shot;
		$check = explode(',',$check);
		$where = 
			((in_array('message',$check)) ? $form['message'].' ' : '').
			((in_array('web',$check)) ? $form['web'].' ' : '').
			((in_array('email',$check)) ? $form['email'].' ' : '').
			((in_array('name',$check)) ? $form['name'].' ' : '');
		$shot = rah_comment_spam_countspamwords($shot,$rs,$where);
		$shot = rah_comment_spam_limitwords($shot,$form['message'],$rs);
		$shot = rah_comment_spam_limitchars($shot,$form['message'],$rs);
		$shot = rah_comment_spam_urlcount($shot,$form['message'],$rs);
		$shot = rah_comment_spam_limitcomments($shot,$rs);
		$shot = rah_comment_spam_dns($shot,$form['email'],$rs);
		if($shot) {
			switch($method) {
				case 'block' :
					$evaluator -> add_estimate(RELOAD,1,$message);
				break;
				case 'spam' :
					$evaluator -> add_estimate(SPAM,0.75);
				break;
				case 'moderate' :
					$evaluator -> add_estimate(MODERATE,0.75);
				break;
				default :
					$evaluator -> add_estimate(SPAM,0.75);
			}
		}
	}

	function rah_comment_spam_form() {
		$fieldname = fetch('value','rah_comment_spam','name','field');
		if($fieldname) return '<div style="display:none;"><input type="text" value="'.htmlspecialchars(ps($fieldname)).'" name="'.htmlspecialchars($fieldname).'" /></div>'.n;
	}

	function rah_comment_spam_countspamwords($shot='',$rs='',$where='') {
		$i = 0;
		extract($rs);
		if($spamwords) {
			$spamword = explode(',',$spamwords);
			foreach($spamword as $needle) {
				$needle = trim($needle);
				if(!empty($needle)) $i = $i + substr_count(strtolower(' '.$where.' '),strtolower($needle));
			}
			if($i>$maxspamwords) $shot = 1;
		}
		return $shot;
	}

	function rah_comment_spam_limitchars($shot='',$checking='',$rs='') {
		extract($rs);
		if($minchars && $maxchars) {
			$chars = strlen($checking);
			if($minchars > 1 and $chars < $minchars) $shot = 1;
			if($chars > $maxchars) $shot = 1;
		}
		return $shot;
	}

	function rah_comment_spam_limitwords($shot='',$checking='',$rs='') {
		extract($rs);
		if($maxwords && $minwords) {
			$words = count(explode(chr(32),$checking));
			if($minwords > 1 and $words < $minwords) $shot = 1;
			if($words > $maxwords) $shot = 1;
		}
		return $shot;
	}

	function rah_comment_spam_limitcomments($shot='',$rs='') {
		global $thisarticle, $is_article_list;
		extract($rs);
		if($commentuse == 'yes') {
			$ip = (isset($_SERVER['REMOTE_ADDR'])) ? $_SERVER['REMOTE_ADDR'] : '';
			if($ip) {
				$time = strtotime('now') - $commenttime;
				$sql = 
					"1=1".
					" and ip='".doSlash($ip)."'".
					" and UNIX_TIMESTAMP(posted) > $time ".
					(($commentin == 'this' && $is_article_list == false) ? " and parentid='".doSlash($thisarticle['id'])."'" : '')
				;
				if(
					safe_count(
						'txp_discuss',
						$sql
					) > $commentlimit
				) $shot = 1;
			}
		}
		return $shot;
	}

	function rah_comment_spam_urlcount($shot='',$checking='',$rs='') {
		extract($rs);
		if(substr_count(strtolower(' '.$checking.' '),'http://') > $urlcount) {
			$shot = 1;
		}
		return $shot;
	}

	function rah_comment_spam_dns($shot='',$checking='',$rs='') {
		extract($rs);
		if($emaildns == 'no')
			return $shot;
		if(!$checking)
			return $shot;
		$checking = explode('@',$checking);
		if(count($checking) != 2)
			return 1;
		$domain = trim($checking[1]);
		if(!$domain)
			return 1;
		if(!function_exists('checkdnsrr'))
			return $shot;
		if(!(checkdnsrr($domain,'MX') || checkdnsrr($domain,'A')))
			return 1;
		return $shot;
	}

	function rah_comment_spam_page() {
		global $step;
		require_privs('rah_comment_spam');
		if(in_array($step,array(
			'rah_comment_spam_save'
		))) $step();
		else rah_comment_spam_edit();
	}
	
	function rah_comment_spam_install() {
		safe_query(
			"CREATE TABLE IF NOT EXISTS ".safe_pfx('rah_comment_spam')." (
				`name` VARCHAR(255) NOT NULL ,
				`value` LONGTEXT NOT NULL,
			PRIMARY KEY(`name`))"
		);
		$array = rah_comment_spam_vars(2);
		foreach($array as $i) {
			if(
				safe_count(
					'rah_comment_spam',
					"name='".doSlash($i['name'])."'"
				) == 0
			) 	
				safe_insert(
					"rah_comment_spam",
					"name='".doSlash($i['name'])."',value='".doSlash($i['default'])."'"
				);
		}
	}

	function rah_comment_spam_vars($output=1) {
		$array =  
			array(
				array(
					'name' => 'spamwords',
					'default' => '[URL], viagra, penis'
				),
				array(
					'name' => 'method',
					'default' => 'block'
				),
				array(
					'name' => 'message',
					'default' => 'Your comment was marked as spam.'
				),
				array(
					'name' => 'field',
					'default' => 'phone'
				),
				array(
					'name' => 'urlcount',
					'default' => '3'
				),
				array(
					'name' => 'maxspamwords',
					'default' => '0'
				),
				array(
					'name' => 'maxwords',
					'default' => '200'
				),
				array(
					'name' => 'minwords',
					'default' => '1'
				),
				array(
					'name' => 'maxchars',
					'default' => '2000'
				),
				array(
					'name' => 'minchars',
					'default' => '2'
				),
				array(
					'name' => 'check',
					'default' => 'message,web,name'
				),
				array(
					'name' => 'commentlimit',
					'default' => '10'
				),
				array(
					'name' => 'commentin',
					'default' => 'all'
				),
				array(
					'name' => 'commentuse',
					'default' => 'yes'
				),
				array(
					'name' => 'commenttime',
					'default' => '300'
				),
				array(
					'name' => 'emaildns',
					'default' => 'no'
				)
			)
		;
		if($output == 1) {
			$fields = array();
			foreach($array as $needle) {
				$fields[] = $needle['name'];
			}
			return $fields;
		} else return $array;
	}

	function rah_comment_spam_save() {
		$fields = rah_comment_spam_vars(1);
		extract(gpsa($fields));
		foreach($fields as $fi) {
			$val = $$fi;
			if(is_array($val)) $val = implode(',',$val);
			safe_update(
				'rah_comment_spam',
				"value='".doSlash($val)."'",
				"name='".doSlash($fi)."'"
			);
		}
		rah_comment_spam_edit('Spam preferences saved.');
	}

	function rah_comment_spam_fetch($escape=0) {
		$out = array();
		$rs = 
			safe_rows_start(
				'*',
				'rah_comment_spam',
				'1=1 order by name asc'
			)
		;
		while($a = nextRow($rs)) {
			extract($a);
			if($escape == 1) $value = htmlspecialchars($value);
			$out[$name] = $value;
		}
		return $out;
	}

	function rah_comment_spam_edit($message='') {
		pagetop('Comment Antispam',$message);
		extract(rah_comment_spam_fetch(1));
		$check = explode(',',$check);
		echo n.
				'	<form method="post" action="index.php" style="width:950px;margin:0 auto;">'.n.
				'		<h1><strong>rah_comment_spam</strong> | Comment antispam tools</h1>'.n.
				'		<p>&#187; <a href="?event=plugin&amp;step=plugin_help&amp;name=rah_comment_spam">Documentation</a></p>'.n.
				'		<p><input type="submit" value="Save settings" class="publish" /></p>'.n.
				'		<p>'.n.
				'			<label>'.n.
				'					Protection method:'.n.
				'					<select name="method">'.n.
				'						<option value="block"'.(($method == 'block') ? ' selected="selected"' : '').'>Block and don\'t save</option>'.n.
				'						<option value="moderate"'.(($method == 'moderate') ? ' selected="selected"' : '').'>Moderate spam</option>'.n.
				'						<option value="spam"'.(($method == 'spam') ? ' selected="selected"' : '').'>Flag as spam</option>'.n.
				'					</select>'.n.
				'			</label>'.n.
				'			<label>'.n.
				'				Error message:'.n.
				'				<input class="edit" type="text" size="80" name="message" value="'.$message.'" />'.n.
				'			</label>'.n.
				'		</p>'.n.
				'		<fieldset style="padding:20px;margin:20px 0;">'.n.
				'			<legend>Comment message limitations</legend>'.n.
				'			<table style="width:100%;" cellspacing="2" cellpadding="0" border="0">'.n.
				'				<tr>'.n.
				'					<td><label for="maxwords">Max words:</label></td>'.n.
				'					<td><input class="edit" id="maxwords" type="text" size="4" name="maxwords" value="'.$maxwords.'" /></td>'.n.
				'					<td><label for="minwords">Min words:</label></td>'.n.
				'					<td><input class="edit" id="minwords" type="text" size="4" name="minwords" value="'.$minwords.'" /></td>'.n.
				'				</tr>'.n.
				'				<tr>'.n.
				'					<td><label for="maxchars">Max characters:</label></td>'.n.
				'					<td><input class="edit" id="maxchars" type="text" size="4" name="maxchars" value="'.$maxchars.'" /></td>'.n.
				'					<td><label for="minchars">Min characters:</label></td>'.n.
				'					<td><input class="edit" id="minchars" type="text" size="4" name="minchars" value="'.$minchars.'" /></td>'.n.
				'				</tr>'.n.
				'				<tr>'.n.
				'					<td><label for="maxurl">Max amount of URLs:</label></td>'.n.
				'					<td colspan="3"><input class="edit" id="maxurl" type="text" size="4" name="urlcount" value="'.$urlcount.'" /></label></td>'.n.
				'				</tr>'.n.
				'			</table>'.n.
				'		</fieldset>'.n.
				'		<fieldset style="padding:20px;margin:20px 0;">'.n.
				'			<legend>Comment spamwords. Comma seperated if multiple.</legend>'.n.
				'			<textarea name="spamwords" style="width:95%;" cols="30" rows="8">'.$spamwords.'</textarea>'.n.
				'			<p><br /><label>Max amount of spam words until comment becomes a spam (zero is instant): <input type="text" class="edit" name="maxspamwords" size="4" value="'.$maxspamwords.'" /></p>'.n.
				'			<p>Check spamwords against:</p>'.n.
				'			<p>'.n.
				'				<label><input type="checkbox" name="check[]" value="message"'.((in_array('message',$check)) ? ' checked="checked"' : '').' /> Comment message</label>'.n.
				'				<label><input type="checkbox" name="check[]" value="web"'.((in_array('web',$check)) ? ' checked="checked"' : '').' /> Comment web input</label>'.n.
				'				<label><input type="checkbox" name="check[]" value="email"'.((in_array('email',$check)) ? ' checked="checked"' : '').' /> Comment email</label>'.n.
				'				<label><input type="checkbox" name="check[]" value="name"'.((in_array('name',$check)) ? ' checked="checked"' : '').' /> Commentator name</label>'.n.
				'			</p>'.n.
				'		</fieldset>'.n.
				'		<fieldset style="padding:20px;margin:20px 0;">'.n.
				'			<legend>Limit posting</legend>'.n.
				'			<p>'.n.
				'				<label for="commentuse">Limit posting?</label>'.n.
				'				<select id="commentuse" name="commentuse">'.n.
				'					<option value="yes"'.(($commentuse == 'yes') ? ' selected="selected"' : '').'>Yes</option>'.n.
				'					<option value="no"'.(($commentuse == 'no') ? ' selected="selected"' : '').'>No</option>'.n.
				'				</select>'.n.
				'				<label for="commentin">Count posts in articles: </label>'.n.
				'				<select id="commentin" name="commentin">'.n.
				'					<option value="all"'.(($commentin == 'all') ? ' selected="selected"' : '').'>All</option>'.n.
				'					<option value="this"'.(($commentin == 'this') ? ' selected="selected"' : '').'>Being displayed</option>'.n.
				'				</select>'.n.
				'			</p>'.n.
				'			<p>'.n.
				'				<label for="commentlimit">Maximum of </label>'.n.
				'				<input class="edit" id="commentlimit" type="text" size="4" name="commentlimit" value="'.$commentlimit.'" /> posts '.n.
				'				<label for="commenttime">Within </label>'.n.
				'				<input class="edit" type="text" size="6" id="commenttime" name="commenttime" value="'.$commenttime.'" /> seconds'.n.
				'			</p>'.n.
				'		</fieldset>'.n.
				'		<fieldset style="padding:20px;margin:20px 0;">'.n.
				'			<legend>Hidden spam trap field</legend>'.n.
				'			<p>Field that is hidden with CSS, so, users can\'t see it. If it is filled, by bot usually, comment is marked as spam. Left field below empty if you don\'t want to use this feature.</p>'.n.
				'			<p><label>Name of the field (if empty not used): <input class="edit" type="text" name="field" size="80" value="'.$field.'" /></label></p>'.n.
				'		</fieldset>'.n.
				'		<fieldset style="padding:20px;margin:20px 0;">'.n.
				'			<legend>DNS</legend>'.n.
				'			<p>Check email\'s DNS. If domain returns false, flags comment spam. Requires <code>checkdnsrr()</code>.</p>'.n.
				'			<p>'.n.
				'				<label for="emaildns">Use DNS: </label>'.n.
				'				<select id="emaildns" name="emaildns">'.n.
				'					<option value="no"'.(($emaildns == 'no') ? ' selected="selected"' : '').'>No</option>'.n.
				'					<option value="yes"'.(($emaildns == 'yes') ? ' selected="selected"' : '').'>Yes</option>'.n.
				'				</select>'.n.
				'			</p>'.n.
				'		</fieldset>'.n.
				'		<input type="hidden" name="event" value="rah_comment_spam" />'.n.
				'		<input type="hidden" name="step" value="rah_comment_spam_save" />'.n.
				'	</form>'.n;
	}?>