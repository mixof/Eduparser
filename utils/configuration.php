<?php

if(!function_exists("getConfig"))
{
	$globalConfig=array();

	function getConfig($param)
	{
		global $globalConfig;
		if(isConfig($param)) return $globalConfig[$param];
		else return false;
	}

	function isConfig($param)
	{
		global $globalConfig;
		if(isset($globalConfig[$param])) return true;
		return false;
	}

	function setConfig($param, $val, $overwrite = true)
	{
		global $globalConfig;
		if((!$overwrite && !isConfig($param)) || $overwrite)
		{
			$globalConfig[$param]=$val;
		} 
	}

	function unsetConfig($param)
	{
		global $globalConfig;
		if(isConfig($param)) unset($globalConfig[$param]);
	}


	function loadStoreConfig($store = 'default')
	{
		$configFile=array();
		if(file_exists(__DIR__."/../configuration.ini")) $configFile=parse_ini_file(__DIR__."/../configuration.ini", true);
		if(isset($configFile["common"]))
		{
			foreach($configFile["common"] as $key=>$param)
			{
				setConfig($key, $param, true);
			}
		}

		if($store === false || !isset($configFile[$store])) $store='default';

		if(isset($configFile[$store]))
		{
			foreach($configFile[$store] as $key=>$param)
			{
				setConfig($key, $param, true);
			}
		}
	}
	
}


?>
