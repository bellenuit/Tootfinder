# Tootfinder

matti@belle-nuit.com @buercher@tooting.ch
MIT license 2023

This is the source code of the search enginge Tootfinder.

Tootfinder indexes recent posts from consenting Mastodon users. The users manifest consent with a magic word on their profile. The posts are available on the website for search for 14 days and then permanenty removed.

Users can revoke the consent by removing the magic word on the profile. Mentions are not displayed in the search results.

Tootfinder uses the public API (JSON, fallback to HTML) and the public API feed from the user. The posts are indexed in an virtual FTS3 table of a SQLite database.

The website is https://tootfinder.ch

There is a wiki https://tootfinder.ch/wiki/index.php 

which has also a list of current issues https://tootfinder.ch/wiki/index.php?name=issues

There is a privacy statement https://tootfinder.ch/privacy.php

Folder structure

- api.php API entry point, include all source files
- cron.php entry point for crontab (disabled from htaccess) 
- index.php main entry point of webserver
- inc/
	- crawl.php get remote files and index them
	- db.php create the databaes
	- info.php get statistics
	- query.php search the posts
	- skin.php format the posts for output
	- user.php handle users
	- utilities.php the rest
- site/
	- bak/ last copy of the feed
	- configuration.php
	- feeds/ temporary copies of remote feeds
	- files/ website files (css and images)
	- profiles/ temporary copies of remote profile pages
	- rejected/ feeds the server could not handle
	
Note that there are .htaccess files to limit remote access of most of the files



