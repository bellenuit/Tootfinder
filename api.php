<?php 
	
/**
 *	main entry point, includes all function
 * 
 *  @version 1.8 2023-03-19
 */
	
	
if (!defined('CRAWLER')) die('invalid acces');

$tfRoot = dirname(__FILE__);
$tfVersion = '1.8';


include_once $tfRoot.'/inc/crawl.php';
include_once $tfRoot.'/inc/db.php';
include_once $tfRoot.'/inc/index.php';
include_once $tfRoot.'/inc/info.php';
include_once $tfRoot.'/inc/query.php';
include_once $tfRoot.'/inc/read.php';
include_once $tfRoot.'/inc/skin.php';
include_once $tfRoot.'/inc/user.php';
include_once $tfRoot.'/inc/utilities.php';
include_once $tfRoot.'/site/configuration.php';





