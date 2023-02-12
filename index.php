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
 *  @version 1.2 2023-02-12
 */
	
?>

<html>
<head>
	<title>Tootfinder</title>
	<link rel='stylesheet' href='./site/files/style.css'>
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
</head>
<body>
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
	</form>	
	
	<?php
				
	if ($query)
	{
		
        $found = false;
        $similar = false;
        
        echo '<div class="container">';
        
        foreach(query($query, $doindex) as $row)
        {
	        if ($row['found']<2 && !$similar) { echo '<div class="post">No exact results. Similar results found.</div>'; $similar = true;}
	        
	        preg_match('/@([a-zA-Z0-9]+)@([a-zA-Z0-9]+\.[a-zA-Z0-9]+)/',$row['user'],$matches); 
	        $username = @$matches[1];
	        $host = @$matches[2];
	        $signature = '<span class="signature">'.$row['user'].'<br><a href="'.$row['link'].'" target="_blank">'.$row['pubdate'].'</a></span>';
	        $line = $row['description'];
	        $line = handleContentWarning($line);
	        $line = handleMentions($line);
	        $line = handleHashtags($line);
	        // touch devices hack
	        $line = '<a href="#link" class="link">'.$line.'</a>';
	        // fix paragraphs
	        $line = preg_replace("/<\/p>.*?<p>/",'<br>',$line);
	        
	        $line = '<div class="postheader"><a href="https://'.$host.'/users/'.$username.'" target="_blank"><img src="'.$row['image'].'" class="avatar"> </a>'. $signature.'</div><div class="postbody">'.$line.'</div>';
	        if (@$row['media'])
	        {
	            foreach(explode(' ',$row['media']) as $m)
	            $line .= '<div class="media"><a href="'.$m.'"><img src="'.$m.'" class="media"></a></div>';
	        }
	        $line = '<div class="post">'.$line.'</div>';
	       
	        //print_r($row);
	        echo $line.PHP_EOL;
	        
	        $found = true;
        }
		
		if (!$found) echo '<div class="post">No results.</div>';
		
		echo '</div>';
		echo '<div style="clear:both"></div>';
		
	}
	else
	{
		
		$jointheinxex = '<div class="post"><p><b>Join the index (step 1)</b>
	    <br>You need first to manifest your consent in your profile.
	    Place the magic word anywhere in your profile. Possible values:
	    <ul><li>tootfinder</li>
	    <li>tfr</li>
	    <li>#tfr</li>
		</ul>
	    </div>

		<div class="post"><p><b>Join the index (step 2)</b>
	    <br>Submit us your full username.
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
	
	echo '<div class="post postwarning"><p><b>Notice: Opt-in has changed</b></p>
	<p>Starting from February 12, the opt-in has changed. OAuth is not longer used. To manifest consent, users place a magic word in their profile.  Users having joined bevor February 12th can add the magic word to their profile until Februar 19th.
	</div>';
	
	echo '<div class="post"><b><p>More about search</b>
	<p>If the crawler does not find exact result, it looks for similar results. Click on the avatar to access the post on Mastoton. Click on the image to access original.</div>';

	
	echo '<div class="post"><p><b>Privacy note </b>
	<p>This is pure opt-in: If you are not interested, just do not join the index. If you quit the index, your posts are removed from the index.</p></div>';

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
	</div>';

echo '<div class="post"><p><b>Contact</b>
		<p><a rel="me" href="https://tooting.ch/@buercher" target="_blank">@buercher@tooting.ch</a>
	<p>12.2.2023 v1.2<p>
	';
	echo getinfo();
	echo "<p>Check out the <a href='wiki/index.php' target='_blank'>Tootfinder Wiki</a>";
		
		echo '</div><div style="clear:both"></div>';
		
	}		
	?>
	
	
		
	
	
	
			
	
	 
</body>
</html>

