<?php 
	
if (!defined('CRAWLER')) die('invalid acces');

/**
 *	functions to process the post for display
 * 
 *  @version 1.2 2023-02-12
 */

function handleContentWarning($s)
{
	// hide content warnings
	$s = trim($s);
	preg_match('#<strong>(.*)</strong>([\S\s]*?)(<[\S\s]*)#',$s,$matches); // content warning is multilanguage. 
	
	if ($matches)
	{
		return '<div class="contentwarning"><div class="contentwarninglabel" onclick="this.parentNode.style.visibility=\'visible\'">'.$matches[1].' '.$matches[2].'</div>'.$matches[3].'</div>';

	}
	else return $s;
}

function handleMentions($s)
{
	// remove all mentions
	return preg_replace('#<a.*?u-url mention.*?</a>#','<a>@…</a>',$s); 
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


