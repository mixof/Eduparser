<?php
/*
Copyright 2014 Anton Rachitskiy < rachitskiy  _at_  gmail.com >

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

    http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.
*/


function getParam($argv, $key, $justKey = false)
{
	foreach($argv as $i=>$val)
	{
		if($val === "-".$key)
		{
			if($justKey) return true;
			else if(isset($argv[$i+1])) return $argv[$i+1];
		}
	}
	return false;
}

function getWebParam($get, $key, $justKey = false)
{
	if(isset($get[$key]))
	{
		if($justKey) return true;
		else
		{
			$val=trim(urldecode($get[$key]));
			if(empty($val) || strtolower($val) == 'false') return false;
			else return $val;
		}
	}
	else return false;
}

//Start configuration

$globalConfig=array();
$debugCount=0;

function getConfig($param)
{
	global $globalConfig, $debugCount;
	$param=strtolower($param);
	if($param == 'debug' && isConfig($param) && $globalConfig[$param] == true && class_exists('Auth') && Auth::isAdmin())
	{
		print "<pre>";
		$tz=date_default_timezone_get();
		date_default_timezone_set("America/Los_Angeles");
		$debugCount++;
		$ar=debug_backtrace();
		print "****\n";
		print $debugCount." :: ".date("H:m:s")."\n";
		print $ar[1]['function']."\n";
		print $ar[2]['function']."\n";
		print "----\n";
		date_default_timezone_set($tz);
		print "</pre>";
		
	}
	if(isConfig($param)) return $globalConfig[$param];
	else return false;
}


function isConfig($param)
{
	global $globalConfig;
	$param=strtolower($param);
	if(isset($globalConfig[$param])) return true;
	return false;
}

function setConfig($param, $val, $overwrite = true)
{
	global $globalConfig;
	$param=strtolower($param);
	if((!$overwrite && !isConfig($param)) || $overwrite)
	{
		$globalConfig[$param]=$val;
	} 
}

function unsetConfig($param)
{
	global $globalConfig;
	$param=strtolower($param);
	if(isConfig($param)) unset($globalConfig[$param]);
}

function loadConfig($file, $section = 'default')
{
	global $globalConfig;
	
	$configFile=array();
	if(file_exists($file)) $configFile=parse_ini_file($file, true);
	if(isset($configFile["common"]))
	{
		foreach($configFile["common"] as $key=>$param)
		{
			setConfig($key, $param, true);
		}
	}

	if($section === false || !isset($configFile[$section])) $section='default';

	if(isset($configFile[$section]))
	{
		foreach($configFile[$section] as $key=>$param)
		{
			setConfig($key, $param, true);
		}
	}
	//just something for the start of the script, so we can debug it
	getConfig('DEBUG');
}



?>