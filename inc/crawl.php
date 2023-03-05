<?php 
	
/**
 *	crawl and index functions
 * 
 *  @version 1.7 2023-03-05
 */
	
if (!defined('CRAWLER')) die('invalid acces');

function crawl($usr=array())
{
	
	global $tfRoot;
	$verbose = '';
	$forcerefresh = false;
	$pc = 0;
	$fc = 0;
	
	if (count($usr)) 
	{
		$list = $usr;
		debugLog('<p>crawl '.@$usr[0]['label']);
		$forcerefresh = true;
	}
	else
		$list = randomUsers();
		
	$journal = array();
	$journal []= 'BEGIN TRANSACTION; ';
		
	foreach($list as $user)
	{
		// profile
		$label = $user['label'];
		
		$localpath = $tfRoot.'/site/profiles/'.$user['label'].'.json';
		debugLog('<p>'.$localpath);		
		
		if ($forcerefresh || !file_exists($localpath) || time()-filemtime($localpath) > 3600 || filesize($localpath) < 300 )
		{			
			// public API seems to work and returns note (bio), fields (label) and id
			
			$url = 'https://'.$user['host'].'/api/v1/accounts/lookup?acct='.$user['label']; 
			debugLog('<p><a href="'.$url.'">'.$url.'</a>');
			
			$shoppinglist[$url]=$localpath;	
			
			if (!file_exists($localpath)) touch($localpath);
		}
		$id = @$user['id'];
		if (!$id)
		{
			$s = file_get_contents($localpath);
			if ($s)
			{
				$j = json_decode($s, true);

				if (@$j['id'])
				{
					$id = $user['id'] = $j['id'];

					$journal [] = "UPDATE users SET id = $id WHERE label = '$label'; ";
					
				}
				
			}
		}
		
		$localpath = $tfRoot.'/site/feeds/'.$user['label'].'.json';

		
		if ($id)
		{
			$url = 'https://'.$user['host'].'/api/v1/accounts/'.$id.'/statuses/?limit=40';
			// we try to get as most posts (max 40), knowing that the feed contains also non public posts.	
	
			$shoppinglist[$url]=$localpath;			
		}
		else
		{
			$localpath = $tfRoot.'/site/feeds/'.$user['label'].'.rss';
			$url = 'https://'.$user['host'].'/users/'.$user['user'].'.rss';
			$shoppinglist[$url]=$localpath;
			
		}
		debugLog('<p><a href="'.$url.'">'.$url.'</a>');
		
		if (!file_exists($localpath)) touch($localpath);
		$fc++;
			
	}
	
	$journal []= 'COMMIT; ';
	$sql = join(PHP_EOL,$journal);
	if (count($journal)>2)
	{
		$db = init();
		$db->exec($sql);
	}
	
	$verbose .= '<p>Crawl Profiles: '.$pc;
	$verbose .= ' Feeds: '.$fc;	
	
	getRemoteFiles($shoppinglist);
	
	return $verbose;
	
}

function index($usr = '')
{
	global $tfRoot;	
	
	if ($usr) debugLog('<p>Indexing: '.$usr);
	
    
    $journal = array();
	$journal []= 'BEGIN TRANSACTION; ';
	
	// clean old posts
	$limit = date('Y-m-d',strtotime('-14 day', time()));
	
	if (rand(0,100)>98) $journal []= "DELETE FROM posts WHERE pubdate < '".$limit."'; "; 

	if ($usr) 
		$files = array($tfRoot.'/site/feeds/'.$usr.'.json');
	else
		$files = glob($tfRoot.'/site/feeds/*.*');
	$okfiles = array();
	
	$labels = array();
	$deletelist = array();
	$verbose = '';
	$pc = 0;
	$uc = 0;
	$prioritystring = '';
	
	foreach($files as $file)
	{			
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
				// delete profile files
				@unlink($tfRoot.'/site/profile/'.$label.'.json');
				@unlink($tfRoot.'/site/profile/'.$label.'.html');
			}
			
		}
	
		
		$magic = validUser($label);
		
		if (!$magic) 
		{
			$verbose .=  '<p>Invalid user: <a href="https://'.$host.'/users/'.$user.'" target="_blank">'.$label.'</a>';
			// we do not delete the user immediately, because the profile page may also have been on error.
			// we wait until there is no new post

			$db2 = init(true);
			$sql = "SELECT count(link) as c FROM posts where user = '$label';";
			$up = $db2->querySingle($sql);
			if (!$up) 
			{
				$journal []= "DELETE FROM users WHERE label = '$label'; ";
				@unlink($file);
			}
			$db2->close();
			@rename($file,$tfRoot.'/site/rejected/invaliduser-'.basename($file));
			$priority = time()+86400;
			$sql = $journal []= "UPDATE users SET priority = $priority WHERE label = '".$label."' ; ";
			continue;
		}
		
		if ($usr) debugLog('<p>magic');
		
		// we do need follower count
		$fields = explode('::',$magic);
		$followers = @$fields[1];
				
		if (!file_exists($file)) 
		{
			if ($usr) debugLog('<p>no file');
			continue;		
		}
		$s = file_get_contents($file);	
		
		if (!$s)
		{
			if ($usr) debugLog('<p>Rejected: '.basename($file));
			
			rename($file,$tfRoot.'/site/rejected/empty-'.basename($file));
			$priority = time()+86400;
			$journal []= "UPDATE users SET priority = $priority WHERE label = '".$label."' ; ";

			continue;
		}
		
		if ($usr) debugLog(expandableSnippet($s));
		
		if ($format == 'json') $feed = readJSONfeed($s,$label, $host, $user,$file);
		else $feed = readRSSFeed($s,$file);
			
		
				
		$generalfound = 0;
		$oldposts = 0;

		
		$minpubdate = 91676409379;
		$maxpubdate = 0;
		$postcount = 0;
		
		// we get a list of current posts. if some more recent than the oldest post may have disappeared, we can discard them
		// we cannot take link as orderes, because some instances do use md5 as id.
		// so we use pubdate and assume that second is unique for users that post
		
		$db2 = init(true);
		$sql = "SELECT pubdate, link FROM posts where user = '$label';";
		$res = $db2->query($sql);
		$currentlinks = array();
		while ($d = $res->fetchArray(SQLITE3_ASSOC)) 
		{
			$currentlinks[$d['link']] = $d['pubdate'];
		}
		$db2->close();
		
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

		foreach($feed as $post)
		{
			$postcount++;
			$found = false;
			
			$link = SQLite3::escapeString($post['link']);
			$edited = @$post['edited_at'];
			$minpubdate = min($minpubdate,$post['pd']);
			$maxpubdate = max($maxpubdate,$post['pd']);
			$pubdate = $post['pubdate'];
			$indexdate = date('Y-m-d H:i:s');
			
			// if the post exists and is not edited, we go no further
			if (array_key_exists($link,$currentlinks))
			{
				// $verbose .= '<p>Found post <a href="'.$link.'" target="_blank">'.$link.'</a>';
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
			if (!$found && $pubdate > $limit)  // we think the posts are immutable
			{
				
				$sql = $journal []= "INSERT INTO posts (link, user, description, pubdate, image, media, soundex, followers, indexdate) VALUES ('".$link."','".$label."','".$description."','".$pubdate."','".$avatar."','".$media."','".$soundex."','".$followers."','".$indexdate."'); ";			
				$generalfound++;
				$pc++;
			}
			
		}
		
		// remove posts that have been deleted.
		foreach($currentlinks as $k=>$v)
		{
			$localpath = $tfRoot.'/site/deleted/'.bin2hex($k);
			$deletelist[$k] = $localpath;
			
			/*
			// delete candidates
			// we check the status, if it returns error, we will delete it -- expensive !
			$s = getRemoteString($k);	
			
			if (strlen($s)<2000) // probably error
			{
				$journal []= "DELETE FROM posts WHERE link = '$k' ;";
				$verbose .= '<p>Deleted post <a href="'.$k.'" target="_blank">'.$k.' '.$v.'</a> '.$oldestid;
			}
			*/
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
	if (rand(0,100)>98) $journal []= "DELETE FROM posts WHERE pubdate < '".$limit."'; VACUUM ; "; 
	
	// remove all deleted files
	$files = glob($tfRoot.'/site/deleted/*');
	foreach($files as $file)
	{
		
		$link = hex2bin(basename($file));
		if (filesize($file)<2000)
		{
			$journal []= "DELETE FROM posts WHERE link = '$link' ;";
			$verbose .= '<p>Deleted post <a href="'.$link.'" target="_blank">'.$link.'</a>';
		}
		unlink($file);
	}
	
		
	$q = join(PHP_EOL,$journal);		
	$db = init();
	if (!$db->exec($q))
	{
		$verbose .= '<p>index error '.$db->lastErrorMsg().' '.$q;
		return array();
	}
	
	foreach($okfiles as $file)
	{
		$bak = str_replace('/site/feeds/','/site/bak/',$file);
		if (file_exists($bak)) @unlink($bak);
		@rename($file,$bak);
	}
	
	$verbose .=  '<p>Index feeds: '.count($files);
	$verbose .=  ' Unchanged: '.$uc;
	$verbose .=  ' Posts: '.$pc;
	
	
	
	
	getRemoteFiles($deletelist);
	
	return $verbose;
	
}

// parsers: all of them return a dictionary with posts

function readJSONFeed($s, $label, $host, $user, $file)
{
	global $tfRoot;	
	$feed = array();
	
	$j = json_decode($s,true);
	
	if (!is_array($j) || @$j['error'] || !count($j)) 
	{
	  	rename($file,$tfRoot.'/site/rejected/jsonerror-'.basename($file));
	  	return $feed;
	}
	
	$feed = array();
	
	foreach($j as $post)
	{
		debugLog('<p>'.@$post['id']);
		
		$list = array();
		$list['medias'] = array();
		
		if (@$post['in_reply_to_id'])  continue; // replying
		if (@$post['in_reply_to_account_id'])  continue; // replying

		if (@$post['visibility'] != 'public')  continue; // private

		debugLog(' '.@$post['visibility']);
		
		if (!@$post['content']) continue; // boost
		
		$list['avatar'] = @$post['account']['avatar'];
		$list['id'] = @$post['id'];
		$list['link'] = 'https://'.$host.'/@'.$user.'/'.$list['id'] ;
		$description = trim(@$post['content']);
		if (strlen($description)>500) $description = substr($description,0,499).'…';
		$list['description'] = $description;
		
		debugLog(' '.@$post['account']['id']);
		debugLog(' <tt>'.substr(htmlspecialchars($list['description']),0,140).'</tt>');

		if (@$post['sensitive'])
		{
			$cw = @$post['spoiler_text']; if (!$cw) $cw = ' ';
			$list['description'] = "<ContentWarning>$cw</ContentWarning>".$list['description'];		
		}
		
		$list['pd'] = strtotime(@$post['created_at']);
		$list['pubdate'] = date('Y-m-d H:i:s',$list['pd']);
		
		
		$attachements = @$post['media_attachments']; 
		
		if (is_array($attachements))
		{
			foreach(@$post['media_attachments'] as $m)
			{
				if (@$m['type']= 'image')
				{
					 $orig = @$m['url'];
					 $thumb = @$m['preview_url'];
					 if (!$thumb) $thumb = $orig;
					 if ($thumb)
					 {
						 $mediadescription = @$m['description'];
						 if (strlen($mediadescription)>500) $mediadescription = substr($mediadescription,0,499).'…';
						 $mediadescription = str_replace('|','&#124;',@$m['description']);
						 $list['medias'][] = $thumb.'|'.$orig.'|'.$mediadescription;
					 }
				}
			}
		}
		
		
		// we treat card as media, but it has 4 fields separated by pipe
		
		$card = @$post['card'];
		
		if (is_array($card))
		{
			if (@$card['type'] == 'link')
			{
				$cardurl = @$card['url'];
				$cardtitle = str_replace('|','&#124;',@$card['title']);
				$carddescription = @$card['description'];
				if (strlen($carddescription)>500) $carddescription = substr($carddescription,0,499).'…';
				$carddescription = str_replace('|','&#124;',@$card['description']);
				$cardurl = @$card['url'];
				$cardimage = @$card['image'];
				// do not index system cache images, they are probably gone 
				if (stristr($cardimage,'/system/cache/')) $cardimage = '';
				
				$list['medias'][] = $cardimage.'|'.$cardurl.'|'.$cardtitle.'|'.$carddescription;				
			}
		}

		$feed[] = $list;
	}
	
	return $feed;
}

function readRSSFeed($s,$file)
{
	global $tfRoot;	
	$feed = array();
	$xml = @simplexml_load_string($s);
	
	if (!$xml) 
	{	
		rename($file,$tfRoot.'/site/rejected/noxml-'.basename($file));
		return $feed;
	}
		
	$arr = xml2array($xml);

	$image = '';
	if (array_key_exists('channel',$arr))
	{
		 $avatar = @$arr['channel']['image']['url'];
		 $j = $xml->channel->item;
	}
	elseif (array_key_exists('entry',$arr))
	{
		return readAtomFeed($s,$file);
	}
	else 
	{
		rename($file,$tfRoot.'/site/rejected/wrongxml-'.basename($file));
		return $feed;
	}
	foreach($j as $post)
	{
		$list = array();
		$postarr = xml2array($post);
		$list['link'] = $post->link;
 		$list['description'] = trim($post->description);
 		
 		$matches = null;
 		preg_match('#<strong>(.*)</strong>([\S\s]*?)(<[\S\s]*)#',$list['description'],$matches); // content warning is multilanguage. 
	
		if ($matches) $list['description'] = '<ContentWarning>'.$matches[2].'</ContentWarning>'.$matches[3];	 		
 		
 		$list['pd'] = strtotime($post->pubDate);
		$list['pubdate'] = date('Y-m-d H:i:s',$list['pd']);
		$list['avatar'] = $avatar;
		$list['medias'] = array();
	    
	    $media = '';
		$medianodes = $post->children('http://search.yahoo.com/mrss/');
	    $medianodesarr = xml2array($medianodes);

	    if ($medianodes && $content = $medianodes->content)
	    {
		    $attr = $content->attributes();
		    if ($attr['medium'] == 'image') { $list['medias'][] = $attr['url'];}
	    }
	    $feed[] = $list;
	}
	return $feed;	
}

function readAtomFeed($s,$file)
{
	$xml = @simplexml_load_string($s);
	
	if (!$xml) 
	{	
		rename($file,$tfRoot.'/site/rejected/noxml-'.basename($file));
		return $feed;
	}
		
	$arr = xml2array($xml);

	$avatar = $xml->logo;
	$j = $xml->entry;
	
	foreach($j as $post)
	{
		$list = array();
		$postarr = xml2array($post);
		$list['medias'] = array();
		$list['link'] = '';
		$list['avatar'] = $avatar;
	    foreach($postarr['link'] as $t)
	    {
	        if (@$t['@attributes']['type'] == 'text/html')  $list['link']  = $t['@attributes']['href'];
	        if (@$t['@attributes']['type'] == 'image/jpeg') $list['medias'][] = $t['@attributes']['href'];
	    }

    	$list['description'] = trim($post->content);
    	
    	$matches = null;
    	preg_match('#<strong>(.*)</strong>([\S\s]*?)(<[\S\s]*)#',$list['description'],$matches); // content warning is multilanguage. f
		if ($matches) $list['description'] = '<ContentWarning>'.$matches[2].'</ContentWarning>'.$matches[3];	 
    	
    	$list['pd'] = strtotime($post->published);
		$list['pubdate'] = date('Y-m-d H:i:s',$list['pd']);
		$feed[] = $list;
	}
	return $feed;
	
}






