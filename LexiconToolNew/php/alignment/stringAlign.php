<?php

// String alignment
//
// This code is a straightforward PHP version of:
// http://www.biorecipes.com/DynProgBasic/code.html
// with a slight adjustment to be able to handle arbitrary input.

// The gap character is a tab ("\t"), so if you have strings containing tabs as
// input, that is fine, but the output can be ambiguous... 

$aSimilarities = FALSE;
$aNonTokenChars = FALSE;

$cGapChar = "\t";

function stringAlign($sS, $sT) {
  // We do this so we can work with the indices of characters in strings.
  // Curly braces ($sS{2} e.g.) doesn't work when the caharacter in question is
  // an utf-8 character... ;-(
  $aS = strToUtf8Array($sS);
  $aT = strToUtf8Array($sT);

  $iN = count($aS); //strlen($sS);
  $iM = count($aT); //strlen($sT);

  $aD = array();
  for($i = 0; $i < $iM; $i++) {
    $aD[$i] = array();
  }

  initGlobals(); // Initializes the global array

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
      $iMatch = $aD[$i-1][$j-1] + getSimScore($aS[$j-1], $aT[$i-1]);
      $iGapS = $aD[$i][$j-1] + $iGapScore;
      $iGapT = $aD[$i-1][$j] + $iGapScore;
      $aD[$i][$j] = max($iMatch,$iGapS,$iGapT);
    }
  }

  $i = $iM;
  $j = $iN;

  $sT_Align = '';
  $sS_Align = '';

  while( ($i > 0) && ($j > 0) ) {
    if( ($aD[$i][$j] - getSimScore($aS[$j-1],$aT[$i-1]) ) ==
	$aD[$i-1][$j-1] ) {
      $sT_Align = $aT[$i-1] . $sT_Align; //.t_aln:
      $sS_Align = $aS[$j-1] . $sS_Align; //.s_aln:
      $i--;
      $j--;
    }
    else {
      if( ($aD[$i][$j] - $iGapScore) == $aD[$i][$j-1] ) {
	$sS_Align = $aS[$j-1] . $sS_Align; //.s_aln:
	$sT_Align = $GLOBALS['cGapChar'] . $sT_Align; // .t_aln:
	$j--;
      }
      else {
	if( ($aD[$i][$j] - $iGapScore) == $aD[$i-1][$j]) {
	  $sS_Align = $GLOBALS['cGapChar'] . $sS_Align; //.s_aln:
	  $sT_Align = $aT[$i-1] . $sT_Align; // .t_aln:
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
      $sS_Align = $aS[$j-1] . $sS_Align;
      $sT_Align = $GLOBALS['cGapChar'] . $sT_Align;
      $j--;
    }
  }
  else {
    if($i > 0) {
      while( $i > 0) {
	$sS_Align = $GLOBALS['cGapChar'] . $sS_Align;
	$sT_Align = $aT[$i-1] . $sT_Align;
	$i--;
      }
    }
  }

  /// print "Returning '$sS_Align', '$sT_Align'.<br>\n"; 

  return array($sS_Align, $sT_Align);
}

// Functions //////////////////////////////////////////////////////////////////

// Initialize the global arrays
function initGlobals() {
  if( ! $GLOBALS['aSimilarities']) {
    $GLOBALS['aSimilarities'] = array('ad' => 1,
				      'ao' => 1,
				      'co' => 1,
				      'da' => 1,
				      'il' => 1,
				      'li' => 1,
				      'oa' => 1,
				      'oc' => 1
				      );
    $GLOBALS['aNonTokenChars'] = array('.' => 1,
				       ',' => 1,
				       '_' => 1,
				       '(' => 1,
				       '<' => 1,
				       ')' => 1,
				       '>' => 1,
				       "'" => 1,
				       '"' => 1,
				       ' ' => 1,
				       '|' => 1
				      );    
  }
}

function getSimScore($c1, $c2) {
  // NOTE that case differences are neglected.
  // Also NOTE that this fails when we have 'Ä' against 'ä' because
  // strtolower() won't do utf-8 and mb_strtolower() is not supported... ;-(
  if( ($c1 == $c2) || (strtolower($c1) == strtolower($c2)) )
    return 2;

  if( array_key_exists("$c1$c2", $GLOBALS['aSimilarities']) )
    return $GLOBALS['aSimilarities']["$c1$c2"];

  // Non-token mismatches are punished harder if the other character is a
  // normal one.
  if( (array_key_exists($c1, $GLOBALS['aNonTokenChars']) &&
       ! array_key_exists($c2, $GLOBALS['aNonTokenChars'])) ||
      (array_key_exists($c2, $GLOBALS['aNonTokenChars']) &&
       ! array_key_exists($c1, $GLOBALS['aNonTokenChars'])) )
    return -2;

  // Default case, for a 'normal' mismatch
  return -1;
}

// Is also used in stripTags.php
function strToUtf8Array($sString) {
  $aReturn = array();
  // NOTE the /u modifier, which ensures utf-8
  preg_match_all("/(.)/u", $sString, $aMatches, PREG_SET_ORDER);
  foreach ($aMatches as $val) {
    array_push($aReturn, $val[1]);
  }
  return $aReturn;
}

// Got this from http://php.net/manual/en/function.strtoupper.php
//
function strtoupper_utf8($sString){
  $sString = utf8_decode($sString);
  $sString = strtoupper($sString);
  $sString = utf8_encode($sString);
  return $sString;
}

?>