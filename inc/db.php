<?php 
	
if (!defined('CRAWLER')) die('invalid acces');


/**
 *	Database creators.
 *  
 *  There are two separate databases for the indexed and the queries.
 *  Whenever possible ask only for read access. Reading is concurrent, writing not.
 * 
 *  @version 1.4 2023-02-20
 */


function init($readonly = false)
{	
	global $tfRoot;
	$path = $tfRoot.'/site/index.db';
	
	if (!file_exists($path)) $readonly = false;
	
	try
	{
		if ($readonly)
			$db = new SQLite3($path, SQLITE3_OPEN_READONLY);
		else
			$db = new SQLite3($path);
	}
	catch (Exception $err)
	{
		echo '<p>init errror index.db '.$err->getMessage(); return null;
	}
			
	if (!$db->busyTimeout(5000))  // sql tries to connect during 5000ms
	{
		echo '<p>db busy errror '.$db->lastErrorMsg(); return null; 
	}
	
	$db->createFunction('score', 'score'); // must be also for readonly!
	$db->createFunction('time2date', 'time2date'); // must be also for readonly!
	
	
	
	if ($readonly) return $db;
	
	if (!$db->exec('CREATE VIRTUAL TABLE IF NOT EXISTS posts USING fts3(link, user, description, pubdate, image, media, soundex, followers, indexdate)'))
	{
		echo '<p>create table posts error '.$db->lastErrorMsg();
	}
	
	
	
	if (!$db->exec('CREATE TABLE IF NOT EXISTS users (user, host, label, id, priority)'))
	{
		echo '<p>create table users error '.$db->lastErrorMsg();
	}
	
	

	if (!$db->exec('CREATE INDEX IF NOT EXISTS users_label ON users (label)'))
	{
		echo '<p>create table queries error '.$db->lastErrorMsg();
	}
	if (!$db->exec('CREATE INDEX IF NOT EXISTS users_priority ON users (priority)'))
	{
		echo '<p>create table queries error '.$db->lastErrorMsg();
	}

	
	
	return $db;	
	
}

function initQueries($readonly = false)
{	
	global $tfRoot;
	$path = $tfRoot.'/site/queries.db';
	
	if (!file_exists($path)) $readonly = false;
	
	try
	{
		if ($readonly)
			$db = new SQLite3($path, SQLITE3_OPEN_READONLY);
		else
			$db = new SQLite3($path);
	}
	catch (Exception $err)
	{
		echo '<p>init errror queries.db '.$err->getMessage(); return null;
	}
			
	if (!$db->busyTimeout(5000))  // sql tries to connect during 5000ms
	{
		echo '<p>db busy errror '.$db->lastErrorMsg(); return null; 
	}
	
	if ($readonly) return $db;
	
	if (!$db->exec('CREATE TABLE IF NOT EXISTS queries (query, date, results)'))
	{
		echo '<p>create table queries error '.$db->lastErrorMsg();
	}
	
	if (!$db->exec('CREATE INDEX IF NOT EXISTS queries_query ON queries (query)'))
	{
		echo '<p>create table queries error '.$db->lastErrorMsg();
	}

	
	return $db;	
	
}




