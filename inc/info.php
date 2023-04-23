<?php 
	
	
/**
 *	info functions for statistics
 * 
 *  @version 1.8 2023-03-19
 */
	
	
if (!defined('CRAWLER')) die('invalid acces');



function getinfo()
{
	$users = $posts = $queries ='';
	$db = init(true);
	if ($db)
	{
		$sql = 'SELECT count(DISTINCT link) FROM posts';
		$posts = $db->querySingle($sql);
		$sql = 'SELECT count(user) FROM users';
		$users = $db->querySingle($sql);
		$db->close();
		$sql = 'SELECT count(query) FROM queries';
		$db = initQueries(true);
		$queries = $db->querySingle($sql);
		$db->close();
	}

	return "$users users, $posts posts, $queries queries";
	
}

function indexStatus()
{
	$db = init(true);
	if ($db)
	{
		$sql = 'SELECT min(priority) FROM users WHERE priority > 0';
		$minpriority = $db->querySingle($sql);
		$db->close();
		
		$delay = round(($minpriority-time())/60);
		if (!$delay) return 'in time';
		elseif ($delay == 1) return '1 minute ahead';
		elseif ($delay == -1) return '1 minute behind';
		elseif ($delay > 0) return $delay.' minutes ahead';
		elseif ($delay < 0) return -$delay.' minutes behind';
	}
}


function popularQueries()
{
	
	global $tfRoot;
	$file = $tfRoot.'/site/cache/popularQueries.txt';
	
	if (file_exists($file) && time() - filemtime($file) < 3600) 
	{
		$s = file_get_contents($file);
		$result = json_decode($s,true);
		return $result;
	}
	
	$limit = date('Y-m-d',strtotime('-1 day', time()));
	$sql = "SELECT DISTINCT query, count(query) as c FROM queries WHERE results > 0 and date > '$limit' GROUP BY query ORDER BY c DESC limit 5"; 
	
	$db = initQueries(true);
	if ($db)
	{
		$list = $db->query($sql);
	
		$result = array();
		while ($d = $list->fetchArray(SQLITE3_ASSOC)) $result[]=$d;	
		$db->close();
		$s = json_encode($result);
		file_put_contents($file, $s);
		return $result;
	}
	return array();
	
	
	
}

function trendingWords($refresh = false)
{
	global $tfRoot;
	$file = $tfRoot.'/site/cache/trendingWords.txt';
	
	if (!$refresh)
	if (file_exists($file) && time() - filemtime($file) < 3600) return file_get_contents($file);
	
	
	$today = date('Y-m-d');
	$q = "SELECT description, followers, user FROM posts ORDER BY pubdate DESC limit 10000";
	
	$db = init();
	$users = array();
	if ($db)
	{
		$list = $db->query($q);
		$descriptions = array();
		if (!$list) return '';
		$i = 0;
		while ($d = $list->fetchArray(SQLITE3_ASSOC)) 
		{
			if (isset($users[$d['user']])) $users[$d['user']] -= 0.3; // divide by 2 with log 10 
			else $users[$d['user']] = log10($d['followers']+1);
			$descriptions[$d['description']] = $users[$d['user']];  // count only once per user
			$i++;
		}
		$db->close();
				
		$s = relatedSearches($descriptions,'');
		file_put_contents($file, $s);
		return $s;
	}
}


function relatedSearches($descriptions,$query)
{
	
	
	$dict = array();
	foreach($descriptions as $p=>$n)
	{
	  	 // clean
	  	 $p = str_replace('<p'," <p",$p);
	  	 $p = str_replace('<br'," <br",$p);	  	 
	     $p = preg_replace('/<.*?>/','',$p); 
	     $p = preg_replace('#https?://\S*#','',$p);	  	 
	  	 $p = preg_replace('/[!-)\+-,\.\/:-@[-`\{-~]|\*|#|’|-/',' ',$p);
	
			// [!-) ascii 33-41
			// \. ascii 46
			// \/ ascii 47
			// :-@ ascii 58-64
			// [-` ascii 91-96
			// \{-~ ascii 123-126
		
		$pwords = array_unique(explode(' ',$p));
			
		foreach($pwords as $t)
		{
			if (!$t) continue;
			if (preg_match('#\d#',$t)) continue;
			$t = strtolower($t);
			if (!isset($dict[$t])) $dict[$t] = $n; else $dict[$t]+=$n;
		}	
	}
	
	$db = init();
	$doccount = $db->querysingle('SELECT count(link) FROM posts');
	$db->exec("CREATE VIRTUAL TABLE temp.terms USING fts4aux(main,posts);");
	$res = $db->query("SELECT DISTINCT term, documents FROM temp.terms WHERE col = '*' ");
	 
	$dict2 = array();
	while ($d = $res->fetchArray(SQLITE3_ASSOC)) 
	{
		$dict2[$d['term']] = log10($doccount/$d['documents']) ; //  IDF
	}
		 
	 $dict3 = array();
	 foreach($dict as $k=>$v)
	 {
		 if (isset($dict2[$k])) $dict3[$k] = $dict[$k] * $dict2[$k]; // TF*IDF
	 }
	 
	 arsort($dict3);
	 
	 $stopwords = array('about', 'after','anyone', 'because', 'being','better', 'check', 'diese','doesn', 'einem', 'einen', 'einfach', 'everything', 'every', 'everyone', 'found', 'getting', 'gerade', 'gewesen', 'going', 'gonna', 'great', 'haben', 'having', 'jetzt', 'meine', 'message','might','never', 'nicht', 'other', 'people', 'playing', 'really','right', 'region', 'seems', 'schon', 'should', 'someone', 'source', 'start', 'still', 'stuff','there', 'these', 'their', 'thing', 'things', 'think', 'those', 'times', 'today', 'translated','translation','users','using', 'video', 'watching', 'werden','which', 'while', 'would');
	 
	 $i = 0;
	 $links = array();
	 foreach($dict3 as $k=>$v)
	 {
		 if ($i>4) break; 
		 if (!stristr($query,$k) && mb_strlen($k,'UTF-8')>4 && !in_array($k, $stopwords) )
		 {
		 	$links[] = '<a href="search/noindex/'.$k.'">'.$k.'</a>'; 
		 	$i++;
		 }
	 }
	 
	 return join('<br>',$links);
	
}

function trendingPosts()
{
	global $tfRoot;
	$file = $tfRoot.'/site/cache/trendingPosts.txt'; 
	
	if (file_exists($file) && time() - filemtime($file) < 3600) 
	{
		$s = file_get_contents($file);
		$result = json_decode($s,true);
		
		
		
		return $result;
	}
	
	$db = init();
	
	$datelimit = date('Y-m-d H:i:s',strtotime('-1 day', time()));
	$q = "SELECT link, description, followers, user, 2 as found, image, media, pubdate, indexdate FROM posts ORDER BY pubdate DESC limit 2000";
	
	$list = $db->query($q);
	$dict = array();
	if (!$list) return '';
	while ($d = $list->fetchArray(SQLITE3_ASSOC)) 
	{
		$wl = array_unique(wordList($d['description']));  
		$wl = array_filter($wl, function($x) { if (strlen($x) < 5) return false; return true; });
		sort($wl); 
		$d['wordlist'] = $wl;
		$d['pd'] = intval(strtotime($d['pubdate']));
		$dict[$d['link']] = $d;
	}
	foreach($dict as $k=>$x)
	{
		$score = 0;
		foreach($dict as $y)
		{
			if ($x['user'] != $y['user']) 
			{
				$sc = count(array_intersect($x['wordlist'], $y['wordlist']));	
				if ($sc) $sc /=  max(100,count($x['wordlist']));
				$score += $sc;
			}	
		}
		$dict[$k]['score'] = $score * log10(intval($y['followers'])+1);
		// $dict[$k]['description'] .= $dict[$k]['pd'];
	}
	
	uasort($dict, function($x,$y) { return $y['score'] - $x['score']; });
	
	// downvote following posts the same user
	
	$users = array();
	foreach($dict as $k=>$x)
	{
		if (isset($users[$x['user']]))
		{
			$users[$x['user']]++;
			$x['score'] /= pow($users[$x['user']],1.5);
			$result[$k] = $x;
		}
		else
		{
			$users[$x['user']] = 1;
		}
	}
	
	uasort($dict, function($x,$y) { return $y['score'] - $x['score']; });
	
	$dict = array_slice($dict,0,100);
	
	// uasort($dict, function($x,$y) { return $y['pd'] - $x['pd']; });
	
	$s = json_encode($dict);
	file_put_contents($file, $s);
	
	return $dict;
}




