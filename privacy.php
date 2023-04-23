<?php
	
/**
 *	tootfinder.ch
 *
 *  opt-in search engine for mastoton posts
 *
 *  privacy note
 *
 *  matti@belle-nuit.com
 *  @buercher@tooting.ch
 * 
 *  @version 2.0 2023-04-23
 */
	
?>

<html>
<head>
	<title>Tootfinder privacy statement</title>
	<link rel='stylesheet' href='./site/files/style20230218.css'>
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
</head>
<body>
	<div class="header">
	<p><a href="index.php">Back to index</a></p>
	<h1>Tootfinder privacy statement</h1><br>
	</div>
	<div class="container">
	
	<div class="post">
	<h4>Principles</h4>
	<p>The application does only record data as far as it is needed for the purpose of the application. It does only index data that is public. Data that is obsolete will be deleted within two weeks.
	</div>	
	
	<div class="post">
	<h4>Purpose</h4>
	<p>The purpose of the application is to make recent posts of consenting Mastodon users searchable.
	</div>	
	
	<div class="post">
	<h4>Visitor data</h4>
	<p>The application does not record visits. The underlying webserver does create logfiles of metada that include IP number, a timestamnp and the URI. The logfiles are deleted by the webserver after a month.
	</div>	
	
	<div class="post">
	<h4>Query data</h4>
	<p>For each query that does give results, the query, the timestamp and the number of results are recorded in the database. The data is used for statistical purposes and deleted after 3 months.
	</div>	

	<div class="post">
	<h4>Consenting user data</h4>
	<p>Users that opt in are only recorded if they manifest their consent with a magic word ("tootfinder","tfr" or "searchable") on their Mastodon profile. For each verified user host, username, label and id are recorded and the public feed is indexed. A priority field is calculated for each user to optimise the frequency of the crawler.
	</div>	
	
	<div class="post">
	<h4>Feed data</h4>
	<p>The application indexes the feeds of the consenting users. Posts that are public and that are neither replies nor boosts are indexed. For each post, the link, the content, the links and the descriptions of the attachments (media, card) and a timestamp are indexed as while the users name, its avatar and its current follower count.
	</div>	
	
	<div class="post">
	<h4>Mentions</h4>
	<p>Mentions in posts are removed before indexing. It might me possible that a post cites names thet are not mentions.
	</div>	
	
	<div class="post">
	<h4>Data sources</h4>
	<p>The application uses the public API of Mastodon to get the profile of the user and the feed. If the API is not available, it may use the HTML source of the user page and the RSS feed.
	</div>
	
	<div class="post">
	<h4>Reuse of results</h4>
	<p>Users might share the URL of the result page. The application may provide a REST API to provide the search functionality to third party applications, eg. Mastodon clients.
	</div>
	
	<div class="post">
	<h4>Revoking consent</h4>
	<p>The user can revoke the consent at any time by removing the magic word on the Mastodon profile. The application checks the profile on a daily base. If the magic word is missing, the user becomes inactive and the posts of the user will not be searchable any more. 
	</div>
	
	<div class="post">
	<h4>Deleting data</h4>
	<p>Post and query data will be deleted after 3 months. User data will be deleted when the user has revoked consent and there are no posts from the user.
	</div>


	
	<div class="post">
	<h4>Cookies</h4>
	<p>The application does not use cookies, neither own cookies not third party cookies.
	</div>
	
	<div class="post">
	<h4>Source code</h4>
	<p>The source code of the application is available on <a href="https://github.com/bellenuit/Tootfinder">GitHub</a>. The production environment of the application may have hot fixes which are ahead of the source code.
	</div>
	
	
	
	<div class="post">
	<h4>Liabilty</h4>
	<p>The result page depends on the user posts and the visitor query. The application has no influence on both factors which are entirely user-driven and may not be hold liable for the content of the page.
	</div>	
	
	<div class="post">
	<h4>Contact</h4>
	<p>If you have a privacy issue, you can contact <a rel="me" href="https://tooting.ch/@buercher" target="_blank">@buercher@tooting.ch</a> with a direct message.
	<p>Version 2.0 2023-04-23
	</div>	
	
	
	</div>
	
			
	
	 
</body>
</html>

