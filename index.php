<?php
	
/**
 *	tootfinder.ch
 *
 *  opt-in search engine for mastoton posts
 *
 *  main entry point
 *
 *  matti@belle-nuit.com
 *  @buercher@tooting.ch
 * 
 *  @version 1.3 2023-02-18
 */
	
?>

<html>
<head>
	<title>Tootfinder</title>
	<link rel='stylesheet' href='./site/files/style20230218.css'>
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
</head>
<body>
	<div class="header">
	<h1>Tootfinder</h1>
	<h4><i>Proof of concept of an opt-in global Mastodon search. <a href="index.php?join=1">Join the index!</a></i></h4>
	
	<?php 
		
	@define('CRAWLER',true);
    include_once 'api.php';
    $userlabel = '';

	$msg = filter_input(INPUT_GET, 'msg', FILTER_SANITIZE_STRING);
	$join = filter_input(INPUT_GET, 'join', FILTER_SANITIZE_STRING);
	$submitjoin = filter_input(INPUT_GET, 'submitjoin', FILTER_SANITIZE_STRING);
	$userlabel = filter_input(INPUT_GET, 'userlabel', FILTER_SANITIZE_STRING);
	$query = filter_input(INPUT_GET, 'query', FILTER_SANITIZE_SPECIAL_CHARS); 
	$noindex = filter_input(INPUT_GET, 'noindex', FILTER_SANITIZE_STRING);
	$doindex = 1 - $noindex;

	
	if ($submitjoin)
	{
		 $msg = addUser($userlabel);
	
		 if (stristr($msg,'class="error"')) $join = 1; else $query = $userlabel;
	}
			
	if ($msg) echo '<div class="status">'.$msg.'</div>';
		
	?>
	
	<p><form method = "get" action ="index.php">
		<input type = "search" name = "query" placeholder="Search..." value = "<?php echo $query; ?>">
		<input type = "submit" name ="submitquery" value="Search">
		<?php if ($query) echo '<input type = "submit" name ="submitnew" value="Date ↓">' ?>
	</form>	
	</div>
	<div class="container">
	<?php
				
	if ($query)
	{
		
        $found = false;
        $similar = false;
        $newposts = false; if (isset($_GET['submitnew'])) $newposts = true;
        $allpost = false;
        
        $list = array();
        
        foreach(query($query, $doindex, $newposts, $allpost) as $row)
        {
	        if (isset($list[$row['link']])) continue; // there should not be duplicates, should there?
	        $list[$row['link']]=1;
	        
	        if ($row['found']<2 && !$similar) { echo '<div class="post">No exact results. Similar results found.</div>'; $similar = true;}
	        
	        preg_match('/@([a-zA-Z0-9]+)@([a-zA-Z0-9]+\.[a-zA-Z0-9]+)/',$row['user'],$matches); 
	        $username = @$matches[1];
	        $host = @$matches[2];
	        $signature = '<span class="signature">'.$row['user'].'<br><a href="'.$row['link'].'" target="_blank">'.$row['pubdate'].'</a></span>';
	        $line = $row['description'];
	        $line = handleContentWarning($line);
	        $cw = false; if (stristr($line,'class="contentwarninglabel"')) $cw = true; 
	        $line = handleMentions($line);
	        $line = handleHashtags($line);
	        // touch devices hack
	        // $line = '<a href="#link" class="link">'.$line.'</a>';
	        // fix paragraphs
	        $line = preg_replace("/<\/p>.*?<p>/",'<br>',$line);
	        
	        
	        $line .= handleMedia(@$row['media']);

	        if ($cw)
				$line = '<div class="contentwarning" onclick="this.style.visibility=\'visible\'">'.$line.'</div>';

				$line = '<div class="post"><div class="postheader"><a href="https://'.$host.'/users/'.$username.'" target="_blank"><img src="'.$row['image'].'" class="avatar"> </a>'. $signature.'</div><div class="postbody">'.$line.'</div></div>';
				
	       
	        //print_r($row);
	        echo $line.PHP_EOL;
	        
	        $found = true;
        }
		
		if (!$found) echo '<div class="post">No results.</div>';
		
		
		
	}
	else
	{
		
		$jointheinxex = '<div class="post"><p><b>Join the index (step 1)</b>
	    <p>You need first to manifest your consent in your profile.
	    Place the magic word anywhere in your profile (either bio or part of a well-formed link in a label). Possible values:
	    <ul><li>tootfinder</li>
	    <li>tfr</li>
	    <li>searchable</li>
		</ul>
	    </div>

		<div class="post"><p><b>Join the index (step 2)</b>
	    <p>Submit us your full username.
	    <form method = "get" action ="index.php?action=join">
		<input type = "text" name = "userlabel" placeholder="@user@example.com" value = "'.$userlabel.'">
		<p><input type = "submit" name ="submitjoin" value="Join">
	</form></div>
		<div class="post"><p><b>Quit the index</b></p>
	    <p>If you change your mind, just remove the magic word in your profile. Tootfinder will indexing your account and your toots will eventually disappear from our database (after 14 days).
	    </div>';
		
		if ($join) echo $jointheinxex;
		
		echo '<div class="post"><img src="site/files/elefant1.jpg" width=200px></div>';
		
		echo '<div class="post"><p><b>Search syntax</b>
	<p>The search is case-insensitive. You can append * to search for words starting with the search term but not preprend *. Words must be 3 letters long at least. You can use NEAR, NOT, AND and OR. 
	</div>';
	
	echo '<div class="post postwarning"><p><b>Notice: Set your magic word now</b></p>
	<p>OAuth is no longer used for opt-in. Users having joined in with OAuth should add now the magic word to manifest consent. From February 20th, accounts without magic word will not be indexed anymore.
	</div>';
	
	echo '<div class="post"><b><p>More about search</b>
	<p>If the crawler does not find exact result, it looks for similar results. Click on the avatar to access the user, click on the date to access the post on Mastoton. Click on the image to access original.</div>';

	
	echo '<div class="post"><p><b>Privacy note </b>
	<p>This is pure opt-in: If you are not interested, just do not join the index. If you quit the index, your posts will eventually disappear from the index.</p></div>';

	if (!$join) echo $jointheinxex;
		
		$pq = '';
		foreach(popularQueries() as $elem)
		{
			$pq .= '<a href="index.php?noindex=1&query='.urlencode($elem['query']).'">'.$elem['query'].'</a><br>';
		}
	
		
		echo '<div class="post"><b><p>Popular queries</b>
	<p>'.$pq.'</div>';
		
		echo '<div class="post"><p><b>Implementation</b>
		<p>Tootfinder uses the public Mastodon API. 
		The RSS feeds of the followers are consulted on a random frequency. The feeds are indexed in a SQLite database and deleted after 14 days.</p>
	<p>Check out the <a href="wiki/index.php" target="_blank">Tootfinder Wiki</a></div>';

echo '<div class="post"><p><b>Contact</b>
		<p><a rel="me" href="https://tooting.ch/@buercher" target="_blank">@buercher@tooting.ch</a>
	<p>18.2.2023 v1.3<p>
	';
	echo getinfo();
	echo "<p>Index ".indexStatus();
		
		
		
	}		
	?>
	</div>
	<div style="clear:both"></div>
	
		
	
	
	
			
	
	 
</body>
</html>

