<?php
require_once('globals.php');
$sImage = isset($_REQUEST['sImage']) ? $_REQUEST['sImage'] : false;

// Special cases: 

// If the 'images' are in fact HTML pages, do a redirect 
if( preg_match("/\.html/i", $sImage) )
    header( "Location: $sImage" );

// If the images must be shown through a special web service, do a redirect 
if($GLOBALS['bDisplayImageInService']) {  
   if(preg_match("/([0-9]{6,8})([^0-9\/][^\/]*)?\.xml/i", $sImage,$matches)
      > 0) {
     $sID = sprintf("%08d", $matches[0]);
     print $sID . "<br>";
     $iPosX = isset($_REQUEST['iPosX']) ? $_REQUEST['iPosX'] : false;
     $iPosY = isset($_REQUEST['iPosY']) ? $_REQUEST['iPosY'] : false;
     $iPosHeight = isset($_REQUEST['iPosHeight'])
       ? $_REQUEST['iPosHeight'] : false;
     $iPosWidth = isset($_REQUEST['iPosWidth'])
       ? $_REQUEST['iPosWidth'] : false;

     $sImageServiceLocation = $GLOBALS['sImageServiceLocation'];

     $sURL = "$sImageServiceLocation/$sID.host?hostopts&page=1&zoom=width" .
       "&showposition=$iPosX,$iPosY" .
       "&highlight=$iPosX,$iPosY,$iPosWidth,$iPosHeight";
     header( "Location: $sURL" ); // Redirect
   }
 }

?>
<html>
<head>

<link rel="stylesheet" type="text/css" href="../css/lexiconTool.css">
   </link>

<script type="text/javascript" src="../js/helpFunctions.js"></script>

<script>

document.onkeydown = myKeyDown;
document.onkeyup = myKeyUp;

function myKeyDown() {
  document.getElementById('wordBox').className = 'wordBox_invisible';
  document.getElementById('wordBoxBorder').className = 'wordBox_invisible';
}

function myKeyUp() {
  document.getElementById('wordBox').className = 'wordBox';
  document.getElementById('wordBoxBorder').className = 'wordBoxBorder';
}

function scrollToWordBox() {
  var oWordBox = document.getElementById('wordBox');

  if( oWordBox ) {
    var iWindowHeight = document.body.clientHeight;
    var iWindowWidth = document.body.clientWidth;
  
    var aWordBoxCoordinates = findPos(oWordBox);
    var iWordBoxLowerCorner = aWordBoxCoordinates[1] + oWordBox.offsetHeight;
    var iWordBoxRightCorner = aWordBoxCoordinates[0] + oWordBox.offsetWidth;

    if( iWordBoxLowerCorner > iWindowHeight)
      document.body.scrollTop = aWordBoxCoordinates[1] - (iWindowHeight/2);
    if( iWordBoxRightCorner > iWindowWidth)
      document.body.scrollLeft = aWordBoxCoordinates[0] - (iWindowWidth/2);
  }
}

</script>

<?php

$sImage = isset($_REQUEST['sImage']) ? $_REQUEST['sImage'] : false;


$iPosX = isset($_REQUEST['iPosX']) ? $_REQUEST['iPosX'] : false;
$iPosY = isset($_REQUEST['iPosY']) ? $_REQUEST['iPosY'] : false;
$iPosHeight = isset($_REQUEST['iPosHeight']) ? $_REQUEST['iPosHeight'] : false;
$iPosWidth = isset($_REQUEST['iPosWidth']) ? $_REQUEST['iPosWidth'] : false;
$sSize = isset($_REQUEST['sSize']) ? $_REQUEST['sSize'] : false;

// For the link
$iOrigPosX = $iPosX;
$iOrigPosY = $iPosY;
$iOrigPosWidth = $iPosWidth;
$iOrigPosHeight = $iPosHeight;

$aSize = getimagesize($sImage);
$iImageWidth = $aSize[0];

// The images can be quite big, so scale them down first
$fScalingFactor = ($sSize == 'small') ? 3.5 : 1;
$iNewImageWidth = $iImageWidth/$fScalingFactor;
$iPosX /= $fScalingFactor;
$iPosY /= $fScalingFactor;
$iPosWidth /= $fScalingFactor;
$iPosHeight /= $fScalingFactor;

// This is correlated to top and left in the style sheet
// (make sure that left and top margins are 0px).
$iPosX += 40;
$iPosY += 40;

print "<script type=\"text/javascript\">\n" .
"if (document.images) {\n" .
"  pic1= new Image();\n" .
"  pic1.src='$sImage';\n" .
"}\n" .
"</script>\n" .
"<title>$sImage</title>\n";

// NOTE that this factor is the scaling factor, but the other way around
$iBorderFactor = ($sSize == 'big') ? 3.5 : 1;
$iCssBorderWidth = $iBorderFactor * 2;
print "<style>" .
"#wordBox {\n" .
" position: absolute;\n" .
" top: $iPosY;\n" .
" left: $iPosX;\n" .
" height: $iPosHeight;\n" .
" width: $iPosWidth;\n" .
"}\n" .
"\n" .
// Note that the CSS border has to be adjusted for (2 * iCssBorderWidth)
// And also the one pixel around the wordBox (so that is the + 2)
".wordBoxBorder {\n" .
" position: absolute;\n" .
" top: " . ($iPosY - (2 * $iCssBorderWidth) ) . ";\n" .
" left: " . ($iPosX - (2 * $iCssBorderWidth) ) . ";\n" .
" height: " . ($iPosHeight + 2 + (2 * $iCssBorderWidth) ) . ";\n" .
" width: " . ($iPosWidth + 2 + (2 * $iCssBorderWidth) ) . ";\n" .
" border: ${iCssBorderWidth}px solid #FF7800;\n" .
"}\n" .
"</style>";

// Toggle size
$sNewSize = ($sSize == 'big') ? 'small' : 'big';

$sLink = "./displayImage.php?sImage=" . rawurlencode($sImage) . 
"&iPosX=$iOrigPosX&iPosY=$iOrigPosY&iPosHeight=$iOrigPosHeight" .
"&iPosWidth=$iOrigPosWidth&sSize=$sNewSize";

?>
</head>
<body bgcolor=#F6F6F6 style="margin: 0;"
      onLoad="javascript: scrollToWordBox();">

<div id=closeDisplayImage align=right>
<form action='JavaScript:window.close()' method=POST>
<input type=submit value='Close'></form>
</div>

<?php
// We only really print the box if it matters.
if( isset($iOrigPosX) && ($iOrigPosX > 0) &&
    isset($iOrigPosY) && ($iOrigPosY > 0) ) {
  print "<div id=wordBox class=wordBox".
  " onMouseOver=\"javascript: this.style.cursor = 'pointer';\"" .
  " onClick=\"javascript: document.location.href = '$sLink';\">&nbsp;</div>\n";

  print "<div id=wordBoxBorder class=wordBoxBorder".
  " onMouseOver=\"javascript: this.style.cursor = 'pointer';\"" .
  " onClick=\"javascript: document.location.href = '$sLink';\">&nbsp;</div>\n";
}

?>

<div id=imageDisplay>

<?php

print "<a href='$sLink'>" .
"<img src='$sImage' width=$iNewImageWidth title='Click to make $sNewSize' border=0></a>";
?>
</div>

</body>
</html>
