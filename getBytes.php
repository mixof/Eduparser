<?php
/**
 * Created by PhpStorm.
 * User: Алексей
 * Date: 27.04.2018
 * Time: 23:12
 */

$bytes=file_get_contents(__DIR__."/utils/loadbytes.dat");
$bytes=intval($bytes);
echo $bytes;
exit();