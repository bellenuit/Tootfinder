<?php
	
/**
 *	functions related to users
 * 
 *
 *  @version 2.1 2023-06-12
 */
	
function addUser($label)
{
	debugLog('<p>addUser '.$label);
	
	// check the format of the user label
	
	$label = trim($label);
	if (!$label) return '<p><span class="error">The username field is empty. Please submit a username.</span>';
	
	preg_match('/@([a-zA-Z0-9_]+)@([a-zA-Z0-9_-]+\.[a-zA-Z0-9.-]+)/',$label,$matches); // @user@example.com // can have two . in domain!

	if (!count($matches)) return '<p><span class="error">The username <b>'.$label.'</b> is invalid. Please submit a complete username (eg @user@example.com) .</span>';

	$profile = getProfile($label, true);
	getOutboxLink($profile, $label, true); 
	
	// check first if there was a profile
	if (!count($profile)) return '<p><span class="error">I cannot find the profile page</b></span>';	
			
	if (!$magic = validUser($profile))  return '<p><span class="error">Your fediverse profile is missing the magic word. Please proceed to step 1 first and then join again.</span>';
	
	debugLog('<p>Valid user. Going to index. Magic '.$magic);
	
	
	// user has magic word in profile. we can add it to the db and index.
	$user = $profile['user'];
	$host = $profile['host'];
	$priority = time();
	
	
	$sql = "BEGIN TRANSACTION; 
			DELETE FROM users WHERE label = '$label'; 
			REPLACE INTO users (user, host, label, priority) VALUES ('$user','$host','$label',$priority); 
			COMMIT;";
	$db = init();
	if ($db)
	{
		if (!@$db->exec($sql))
		{
			$db->close();
			return '<p><span class="error">Database was not available. Please try later.</span>';
		}	
		$db->close();
	}
	
	crawl($label);
	index($label);
	
	return '<p><span class="ok">Magic word <b>'.$magic.'</b> found. From now on, you are indexed.</span>';
	
}
	
function validUser($profile)
{
	// ActivityPub profile
	$bio = @$profile['summary'];
	$attachment = @$profile['attachment'];
	$head1 = json_encode($bio).json_encode($attachment);
	
	// Mastodon profile
	$bio = @$profile['note'];
	$attachment = @$profile['fields'];
	$head2 = json_encode($bio).json_encode($attachment);
	
	$head = $head1.$head2;
	
	

	if (stristr($head,'tootfinder')) return 'tootfinder';
	if (stristr($head,'tfr')) return 'tfr';
	if (stristr($head,'searchable')) return 'searchable';
	
	$test = validInstance($profile['host']);
	
	if ($test !== false)
	{ 
		if (stristr($head,'noindex')) return false;
		
		return 'instance opt-in '.$profile['host'];
	}
	
		
	debugLog(expandableSnippet($head));
		
	return false;	
}

function randomUsers()
{
	
	$q = "SELECT user, host, label, id, priority FROM users WHERE priority > 0 ORDER BY priority LIMIT 20;";
	
	if (rand(0,100)> 90) 
		$q = "SELECT user, host, label, id, priority FROM users ORDER BY RANDOM() DESC LIMIT 20;";
	
	$db = init(true);
	if ($db)
	{
		$list = $db->query($q);
		$result = array();
		while($d = $list->fetchArray(SQLITE3_ASSOC)) $result[] = $d['label'];
		$db->close();
	}
	
	return $result;
			
}

function getHostMeta($host, $forcerefresh=false)
{
	
	global $tfRoot;
	$url = 	'https://'.$host.'/.well-known/host-meta';
	$localpath = $tfRoot.'/site/hostmeta/'.$host.'.xml';
	
	if ($forcerefresh || !file_exists($localpath) || time()-filemtime($localpath) > 3600*24*7 || filesize($localpath) < 50 )
	{
		debugLog('<p><a href="'.$url.'">'.$url.'</a>');
		$s = getRemoteString($url);
		file_put_contents($localpath,$s);
		debugLog(expandableSnippet($s));
	}
	else
		$s = file_get_contents($localpath);

	
	preg_match('/template="https:\/\/(.*?)\/\.well-known\/webfinger/',$s,$matches);
	if (isset($matches[1])) $host = $matches[1];
	
	debugLog(' host-meta '.$host);
	
	return $host;
}

function getWebfinger($host, $label, $forcerefresh=false)
{
	$host = getHostMeta($host, $forcerefresh);
	
	global $tfRoot;
	$url = 	'https://'.$host.'/.well-known/webfinger?resource='.substr($label,1); // remove first @
	$localpath = $tfRoot.'/site/webfinger/'.$label.'.json';
	
	
	
	if ($forcerefresh || !file_exists($localpath) || time()-filemtime($localpath) > 3600*24*7 || filesize($localpath) < 50 )
	{
		debugLog('<p><a href="'.$url.'">'.$url.'</a>');
		$s = getRemoteString($url);
		file_put_contents($localpath,$s);
		debugLog(expandableSnippet($s));
	}
	else
		$s = file_get_contents($localpath);
		
	
	$dict = array();
	$j = json_decode($s,true);
	if (is_array($j) && isset($j['links']))
	{
		foreach($j['links'] as $l )
		{
			if (@$l['rel']=='self') $link = @$l['href']; else continue;
			preg_match('/https:\/\/(.*?)\/users\/(.*?)$/',$link,$matches);
			if ($matches) {
				$dict['host'] = $matches[1];
				$dict['user'] = $matches[2];
				$dict['link'] = $link;
				$dict['label'] = $label;
			}
		}
	}
	else
	{
		// fallback to host/user/user
		$dict['host']  = $host;
		$dict['label'] = $label;
		preg_match('/@([a-zA-Z0-9_]+)@([a-zA-Z0-9_-]+\.[a-zA-Z0-9.-]+)/',$label,$matches); // @user@example.com // can have two . in domain!
		$dict['user']  = @$matches[1];
		$dict['link'] = 'https://'.$host.'/users/'.$dict['user'];
		
	}
	debugLog(' webfinger '.$label);
	return $dict;	
}

function getProfile($label, $forcerefresh=false)
{
	
	preg_match('/@([a-zA-Z0-9_]+)@([a-zA-Z0-9_-]+\.[a-zA-Z0-9.-]+)/',$label,$matches); // @user@example.com // can have two . in domain!
	if (count($matches)<3) return array();  // error
	
	$user = $matches[1];
	$host = $matches[2];

	global $tfRoot;
	
	$localpath = $tfRoot.'/site/profiles/'.$label.'.json';
	
	if ($forcerefresh || !file_exists($localpath) || time()-filemtime($localpath) > 3600*24 || filesize($localpath) < 50 )
	{
		$dict = getWebfinger($host, $label, $forcerefresh);
		if (isset($dict['link']))
		{
			$url = 	$dict['link'];
			debugLog('<p><a href="'.$url.'">'.$url.'</a>');
			$s = getRemoteString($url,$localpath);
			file_put_contents($localpath,$s);
			debugLog(expandableSnippet($s));
			
			$j = json_decode($s,true);
			if (!isset($j['error'])) 			
			{
				$profile['followercount'] = 20;
				$profile['format'] = 'json';
				$profile['label'] = $label;
				$profile['user'] = $user;
				$profile['host'] = $host;
				$profile['summary'] = @$j['summary'];
				$profile['attachment'] = @json_encode($j['attachment']);
				$profile['outbox'] = @$j['outbox'];
				
				if (@$j['icon'])
				{
					$profile['avatar'] = @$j['icon']['url']; 

				}
				
				if (isset($j['followers']))
				{
					$url2 = $j['followers'];
					$s2 = getRemoteString($url2,'.json');
					$j2 = json_decode($s2,true);
					if (isset($j2['totalItems']))
						$profile['followercount'] = $j2['totalItems'];
				}
				
				// get Mastodon ID
				$url = 'https://'.$profile['host'].'/api/v1/accounts/lookup?acct='.$profile['label']; 
				$s = getRemoteString($url,'.json');  
				$j = json_decode($s,true);
				$id = @$j['id'];
				
				if ($id)
				{
					$profile['id'] = $id;
				}
				else
				{
					$profile['id'] = '';
				}

				
				$s = json_encode($profile);
				file_put_contents($localpath,$s);
				
			}
			
		}
		else 
		{
			$s = '';
		}
	}
	else
		$s = file_get_contents($localpath);
		
	if ($s) $profile = json_decode($s,true);
	
	if (isset($profile['error'])) 
	{
		unset($profile);
		@unlink($localpath);
	}
	
	if (!isset($profile))
	{
		// fallback HTML
		if (!isset($dict)) $dict = getWebfinger($host, $label, $forcerefresh);
		
		$url = $dict['link'];
		
		$s = getRemoteString($url,'.html');
		debugLog(expandableSnippet($s));
		
		// now we search also for followers
		// <meta content='1.27K Posts, 460 Following, 362 Followers · #...
	
		preg_match("/meta content='[0-9.K]+? Posts, [0-9.K]+ Following, ([0-9.K]+) Followers/",$s,$matches);
			
		$followers = 0;
		if (count($matches)) $followers = $matches[1];
		if (stristr($followers,'K')) $followers = 1000 * str_replace('K','',$followers);
		if (stristr($followers,'M')) $followers = 1000000 * str_replace('M','',$followers);

		
		$profile['summary'] = $s;
		$profile['attachment'] = '';
		$profile['followercount'] = $followers;
		$profile['format'] = 'html';
		$profile['label'] = $label;
		$profile['user'] = $user;
		$profile['host'] = $host;
		$profile['id'] = '';
		$profile['outbox'] = '';
		
		preg_match_all("/<meta(.*)>/i",$s,$matches);
		foreach($matches[1] as $m)
		{
			preg_match("/content=['\"](.*)['\"].+property=['\"]og:image['\"]/",$m, $matches2);
			if (@$matches2[1])
			{
				$profile['avatar'] = $matches2[1]; 
			}
		}
		
		
		// we create a json profile 
		$s = json_encode($profile);
		$localpath = $tfRoot.'/site/profiles/'.$label.'.json';
		file_put_contents($localpath,$s);

		
	}
	
	return $profile;	

}

function getOutboxLink($profile, $forcerefresh=false)
{	
	global $tfRoot;
	
	// we prefer Mastodon API because the feed is richer than fediverse
	
	if ($profile['id']) return 'https://'.$profile['host'].'/api/v1/accounts/'.$profile['id'].'/statuses/?limit=40';
		
	if ($profile['outbox']) // fediverse standard
	{
		
		$url = $profile['outbox'];
		//print_r($profile);
		$localpath = $tfRoot.'/site/outbox/'.$profile['label'].'.json';
		
		
		
		if ($forcerefresh || !file_exists($localpath) || time()-filemtime($localpath) > 3600*24*7 || filesize($localpath) < 50 )
		{
			debugLog('<p><a href="'.$url.'">'.$url.'</a>');
			$s = getRemoteString($url);
			file_put_contents($localpath,$s);
			debugLog(expandableSnippet($s));
		}
		else
			$s = file_get_contents($localpath);
		
		$dict = array();
		$j = json_decode($s,true);
		if (is_array($j) && isset($j['first'])) return $j['first'];		

	}
	// RSS fallback

	
	return 'https://'.$profile['host'].'/users/'.$profile['user'].'.rss';	

}

