<?php
	
/**
 *	index functions
 *  reads the files in the feeds folder and indexes them
 *  index check if the file has changes (comparing with bak), if the user is valid
 *  if valid, it chooses a function to read and then adds the posts to the database
 *  if not valid, it checks if the user still has posts, if not the user is deleted (14 days)
 *  files are added and moved to the bak or the reject folder
 * 
 *  @version 1.9 2023-03-17
 */
	
function index($usr = '')
{
	global $tfRoot;		
	
	
	if ($usr) debugLog('<p><b>index '.$usr.'</b>');
	
    
   
	if ($usr) $files = glob($tfRoot.'/site/feeds/'.$usr.'.*');
	else	$files = glob($tfRoot.'/site/feeds/*.*');
	
	$okfiles = array();
	
	$labels = array();
	$deletelist = array();
	$fc = 0;
	$pc = 0;
	$uc = 0;
	$prioritystring = '';
	
	$journal = array();
	$journal []= 'BEGIN TRANSACTION; ';

	
	foreach($files as $file)
	{			
		debugLog('<p>file '.basename($file) );
		
		$fc++;
		$format = '';
		if (substr(basename($file),-5)=='.json')
		{
			 $format = 'json';
			 $label = preg_replace('/\.json$/','',basename($file)); 
		}
		if (substr(basename($file),-4)=='.rss')
		{
			$format = 'rss';
			$label = preg_replace('/\.rss$/','',basename($file)); 
		}
		if (!$format) continue;
		
		preg_match('/@([a-zA-Z0-9_]+)@([a-zA-Z0-9_-]+\.[a-zA-Z0-9.-_]+)/',$label,$matches); // @user@example.com // can have two . in domain!
		if (count($matches)<3) continue;
		$host = $matches[2];
		$user = $matches[1];
						
		$bak = $tfRoot.'/site/bak/'.basename($file);
		if (file_exists($bak))
		{
			if (@filesize($file) == @filesize($bak)) // did not change
			{
				$uc++;
				$priority = time()+86400;
				$journal []= "UPDATE users SET priority = $priority WHERE label = '".$label."' ; ";
				// delete feed file
				@unlink($file);
				// delete bak file
				@unlink($tfRoot.'/site/bak/'.$label.'.json');
				@unlink($tfRoot.'/site/bak/'.$label.'.rss');
			}
			
		}
	
		$profile = getProfile($label);
		$followers = $profile['followercount'];
		$magic = validUser($profile);
		
		if (!@$magic) 
		{
			debugLog('<p>Invalid user: <a href="https://'.$host.'/users/'.$user.'" target="_blank">'.$label.'</a>');
			// we do not delete the user immediately, because the profile page may also have been on error.
			// we wait until there is no new post

			$sql = "SELECT count(link) as c FROM posts where user = '$label';";
			$db2 = init(true);
			if ($db2)
			{
				$up = $db2->querySingle($sql);
				$db2->close();
			}
			if (!$up) 
			{
				$journal []= "DELETE FROM users WHERE label = '$label'; ";
				@unlink($file);
			}
			
			@rename($file,$tfRoot.'/site/rejected/invaliduser-'.basename($file));
			$priority = time()+86400;
			$journal []= "UPDATE users SET priority = $priority WHERE label = '".$label."' ; ";
			debugLog(' invaliduser '); continue;
			continue;
		}
	
		
				
		if (!file_exists($file)) 
		{
			debugLog(' nofile '); continue;
				
		}
		$s = file_get_contents($file);	
		debugLog(' read '.$format);
		
		if (!$s)
		{
			if ($usr) debugLog('<b>rejected</b>: '.basename($file));
			
			@rename($file,$tfRoot.'/site/rejected/empty-'.basename($file));
			$priority = time()+86400;
			$journal []= "UPDATE users SET priority = $priority WHERE label = '".$label."' ; ";
			debugLog(' empty ');
			continue;
		}
		
		if ($usr) debugLog(expandableSnippet($s));
		
		if ($format == 'json') $feed = readJSONfeed($s,$label, $host, $user,$file);
		else 
		{
			$feed = readRSSFeed($s,$file);
			if (!count($feed))
			{
				// it didn't work. It may be that it was a gotosocial site, so we try the other url
				// it's a hack using ressources. should we add the URL to the database?
				$localpath = $tfRoot.'/site/feeds/'.$label.'.rss';
				$url = 'https://'.$host.'/@'.$user.'/feed.rss';
				$shoppinglist = array($url=>$localpath);
				getRemoteFiles($shoppinglist);
				
				if ($usr)
				{	
					$s = getRemoteString($url);
					file_put_contents($localpath,$s);
					$feed = readRSSFeed($s,$file);
				
					debugLog('<p>Trying other URL '.$url.' '.count($feed)); 
					debugLog(expandableSnippet(json_encode($feed)));
					
				}
				
			}
		}
			
		debugLog(' '.count($feed));
				
		$generalfound = 0;
		$oldposts = 0;

		
		$minpubdate = 91676409379;
		$maxpubdate = 0;
		$postcount = 0;
		
		// we get a list of current posts. if some more recent than the oldest post may have disappeared, we can discard them
		// we cannot take link as orderes, because some instances do use md5 as id.
		// so we use pubdate and assume that second is unique for users that post
		
		
		
		$sql = "SELECT pubdate, link FROM posts where user = '$label';";
		$db2 = init(true);
		
		if ($db2)
		{
			$res = $db2->query($sql);
			$currentlinks = array();
			while ($d = $res->fetchArray(SQLITE3_ASSOC)) 
			{
				$currentlinks[$d['link']] = $d['pubdate'];
			}
			$db2->close();
		}
		
		// we get the oldest post
		
		$oldestid = false;
		if (count($feed))
		{
			$post = end($feed);
			$oldestid = $post['pubdate'];
			
			foreach($currentlinks as $k=>$v)
			{
				if ($v < $oldestid) unset($currentlinks[$k]);
			}
		}
		else
		{
			// we ignore
			$currentlinks = array();
		}
		$k=0;
		foreach($feed as $post)
		{
			debugLog(' <a href="'.@$post['link'].'" target="_blank">'.$k.'</a>'); $k++;
			
			$postcount++;
			$found = false;
			
			$link = SQLite3::escapeString($post['link']);
			$edited = @$post['edited_at'];
			$minpubdate = min($minpubdate,$post['pd']);
			$maxpubdate = max($maxpubdate,$post['pd']);
			$pubdate = $post['pubdate'];
			$datelimit = date('Y-m-d',strtotime('-14 day', time()));
			if ($pubdate < $datelimit) continue; // too old
			$indexdate = date('Y-m-d H:i:s');
			
			// if the post exists and is not edited, we go no further
			if (array_key_exists($link,$currentlinks))
			{
				if ($edited)
				{
					$link = $currentlinks[$pubdate];
					$journal []= "DELETE FROM posts WHERE link = '$link' ;";
				}
				else
				{
					$found = true;
					$oldposts++;
				}				
			}
			unset($currentlinks[$link]);
			
			$avatar = SQLite3::escapeString($post['avatar']);
			
			
			$description = handleMentions($post['description']);	
			
			$description = encodeSpacelessLanguage($description);
			
			if(is_array($post['medias']))
				$media = join('::',$post['medias']);
			else
				$media = '';
			$media = encodeSpacelessLanguage($media);
				
			$soundex = SQLite3::escapeString(soundexLong($description.' '.$label.' '.$media.' '));
			$media = SQLite3::escapeString($media);
			$description = SQLite3::escapeString($description);	
			
			

			$db2->close();
			$sql = "";			
			if (!$found || true)  // we think the posts are immutable
			{	
				$sql = $journal []= "INSERT INTO posts (link, user, description, pubdate, image, media, soundex, followers, indexdate) VALUES ('".$link."','".$label."','".$description."','".$pubdate."','".$avatar."','".$media."','".$soundex."', ".max(intval($followers),1).",'".$indexdate."'); ";			
				$generalfound++;
				$pc++;
			}
			
		}
		
		// remove posts that have been deleted.
		foreach($currentlinks as $k=>$v)
		{
			$localpath = $tfRoot.'/site/deleted/'.bin2hex($k);
			$deletelist[$k] = $localpath;
			
		}	
				
		// we calculated when the next post is likely to happen, but we wait at most 1 day
		$period = round(($maxpubdate - $minpubdate ) / ($postcount+1));
		if (!$generalfound) $period *= 2;
		if ($postcount == 0) $period = 86400;
		if ($postcount == 1) $period = time() - $maxpubdate;
		if (!$generalfound) $period *= 2;
		$period *= 2; // safety, if we ask too early we risk not to have a message
		$period = min($period,86400);
		$period = max($period,300);
		$priority = time() + $period;
		$journal []= "UPDATE users SET priority = $priority WHERE label = '".$label."' ; ";
		
		$okfiles []= $file;
	
	} 
	
	$journal []= 'COMMIT; ';
	
	$limit = date('Y-m-d',strtotime('-14 day', time()));
	if (rand(0,100)>-98) $journal []= "DELETE FROM posts WHERE pubdate < '".$limit."'; "; 
	
	// remove all deleted files
	$files = glob($tfRoot.'/site/deleted/*');
	foreach($files as $file)
	{
		$link = hex2bin(basename($file));
		if (@filesize($file)<2000)
		{
			$journal []= "DELETE FROM posts WHERE link = '$link' ;";
			debugLog('<p><b>deleted post</b> <a href="'.$link.'" target="_blank">'.$link.'</a>');
		}
		@unlink($file);
	}
	
		
	$q = join(PHP_EOL,$journal);		
	$db = init();
	if ($db)
	{
		if (!@$db->exec($q))
		{
			debugLog('<b>index error</b> '.$db->lastErrorMsg().' '.$q);
			return;
		}
		$db->close();
	}
	
	foreach($okfiles as $file)
	{
		$bak = str_replace('/site/feeds/','/site/bak/',$file);
		if (file_exists($bak)) @unlink($bak);
		@rename($file,$bak);
	}
	
	debugLog('<p><b>index feeds: '.$fc. ' Unchanged: '.$uc. ' Posts: '.$pc.'</b>');	
	
	getRemoteFiles($deletelist);	
	
}
