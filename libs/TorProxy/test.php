<?php
ini_set("include_path", "../".PATH_SEPARATOR."libs/");  

require_once "Configurator/Configurator.php";
require __DIR__."/TorProxy.php";

setConfig("DEBUG", true);

print_r(TorProxy::getBusyPorts());
print_r(TorProxy::getRunningTors());
/*$ar=TorProxy::getBusyPorts();
shuffle($ar);
$pid=array_shift($ar);
TorProxy::stop($pid);*/
?>