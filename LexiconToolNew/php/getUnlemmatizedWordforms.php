<?php

require_once('./databaseUtils.php');

$sDatabase =  $_REQUEST['sDatabase'];

?>

<html>
<head>
<meta name="http-equiv" content="Content-Type: text/html; charset=utf-8">
<title>Not yet lemmatized wordforms</title>
</head>

<body bgcolor="#F6F6F6">
<?php
getUnlemmatizedWordforms($sDatabase)
?>
</body>
</html>