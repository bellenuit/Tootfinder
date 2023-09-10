<?php
	
/**
 *	Indexes the crawler. This is called from a cron tab 
 *  In production, htaccess refuses external access
 * 
 *  @version 2.2 2023-09-10
 */


?>
<html>
<head>
</head>
<body>
	<h1>Tootfinder crawler</h1>
	<?php
	$start = time();  
	proc_nice(10);
	ini_set('max_execution_time', '300');
	define('CRAWLER',true);
	include 'api.php';	
	
			
	$userlabel = trim(preg_replace('/\t+/', '',filter_input(INPUT_GET, 'userlabel', FILTER_SANITIZE_STRING)));

	$echo = '<form method = "get" action ="cron3.php"> 
		<input type = "submit" name ="submitindex" value="Index"> 
		<input type = "text" name = "userlabel" placeholder="" value = "'.$userlabel.'"> <input type = "submit" name ="submitjoin" value="Join Debug"> 
		<input type = "submit" name ="submitquery" value="Query"> 
		<input type = "submit" name ="submitdelete" value="Delete user and posts" style="color:red">
		<input type = "submit" name ="submitvacuum" value="Vacuum" style="color:red">
		<input type = "submit" name ="submittruncate" value="Truncate" style="color:red">
		<input type = "submit" name ="submitaddinstance" value="Add Instance" style="color:red"></form>';


	
		
    $ende = time();
	$echo .= sprintf('<p>Startup (%0d sec)',$ende - $start); 

	
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
    elseif ($userlabel && isset($_GET['submitaddinstance']))
	{
		 $tfDebug = '';
		 $msg = addInstance($userlabel);
	
		 if (stristr($msg,'class="error"'))
		 	$echo .= $msg.$tfDebug;
		 else
		 {
		 	$echo .= '<p>ok';
		 	
		 	
		 	
		 	$echo .= $tfDebug;
		 	
		 }
		 $db = init(true);
		 $echo .=  '<h4>Instances</h4>'.PHP_EOL;
		 $echo .= sqlTable($db,"SELECT * FROM instances");
		 $echo .=  '<h4>User Count</h4>'.PHP_EOL;
		 $echo .= sqlTable($db,"SELECT count(user) FROM users");
		 $db->close();
	}

	
    elseif ($userlabel && isset($_GET['submitquery']))
	{
		
		$tfDebug = '';
		$msg = query($userlabel);
		
		$lines = array();
		
		foreach($msg as $d)
		{
			$keys = array_keys($d);
			$values = array_values($d);
			if (!count($lines)) $lines []= '<tr><th>'.join('</th><th>',$keys).'</th></tr>';			
			$lines []= '<tr><td>'.join('</td><td>',$values).'</td></tr>';	
		}	
		
		$echo .= $tfDebug;
		$echo .= '<table>'.join($lines).'</table>';	  		 
		 	
		 			 
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
	elseif (isset($_GET['submitdvacuum']))
	{
		$db = init();
		$echo .= sqlTable($db,"VACUUM;");
		$db->close();
		$db = initQuery();
		$echo .= sqlTable($db,"VACUUM;");
		$db->close();
	}
	elseif (isset($_GET['submittruncate']))
	{
		//$db = init();
		//$echo .= sqlTable($db,"PRAGMA wal_checkpoint(TRUNCATE);");
		//$db->close();
		
		$echo .= cleanDb();
	}
	else
	{	
		$echo .= '<p>index.db '.round(filesize($tfRoot.'/site/index.db')/1024/1024).' MB';
		$echo .= '<br>queries.db '.round(filesize($tfRoot.'/site/queries.db')/1024/1024).' MB';
		
		$ende = $start = time();
		
		$tfDebug = '';
		
		crawl();
		
		$echo .= $tfDebug;
		
		
		$ende = time();
		$echo .= '<p>'.sprintf('%0d',$ende - $start).' seconds';
		
		$tfDebug = '';
		
		index();
		
		$echo .= $tfDebug;
		
		$ende = time();
		$echo .= '<p>'.sprintf('%0d',$ende - $start).' seconds';
		
		$db = init();
		//$db->exec('DELETE FROM posts');
		
		$echo .= '<h4>Newest post</h4>';
			$echo .= sqlTable($db,"SELECT user, pubdate, indexdate, followers, docid FROM posts ORDER BY docid DESC LIMIT 10");
		
		$ende = time();
		$echo .= '<p>'.sprintf('%0d',$ende - $start).' seconds';
			
		$echo .= '<h4>Priority</h4>';
		$echo .= sqlTable($db,'SELECT label, time2date(priority) as priority FROM users WHERE priority > 0 ORDER BY priority LIMIT 10;');
		
		$ende = time();
		$echo .= '<p>'.sprintf('%0d',$ende - $start).' seconds';
		
		if ($userlabel && isset($_GET['submitindex'])) 
{
		
		$db2 = initQueries(true);
		$echo .= '<h4>Queries</h4>';
		$limit = date('Y-m-d',strtotime('-2 day', time()));
		$echo .= sqlTable($db2,"SELECT DISTINCT query, count(query) as c FROM queries WHERE results > 0 AND date > '$limit' GROUP BY query ORDER BY c DESC limit 10;");
		
		$ende = time();
		$echo .= '<p>'.sprintf('%0d',$ende - $start).' seconds';
		
		$echo .= '<h4>Query timeline</h4>';
		$echo .= sqlTable($db2,"SELECT substr(date,1,10) as date, count(query) FROM queries GROUP BY substr(date,1,10) ORDER by date DESC");
		
		$ende = time();
		$echo .= '<p>'.sprintf('%0d',$ende - $start).' seconds';
		
		$echo .= '<h4>Post timeline</h4>';
		$db = init();
		$echo .= sqlTable($db,"SELECT substr(pubdate,1,10) as date, count(link) FROM posts GROUP BY substr(pubdate,1,10) ORDER by date DESC");
		
		$ende = time();
		$echo .= '<p>'.sprintf('%0d',$ende - $start).' seconds';
		
		$echo .= '<h4>Followers</h4>';
		$echo .= sqlTable($db,"SELECT DISTINCT user, MAX(CAST(followers AS NUMERIC)) AS followers FROM posts GROUP BY user ORDER by CAST(followers AS NUMERIC) DESC LIMIT 20");
		
		$ende = time();
		$echo .= '<p>'.sprintf('%0d',$ende - $start).' seconds';
					
		if (rand(0,1000)<10)  popularQueries(true);
		if (rand(0,1000)>990)  trendingWords(true);
		
		$echo .= '<p>'.sprintf('%0d',$ende - $start).' seconds';
}		
		file_put_contents('site/job.txt',$echo);
	}
	
	echo $echo;

	?>	 
</body>
</html>

