<?php
	
/**
 *	tootfinder.ch
 *
 *  opt-in search engine for mastoton posts
 *
 *  instance opt-in
 *
 *  matti@belle-nuit.com
 *  @buercher@tooting.ch
 * 
 *  @version 2.2 2023-09-10
 */

@define('CRAWLER',true);
include_once 'api.php';
 
$submitjoin = filter_input(INPUT_GET, 'submitjoin', FILTER_SANITIZE_STRING);
$instance = filter_input(INPUT_GET, 'instance', FILTER_SANITIZE_STRING);

?>

<html>
<head>
	<title>Tootfinder instance opt-in</title>
	<link rel='stylesheet' href='./site/files/style20230218.css'>
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
</head>
<body>
	<div class="header">
	<p><a href="index.php">Back to index</a></p>
	<h1>Tootfinder instance opt-in</h1><br>
	</div>
	
	<?php
	
	if ($submitjoin && $instance)
	{
		 $msg = addInstance($instance);
		 if ($msg) echo '<div class="status">'.$msg.'</div>';
	}
	
	?>
	
	
	
	<div class="container">
	
	<div class="post">
	<h4>Principles</h4>
	<p>Mastodon instance administrators can opt in their instance on this page. If the instance opts in, all users of the instance are indexed, except if the opt-out (see below).
	</div>	

	<div class="post">
	<h4>Technical conditions</h4>
	<p>Only the Mastodon API is supported. Instances must have an the following open API endpoints: <b>/api/v1/instance/rules</b> and <b>/api/v1/directory?</b>.
	</div>	

	<div class="post">
	<h4>Instance rules (step 1)</h4>
	<p>Instances must add an <b>instance rule</b> with the text <p><b>All public posts from this instance are indexed by tootfinder.ch.</b><p>The purpose of this rule is that all users of the instance are aware of the opt-in. Instances commit themselves to actively communicate the opt-in to their users (rule of honor: this is something tootfinder.ch cannot verify).
	</div>	
	
	
	<div class="post">
	<h4>Instance opt-in (step 2)</h4>
	     <p>Submit the domain name of the host
	    <form method = "get" action ="instance.php?action=join">
		<input type = "text" name = "instance" placeholder="example.com" value = "<?php echo $instance?>">
		<p><input type = "submit" name ="submitjoin" value="Join">
		</form>
	</div>
	
		
	
	<div class="post">
	<h4>Instance opt-out</h4>
	<p>The rules of the instance are verified on a regularly basis. If the rules do not contain the magic sentence any more, the indexing stops. However, the instances are not removed from the list below. To be explicitely unlisted on the list, instance administrator must submit join again after removing the rule. Users that individually opt-in will stay on the index. 
	</div>	
	
	
	<div class="post">
	<h4>List of instances that have opted in</h4>
	<?php echo instanceList();
	?>
	</div>	
	
	<div class="post">
	<h4>Opt-out for users</h4>
	<p>Individual users of these instances can opt out, if they have the magic word ("noindex") on their Mastodon profile. 
	</div>

	
	</div>
	
			
	
	 
</body>
</html>

