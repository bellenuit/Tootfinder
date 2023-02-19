<?php
	
/**
 *	Indexes the crawler. This is called from a cron tab 
 *  In production, htaccess refuses external access
 * 
 *  @version 1.4 2023-02-20
 */

?>
<html>
<head>
</head>
<body>
	<h1>Tootfinder crawler</h1>
	<?php

	ini_set('max_execution_time', '300');
	define('CRAWLER',true);
	include 'api.php';
	
	
	$echo = '';
	
	
	$ende = $start = time();
	
	$echo .= crawl();
	
	
	
	$ende = time();
	$echo .= '<p>'.sprintf('%0d',$ende - $start).' seconds';
	
	$echo .= index();
	
	$ende = time();
	$echo .= '<p>'.sprintf('%0d',$ende - $start).' seconds';
	
	$db = init();
	//$db->exec('DELETE FROM posts');
	
	$echo .= '<h4>Newest post</h4>';
		$echo .= sqlTable($db,"SELECT user, pubdate, indexdate, followers FROM posts WHERE indexdate <> '' ORDER BY indexdate DESC LIMIT 10");
	
		
	$echo .= '<h4>Priority</h4>';
	$echo .= sqlTable($db,'SELECT label, time2date(priority) as priority FROM users WHERE priority > 0 ORDER BY priority LIMIT 10;');
	
	$echo .= '<h4>Content warning</h4>';
	$echo .= sqlTable($db,"SELECT user, indexdate FROM posts WHERE description LIKE '%contentwarning%' OR description LIKE '%<strong>%' ORDER BY indexdate DESC LIMIT 10;");
	
	$db2 = initQueries(true);
	$echo .= '<h4>Queries</h4>';
	$limit = date('Y-m-d',strtotime('-1 day', time()));
	$echo .= sqlTable($db2,"SELECT DISTINCT query, count(query) as c FROM queries WHERE results > 0 AND date > '$limit' GROUP BY query ORDER BY c DESC limit 10;");
		
	$ende = time();
	
	$echo .= '<p>'.sprintf('%0d',$ende - $start).' seconds';
	
	file_put_contents('site/job.txt',$echo);
	
	echo $echo;

	?>	 
</body>
</html>

