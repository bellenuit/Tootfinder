<?php
/**
 *	REST API
 * 
 *  @version 1.6 2023-02-26
 */	
 
 
if (!defined('CRAWLER')) die('invalid acces');

header('Content-Type: application/json; charset=utf-8'); 
$result = array();

$linkpath = $tfRoot.'index.php?name=';



// open api default
$list = array();
$list[] = array(
	'id' => '/search/{query}',
	'operationid' => '/search.+',
	'root' => '/search',
	'description' => 'fulltext search',
	'parameters' => 'query',
	'parameterdescriptions' => 'search term (FTS3 syntax)',
	'parameterexamples' => 'san AND francisco',
	'sql' => 'query') ;

foreach($list as $v)
{
	$k = $v['id'];
	$k2 = str_replace('/','\/',@$v['operationid']);
	$r2 = str_replace('/','\/',@$v['root']);
	if (preg_match('/'.$k2.'/',$name)) 
	{
		if ($v['sql'] == 'query')
		{
			$q = substr($name,strlen('rest/api'.$r2));
			if ($q)
			foreach(query($q, false, false) as $row)
			{
				$post = array();
					
				$matches = null;
				preg_match('#https://(.*?)/@(.*?)/(.*)#',@$row['link'],$matches);
				
				$post['id'] = $matches[3];
				
				$post['created_at'] = @$row['pubdate'];
				$post['in_reply_to_id'] = null;
				$post['in_reply_to_account_id'] = null;
				
				$description = handleHTMLHeader(@$row['description']);
				$matches2 = null;
				preg_match('#<contentwarning>(.*?)</contentwarning>([\S\s]*)#',$description,$matches2); 				
				if ($matches2)
				{
					$post['sensitive']  = true;
					$post['spoiler']  = $matches[1];
					$description = $matches[2];
				}
				else
				{
					$post['sensitive']  = false;
					$post['spoiler']  = '';
				}
				
				
				$post['visibility'] = 'public';
				$post['language'] = '';
				$post['uri'] = 'https://'.$matches[1].'/users/'.$matches[2].'/statuses/'.$matches[3];
				$post['url'] = @$row['link'];
				$post['content'] = trim($description);
				$card = array();
				foreach(explode('::',@$row['media']) as $elem)
				{
					$fields = explode('|',$elem);
					if (count($fields) == 4)
					{
						// card  if (@$card['type'] == 'link')/*
						$card['type'] = 'link';
						$card['title'] = $fields[2];
						$card['description'] = $fields[3];
						$card['url'] =  $fields[1];
						$card['image'] = $fields[0];
							
					}	
					elseif (count($fields)>1)
					{
						$ma = array();
						$ma['type'] = 'image'; 
						$ma['preview_url'] = $fields[0]; 
						$ma['url'] = @$fields[1]; 
						$ma['description'] = @$fields[2]; 
						
						$post['media_attachments'][] = $ma;
					}
				}
				
				if (count($card)) $post['card'] = $card;
				
				
				
				$result[] = $post;
			}
			
		}
		
		echo json_encode($result); exit;
	}
	
}

$result['openapi'] = '3.1.0';	
$result['info']['title'] = 'Tootfinder REST API';
$result['info']['description'] = 'Tootfinder REST API for Mastodon applications';
$result['info']['version'] = $tfVersion;
$result['servers'][]['url'] = 'https://www.tootfinder.ch/rest/api';

foreach($list as $v)
{
		
	$k = $v['id'];	
	
	$result['paths'][$k]['get']['operationId'] = $v['operationid'];
	$result['paths'][$k]['get']['description'] = $v['description'];
	
	if ($v['parameters'])
	{
		$ps = explode('::',$v['parameters']);
		$ds = explode('::',@$v['parameterdescriptions']);
		$es = explode('::',@$v['parameterexamples']);
		$c = count($ps);
		$pi = 0;
		for($i=0;$i<$c;$i++)
		{
			$plist = array();
			$plist['name'] = $ps[$i];
			$plist['in'] = 'path';
			$plist['required'] = true;
			$plist['description'] = $ds[$i];
			$plist['schema']['type'] = 'string';
			$plist['example'] = $es[$i];
			$result['paths'][$k]['get']['parameters'] []= $plist;
			
		}
	}
			
}

echo json_encode($result);
