<?php

require_once('lexiconToolBox.php');

$sDatabase =  $_REQUEST['sDatabase'];
$iDocumentId = $_REQUEST['iDocumentId'];
$sDocumentTitle = $_REQUEST['sDocumentTitle'];
$iDocumentSize = $_REQUEST['iDocumentSize'];
$iSearchOffset = $_REQUEST['iSearchOffset'];
$iWindowWidth = $_REQUEST['iWindowWidth'];
$iOrigWindowWidth= $_REQUEST['iOrigWindowWidth'];
$sWordForm = urldecode($_REQUEST['sWordForm']);
$iSearchWordLength = $_REQUEST['iSearchWordLength'];

chooseDb($sDatabase);
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


// 2013: Get the entire file
// two possible modes: full database mode, or physical files mode
if (fullDatabaseMode($sDatabase))
	{
	$sFileContents = getDocumentContentFromDb($sDocumentTitle);
	}
else
	{
	$fh = fopen($sDocumentTitle, 'r');
	$sFileContents = fread($fh, $iDocumentSize);
	fclose($fh);
	}


// Enlarge the window with the original width
$iNewWindowWidth = $iWindowWidth + $iOrigWindowWidth;

// Special extra option (computed for the MORE CONTEXT buttons)
$iNewLargeWindowWidth = ($iWindowWidth * 100) + $iOrigWindowWidth;

// start index of content, and context length
// [start index]  = [position of the central word] - [its length] - [window length]
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
"<a href=\"./moreContext.php?sDatabase=$sDatabase&iDocumentId=$iDocumentId&
sDocumentTitle=$sDocumentTitle" .
"&iDocumentSize=$iDocumentSize&iSearchOffset=$iSearchOffset" .
"&sWordForm=" . urlencode($sWordForm) .
"&iSearchWordLength=$iSearchWordLength" .
"&iWindowWidth=$iNewWindowWidth&iOrigWindowWidth=$iOrigWindowWidth\">" .
"&raquo; Yet more context...</a></span>&nbsp;&nbsp;";
# New extra option
print "<span align=right>" .
"<a href=\"./moreContext.php?sDatabase=$sDatabase&iDocumentId=$iDocumentId&
sDocumentTitle=$sDocumentTitle" .
"&iDocumentSize=$iDocumentSize&iSearchOffset=$iSearchOffset" .
"&sWordForm=" . urlencode($sWordForm) .
"&iSearchWordLength=$iSearchWordLength" .
"&iWindowWidth=$iNewLargeWindowWidth&iOrigWindowWidth=$iOrigWindowWidth\">" .
"&raquo;&raquo; A lot more context...</a></span>";
print "</div>\n";


// print the context without tags, but with the central word highlighted
print "<p>";

// left part
print makePrintable_simple(substr($sFileContents, $iWindowStart, $iWindowLength));

// central part (matched)
print " <span class=matchedPart>" . /// $sWordForm .
substr($sFileContents,
       $iSearchOffset - 2,
       strpos(substr($sFileContents, $iSearchOffset - 1, 300), "\t") + 1) .
  "</span>";
  
// right part
print makePrintable_simple(substr($sFileContents, $iSearchOffset, $iNewWindowWidth));
				  
print "</p>";


// context with HTML tags, if required!

// index of central part (is the same as the length of the left part)
$aLeftPart = preg_split("/\s+/", trim(makePrintable_simple(substr($sFileContents, $iWindowStart, $iWindowLength))));
$iCentralWordNumber = count($aLeftPart);

if( $GLOBALS['bFullAnalyses'] )
		{
		print "<p>";

		// print the context WITH tags
		$iTagContextStart = $iWindowStart;
		$iTagContextEnd   = $iNewWindowWidth * 2;
		print makePrintableWithTags($sDatabase, $iDocumentId, substr($sFileContents, $iTagContextStart, $iTagContextEnd), $iCentralWordNumber);
		
		
		print "</p>";
		}
				  
				  
print "<div align=right>" .
"<span>" .
"<a href=\"./moreContext.php?sDatabase=$sDatabase&iDocumentId=$iDocumentId&
sDocumentTitle=$sDocumentTitle" .
"&iDocumentSize=$iDocumentSize&iSearchOffset=$iSearchOffset" .
"&sWordForm=" . urlencode($sWordForm) .
"&iSearchWordLength=$iSearchWordLength" .
"&iWindowWidth=$iNewWindowWidth&iOrigWindowWidth=$iOrigWindowWidth\">" .
"&raquo; Yet more context...</a></span>&nbsp;&nbsp;";
# New extra option
print "<span align=right>" .
"<a href=\"./moreContext.php?sDatabase=$sDatabase&iDocumentId=$iDocumentId&
sDocumentTitle=$sDocumentTitle" .
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
