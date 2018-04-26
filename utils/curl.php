<?php

$cachePurged=false;
if(!function_exists("readCSV"))
{
    function readCSV($csvFile){
        $links=array();
        $file_handle = fopen($csvFile, 'r');
        while (!feof($file_handle) ) {

            $tmp = fgetcsv($file_handle, 1024);

            if(is_array($tmp))
            {
                if(stripos($tmp[0],"http")!==false)
                {
                    $links[]=$tmp[0];
                }
            }

        }
        fclose($file_handle);
        return $links;
    }
}

//delete cache from current url
function deleteMyCache($url)
{
	$cacheDir=__DIR__."/cache/";
	if(isConfig("cacheDir")) $cacheDir=getConfig("cacheDir");
	
		$md5=md5($url);
		if(file_exists($cacheDir.$md5))
		{
			unlink($cacheDir.$md5);
		}
}

function deleteCurlCache($delayMinutes = 1440)
{
	//delete old cache files on load

		$cacheDir=__DIR__."/cache/";
		if(isConfig("cacheDir")) $cacheDir=getConfig("cacheDir");
		
		$files = glob($cacheDir."*");
		$count=0;
		foreach($files as $file) 
		{
			//60 minutes
			if(is_file($file) && time() - filemtime($file) >= $delayMinutes * 60) 
			{
				$count++;
		    	if(file_exists($file)) unlink($file);
			}
		}
		if(getConfig('DEBUG')) print "Deleted $count old cache files\n";

	
}

//$use_proxy is obsolete
function getDocument($url, $use_proxy = false, $debug = false, $proxyType = CURLPROXY_HTTP, $json = false, $header = false, $cookie = false) 
{
	$data = getSingle($url, $use_proxy, $debug, $proxyType, $json, $header, $cookie);
	return $data;
}


function getImage($url, $file, $checkType = 'size', $force = false, $use_proxy = false, $debug =false)
{
	//for ebay lister a little hack
	if(getConfig("lastImage") == $url)
	{
		exec("wget $url -O $file");
		return;
	}
	setConfig("lastImage", $url);
	
	//careful, if we put safari here, it will make some cdns return webp, which is not good for many things
	$agent= 'Mozilla/5.0 (Windows NT 10.0; WOW64; rv:45.0) Gecko/20100101 Firefox/45.0';
	$timeout = 10;
	if(!defined('USE_PROXY') || USE_PROXY === null) $proxy = null;
	else $proxy = USE_PROXY;
	
	if(getConfig('Proxy')) $proxy=getConfig('Proxy');
	
	$getImage=true;
	switch($checkType)
	{
		case "size":
			if(getSize($url) == getLocalSize($file)) $getImage=false;
			break;
		case "exists":
			if(getLocalSize($file) > 100) $getImage=false;
			break;
		case "force":
			$getImage=true;
			break;
	}
	
	if(!getConfig('ImageCookie'))
	{
		$cookie="/tmp/".time()."_".getConfig('STORE')."_image_cookie.txt";
		setConfig('ImageCookie', $cookie);
	}
	
	if($getImage || $force )
	{
		if(getConfig('DEBUG')) print "Getting image: $url\n";
		$fp = fopen($file, 'wb');
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_PROXY, $proxy);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_VERBOSE, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_USERAGENT, $agent);
		curl_setopt($ch, CURLOPT_URL,$url);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_AUTOREFERER, true);
		curl_setopt($ch, CURLOPT_FILE, $fp);
		curl_setopt($ch, CURLOPT_HEADER, 0);
	    curl_setopt($ch, CURLOPT_COOKIEJAR, getConfig('ImageCookie'));
	    curl_setopt($ch, CURLOPT_COOKIEFILE, getConfig('ImageCookie'));
		curl_exec($ch);
		curl_close($ch);
		fclose($fp);
	}
	else
	{
		if(getConfig('DEBUG')) print "Skipping image: $url\n";
	}
}

//gets the size of the remote body
function getSize($url)
{
	if(getConfig("ALLOWCACHE"))
	{
		$cacheDir=__DIR__."/cache/";
		if(isConfig("cacheDir")) $cacheDir=getConfig("cacheDir");
		
		$md5=md5($url);
		if(file_exists($cacheDir.$md5))
		{
			$data=file_get_contents($cacheDir.$md5);
			if(strlen($data) > 100)
			{
				if(getConfig('DEBUG')) print "got data from cache [$md5]: $url\n";
				return count($data);
			}
		}
	}
	if(!defined('USE_PROXY') || USE_PROXY === null) $proxy = null;
	else $proxy = USE_PROXY;
	
	if(getConfig('Proxy')) $proxy=getConfig('Proxy');
	
     $ch = curl_init($url);

     curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
     curl_setopt($ch, CURLOPT_HEADER, TRUE);
     curl_setopt($ch, CURLOPT_NOBODY, TRUE);
	 curl_setopt($ch, CURLOPT_PROXY, $proxy);


     $data = curl_exec($ch);
     $size = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);

/*
for debug - print header
	$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
	$header = substr($data, 0, $header_size);
	print $header."\n";
*/
     curl_close($ch);
     return $size;
}

function getLocalSize($file)
{
	$size=0;
	if(file_exists($file)) $size=filesize($file);
	return $size;
}

$lastHeader=array();
function curlSaveHeader( $curl, $header_line ) 
{
	global $lastHeader;
    $lastHeader[]=trim($header_line);
    return strlen($header_line);
}

function getLastHeader($key = false)
{
	global $lastHeader;
	if($key)
	{
		$ret=array();
		foreach($lastHeader as $val)
		{
			if(stripos($val, $key.":") === 0)
			{
				$ret[]=str_replace($key.":", "", $val);
			}
		}
		return $ret;
	}
	else
	{
		return $lastHeader;
	}
}

function getSingleNoWait($url, $use_proxy = false, $debug = false,$proxyType = CURLPROXY_HTTP, $json=false, $header = false, $cookie = false, $postdata = false, $cookiejar = false, $saveHeader = false, $redirect = 0)
{
	$oldConnectTimeout=getConfig('CurlConnectTimeout');
	$oldTimeout=getConfig('CurlTimeout');
	setConfig('CurlConnectTimeout', 5);
	setConfig('CurlTimeout', 10);
	
	$data = getSingleNoCache($url, $use_proxy, $debug, $proxyType, $json, $header, $cookie, $postdata, $cookiejar, $saveHeader, $redirect);
	
	setConfig('CurlConnectTimeout', $oldConnectTimeout);
	setConfig('CurlTimeout', $oldTimeout);
	
	return $data;
}

function getSingleNoCache($url, $use_proxy = false, $debug = false,$proxyType = CURLPROXY_HTTP, $json=false, $header = false, $cookie = false, $postdata = false, $cookiejar = false, $saveHeader = false, $redirect = 0)
{
	$cache = getConfig('ALLOWCACHE');
	setConfig("ALLOWCACHE", false);
	$remote=getConfig('useRemoteCache');
	setConfig('useRemoteCache', false);

	$data = getSingle($url, $use_proxy, $debug, $proxyType, $json, $header, $cookie, $postdata, $cookiejar, $saveHeader, $redirect);
	setConfig('ALLOWCACHE', $cache);
	setConfig('useRemoteCache', $remote);
	
	return $data;
}


function getGetterTest($url, $header = false, $cookie = false, $json = false, $postdata = false, $scraperUrl = false, $data = "html")
{
	if(getConfig('DEBUG')) print "Doing Getter for url: $url\n";
	$interface='http://172.93.229.130:9999/scraper/Scraper/get.php';
	$fields=array(	"cacheChecked"=>1,
					"function"=>"Getter",
					"urlkey"=>getConfig('GetterType'),
					"body"=>json_encode(array("url"=>$url, "header"=>$header, "cookie"=>$cookie, "postdata"=>$postdata, "json"=>$json)),
					"scraperUrl"=>false);

	$fields_string="";
	foreach($fields as $key=>$value) 
	{ 
		$key=rawurlencode($key);
		$value=rawurlencode($value);
		if(empty($value)) $fields_string .= $key.'&';
		else $fields_string .= $key.'='.$value.'&'; 
	}
	rtrim($fields_string, '&');
	
	$ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $interface."?".$fields_string);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $resp = curl_exec ($ch);
    curl_close ($ch);

	$json=json_decode($resp, true);
	if(getConfig('DEBUG')) print_r($json);
	if($json && $json['valid'])
	{
		$json['html']=base64_decode($json['html']);
		if($data) return $json[$data];
		else return $json;
	}
   	return false;
 
}

function getGetter2($url, $header = false, $cookie = false, $json = false, $postdata = false, $scraperUrl = false, $data = "html")
{
	debug("Doing Getter v.2 for url: $url");
	
	$serverIp=false;
	$fields=array(	"cacheChecked"=>1,
					"hash"=>md5($url)."-1",
					"function"=>"Getter",
					"urlkey"=>getConfig('GetterType'),
					"scraperUrl"=>false);
	$body=json_encode(array("url"=>$url, "header"=>$header, "cookie"=>$cookie, "postdata"=>$postdata, "json"=>$json));
	$fields["body"]=$body;

	//check for cache
	$resp=file_get_contents("http://cache.skuio.com/get_cache?key=".$fields['hash']);
	if(!$resp)
	{
		$ret=file_get_contents("http://107.150.51.50:1123/?function=getNext");
		$ret=json_decode($ret, true);
		if(isset($ret['server']))
		{
			$serverIp=$ret['server'];
			$interface="http://".$ret['server'].":9999/scraper/Scraper/get.php";
		}
		else return false;
		debug("Got server: ".$interface);
		
		$fields_string="";
		foreach($fields as $key=>$value) 
		{ 
			$key=rawurlencode($key);
			$value=rawurlencode($value);
			if(empty($value)) $fields_string .= $key.'&';
			else $fields_string .= $key.'='.$value.'&'; 
		}
		rtrim($fields_string, '&');

		$ch = curl_init();


	    curl_setopt($ch, CURLOPT_URL, $interface."?".$fields_string);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");                                                                     
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);   
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		                                                                                                                                                                           

		$resp = curl_exec($ch);
		
	}
	else
	{
		debug("Got cache");
	}



	$json=json_decode($resp, true);
	if(getConfig('DEBUG'))
	{
		
		print $interface."?".$fields_string."\n";
		print $body."\n";
		
		if($json)
		{
			$json1=$json;
			unset($json1['html']);
			print_r($json1);
		}
		else print $resp."\n";
	}
	if($json)
	{
		if($json['valid'])
		{
			$json['html']=base64_decode($json['html']);
			if($data) return $json[$data];
			else return $json;
		}
		else
		{
			if(isset($json['block']) && $json['block'] > 0) $block=$json['block'];
			else $block=2;
			debug("Blocking $serverIp for $block minutes");
			file_get_contents("http://107.150.51.50:1123/?function=setBlock&server=$serverIp&minutes=$block");
		}
		
	}
	else
	{
		//non responsive - ban for 15 minutes
		debug("Blocking $serverIp as non-responsive for 15 minutes");
		
		file_get_contents("http://107.150.51.50:1123/?function=setBlock&server=$serverIp&minutes=15");
	}
   	return false;
 
}


function getGetter($url, $header = false, $cookie = false, $json = false, $postdata = false, $scraperUrl = false, $data = "html")
{
	if(getConfig('DEBUG')) print "Doing Getter for url: $url\n";
	$interface='http://balancer.skuio.com/v1';
	$fields=array(	"key"=>"KEY",
					"cacheChecked"=>1,
					"hash"=>md5($url)."-1",
					"function"=>"Getter",
					"urlkey"=>getConfig('GetterType'),
					"scraperUrl"=>false);
	$body=json_encode(array("url"=>$url, "header"=>$header, "cookie"=>$cookie, "postdata"=>$postdata, "json"=>$json));

	$fields_string="";
	foreach($fields as $key=>$value) 
	{ 
		$key=rawurlencode($key);
		$value=rawurlencode($value);
		if(empty($value)) $fields_string .= $key.'&';
		else $fields_string .= $key.'='.$value.'&'; 
	}
	rtrim($fields_string, '&');
	
	$ch = curl_init();
	

    curl_setopt($ch, CURLOPT_URL, $interface."?".$fields_string);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");                                                                     
	curl_setopt($ch, CURLOPT_POSTFIELDS, $body);                                                                  
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);                                                                      
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                          
	    'Content-Type: application/json',                                                                                
	    'Content-Length: ' . strlen($body))                                                                       
	);                                                                                                                   

	$resp = curl_exec($ch);

	$json=json_decode($resp, true);
	if(getConfig('DEBUG'))
	{
		print $interface."?".$fields_string."\n";
		print $body."\n";
		
		$json1=$json;
		unset($json1['html']);
		print_r($json1);
	}
	if($json && $json['valid'])
	{
		$json['html']=base64_decode($json['html']);
		if($data) return $json[$data];
		else return $json;
	}
   	return false;
 
}

//ASP.NET ? rawurlencode(VIEWSTATE), rawurlencode(VIEWVALIDATION)
// getSingle($url, false, false, CURLPROXY_HTTP, $json, $header, $cookie);
function getSingle($url, $use_proxy = false, $debug = false,$proxyType = CURLPROXY_HTTP, $json=false, $header = false, $cookie = false, $postdata = false, $cookiejar = false, $saveHeader = false, $redirect = 0)
{	  
	global $lastHeader;
	$myHash=md5($url);
	setConfig('gotFromRemoteCache_'.$myHash, false);    
	
	if(getConfig('OfflineMode')) return "";
	if(getConfig('GetterEnabled'))
	{
		return getGetter($url, $header, $cookie, $json, $postdata);
	}
	if(getConfig('Getter2Enabled'))
	{
		return getGetter2($url, $header, $cookie, $json, $postdata);
	}
	setConfig('curlURLHash', false);
	

	//because for some cases we send proxyType = 5 and it fails everything
	if($use_proxy == false) $proxyType = CURLPROXY_HTTP;  
	
	if(getConfig("StartTime") > 0 && getConfig("MaxExecutionTime") > 0 && (time() - getConfig("StartTime")) > getConfig("MaxExecutionTime"))
	{
		if(getConfig('DEBUG')) return "";
		else exit();
	}

	deleteCurlCache();
	
	if(getConfig("ALLOWCACHE"))
	{
		
		$cacheDir=__DIR__."/cache/";
		if(isConfig("cacheDir")) $cacheDir=getConfig("cacheDir");
		
		$md5=md5($url.$json);
		if(file_exists($cacheDir.$md5))
		{
			$data=file_get_contents($cacheDir.$md5);
			//if(strlen($data) > 100)
			//{
				if(getConfig('DEBUG')) print "got data from cache[$md5]: $url\n";
				return $data;
			//}
		}
	}
	//cookies
	/*if(!isConfig('COOKIEJAR') || !file_exists(getConfig('COOKIEJAR')))
	{
		$file=__DIR__."/tmp/".time()."_".getConfig('STORE')."_cookie.txt";
		touch($file);
		setConfig('COOKIEJAR', $file);
	}
	//just in case cookiejar was passed to us
	if($cookiejar)
	{
		$file=__DIR__."/tmp/".time()."_".$cookiejar."_cookie.txt";
		setConfig('COOKIEJAR', $file);		
	}*/
	
	//Proxy
	$proxy=false;
	if(getConfig('Proxy')) $proxy=getConfig('Proxy');
	if($use_proxy) $proxy=$use_proxy;
	
	//debug("Getting page [".md5($url)."]: $url");
	if(getConfig('DEBUG') && $proxy) print "Proxy: $proxy\n";
	
	
	//$agent= 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_7_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/32.0.1700.107 Safari/537.36';
	$agent='Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/52.0.2743.116 Safari/537.36';
	if(getConfig('UserAgent')) $agent=getConfig('UserAgent');
	$connectTimeout=20;
	if(getConfig('CurlConnectTimeout')) $connectTimeout=getConfig('CurlConnectTimeout');
	//timeout should include connect time out + time for processing
	$timeout=$connectTimeout+60;
	if(getConfig('CurlTimeout')) $timeout=getConfig('CurlTimeout');
	
	
	if($proxy)
	{
		if(getConfig('CurlProxyTimeOut')) $connectTimeout=getConfig('CurlProxyTimeOut');
	}

	$lastHeader=array();
	

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
	if(getConfig('SSLProblem'))
	{
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
	}
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
//	curl_setopt($ch, CURLOPT_SSLVERSION, 3);
	curl_setopt($ch, CURLOPT_VERBOSE, false);
	curl_setopt($ch, CURLOPT_PROXY, $proxy);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_USERAGENT, $agent);
	curl_setopt($ch, CURLOPT_URL,$url);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connectTimeout);
	curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_AUTOREFERER, true);
	curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
	if($saveHeader) curl_setopt($ch, CURLOPT_HEADERFUNCTION, "curlSaveHeader");
	
	
	/*if($cookie)
	{
		debug("Curl: Using cookie: $cookie");
		curl_setopt($ch, CURLOPT_COOKIE, $cookie);
	}
	
	curl_setopt($ch, CURLOPT_COOKIEJAR, getConfig('COOKIEJAR'));
	curl_setopt($ch, CURLOPT_COOKIEFILE, getConfig('COOKIEJAR'));*/
	
	//curl_setopt($ch, CURLOPT_ENCODING , ""); 
	curl_setopt($ch,CURLOPT_ENCODING , "gzip");
	curl_setopt($ch, CURLOPT_PROXYTYPE, $proxyType);
	if($proxy) curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, 0);
	//if we need to pass json via post request (used in walmart)
	if($json)
	{
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");                                                                     
		curl_setopt($ch, CURLOPT_POSTFIELDS, $json);                                                                  
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                          
		    'Content-Type: application/json',                                                                                
		    'Content-Length: ' . strlen($json))                                                                       
		);
		if($header === false) $header = 'default';
	}
	if($postdata)
	{
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
	}
	
	if(is_array($header))
	{
		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
	}
	else if($header != "default")
	{
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
		    'Accept-Language: en-US,en;q=0.8,ru;q=0.6',
			'Cache-Control:max-age=0',
			'Connection:keep-alive'
		    ));
	}

	

	//try binding to the ip that this script was called via (for servers with multiple ips)
	//if(isset($_SERVER['SERVER_ADDR'])) curl_setopt($ch, CURLOPT_INTERFACE, $_SERVER['SERVER_ADDR']);
	$data = curl_exec($ch);
	$info = curl_getinfo($ch);
	setConfig('CurlCode', curl_getinfo($ch, CURLINFO_HTTP_CODE));
	
	setConfig('CURLLocation',curl_getinfo($ch, CURLINFO_EFFECTIVE_URL));
	
	if(getConfig('DEBUG') && !$data) print curl_error($ch);
	//if(getConfig('DEBUG') && $debug)  print_r($data);
	
	if(getConfig('DEBUG'))
	{
	//	print_r($info);
	}
	curl_close($ch);
	
	if(preg_match_all("/http-equiv=.refresh.*url=(.*?)(\"|')/", $data, $matches) && $redirect < 3)
	{
		
		if(stripos($matches[1][0], "http") == 0 )
		{
			$redirected_from = $url;
			$url=$matches[1][0];
			$host = parse_url( $url, PHP_URL_HOST );
			if( ! $host ) {
				$redirected_host = parse_url( $redirected_from );
				if( isset( $redirected_host['host'] ) ) {
					$url = $redirected_host['scheme']
							. '://' . $redirected_host['host']
							. ( ( substr( $url, 0, 1 ) == '/' ) ? '' : '/' ) . $url;

				}
			}
		}
		else
		{
			$struct=parse_url($url);
			$url=$struct['scheme']."://".$struct['host']."/".$matches[1][0];
		}
		$oldCache=getConfig('ALLOWCACHE');
		setConfig('ALLOWCACHE', false);
		$data=getSingle($url, $use_proxy, $debug,$proxyType, $json, $header, $cookie , $postdata , $cookiejar, $saveHeader, $redirect+1);
		setConfig('ALLOWCACHE', $oldCache);
	}
	
	
	if($data && getConfig("ALLOWCACHE"))
	{
		//if(empty($data))$data=0;
		$cacheDir=__DIR__."/cache/";
		if(isConfig("cacheDir")) $cacheDir=getConfig("cacheDir");
		
		$md5=md5($url.$json);
		$fh=fopen($cacheDir.$md5, 'w');
		if($fh)
		{
			fwrite($fh, $data);
			fclose($fh);
		}
	}
//	print "*********** DATA ***********\n".$data."\n*********** DATA END ***********\n";	
	
	return $data;
}

function getMulti($urls, $use_proxy = false)
{
	$url_count = count($urls);
	$curl_arr = array();
	$master = curl_multi_init();

	for($i = 0; $i < $url_count; $i++)
	{
	    $url =$urls[$i];
	    $curl_arr[$i] = curl_init($url);
	    curl_setopt($curl_arr[$i], CURLOPT_RETURNTRANSFER, true);
	    curl_multi_add_handle($master, $curl_arr[$i]);
	}


	do {
	    $mrc = curl_multi_exec($master, $active);
	} while ($mrc == CURLM_CALL_MULTI_PERFORM);

	while ($active && $mrc == CURLM_OK) {
	    if (curl_multi_select($master) != -1) {
	        do {
	            $mrc = curl_multi_exec($master, $active);
	        } while ($mrc == CURLM_CALL_MULTI_PERFORM);
	    }
	}
	
	for($i = 0; $i < $url_count; $i++)
	{
		curl_multi_remove_handle($master, $curl_arr[$i]);
	}
	curl_multi_close($master);
}


function curlPost($url, $fields)
{
	$agent= 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_7_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/32.0.1700.107 Safari/537.36';
	$timeout = 160;
	
	if(!defined('USE_PROXY') || USE_PROXY === null) $proxy = null;
	else $proxy = USE_PROXY;
	
	if(getConfig('Proxy')) $proxy=getConfig('Proxy');
	
	//url-ify the data for the POST
	if(is_array($fields))
	{
		$fields_string="";
		foreach($fields as $key=>$value) 
		{ 
			$key=rawurlencode($key);
			$value=rawurlencode($value);
			if(empty($value)) $fields_string .= $key.'&';
			else $fields_string .= $key.'='.$value.'&'; 
		}
		rtrim($fields_string, '&');
	}
	else $fields_string=$fields;
	
	
	$connectTimeout=20;
	if(getConfig('CurlConnectTimeout')) $connectTimeout=getConfig('CurlConnectTimeout');
	//timeout should include connect time out + time for processing
	$timeout=$connectTimeout+60;
	if(getConfig('CurlTimeout')) $timeout=getConfig('CurlTimeout');
	
	//open connection
	$ch = curl_init();

	//set the url, number of POST vars, POST data
	curl_setopt($ch,CURLOPT_URL, $url);
	curl_setopt($ch,CURLOPT_POST, count($fields));
	curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
	curl_setopt($ch, CURLOPT_VERBOSE, false);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_USERAGENT, $agent);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connectTimeout);
	curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_AUTOREFERER, true);
	curl_setopt($ch, CURLOPT_PROXY, $proxy);
	curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
	//needed to propagate servers (for example)
	curl_setopt($ch,CURLOPT_HTTPHEADER,array("Expect:  "));
	if(isConfig('COOKIEJAR'))
	{
		curl_setopt( $ch, CURLOPT_COOKIESESSION, false );
		curl_setopt( $ch, CURLOPT_COOKIEJAR, getConfig('COOKIEJAR') );
		curl_setopt( $ch, CURLOPT_COOKIEFILE, getConfig('COOKIEJAR') );
	}	

	//execute post
	$result = curl_exec($ch);
	//close connection
	curl_close($ch);
	
	
	return $result;
}

// Helper function courtesy of https://github.com/guzzle/guzzle/blob/3a0787217e6c0246b457e637ddd33332efea1d2a/src/Guzzle/Http/Message/PostFile.php#L90
function getCurlFileToPost($filename, $contentType, $postname)
{
    // PHP 5.5 introduced a CurlFile object that deprecates the old @filename syntax
    // See: https://wiki.php.net/rfc/curl-file-upload
    if (function_exists('curl_file_create')) {
        return curl_file_create($filename, $contentType, $postname);
    }
 
    // Use the old style if using an older version of PHP
    $value = "@{$filename};filename=" . $postname;
    if ($contentType) {
        $value .= ';type=' . $contentType;
    }
 
    return $value;
}

function randomUserAgent()
{

	return UserAgent::random();
	
}


/*
"useRemoteCache" => true,
"remoteCacheTTL" => 96*60,
if don't need remote cache, use getSingleNoCache
if need custom ttl, then set it before temp doc, remove after temp doc
if using getSingle, must use 
putRemoteCache(getConfig('curlURLHash'), $html, $cacheTime);
*/

function putRemoteCache($hash, $html, $cacheTime)
{
	if(getConfig('gotFromRemoteCache_'.$hash)) return;
	if(!$hash) return;
	debug("Putting cache for $hash\tsize: ".strlen($html)."\tttl: $cacheTime minutes");
	$body=json_encode(array("html"=>$html));
	$ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://cache.skuio.com/put_cache?ttl='.$cacheTime.'&key='.$hash);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");                                                                     
	curl_setopt($ch, CURLOPT_POSTFIELDS, $body);                                                                  
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);    
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
	curl_setopt($ch, CURLOPT_TIMEOUT, 15);                                                                  
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                          
	    'Content-Type: application/json',                                                                                
	    'Content-Length: ' . strlen($body))                                                                       
	);                                                                                                                   

	$resp = curl_exec($ch);
}

function getRemoteCache($hash)
{
	debug("Getting cache for $hash");
	$ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://cache.skuio.com/get_cache?key='.$hash);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);    
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
	curl_setopt($ch, CURLOPT_TIMEOUT, 15);                                                                  
	$resp = curl_exec($ch);
	
	$json=json_decode($resp,true);
	if(isset($json['html']))
	{
		debug("Got cache for $hash\tsize: ".strlen($json['html']));
		return $json['html'];
	}
	else return false;
}

?>
