<?php
$filename = $_GET["file"];

header("Content-type: application/x-download");
header("Content-Disposition: attachment; filename=$filename");
readfile($filename);
