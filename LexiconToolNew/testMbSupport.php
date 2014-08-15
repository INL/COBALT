<html>
<head>
<meta name="http-equiv" content="Content-Type: text/html; charset=utf-8">
</head>
<body>

<?php

require_once('./php/normalizeToken.php');

$sVar = "PÖÈÁĚÀÄŽÑÝŮŠ";

print "Lower case version of '$sVar' is '" .
mb_strtolower($sVar, 'UTF-8') . "'<p>";

print "normalized version: '" . normalizeToken($sVar) . "'";

?>

</body>
</html>
