<?php 
	
	
/**
 *	info functions for statistics
 * 
 *  @version 1.2 2023-02-12
 */
	
	
if (!defined('CRAWLER')) die('invalid acces');



function getinfo()
{
	$db = init(true);
	$sql = 'SELECT count(link) FROM posts';
	$posts = $db->querySingle($sql);
	$sql = 'SELECT count(user) FROM users';
	$users = $db->querySingle($sql);
	$db->close();
	$sql = 'SELECT count(query) FROM queries';
	$db = initQueries(true);
	$queries = $db->querySingle($sql);
	$db->close();

	return "$users users, $posts posts, $queries queries";
	
}

function indexStatus()
{
	$db = init(true);
	$sql = 'SELECT min(priority) FROM users WHERE priority > 0';
	$minpriority = $db->querySingle($sql);
	$delay = round(($minpriority-time())/60);
	if (!$delay) return 'in time';
	elseif ($delay == 1) return '1 minute ahead';
	elseif ($delay == -1) return '1 minute behind';
	elseif ($delay > 0) return $delay.' minutes ahead';
	elseif ($delay < 0) return -$delay.' minutes behind';
}


function popularQueries()
{
	
	if (!$db = initQueries(true)) return array();
	
	$sql = 'SELECT DISTINCT query, count(query) as c FROM queries WHERE results > 0 GROUP BY query ORDER BY c DESC limit 5'; 
		$list = $db->query($sql);
	
	$result = array();
	while ($d = $list->fetchArray(SQLITE3_ASSOC)) 
	{
		$result[]=$d;
	}
	
	$db->close();
	
	return $result;
	
}



