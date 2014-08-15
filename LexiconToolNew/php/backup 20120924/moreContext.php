<?php

require_once('./lexiconToolBox.php');

$sDocumentTitle = $_REQUEST['sDocumentTitle'];
$iDocumentSize = $_REQUEST['iDocumentSize'];
$iSearchOffset = $_REQUEST['iSearchOffset'];
$iWindowWidth = $_REQUEST['iWindowWidth'];
$iOrigWindowWidth= $_REQUEST['iOrigWindowWidth'];
$sWordForm = urldecode($_REQUEST['sWordForm']);
$iSearchWordLength = $_REQUEST['iSearchWordLength'];

?>

<html>
<head>
<meta name="http-equiv" content="Content-Type: text/html; charset=utf-8">

<?php
print "<title>" .
  "IMPACT - Lexicon Tool - More context for file '" .
  substr($sDocumentTitle, strlen($GLOBALS['sDocumentRoot']) + 1) .
  "'</title>"
?>

<link rel="shortcut icon" type="image/ico" href="../favicon.ico" />

<link rel="stylesheet" type="text/css" href="../css/lexiconTool.css">
   </link>
</head>
<body bgcolor="#F6F6F6">

<?php

$fh = fopen($sDocumentTitle, 'r');
// Get the entire file
$sFileContents = fread($fh, $iDocumentSize);
fclose($fh);

// Enlarge the window with the original width
$iNewWindowWidth = $iWindowWidth + $iOrigWindowWidth;

// Special axtra option
$iNewLargeWindowWidth = ($iWindowWidth * 100) + $iOrigWindowWidth;

$iWindowStart = ($iSearchOffset - $iSearchWordLength - $iNewWindowWidth);
$iWindowLength = $iNewWindowWidth;
if( $iWindowStart < 0) {
  $iWindowLength += $iWindowStart; // Shorten it
  $iWindowStart = 0;
}

print
// "Document: " .
//substr($sDocumentTitle, strlen($GLOBALS['sDocumentRoot']) + 1) .
//" (window length: $iNewWindowWidth)\n<p>\n" .
"<div align=right><form action='JavaScript:window.close()' method=POST>" .
" <input type=submit value='Close'></form></div>";

print "<div class=moreContextDoc>";
print "<div align=right>";
print "<span>" .
"<a href=\"./moreContext.php?sDocumentTitle=$sDocumentTitle" .
"&iDocumentSize=$iDocumentSize&iSearchOffset=$iSearchOffset" .
"&sWordForm=" . urlencode($sWordForm) .
"&iSearchWordLength=$iSearchWordLength" .
"&iWindowWidth=$iNewWindowWidth&iOrigWindowWidth=$iOrigWindowWidth\">" .
"&raquo; Yet more context...</a></span>&nbsp;&nbsp;";
# New extra option
print "<span align=right>" .
"<a href=\"./moreContext.php?sDocumentTitle=$sDocumentTitle" .
"&iDocumentSize=$iDocumentSize&iSearchOffset=$iSearchOffset" .
"&sWordForm=" . urlencode($sWordForm) .
"&iSearchWordLength=$iSearchWordLength" .
"&iWindowWidth=$iNewLargeWindowWidth&iOrigWindowWidth=$iOrigWindowWidth\">" .
"&raquo;&raquo; A lot more context...</a></span>";
print "</div>\n";
print "<p>";
print makePrintable_simple(substr($sFileContents, $iWindowStart,
				  $iWindowLength));
print " <span class=matchedPart>" . /// $sWordForm .
substr($sFileContents,
       $iSearchOffset - 2,
       strpos(substr($sFileContents, $iSearchOffset - 1, 300), "\t") + 1) .
  "</span>";
print makePrintable_simple(substr($sFileContents, $iSearchOffset,
				  $iNewWindowWidth));

print "<div align=right>" .
"<span>" .
"<a href=\"./moreContext.php?sDocumentTitle=$sDocumentTitle" .
"&iDocumentSize=$iDocumentSize&iSearchOffset=$iSearchOffset" .
"&sWordForm=" . urlencode($sWordForm) .
"&iSearchWordLength=$iSearchWordLength" .
"&iWindowWidth=$iNewWindowWidth&iOrigWindowWidth=$iOrigWindowWidth\">" .
"&raquo; Yet more context...</a></span>&nbsp;&nbsp;";
# New extra option
print "<span align=right>" .
"<a href=\"./moreContext.php?sDocumentTitle=$sDocumentTitle" .
"&iDocumentSize=$iDocumentSize&iSearchOffset=$iSearchOffset" .
"&sWordForm=" . urlencode($sWordForm) .
"&iSearchWordLength=$iSearchWordLength" .
"&iWindowWidth=$iNewLargeWindowWidth&iOrigWindowWidth=$iOrigWindowWidth\">" .
"&raquo;&raquo; A lot more context...</a></span>";
print "</div>"; // More context div

print "</div>"; // Entire div

?>
<p>
<div align=right><form action='JavaScript:window.close()' method=POST>
<input type=submit value='Close'></form>

</div>

</body>
</html>
