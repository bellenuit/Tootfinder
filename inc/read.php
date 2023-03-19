<?php
	
/**
 *	parsers: all of them return a dictionary with posts
 * 
 *  @version 1.8 2023-03-19
 */
	

function readJSONFeed($s, $label, $host, $user, $file)
{
	
	global $tfRoot;	
	$feed = array();
	
	$j = json_decode($s,true);
	
	if (isset($j['@context'][0]) && $j['@context'][0] == 'https://www.w3.org/ns/activitystreams')
		return readActivityPubFeed($s, $label, $host, $user, $file);
	
	debugLog(' mastodon ');
	
	if (!is_array($j) || @$j['error'] || !count($j)) 
	{
	  	@rename($file,$tfRoot.'/site/rejected/jsonerror-'.basename($file));
	  	return $feed;
	}
	
	if (isset($j['orderedItems'])) $j = $j['orderedItems']; // outbox
	
	$feed = array();
	
	$k=0;
	
	foreach($j as $post)
	{
		
		$list = array();
		$list['medias'] = array();
		
		if (@$post['in_reply_to_id'])  continue; // replying
		
		if (@$post['in_reply_to_account_id'])  continue; // replying

		if (@$post['visibility'] != 'public')  continue; // private only 
		
		if (!@$post['content']) continue; // boost
		
		$list['avatar'] = @$post['account']['avatar'];
		$list['id'] = @$post['id'];
		$list['link'] = 'https://'.$host.'/@'.$user.'/'.$list['id'] ;
		$description = trim(@$post['content']);
		if (mb_strlen($description,'UTF-8')>500) $description = mb_substr($description,0,499,'UTF-8').'…';
		$list['description'] = $description;
		
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
						 $mediadescription = str_replace('|','&#124;',@$m['description']);
						 if (mb_strlen($mediadescription,'UTF-8')>500) $mediadescription = mb_substr($mediadescription,0,499,'UTF-8').'…';
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
				if (mb_strlen($cardtitle,'UTF-8')>500) $$cardtitle = mb_substr($cardtitle,0,499,'UTF-8').'…';
				$carddescription = str_replace('|','&#124;',@$card['description']);
				if (mb_strlen($carddescription,'UTF-8')>500) $carddescription = mb_substr($carddescription,0,499,'UTF-8').'…';				
				$cardurl = @$card['url'];
				$cardimage = @$card['image'];
				// do not index system cache images, they are probably gone 
				if (stristr($cardimage,'/system/cache/')) $cardimage = '';
				
				$list['medias'][] = $cardimage.'|'.$cardurl.'|'.$cardtitle.'|'.$carddescription;				
			}
		}

		$feed[] = $list;
	}
	
	debugLog(count($feed));
	
	return $feed;
}

function readActivityPubFeed($s, $label, $host, $user, $file)
{
	debugLog(' activitypub ');
	
	global $tfRoot;	
	$feed = array();
	
	$j = json_decode($s,true);
	
	if (!is_array($j) || @$j['error'] || !count($j)) 
	{
	  	@rename($file,$tfRoot.'/site/rejected/jsonerror-'.basename($file));
	  	return $feed;
	}
	
	if (isset($j['orderedItems'])) $j = $j['orderedItems']; // outbox
	
	$feed = array();
	
	$k=0;
	
	debugLog(' r '.count($j));
	
	$profile = getProfile($label);
	
	foreach($j as $post)
	{

		$list = array();
		
		
		if (!in_array('https://www.w3.org/ns/activitystreams#Public', @$post['to'])
		&& !in_array('https://www.w3.org/ns/activitystreams#Public', @$post['cc'])) continue;  // not public
		
		
		$post = @$post['object']; 
		
		if (!isset($post['id'])) continue; // ignore announce
		
		$list['link'] = $list['id'] = @$post['id'];
		
		if (!count($post)) continue;
		
		if (!@$post['content']) continue; // boost
		
		
		
		$list['medias'] = array();
		
		$list['avatar'] = $profile['avatar'];  // activitypub does not show avatar
		
		$description = trim(@$post['content']);
		if (mb_strlen($description,'UTF-8')>500) $description = mb_substr($description,0,499,'UTF-8').'…';
		$list['description'] = $description;

		if (@$post['sensitive']) $list['description'] = "<ContentWarning>&nbsp;</ContentWarning>".$list['description']; // activitypub does not have content warning description
		
		$list['pd'] = strtotime(@$post['published']);
		$list['pubdate'] = date('Y-m-d H:i:s',$list['pd']);
		
		
		$attachements = @$post['attachment']; 
		
		if (is_array($attachements))
		{
			foreach($attachements as $m)
			{
				if (in_array(@$m['mediaType'], array('image/gif','image/jpg','image/jpeg','image/png')))
				{
					 $orig = @$m['url'];
					 $thumb = @$m['url'];  // activitypub does not support thumbnails
					 if ($thumb)
					 {
						 $mediadescription = str_replace('|','&#124;',@$m['summary']);
						 if (mb_strlen($mediadescription,'UTF-8')>500) $mediadescription = mb_substr($mediadescription,0,499,'UTF-8').'…';
						 $list['medias'][] = $thumb.'|'.$orig.'|'.$mediadescription;
					 }
				}
			}
		}
		
		
		// activitypub does not have cards
		if ($list['link']=='h') print_r($list);

		$feed[] = $list;
	}
	
	debugLog(' w '.count($feed));
	
	return $feed;
}

function readRSSFeed($s,$file)
{
	global $tfRoot;	
	$feed = array();
	$xml = @simplexml_load_string($s);
	
	if (!$xml) 
	{	
		@rename($file,$tfRoot.'/site/rejected/noxml-'.basename($file));
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
	    
	    // gotosocial feed
	    if ($content = @$post->enclosure)
	    {
		    $attr = $content->attributes();
		    if ($attr['type'] == 'image/jpeg') {  $list['medias'][] = $attr['url'];}
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


