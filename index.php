<?php
ini_set('max_execution_time', 86400);
ini_set('xdebug.max_nesting_level', 1000);
ini_set("include_path", ".." . PATH_SEPARATOR . "libs/" . PATH_SEPARATOR . "../old/");
require_once "Configurator/configuration.php";
setConfig('DEBUG', false);
require_once __DIR__."/vendor/autoload.php";
require_once __DIR__ . "/classes/parser.class.php";

setConfig('useTor', false);
setConfig('useProxy', true);
setConfig('ALLOWCACHE', true);


if (isset($_POST["keyword"]) && !empty(trim($_POST["keyword"])) && isset($_FILES['userfile']["name"])){
    $uploadfile = __DIR__ .'/'. basename("data.csv");
    $keyword=explode("\r\n",trim($_POST["keyword"]));

    if (move_uploaded_file($_FILES['userfile']['tmp_name'], $uploadfile)) {
        $parser = new Parser($keyword,$uploadfile);
        $filename=$parser->parse();
    } else {
        echo "Error, file not uploaded..\n";
    }
}
?>
<html lang="en-US">
<head>
    <title>Search words</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="message"></div>
<script>

    function myFunction() {
        var el=document.getElementById('keyword');
        if(el.value.length>0) {
            var x = document.getElementById("preloader");
            if (x.style.display === "none") {
                x.style.display = "block";
            } else {
                x.style.display = "none";
            }

            var d = document.getElementById("download");
            d.remove();
        }
    }
</script>
<form method="post" enctype="multipart/form-data">
    <div class="inline">
        <textarea name="keyword" type="text" id="keyword" placeholder="Each keyword from new line" style="width: 400px;height: 200px;" required></textarea>

        <div>File with links: <input name="userfile" type="file" required/></div>
        <button type="submit" onclick="myFunction()">Search</button>
        <img id="preloader" style="display: none" src="img/Ellipsis.svg">
        <?php
        if (!empty($filename)) {
            echo '<a id="download" href="getFile.php?file=' . $filename . '">Download</a>';
        } ?>
    </div>
</form>

</body>
</html>
