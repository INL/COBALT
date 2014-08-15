<?php

require_once('globals.php');

// Globals
$aFunctions = array('state0', 'state1', 'state2', 'state3', 'state4', 'state5',
		    'state6', 'state7', 'state8', 'state9', 'state10',
		    'state11', 'state12');
$iLastChar;
$aTmpPattern = array();

/******************************************************************************
*
* The next function is a mini one-purpose state parser.
* It goes through the string character by character.
* It returns an array that looks like this:
*
* Array (
*        [0] => lemma headword,
*        [1] => modern wordform,
*        [2] => [... list of patterns ...],
*        [3] => pos,
*        [4] => language_id,
*        [5] => gloss
*       )
*
* The list of patterns comes as [[lhs, rhs, index], [lhs, rhs, index], ...]
* So an array of arrays consisting of [left hand side, right hand side, index].
*
* All fields, except for 0 and 3 which should always be there, can be false
* (if no value was provided for them).
* The function addslashes() is applied for the relevant fields.
*
* Maybe it seems like a lot of code for a very simple task but the idea is that
* it runs very, very fast as it only goes through the string once and there is
* no useless copying or whatever.
*
******************************************************************************/
 
function lemmaTupleString2array($sLemmaTuple, $sMode, $bVerbose) {
  if( ! $GLOBALS['aLanguages'] )
    fillLanguages();

  $aLemma = array(false, false, false, false, false, false);
  $iState = 0;
  $iStart = -1;
  $iEnd = -1;
  $iStrLen = strlen($sLemmaTuple);
  $GLOBALS['iLastChar'] = $iStrLen - 1; 
  // We look up the right function for the current state in the functions array
  for($i = 0; $i < $iStrLen; $i++) {
    if( ! $GLOBALS['aFunctions'][$iState]($iState, $sLemmaTuple, $i, $iStart,
					  $iEnd, $aLemma, $sMode, $bVerbose)) {
      if( $sMode == 'partial') 
	return $aLemma;
      return false;
    }
  }

  return $aLemma;
}

/*******************************************************************************
*
* On the states
*
* The idea is (because I hoped that would be nice and more or less
* straightforward) that the first states (0..5) correspond to the positions in
* the return array and the higher states are intermediate/help states.
* Also, this makes it easier to add a state with the first states staying the
* same.
* So the parser doesn't necessarily go from state 0 to 5 in one go, but it
* jumps to and from these higher/intermediate states as it does.
* 
* State 0: headword
* State 1: modern wordform
* State 2: patterns
* State 3: pos
* State 4: language
* State 5: gloss
*
* State 6: in-between state after the headword is read
* State 7: in between state after modern wordfrom is read
* State 8: for after a modern wordform is read
* State 9: for after a patterns is read
* State 10: first pattern part
* State 11: second pattern part
* State 12: in-between state after a pattern is read
*
*******************************************************************************/

// NOTE in all the state<n> functions the & before the lemma tuple which
// hopefully prevents it from being copied all the time (as in C)
//
// The other &'s are there because the values can change in the function

// Headword
function state0(&$iState, &$sLemmaTuple, $i, &$iStart, &$iEnd, &$aLemma,
		$sMode, $bVerbose) {
  if( $bVerbose )
    print "State 0 ($sLemmaTuple[$i], $iStart, $iEnd)\n";

  if( $sLemmaTuple[$i] == ',' ) { // Go to next state
    if( $iStart == -1)
      return false;
    $aLemma[0] =
      addslashes(substr($sLemmaTuple, $iStart, ($iEnd - $iStart) + 1));
    $iStart = $iEnd = -1;
    $iState = 6;
  } // No leading spaces
  else if( $sLemmaTuple[$i] != ' ' ) {
    if( $iStart == -1)
      $iStart = $i;
    $iEnd = $i;
  }
 
  if( $i == $GLOBALS['iLastChar']) {
    if( ($sMode == 'partial') && ($iStart != -1) )
      fillLemma($sLemmaTuple, 0, $iStart, $iEnd, $aLemma);
    return false;
  }

  return true;
}

// Modern wordform
function state1(&$iState, &$sLemmaTuple, $i, &$iStart, &$iEnd, &$aLemma,
		$sMode, $bVerbose) {
  if( $bVerbose )
    print "State 1 ($sLemmaTuple[$i], $iStart, $iEnd)\n";

  if( $sLemmaTuple[$i] == '>' ) { // Stop condition for word form itself
    if( $iStart == -1 )
      return false;
    $aLemma[1] = 
      addslashes(substr($sLemmaTuple, $iStart, ($iEnd - $iStart) + 1));
    $iStart = $iEnd = -1;
    $iState = 8;
  }
  else if( $sLemmaTuple[$i] != ' ') {
    if( $iStart == -1)
      $iStart = $i;
    $iEnd = $i;
  }

  if( $i == $GLOBALS['iLastChar']) {
    if( ($iStart != -1) && ($sMode == 'partial') )
      fillLemma($sLemmaTuple, 1, $iStart, $iEnd, $aLemma);
    return false;
  }

  return true;
}

// Patterns
// Actually, this is an intermediate state that reads up to the first '(' and
// then goes to the next state where the actual pattern is read
function state2(&$iState, &$sLemmaTuple, $i, &$iStart, &$iEnd, &$aLemma,
		$sMode, $bVerbose) {
  if( $bVerbose )
    print "State 2 ($sLemmaTuple[$i], $iStart, $iEnd)\n";

  if( $i == $GLOBALS['iLastChar'])
    return false;
  else if( $sLemmaTuple[$i] == '(' ) { // Stop condition
    $iState = 10; // State for the actual patterns
  }
  else if( $sLemmaTuple[$i] != ' ') // Only spaces are allowed
    return false;
  return true;
}

// Pos
function state3(&$iState, &$sLemmaTuple, $i, &$iStart, &$iEnd, &$aLemma,
		$sMode, $bVerbose) {
  if( $bVerbose )
    print "State 3 ($sLemmaTuple[$i], $iStart, $iEnd)\n";

  // Stop condition
  if( ($sLemmaTuple[$i] == ',') || ($i == $GLOBALS['iLastChar']) ) {
    // If we reached the end
    if( $i == $GLOBALS['iLastChar']) {
      if( ($sLemmaTuple[$i] != ' ') && ($sLemmaTuple[$i] != ',') ) {
	if( $iStart == -1)
	  $iStart = $i;
	$iEnd = $i;
      }
    }
    /// else
    if( $iStart == -1)
      return false;
    $aLemma[3] = substr($sLemmaTuple, $iStart, ($iEnd - $iStart) + 1);
    $iStart = $iEnd = -1;
    $iState = 4; // After pos
  }
  else if( $sLemmaTuple[$i] != ' ') {
    if( $iStart == -1)
      $iStart = $i;
    $iEnd = $i;
  }
  return true;
}

// Language
// Actually, after reading pos, it is either either language or gloss
function state4(&$iState, &$sLemmaTuple, $i, &$iStart, &$iEnd, &$aLemma,
		$sMode, $bVerbose) {
  if( $bVerbose )
    print "State 4 ($sLemmaTuple[$i], $iStart, $iEnd)\n";

  // Stop condition
  if( ($sLemmaTuple[$i] == ',') || ($i == $GLOBALS['iLastChar']) ) {
    if($i == $GLOBALS['iLastChar'] ) { // If we reached the end
      if($sLemmaTuple[$i] != ' ') {
	if( $iStart == -1)
	  $iStart = $i;
	$iEnd = $i;
      }
    }
    /// stond nog een 'else' hier...
    if( $iStart == -1)
      return false;

    $sValue = substr($sLemmaTuple, $iStart, ($iEnd - $iStart) + 1);
    if( array_key_exists($sValue, $GLOBALS['aLanguages']) ) {
      // Assign the language id
      $aLemma[4] = $GLOBALS['aLanguages'][$sValue];
      $iStart = $iEnd = -1; // Start again
    }
    else if( $i == $GLOBALS['iLastChar'] ) {
      $aLemma[5] = addslashes($sValue);
      $iStart = $iEnd = -1; // Clean exit
    }

    // Regardless of whether we saw a language or not, we go to the next state,
    // but we keep the original start value if this wasn't a language
    $iState = 5; // Next is gloss
  }
  else if( $sLemmaTuple[$i] != ' ') {
    if( $iStart == -1)
      $iStart = $i;
    $iEnd = $i;
  }
  return true;
}

// Gloss
function state5(&$iState, &$sLemmaTuple, $i, &$iStart, &$iEnd, &$aLemma,
		$sMode, $bVerbose) {
  if( $bVerbose )
    print "State 5 ($sLemmaTuple[$i], $iStart, $iEnd)\n";

  if( $sLemmaTuple[$i] != ' ') {
    if( $iStart == -1)
      $iStart = $i;
    $iEnd = $i;
  }

  if( $i == $GLOBALS['iLastChar'] ) { // Stop condition
    if( $iStart == -1)
      return false;
    $aLemma[5] = 
      addslashes(substr($sLemmaTuple, $iStart, ($i - $iStart) + 1 ));
  }

  return true;
}

// In between state, after we have seen a headword
function state6(&$iState, &$sLemmaTuple, $i, &$iStart, &$iEnd, &$aLemma,
		$sMode, $bVerbose) {
  if( $bVerbose )
    print "State 6 ($sLemmaTuple[$i], $iStart, $iEnd)\n";

  // Neglect leading spaces
  if( ($sLemmaTuple[$i] == ' ') && ($i != $GLOBALS['iLastChar']))
    return true;
  else if( $sLemmaTuple[$i] == '<')
    $iState = 1; // Modern wordform
  else if( $sLemmaTuple[$i] == '[')
    $iState = 2; // Patterns
  else {
    $iOrd = ord($sLemmaTuple[$i]); // Convert to number
    if( (($iOrd >= 65) && ($iOrd <= 90)) || // Check if it is in [A-Za-z]
	(($iOrd >= 97) && ($iOrd <= 122)) ) {
      $iStart = $iEnd = $i;
      $iState = 3; // Pos
      // It could be that it is only a one character pos and then nothing
      if($i == $GLOBALS['iLastChar'])
	$aLemma[3] = substr($sLemmaTuple, $iStart, ($iEnd - $iStart) + 1);
    }
    else {
      if( $bVerbose )
	print "Error '$sLemmaTuple[$i] ($iOrd)'\n";
      return false; // No other possibilities
    }
  }
  return true;
}

// In between state, after we've seen a modern wordform
function state7(&$iState, &$sLemmaTuple, $i, &$iStart, &$iEnd, &$aLemma,
		$sMode, $bVerbose) {
  if( $bVerbose )
    print "State 7 ($sLemmaTuple[$i], $iStart, $iEnd)\n";

  /* weg 
  if( $i == $GLOBALS['iLastChar'])
    return false;
  }
  else */

  if( $sLemmaTuple[$i] == ' ') // Neglect leading spaces
    return ( $i == $GLOBALS['iLastChar'] ) ? false : true;
  else if( $sLemmaTuple[$i] == '[') {
    $iState = 2; // Patterns
    if( $i == $GLOBALS['iLastChar'] )
      return false;
  }
  else {
    $iOrd = ord($sLemmaTuple[$i]); // Convert to number
    if( (($iOrd >= 65) && ($iOrd <= 90) ) || // Check if it is in [A-Za-z]
	(($iOrd >= 97) && ($iOrd <= 122) ) ) {
      $iStart = $iEnd = $i;
      if( $i == $GLOBALS['iLastChar'] )
	$aLemma[3] = substr($sLemmaTuple, $iStart, ($iEnd - $iStart) + 1);
      $iState = 3; // Pos
    }
    else
      return false; // No other possibilities
  }
  return true;
}

// In between state, after the modern wordform is read
function state8(&$iState, &$sLemmaTuple, $i, &$iStart, &$iEnd, &$aLemma,
		$sMode, $bVerbose) {
  if( $bVerbose )
    print "State 8 ($sLemmaTuple[$i], $iStart, $iEnd)\n";

  if( $i == $GLOBALS['iLastChar'])
    return false;
  else if( $sLemmaTuple[$i] == ',') // Stop condition
    $iState = 7; // In between state to decide what's next
  else
    if( $sLemmaTuple[$i] != ' ') // Only spaces are allowed
      return false;
  return true;
}

// In between state, after all patterns have been read
function state9(&$iState, &$sLemmaTuple, $i, &$iStart, &$iEnd, &$aLemma,
		$sMode, $bVerbose) {
  if( $bVerbose )
    print "State 9 ($sLemmaTuple[$i], $iStart, $iEnd)\n";

  if( $i == $GLOBALS['iLastChar'])
    return false;
  else if( $sLemmaTuple[$i] == ',') // Stop condition
    $iState = 3; // Pos
  else
    if( $sLemmaTuple[$i] != ' ') // Only spaces are allowed
      return false;
  return true;
}

// First pattern part
function state10(&$iState, &$sLemmaTuple, $i, &$iStart, &$iEnd, &$aLemma,
		 $sMode, $bVerbose) {
  if( $bVerbose )
    print "State 10 ($sLemmaTuple[$i], $iStart, $iEnd)\n";

  if( $i == $GLOBALS['iLastChar']) {
    if( $sMode == 'partial') {
      if( ($sLemmaTuple[$i] != '_') && ($sLemmaTuple[$i] != ',') &&
	  ($sLemmaTuple[$i] != ' ')) {
	if( $iStart == -1)
	  $iStart = $i;
	$iEnd = $i;
      }
      if($iStart != -1) {
	array_push($GLOBALS['aTmpPattern'],
		   substr($sLemmaTuple, $iStart, ($iEnd - $iStart) + 1) );
      }
      if( ! $aLemma[2]) // The first pattern we see
	$aLemma[2] = array();
      array_push($aLemma[2], $GLOBALS['aTmpPattern']);
    }
    return false;
  }
  else if( $sLemmaTuple[$i] == ',') { // Stop condition
    if( $iStart == -1)
      return false;
    array_push($GLOBALS['aTmpPattern'],
	       substr($sLemmaTuple, $iStart, ($iEnd - $iStart) + 1) );
    $iStart = $iEnd = -1;
    $iState = 11; // Next pattern part
  }
  else if( $sLemmaTuple[$i] == '_') { // Left hand side
    if( $iStart == -1 || (count($GLOBALS['aTmpPattern']) > 0) )
      return false;
    else {
      // NOTE the different values in the substr() because we don't want the _
      array_push($GLOBALS['aTmpPattern'],
		 substr($sLemmaTuple, $iStart, ($iEnd - $iStart) + 1) );
      $iStart = $iEnd = -1;
    }
  }
  else if( $sLemmaTuple[$i] != ' ') {
    if( $iStart == -1)
      $iStart = $i;
    $iEnd = $i;
  }
  return true;
}

// Second pattern part
function state11(&$iState, &$sLemmaTuple, $i, &$iStart, &$iEnd, &$aLemma,
		 $sMode, $bVerbose) {
  if( $bVerbose )
    print "State 11 ($sLemmaTuple[$i], $iStart, $iEnd)\n";

  if( $i == $GLOBALS['iLastChar']) {
    if( $sMode == 'partial') {
      $iOrd = ord($sLemmaTuple[$i]);
      if( ($iOrd >= 48) && ($iOrd <= 57) ) {
	if( $iStart == -1)
	  $iStart = $i;
	$iEnd = $i;
      }
      if($iStart != -1) {
	array_push($GLOBALS['aTmpPattern'],
		   substr($sLemmaTuple, $iStart, ($iEnd - $iStart) + 1) );
      }
      if( ! $aLemma[2]) // The first pattern we see
	$aLemma[2] = array();
      array_push($aLemma[2], $GLOBALS['aTmpPattern']);
    }
    return false;
  }
  else if( $sLemmaTuple[$i] == ')') { // Stop condition
    if( $iStart == -1)
      return false;
    array_push($GLOBALS['aTmpPattern'],
	       substr($sLemmaTuple, $iStart, ($iEnd - $iStart) + 1));
    if( ! $aLemma[2]) // The first pattern we see
      $aLemma[2] = array();
    array_push($aLemma[2], $GLOBALS['aTmpPattern']);
    $iStart = $iEnd = -1;
    $GLOBALS['aTmpPattern'] = array();
    $iState = 12; // In-between pattern state for deciding what's next
  }
  else if( $sLemmaTuple[$i] != ' ') {
    // See if it is a digit
    $iOrd = ord($sLemmaTuple[$i]);
    if( ($iOrd >= 48) && ($iOrd <= 57) ) {
      if( $iStart == -1)
	$iStart = $i;
      $iEnd = $i;
    }
    else // Not a digit
      return false;
  }
  return true;
}

// In between state after a full patterns is read.
// Now another pattern can come, or it was the last one
function state12(&$iState, &$sLemmaTuple, $i, &$iStart, &$iEnd, &$aLemma,
		 $sMode, $bVerbose) {
  if( $bVerbose )
    print "State 12 ($sLemmaTuple[$i], $iStart, $iEnd)\n";

  if( $i == $GLOBALS['iLastChar'])
    return false;
  else if( $sLemmaTuple[$i] != ' ') // Stop condition, spaces are neglected
    if( $sLemmaTuple[$i] == ',' )
      $iState = 2; // Another pattern
    else if( $sLemmaTuple[$i] == ']') // We've seen all patterns
      $iState = 9;
    else
      return false; // Nothing else allowed
  else
    if( $sLemmaTuple[$i] != ' ') // Only spaces are allowed
      return false;
  return true;
}

/// Help function /////////////////////////////////////////////////////////////

function fillLemma(&$sLemmaTuple, $iLemmaPart, &$iStart, &$iEnd, &$aLemma) {
  $aLemma[$iLemmaPart] =
    addslashes(substr($sLemmaTuple, $iStart, ($iEnd - $iStart) + 1));
  $iStart = $iEnd = -1;
}

?>