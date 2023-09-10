<?php 
	
/**
 *	crawl function
 *  gets a batch of posting files from the instances and puts them in the feeds folder
 * 
 *  @version 2.2 2023-09-10
 */
	
if (!defined('CRAWLER')) die('invalid acces');

function crawl($usr='')
{
	
	$start = time();
	
	global $tfRoot;
	$verbose = '';
	$shoppinglist = array();
	$pc = 0;
	$fc = 0;
	
	if ($usr) 
	{
		$list = array($usr);
		$forcerefresh = true;
	}
	else
		$list = randomUsers();
		
	$journal = array();
	$journal []= 'BEGIN TRANSACTION; ';
		
	foreach($list as $label)
	{
		debugLog('<p>crawl '.$label);
		
		$pc++;
		
		$profile = getProfile($label, false && rand(0,100)> 95); // check profile systematically every 20 crawls
		
		if (!count($profile)) 
		{ debugLog('<p>crawl empty profile '.$label); 
			
			// common error
			if (strstr($label,'https://') or strstr($label,'http://'))
				$journal []= "DELETE FROM users WHERE label = '$label'; ";
			else
				$priority = time()+86400;
				$journal []= "UPDATE users SET priority = $priority WHERE label = '".$label."' ; ";		
			
		    continue; }
		
		$localpath = $tfRoot.'/site/feeds/'.$label.'.json';
		
		$url = getOutboxLink($profile);
		
		debugLog(' url <a href="'.$url.'">'.$url.'</a>');

		if ($url) 
		{
			if (substr($url,-4,4) == '.rss') $localpath = $tfRoot.'/site/feeds/'.$profile['label'].'.rss';
			$shoppinglist[$url]=$localpath;
			if (!file_exists($localpath)) touch($localpath);
			$fc++;
			
		}		
	}
	
	$journal []= 'COMMIT; ';
	$sql = join(PHP_EOL,$journal);
	if (count($journal)>2)
	{
		$db = init();
		if ($db)
		{
			@$db->exec($sql);
			$db->close();
		}
	}
	
	$ende = time();
		
	
	debugLog('<p><b>crawl profiles: '.$pc. ' feeds: '.$fc.'</b>');	
	debugLog(sprintf(' (%0d sec)',$ende - $start));
	
	getRemoteFiles($shoppinglist);
	
}

