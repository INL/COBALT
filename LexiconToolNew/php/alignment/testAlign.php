<html>
<head>
<meta name="http-equiv" content="Content-Type: text/html; charset=utf-8">
<title>Test alignment</title>
</head>
<body>

<?php

require_once("alignTokens.php");

$sSelectedSentences = '206,7502,7505|206,9597,9600|206,10316,10319|206,10727,10730|207,10822,10825|207,12044,12047|206,12212,12215|207,12648,12651|206,12774,12777|206,12851,12854';

$aSelectedSentences = array_unique(explode('|', $sSelectedSentences));
usort($aSelectedSentences, "cmpSelecteds");

print "aSelecteds:<br>";
foreach($aSelectedSentences as $sKey => $sValue) {
  print "$sKey: $sValue<br>";
}

//Engeland€
$sLine = "dotäba\tDOTÄBA.\t4492\t4496\t\n";
//"hallo\t(.hal<tag>lo.)\t666\t676\n";

$sTest = "ld€aäs";
//preg_match_all("/(.)/u", $sTest, $aMatches, PREG_SET_ORDER);
//for($i = 0; $i < count($aMatches); $i++) {
//foreach ($aMatches as $val) {
//  print "Char: " . $val[1] . ", " . strtoupper($val[1]) . "<br>\n";
//}
$aArr = strToUtf8Array($sTest);
for($i = 0; $i < count($aArr); $i++) {
  print "$i: '$aArr[$i]'<br>\n";
}

$sNewToken = "dot ü|ba";

$aNewOnsetOffset = array();
$iOffsetChange = alignTokens(1, $sLine, $sNewToken, FALSE, 0,
			     $aNewOnsetOffset);

print "<p>Offset change: $iOffsetChange<br>\n";
print "New onset/offsets:<pre>\n";
print_r($aNewOnsetOffset);
print "</pre>\n";

function cmpSelecteds($a, $b) {
  $iPos_a = strpos($a, ",");
  $iDocId_a = substr($a, 0, $iPos_a);

  $iPos_b = strpos($b, ",");
  $iDocId_b = substr($b, 0, $iPos_b);

  if( $iDocId_a != $iDocId_b)
    return $iDocId_a - $iDocId_b;

  $iPos_a++;
  $iPos_b++;

  $iOnset_a = substr($a, $iPos_a, strpos($a, ",", $iPos_a) - $iPos_a);
  $iOnset_b = substr($b, $iPos_b, strpos($b, ",", $iPos_b) - $iPos_b);

  return $iOnset_a - $iOnset_b;
}

?>

</body>
</html>