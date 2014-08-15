function arrangeKeydown() {
  if( sClickedInWindowPart == 'sentences')
    document.onkeydown = keyDown_default;
  else // 'topBar' or 'wordsToAttest'
    document.onkeydown = keyDown_wordToAttest;
  ///  bClickedInWordtToAttest = 0;
}

// This functions is used to unbind all the bindings of keyDown(). This is
// handy when somebody starts typing in an input box (otherwise if
// someone e.g. hits an arrow, suddenly the next word form will be shown...).
function keyDown_default (e) {
  var keyCode;
  // If we return false the key which was pressed is disabled for further use
  var bReturn = true;

  if( window.event ) // Explorer
    keyCode = window.event.keyCode;
  else if (e) // Else
    keyCode = e.which;

  // If Shift-Ctrl-Alt was pressed, we neglect other keys
  if( (iHeldDownKey != 16 ) && (iHeldDownKey != iCtrlKey)
      // && (iHeldDownKey != 18)
      ) {
    iHeldDownKey = keyCode;
    notifyHeldDownKey();
    iLastKeyPressed = keyCode;
  }

  // For the dropdown menu in the sentences part of the screen
  switch(keyCode) {
  case 119: // F8 (note that we do this in both keyDown functions)
    toggleTokenAttestation(false, 0, 0, 0);
    break;
  case 77: // m
    if(iHeldDownKey == 17) // Ctrl-m
      document.onkeydown = keyDown_wordToAttest;
    break;
  case 40: // Down arrow
    if( document.getElementById('tokenAttSuggestions').style.visibility ==
	'visible') {
      bReturn = false;
      // If the other drowdown is visible as well (i.e. something was typed in
      // in the 'new' box) we are actually navigating in that one 
      if( document.getElementById('textAttSuggestions').style.visibility ==
	  'visible')
	downArrowTextAttSuggMenu();
      else
	downArrowTokenAttSuggMenu();
    }
    break;
  case 38: // Up arrow
    if( document.getElementById('tokenAttSuggestions').style.visibility ==
	'visible') {
      bReturn = false;
      // If the other drowdown is visible as well (i.e. something was typed in
      // in the 'new' box) we are actually navigating in that one 
      if( document.getElementById('textAttSuggestions').style.visibility ==
	  'visible')
	upArrowTextAttSuggMenu();
      else
	upArrowTokenAttSuggMenu();
    }
    break;
  case 13: // Enter
    if( iSelectedLemmaSuggestionRow != -1) {
      var oLemmaSuggestion =
	document.getElementById('lemmaSuggestionRow_' +
				iSelectedLemmaSuggestionRow);
      // Find out if we are in the tokenAtt suggestion menu or the lemma
      // suggestions menu
      if( document.getElementById('textAttSuggestions').style.visibility == 
	  'visible') { // Lemma suggestions
    	// In that case, the 'new' box must have been made editable
	if( oLemmaSuggestion ) { // Not the case if the 'new' box was selected
	  document.getElementById('newTokenAttInput').value =
	    oLemmaSuggestion.title;

	}
      }
      else { // We were in the token attestation suggestion menu
	// See if we were in the 'new' row
	if( (iMaxLemmaSuggestionRow == (iSelectedLemmaSuggestionRow - 1)) ||
	    // Next is the case when only the 'New' box is there
	    ((iMaxLemmaSuggestionRow == -1) &&
	     (iSelectedLemmaSuggestionRow == 1)) ) {
	  // If somebody hits enter in the 'new' box, we make it editable
	  var oNewTokenAtt = document.getElementById('newTokenAtt');
	  if( oNewTokenAtt ) {
	    // Title = 'maxRowNr|docId|sentenceNr|wordFormId|startPos|endPos'
	    var aTitle = oNewTokenAtt.title.split('|');
	    makeTokenAttSuggestionEditable(oNewTokenAtt, aTitle[1], aTitle[2],
					   aTitle[3], aTitle[4], aTitle[5]);
	  }
	}
	else { // A normal row
	  // Title = 'docId|sentenceNr|wordFormId|analyzedWfId|startPos|endPos'
	  var aTitle = oLemmaSuggestion.title.split('|');
	  oLemmaSuggestion.className =
	    tokenAttest(aTitle[0], aTitle[1], aTitle[2], aTitle[3], aTitle[4],
			aTitle[5], oLemmaSuggestion.className);
	}
      }
    }
    else { // No row selected
      // Check if we are not coming here because a user pressed enter while
      // typing in a new token attestation in the lower part.
      if( (sClickedInWindowPart == 'wordsToAttest') &&
	  (! document.getElementById('newTokenAttInput')) )
	updatePosInput('');
    }

    break;
  }

  return bReturn;
}

function downArrowTokenAttSuggMenu() {
  var iNewSelectedRow = ( iSelectedLemmaSuggestionRow == -1)
    ? 1 : (iSelectedLemmaSuggestionRow + 1);
  
  var oLemmaSuggestion = document.getElementById('lemmaSuggestionRow_' +
						 iNewSelectedRow);
      
  if( oLemmaSuggestion ) // If we are not at the end
    highlightLemmaRow('', iNewSelectedRow);
  else { // We reached the end (or we have already reached it)
    if( iMaxLemmaSuggestionRow != (iSelectedLemmaSuggestionRow - 1)) {
      // Un-highlight the previous
      if( iSelectedLemmaSuggestionRow != -1) {
	var oCurrentlyHighlighted =
	  document.getElementById('lemmaSuggestionRow_' +
				  iSelectedLemmaSuggestionRow);
	if( oCurrentlyHighlighted )
	  oCurrentlyHighlighted.className =
	    oCurrentlyHighlighted.className.substr(0, (oCurrentlyHighlighted.className.length - 1) );
      }
      // Higlight the 'new' box
      var oNewBox = document.getElementById('newTokenAtt');
      oNewBox.className = 'lemmaSuggestion_';
      iMaxLemmaSuggestionRow = iSelectedLemmaSuggestionRow;
      // So this value is now set to a row that actually doesn't exist
      iSelectedLemmaSuggestionRow = iNewSelectedRow;
    }
  }
}

function upArrowTokenAttSuggMenu() {
  // Check if we are at the bottom (i.e. the 'new' box was highlighted)
  if( (iMaxLemmaSuggestionRow != -1) &&
      (document.getElementById('newTokenAtt').className ==
       'lemmaSuggestion_') ) {
    // Unhighlight the 'new' box
    document.getElementById('newTokenAtt').className = '';
    // Highlight the last row
    var oLastRow = document.getElementById('lemmaSuggestionRow_' +
					   iMaxLemmaSuggestionRow);
    if( oLastRow) // If there is a last row...
      oLastRow.className += '_';
    iSelectedLemmaSuggestionRow = iMaxLemmaSuggestionRow;
  }
  else { // Normal case
    var iNewSelectedRow = ( (iSelectedLemmaSuggestionRow == -1) ||
			    (iSelectedLemmaSuggestionRow == 1) )
      ? 1 : (iSelectedLemmaSuggestionRow - 1);
    
    var oLemmaSuggestion = document.getElementById('lemmaSuggestionRow_' +
						   iNewSelectedRow);
    
    if( oLemmaSuggestion ) // If we are not at the top
      highlightLemmaRow('', iNewSelectedRow);
  }
}

function keyDown_wordToAttest (e) {
  var keyCode;
  // If we return false the key which was pressed is disabled for further use
  var bReturn = true;

  if( window.event ) // Explorer
    keyCode = window.event.keyCode;
  else if (e) // Else
    keyCode = e.which;

  // Set the global variable
  // This is used for all kind of click events
  if( (iHeldDownKey != 16 ) && (iHeldDownKey != iCtrlKey)
      //&& (iHeldDownKey != 18)
      ) {
    iHeldDownKey = keyCode;
    notifyHeldDownKey();
  }
  iLastKeyPressed = keyCode;
  
  // For the arrows we handle thing right away
  switch(keyCode) {
  case 119: // F8 (note that we do this in both keyDown functions)
    toggleTokenAttestation(false, 0, 0, 0);
    break;
  case 77: // m
    if(iHeldDownKey == 17) // Ctrl-m
      document.onkeydown = keyDown_wordToAttest;
    break;
  case 40: // Down arrow
    if( document.getElementById('textAttSuggestions').style.visibility ==
	'visible') {
      bReturn = false;
      downArrowTextAttSuggMenu();
    }
    else {
      // Also, since we don't have an input box anymore to put the focus on,
      // scroll up a bit to keep the row in view
      var iOffsetHeight =
	document.getElementById('wordRow_' + iSelectedRow).offsetHeight - 2;
      document.getElementById('wordsToAttest').scrollTop += 
	(iOffsetHeight / 2) - 2;
      selectTextAttestationRow(iSelectedRow + 1);
    }
    break;
  case 38: // Up arrow
    bReturn = false;
    if( document.getElementById('textAttSuggestions').style.visibility ==
	'visible') {
      upArrowTextAttSuggMenu();
    }
    else {
      // Also, since we don't have an input box anymore to put the focus on,
      // scroll down a bit to keep the row in view
      document.getElementById('wordsToAttest').scrollTop -=
      	document.getElementById('wordRow_' + iSelectedRow).offsetHeight;

      selectTextAttestationRow(iSelectedRow - 1);
    }
    break;
  case 13: // Enter
    if( sClickedInWindowPart == 'wordsToAttest')
      updatePosInput('');
    if( sClickedInWindowPart == 'topBar')
      updateLemmaFilter('');
  }

  return bReturn;
}

function downArrowTextAttSuggMenu() {
  var iNewSelectedRow = ( iSelectedLemmaSuggestionRow == -1)
    ? 1 : (iSelectedLemmaSuggestionRow + 1);
  
  var oLemmaSuggestion = document.getElementById('lemmaSuggestionRow_' +
						 iNewSelectedRow);

  if( oLemmaSuggestion ) // Als we niet aan het eind zijn
    highlightLemmaRow('',iNewSelectedRow);
}

function upArrowTextAttSuggMenu() {
  var iNewSelectedRow = ( (iSelectedLemmaSuggestionRow == -1) ||
			  (iSelectedLemmaSuggestionRow == 1) )
    ? 1 : (iSelectedLemmaSuggestionRow - 1);

  var oLemmaSuggestion = document.getElementById('lemmaSuggestionRow_' +
						 iNewSelectedRow);

  if( oLemmaSuggestion )
    highlightLemmaRow('', iNewSelectedRow);
}

// Unsets the global variable
function keyUpHandler(e) {
  var keyCode;

  if( window.event ) // Explorer
    keyCode = window.event.keyCode;
  else if (e) // Else
    keyCode = e.which;

  // If Shift-Ctrl-Alt was pressed, we neglect other keys
  if( iHeldDownKey == keyCode) {
    iHeldDownKey = 0;
    notifyHeldDownKey();
  }
}

function notifyHeldDownKey() {
  var sText = false;

  if( iHeldDownKey == iCtrlKey) {
    sText = '!!!';
    if( iHeldDownKey == 17) // These two (Ctrl/Esc) get special treatement.
      sText = 'Ctrl';
    if( iHeldDownKey == 27)
      sText = 'Esc';
  }
  
  var oNotifier = document.getElementById('heldDownKeyNotifier');
  if( oNotifier) {
    if( sText ) {
      oNotifier.style.visibility = 'visible';
      oNotifier.innerHTML = sText;
    }
    else
      oNotifier.style.visibility = 'hidden';
  }
}

function highlightLemmaRow(sMode, iRowNr) {
  if( sMode == 'tok' ) {
    // This means the mouse goes over a row of the token attestation suggestion
    // menu. Now, if the lemma list is still open, we close this (otherwise
    // we don't really know what the arrows should do).
    var oTextAttSuggestions = document.getElementById('textAttSuggestions');
    if( oTextAttSuggestions.style.visibility == 'visible' ) {
      oTextAttSuggestions.innerHTML = '';
      oTextAttSuggestions.style.visibility = 'hidden';
      iSelectedLemmaSuggestionRow = -1;
      iMaxLemmaSuggestionRow = -1;
    }
  }

  if( iRowNr == 'new') { // We have to highlight the 'new' box
    // Unhighlight the current one
    var oCurrentlyHighlighted = 
      document.getElementById('lemmaSuggestionRow_' +
			      iSelectedLemmaSuggestionRow);
    if( oCurrentlyHighlighted ) // There isn't necessarily one
      oCurrentlyHighlighted.className =
	oCurrentlyHighlighted.className.substr(0,(oCurrentlyHighlighted.className.length-1));

 
    var oNewBox = document.getElementById('newTokenAtt');
    oNewBox.className = 'lemmaSuggestion_';
    // Title = 'maxSentenceNr|docId|sentenceNr|wordFormId|startPos|endPos'
    var aTitle = oNewBox.title.split('|');
    iMaxLemmaSuggestionRow = parseInt(aTitle[0]);
    iSelectedLemmaSuggestionRow = (iMaxLemmaSuggestionRow + 1);
  }
  else { // We are highlighting a normal row
    // Unhighlight the currently highlighted one

    // See if it was the 'new' box
    if( (iMaxLemmaSuggestionRow != -1) &&
	(iMaxLemmaSuggestionRow == (iSelectedLemmaSuggestionRow - 1)) ) {
      // Unhighlight the 'new' box
      var oNewBox = document.getElementById('newTokenAtt');
      oNewBox.className = '';
    }
    else { // Normal case
      if(iSelectedLemmaSuggestionRow != -1 ) {
	// Unhighlight the previous one if there is one
	var oCurrentlyHighlighted =
	  document.getElementById('lemmaSuggestionRow_' +
				  iSelectedLemmaSuggestionRow);
	if( oCurrentlyHighlighted )
	  oCurrentlyHighlighted.className =
	    oCurrentlyHighlighted.className.substr(0, (oCurrentlyHighlighted.className.length - 1) );
      }
    }

    // Highlight the new one, unless it already was highlighted
    var sClassName =
      document.getElementById('lemmaSuggestionRow_' + iRowNr).className;
    if( sClassName.substr(-1) != '_')
      document.getElementById('lemmaSuggestionRow_' + iRowNr).className += '_';
    // Set the global variable
    iSelectedLemmaSuggestionRow = iRowNr;
  }
}
