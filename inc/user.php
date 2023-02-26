<?php
	
/**
 *	functions related to users
 * 
 * 
 *  @version 1.5 2023-02-25
 */
	
function addUser($label)
{
	debugLog('<p>addUser '.$label);
	
	// check the format of the user label
	
	$label = trim($label);
	if (!$label) return '<p><span class="error">The username field is empty. Please submit a username.</span>';
	
	preg_match('/@([a-zA-Z0-9_]+)@([a-zA-Z0-9_-]+\.[a-zA-Z0-9.-]+)/',$label,$matches); // @user@example.com // can have two . in domain!

	if (!count($matches)) return '<p><span class="error">The username <b>'.$label.'</b> is invalid. Please submit a complete username (eg @user@example.com) .</span>';

	$user = $matches[1];
	$host = $matches[2];

	$dict = array('label'=>$label, 'user'=>$user, 'host'=>$host); 
	
	// handle redirection
	// we check host-meta first
	
	$url = 	'https://'.$host.'/.well-known/host-meta';
	debugLog('<p><a href="'.$url.'">'.$url.'</a>');
	if ($s = getRemoteString($url))
	{
		debugLog('<p><tt>'.htmlspecialchars($s).'</tt>');
		preg_match('/template="https:\/\/(.*?)\/\.well-known\/webfinger/',$s,$matches);
		if (isset($matches[1])) $host = $dict['host'] = $matches[1];
	}
	
	// handle webfinger
	$url = 	'https://'.$host.'/.well-known/webfinger?resource='.substr($label,1); 
	debugLog('<p><a href="'.$url.'">'.$url.'</a>');
	if ($s = getRemoteString($url))
	{
		debugLog('<p><tt>'.htmlspecialchars($s).'</tt>');
		$j = json_decode($s,true);
		if (is_array($j) && isset($j['links']))
			foreach($j['links'] as $l )
			{
				if (@$l['rel']=='self') $link = @$l['href']; else continue;
				preg_match('/https:\/\/(.*?)\/users\/(.*?)$/',$link,$matches);
				if ($matches) {
					$host = $dict['host'] = $matches[1];
					$user = $dict['user'] = $matches[2];
				}
			}
		
	}	

	// we have a valid user, let's check if the profile is valid
	
	$err = crawl(array($dict));

	// check first if there was a profile
	if (substr($err,0,9) == 'ERROR_URL')
		return '<p><span class="error">I cannot find the profile page <b><a href="'.substr($err,10).'" target="_blank">'.substr($err,10).'</b></span>';

	
	if (!$magic = validUser($label, true)) return '<p><span class="error">Your fediverse profile is missing the magic word. Please proceed to step 1 first and then join again.</span>';
	
	
	debugLog('<p>Valid user. Going to index.');
	
	// we do not need follower count
	
	$fields = explode('::',$magic);
	$magic = $fields[0];
	$priority = time();
	
	// user has magix word in profile. we can add it to the db and index.
	
	$db = init();
	
	$sql = "BEGIN TRANSACTION; 
			DELETE FROM users WHERE label = '$label'; 
			REPLACE INTO users (user, host, label, priority) VALUES ('$user','$host','$label',$priority); 
			COMMIT;";
	
	if (!$db->exec($sql))
	{
		$db->close();
		return '<p><span class="error">Database was not available. Please try later.</span>';
	}
	
	$db->close();
	
	
	crawl(array(array('host'=>$host,'user'=>$user,'label'=>$label)));
	index($label);
	
	return '<p><span class="ok">Magic word <b>'.$magic.'</b> found. From now on, you are indexed.</span>';
	
}
	
function validUser($label, $refresh = false)
{
	global $tfRoot;
	debugLog('<p>validUser '.$label);
	
	
	preg_match('/@([a-zA-Z0-9_]+)@([a-zA-Z0-9_-]+\.[a-zA-Z0-9.-]+)/',$label,$matches); // @user@example.com // can have two . in domain!
	if (count($matches)<3) return false; 
	$host = $matches[2];
	$user = $matches[1];

	$localpath = $tfRoot.'/site/profiles/'.$label.'.json';
	
	if (file_exists($localpath))
	{
		$head = '';
		$followers = 0;
		$s = file_get_contents($localpath);
		debugLog('<p><tt>'.htmlspecialchars($s).'</tt>');
		$j = json_decode($s,true);
		
		//echo '*'; // print_r($j);
		
		if (isset($j['error']) || isset($j['errors'])) 
		{
				
				$localpath = $tfRoot.'/site/profiles/'.$label.'.html';
				debugLog('<p>'.$localpath);
				
				if (!file_exists($localpath) || time()-filemtime($localpath) > 3600 ||
				 !filesize($localpath) || filesize($localpath) < 300 || $refresh)
				{
					
					$url = 'https://'.$host.'/users/'.$user; 
					debugLog('<p><a href="'.$url.'">'.$url.'</a>');
					$s = getRemoteString($url);	
					
					if (strlen($s) < 100)
					{
						$url = 'https://'.$host.'/@'.$user; 
						$s = getRemoteString($url);	
					}
									
					file_put_contents($localpath,$s);				
				} 
				else
				{
					$s = file_get_contents($localpath);
				}
				
				debugLog('<p><tt>'.htmlspecialchars($s).'</tt>');
				
				$head = $s; // depending on instance, the magix word can be in the header or in the body
								
				// now we search also for followers
				// <meta content='1.27K Posts, 460 Following, 362 Followers · #...
		
				preg_match("/meta content='[0-9.K]+? Posts, [0-9.K]+ Following, ([0-9.K]+) Followers/",$head,$matches);
				
				$followers = 0;
				if (count($matches)) $followers = $matches[1];
				if (stristr($followers,'K')) $followers = 1000 * str_replace('K','',$followers);
				if (stristr($followers,'M')) $followers = 1000000 * str_replace('M','',$followers);
			//}
		}
		else
		{
			$bio = @$j['note'];
			$attachment = @$j['fields'];
			$head = json_encode($bio).json_encode($attachment);
			$followers = @$j['followers_count'];
		}

		
							
		if (stristr($head,'tootfinder')) return 'tootfinder::'.$followers;
		if (stristr($head,'tfr')) return 'tfr::'.$followers;
		if (stristr($head,'searchable')) return 'searchable::'.$followers;
		
		debugLog('<p>not valid');
	}
	
		
	return false;	
}

function randomUsers()
{
	
	$db = init(true); 
	
	$q = "SELECT user, host, label, id, priority FROM users WHERE priority > 0 ORDER BY priority LIMIT 50;";
	
	if (rand(0,100)> 90) 
		$q = "SELECT user, host, label, id, priority FROM users ORDER BY RANDOM() DESC LIMIT 50;";
	
	
	$list = $db->query($q);
	
	$result = array();
	
	while($d = $list->fetchArray(SQLITE3_ASSOC)) $result[] = $d;
	
	$db->close();
	
	return $result;
			
}
