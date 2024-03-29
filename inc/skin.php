<?php 
	
if (!defined('CRAWLER')) die('invalid acces');

/**
 *	functions to process the post for display
 * 
 *  @version 2.2 2023-09-10
 */

function handleContentWarning($s)
{
	
	preg_match('#<contentwarning>(.*?)</contentwarning>([\S\s]*)#',$s,$matches); // content warning is multilanguage. 
	
	if ($matches)
	{
		$m = trim($matches[2]);
		if (substr($m,0,4)=='<br>') $m = substr($m,4); 
		$m = str_replace('<hr>','',$m);
		$m = str_replace('<p></p>','',$m);
		if ($matches[1]) $matches[1] = ': '.$matches[1];
		$line = '
<div class="contentwarning" onclick="this.style.visibility=\'visible\'">
	<div class="contentwarninglabel">Content warning'.$matches[1].'</div>
'.$m.'
</div>';
		return $line;

	}
	
	return $s;
}

function handleMentions($s)
{
	// remove all mentions 
	
	echo "<!-- ".$s."-->";
	
	$doc = new DOMDocument();
	$s  = mb_encode_numericentity($s, array (0x80, 0xffffff, 0x80, 0xffffff), 'UTF-8') ; // DOM does not reasd unicode
	
	echo "<!-- ".$s."-->";
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
	$s  = $doc->saveHTML();	
	if ($s) $s  = mb_decode_numericentity($s, array (0x80, 0xffffff, 0x80, 0xffffff), 'UTF-8') ;// give back unicode for proper fulltext search of japanese
	echo "<!-- ".$s."-->";
	
	
	
	return $s;
}

function handleHashtags($s)
{
     // link hashtags to tootfinder // hashtag can be cut by ellipsis
     $s = urldecode($s);
     return preg_replace('/(<a href=")https:\/\/.*?\/tags\/(.*?)" class="mention hash.*?" rel="tag">.*?<\/a>/','$1tags/$2">#$2</a>',$s);  
}

function sqltable($db,$sql)
{
	$db->enableExceptions(true);
	try { $list = $db->query($sql); } catch (Exception $e) { return 'Caught exception: ' . $e->getMessage().' '.$sql; }
	
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
			
			// remove single quotes in card description
			$carddescription = preg_replace("/^'/",'',$carddescription);
			$carddescription = preg_replace("/'$/",'',$carddescription);
			if (strlen($carddescription)>500) $carddescription = substr($carddescription,0,499).'…';	
			if ($thumb || $carddescription)
			{
			    if ($thumb) $thumb = '<img src="'.$thumb.'" onerror="this.style.display=\'none\'" class="card">';
				$result .= '<div class="card"><a href="'.$orig.'" target="_blank">'.$thumb.'<p class="card"><b>'.$cardtitle.'</b><br>'.$carddescription.'</a></div>';
			
			}
		}
		else  // 1-3 fields
		{
			$thumb = $fields[0];
			if (count($fields)>1) $orig = $fields[1]; else $orig = $thumb;
			if (count($fields)>2) $alt =  'alt="'.str_replace('"','&quot;',$fields[2]).'"'; else $alt = '';
			
			$result .= '<div class="media"><a href="'.$orig.'" target="_blank"><img src="'.$thumb.'" onerror="this.style.display=\'none\'"  class="media" '.$alt.' ></a></div>';
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

function encodeSpacelessLanguage($s)
{
	// japanese
	// source https://gist.github.com/terrancesnyder/1345094
	
	$s = preg_replace('/([一-龠]|[ぁ-ゔ]|[ァ-ヴー]|[々〆〤ヶ])/u','$1 ',$s); 
	// https://stackoverflow.com/questions/6787716/regular-expression-for-japanese-characters
	// Japanese katakana, hiragana and dashes
	// removed alphanumeric from snippet
	
	return $s;
	
}

function decodeSpacelessLanguage($s)
{
	// japanese
	// source https://gist.github.com/terrancesnyder/1345094
	
	$s = preg_replace('/([一-龠]|[ぁ-ゔ]|[ァ-ヴー]|[々〆〤ヶ])\s/u','$1',$s); 
	// https://stackoverflow.com/questions/6787716/regular-expression-for-japanese-characters
	// Japanese katakana, hiragana and dashes
	// removed alphanumeric from snippet
	
	return $s;
	
}

function expandableSnippet($s)
{

     $script = 'ch = this.parentNode.children; for (var i = 1; i < ch.length; i++) { if (ch[i].style.display == "block")  ch[i].style.display = "none"; else ch[i].style.display = "block"; }';     
     return "<p><tt><div><a onclick='$script'>(+)</a><div style='display:none'><p>".htmlspecialchars($s)."</a></div></div></tt>";
     
}



