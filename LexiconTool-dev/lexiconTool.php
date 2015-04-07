<html>
<head>
<meta name="http-equiv" content="Content-Type: text/html; charset=utf-8">
<title>IMPACT CoBaLT v2015.04.02</title>

<!-- prevent caching of javascript etc. -->
<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
<meta http-equiv="Pragma" content="no-cache" />
<meta http-equiv="Expires" content="0" />

<link rel="shortcut icon" type="image/ico" href="./favicon.ico">

<script type="text/javascript" src="./js/globals.js"></script>
<script type="text/javascript" src="./js/helpFunctions.js"></script>
<script type="text/javascript" src="./js/lexiconTool.js"></script>
<script type="text/javascript" src="./js/menuFunctions.js"></script>
<script type="text/javascript" src="./js/tokenAttestation.js"></script>
<script type="text/javascript" src="./js/keyFunctions.js"></script>
<script type="text/javascript" src="./js/fileBrowser.js"></script>
<script type="text/javascript" src="./js/scroller.js"></script>
<script type="text/javascript" src="jquery/js/jquery-1.5.1.min.js"></script>
<script type="text/javascript" src="jquery/js/jquery-ui-1.8.11.custom.min.js"></script>

<script type="text/javascript">

var iStartTopHeight = 0;
var iStartBottomHeight = 0;

$(function() {
 $("#resizer").draggable({axis: "y",
       start: function(event, ui) { startDragging(ui); },
       drag: function(event, ui) { resizeWindowParts(ui); } });
  });

function startDragging(ui) {
  iStartTopHeight = document.getElementById('wordsToAttest').offsetHeight;
  iStartBottomHeight = document.getElementById('sentences').offsetHeight;
}

function resizeWindowParts(ui) {
 var oTop = document.getElementById('wordsToAttest'); 
 var oBottom = document.getElementById('sentences'); 
 var iNewTopHeight = iStartTopHeight + ui.position.top;
 var iNewBottomHeight = iStartBottomHeight - ui.position.top;
 oTop.style.height = iNewTopHeight + 'px';
 oBottom.style.height = iNewBottomHeight + 'px';
 ui.position.top = 0;
}

</script>

<script type="text/javascript" language="JavaScript">

// Definition in keyFunctions.js
document.onmouseup = endSelection;
document.onkeyup = keyUpHandler;

// Preload some images so the progress is displayed smoothly
var pic1= new Image(104,10);
pic1.src="./img/ProgressBar_0.png"; 
var pic2= new Image(104,10); 
pic2.src="./img/ProgressBar_5.png"; 
var pic3= new Image(104,10); 
pic3.src="./img/ProgressBar_15.png"; 
var pic4= new Image(104,10); 
pic4.src="./img/ProgressBar_20.png"; 
var pic5= new Image(104,10); 
pic5.src="./img/ProgressBar_25.png"; 
var pic6= new Image(104,10); 
pic6.src="./img/ProgressBar_30.png"; 
var pic7= new Image(104,10); 
pic7.src="./img/ProgressBar_35.png"; 
var pic8= new Image(104,10); 
pic8.src="./img/ProgressBar_40.png"; 
var pic9= new Image(104,10); 
pic9.src="./img/ProgressBar_45.png"; 
var pic10= new Image(104,10); 
pic10.src="./img/ProgressBar_50.png"; 
var pic11= new Image(104,10); 
pic11.src="./img/ProgressBar_55.png"; 
var pic12= new Image(104,10);
pic12.src="./img/ProgressBar_60.png"; 
var pic13= new Image(104,10); 
pic13.src="./img/ProgressBar_65.png"; 
var pic14= new Image(104,10);
pic14.src="./img/ProgressBar_70.png";
var pic15= new Image(104,10);
pic15.src="./img/ProgressBar_75.png";
var pic16= new Image(104,10);
pic16.src="./img/ProgressBar_80.png";
var pic17= new Image(104,10);
pic17.src="./img/ProgressBar_85.png";
var pic18= new Image(104,10);
pic18.src="./img/ProgressBar_90.png";
var pic19= new Image(104,10);
pic19.src="./img/ProgressBar_95.png";
var pic20= new Image(104,10);
pic20.src="./img/ProgressBar_100.png";
var pic21= new Image(16,16);
pic21.src="./img/circle-ball-dark-antialiased.gif"; 
var pic22= new Image(15,15);
pic22.src = "./img/checked.gif";
var pic23= new Image(15,15);
pic23.src = "./img/unchecked.gif";
var pic24= new Image(15,15);
pic24.src = "./img/grayChecked.gif";

</SCRIPT>

<link rel="stylesheet" type="text/css" href="./css/lexiconTool.css">
   </link>

<?php

// if we don't set the timezone manually, pclzip will give an error ("It is not safe to rely on the system's timezone settings")
// (see user 'PeerGoal.com' at http://php.net/manual/en/function.date-default-timezone-set.php)
date_default_timezone_set("Europe/Amsterdam");

// error handler function
function customError($errno, $errstr, $errfile, $errline)
  {
  echo "<b>Error:</b> [$errno] $errstr<br />";
  echo " Error on line $errline in $errfile<br />";
  echo "Ending Script";
  die();
  }
// set error handler
set_error_handler("customError");

// this call the globals.php too
require_once('./php/lexiconToolBox.php');

// check if some custom css file is required
$tmpCustomCssArray = $GLOBALS['asCustomCss'];
if ( isset($_REQUEST['sDatabase']) )
	{	
	if ( isset($tmpCustomCssArray) && isset( $tmpCustomCssArray[ $_REQUEST['sDatabase'] ] ) )
		{
		$sRightCssFileToUse = $tmpCustomCssArray[ $_REQUEST['sDatabase'] ];
		print '<link rel="stylesheet" type="text/css" ' . "href=\"./css/".$sRightCssFileToUse."\"></link>\n";
		}
	else
		{
		print '<link rel="stylesheet" type="text/css" href="./css/lexiconTool_normal.css">' . "</link>\n";
		}
	}

// should we use the format checker?
if( $GLOBALS['bCheckAnalysisFormatValidity'] )
 print '<script type="text/javascript" ' .
   'src="./js/analysisFormatChecker.js"></script>';

   
$sDatabase = isset($_REQUEST['sDatabase']) ? $_REQUEST['sDatabase'] : false;
$iUserId = isset($_REQUEST['iUserId']) ? $_REQUEST['iUserId'] : false;
$sUserName = isset($_REQUEST['sUserName']) ? $_REQUEST['sUserName'] : false;
$sNewUploadFile = isset($_FILES['sNewUploadFile']) ?
 $_FILES['sNewUploadFile']['tmp_name'] : false;
if( isset($_FILES['sNewUploadFile']['error']) &&
    ($_FILES['sNewUploadFile']['error'] != UPLOAD_ERR_OK) ) {
  handleUploadFileError($_FILES['sNewUploadFile']['error']);
  $sNewUploadFile = false;
 }
$iCorpusAddedTo = isset($_REQUEST['iCorpusAddedTo']) ?
 $_REQUEST['iCorpusAddedTo'] : 0;
$iFileId = isset($_REQUEST['iFileId']) ? $_REQUEST['iFileId'] : false;
$sFileName = isset($_REQUEST['sFileName']) ? $_REQUEST['sFileName'] : false;
$iCorpusId = isset($_REQUEST['iCorpusId']) ? $_REQUEST['iCorpusId'] : false;
$sCorpusName = isset($_REQUEST['sCorpusName']) ? $_REQUEST['sCorpusName']
: false;

// Make deep linking to a particular filter possible
$sGoto = isset($_REQUEST['sGoto']) ? addslashes($_REQUEST['sGoto']) : '';
$sFilter = isset($_REQUEST['sFilter']) ? addslashes($_REQUEST['sFilter']) : '';
$sLemmaFilter = isset($_REQUEST['sLemmaFilter']) ?
 addslashes($_REQUEST['sLemmaFilter']) : '';
// NOTE that addslashes(rawurldecode($_REQUEST['sFilter']))
// is not needed here, as the filter comes decoded already.

// NOTE that case INsensitivity is default ON
$bCaseInsensitivity = isset($_REQUEST['bCaseInsensitivity']) ?
($_REQUEST['bCaseInsensitivity'] == 'true') ? true : false : true;

// Don't show options.
// The default is not to show the don't shows, so we need to know what we DO
// have to show
$bDoShowAll = isset($_REQUEST['bDoShowAll']) ? $_REQUEST['bDoShowAll'] : 0;
$bDoShowCorpus = isset($_REQUEST['bDoShowCorpus']) ?
 $_REQUEST['bDoShowCorpus'] : 0;
$bDoShowDocument = isset($_REQUEST['bDoShowDocument']) ?
 $_REQUEST['bDoShowDocument'] : 0;
 
// Nr of word forms per page is 100 by default
// NOTE that this default is a value that is also featured in the
// $GLOBALS['aNrOfWordFormsPerPage'] list.
$iNrOfWordFormsPerPage = isset($_REQUEST['iNrOfWordFormsPerPage']) ?
 $_REQUEST['iNrOfWordFormsPerPage'] : 100;
 
// Nr of word forms per page is 100 by default
// NOTE that this default is a value that is also featured in the
// $GLOBALS['aNrOfSentencesPerWordform'] list.
$iNrOfSentencesPerWordform = isset($_REQUEST['iNrOfSentencesPerWordform']) ?
 $_REQUEST['iNrOfSentencesPerWordform'] : 15;
 
// Amount of context. Default 220
$iAmountOfContext = isset($_REQUEST['iAmountOfContext']) ?
 $_REQUEST['iAmountOfContext'] : $GLOBALS['aAmountOfContext']['normal'];
$iStartAt = isset($_REQUEST['iStartAt']) ? $_REQUEST['iStartAt'] : 0;
$sSortBy = isset($_REQUEST['sSortBy']) ? $_REQUEST['sSortBy'] : 'wordForm';
$sSortMode = isset($_REQUEST['sSortMode']) ? $_REQUEST['sSortMode'] : 'asc';
$sSortSentencesBy = isset($_REQUEST['sSortSentencesBy']) ?
  $_REQUEST['sSortSentencesBy'] : 'doc';
$sSortSentencesMode = isset($_REQUEST['sSortSentencesMode']) ?
  $_REQUEST['sSortSentencesMode'] : 'asc';
$bSortReverse = isset($_REQUEST['bSortReverse'])
     ? ($_REQUEST['bSortReverse'] == 'true') ? true : false : false;
	 
	 
if ($sGoto != '')
	$iStartAt = getPageRank($sGoto, ($iFileId ? $iFileId : $iCorpusId), 
				   ($iFileId ? 'file' : ($iCorpusId ? 'corpus' : '')), $sSortBy, $sSortMode, 
				   $bSortReverse, $sFilter, $bCaseInsensitivity,
				   $sLemmaFilter, $bDoShowAll,
				   $bDoShowCorpus, $bDoShowDocument, $iNrOfWordFormsPerPage);

// Someone has just logged in with a name. Get the id.
if( $sUserName && ! $iUserId) {
  $iUserId = getUserId($sDatabase, $sUserName);
}

$sFillWords_JS = ($iStartAt) ?
  "fillWordsToAttest_($iStartAt, $iNrOfWordFormsPerPage, false)" :
  "fillWordsToAttest()";

// The first time not logged in yet, or unkown user.
if( ! $sUserName || ! $iUserId ) {
  printTitle(false, false, false, false);

  print "</head>\n<body bgcolor='#FFFFFF' " .
    "onLoad='javascript: document.loginForm.sUserName.focus(); if(navigator.userAgent.indexOf(\"Firefox\")<0) {alert(\"This piece of software only works in Firefox. Please close your current browser and start again in Firefox. Thank you!\");};'>\n" .
    "<center><h1>CoBaLT - IMPACT Corpus-Based Lexicon Tool</h1></center>\n<p>\n";

  if( $sUserName && ! $iUserId ) {
    print "<b>Error</b>: unkown user '$sUserName'\n<p>\n";
  }
?>


<form action="./lexiconTool.php" method=POST name=loginForm>
<table>
   <tr>
   <td style="padding: 0px 30px 0px 30px">
<?php
// display the projects list
$iProjectListCounter = 1;
$aProjectList = $GLOBALS['asProject'];
$iProjectListLength = count($aProjectList);

foreach ($aProjectList as $sProjectCode => $sProjectDescription) {

 print '<p>'.
 '<input type="radio" name="sDatabase" value="'.$sProjectCode.'"';
 if ($sProjectCode == $GLOBALS['sChecked'])
	print ' checked';
 print '>'.$sProjectDescription.
 '</p>'; 
 
 if ($iProjectListCounter > (int)($iProjectListLength / 3) )
	{
	$iProjectListCounter = 1;
	print '</td><td style="padding: 0px 30px 0px 30px">';
	}
 else
	$iProjectListCounter++;
}
?>
<p>
</td>
</tr>
</table>

   <p>

Please log in <input id=sUserName name=sUserName type="text" autocomplete="on" value="tom" size="15" maxlength="25"></p>
    </form>
<?php  
}
elseif( $iFileId ) { // Somebody clicked on a file
  printTitle($sDatabase, $sUserName, 'file', $sFileName);

  // Make a global Javascript variable of the user id, etc.
  printJavascriptGlobals($sDatabase, $iUserId, $sUserName, $iFileId,
			 $sFileName, 'file', $sFilter, $bCaseInsensitivity,
			 $sLemmaFilter, $sSortBy, $sSortMode, $bSortReverse,
			 $sSortSentencesBy, $sSortSentencesMode,
			 $bDoShowAll, $bDoShowCorpus, $bDoShowDocument,
			 $iNrOfWordFormsPerPage, $iNrOfSentencesPerWordform,
			 $iAmountOfContext, $sGoto);

  print "</head>\n" .
    "<body bgcolor='#FFFFFF' onLoad=\"javascript: $sFillWords_JS;\"" .
    " onClick=\"javascript: hideMenus(); arrangeKeydown();\">\n";
  printMainPage($sDatabase, $iFileId, $sFileName, 'file', $iUserId, $sUserName,
		$sFilter, $bCaseInsensitivity, $sLemmaFilter, $bDoShowAll,
		$bDoShowCorpus, $bDoShowDocument, $iNrOfWordFormsPerPage,
		$iNrOfSentencesPerWordform, $iAmountOfContext,
		$sSortSentencesBy, $sSortSentencesMode, $sGoto);
}
elseif( $iCorpusId ) { // Somebody clicked on a corpus
  printTitle($sDatabase, $sUserName, 'corpus', $sCorpusName);

  // Make a global Javascript variable of the user id, etc.
  printJavascriptGlobals($sDatabase, $iUserId, $sUserName, $iCorpusId,
			 $sCorpusName, 'corpus', $sFilter, $bCaseInsensitivity,
			 $sLemmaFilter, $sSortBy, $sSortMode, $bSortReverse,
			 $sSortSentencesBy, $sSortSentencesMode,
			 $bDoShowAll, $bDoShowCorpus, $bDoShowDocument,
			 $iNrOfWordFormsPerPage, $iNrOfSentencesPerWordform,
			 $iAmountOfContext, $sGoto);

  print "</head>\n" .
    "<body bgcolor='#FFFFFF' onLoad=\"javascript: $sFillWords_JS;\"" .
    " onClick=\"javascript: hideMenus(); arrangeKeydown();\">\n";
  printMainPage($sDatabase, $iCorpusId, $sCorpusName, 'corpus', $iUserId,
		$sUserName, $sFilter, $bCaseInsensitivity, $sLemmaFilter,
		$bDoShowAll, $bDoShowCorpus, $bDoShowDocument,
		$iNrOfWordFormsPerPage, $iNrOfSentencesPerWordform,
		$iAmountOfContext, $sSortSentencesBy, $sSortSentencesMode, $sGoto);
}
else { // First time after login
  printTitle($sDatabase, $sUserName, false, false);

  // Make a global Javascript variable of the user id
  print "<script type=\"text/javascript\">var sDatabase = '$sDatabase'; " .
    "var iUserId = $iUserId; var sUserName = '$sUserName';</script>";
  
  if( $sNewUploadFile ) { // If the user added a file to a corpus
    // Dummy value
    $sAuthor = 'Impact';
    $sNewFile =
      "$sDocumentRoot/" . basename($_FILES['sNewUploadFile']['name']);

    // If it exists already there is no need to copy it again
    if( ! file_exists($sNewFile) )
      move_uploaded_file($_FILES['sNewUploadFile']['tmp_name'], $sNewFile);

    processFile($sDocumentRoot, $sZipExtractDir, $sDatabase, $iCorpusAddedTo,
		$sNewFile, $sAuthor);

  }

  print "</head>\n<body bgcolor='#FFFFFF' onLoad=\"javascript: " .
    "fillFileBrowser($iUserId, '$sUserName', $iCorpusAddedTo);\">\n";
?>

<table width=100%>
 <tr>
   <td width=10%>&nbsp;</td>
   <td align=center><h1>CoBaLT - IMPACT Corpus-Based Lexicon Tool</h1></td>
   <td align=right width=10%><form action='./lexiconTool.php' method=POST>
<input type=submit value='<< Start page'></form></td>
</table>
Corpora:&nbsp;
<span id=newCorpus onClick="javascript: toggleNewCorpus();">
 <img alt='New corpus' title='New corpus' src='./img/folderNew.png'>
</span>
<span id=newCorpusInput style='display: none;'>
</span>
<div id=fileBrowser></div>

<?php
}
?>

</body>
</html>
