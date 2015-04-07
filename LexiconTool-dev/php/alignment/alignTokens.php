<?php

require_once("stringAlign.php");
require_once("stripTags.php");

if( isset($GLOBALS['bNormalizeToken']) && $GLOBALS['bNormalizeToken'])
  require_once("./normalizeToken.php");

function alignTokens($iDocId, $sLine, $sNewToken, $fhFile,
		     $iCurrentOffsetChange, &$aNewOnsetOffsets) {
  $aLine = explode("\t", $sLine);
  $sNormalizedToken = $aLine[0];
  $sUnnormalizedToken = $aLine[1];
  $iOrigOnset = $aLine[2] + $iCurrentOffsetChange;
  $iOrigOffset = $aLine[3];

  // Strip the tags
  list($sStrippedUnnormalizedToken, $aSkipped)= stripTags($sUnnormalizedToken);

  printStripOutcome($fhFile, $sUnnormalizedToken,
		    $sStrippedUnnormalizedToken, $aSkipped);

  // Align the original stripped unnormalized with the original normalized
  // token to find out what leading/trailing non-word characters there are in
  // the unnormalized token.
  list($sOrigNorm_Align, $sOrigUnnorm_Align) =
    stringAlign($sNormalizedToken, $sStrippedUnnormalizedToken);

  printAlignmentOutcome($fhFile, $sNormalizedToken, $sUnnormalizedToken,
			$sOrigNorm_Align, $sOrigUnnorm_Align);

  // Align the stripped one with the new token
  list($sS_Align, $sT_Align) = stringAlign($sNormalizedToken, $sNewToken);

  printAlignmentOutcome($fhFile, $sNormalizedToken, $sNewToken, $sS_Align,
			$sT_Align);


  // Align the stripped unnormalized one with the new token
  list($sS_Align_unnormalized, $sT_Align_unnormalized) =
    stringAlign($sStrippedUnnormalizedToken, $sNewToken);

  printAlignmentOutcome($fhFile, $sStrippedUnnormalizedToken, $sNewToken,
			$sS_Align_unnormalized, $sT_Align_unnormalized);

  

  // Make a new unnormalized token
  if( ! $fhFile )
    print "<pre>\nOriginal:\n" . str_replace('<', '&lt;', $sLine) . "\nNew:\n";
  $iOffsetChange =
    lineUp($iDocId, $sS_Align, $sT_Align, $sOrigNorm_Align, $sOrigUnnorm_Align,
	   $sUnnormalizedToken, $sS_Align_unnormalized, $aSkipped,
	   $iOrigOnset, $iOrigOffset, $fhFile, $aNewOnsetOffsets, $aLine);
  if( ! $fhFile )
    print "</pre>End";

  return $iOffsetChange;
}

// Functions //////////////////////////////////////////////////////////////////

// Separate function that uses the output of the string alignment above.
//
// The purpose of the function is to make a new unnormalized token, based on
// the new string the user provided, and the unnormalized token in the 
// original text.
// The point is that the unnormalized token can contain tags and punctuation.
// The user however can't see those tags, and won't type in punctuation. (S)he
// will just type in a clean, normalized token.
//
// This function takes the new string ($sT_Align) aligned with the old one
// ($sS_Align) and inserts any skipped parts of the original (tags and
// punctuation).
//
// If the string has become larger/smaller this is returned in the
// offset change (which will be positive/negative respectively).
//
function lineUp($iDocId, $sS_Align, $sT_Align, $sOrigNorm_Align,
		$sOrigUnnorm_Align, $sUnnormalizedToken, $sS_Align_unnormalized,
		$aSkipped, $iOrigOnset, $iOrigOffset, $fhFile,
		&$aNewOnsetOffsets,$aLine){
  if( ! $fhFile )
    print "lineUp($iDocId, '$sS_Align', '$sT_Align', '$sOrigNorm_Align', " .
      "'$sOrigUnnorm_Align', '$sUnnormalizedToken', \$aSkipped," .
      "$iOrigOnset, $iOrigOffset, \$fhFile, &\$aNewOnsetOffsets,\$aLine')" .
      "<br>\n";

  // Chop off newline if 5th column was the last
  // This makes printing easier below.
  // NOTE that the 5th column is required to be present!
  $aLine[4] = preg_replace("/[\r\n]+$/", '', $aLine[4]);

  $sNewUnnormalized = '';
  // Separate onset count for the skipped part, which is about the unnormalized
  // token
  $iSkippedOnset = 0;
  // Leading omitted characters
  for($i=0; $i < strlen($sOrigNorm_Align); $i++) {
    if( $sOrigNorm_Align{$i} == "\t" ) {
      $sNewUnnormalized .= $sOrigUnnorm_Align{$i};
      $iSkippedOnset++;
    }
    else
      break;
  }

  // We do this so we can work with the indices of characters in strings.
  // Curly braces ($sS{2} e.g.) don't work when the character in question is
  // an utf-8 character.
  $aS_Align = strToUtf8Array($sS_Align);
  $aT_Align = strToUtf8Array($sT_Align);
  $aUnnormalizedToken = strToUtf8Array($sUnnormalizedToken);
  $aS_Align_unnormalized = strToUtf8Array($sS_Align_unnormalized);

  $iNewOnset = $iOrigOnset;
  $iOffsetChange = 0;
  $iSkippedIndex = 0; // Index of the aSkipped array
  $iLengthAligned = count($aS_Align); 
  $sNewNormalized = '';
  $iSkippedLength = 0;
  for($i=0; $i < $iLengthAligned; $i++) {
    // Check of there was something skipped at this position
    if( ($iSkippedIndex < count($aSkipped)) &&
	($iSkippedOnset == $aSkipped[$iSkippedIndex][0]) ) {
      $iLength =
	($aSkipped[$iSkippedIndex][1] -$aSkipped[$iSkippedIndex][0]) + 1;
      $sNewUnnormalized .= // Insert the tag in the unnormalized token

	implode(array_slice($aUnnormalizedToken, $aSkipped[$iSkippedIndex][0],
			    $iLength));
      $iSkippedOnset += $iLength;
      if( $i != 0)  // Only tags *in* words are counted for the length
	$iSkippedLength += $iLength;
      $iSkippedIndex++; // Next one in the aSkipped array
    }

    if( ($aT_Align[$i] == ' ') || ($aT_Align[$i] == '|') ) { // New token
      // No strlen because of utf8

      $iNewOffset = $iNewOnset + count(strToUtf8Array($sNewNormalized))
	+ $iSkippedLength;

      if( array_key_exists("$iDocId $iOrigOnset", $aNewOnsetOffsets) )
	array_push($aNewOnsetOffsets["$iDocId $iOrigOnset"],
		   array($iNewOnset, $iNewOffset));
      else
	$aNewOnsetOffsets["$iDocId $iOrigOnset"] =
	  array(array($iNewOnset, $iNewOffset));
      
      if( ! $fhFile )
      	$sNewUnnormalized = str_replace("<", "&lt;", $sNewUnnormalized);

      if( isset($GLOBALS['bNormalizeToken']) && $GLOBALS['bNormalizeToken'])
	$sNewNormalized = normalizeToken($sNewNormalized);

      // Compliant with 'isNotAWordformInDb'
      // Actually, it just goes wrong if it's not there...
      $sNewLine = "$sNewNormalized\t$sNewUnnormalized\t$iNewOnset\t$iNewOffset"
	. "\t" . $aLine[4];

      // Compliant with position info
      if( isset($aLine[5]) )
	$sNewLine .= "\t" . $aLine[5] . "\t" . $aLine[6] .
	  "\t" . $aLine[7] . "\t" . $aLine[8]; // NOTE: aLine[8] has a new line
      else
	$sNewLine .= "\n";


      if( $fhFile )
	fwrite($fhFile, $sNewLine);
      else
	print $sNewLine;

      $sNewUnnormalized = ''; // Initialize everything again
      $sNewNormalized = '';
      $iNewOnset += ($iNewOffset - $iNewOnset);
      $iSkippedLength = 0;
      if($aT_Align[$i] == ' ')
	$iNewOnset++;
      // Later toegevoegd
      if( ($aT_Align[$i] == ' ') && ($aS_Align[$i] == "\t") )
	$iOffsetChange++;
      elseif( ($aT_Align[$i] == '|') && ($aS_Align[$i] != "\t") )
	// Precorrection, because the next step will add one too many...
	$iOffsetChange--;
      //
      $iSkippedOnset++;
    }
    else {
      if( $aS_Align[$i] == "\t") {
	$iOffsetChange++;

	$sNewUnnormalized .= $aT_Align[$i];
	$sNewNormalized .= $aT_Align[$i];
	// >> NOTE that we DON'T do $iSkippedOnset++ here <<
      }
      else {
	if( $aT_Align[$i] == "\t") {
	  $iOffsetChange--;
	}
	else {
	
	  $sNewUnnormalized .= $aT_Align[$i];
	  $sNewNormalized .= $aT_Align[$i];
	}
	$iSkippedOnset++;
	
      }
    }
  }

  // Check something was skipped at the end
  if( ($iSkippedIndex < count($aSkipped)) &&
      ($iSkippedOnset == $aSkipped[$iSkippedIndex][0]) ) {
    $iLength =
      ($aSkipped[$iSkippedIndex][1] -$aSkipped[$iSkippedIndex][0]) + 1;
    $sNewUnnormalized .= // Insert the tag in the unnormalized token
      
      implode(array_slice($aUnnormalizedToken, $aSkipped[$iSkippedIndex][0],
			  $iLength));
    // >> NOTE that the we DON'T update $iSkippedLength here, because tags
    // at the end don't count for the offset
  }

  // Any trailing punctuation.
  // NOTE: The punctuation only occurs after any trailing tags.
  // If the punctuation would have been before that tag, the tag shouldn't have
  // ended up here at all!
  $sTrailing = '';
  for($i=strlen($sOrigNorm_Align)-1; $i > 0; $i--) {
    if( $sOrigNorm_Align{$i} == "\t" ) {
      $sTrailing = $sOrigUnnorm_Align{$i} . $sTrailing;
    }
    else
      break;
  }
  $sNewUnnormalized .= $sTrailing;

  if( strlen($sNewUnnormalized) ) {
    // NOTE we don't use strlen here, because of utf8 difficulties.
    // So we convert to an array every time (it might change on the way).
    $iNewOffset = $iNewOnset + count(strToUtf8Array($sNewNormalized))
      + $iSkippedLength;

    if( array_key_exists("$iDocId $iOrigOnset", $aNewOnsetOffsets) )
      array_push($aNewOnsetOffsets["$iDocId $iOrigOnset"],
		 array($iNewOnset, $iNewOffset));
    else
      $aNewOnsetOffsets["$iDocId $iOrigOnset"] =
	array(array($iNewOnset, $iNewOffset));

    if( ! $fhFile )
      $sNewUnnormalized = str_replace("<", "&lt;", $sNewUnnormalized);

    if( isset($GLOBALS['bNormalizeToken']) && $GLOBALS['bNormalizeToken'])
      $sNewNormalized = normalizeToken($sNewNormalized);

    $sNewLine = "$sNewNormalized\t$sNewUnnormalized\t$iNewOnset\t$iNewOffset".
      "\t" . $aLine[4];

    // NOTE that we actually copy the position info for any word parts
    // We can not know where to divide pixel-wise, so it all just stays the
    // same block
    if( isset($aLine[5]) )
      $sNewLine .= "\t" . $aLine[5] . "\t" . $aLine[6] . "\t" . $aLine[7] .
	"\t" . $aLine[8];  // aLine[8] has a new line
    else
      $sNewLine .= "\n";

 
    if( $fhFile )
      fwrite($fhFile, $sNewLine );
    else
      print $sNewLine;
  }

  return $iOffsetChange;
}

// Help functions for printing
function printStripOutcome($fhFile, $sUnnormalizedToken,
			   $sStrippedUnnormalizedToken, $aSkipped) {
  if( ! $fhFile ) {
    print "'" . str_replace('<', '&lt;', $sUnnormalizedToken) .
      "' stripped: '" . str_replace('<', '&lt;', $sStrippedUnnormalizedToken) .
      "'<br>";
    for($i = 0; $i < count($aSkipped); $i++) {
      print "Skipped indices " . $aSkipped[$i][0] . " to " . $aSkipped[$i][1] .
	"<br>\n";
    }
  }
}

function printAlignmentOutcome($fhFile, $sS, $sT, $sS_Align, $sT_Align) {
  if( ! $fhFile) {
    print "Aligning $sS to $sT:<br>";
    print "<table border=0 cellpadding=4>\n";
    printRow($sS_Align);
    printRow($sT_Align);
    print("</table>");
  }
}

function printRow($s) {
  print "<tr>";

  preg_match_all("/(.)/u", $s, $aMatches, PREG_SET_ORDER);
  foreach ($aMatches as $val) {
    $sBackground = "#E6E6E6";
    if($val[1] == "\t")
      $sBackground = "#FFE6E6";
    if($val[1] == ' ') 
      $sBackground = "#E6FFE6";
    print "<td style=\"background: $sBackground; padding: 2px 8px 2px 8px;\">"
      . $val[1] . "</td>";
  }
  print "</tr>\n";
}

?>