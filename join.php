<?php

/**
 *	tootfinder.ch
 *
 *  opt-in search engine for mastoton posts
 *
 *  join page
 *
 *  matti@belle-nuit.com
 *  @buercher@tooting.ch
 *  @version 2.2 2023-09-10
 */

error_reporting(E_ERROR | E_WARNING | E_PARSE);
error_reporting( E_ALL | E_STRICT );
ini_set("display_errors",1); 


@define('CRAWLER',true);
include_once 'api.php';

$userlabel = '';

$msg = filter_input(INPUT_GET, 'msg', FILTER_SANITIZE_ENCODED);
$submitjoin = filter_input(INPUT_GET, 'submitjoin', FILTER_SANITIZE_ENCODED);
$userlabel = filter_input(INPUT_GET, 'userlabel', FILTER_SANITIZE_ENCODED);
if ($userlabel) $userlabel = urldecode($userlabel);
if ($userlabel) $userlabel = trim(preg_replace('/\t+/', '',$userlabel));
$subst = 0;

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
    <p><a href="index.php">Back to index</a></p>
	<h1>Join Tootfinder</h1>
	<h4>Opt-in global Mastodon full text search.</h4>
	
	<?php 
	
		
	if ($submitjoin)
	{
		 $msg = addUser($userlabel);

		 if (stristr($msg,'class="error"')) $join = 1; else { $query = $userlabel; }
	}

	if ($msg) echo '<div class="status">'.$msg.'</div>';

	?>

	</div>
	<div class="container">
	<div class="post"><p><b>Join the index (step 1)</b>
	    <p>You need first to provide consent via your profile.
	    Place the magic word anywhere in your profile (either bio or part of a well-formed link in a label). Possible values:
	    <ul><li>tootfinder</li>
	    <li>tfr</li>
	    <li>searchable</li>
		</ul>
	    <p>Wait some minutes, to let the server cache update your profile.</p>
	    </div>

		<div class="post"><p><b>Join the index (step 2)</b>
	    <p>Submit us your full username.
	    <form method = "get" action ="join.php">
		<input type = "text" name = "userlabel" placeholder="@user@example.com" value = "<?php echo $userlabel ?>">
		<p><input type = "submit" name ="submitjoin" value="Join">
	</form></div>
		<div class="post"><p><b>Quit the index</b></p>
	  <p>If you change your mind, just remove the magic word in your profile. Tootfinder will stop indexing your account and your toots will eventually disappear from our database (after 3 months).
	    </div>

		<div class="post"><p><b>Instance opt-in</b>
	<p>Instances can opt-in globally on <a href="instance.php">this page</a>. 
	<p>The page lists the instances that have opted in. These instances must declare the indexing in their ruleset. Users on these instances can still opt out having the magic word "noindex" in their profile.
	</div>


		<div class="post"><img src="site/files/elefant1.jpg" width=200px></div>


<div class="post"><p><b>Privacy note </b>
	<p>This is pure opt-in: If you are not interested, just do not join the index. If you quit the index, your posts will be removed from the index after 3 months.</p>
<p><a href="privacy.php">Privacy statement</a></div>

	
<div class="post"><p><b>Contact</b>
<p><a rel="me" href="https://tooting.ch/@buercher" target="_blank">@buercher@tooting.ch</a>
</div>
	</div>
	<div style="clear:both"></div>

</body>
</html>
