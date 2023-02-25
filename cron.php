<?php
	
/**
 *	Indexes the crawler. This is called from a cron tab 
 *  In production, htaccess refuses external access
 * 
 *  @version 1.5 2023-02-25
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
	
			
	$userlabel = trim(preg_replace('/\t+/', '',filter_input(INPUT_GET, 'userlabel', FILTER_SANITIZE_STRING)));

	$echo = '<form method = "get" action ="cron3.php"> 
		<input type = "text" name = "userlabel" placeholder="@user@example.com" value = "'.$userlabel.'"> <input type = "submit" name ="submitjoin" value="Join Debug"> 
		<input type = "submit" name ="submitdelete" value="Delete user and posts" color="red"></form>';
		
	
	if ($userlabel && isset($_GET['submitjoin']))
	{
		 $tfDebug = '';
		 $msg = addUser($userlabel);
	
		 if (stristr($msg,'class="error"'))
		 	$echo .= $msg.$tfDebug;
		 else
		 {
		 	$echo .= '<p>ok';
		 	
		 	
		 	
		 	$echo .= $tfDebug;
		 	
		 }
	}
	
	elseif ($userlabel && isset($_GET['submitdelete']))
	{
		 $tfDebug = '';
		 $sql =  "DELETE FROM users WHERE label = '$userlabel'; DELETE FROM posts WHERE user = '$userlabel'";
		 $db = init();
		 $db->exec($sql);
		 $echo .= '<p>'.$sql;
		 
		// delete bak file
		@unlink($tfRoot.'/site/bak/'.$label.'.json');
		@unlink($tfRoot.'/site/bak/'.$label.'.rss');
		// delete profile files
		@unlink($tfRoot.'/site/profile/'.$label.'.json');
		@unlink($tfRoot.'/site/profile/'.$label.'.html');
		 
		 
	} 

	else
	{	
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
	}
	
	echo $echo;

	?>	 
</body>
</html>

