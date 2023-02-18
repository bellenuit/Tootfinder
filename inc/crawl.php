<?php 
	
/**
 *	crawl and index functions
 * 
 *  @version 1.3 2023-02-18
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
		
		if ($forcerefresh || !file_exists($localpath) || time()-filemtime($localpath) > 3600 )
		{			
			// public API seems to work and returns note (bio), fields (label) and id
			
			$url = 'https://'.$user['host'].'/api/v1/accounts/lookup?acct='.$user['label']; 
			
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
			$url = 'https://'.$user['host'].'/api/v1/accounts/'.$id.'/statuses';		
	
			$shoppinglist[$url]=$localpath;			
		}
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
    
    $journal = array();
	$journal []= 'BEGIN TRANSACTION; ';
	
	// clean old posts
	$limit = date('Y-m-d',strtotime('-14 day', time()));
	
	
	if (rand(0,100)>98) $journal []= "DELETE FROM posts WHERE pubdate < '".$limit."'; "; 

	if ($usr) 
		$files = array($tfRoot.'/site/feeds/'.$usr.'.json');
	else
		$files = glob($tfRoot.'/site/feeds/*.json');
	$okfiles = array();
	
	$labels = array();
	$verbose = '';
	$pc = 0;
	$uc = 0;
	$prioritystring = '';
	
		
	
	foreach($files as $file)
	{			
		
		$label = preg_replace('/\.json$/','',basename($file)); 
		preg_match('/@([a-zA-Z0-9_]+)@([a-zA-Z0-9_-]+\.[a-zA-Z0-9.-]+)/',$label,$matches); // @user@example.com // can have two . in domain!
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
				@unlink($file);
			}
			
		}
	
		$magic = validUser($label);
		
		if (!$magic) 
		{
			$verbose .=  '<p>invalid user '.$label;
			// we do not delete the user immediately, because the profile page may also have been on error.
			// we wait until there is no new post
			//echo 'not magic';
			$db2 = init(true);
			$sql = "SELECT count(link) as c FROM posts where user = '$label';";
			$up = $db2->querySingle($sql);
			if (!$up) $journal []= "DELETE FROM users WHERE label = '$label'; ";
			$db2->close();
			$priority = time()+86400;
			$sql = $journal []= "UPDATE users SET priority = $priority WHERE label = '".$label."' ; ";
			
			@unlink($file);
			continue;
		}
		
		// we do need follower count
		$fields = explode('::',$magic);
		$followers = @$fields[1];
				
		if (!file_exists($file)) continue;		
		$s = file_get_contents($file);	
		
		if (!$s)
		{
			// $verbose .=  '<p>empty file '.$file;
			rename($file,$tfRoot.'/site/rejected/'.basename($file));
			$priority = time()+86400;
			$journal []= "UPDATE users SET priority = $priority WHERE label = '".$label."' ; ";

			continue;
		}
		
		$j = json_decode($s,true);

		if (!is_array($j) || @$j['error']) 
		{
		  	$verbose .=  '<p>invalid feeed '.$file.' '.@$j['error'];
		  	rename($file,$tfRoot.'/site/rejected/'.basename($file));
		  	$priority = time()+86400;
		  	$journal []= "UPDATE users SET priority = $priority WHERE label = '".$label."' ; ";

			continue;
		}
		if (!count($j)) 
		{
		  	$verbose .=  '<p>empty feeed '.$file.' '.@$j['error'];
		  	rename($file,$tfRoot.'/site/rejected/'.basename($file));
		  	$priority = time()+86400;
		  	$journal []= "UPDATE users SET priority = $priority WHERE label = '".$label."' ; ";

			continue;
		}	
				
		$generalfound = 0;
		$oldposts = 0;
		
		$list = $j;
		
		$minpubdate = 91676409379;
		$maxpubdate = 0;
		$postcount = 0;

		foreach($list as $post)
		{
	
			if (@$post['in_reply_to_id'])  continue; // replying
			if (@$post['in_reply_to_account_id'])  continue; // replying

			if (!@$post['visibiliy'] != 'public' )  continue; // private
			if (!@$post['content']) continue; // boost
			
			$postcount++;
			
			$image = @$post['account']['avatar'];
			$pid = @$post['id'];
			$link = 'https://'.$host.'/@'.$user.'/'.$pid;

			$description = @$post['content'];
			$description = trim($description);
			$description = handleMentions($description);
			$description = SQLite3::escapeString($description);
			
			
			$pd = strtotime(@$post['created_at']);
			$minpubdate = min($minpubdate,$pd);
		    $maxpubdate = max($maxpubdate,$pd);
			$pubdate = date('Y-m-d H:i:s',$pd);
			
			$medias = array();
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
							 $mediadescription = str_replace('|','&#124;',@$m['description']);
							 $medias[] = $thumb.'|'.$orig.'|'.$mediadescription;
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
					$carddescription = str_replace('|','&#124;',@$card['description']);
					$cardurl = @$card['url'];
					$cardimage = @$card['image'];
					$medias[] = $cardimage.'|'.$cardurl.'|'.$cardtitle.'|'.$carddescription;				
				}
			}
			
			$media = join('::',$medias);
			$media = SQLite3::escapeString($media);
			
			$indexdate = date('Y-m-d H:i:s');
			
			$soundex = SQLite3::escapeString (soundexLong($description.' '.$label.' '.$media.' '));

			$db2 = init(true);
			$sql = "SELECT link FROM posts WHERE link = '".$link."'";
			$result = @$db2->query($sql);
			
			$found = false;
			
			if ($result && $d = $result->fetchArray(SQLITE3_ASSOC)) 
			{
				$found = true;
				$oldposts++;
			}

			$db2->close();
				$sql = "";			
			if (!$found && $pubdate > $limit)  // we think the posts are immutable
			{
				
				$sql = $journal []= "INSERT INTO posts (link, user, description, pubdate, image, media, soundex, followers, indexdate) VALUES ('".$link."','".$label."','".$description."','".$pubdate."','".$image."','".$media."','".$soundex."','".$followers."','".$indexdate."'); ";			
				
				$generalfound++;
				$pc++;
			}
			
		}
		$r = rand(0,10);
		
		
		
		// we calculated when the next post is likely to happen, but we wait at most 1 day
		$period = round(($maxpubdate - $minpubdate ) / ($postcount+1));
		if ($postcount == 0) $period = 86400;
		if ($postcount == 1) $period = time() - $maxpubdate;
		$period *= 2; // safety, if we ask too early we risk not to have a message
		$period = min($period,86400);
		$period = max($period,300);
		$priority = time() + $period;
		$journal []= "UPDATE users SET priority = $priority WHERE label = '".$label."' ; ";
		
		
		$labels[] = $label.' (+ '.$generalfound.' - '.$oldposts.')';
		
		$okfiles []= $file;
	
	}
	


 
	
	$journal []= 'COMMIT; ';
	
	if (rand(0,100)>98) $journal []= " VACUUM; ";
		
	$q = join(PHP_EOL,$journal);
	
	
	$db = init();

	if (!$db->exec($q))
	{
		echo '<p>index error '.$db->lastErrorMsg().' '.$q;
		return array();
	}
	
	foreach($okfiles as $file)
	{
		$bak = str_replace('/site/feeds/','/site/bak/',$file);
		if (file_exists($bak)) @unlink($bak);
		@rename($file,$bak);
	}
	
	$verbose .=  '<p>Index Users: '.count($labels);
	$verbose .=  ' Unchanged: '.$uc;
	$verbose .=  ' Posts: '.$pc;
	
	return $verbose;
	
}







