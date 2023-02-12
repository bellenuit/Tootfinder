<?php
	
/**
 *	functions related to users
 * 
 * 
 *  @version 1.2 2023-02-12
 */
	
function addUser($label)
{
	// check the format of the user label
	
	$label = trim($label);
	if (!$label) return '<p><span class="error">The username field is empty. Please submit a username.</span>';
	
	preg_match('/@([a-zA-Z0-9_]+)@([a-zA-Z0-9_-]+\.[a-zA-Z0-9.-]+)/',$label,$matches); // @user@example.com // can have two . in domain!

	if (!count($matches)) return '<p><span class="error">The username <b>'.$label.'</b> is invalid. Please submit a complete username (eg @user@example.com) .</span>';

	$user = $matches[1];
	$host = $matches[2];

	$dict = array('label'=>$label, 'user'=>$user, 'host'=>$host); 

	// we have a valid user, let's check if the profile is valid
	
	$err = crawl(array($dict));

	// check first if there was a profile
	if (substr($err,0,9) == 'ERROR_URL')
		return '<p><span class="error">I cannot find the profile page <b><a href="'.substr($err,10).'" target="_blank">'.substr($err,10).'</b></span>';

	
	if (!$magic = validUser($label)) return '<p><span class="error">Your fediverse profile is missing the magic word. Please proceed to step 1 first and then join again.</span>';
	
	// we do not need follower count
	
	$fields = explode('::',$magic);
	$magic = $fields[0];
	
	// user has magix word in profile. we can add it to the db and index.
	
	$db = init();
	
	$sql = "BEGIN TRANSACTION; 
			DELETE FROM users WHERE label = '$label'; 
			REPLACE INTO users (user, host, label, priority) VALUES ('$user','$host','$label',100); 
			COMMIT;";
			
	if (!$db->exec($sql))
	{
		$db->close();
		return '<p><span class="error">Database was not available. Please try later.</span>';
	}
	
	$db->close();
	
	index();
	
	return '<p><span class="ok">Magic word <b>'.$magic.'</b> found. From now on, you are indexed.</span>';
	
}
	
function validUser($label)
{
	global $tfRoot;

	$localpath = $tfRoot.'/site/profiles/'.$label.'.html';
	
	if (file_exists($localpath))
	{
		$s = file_get_contents($localpath);
		$p1 = strpos($s,'<head>');
		$p2 = strpos($s,'</head>',$p1);
		$head = substr($s,$p1,$p2); 
	
		
		// now we search also for followers
		// <meta content='1.27K Posts, 460 Following, 362 Followers · #...
		
		preg_match("/meta content='[0-9.K]+? Posts, [0-9.K]+ Following, ([0-9.K]+) Followers/",$head,$matches);
		
		$followers = 0;
		if (count($matches)) $followers = $matches[1];
		if (stristr($followers,'K')) $followers = 1000 * str_replace('K','',$followers);
		if (stristr($followers,'M')) $followers = 1000000 * str_replace('M','',$followers);
		
		if (stristr($head,'tootfinder')) return 'tootfinder::'.$followers;
		if (stristr($head,'tfr')) return 'tfr::'.$followers;
	}
	else
	{
		// echo "no file";
	}
	
	// valid until february 19th
	global $oldusers;	
	if (stristr($oldusers,$label)) { return '::25';}
		
	return false;	
}

function randomUsers()
{
	
	$db = init(true);
	
	$q = "SELECT user, host, label, priority, priority + (random() % 50) as r FROM users ORDER BY r DESC LIMIT 10;";
	
	$list = $db->query($q);
	
	$result = array();
	
	while($d = $list->fetchArray(SQLITE3_ASSOC)) $result[] = $d;
	
	$db->close();
	
	return $result;
			
}
