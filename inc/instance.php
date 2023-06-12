<?php
	
/**
 *	functions related to instance opt-in
 * 
 *
 *  @version 2.1 2023-06-12
 */	
 

function addInstance($host)
{
	debugLog('<p>addInstance '.$host);
	
	// check the format of the user label
	
	$host = trim($host);
	if (!$host) return '<p><span class="error">The instance field is empty. Please submit a username.</span>';
	
	preg_match('/[a-zA-Z0-9_-]+\.[a-zA-Z0-9.-]+/',$host,$matches); // @example.com // can have two . in domain! 

	if (!count($matches)) return '<p><span class="error">The instance domain <b>'.$host.'</b> is invalid. Please submit a complete domain (eg example.com) .</span>';
	
	// check first if there was a rules
	$magic = validInstance($host, true);	   
			
	if ($magic === false || stristr($magic,'error')) 
	{
		$sql = "BEGIN TRANSACTION;
			DELETE FROM instances WHERE host = '$host'; 
			COMMIT;";  // echo $sql;  
		$db = init();
		if ($db)
		{	
			if (!@$db->exec($sql)) return '<p><span class="error">Database error '.$db->lastErrorMsg().'</span>';
			$db->close();
		}
		else
		{
			
			return '<p><span class="error">Database was not available. Please try later.</span>';
		}	

	
	 return '<p><span class="error">Your instance rules are missing the magic sentence. Please update the instance rules first and then join again.</span>';
	 
	 
	}
	
	debugLog('<p>Valid instance. Going to add users. Magic: '.$magic);
		
	
	$sql = "BEGIN TRANSACTION; 
			DELETE FROM instances WHERE host = '$host'; 
			REPLACE INTO instances (host) VALUES ('$host');  
			COMMIT;";
	$db = init();
	if ($db)
	{
		if (!@$db->exec($sql))
		{
			$db->close();
			return '<p><span class="error">Your instance is ot but the database was not available. Please try later.</span>';
		}	
		$db->close();
	}
	
	return '<p><span class="ok">Magic sentence <b>'.$magic.'</b> found. From now on, the instance is indexed and listed below.</span>';
	
}

function validInstance($host, $forcerefresh=false)
{
	$rules = getInstanceRules($host, $forcerefresh);
	if (!count($rules)) return '<p><span class="error">I cannot find the rules of the instance <b>'.$host.'</b></span>';
	$s = json_encode($rules);
	$validrules = array('Public posts from this instance are indexed by tootfinder.ch');   
	foreach($validrules as $v) 
	
	if (stristr($s,$v)) 
	{
		addInstanceUsers($host);
		
		return $v;
	}
	
		
	debugLog('<h4>Rules</h4>'); 	 
	debugLog(expandableSnippet($s));
		
	return false;	
}

function randomInstance()
{
	
	$q = "SELECT host FROM instances ORDER BY RANDOM() LIMIT 1;";
		
	$db = init(true);
	if ($db)
	{
		$list = $db->query($q);
		$result = array();
		while($d = $list->fetchArray(SQLITE3_ASSOC)) $result[] = $d['host'];
		$db->close();
	}
	
	if (count($result)) return $result[0];
			
}


function getInstanceRules($host, $forcerefresh=false)
{
	
	global $tfRoot;
	
	$localpath = $tfRoot.'/site/instancerules/'.$host.'.json'; 
	
	if ($forcerefresh || !file_exists($localpath) || time()-filemtime($localpath) > 3600*24 || filesize($localpath) < 50 )
	{
		$host = getHostMeta($host, $forcerefresh);
		
		$url = $host.'/api/v1/instance/rules';
		
		$s = getRemoteString($url,$localpath);
		file_put_contents($localpath,$s);
		
		
	}
	else
		$s = file_get_contents($localpath);
		
	if ($s) $rules = json_decode($s,true);
	
	if (isset($rules['error'])) 
	{
		unset($rules);
		@unlink($localpath);
	}
	
	
		
	if (isset($rules))return $rules; else return array();

}

function getInstanceUsers($host, $forcerefresh=false, $offset=0)
{
	
	
	
	global $tfRoot;
	
	$localpath = $tfRoot.'/site/instanceusers/'.$host.'-'.$offset.'.json';
	
	if ($forcerefresh || !file_exists($localpath) || time()-filemtime($localpath) > 3600*24 || filesize($localpath) < 50 )
	{
		$host = getHostMeta($host, $forcerefresh);
		
		$url = $host.'/api/v1/directory?local=1&order=new&offset='.$offset;
		
		$s = getRemoteString($url,$localpath);
		file_put_contents($localpath,$s);
	}
	else
		$s = file_get_contents($localpath);
		
	if ($s) $users = json_decode($s,true);
	
	if (isset($users['error'])) 
	{
		unset($users);
		@unlink($localpath);
	}
	if(isset($users)) return $users;	
}

function addInstanceUsers($host)
{	
	
	DebugLog('<h4>Add instance users</h4>');
	// get all current instance users
	$currentusers = array();
	
	$q = "SELECT user FROM users WHERE host = '$host';";	
	$db = init(true);
	if ($db)
	{
		$list = $db->query($q);
		
		while($d = $list->fetchArray(SQLITE3_ASSOC)) $currentusers[$d['user']] = 1;
		$db->close();
	}		
	
	$sql = array("BEGIN TRANSACTION;");
	
	$found = true;
	$offset = 0;
	while ($found)
	{
		$users = getInstanceUsers($host,false,$offset);
				
		$found = false;
		foreach($users as $elem)
		{			
			$user = $elem['username']; debugLog('<p>'.$user);
			if (!array_key_exists($user,$currentusers))
			{
				debugLog(' added');
				
				$priority = time();
				$label = '@'.$user.'@'.$host;
				$host = $host;
				
				$sql [] = "INSERT INTO users (user, host, label, priority) VALUES ('$user','$host','$label',$priority);" ; 
				$found = true;	
			}
			else
			{
				debugLog(' existing');
			}
		}
		$offset +=40;
	}
	
	$sql []= "COMMIT;"; 
	$sql = join(PHP_EOL,$sql);
	
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
	

}


function instanceList()
{
	
	
	$q = "SELECT host FROM instances ORDER BY host"; 
	
	$result = array();
	$db = init(true);
	if ($db)
	{
		$list = $db->query($q);
		while($d = $list->fetchArray(SQLITE3_ASSOC)) $result[] = '<a href="https://'.$d['host'].'" target="_blank">'.$d['host'].'</a><br>';
		$db->close();
	}
	
	if (count($result)) return join(PHP_EOL,$result); else return "-";  
	
}

