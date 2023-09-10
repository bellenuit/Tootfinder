<?php 
	
/**
 *	main entry point, includes all function
 * 
 *  @version 2.2 2023-09-10
 */

/*
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
*/
	
if (!defined('CRAWLER')) die('invalid acces');

$tfRoot = dirname(__FILE__);
$tfVersion = '2.2';
$tfVersionDate = '2023-09-10';
$tfDebug = '';


include_once $tfRoot.'/inc/crawl.php';
include_once $tfRoot.'/inc/db.php';
include_once $tfRoot.'/inc/index.php';
include_once $tfRoot.'/inc/info.php';
include_once $tfRoot.'/inc/query.php';
include_once $tfRoot.'/inc/read.php';
include_once $tfRoot.'/inc/skin.php';
include_once $tfRoot.'/inc/user.php';
include_once $tfRoot.'/inc/instance.php'; 
include_once $tfRoot.'/inc/utilities.php';
include_once $tfRoot.'/site/configuration.php';





