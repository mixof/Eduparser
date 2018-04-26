<?php
require_once __DIR__."/../Process/Process.php";

class TorProxy
{
	private static $socks_port=false;
	private static $started=array();
	
	
	//starts up to $num tors and returns one
	//$num  = false, so we can use getConfig in there
	public static function startBatch($num = false, $country = 'us')
	{
		if(!$num) $num=2;

		//do lock, only 1 tor manager is allowed
		$lockNum=getmypid()%1;
		$fl=fopen(sys_get_temp_dir()."/torStart_$lockNum.lock", 'w');
		//get here if you are a manager 
		//and have started within first 30 seconds of the minute
		//otherwise skip and just get a running port
		if((time()%60) < 30 && flock($fl, LOCK_EX | LOCK_NB))
		{
			//get tors already running
			$busyPorts=self::getBusyPorts();
			self::$started=self::getRunningTors($busyPorts);
			//and if it's still not enough
			//we only start if less than 60% of $num is available
			if(count(self::$started) < max(2,ceil($num*0.6)))
			{
				for($i=count(self::$started);$i<$num;$i++)
				{
					$proxy=self::start(false, $country, $busyPorts, $num);
					if($proxy)
					{
						list($addr, $port)=explode(":", $proxy);
						self::$started[]=$port;
					}
				}
			}
			flock($fl, LOCK_UN);
		}
		
		$ret=false;
		$tries=0;
		while($ret == false && $tries < 20)
		{
			$tries++;
			$ret=self::getNext($num);
			sleep(1);
		}
		self::$socks_port=$ret;
		
		
		/*
		this is old process
		//getting one proxy
		//but making sure that it is actually running
		$ret=false;
		while(!empty(self::$started) && $ret == false)
		{
			$port=array_shift(self::$started);
			self::$socks_port=$port;
			if(self::get()) $ret=self::get(); 
		}
		*/
		if(getConfig('DEBUG')) print "Got tor proxy:". $ret."\n";
		//if(getConfig('DEBUG')) print "Still have ".count(self::$started)." running proxies in the queue\n";

		if($ret) return "127.0.0.1:".$ret;
		else return false;
	}
	
	public static function getRunningTors($ports = false)
	{
		$active=array();
		//get all busy ports
		if(!$ports) $ports=self::getBusyPorts();
		if(getConfig('DEBUG')) print "getRunningTors\n";
		//with new check busy ports ensures that tor is running
	//	$active=$ports;
		//now check if the tor is running on these ports
		foreach($ports as $port)
		{
			$pidFile=sys_get_temp_dir()."/tor".$port.".pid";
			if(getConfig('DEBUG')) print "File: $pidFile\n";
			if(file_exists($pidFile))
			{
				if(getConfig('DEBUG')) print "File Exists\n";
				//we don't stop it now, since it updates ip constantly anyway, hence "false" in if
				if(time() - filemtime($pidFile) >= 60 * 60 && false) 
				{
					if(getConfig('DEBUG')) print "Olrder than 1 hour\n";
					//then stop this tor
					self::stop($port);
				}
				else if(self::isRunning($pidFile))
				{
					if(getConfig('DEBUG')) print "Port is running\n";
					$active[]=$port;
				}
				else
				{
					if(getConfig('DEBUG')) print "Port is not running\n";
					unlink($pidFile);
				}
			}
			else
			{
				if(getConfig('DEBUG')) print "File does not exist\n";
			}
		}
		if(getConfig('DEBUG'))
		{
			print "Running ports\n";
			print_r($active);
		}
		/*
		$list=Process::getPSList();
		$nList=array();
		foreach($list as $cmd)
		{
			if(strpos($cmd, "tor") !== FALSE) $nList[]=$cmd;
		}
		$list=$nList;
		foreach($ports as $port)
		{
			foreach($nList as $cmd)
			{
				if(strpos($cmd, "tor".$port) !== FALSE)
				{
					$active[]=$port;
					break;
				}
			}			
		}*/
		//shuffle tors, so that nobody is always going to use the same one
		shuffle($active);
		return $active;
	}
	
	//get next proxy
	public static function getNext($num)
	{
		//if we don't have any yet, get the random start
		if(self::$socks_port === false) self::$socks_port=rand(20000, 20000+$num);
		$tries=0;
		while($tries < 500)
		{
			self::$socks_port++;
			if(self::$socks_port >= 20000+$num) self::$socks_port=20000;
			
			$pidFile=sys_get_temp_dir()."/tor".self::$socks_port.".pid";
			if(file_exists($pidFile))
			{
				if(self::isRunning($pidFile)) return self::$socks_port;
			}
			$tries++;
		}
		return false;
	}
	
	//getting active proxy if one exists
	public static function get()
	{
		if(self::$socks_port !== FALSE)
		{
			$pidFile=sys_get_temp_dir()."/tor".self::$socks_port.".pid";
			if(file_exists($pidFile))
			{
				if(self::isRunning($pidFile)) return "127.0.0.1:".self::$socks_port;
			}
			/*
			//if one process is running (-1 -s none)
			if(Process::countProcs("tor".self::$socks_port) == 1)
			{
				return "127.0.0.1:".self::$socks_port;
			}*/
		}
		return false;
	}
	
	public static function isRunning($pidFile)
	{
		$pid=file_get_contents($pidFile);
		$pid=trim($pid);
		if(file_exists("/proc/$pid"))
		{
			return true;
		}
		return false;
	}
	
	public static function start($single = true, $country = 'us', $busyPorts = false, $num = 2)
	{
		//kill current server
		if($single) self::stop();
		//we must kill all tor servers older than 60 minutes
		//it's already done in busy ports
		//but still do here just in case
		//Process::killExpired("torrc", 0, 60, 0);
		//exec("killall --older-than 1h tor");
	
		if(!$busyPorts) $busyPorts=self::getBusyPorts();
		//if we have too many tors (something wrong) running
		//then just return false
	//	if(count($busyPorts) == 1500) return false;
	

		//make sure that it is not occupied by other tor client
		for($port=20000;$port<20000+$num;$port++)
		{
			if(!in_array($port, $busyPorts))
			{
				self::$socks_port=$port;
				break;
			}
		}
	
		
		$control_port=self::$socks_port+2000;
		//pid file
		$pidFile=sys_get_temp_dir()."/tor".self::$socks_port.".pid";
		$dataDir=sys_get_temp_dir()."/tor_data/".self::$socks_port;
		if(!file_exists($dataDir)) mkdir($dataDir, 0777, true);
				
		//$cmd="tor 	-f ".__DIR__."/torrc --RunAsDaemon 1 --CookieAuthentication 0 --HashedControlPassword '' --ControlPort $control_port --PidFile $pidFile --SocksPort ".self::$socks_port." --DataDirectory $dataDir --ReachableAddresses 'reject *:25, accept *:*' --ClientOnly 1 --ExitNodes '{".$country."}' --CircuitBuildTimeout 20 --KeepalivePeriod 60 --NumEntryGuards 8 --EnforceDistinctSubnets 0";
		
		$cmd="tor -f ".__DIR__."/torrc --RunAsDaemon 1 --CookieAuthentication 0 --HashedControlPassword '' --ControlPort $control_port --PidFile $pidFile --SocksPort ".self::$socks_port." --DataDirectory $dataDir --ReachableAddresses 'reject *:25, accept *:*' --ClientOnly 1 --CircuitBuildTimeout 10 --KeepalivePeriod 240 --UseEntryGuards 0 --EnforceDistinctSubnets 0 --ExcludeSingleHopRelays 0 --LearnCircuitBuildTimeout 0 --NewCircuitPeriod 20 --MaxCircuitDirtiness 30 --AllowSingleHopCircuits 1 --CircuitIdleTimeout 240";
		
		if(getConfig('DEBUG')) print $cmd."\n";
		//starting the tor process
		shell_exec($cmd);
		
		//wait for 10 seconds maximum until the file is created or port is open
		$timerCount=0;
		do
		{
			sleep(1);
			$timerCount++;
		}while($timerCount < 30 && !file_exists($pidFile) && !self::checkPort(self::$socks_port));
		
		//if it was started before we hit the time  out
		if($timerCount < 30)
		{
			return "127.0.0.1:".self::$socks_port;
		}
		else if(getConfig('DEBUG'))
		{
			print "TOR did not seem to have started: $pidFile\t port: ".self::$socks_port."\n";
		}
		
		/*
		sleep(3);
		//if pid file exists
		//then it started
		if(file_exists($pidFile))
		{
			return "127.0.0.1:".self::$socks_port;
		}
		else if(getConfig('DEBUG'))
		{
			print "PID File not found: $pidFile\n";
		}*/
		//otherwise there was some error
		return false;
		
	}
	
	public static function checkPort($port)
	{
		exec('netstat -na|grep "127.0.0.1:'.$port.'"|grep -E "LISTEN|ESTAB"', $output);
		if(count($output) > 0) return true;
		return false;
		
	}
	
	public static function getBusyPorts()
	{
		if(getConfig('DEBUG')) print "getBusyPorts\n";
		$ports=array();
		//It does not work since -p does not work for scraper
	/*	exec('netstat -n -p|grep "/tor"|grep "127.0.0.1:"|grep "ESTA"', $output);
		foreach($output as $line)
		{
			if(preg_match_all("/127\.0\.0\.1:(\d+)/", $line, $matches))
			{
				if(preg_match_all("/ESTABLISHED (\d+)\/tor/", $line, $pidMatch))
				{
					if(file_exists("/proc/".$pidMatch[1][0]))
					{
						$ports[]=$matches[1][0];
						
					}
				}
			}
		}*/
		//here we will get ALL busy ports on 2* ports (though only tor should be listenning on 127.0.0.1:\d+ anyway)
		$netstatOut=array();
		exec('netstat -na|grep "127.0.0.1:2"|grep -E "LISTEN"', $netstatOut);
		if(getConfig('DEBUG')) print_r($netstatOut);
		foreach($netstatOut as $line)
		{
			if(preg_match_all("/127\.0\.0\.1:(\d+)/", $line, $matches))
			{
				$port=intval($matches[1][0]);
				if(getConfig('DEBUG')) print "port: $port\n";
				//port must be between 20000 and 22000
				if($port >= 20000 && $port <= 22000)
				{
					$ports[]=$port;					
				}
			}
		}
		
		
		/*
		$files = glob(sys_get_temp_dir()."/tor*pid");
		foreach($files as $file) 
		{
			//make sure it's a file
			if(is_file($file) && file_exists($file))
			{
				$port=false;
				//get port of this pid
				if(preg_match_all("/tor(\d+)/", $file, $matches))
				{
					$port=$matches[1][0];
				}
				//if pid is more than 60 minutes old
				if(time() - filemtime($file) >= 60 * 60) 
				{
					//then stop this tor
					self::stop($port);
				}
				//get port of this pid
				else if($port)
				{
					if(self::isRunning($file))
					{
						$ports[]=$port;
					}	
					else
					{
						if(getConfig('DEBUG'))  print "Not running, delete $file\n";
						unlink($file);
					}
				}
				
			}
		}*/
		
		if(getConfig('DEBUG') && !empty($ports))
		{
			print "Busy ports:\n";
			print_r($ports);
		}
		return $ports;
	}
	
	public static function stop($port = false)
	{
		if(!$port) $port=self::$socks_port;
		if($port)
		{
		/*	exec('netstat -n -p|grep "127.0.0.1:'.$port.'"|grep "/tor"|grep "ESTAB"', $output);
			if(count($output) > 0)
			{
				$line=$output[0];
				if(preg_match_all("/ESTABLISHED (\d+)\/tor/", $line, $pidMatch))
				{
					$pid=$pidMatch[1][0];
					exec("kill -KILL ".$pid);
				}
				
			}*/
			
			$pidFile=sys_get_temp_dir()."/tor".$port.".pid";
			$cmd="kill -KILL $(cat $pidFile)";
			if(getConfig('DEBUG')) print $cmd."\n";
			exec($cmd);
			
			unlink($pidFile);
			//if we were killing or tor
			//then set our port to false
			if($port == self::$socks_port) self::$socks_port=false;
		}
	}
	
}
?>