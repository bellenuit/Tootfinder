# Tootfinder

MIT license Copyright (c) 2023 Matthias Bürcher matti@belle-nuit.com @buercher@tooting.ch

This is the source code of the search enginge Tootfinder.

Tootfinder indexes recent posts from consenting Mastodon users. The users
manifest consent with a magic word on their profile. The posts are available on
the website for search for 3 months and then permanency removed.

Users can revoke the consent by removing the magic word on their profile
(*tootfinder*, *tfr* or *searchable*). The posts are available on the website
for search for 3 months and then permanently removed. Mentions are not displayed
in the search results.

Tootfinder uses the public API (JSON, fallback to HTML) and the public API feed from the user. The posts are indexed in an virtual FTS3 table of a SQLite database.

Website: https://tootfinder.ch

Wiki: https://tootfinder.ch/wiki/index.php

REST API: https://www.tootfinder.ch/rest/api/

Current issues: https://tootfinder.ch/wiki/index.php?name=issues

Privacy statement: https://tootfinder.ch/privacy.php

Folder structure

- api.php API entry point, include all source files
- cron.php entry point for crontab (disabled from htaccess)
- index.php main entry point of webserver
- inc/
	- crawl.php get remote postings files to local
	- db.php create the database
	- index.php analyze posting files
	- info.php get statistics
	- instance.php handling instance opt-in
	- query.php search the posts
	- read.php read posting files format 
	- rest.php handle REST API
	- skin.php format the posts for output
	- user.php handle users
	- utilities.php the rest
- instance.php interface for instance opt-in
- privacy.php privacy statement
- site/
	- bak/ last copy of the feed
	- cache/ cache of some indicators of website
	- configuration.php
	- deleted/ delete candidates
	- feeds/ temporary copies of remote feeds
	- files/ website files (css and images)
	- hostmeta/ cache of host-meta files
	- instancerules/ cache of instance rules
	- instancusers/ cache of instance users
	- outbox/ cache of outbox 
	- profiles/ temporary copies of remote profile pages
	- rejected/ feeds the server could not handle
	- webfinger/ cache of webfinger files

Note that there are .htaccess files to limit remote access of most of the files
