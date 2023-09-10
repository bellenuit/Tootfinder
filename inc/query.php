<?php 
	
	
/**
 *	query functions
 * 
 *  @version 2.2 2023-09-10
 */	
	
if (!defined('CRAWLER')) die('invalid acces');


function query($q, $doindex = true, $newposts = false, $allposts = false)
{
	
	$start = time();
	$query0 = $q;
	
	if ($q == ':now')
	{
		 $result = trendingPosts();
		 if ($newposts)
		 {
			 uasort($result, function($x,$y) { return $x['pubdate'] - $y['pubdate']; });
		 }
		 
		 return $result;
		 
	}
	
	$hastagquery = '';
	if (preg_match('/^#[a-zA-Z0-9:]+$/',$q)) $hastagquery = " AND description LIKE '%/tags/".substr($q,1)."%'";
	
	$q = preg_replace('/[!-)\+-,\.\/:-@[-`\{-~]|\b-\b|\*\b/',' ',$q);
	
	// [!-) ascii 33-41
	// \. ascii 46
	// \/ ascii 47
	// :-@ ascii 58-64
	// [-` ascii 91-96
	// \{-~ ascii 123-126
	// -\b- dash a boundary (not starting word)
	// \*\b star befor a boundary (not ending a word
	// are replaced with spaces 
	
	// echo '<p>'.$q;
	
	$q = encodeSpacelessLanguage($q);
	$q = SQLite3::escapeString($q);
	$planb = false;
	$order = ' ORDER BY score DESC '; if ($newposts) $order = ' ORDER BY pubdate DESC ';
	$limit = ' LIMIT 100 '; if ($allposts) $limit = '  ';
 
	
	$sql = "SELECT '2' as found, score(offsets(posts), description, followers, pubdate) as score, link, user, description, pubdate, image, media, followers FROM posts 
  WHERE posts MATCH '$q' $hastagquery
  $order
  $limit ";
  	
	debugLog('<p>'.$sql);
	
	$start = $ende = time();
	
	$db = init(true);
	if ($db)
	{
		$list = $db->query($sql);
		
		if (NULL == $list->fetchArray(SQLITE3_ASSOC)) 
		{
			$planb = true;
			
			$q = queryStar($query0);
			$q = SQLite3::escapeString($q);
			
			$sql = "SELECT '1' as found, score(offsets(posts), description, followers, pubdate) as score, link, user, description, pubdate, image, media, followers FROM posts 
	  WHERE posts MATCH '$q' 
	  $order
	  limit 10 ";
	  		debugLog('<p>Starred: '.$sql);
			
			$list = $db->query($sql);
			
			if (NULL == $list->fetchArray(SQLITE3_ASSOC)) 
			{
			
				$q = QuerySoundex($query0);
				
				$sql = "SELECT '0' as found, score(offsets(posts), description, followers, pubdate) as score, link, user, description, pubdate, image, media, followers FROM posts 
				  WHERE soundex MATCH '$q'
		  $order
		  limit 10 ";
		  		debugLog('<p>Soundex: '.$sql);
		  
		  		$list = $db->query($sql); 
	  		
	  		}
		}
		
		$list->reset();
	
		$rc = 0;
		$result = array();
		$validusers = array();
		
		while ($d = $list->fetchArray(SQLITE3_ASSOC)) 
		{
			$profile = getProfile($d['user']);
			if (!isset($validusers[$d['user']]) && !validUser($profile)) continue;  // WE MUST GET VALIDUSER BACK HERE HOTFIX
			$validusers[$d['user']] = 1;
	
			$rc++;
			
			$d['description-jp'] = $d['description'];
			$d['description'] = decodeSpacelessLanguage($d['description']);	
			$d['media'] = decodeSpacelessLanguage($d['media']);	
						
			$result[] = $d;
		}
		$db->close();
		
		// downvote following posts the same user
		
		if (!$newposts)
		{
			// echo 'sort';   
			 
			$users = array();
			foreach($result as $k=>$x)
			{
				if (isset($users[$x['user']]))
				{
					$users[$x['user']]++;
					$x['score'] = round($x['score']/$users[$x['user']]);
					$result[$k] = $x;
				}
				else
				{
					$users[$x['user']] = 1;
				}
			}
			
			uasort($result, function($x,$y) { return $y['score'] - $x['score']; });
		}
		
	}
	
	$ende  = time();
	debugLog(sprintf('<p>SQL (%0d sec)',$ende - $start));
	$start  = time();
	
	if ($doindex && ! stristr($query0,'@'))
	{
	    
		if ($planb) $rc = 0;
		$date = date("Y-m-d H:i"); // remove seconds to discourage clickbait
		$q = SQLite3::escapeString($query0);
		$sql2 = "INSERT INTO queries (query, date, results) VALUES ('".$q."','".$date."',".$rc.");"; 
		$db = initQueries();
		if ($db)
		{
			if (!$db->exec($sql2)) echo '<p>index error '.$db->lastErrorMsg(); 
			$datelimit = date('Y-m-d',strtotime('-3 month', time()));
			$sql2 = "DELETE FROM queries WHERE date < '".$datelimit."' ;"; 
			if (rand(0,1000)>998) $db->exec($sql2); 
			$db->close();
		}
	}
	$ende = time();
	debugLog(sprintf('<p>Update queries (%0d sec)',$ende - $start));
	$start  = time();
	
	return $result;	
}

function score($s, $description, $followers, $pubdate)
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
  
   $r *= 90 / max(1,$interval->d);
   
   $r = round($r*1000);
   
   return $r;
}

function time2date($t)
{
	return date("Y-m-d H:i:s",$t);
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






