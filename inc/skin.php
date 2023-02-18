<?php 
	
if (!defined('CRAWLER')) die('invalid acces');

/**
 *	functions to process the post for display
 * 
 *  @version 1.3 2023-02-18
 */

function handleContentWarning($s)
{
	// hide content warnings
	$s = trim($s);
	preg_match('#<strong>(.*)</strong>([\S\s]*?)(<[\S\s]*)#',$s,$matches); // content warning is multilanguage. 
	
	if ($matches)
	{
		return '<div class="contentwarninglabel">'.$matches[1].' '.$matches[2].'</div>'.$matches[3];

	}
	else return $s;
}

function handleMentions($s)
{
	// remove all mentions 
	
	$doc = new DOMDocument();
	$s  = mb_convert_encoding($s , 'HTML-ENTITIES', 'UTF-8'); 
	@$doc->loadHTML($s);
	foreach ($doc->getElementsByTagName('span') as $item)
	{
		$attr =  $item->getAttribute('class');
		if ($attr == 'h-card')
		{
			foreach($item->childNodes as $n) $item->removeChild($n);
			$newnode = $doc->createElement('span','@…');
			$item->appendChild($newnode);
		} 
		
	}
	return $doc->saveHTML();	
}

function handleHashtags($s)
{
     // link hashtags to tootfinder 
     return preg_replace('/(<a href=")https:\/\/.*?\/tags\/(.*?)" class="mention hashtag" rel="tag">/','$1index.php?query=%23$2">',$s);  
}

function sqltable($db,$sql)
{
	$list = $db->query($sql);
	$lines = [];
	while ($d = $list->fetchArray(SQLITE3_ASSOC)) 
	{
		$keys = array_keys($d);
		$values = array_values($d);
		if (!count($lines))
			$lines []= '<tr><th>'.join('</th><th>',$keys).'</th></tr>';
		
		$lines []= '<tr><td>'.join('</td><td>',$values).'</td></tr>';	
	}
	
	return '<table border=1>'.join(PHP_EOL,$lines).'</table>';

}

function handleMedia($media)
{
	if (!$media) return '';
	
	$result = '';
	
	foreach(explode('::',$media) as $m)
	{
		$fields = explode('|',$m);
		
		if (count($fields)==4)
		{
			// card
			// $cardimage.'|'.$cardurl.'|'.$cardtitle.'|'.$carddescription;
			$thumb = $fields[0];
			$orig = $fields[1];
			$cardtitle = $fields[2];
			$carddescription = $fields[3];
			$result .= '<div class="card"><a href="'.$orig.'" target="_blank"><img src="'.$thumb.'" class="card"><p class="card"><b>'.$cardtitle.'</b><br>'.$carddescription.'</a></div>';
		}
		else  // 1-3 fields
		{
			$thumb = $fields[0];
			if (count($fields)>1) $orig = $fields[1]; else $orig = $thumb;
			if (count($fields)>2) $alt =  'alt="'.str_replace('"','&quot;',$fields[2]).'"'; else $alt = '';
			
			$result .= '<div class="media"><a href="'.$orig.'" target="_blank"><img src="'.$thumb.'" class="media" '.$alt.' ></a></div>';
		}
	}
	
	return $result;	
}


