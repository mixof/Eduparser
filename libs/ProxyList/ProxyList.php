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


class ProxyList
{
	private static $all=array();
	private static $used=array();
	private static $active=array();
	private static $startTimes=array();
	
	public static function getActive($domain, $timeout = 600)
	{
		if(isset(self::$active[$domain]) && isset(self::$startTimes[$domain]) && (time() - self::$startTimes[$domain]) < $timeout) 
		{			
			return self::$active[$domain];
		}
		else if(isset(self::$active[$domain]))
		{
			unset(self::$active[$domain]);
		}
		return false;
	}
	
	public static function setActive($domain, $proxy)
	{
		self::$active[$domain]=$proxy;
		self::$startTimes[$domain]=time();
	}
	
	public static function getRandom()
	{
		if(empty(self::$all)) self::load();

		$diff=array_diff(self::$all, self::$used);
		if(empty($diff))
		{
			self::$used=array();
			$diff=self::$all;
		}
		$proxy=$diff[array_rand($diff)];
		if(stripos($proxy, ":") !== FALSE)
		{
			self::$used[]=$proxy;
			if(getConfig('DEBUG')) print "Got proxy: $proxy\n";
			$proxy=explode(":", $proxy);
			return $proxy[0].":".$proxy[1];
		}

		return false;
	}
	
	private static function load()
	{
		$fh=fopen(__DIR__."/proxies.csv", 'r');
		if($fh)
		{
			while(($proxy = fgetcsv($fh, 1024, ":")) !== FALSE)
			{
				self::$all[]=implode(":", $proxy);
			}
			fclose($fh);
		}
	}
	

}


?>