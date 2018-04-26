<?php
/*
Copyright 2014 Anton Rachitskiy

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


class Process
{
	//check if process exists
	//matches full command line
	public static function fastCheck($str)
	{
		$output=array();
		exec("pgrep -f -o -l $str", $output);	
		if(count($output) > 0) return true;
		else return false;
	}
	
	public static function countProcs($str)
	{
		$list=self::getPSList();
		$count=0;
		foreach($list as $cmd)
		{
			if(stripos($cmd, $str) !== FALSE) $count++;
		}	
		if(stripos($_SERVER['SCRIPT_FILENAME'], $str) !== FALSE) $count--;
		return $count;
	}
	
	public static function getPSList()
	{
		$output=array();
		//exec("ps auxwww|grep ".$str."|grep -v grep|grep -v '/bin/sh -c'|grep -v 'sh -c'", $output);
		exec("ps ax -o command", $output);	
		return $output;
	}
	
	//kill processes that run for more than specific time
	public static function killExpired($str, $hours = 1, $minutes = 0, $seconds = 0)
	{
		exec("ps -eo pid,etime,args --sort -etime|grep ".$str."|awk '{print $1,$2}'", $output);
		foreach($output as $line)
		{
			$ar=explode(" ", $line);
			if(isset($ar[1]))
			{
				$time=explode("-", $ar[1]);
				if(isset($time[1])) $time=$time[1];
				else $time=$time[0];
				$time=explode(":", $ar[1]);
				
				if(count($time) == 3)
				{
					$tH=intval($time[0]);
					$tM=intval($time[1])+$tH*60;
					$tS=intval($time[2])+$tM*60;
				}
				else if(count($time) == 2)
				{
					$tH=0;
					$tM=intval($time[0]);
					$tS=intval($time[1])+$tM*60;
				}
				else if(count($time) == 1)
				{
					$tH=0;
					$tM=0;
					$tS=intval($time[0]);
				}
				if($tH >= $hours && $tM >= $minutes && $tS >= $seconds)
				{
					exec("kill -9 $ar[0]");
				}
			}

		}
	}
	
	public static function killOthers($str, $keep = 0)
	{
		$myPid=posix_getpid();
		exec("ps -eo pid,etime,args|grep ".$str."|grep -v 'sh -c'|grep -v '/bin/sh -c'|grep -v grep|awk '{print $1}'", $output);
		$running=count($output);
		foreach($output as $pid)
		{
			if($pid != $myPid && $running > $keep ) 
			{
				exec("kill -9 $pid"); 
				$running--;
			}
		}
	}	
}

?>