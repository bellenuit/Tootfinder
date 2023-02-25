<?php 
	
if (!defined('CRAWLER')) die('invalid acces');

/**
 *	functions to process the post for display
 * 
 *  @version 1.4 2023-02-20
 */

function handleContentWarning($s)
{
	// hide old content warnings  to be removed march 8th
	$s = trim($s);
	preg_match('#<strong>(.*)</strong>([\S\s]*?)(<[\S\s]*)#',$s,$matches); // content warning is multilanguage. 
	
	if ($matches)
	{
		$m = trim($matches[3]);
		if (substr($m,0,4)=='<br>') $m = substr($m,4); 
		$m = str_replace('<hr>','',$m);
		$m = str_replace('<p></p>','',$m);
		$line = '<div class="contentwarninglabel">'.$matches[1].' '.$matches[2].'</div><p>'.$m;
		$line = '<div class="contentwarning" onclick="this.style.visibility=\'visible\'">'.$line.'</div>';
		$line = str_replace('<p></p>','',$line);
		return $line;

	}
	
	// hide new content warnings
	
	preg_match('#<contentwarning>(.*?)</contentwarning>([\S\s]*)#',$s,$matches); // content warning is multilanguage. 
	
	if ($matches)
	{
		$m = trim($matches[2]);
		if (substr($m,0,4)=='<br>') $m = substr($m,4); 
		$m = str_replace('<hr>','',$m);
		$m = str_replace('<p></p>','',$m);
		$line = '
<div class="contentwarning" onclick="this.style.visibility=\'visible\'">
	<div class="contentwarninglabel">Content warning: '.$matches[1].'</div>
'.$m.'
</div>';
		return $line;

	}
	
	return $s;
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
			
			if ($thumb || $carddescription)
			{
			    if ($thumb) $thumb = '<img src="'.$thumb.'" class="card">';
				$result .= '<div class="card"><a href="'.$orig.'" target="_blank">'.$thumb.'<p class="card"><b>'.$cardtitle.'</b><br>'.$carddescription.'</a></div>';
			
			}
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

function handleHTMLHeader($s)
{
	$s = preg_replace('/<!DOCTYPE.*?>/','',$s);
	$s = str_ireplace('<html>','',$s);
	$s = str_ireplace('</html>','',$s);
	$s = str_ireplace('<body>','',$s);
	$s = str_ireplace('</body>','',$s);
	return $s;
}

