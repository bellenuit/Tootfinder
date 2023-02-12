<?php 
	
	
/**
 *	query functions
 * 
 *  @version 1.2 2023-02-12
 */	
	
if (!defined('CRAWLER')) die('invalid acces');




function query($q, $doindex = true)
{
	$db = init(true);
	$query0 = $q;
	$q = SQLite3::escapeString($q);
	$planb = false;
	
	$sql = "SELECT '2' as found, score(offsets(posts), description, followers, pubdate, indexdate) as score, link, user, description, pubdate, image, media, followers, indexdate FROM posts 
  WHERE posts MATCH '$q'
  ORDER BY score DESC
  LIMIT 100";
	
	$list = $db->query($sql);
	
	if (NULL == $list->fetchArray(SQLITE3_ASSOC)) 
	{
		$planb = true;
		
		$q = queryStar($query0);
		$q = SQLite3::escapeString($q);
		
		$sql = "SELECT '1' as found, score(offsets(posts), description, followers, pubdate, indexdate) as score, link, user, description, pubdate, image, media, followers, indexdate FROM posts 
  WHERE posts MATCH '$q'
  ORDER BY score DESC
  LIMIT 100";
		
		$list = $db->query($sql);
		
		if (NULL == $list->fetchArray(SQLITE3_ASSOC)) 
		{
		
			$q = QuerySoundex($query0);
			
			$sql = "SELECT '0' as found, score(offsets(posts), description, followers, pubdate, indexdate) as score, link, user, description, pubdate, image, media, followers, indexdate FROM posts 
			  WHERE soundex MATCH '$q'
	  ORDER BY score DESC
	  LIMIT 10";
	  
	  		$list = $db->query($sql); 
  		
  		}
	}
	
	$list->reset();

	$rc = 0;
	$result = array();
	
	while ($d = $list->fetchArray(SQLITE3_ASSOC)) 
	{
		if (!validUser($d['user'])) continue;

		$rc++;	
			
		$result[] = $d;
	}
	$db->close();
	
	
	
	
	if (!$planb && $doindex && ! stristr($query0,'@'))
	{
	    $db = initQueries();
		$date = date("Y-m-d H:i"); // remove seconds to discourage clickbait
		$q = SQLite3::escapeString($query0);
		$sql2 = "INSERT INTO queries (query, date, results) VALUES ('".$q."','".$date."',".$rc.");"; 
		if (!$db->exec($sql2)) echo '<p>index error '.$db->lastErrorMsg(); 
		$db->close();
	}
	
	return $result;	
}

function score($s, $description, $followers, $pubdate, $indexdate)
{
   // occurencies, the earlier the more
   
   $offsets = explode(' ',$s);
   $r = 0;
   for ($i=0;$i<count($offsets);$i+=4)
   {
   	 $o = $offsets[$i+2];
   	 $r += 1/(1+log($o+1));
   }
   
   // reputation
   
   $r *= log(max($followers,2.833));
   
   // multiple hash penalty if more than 3 hashtags
   
   $fields = explode('#',$description);
   if (count($fields)>4)
   {
	   $r /= count($fields);
   }
   
   // newer posts first
   
   $pub = date_create($pubdate);
   $tod = date_create();
   $interval = date_diff($tod, $pub);
  
   $r *= 14 / max(1,$interval->d);
   
   $r = floor($r*1000);
   
   return $r;
}



	
function queryStar($q)
{
	$list = array();
	$q = str_replace('(',' ( ',$q);
	$q = str_replace(')',' ) ',$q);
	foreach(explode(' ',$q) as $w)
	{
		switch(strtolower($w))
		{
			case 'and':
			case 'or':
			case 'not':
			case 'near':
			case '(';
			case ')': $list[] = $w; break;
			case '': break;
			default: if (substr($w,-1) == '*') $list[] = $w; else $list[] = $w.'*';
		}
	}
	return join(' ',$list);
}

function querySoundex($q)
{
	$list = array();
	$q = str_replace('(',' ( ',$q);
	$q = str_replace(')',' ) ',$q);
	foreach(explode(' ',$q) as $w)
	{
		switch(strtolower($w))
		{
			case 'and':
			case 'or':
			case 'not':
			case 'near':
			case '(';
			case ')': $list[] = $w; break;
			case '': break;
			default: $list[] = soundex(str_replace('*','',$w));
		}
	}
	return join(' ',$list);
}






