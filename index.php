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
 *  @version 1.8 2023-03-19
 */

@define('CRAWLER',true);
include_once 'api.php';

$userlabel = '';

$msg = filter_input(INPUT_GET, 'msg', FILTER_SANITIZE_STRING);
$join = filter_input(INPUT_GET, 'join', FILTER_SANITIZE_STRING);
$submitjoin = filter_input(INPUT_GET, 'submitjoin', FILTER_SANITIZE_STRING);
$userlabel = trim(preg_replace('/\t+/', '',filter_input(INPUT_GET, 'userlabel', FILTER_SANITIZE_STRING)));
$query = trim(preg_replace('/\t+/', '',filter_input(INPUT_GET, 'query', FILTER_SANITIZE_SPECIAL_CHARS)));
$noindex = filter_input(INPUT_GET, 'noindex', FILTER_SANITIZE_STRING);
$name = filter_input(INPUT_GET, 'name', FILTER_SANITIZE_STRING);
$doindex = 1 - $noindex;

if ($name && substr($name,0,9)=='rest/api/') { include 'inc/rest.php'; exit; }
if ($name && substr($name,0,15)=='search/noindex/') { $query = substr($name,15);  $noindex = 1; } 
elseif ($name && substr($name,0,7)=='search/') { $query = substr($name,7);  }  // support for pretty URL
elseif ($name && substr($name,0,13)=='tags/noindex') { $query = '#'.substr($name,13); $noindex = 1;  } 
elseif ($name && substr($name,0,5)=='tags/') { $query = '#'.substr($name,5);  }  // support for pretty URL for tag search

?>

<html>
<head>
	<title>Tootfinder</title>
	<base href="https://tootfinder.ch/">
	<link rel='stylesheet' href='./site/files/style20230218.css'>
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
</head>
<body>
	<div class="header">
	<h1>Tootfinder</h1>
	<h4><i>Opt-in global Mastodon full text search. <a href="index.php?join=1">Join the index!</a></i></h4>
	
	<?php 
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

        $found = 0;
        $similar = false;
        $newposts = false; if (isset($_GET['submitnew'])) $newposts = true;
        $allpost = false;

        $list = array();
        
        $descriptions = array();
        $echo = array();

        foreach(query($query, $doindex, $newposts, $allpost) as $row)
        {
	        if (isset($list[$row['link']])) continue; // there should not be duplicates, should there?
	        $list[$row['link']]=1;

	        if ($row['found']<2 && !$similar) { echo '<div class="post">No exact results. Similar results found.</div>'; $similar = true;}
          preg_match('/@([a-zA-Z0-9_]+)@([a-zA-Z0-9-_]+\.[a-zA-Z0-9.-_]+)/',$row['user'],$matches); 
	        $username = @$matches[1];
	        $host = @$matches[2];
	        $signature = '<span class="signature">'.$row['user'].'<br><a href="'.$row['link'].'" target="_blank" rel="nofollow">'.$row['pubdate'].'</a></span>';
	        $line = $row['description'];

	        $line = handleHTMLHeader($line);
	        $line = handleMentions($line);
	        $line = handleHashtags($line);
	        // fix paragraphs
	        $line = preg_replace("/<\/p>.*?<p>/",'<br>',$line);


	        $line .= handleMedia(@$row['media']);

	        $line = handleContentWarning($line);

			$line = '<div class="post" id="'.$row['link'].'"><div class="postheader"><a href="https://'.$host.'/users/'.$username.'" target="_blank" rel="nofollow"><img src="'.$row['image'].'" class="avatar"> </a>'. $signature.'</div><div class="postbody">'.$line.'</div></div>';
			
	        $echo[] = $line.PHP_EOL;

	        $found++;
	        
	        
	        $descriptions[$row['description']] = 1;
	        
        }
               
        echo join('',$echo);

		if (!$found) echo '<div class="post">No results.</div>';



	}
	else
	{

		$jointheinxex = '<div class="post"><p><b>Join the index (step 1)</b>
	    <p>You need first to provide consent via your profile.
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
	  <p>If you change your mind, just remove the magic word in your profile. Tootfinder will stop indexing your account and your toots will eventually disappear from our database (after 14 days).
	    </div>';

		if ($join) echo $jointheinxex;

		echo '<div class="post"><p><b>Full text search on Mastodon</b>
	<p>Imagine searching any post on Mastodon. This is now possible - at least for the posts of users who opt in.
	<p>Tootfinder indexes all public posts of consenting users and makes them searchable for 14 days. If you want to be part of it, <a href="index.php?join=1">join the index</a>.
	</div>';

		echo '<div class="post"><img src="site/files/elefant1.jpg" width=200px></div>';

		echo '<div class="post"><p><b>Search syntax</b>

	<p>The search is case-insensitive. You can append * to the end of a word. You can use NEAR, OR and the prefix -. 
		<ul>
		<li>san franc*</li>
		<li>san NEAR francisco</li>
	    <li>san OR francisco</li>
	    <li>san -francisco</li>
		</ul>
	</div>';

	echo '<div class="post"><b><p>More about search</b>
	<p>If the crawler does not find an exact result, it looks for similar results. Click on the avatar to access the user, click on the date to access the post on Mastoton. Click on the image to access the original image.</div>';

	echo '<div class="post"><p><b>Privacy note </b>
	<p>This is pure opt-in: If you are not interested, just do not join the index. If you quit the index, your posts will be removed from the index after 14 days.</p>
<p><a href="privacy.php">Privacy statement</a></div>';

	 echo '<div class="post"><b><p>Trending words</b><p>'.trendingWords().'
	 <p><a href="search/%3Anow">Trending posts</a></div>';


$pq = '';
		foreach(popularQueries() as $elem)
		{
			$pq .= '<a href="search/noindex/'.urlencode($elem['query']).'">'.$elem['query'].'</a><br>';
		}

		echo '<div class="post"><b><p>Popular queries</b>
	<p>'.$pq.'</div>';

	if (!$join) echo $jointheinxex;

		echo '<div class="post"><p><b>Implementation</b>
		<p>Tootfinder uses the public Mastodon API for the profile and the JSON feed. The  feeds are consulted on an optimized frequency, indexed in a SQLite database and deleted after 14 days.</p>
	<p>Check out the <a href="wiki/index.php" target="_blank">Tootfinder Wiki</a></div>';

echo '<div class="post"><p><b>Contact</b>
		<p><a rel="me" href="https://tooting.ch/@buercher" target="_blank">@buercher@tooting.ch</a>
	<p>v'.$tfVersion.' 2023-03-19<p>
	';
	echo getinfo();
	echo "<p>Index ".indexStatus();
	echo "</div>";
	
	

	}


	?>
	</div>
	<div style="clear:both"></div>

</body>
</html>
