<?php

// PHP version of http://www.biorecipes.com/DynProgBasic/code.html


$sS = "ACCCCC";
$sT = "CCCACTT";

$iN = strlen($sS);
$iM = strlen($sT);

$aD = array();
for($i = 0; $i < $iM; $i++) {
  $aD[$i] = array();
}

$aSimMat = array(array(2, -1, 1, -1),
		 array(-1, 2, -1, 1),
		 array(1, -1, 2, -1),
		 array(-1, 1, -1, 2) );

$iGapScore = -2;

$aD[0][0] = 0;

for($j = 0; $j <= $iN; $j++) {
  $aD[0][$j] = $iGapScore * $j;
}

for($i = 0; $i <= $iM; $i++) {
  $aD[$i][0] = $iGapScore * $i;
}



for($i = 1; $i <= $iM ; $i++) {
  for($j = 1; $j <= $iN; $j++) {
    $iMatch = $aD[$i-1][$j-1] + $aSimMat[toInt($sS{$j-1})][toInt($sT{$i-1})];
    $iGapS = $aD[$i][$j-1] + $iGapScore;
    $iGapT = $aD[$i-1][$j] + $iGapScore;
    $aD[$i][$j] = max($iMatch,$iGapS,$iGapT);
  }
}

printArr($aD);

$i = $iM;
$j = $iN;

$sT_Align = '';
$sS_Align = '';

while( ($i > 0) && ($j > 0) ) {
  if( ($aD[$i][$j] - $aSimMat[toInt($sS{$j-1})][toInt($sT{$i-1})]) ==
      $aD[$i-1][$j-1] ) {
    $sT_Align = $sT{$i-1} . $sT_Align; //.t_aln:
    $sS_Align = $sS{$j-1} . $sS_Align; //.s_aln:
    $i--;
    $j--;
  }
  else {
    if( ($aD[$i][$j] - $iGapScore) == $aD[$i][$j-1] ) {
      $sS_Align = $sS{$j-1} . $sS_Align; //.s_aln:
      $sT_Align = '_' . $sT_Align; // .t_aln:
      $j--;
    }
    else {
      if( ($aD[$i][$j] - $iGapScore) == $aD[$i-1][$j]) {
	$sS_Align = '_'. $sS_Align; //.s_aln:
	$sT_Align = $sT[$i-1] . $sT_Align; // .t_aln:
	$i--;
      }
      else {
	print 'should not happen';
      }
    }
  }
}

if( $j > 0) {
  while($j > 0) {
    $sS_Align = $sS{$j-1} . $sS_Align;
    $sT_Align = '_' . $sT_Align;
    $j--;
  }
}
else {
  if($i > 0) {
    while( $i > 0) {
      $sS_Align = '_' . $sS_Align;
      $sT_Align = $sT{$i-1} . $sT_Align;
      $i--;
    }
  }
}

print "Aligning $sS to $sT:<br>";
print "<pre>$sS_Align<br>$sT_Align</pre>\n";

// Functions //////////////////////////////////////////////////////////////////

function toInt($c) {
  if( $c == 'A')
    return 0;
  if( $c == 'C')
    return 1;
  if( $c == 'G')
    return 2;
  if( $c == 'T')
    return 3;
}

function printArr($aArr) {
  print "<table border=1 cellpadding=10>\n";
  for($i = 0; $i < count($aArr); $i++) {
    print "<tr>";
    for($j = 0; $j < count($aArr[$i]); $j++) {
      print "<td>" . $aArr[$i][$j] . "</td>";
    }
    print "</tr>\n";
  }
  print "</table>\n";
}

?>