// This function is called when somebody clicks on the column that displays
// what lemmata a word is currently associated with
function makePosColEditable(iWordFormId, sPosCol, iRowNr) {
  sOpenMenu = 'posInput';

  var oPosCol = document.getElementById(sPosCol);

  var sInnerHTML = oPosCol.innerHTML;
  // Here we set the global variable
  // We replace the non-breakable spaces because they can be converted by the
  // browser to \xA0 characters (see below)
  if( sInnerHTML && sInnerHTML.length) {
    sInnerHTML = sInnerHTML.replace('&nbsp;', ' ');
    sInnerHTML = htmlDecode(sInnerHTML);
    sPosInputBeforeEditing = sInnerHTML.replace(/\xA0/g, ' ');
    sPosInputBeforeEditing = sPosInputBeforeEditing.replace(/</g, '&lt;');
  }
  else
    sPosInputBeforeEditing = '';

  var iInputBoxSize = Math.min(sInnerHTML.length, 70);
  if( iInputBoxSize == 0)
    iInputBoxSize = 30;
  oPosCol.innerHTML =
    "<input type=text id=posInput size=" + iInputBoxSize +
    // NOTE that the title is id<TAB>mode<TAB>wordFormId
    " title='" + iId + "\t" + sMode + "\t" + iWordFormId + "\t" + iRowNr + "'"
    + " onClick=\"javascript: sOpenMenu = 'posInput';\" " +
    " onkeyup=\"javascript: " +
    " if(iLastKeyPressed == 13) " +
    "  hideMenus(); " +
    " else if( (iHeldDownKey != 16) && (iHeldDownKey != iCtrlKey) && " + 
    "(iLastKeyPressed != 40) && " + // Up arrow
    "(iLastKeyPressed != 38)) {" +  // Down arrow
    " iSelectedLemmaSuggestionRow = -1; iMaxLemmaSuggestionRow = -1; " +
    "fillLemmaSuggestions(this, 'posInput');}\" " +
    " value='" + escapeSingleQuotes(sInnerHTML) + "'>";
  // Now that it's there, give it focus
  document.getElementById('posInput').focus();
}

function savePosInput(oPosInput) {
  if( oPosInput ) {
    // NOTE that the title is id<TAB>mode<TAB>wordFormId<TAB>rowNr
    var aTitle = oPosInput.title.split("\t");
    
    // The oPosInput can contain &nbsp; which end up here looking as a space
    // while they are actually \xA0 characters, that lemmaTupleString2array
    // can't handle
    oPosInput.value = oPosInput.value.replace(/\xA0/g, ' ');

    // If it changed put in the database
    if( oPosInput.value != sPosInputBeforeEditing) {
      var bAllValid = true;

      // Check if every one of the analyses is valid (the user might have
      // altered one in the middle).
      if( bCheckAnalysisFormatValidity ) {
	var aAnalyses = oPosInput.value.split("|");
	for(var i = 0; i < aAnalyses.length; i++ ) {
	  if( ! analysisFormatIsValid(aAnalyses[i]) ) {
	    alert("'" + aAnalyses[i] + "' is not in valid analysis format");
	    bAllValid = false;
	    break;
	  }
	}
      }

      if( bAllValid)
	saveTextAttestations(aTitle[2], aTitle[3], oPosInput);
    }
  }
}

// This function is called when the user clicks on an item in the lemma
// suggestion list, hits enter in the input box or clicks somewhere else
// on the screen.
// In short: when the contents of the box needs to be saved.
function updatePosInput(sLemmaSuggestion) {
  var oPosInput = document.getElementById('posInput');

  // Check if a row was selected in the menu
  if( iSelectedLemmaSuggestionRow != -1 )
    sLemmaSuggestion =
      document.getElementById('lemmaSuggestionRow_' +
			      iSelectedLemmaSuggestionRow).title;

  console.log('Lemma suggestion: ' + sLemmaSuggestion);

  // If there is a new lemma suggestion
  if( sLemmaSuggestion.length ) {
    // Get the current values
    var aOldValues = oPosInput.value.split("|");
    // Take all but the last one, which is replaced by the new suggestion
    oPosInput.value = aOldValues.slice(0, (aOldValues.length -1 )).join(" | ");
    if(oPosInput.value.length)
      oPosInput.value += " | ";
    oPosInput.value += sLemmaSuggestion;
  }

  // Validate the suggestions, update the database and fill the pos col again
  savePosInput(oPosInput);
  
  // And now that we are done, hide the suggestions box again
  var oTextAttSuggestions = document.getElementById('textAttSuggestions');
  oTextAttSuggestions.innerHTML = '';
  oTextAttSuggestions.style.visibility = 'hidden';

  iSelectedLemmaSuggestionRow = -1;
  iMaxLemmaSuggestionRow = -1;
}

function makeTokenAttSuggestionEditable(oNewTokenAtt, iDocumentId, iSentenceNr,
					iWordFormId, iStartPos, iEndPos) {
  var sInnerHtml = oNewTokenAtt.innerHTML;
  if(sInnerHtml.substr(0, 6) != '<input') {
    oNewTokenAtt.innerHTML = "<input type=text id=newTokenAttInput size=25" +
      // NOTE that the title is
      // documentId<TAB>sentenceNr<TAB>wordFormId<TAB>startPos<TAB>endPos
      " title='" + iDocumentId + "\t" + iSentenceNr + "\t" + iWordFormId +
      "\t" + iStartPos + "\t" + iEndPos + "'" +
      " onClick=\"javascript: sOpenMenu = 'tokenAttSuggestions';\" " +
      " onkeyup=\"javascript:" + 
      " if(event.keyCode == 13)" +
      "  updateTokenAttInput('');" +
      " else if( (event.keyCode != 38) && (event.keyCode != 40) ) " +
      "  fillLemmaSuggestions(this, 'tokenAttSuggestion');\" " + ">";

    // Now that it's there, give it focus
    document.getElementById('newTokenAttInput').focus();
  }
  sOpenMenu = 'tokenAttSuggestions';
}

function saveNewTokenAttInput(oNewTokenAttInput) {
  if( oNewTokenAttInput ) {
    // documentId<TAB>sentenceNr<TAB>wordFormId<TAB>startPos<TAB>endPos
    var aTitle = oNewTokenAttInput.title.split("\t");

    // The oNewTokenAttInput can contain &nbsp; which end up here looking as a
    // space while they are actually \xA0 characters, that
    // lemmaTupleString2array can't handle
    var sValue = oNewTokenAttInput.value.replace(/\xA0/g, ' ');

    if( (! bCheckAnalysisFormatValidity) ||
	(bCheckAnalysisFormatValidity && analysisFormatIsValid(sValue)) )
      // Put in the database
      addNewTokenAttestation(aTitle[0], aTitle[1], aTitle[2], aTitle[3],
			     aTitle[4], sValue);
    else
      alert("'" + sValue + "' is not in valid analysis format");
  }
}

// This function is called when the user clicks on an item in the lemma
// suggestion list, hits enter in the input box or clicks somewhere else
// on the screen.
// In short: when the contents of the box needs to be saved.
function updateTokenAttInput(sLemmaSuggestion) {
  var oNewTokenAttInput = document.getElementById('newTokenAttInput');
  // If there is a new lemma suggestion
  if( sLemmaSuggestion.length )
    oNewTokenAttInput.value = sLemmaSuggestion;

  // Validate the suggestions, update the database
  saveNewTokenAttInput(oNewTokenAttInput);
  iSelectedLemmaSuggestionRow = -1;
  iMaxLemmaSuggestionRow = -1;
}

// Fill the box that displays suggestions of currently available lemmata that
// match what was typed in.
// sMenuMode = 'posInput', 'tokenAttSuggestion' or 'lemmaFilter'
function fillLemmaSuggestions(oPosInput, sMenuMode) {
  var sPage = './php/fillLemmaSuggestions.php?sDatabase=' + sDatabase +
    '&sValue=' + encodeURIComponent(oPosInput.value) + "&sMenuMode="
    + sMenuMode + "&" + uniqueString();

  // We use a global xmlHttp object so we can abort it if it is still busy
  // doing somthing in the background
  if( oXmlHttpLemmaSuggestion )
    oXmlHttpLemmaSuggestion.abort();
  else
    oXmlHttpLemmaSuggestion = getXMLHttpObject();

  oXmlHttpLemmaSuggestion.onreadystatechange=function() {
    if(oXmlHttpLemmaSuggestion.readyState == 4) {
      var oSuggestions = document.getElementById('textAttSuggestions');

      if( oXmlHttpLemmaSuggestion.responseText.length ) {
	oSuggestions.innerHTML = oXmlHttpLemmaSuggestion.responseText;
	var aCoordinates = findPos(oPosInput);
	oSuggestions.style.left = aCoordinates[0]  + 'px';

	// NOTE that we take scrolling that has been done into account
	// depending on where we are displaying
	var sWindowPart = (sMenuMode == 'posInput')
	? 'wordsToAttest' : 'sentences';
      
	var iScrollTop = (sWindowPart == 'wordsToAttest')
	? document.getElementById(sWindowPart).scrollTop : 0;
      
	var iSuggestionsTop = (parseInt(aCoordinates[1]) +
			       oPosInput.offsetHeight - iScrollTop);
	oSuggestions.style.top = iSuggestionsTop + 'px';
	// The menu drop 'up' rather than down if it doesn't fit.
	// Only in tokenAttSuggestion mode (in the bottom part of the screen).
	if( (sMenuMode == 'tokenAttSuggestion') &&
	    (iSuggestionsTop + oSuggestions.offsetHeight) >
	      document.body.clientHeight) {
	  oSuggestions.style.top = (iSuggestionsTop - oPosInput.offsetHeight -
				    oSuggestions.offsetHeight) + 'px';
	}
	oSuggestions.style.visibility = 'visible';
      }
      else { // No suggestions
	oSuggestions.innerHTML = '';
	oSuggestions.style.visibility = 'hidden';
      }

      // Always start at the top
      iMaxLemmaSuggestionRow = -1;
      iSelectedLemmaSuggestionRow = -1;
    }
  }
  oXmlHttpLemmaSuggestion.open("GET", sPage, true);

  oXmlHttpLemmaSuggestion.send(null);
}

// NOTE that this function is always called when somebody hits 'Enter' and the
// the top bar of the screen was selected (sClickedInWindowPart == 'topBar');
function updateLemmaFilter() {
  var sLemmaSuggestion;

  // Check if a row was selected in the menu
  if( iSelectedLemmaSuggestionRow != -1 )
    sLemmaSuggestion =
      document.getElementById('lemmaSuggestionRow_' +
			      iSelectedLemmaSuggestionRow).title;
  else { // It is whatever the user typed in
    sLemmaSuggestion = document.getElementById('lemmaFilterInput').value;
    if( sLemmaSuggestion == 'Filter lemmata...')
      sLemmaSuggestion = '';
  }

  if( sLemmaSuggestion != sLemmaFilter)
    applyLemmaFilter(sLemmaSuggestion);
}

function applyLemmaFilter(sLemmaSuggestion) {
  var sHref = './lexiconTool.php?sDatabase=' + sDatabase +
    '&iUserId=' + iUserId + '&sUserName=' + sUserName + '&sFilter=' +
    sFilter + '&sLemmaFilter=' + encodeURIComponent(sLemmaSuggestion) +
    '&iNrOfWordFormsPerPage=' + iNrOfWordFormsPerPage +
    '&iNrOfSentencesPerWordform=' + iNrOfSentencesPerWordform +
    '&sSortBy=' + sSortBy + '&sSortMode=' + sSortMode + 
    '&bSortReverse=' + bSortReverse +
    '&sSortSentencesBy=' + sSortSentencesBy + 
    '&sSortSentencesMode=' + sSortSentencesMode +
    '&iAmountOfContext=' + iAmountOfContext + '&bDoShowAll=' + bDoShowAll;
  if( sMode == 'corpus')
    sHref += '&bDoShowCorpus=' + bDoShowCorpus +
      '&iCorpusId=' + iId + '&sCorpusName=' + sName;
  else // File mode
    sHref += '&bDoShowDocument=' + bDoShowDocument +
      '&iFileId=' + iId + '&sFileName=' + sName;
  top.location.href = sHref;
}

function hideMenus() {
  if( sOpenMenu != 'textAttSuggestions') {
    var oTextAttSuggestions = document.getElementById('textAttSuggestions');
    oTextAttSuggestions.innerHTML = '';
    oTextAttSuggestions.style.visibility = 'hidden';
    iSelectedLemmaSuggestionRow = -1;
    iMaxLemmaSuggestionRow = -1;
  }

  if( sOpenMenu != 'tokenAttSuggestions') {
    var oSuggestions = document.getElementById('tokenAttSuggestions');
    oSuggestions.style.visibility = 'hidden';
    // Because of this box being filled asynchronously we empty it here.
    // Otherwise sometimes a glimpse of the former innerHTML is still visible
    // before the new content comes in
    oSuggestions.innerHTML = '';
  }

  sOpenMenu = '';
}

function sortWordsToAttest(sNewSortBy, sNewSortMode, bNewSortReverse) {
  // Check out if the user hasn't clicked the button that was highlighted
  // already
  if( (sNewSortBy != sSortBy) || (sNewSortMode != sSortMode) || 
      (bNewSortReverse != bSortReverse) ) {
    // Lowlight the current sort button
    // The new button is highlighted when we are done filling in
    // fillWordsToAttest()
    var sReverse = '';
    if(sSortBy == 'wordForm') 
      sReverse = (bSortReverse) ? 'rl_': 'lr_';
    
    document.getElementById('sort_' + sSortBy + '_' + sReverse +
			    sSortMode).className = 'sortArrow';
    
    // Set the global variables
    sSortBy = sNewSortBy;
    sSortMode = sNewSortMode;
    bSortReverse = bNewSortReverse;

    // Whetever row was selected is not anymore
    iSelectedRow = -1;
    iStartAt = 0; // Reset the global variable. Begin at the start again.

    fillWordsToAttest(-1, -1, -1);
  }
}

function sortSentences(sNewSortBy, sNewSortMode) {
  // Reset global variable
  iStartAtSentence = 0;

  var sCurrentlySelected = (sSortSentencesBy) ?
    sSortSentencesBy + sSortSentencesMode : 'docasc';
  document.getElementById(sCurrentlySelected).className= 'sortByContextButton';

  var sNewSelected = (sNewSortBy) ?
    sNewSortBy + sNewSortMode : 'docasc';
  document.getElementById(sNewSelected).className = 'sortByContextButton_';

  // Set the global variables
  sSortSentencesBy = sNewSortBy;
  sSortSentencesMode = sNewSortMode;
  //  aSelected = []; // No selection anymore.

  var aIdAndWf =
    document.getElementById('wordRow_' + iSelectedRow).title.split("\t");
  // Scroll the sentence window to the top
  document.getElementById('sentences').scrollTop = 0;

  fillSentences(aIdAndWf[0], aIdAndWf[1], true);
}

function makeMatchedPartEditable(iSentenceNr, iDocumentId, iWordFormId, iOnset,
				 iOffset, sWordForm, sWordFormInText) {
  // If we are here, somebody definitely clicked in the sentence part
  sClickedInWindowPart = 'sentences';

  var oMatchedPartCol = document.getElementById('matchedPartCol_' + iDocumentId
						+ '_' + iOnset);


  // These two could be identical. By coincidence of course, but also because
  // when bNormalizeToken is on in the global settings (in php/global.php)
  // we work with the word form in the text rather than the canonical word form
  // (which is default).
  var sEscaped = html_entity_decode(decodeURIComponent(sWordForm));
  sEscaped = sEscaped.replace(/\"/g, '&quot;');
  var sEscapedWfInText =
    html_entity_decode(decodeURIComponent(sWordFormInText));
  sEscapedWfInText = sEscapedWfInText.replace(/\"/g, '&quot;');

  oMatchedPartCol.innerHTML = "<div id=matchedPart_editable>" +
    "<input type=text name=newCwWordForm value=\"" + sEscaped + "\"" +

    "onChange=\"javascript: " +
    " changeWordForm(" + iSentenceNr + ", " + iDocumentId + ", " + iOnset +
    ", " + iOffset + ", " + iWordFormId + ", '" +
    sEscaped.replace(/\'/g, "\\'") + "', '" + 
    sEscapedWfInText.replace(/\'/g, "\\'") +
    "', this.value" +
    ");\"" +
    ">" +
    "</div>";
}

function changeWordForm(iSentenceNr, iDocumentId, iOnset, iOffset,
			iOldWordFormId, sOldWordForm, sOldWordFormInText,
			sNewWordForm
			) {
  // Get the *currently* selected sentences (this used to be done higher up
  // but now we get them them 'live').
  var sSelectedSentences = '';
  var cPipe = '';
  if( aSelected.length ) {
    for( var i = 0; i < aSelected.length; i++ ) {
      // aSelected is an array of arrays. So aSelected[i] is an array:
      // 0: sentence number
      // 1: the title, which is documentId,startPos,endPos
      if(aSelected[i]) {
	sSelectedSentences += cPipe + aSelected[i][1];
	cPipe = '|';
      }
    }
  }
  else // No sentences selected, just the current one
    sSelectedSentences = iDocumentId + "," + iOnset + "," + iOffset;

  // First, for some robustness, delete surrounding spaces or '|'s.
  // They should not be possible, because they make no sense, so if the user
  // typed them in, neglect them.
  sNewWordForm = sNewWordForm.replace(/^[\s\|]+/, '');
  sNewWordForm = sNewWordForm.replace(/[\s\|]+$/, '');

  // If nothing actually changed, rebuild the original column.
  if( sOldWordForm == sNewWordForm) {
    var oMatchedPartCol =
      document.getElementById('matchedPartCol_' + iDocumentId + '_' + iOnset);
    /// NOTE THAT this also appears in php/lexiconTool.php:
    /// fillSentencesForDoc()
    oMatchedPartCol.innerHTML =
      "<div id=matchedPart_" + iDocumentId + "_" + iOnset +
      " class=matchedPart " +
      " onClick=\"javascript: " +
      "if( iHeldDownKey == iCtrlKey ) " +
      " makeMatchedPartEditable(" + iSentenceNr + ", " + iDocumentId + ", " +
      iOldWordFormId + ", " + iOnset + ", " + iOffset + ", '" +
      sOldWordForm + "', '" + sOldWordFormInText + "'); " +
      "else" +
      " showTokenAttSuggestions(this, " + iDocumentId + ", " + iSentenceNr +
      ", " + iOldWordFormId + ", " + iOnset + ", " + iOffset + ");\">" +
      sOldWordFormInText + "</div>";
  }
  else {
    changeWordForm_(iOldWordFormId, sOldWordForm, sNewWordForm,
		    sSelectedSentences);
  }
}

function changeWordForm_(iOldWordFormId, sOldWordForm, sNewWordForm,
			 sSelectedSentences) {
  var sPage = './php/changeWordForm.php?sDatabase=' + sDatabase +
    '&iUserId=' + iUserId + '&iOldWordFormId=' + iOldWordFormId +
    '&sOldWordForm=' + encodeURIComponent(sOldWordForm) +
    '&sNewWordForm=' + encodeURIComponent(sNewWordForm) +
    '&sSelectedSentences=' + sSelectedSentences +  "&" + uniqueString();

  oXmlHttp = getXMLHttpObject();

  var oProgressBar = document.getElementById('progressBar');

  oXmlHttp.onreadystatechange=function() {
    if(oXmlHttp.readyState == 0 ) {
      oProgressBar.innerHTML ="The request is not initialized";
    }
    if(oXmlHttp.readyState == 1 ) {
      oProgressBar.innerHTML = "Changing wordform...&nbsp;&nbsp;&nbsp;" +
      "<img src='./img/circle-ball-dark-antialiased.gif'>";
    }
    if(oXmlHttp.readyState == 2 ) {
      oProgressBar.innerHTML ="The request has been sent.";
    }
    if(oXmlHttp.readyState == 3 ) {
      oProgressBar.innerHTML ="The request is in progress.";
    }
    if(oXmlHttp.readyState == 4) {
      if( oXmlHttp.responseText.length) { // There was an error
		critical_alert(oXmlHttp.responseText);
      }

      // Even if an error occurred we reload anyway to display the current
      // state of affairs
      // As some frequencies have changed, we need to fill the words to attest
      fillWordsToAttest(iSelectedRow,
			iOldWordFormId +"\t" + encodeURIComponent(sOldWordForm),
			sNewWordForm);
    }
  }

  oProgressBar.style.visibility = 'visible';
  ///  alert("GEtting page: " + sPage);
  oXmlHttp.open("GET", sPage, true);

  oXmlHttp.send(null);
}

/* Lemma edit functions *******************************************************/

function fillEditLemmaDiv(iLemmaId) {
  var oLemmaEditDiv = document.getElementById('lemmaEditDiv');

  if(oLemmaEditDiv.style.display == 'block') // You can toggle
    oLemmaEditDiv.style.display = 'none';
  else {
    fillEditLemmaDiv_(oLemmaEditDiv, iLemmaId);
  }
}

function fillEditLemmaDiv_(oLemmaEditDiv, iLemmaId) {
  var sPage = './php/fillEditLemma.php?sDatabase=' + sDatabase +
    '&iUserId=' + iUserId + '&iLemmaId=' + iLemmaId + "&" + uniqueString();
    
  oXmlHttp = getXMLHttpObject();
    
  oXmlHttp.onreadystatechange=function() {
    if(oXmlHttp.readyState == 4) {
      oLemmaEditDiv.innerHTML = oXmlHttp.responseText;
      oLemmaEditDiv.style.display = 'block';
    }
  }

  oXmlHttp.open("GET", sPage, true);

  oXmlHttp.send(null);
}

// NOTE that you can not alter/delete modern wordform/patterns, because these
// are attached at analysis level
function alterLemma(iLemmaId) {
  var oForm = document.getElementById('editLemmaForm');

  var sNewLemmaString = oForm.el_modernLemma.value + ", " +
    oForm.el_partOfSpeech.value;
  var iLanguageId = 0;
  if( oForm.el_language && oForm.el_language.value.length ) {
    var aLang = oForm.el_language.value.split(':');
    iLanguageId = aLang[0];
    sNewLemmaString += ", " + aLang[1];
  }
  if( oForm.el_gloss.value.length )
    sNewLemmaString += ", " + oForm.el_gloss.value;

  var sConfirmString = "Do you really want to change the lemma into '" +
    sNewLemmaString + "'?";
  var bYes = confirm(sConfirmString);
  if( bYes ) {
    alterLemma_(iLemmaId, oForm.el_modernLemma.value,
		oForm.el_partOfSpeech.value, oForm.el_gloss.value,
		iLanguageId, sNewLemmaString);
  }
}

function alterLemma_(iLemmaId, sModernLemma, sPartOfSpeech, sGloss, iLanguageId,
		     sNewLemmaString) {
  var oLemmaEditDiv = document.getElementById('lemmaEditDiv');

  var sPage = './php/alterLemma.php?sDatabase=' + sDatabase +
    '&iLemmaId=' + iLemmaId +
    '&sModernLemma=' + encodeURIComponent(sModernLemma) +
    '&sPartOfSpeech=' + encodeURIComponent(sPartOfSpeech) +
    '&sGloss=' + encodeURIComponent(sGloss) +
    '&iLanguageId=' + iLanguageId +
    '&' + uniqueString();
    
  oXmlHttp = getXMLHttpObject();
    
  oXmlHttp.onreadystatechange=function() {
    if(oXmlHttp.readyState == 4) {
      if( oXmlHttp.responseText.length ) // An error occured
	oLemmaEditDiv.innerHTML = oXmlHttp.responseText;
      else {
	// Update the lemma filter box
	document.getElementById('lemmaFilterInput').value = sNewLemmaString;
	// Set the global variable
	sLemmaFilter = sNewLemmaString;
	// Update the page
	fillWordsToAttest(-1, -1, -1);
	// Clear the sentences
	clearSentences();
      }
    }
  }
  oXmlHttp.open("GET", sPage, true);

  oXmlHttp.send(null);
  
}

function deleteLemma(iLemmaId) {
  var oForm = document.getElementById('editLemmaForm');

  var sLemmaString = oForm.el_modernLemma.value + ", " +
    oForm.el_partOfSpeech.value;
  if( oForm.el_language && oForm.el_language.value.length ) {
    var aLang = oForm.el_language.value.split(':');
    sLemmaString += ", " + aLang[1];
  }
  if( oForm.el_gloss.value.length )
    sLemmaString += ", " + oForm.el_gloss.value;

  var sConfirmString = "Do you really want to remove '" + sLemmaString +
    "'? Please note that al analyses below featuring this " +
    "lemma will dispappear. Also NOTE that this step is *NOT* reversible";
  var bYes = confirm(sConfirmString);
  if( bYes) {
    deleteLemma_(iLemmaId, sLemmaString);
  }
}

function deleteLemma_(iLemmaId, sLemmaString) {
  var oLemmaEditDiv = document.getElementById('lemmaEditDiv');

  var sPage = './php/deleteLemma.php?sDatabase=' + sDatabase +
    '&iLemmaId=' + iLemmaId + "&" + uniqueString();
    
  oXmlHttp = getXMLHttpObject();
    
  oXmlHttp.onreadystatechange=function() {
    if(oXmlHttp.readyState == 4) {
      if( oXmlHttp.responseText.length ) // An error occured
	oLemmaEditDiv.innerHTML = oXmlHttp.responseText;
      else {
	oLemmaEditDiv.innerHTML = "<div class=lemmaDeleted>Deleted lemma '" +
	  sLemmaString + "'</div>";
	// Update the page (which will be empty)
	fillWordsToAttest(-1, -1, -1);
	// Clear the sentences
	clearSentences();
      }
    }
  }

  oXmlHttp.open("GET", sPage, true);

  oXmlHttp.send(null);
}

/* Help functions ***** *******************************************************/

// Got this code from http://www.daniweb.com/forums/thread137235.html
// mild adjustment to remove node before returnig
function html_entity_decode(str) {
  try {
    var tarea=document.createElement('textarea');
    tarea.innerHTML = str;
    var sReturn = tarea.value;
    if( tarea.parentNode )
      tarea.parentNode.removeChild(tarea);
    return sReturn;
  }
  catch(e) {
    // for IE add <div id="htmlconverter" style="display:none;"></div>
    // to the page
    document.getElementById("htmlconverter").innerHTML =
      '<textarea id="innerConverter">' + str + '</textarea>';
    var content = document.getElementById("innerConverter").value;
    document.getElementById("htmlconverter").innerHTML = "";
    return content;
  }
}
