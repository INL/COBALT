<?php

// Strip the tags from a string.
// Keep track of (single/double) quoted parts.
//
// "ab<cd>ef" will result in "abef"
// "ab<cdef" will be "ab<cdef" (i.e. unbalanced tags/quotes, are neglected)
//
// The last tag opening bracket is taken to be the real opening bracket.
// So "ab<c<de>f" will result in "ab<cf"
//
// The stripped string is returned together with an array of arrays, which
// are pairs of onset/offsets of skipped parts in th original string.

// The function goes through the string character by character and implements
// a very simple state parser.
// On encountering a certain token some boolean is set indicating a certain
// state.

function stripTags($sStr) {
  $aStr = strToUtf8Array($sStr);

  $aSkipped = array();
  $iSkipOnset;
  $sStripped = '';
  $sTagText = '';
  $bInTag = $bInDoubleQuote = $bInSingleQuote = FALSE;
  ///  for($i = 0; $i < strlen($sStr); $i++ ) {
  for($i = 0; $i < count($aStr); $i++ ) {
    if( $aStr[$i] == '<') {
      if (! $bInTag) {
	$bInTag = TRUE;
	$iSkipOnset = $i;
      }
      else // If we thought we were already in a tag, then actually the tag
	// should start here...
	if( ! $bInSingleQuote && ! $bInDoubleQuote) {
	  $sStripped .= $sTagText;
	  $sTagText = '';
	  $iSkipOnset = $i;
	}
      $sTagText .= $aStr[$i];
    }
    else
      if( $aStr[$i] == '>') {
	if($bInTag) {
	  if(! ($bInSingleQuote || $bInDoubleQuote) ) {
	    $bInTag = FALSE;
	    $sTagText = '';
	    array_push($aSkipped, array($iSkipOnset, $i));
	  }
	  else // We are in a quoted part
	    $sTagText .= $aStr[$i];
	}
	else // It is just a '>' in the text
	  $sStripped .= $aStr[$i];
      }
      else 
	if($aStr[$i] == '"')
	  if($bInTag) {
	    if( ! $bInSingleQuote)
	      if( $bInDoubleQuote )
		$bInDoubleQuote = FALSE;
	      else
		$bInDoubleQuote = TRUE;
	    $sTagText .= $aStr[$i];
	  }
	  else // Not in tag
	    $sStripped .= $aStr[$i];
	else
	  if( $aStr[$i] == "'")
	    if($bInTag) {
	      if( ! $bInDoubleQuote)
		if( $bInSingleQuote )
		  $bInSingleQuote = FALSE;
		else
		  $bInSingleQuote = TRUE;
	      $sTagText .= $aStr[$i];
	    }
	    else // Not in tag
	      $sStripped .= $aStr[$i];
	  else // It is none of the options above
	    if( $bInTag )
	      $sTagText .= $aStr[$i];
	    else
	      $sStripped .= $aStr[$i];
  } // End of for loop

  // If a tag was opened but not closed, we consider it part of the string
  if( strlen($sTagText) )
    $sStripped .= $sTagText;

  return array($sStripped, $aSkipped);
}

?>