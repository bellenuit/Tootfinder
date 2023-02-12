<?php 
	
/**
 *	crawl and index functions
 * 
 *  @version 1.2 2023-02-12
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
		
	foreach($list as $user)
	{
		// profile
		
		$localpath = $tfRoot.'/site/profiles/'.$user['label'].'.html';
		
		if ($forcerefresh || !file_exists($localpath) || time()-filemtime($localpath) > 3600 )
		{
			
			$url = 'https://'.$user['host'].'/users/'.$user['user'];  //echo $url;
			$s = getRemoteFile($url); 
			if (!$s && $forcerefresh) return 'ERROR_URL '.$url;
			$localpath = $tfRoot.'/site/profiles/'.$user['label'].'.html';
			if ($s) { file_put_contents($localpath,$s); $pc++; }
			
			
		}
		
		// feed
		$url = 'https://'.$user['host'].'/users/'.$user['user'].'.rss';
				
		$s = getRemoteFile($url);
		$localpath = $tfRoot.'/site/feeds/'.$user['label'].'.rss';
		
		if (substr($s,0,6)=='<html>')
		{
		    // redirection to atom
		    $url = preg_replace("/.*(https:\/\/.*?)['\"].*/","$1",$s);
		    $s = getRemoteFile($url);
		    $localpath = $tfRoot.'/site/feeds/'.$user['label'].'.atom';
		}
		if ($s) { file_put_contents($localpath,$s); $fc++;}
	}
	
	$verbose .= '<p>Profiles: '.$pc;
	$verbose .= '<p>Feeds: '.$fc;
	
	return $verbose;
	
}

function index()
{
	global $tfRoot;		
    
    $journal = array();
	$journal []= 'BEGIN TRANSACTION; ';
	
	$files = glob($tfRoot.'/site/feeds/*.*');
	$okfiles = array();
	
	$labels = array();
	$verbose = '';
	$pc = 0;
	$prioritystring = '';
	
	foreach($files as $file)
	{
			
		
		$label = $user = str_replace('.rss','',str_replace('.atom','',basename($file)));
		$magic = validUser($label);
		
		if (!$magic) 
		{
			// we do not delete the user immediately, because the profile page may also have been on error.
			// we wait until there is no new post
			
			$db2 = init(true);
			$sql = "SELECT count(link) as c FROM posts where user = '$label';";
			$up = $db2->querySingle($sql);
			if (!$up) $journal []= "DELETE FROM users WHERE label = '$label'; ";
			$db2->close();
			
			continue;
		}
		// we do not need follower count
		$fields = explode('::',$magic);
		$followers = @$fields[1];
				
		if (!file_exists($file)) continue;		
		$s = file_get_contents($file);	
		
		if (!$s)
		{
			$verbose .=  '<p>empty file '.$file;
			rename($file,$tfRoot.'/site/rejected/'.basename($file));
			
			$journal []= "UPDATE users SET priority = priority/2 WHERE label = '".$label."' ; ";
			
			continue;
		}
		
		$xml = @simplexml_load_string($s);
		if (!$xml) 
		{
		  	$verbose .=  '<p>nox xml '.$file;
		  	rename($file,$tfRoot.'/site/rejected/'.basename($file));
		  	
		  	$journal []= "UPDATE users SET priority = priority/2 WHERE label = '".$label."' ; ";
		  	
			continue;
		}
		$arr = xml2array($xml);
		
		if (array_key_exists('channel',$arr))
		{
		    $format = 'rss';
		}
		elseif (array_key_exists('entry',$arr))
		{
		    $format = 'atom';
		}
		else
		{
		   	$verbose .=  '<p>invalid format '.$file;
		    rename($file,$tfRoot.'/site/rejected/'.basename($file));
		    
		    $journal []= "UPDATE users SET priority = priority/2 WHERE label = '".$label."' ; ";
		    
		    continue;
		}
		
		$image = '';
		if ( $format == 'rss' ) $image = @$arr['channel']['image']['url'];
		if ( $format == 'atom' ) $image = $xml->logo;
		
		$generalfound = 0;
		$oldposts = 0;
		
		$list = array();
		if ( $format == 'rss' ) $list = $xml->channel->item;
		if ( $format == 'atom' ) $list = $xml->entry;
		
		
		
		foreach($list as $post)
		{
			$postarr = xml2array($post);
			$medias = array();
			
			if ( $format == 'rss' )
			{
			    $link = SQLite3::escapeString ($post->link);
		    	$description = SQLite3::escapeString (trim($post->description));
				$pubdate = date('Y-m-d H:i:s',strtotime(SQLite3::escapeString ($post->pubDate)));
			
			    $media = '';
			    
			    
			    $medianodes = $post->children('http://search.yahoo.com/mrss/');
			    $medianodesarr = xml2array($medianodes);

			    if ($medianodes && $content = $medianodes->content)
			    {
				    $attr = $content->attributes();
				    if ($attr['medium'] == 'image') { $media = $attr['url'];}
			    }
			    
			    
			}
			if ( $format == 'atom' )
			{
			    $link = '';
			    foreach($postarr['link'] as $t)
			    {
			        if (@$t['@attributes']['type'] == 'text/html')
			        {
			            $link = SQLite3::escapeString ($t['@attributes']['href']);
			        }
			        
			        if (@$t['@attributes']['type'] == 'image/jpeg')
			        {
			            $medias[] = $t['@attributes']['href'];
			        }
			    }
			    $media = join(' ',$medias);
			    
		    	$description = SQLite3::escapeString (trim($post->content));
				$pubdate = date('Y-m-d H:i:s',strtotime(SQLite3::escapeString($post->published)));

			}
			
			$indexdate = date('Y-m-d H:i:s');
			
			$soundex = SQLite3::escapeString (soundexLong($description.' '.$user.' '.$media.' '));

			$db2 = init(true);
			$sql = "SELECT link FROM posts WHERE link = '".$link."'";
			$result = $db2->query($sql);
			
			$found = false;
			
			while ($d = $result->fetchArray(SQLITE3_ASSOC)) 
			{
				$found = true;
				$oldposts++;
			}
			$db2->close();
						
			if (!$found)  // we think the posts are immutable
			{
				
				$journal []= "INSERT INTO posts (link, user, description, pubdate, image, media, soundex, followers, indexdate) VALUES ('".$link."','".$user."','".$description."','".$pubdate."','".$image."','".$media."','".$soundex."','".$followers."','".$indexdate."'); ";			
				
				$generalfound++;
				$pc++;
			}
			
		}
		$r = rand(0,10);
		if ($generalfound)
		{
			$journal []= "UPDATE users SET priority = $generalfound + $r WHERE label = '".$label."' ; ";
		}
		else
		{
			$journal []= "UPDATE users SET priority = priority/2 + $r WHERE label = '".$label."' ; ";
		}
		
		$labels[] = $label.' (+ '.$generalfound.' = '.$oldposts.')';
		
		$okfiles []= $file;
	
	}
	
	// clean old posts
	$limit = date('Y-m-d',strtotime('-14 day', time()));

	$journal []= "DELETE FROM posts WHERE pubdate < '".$limit."'; "; 
	$journal []= "DELETE FROM queries WHERE date < '".$limit."'; "; 
	
	$journal []= 'COMMIT; ';
	
	if (rand(0,100)>98) $journal []= " VACUUM; ";
		
	$q = join(PHP_EOL,$journal);
	
	
	$db = init();

	if (!$db->exec($q))
	{
		echo '<p>index error '.$db->lastErrorMsg();
		return array();
	}
	
	foreach($okfiles as $file) @unlink($file);
	
	$verbose .=  '<p> Users: '.count($labels);
	$verbose .=  '<p> Posts: '.$pc;
	
	return $verbose;
	
}







