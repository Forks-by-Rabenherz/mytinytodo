<?php
/*
	This file is part of myTinyTodo.
	(C) Copyright 2009-2010,2020 Max Pozdeev <maxpozdeev@gmail.com>
	Licensed under the GNU GPL v2 license. See file COPYRIGHT for details.
*/

define('MTT_VERSION', '@VERSION');

##### MyTinyTodo requires php 5.4.0 and above! #####
if (version_compare(PHP_VERSION, '5.4.0') < 0) {
	die("PHP 5.4+ is required");
}

if(!defined('MTTPATH')) define('MTTPATH', dirname(__FILE__) .'/');
if(!defined('MTTINC'))  define('MTTINC', MTTPATH. 'includes/');

require_once(MTTPATH. 'common.php');
require_once(MTTPATH. 'db/config.php');

ini_set('display_errors', 'On');

if(!isset($config)) global $config;
Config::loadConfig($config);
unset($config);

date_default_timezone_set(Config::get('timezone'));

# MySQL Database Connection
if(Config::get('db') == 'mysql')
{
	require_once(MTTPATH. 'class.db.mysql.php');
	$db = DBConnection::init(new Database_Mysql);
	try {
		$db->connect(Config::get('mysql.host'), Config::get('mysql.user'), Config::get('mysql.password'), Config::get('mysql.db'));
	}
	catch(Exception $e) {
		die2("Failed to connect to mysql database: ". $e->getMessage());
	}
	$db->dq("SET NAMES utf8");
}

# SQLite3 (pdo_sqlite)
elseif(Config::get('db') == 'sqlite')
{
	require_once(MTTPATH. 'class.db.sqlite3.php');
	$db = DBConnection::init(new Database_Sqlite3);
	$db->connect(MTTPATH. 'db/todolist.db');
}
else {
	# It seems not installed
	die("Not installed. Run <a href=setup.php>setup.php</a> first.");
}
$db->prefix = Config::get('prefix');

//User can override language setting by cookies
if(isset($_COOKIE['lang']) && preg_match("/^[a-z-]+$/i", $_COOKIE['lang']) && file_exists(MTTPATH. 'lang/'. $_COOKIE['lang']. '.php')) {
	Config::set('lang', $_COOKIE['lang']);
}

require_once(MTTINC. 'class.lang.php');
Lang::loadLang(Config::get('lang'));

$_mttinfo = array();

$needAuth = (Config::get('password') != '') ? 1 : 0;
if($needAuth && !isset($dontStartSession))
{
	if(Config::get('session') == 'files')
	{
		session_save_path(MTTPATH. 'tmp/sessions');
		ini_set('session.gc_maxlifetime', '1209600'); # 14 days session file minimum lifetime
		ini_set('session.gc_probability', 1);
		ini_set('session.gc_divisor', 10);
	}

	ini_set('session.use_cookies', true);
	ini_set('session.use_only_cookies', true);
	session_set_cookie_params(1209600, url_dir(Config::get('url')=='' ? $_SERVER['REQUEST_URI'] : Config::get('url'))); # 14 days session cookie lifetime
	session_name('mtt-session');
	session_start();
}

function is_logged()
{
	if(!isset($_SESSION['logged']) || !$_SESSION['logged']) return false;
	return true;
}

function is_readonly()
{
	$needAuth = (Config::get('password') != '') ? 1 : 0;
	if($needAuth && !is_logged()) return true;
	return false;
}

function timestampToDatetime($timestamp)
{
	$format = Config::get('dateformat') .' '. (Config::get('clock') == 12 ? 'g:i A' : 'H:i');
	return formatTime($format, $timestamp);
}

function formatTime($format, $timestamp=0)
{
	$lang = Lang::instance();
	if($timestamp == 0) $timestamp = time();
	$newformat = strtr($format, array('F'=>'%1', 'M'=>'%2'));
	$adate = explode(',', date('n,'.$newformat, $timestamp), 2);
	$s = $adate[1];
	if($newformat != $format)
	{
		$am = (int)$adate[0];
		$ml = $lang->get('months_long');
		$ms = $lang->get('months_short');
		$F = $ml[$am-1];
		$M = $ms[$am-1];
		$s = strtr($s, array('%1'=>$F, '%2'=>$M));
	}
	return $s;
}

function _e($s)
{
	echo Lang::instance()->get($s);
}

function __($s)
{
	return Lang::instance()->get($s);
}

function mttinfo($v)
{
	global $_mttinfo;
	if(!isset($_mttinfo[$v])) {
		echo get_mttinfo($v);
	} else {
		echo $_mttinfo[$v];
	}
}

function get_mttinfo($v)
{
	global $_mttinfo;
	if (isset($_mttinfo[$v])) {
		return $_mttinfo[$v];
	}
	switch($v)
	{
		case 'template_url':
			$_mttinfo['template_url'] = get_mttinfo('mtt_url'). 'themes/'. Config::get('template') . '/';
			return $_mttinfo['template_url'];
		case 'url':
			$_mttinfo['url'] = Config::get('url');
			if ($_mttinfo['url'] == '') {
				$proto = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != '' && $_SERVER['HTTPS'] != 'off') ? 'https://' : 'http://';
				$defport = $proto == 'https://' ? 443 : 80;
				$_mttinfo['url'] = $proto. $_SERVER['HTTP_HOST']. ($_SERVER['SERVER_PORT'] != $defport ? ':'.$_SERVER['SERVER_PORT'] : '').
									url_dir(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : $_SERVER['SCRIPT_NAME']);
			}
			return $_mttinfo['url'];
		case 'mobile_url':
			$_mttinfo['mobile_url'] = Config::get('mobile_url');
			if ($_mttinfo['mobile_url'] == '') {
				$_mttinfo['mobile_url'] = get_mttinfo('url'). '?pda';
			}
			return $_mttinfo['mobile_url'];
		case 'mtt_url':
			$_mttinfo['mtt_url'] = Config::get('mtt_url');
			if ($_mttinfo['mtt_url'] == '') {
				$_mttinfo['mtt_url'] = url_dir(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : $_SERVER['SCRIPT_NAME']);
			}
			return $_mttinfo['mtt_url'];
		case 'title':
			$_mttinfo['title'] = (Config::get('title') != '') ? htmlarray(Config::get('title')) : __('My Tiny Todolist');
			return $_mttinfo['title'];
		case 'version':
			if (MTT_VERSION != '@VERSION') {
				$_mttinfo['version'] = MTT_VERSION;
				return $_mttinfo['version'];
			}
			return time(); //force no-cache for dev needs
	}
}

function jsonExit($data)
{
	header('Content-type: application/json; charset=utf-8');
	echo json_encode($data);
	exit;
}

function die2($userText, $errText = null)
{
	$errText === null ? error_log($userText) : error_log($errText);
	echo $userText;
	exit(1);
}

?>