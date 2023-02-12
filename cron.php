<?php
	
/**
 *	Indexes the crawler. This is called from a cron tab 
 *  In production, htaccess refuses external access
 * 
 *  @version 1.2 2023-02-12
 */

?>
<html>
<head>
</head>
<body>
	<h1>Tootfinder crawler</h1>
	<?php
	ini_set('max_execution_time', '300'); //300 seconds = 5 minutes
	$start = time();
	define('CRAWLER',true);
    include 'api.php';
    
	echo '<h4>Crawl</h4>';
    echo crawl();
    $ende = time();
    echo '<p>'.sprintf('%0d',$ende - $start).' seconds';

	$start = time();
	echo '<h4>Indexed</h4>';
	
	echo index();
	
	$ende = time();
	
	echo '<p>'.sprintf('%0d',$ende - $start).' seconds';
	
	$start = time();
	
	$db = init();
	
	echo '<h4>Newest post</h4>';
	echo sqlTable($db,"SELECT user, pubdate, indexdate, followers FROM posts WHERE indexdate <> '' ORDER BY indexdate DESC LIMIT 10");

	
	echo '<h4>Priority</h4>';
	echo sqlTable($db,'SELECT priority, count(user) as c FROM users GROUP BY priority ORDER BY priority DESC LIMIT 10');
	
	echo '<h4>Followers</h4>';
	echo sqlTable($db,'SELECT followers, count(link) as c FROM posts GROUP BY followers ORDER BY followers DESC');
	
	echo '<p>'.sprintf('%0d',$ende - $start).' seconds';
	?>	 
</body>
</html>

