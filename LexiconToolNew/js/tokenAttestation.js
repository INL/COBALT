function ajaxCall_fillTokenAttestations(sWordFormIds, iOriginalStartRow,
					iNewStartRow, sProgressMode) {
  // Update the token attestations for corpus in the upper part of the
  // screen
  sPage = "./php/fillTokenAttestations.php?sDatabase=" + sDatabase +
    "&iUserId=" + iUserId + "&sUserName=" + sUserName + "&sMode=" + sMode +
    "&iId=" + iId + "&sWordFormIds=" + sWordFormIds + "&" + uniqueString();

  // Custom AJAX bit
  var oXmlHttp = getXMLHttpObject();

  var oSentences = document.getElementById('sentences');

  oXmlHttp.onreadystatechange=function() {
    if(oXmlHttp.readyState == 4) {
      var oProgressBar = document.getElementById('progressBar');
      
      // The response should look like:
      // wordFormId#tokenAttsForCorpus#tokenAttsForDb<TAB>wordFormId#etc...
      // if it doesn't we are having an error
      if( oXmlHttp.responseText.length &&
	  (! oXmlHttp.responseText.match(/^\d+#/)) ) {
	// Show the error
	oProgressBar.innerHTML = oXmlHttp.responseText;
      }
      else {
	if( oXmlHttp.responseText.length ) {
	  fillTokenAttestationDivs(iOriginalStartRow, oXmlHttp.responseText);
	}
      
	if( sProgressMode == 'going') {
	  oProgressBar.innerHTML =
	  'Done ' + iOriginalStartRow + ' to ' + (iNewStartRow-1);
	}
	else {
	  oProgressBar.innerHTML = '';
	  oProgressBar.style.visibility = 'hidden';
	}

	// Recurse
	fillAllTokenAttestations(iNewStartRow);
      }
    }
  }
  oXmlHttp.open("GET", sPage, true);

  oXmlHttp.send(null);
}

function updateTokenAttsForCorpus(iWordFormId, iRowNr) {
  var oXmlHttp = getXMLHttpObject();

  var sPage = "./php/updateTokenAttsForCorpus.php?sDatabase=" + sDatabase +
    "&iUserId=" + iUserId + "&sUserName=" + sUserName + "&iWordFormId=" +
    iWordFormId + "&sMode=" + sMode + "&iId=" + iId + "&" + uniqueString();
  var oDiv = document.getElementById('tokenAttsInCorpus_' + iWordFormId);

  oXmlHttp.onreadystatechange=function() {
    if(oXmlHttp.readyState == 4) {
      if( oDiv ) {
	oDiv.innerHTML = oXmlHttp.responseText;
	
	if( iRowNr == iSelectedRow) {
	  makePosColEditable(iWordFormId, 'tokenAttsInCorpus_' + iWordFormId,
			     iRowNr);
	}
      }
    }
  }
  oXmlHttp.open("GET", sPage, true);

  oXmlHttp.send(null);
}

function fillTokenAttestationDivs(iRowNr, sResponseText) {
  // We are doing this extra check because splitting an empty string results
  // in an array with length 1 in Javascript...
  if( sResponseText.length) {
    var aTokenAtts = sResponseText.split("\t");

    var aTuple;
    for(var i = 0; i < aTokenAtts.length; i++) {
      // Tuple is wordFormId#tokenAttsForCorpus#tokenAttsForDb
      aTuple = aTokenAtts[i].split("#");
      document.getElementById('tokenAttsInCorpus_' + aTuple[0]).innerHTML =
	corpusAttestations(iRowNr, aTuple[0], aTuple[1]);
      iRowNr++;
      document.getElementById('tokenAttsInDb_' + aTuple[0]).innerHTML =
	dbAttestations(aTuple[0], aTuple[2], iRowNr);
    }
  }
}

// Very similar to fillLemmaSuggestions
function showTokenAttSuggestions(oWordFormBox, iDocumentId, iSentenceNr,
				 iWordFormId, iStartPos, iEndPos) {
  var aCoordinates = findPos(oWordFormBox);
  var oSuggestions = document.getElementById('tokenAttSuggestions');

  // Custom AJAX part
  var sPage = './php/fillTokenAttSuggestions.php';
  var sParams = 'sDatabase=' + sDatabase +
    "&iDocumentId=" + iDocumentId + "&iSentenceNr=" + iSentenceNr +
    '&iWordFormId=' + iWordFormId + "&iStartPos=" + iStartPos +
    "&iEndPos=" + iEndPos + "&" + uniqueString();
  var sErrorMessage = "Error in showing token attestation suggestions...";

  // Custom AJAX bit
  var oXmlHttp = getXMLHttpObject();

  oXmlHttp.onreadystatechange=function() {
    if(oXmlHttp.readyState == 4) {
      oSuggestions.innerHTML = oXmlHttp.responseText;
      oSuggestions.style.left = aCoordinates[0]  + 'px';
      // NOTE that we take the position the sentence div is scrolled to into
      // account (though actually maybe the findPos() function should do that)
      var iSuggestionsTop = (parseInt(aCoordinates[1]) +
			     oWordFormBox.offsetHeight -
			     document.getElementById('sentences').scrollTop);
      oSuggestions.style.top = iSuggestionsTop + 'px';
      /* alert("Bottom: (" + (aCoordinates[1] + oWordFormBox.offsetHeight -
       document.getElementById('sentences').scrollTop) + " + " +
	    oSuggestions.offsetHeight + ") = " +
	    (aCoordinates[1] + oWordFormBox.offsetHeight -
       document.getElementById('sentences').scrollTop + oSuggestions.offsetHeight) +
	    " <-> window height: " + document.body.clientHeight);
      */
      if( (iSuggestionsTop + oSuggestions.offsetHeight) >
	  document.body.clientHeight) {
	oSuggestions.style.top = (iSuggestionsTop - oWordFormBox.offsetHeight -
				  oSuggestions.offsetHeight) + 'px';
      }

      oSuggestions.style.visibility = 'visible';
      sOpenMenu = 'tokenAttSuggestions';
    }
  }
   
  oXmlHttp.open("POST", sPage, true);
  oXmlHttp.setRequestHeader("Content-type",
			    "application/x-www-form-urlencoded");
  oXmlHttp.setRequestHeader("Content-length", sParams.length);
  oXmlHttp.setRequestHeader("Connection", "close");
  
  oXmlHttp.send(sParams);
}

// This function just calls the next one, but it makes the code elsewhere
// considerably shorter
function removeTokenAttestation(iSentenceNr, iAnalyzedWordFormId) {
  // Title of the sentence is docId,startPos,endPos
  var aTitle = document.getElementById('sentence_' +
				       iSentenceNr).title.split(',');
  
  // NOTE that the className is (mis)used to indicate that we are removing
  // an attestation here
  tokenAttest(aTitle[0], iSentenceNr, iSelectedWordId, iAnalyzedWordFormId,
	      aTitle[1], aTitle[2], 'lemmaSuggestion_isAtt_');
}

// This function is called when somebody clicks on an option in the token
// attestation suggestions box (and also when somebody clicks on an attestation 
// to the right side of a sentence, so as to remove it)
function tokenAttest(iDocumentId, iSentenceNr, iWordFormId,
		     iAnalyzedWordFormId, iStartPos, iEndPos, sClassName) {
	
	// tell the user (s)he has to wait (make progressbar visible)
	var oProgressBar = document.getElementById('progressBar');
	oProgressBar.innerHTML = "Processing...&nbsp;&nbsp;&nbsp;" +
	"<img src='./img/circle-ball-dark-antialiased.gif'>";
	oProgressBar.style.visibility = 'visible';	
			 
			 
  // If some rows were selected we attest for all these rows including the one
  // that was clicked in (which isn't necessarily selected... try it).
  // NOTE that this might result in a double entry in the sSelecteds list, in
  // case it *was* selected.
  var sSelecteds = iDocumentId + "," + iStartPos + "," + iEndPos;

  if( aSelected.length) {
    for( var i = 0; i < aSelected.length; i++ ) {
      if( aSelected[i] )
	// aSelected is an array of arrays. So aSelected[i] is an array:
	// 0: sentence number
	// 1: the title, which is documentId,startPos,endPos
	sSelecteds += '|' + aSelected[i][1];
    }
  }

  // NOTE that we append a "unique string" at the end, to make sure that e.g.
  // IE will actually carry out AJAX calls more than once as well...
  var sPage = "./php/tokenAttest.php";
  var sParams = "sDatabase=" + sDatabase + "&iUserId=" +
    iUserId + "&iWordFormId=" + iWordFormId + "&iAnalyzedWordFormId=" +
    iAnalyzedWordFormId + "&sSelecteds=" + sSelecteds +
    "&sClassName=" + sClassName + "&" + uniqueString();

  // Custom AJAX part
  var xmlHttp = getXMLHttpObject();

  xmlHttp.onreadystatechange=function() {
    if(xmlHttp.readyState == 4) {
		if( xmlHttp.responseText.length)
			critical_alert(xmlHttp.responseText); // if an error occured

	  var oWordRow = document.getElementById('wordRow_' + iSelectedRow);
	  var aIdAndWf = oWordRow.title.split("\t");
	  
		// Update the sentences, bottom part of the screen 
		// old way: update whole view, which is often slow (and makes modified analyses disappear in some sorting modes)
		//fillSentences(iWordFormId, aIdAndWf[1], false);		
		// new way: update only the selection and don't change sorting (as the update is per row)
		updateAnalysesOfSelectedRowsOnScreen(aSelected, iSentenceNr);
		
		// if we are not de-attesting, check the checkboxes as well
		if (sClassName!='lemmaSuggestion_isAtt_') updateCheckBoxes(aSelected, "checked");

	  // Update the token attestations for corpus in the upper part of the
	  // screen
	  updateTokenAttsForCorpus(iWordFormId, iSelectedRow);

	  // And do the same for the token attestations in db
	  ajaxCall("./php/updateTokenAttsForDb.php?sDatabase=" + sDatabase +
		   "&iRowNr=" + iSelectedRow + "&iWordFormId=" + iWordFormId +
		   "&" + uniqueString(),
		   document.getElementById('tokenAttsInDb_' + iWordFormId),
		   "");
		   
    }
  }
  
  xmlHttp.open("POST", sPage, true);
  xmlHttp.setRequestHeader("Content-type",
			   "application/x-www-form-urlencoded");
  xmlHttp.setRequestHeader("Content-length", sParams.length);
  xmlHttp.setRequestHeader("Connection", "close");

  xmlHttp.send(sParams);

  // Toggle the attested/not attested style in the menu
  // This can't be done in the readyState == 4 part because of the
  // asynchronisity
  return ( sClassName.substr(-7) == '_isAtt_' )
    ? sClassName.substr(0, sClassName.length-6) : sClassName + 'isAtt_';
}

// Toggle the verification box
// When F8 is pressed this function is called from a global perspective, so
// none of its arguments can be set in that case.
// Normally we check the row that was clicked on to see if we need to verify or
// unverify. In the globals case we see if one of the selected rows was
// unchecked. If so, we check them all. If all were checked, we uncheck them
// all.
function toggleTokenAttestation(oTokenVerificationBox, iDocumentId,
				iStartPos, iEndPos) {
	
	// tell the user (s)he has to wait
	document.body.style.cursor = "wait";	
	var oProgressBar = document.getElementById('progressBar');
	// Make progressbar visible 
	oProgressBar.innerHTML = "Processing...&nbsp;&nbsp;&nbsp;" +
	"<img src='./img/circle-ball-dark-antialiased.gif'>";
	oProgressBar.style.visibility = 'visible';	
	
	
  var sNewClassName, iNewValue;
  // See out if we to check or uncheck it, based on the row that was clicked
  if( oTokenVerificationBox ) {
    if( oTokenVerificationBox.className == 'checked' ||
	oTokenVerificationBox.className == 'grayChecked') {
      sNewClassName = 'unchecked';
      iNewValue = 0;
    }
    else {
      sNewClassName = 'checked';
      iNewValue = 1;
    }
  }

  // If some rows were selected we (un)verify for all these rows
  var sSelecteds = '';
  var cSeparator = '';
  var sCurrentRow = (oTokenVerificationBox)
    ? iDocumentId + "," + iStartPos + "," + iEndPos
    : '';
  var bAddCurrentRow = true;
  var bOneWasUnchecked = false;
  
  if( aSelected.length) {
    for( var i = 0; i < aSelected.length; i++ ) {
      if( aSelected[i] ) {
	  
		// aSelected is an array of arrays. So aSelected[i] is an array:
		// 0: sentence number
		// 1: the title, which is documentId,startPos,endPos
		sSelecteds += cSeparator + aSelected[i][1];
		cSeparator = "|";
		if( aSelected[i][1] == sCurrentRow)
		  bAddCurrentRow = false;
		if( ! oTokenVerificationBox) { // In the global case
		  var aDocIdStartEnd = aSelected[i][1].split(",");
		  if(document.getElementById('checkBox_tokenAtt_' + aDocIdStartEnd[0] +
					   '_' + aDocIdStartEnd[1]).className ==
			 'unchecked')
			bOneWasUnchecked = true;
		}
      }
    }
  }

  if( ! oTokenVerificationBox ) { // Global case
    if(bOneWasUnchecked) {
      sNewClassName = 'checked';
      iNewValue = 1;
    }
    else {
      sNewClassName = 'unchecked';
      iNewValue = 0;
    }
  }

  // If the current row wasn't selected, we add it to the sSelecteds and also to aSelected, which
  // ensures that the toggling is in fact done for the row.
  // NOTE that this does/must NOT result in the row being selected.
  if(oTokenVerificationBox && bAddCurrentRow)
	{
    sSelecteds += cSeparator + sCurrentRow;
	
	var sIdOfRow = "checkBox_tokenAtt_"+sCurrentRow.split(",")[0]+"_"+sCurrentRow.split(",")[1];
	var sIdOfRow = document.getElementById(sIdOfRow).parentNode.parentNode.id;
	var iNumberOfRow = parseInt(sIdOfRow.replace("sentence_", ""));
	aSelected.push([iNumberOfRow, sCurrentRow]);
	}

  if( sSelecteds.length ) {
    var sPage = "./php/verifyTokenAttestation.php";
    var sParams = "sDatabase=" + sDatabase +
      "&sSelecteds=" + sSelecteds +
      "&iWordformId=" + iSelectedWordId + "&iNewValue=" + iNewValue +
      "&iUserId=" + iUserId + "&" + uniqueString();

    // Custom AJAX part
    var xmlHttp = getXMLHttpObject();

    xmlHttp.onreadystatechange=function() {
			
      if(xmlHttp.readyState == 4) {
		if( xmlHttp.responseText.length ) {
		  sNewClassName = 'undefinedCheckbox';
		  critical_alert(xmlHttp.responseText); // if an error occured
		}

		// We update all sentences
		var oWordRow = document.getElementById('wordRow_' + iSelectedRow);
		var aIdAndWf = oWordRow.title.split("\t");
		
		
		// Update the sentences part of the screen 
		// old way: update whole view, which is often slow (and makes modified analyses disappear in some sorting modes)
		////fillSentences(iSelectedWordId, aIdAndWf[1], false);
		// new way: update only the checkboxes (fast!)
		updateCheckBoxes(aSelected, sNewClassName);
		
		
		// If no row was selected, but one row was clicked upon, we had to add it to the selected array
		// to make the updateCheckBoxes function work for this not selected row. But to keep things clean
		// we have to remove this row from the array now.
		if (bAddCurrentRow) aSelected = [];
		
		// Update the token attestations for corpus in the upper part of the
		// screen if the token attestation was being verified (ie. the new value
		// is 1), because in that case an analyzed wordform might have become
		// validated as well
		if( iNewValue == 1 ) {
		  ajaxCall("./php/updateTokenAttsForDb.php?sDatabase=" + sDatabase +
			   "&iRowNr=" + iSelectedRow +
			   "&iWordFormId=" + iSelectedWordId + "&" + uniqueString(),
			   document.getElementById('tokenAttsInDb_' + iSelectedWordId),
			   "");
		}
		
		// make progress bar invisible again
		oProgressBar.style.visibility = 'hidden';
		document.body.style.cursor = "default";
      }
    }
    // NOTE that we append a "unique string" at the end, to make sure that e.g.
    // IE will actually carry out AJAX calls more than once as well...
    xmlHttp.open("POST", sPage, true);
    xmlHttp.setRequestHeader("Content-type",
			     "application/x-www-form-urlencoded");
    xmlHttp.setRequestHeader("Content-length", sParams.length);
    xmlHttp.setRequestHeader("Connection", "close");

    xmlHttp.send(sParams);
  }
}

// subroutine of toggleTokenAttestation
function updateCheckBoxes(aSelected, sNewValue){

	for (var i=0; i<aSelected.length; i++)
	{
		if (typeof aSelected[i] == 'undefined') 
			continue;
			
		// aSelected is an array of arrays. So aSelected[i] is an array:
		// 0: sentence number
		// 1: the title, which is documentId,startPos,endPos
		var iSentenceNr = aSelected[i][0];
		var aDocIdAndStartPos = aSelected[i][1].split(",");
		
		// get the span of the checkbox, given its id
		var sCheckBoxId = "checkBox_tokenAtt_"+aDocIdAndStartPos[0]+"_"+aDocIdAndStartPos[1];
		var eCheckbox = document.getElementById(sCheckBoxId);
		
		// change the src of the image of the checkbox (show checked or unchecked box)
		
		// create a new img element
		var sNewSrcValue = "./img/"+sNewValue+".gif";
		var eImgToReplace = eCheckbox.getElementsByTagName("img")[0];
		eImgToReplace.parentNode.replaceChild( document.createElement("img"), eImgToReplace );
		
		// set the attribute of the new img and also change the class of the span to 'checked' or 'unchecked' value
		eCheckbox = document.getElementById(sCheckBoxId);
		eCheckbox.setAttribute("class", sNewValue);
		eNewImg = eCheckbox.getElementsByTagName("img")[0];
		eNewImg.setAttribute("src", sNewSrcValue);
	}

}


// this function is not in use anymore (replaced by updateAnalysesOfSelectedRows)
function tokenAttestSelected(iWordFormId, iAnalyzedWordFormId, iRowNr) {
  // Here we wait for the sentences to load.
  // Actually we are just working around the a-synchronisity where the
  // sentences load and are iterated through at (possibly) the same time
  if( ! document.getElementById('done_' + iWordFormId) )
    setTimeout("tokenAttestSelected(" + iWordFormId + ", " +
	       iAnalyzedWordFormId + " , " + iRowNr + ")", 10);
  else {
    var aTitle;
    var sTokenAttestations = '';
    var cSeparator = '';
    if( aSelected.length) {
      for( var i = 0; i < aSelected.length; i++ ) {
	// aSelected is an array of arrays. So aSelected[i] is an array:
	// 0: sentence number
	// 1: the title, which is documentId,startPos,endPos
	
	// If an element is deleted it is actually set to undefined...
	if(aSelected[i]) {
	  sTokenAttestations +=
	    cSeparator + iWordFormId + ',' + aSelected[i][1] + ',' +
	    iAnalyzedWordFormId;
	  cSeparator = "|";
		}
      }
    }
    else { // None selected, which means we take all
      var i = 0;
      var oWordForm = document.getElementById('sentence_' + i);
      while( oWordForm ) {
		sTokenAttestations += cSeparator + iWordFormId + ',' + oWordForm.title
		  + ',' + iAnalyzedWordFormId;
		cSeparator = "|";
		
		i++;
		oWordForm = document.getElementById('sentence_' + i);
      }
    }

    /// Misschien is het ook hier niet helemaal lekker omdat dit een nogal
    /// grote POST variabele kan worden
    
    // So, now we have all the right data and we can send it to PHP
    var sPage = "./php/saveTokenAttestations2.php";
    var sParams = "sDatabase=" + sDatabase +
      "&iUserId=" + iUserId + '&iId=' + iId + "&sMode=" + sMode +
      "&iWordFormId=" + iWordFormId +
      "&sTokenAttestations=" + sTokenAttestations +
      // Here we use the length of aSelected array to see whether we have to
      // verify or not
      "&bVerify=" + aSelected.length + "&" + uniqueString();
    var oDiv = document.getElementById('tokenAttsInDb_' + iWordFormId);
    var sErrorMessage =
      "<b>ERROR</b>: Saving token attestations didn't work out somehow";
  
    // Custom AJAX bit
    var xmlHttp = getXMLHttpObject();

    xmlHttp.onreadystatechange=function() {
      if(xmlHttp.readyState == 4) {
		// Check if the response is as expected...
		if(xmlHttp.responseText.length) {
		  oDiv.innerHTML = xmlHttp.responseText;
		}
		else {
		  // The returned line contains the token attestations per sentence
		  // We want to know this if the sentences are still being displayed
		  // (i.e. the user hasn't clicked on another row).
		  if(iSelectedRow == iRowNr) {
			var oWordRow = document.getElementById('wordRow_' + iRowNr);
			var aIdAndWf = oWordRow.title.split("\t");
		  
		  // update the whole sentence view (bottom part of screen)
			fillSentences(iWordFormId, aIdAndWf[1], false);
			
		  }
		
		  // Update the token attestations for corpus in the upper part of the
		  // screen
		  updateTokenAttsForCorpus(iWordFormId, iRowNr);
		
		  // And do the same for the token attestations in db
		  ajaxCall("./php/updateTokenAttsForDb.php?sDatabase=" + sDatabase +
			   "&iRowNr=" + iRowNr + "&iWordFormId=" + iWordFormId +
			   "&" + uniqueString(),
			   document.getElementById('tokenAttsInDb_' + iWordFormId),
			   "");
			}
      }
    }

    xmlHttp.open("POST", sPage, true);
    xmlHttp.setRequestHeader("Content-type",
			     "application/x-www-form-urlencoded");
    xmlHttp.setRequestHeader("Content-length", sParams.length);
    xmlHttp.setRequestHeader("Connection", "close");

    xmlHttp.send(sParams);
  }
}

// this is the same as tokenAttestSelected, but this version of the function is specialized
// in processing a few rows at the time (instead of everything at once = too heavy for small selections)
function updateAnalysesOfSelectedRows(iWordFormId, iAnalyzedWordFormId, iRowNr){

	// Here we wait for the sentences to load.
  // Actually we are just working around the a-synchronisity where the
  // sentences load and are iterated through at (possibly) the same time
  if( ! document.getElementById('done_' + iWordFormId) )
    setTimeout("updateAnalysesOfSelectedRows(" + iWordFormId + ", " +
	       iAnalyzedWordFormId + " , " + iRowNr + ")", 10);
  else {
		// tell the user (s)he has to wait (make progressbar visible(
		var oProgressBar = document.getElementById('progressBar');
		oProgressBar.innerHTML = "Processing...&nbsp;&nbsp;&nbsp;" +
		"<img src='./img/circle-ball-dark-antialiased.gif'>";
		oProgressBar.style.visibility = 'visible';	
		document.body.style.cursor = "wait";
		
		// check if there was a row selection / if there was none, select all the sentences
		var aSelection;
		
		if (aSelected.length)
			{
			aSelection = aSelected.slice(0);
			}
		else 
			{
			aSelection = [];
			var i = 0;
			var oWordForm = document.getElementById('sentence_' + i);
			while( oWordForm ) {
			// aSelected is an array of arrays. So aSelected[i] is an array:
			// 0: sentence number
			// 1: the title, which is documentId,startPos,endPos
				aSelection.push([i, oWordForm.title]);
				i++;
				oWordForm = document.getElementById('sentence_' + i);
				}
			}
		
		var aWholeSelection = aSelection.slice(0);
		_updateAnalysesOfSelectedRows(iWordFormId, iAnalyzedWordFormId, iRowNr, aSelection,  aWholeSelection);
    }
}

// subroutine of updateAnalysesOfSelectedRows
// aWholeSelection contains the whole original selection (needed for operation refering the whole original selection)
// while we will process only small pieces at the time
function _updateAnalysesOfSelectedRows(iWordFormId, iAnalyzedWordFormId, iRowNr, aSelection, aWholeSelection){

	var iMaxNumberToProcessAtOnce = 30;

	// take the front part of the list of rows to process
	// (we need to limit that otherwise the queries get to big and cause malfunction)
	if (aSelection.length <= iMaxNumberToProcessAtOnce)
		{
		aSmallSelected = aSelection.slice(0);
		aSelection = [];
		}
	else
		{
		aSmallSelected = aSelection.slice(0, iMaxNumberToProcessAtOnce);
		aSelection = aSelection.slice(iMaxNumberToProcessAtOnce);
		}

	var aTitle;
    var sTokenAttestations = '';
    var cSeparator = '';
    if( aSmallSelected.length) {
      for( var i = 0; i < aSmallSelected.length; i++ ) {
	// aSmallSelected is an array of arrays. So aSmallSelected[i] is an array:
	// 0: sentence number
	// 1: the title, which is documentId,startPos,endPos
	
	// If an element is deleted it is actually set to undefined...
	if(aSmallSelected[i]) {
	  sTokenAttestations +=
	    cSeparator + iWordFormId + ',' + aSmallSelected[i][1] + ',' +
	    iAnalyzedWordFormId;
	  cSeparator = "|";
		}
      }
    }

    /// Misschien is het ook hier niet helemaal lekker omdat dit een nogal
    /// grote POST variabele kan worden
    
    // So, now we have all the right data and we can send it to PHP
    var sPage = "./php/saveTokenAttestations2.php";
    var sParams = "sDatabase=" + sDatabase +
      "&iUserId=" + iUserId + '&iId=' + iId + "&sMode=" + sMode +
      "&iWordFormId=" + iWordFormId +
      "&sTokenAttestations=" + sTokenAttestations +
      // Here we use the length of aSmallSelected array to see whether we have to
      // verify or not
      "&bVerify=" + aSmallSelected.length + "&" + uniqueString();
	  
	  // Custom AJAX bit
    var xmlHttp = getXMLHttpObject();

    xmlHttp.onreadystatechange=function() {
      if(xmlHttp.readyState == 4) {
	  
			if (aSelection.length>0)
				{
				_updateAnalysesOfSelectedRows(iWordFormId, iAnalyzedWordFormId, iRowNr, aSelection, aWholeSelection);
				}
	  		else
				{
				// make progress bar invisible again
				var oProgressBar = document.getElementById('progressBar');
				oProgressBar.style.visibility = 'hidden';
				document.body.style.cursor = "default";
				document.body.style.cursor = "default";
				
				
				// Update only the shown analyses of the selected rows: 
				// the php will return updated html for the attestations
				// to the right side of the sentences, so we will update only that
				// and NOT the whole collection of sentences (like in tokenAttestSelected(), which is
				// too heavy for a small collection, in the sense that it costs too much time to
				// update the whole sentence view for just a few little changes!!)
				
				// update the shown analyses of the selected rows
				updateAnalysesOfSelectedRowsOnScreen(aWholeSelection);
				// show they are verified
				updateCheckBoxes(aWholeSelection, "checked");
						
				// Update the token attestations for corpus in the upper part of the
				// screen
				updateTokenAttsForCorpus(iWordFormId, iRowNr);

				// And do the same for the token attestations in db
				ajaxCall("./php/updateTokenAttsForDb.php?sDatabase=" + sDatabase +
				   "&iRowNr=" + iRowNr + "&iWordFormId=" + iWordFormId +
				   "&" + uniqueString(),
				   document.getElementById('tokenAttsInDb_' + iWordFormId),
				   "");
				   
				   
				
				}
			

		}
      }
	  
	  
	xmlHttp.open("POST", sPage, true);
	xmlHttp.setRequestHeader("Content-type",
				 "application/x-www-form-urlencoded");
	xmlHttp.setRequestHeader("Content-length", sParams.length);
	xmlHttp.setRequestHeader("Connection", "close");

	xmlHttp.send(sParams);
}

// function specialized in updating only a small number of sentence rows
// (so as to prevent updating the whole sentences view at once, which is too heavy (=time consuming)
// in cases of small selections)
function updateAnalysesOfSelectedRowsOnScreen(aSelected, iSentenceNr) {
	
	var iProgressBarCurrentValue = 0;
	var iProgressBarMaxValue = aSelected.length;
	
	var oProgressBar = document.getElementById('progressBar');
	// Make progressbar visible 
	oProgressBar.innerHTML =
					  // Generate an image name
					  "<table width=100%><tr>" +
					  "<td align=center><img src='./img/ProgressBar_0.png'></td></tr>" + 
					  // Display real percentage
					  "<tr><td align=right>0%</td>" +
					  "</tr></table>\n";
	oProgressBar.style.visibility = 'visible';	
	
	// mouse pointer should show the user (s)he has to wait
	document.body.style.cursor = "wait";
	
	_updateAnalysesOfSelectedRowsOnScreen(aSelected, iSentenceNr, iProgressBarCurrentValue, iProgressBarMaxValue);
}


function _updateAnalysesOfSelectedRowsOnScreen(aSelection, iSentenceNr, iProgressBarCurrentValue, iProgressBarMaxValue) {
	
	var oProgressBar = document.getElementById('progressBar');
	
	// a higher value than 15 causes 'too high level of nesting for select' error on production
	var iMaxNumberToProcessAtOnce = 15; 

	// take the front part of the list of rows to process
	// (we need to limit that otherwise the queries get to big and cause malfunction)
	if (aSelection.length <= iMaxNumberToProcessAtOnce)
		{
		aSmallSelected = aSelection.slice(0);
		aSelection = [];
		}
	else
		{
		aSmallSelected = aSelection.slice(0, iMaxNumberToProcessAtOnce);
		aSelection = aSelection.slice(iMaxNumberToProcessAtOnce);
		}
	
	
	var sArg = "";
	
	var aSelectedClone = aSmallSelected ? aSmallSelected.slice(0) : new Array();
	
	// if the sentence number is no part of the selection, we need to add it 
	// to the array of sentences to process (this happens if clicking happened
	// outside the selection)
	if (iSentenceNr != null 
		&& (aSelectedClone.length == 0 || !isPartOfSelection(iSentenceNr) ) )
	{
		var oSentence = document.getElementById('sentence_' + iSentenceNr);		
		if (oSentence != null) // somehow this happens sometimes?!
			aSelectedClone.push( [iSentenceNr, oSentence.title] );
	}
	
	// we need to get this set of data for each selected row:
	// iDocumentId, iSentenceNr, iWordFormId, iStartPos, iEndPos
	var aDocIdAndStartPos2SentenceNr = new Array();
	for( var i = 0; i < aSelectedClone.length; i++ ) {
	
		if (typeof aSelectedClone[i] == 'undefined') 
						continue;
	
		// aSelected is an array of arrays. So aSelected[i] is an array:
		// 0: sentence number
		// 1: the title, which is documentId,startPos,endPos		
		var iSentenceNr = aSelectedClone[i][0];
		var iWordFormId = iSelectedWordId;
		var iDocumentId = aSelectedClone[i][1].split(",")[0];
		var iStartPos = aSelectedClone[i][1].split(",")[1];
		var iEndPos = aSelectedClone[i][1].split(",")[2];
		
		// we will need this for matching later on
		aDocIdAndStartPos2SentenceNr[iDocumentId+"_"+iStartPos] = iSentenceNr;
		
		if (sArg!= "") sArg += "|";
		sArg += [iDocumentId, iSentenceNr, iWordFormId, iStartPos, iEndPos].join(",");
	}
	
	var sPage = "./php/updateAnalysesOfSelectedRows.php";
	var sParams = "sDatabase=" + sDatabase +
	  "&iUserId=" + iUserId + 
	  "&sRowsData=" + sArg +
	  "&iWordformId=" + iSelectedWordId +
	  "&" + uniqueString();
	
	// Custom AJAX bit
	var xmlHttp = getXMLHttpObject();

	xmlHttp.onreadystatechange=function() {
	
		if(xmlHttp.readyState == 4) {
			// Check if the response is as expected...
			if(xmlHttp.responseText.length) {
			
				// First put the response into an associative array.
				// Each row that has to be updated will be a value in this array, 
				// the key is the number of the sentence which must be updated
				var aIdToHtml = new Array();
				var aResponseArray = xmlHttp.responseText.split("\n");
				for (var i = 0; i < aResponseArray.length; i++ ) {
					var aKeyAndValue = aResponseArray[i].split(":::::");
					aIdToHtml[aKeyAndValue[0]] = aKeyAndValue[1];
					
				}
				
				for( var i = 0; i < aSelectedClone.length; i++ ) {
				
					iProgressBarCurrentValue++;
				
					if (typeof aSelectedClone[i] == 'undefined') 
						continue;
				
					// compute the right element id of the attestation in the html (given the doc ids and such),
					// and replace that element by the updated html returned by the php side
					var iDocId = aSelectedClone[i][1].split(",")[0];
					var iStartPos = aSelectedClone[i][1].split(",")[1];
					var sId = 'tokenAtts_' + iDocId + "_" + iStartPos;
					var oDiv = document.getElementById(sId);
					oDiv.innerHTML = aIdToHtml[ aDocIdAndStartPos2SentenceNr[iDocId + "_" + iStartPos] ];
					
					// progress bar
					var iProgress = Math.floor((iProgressBarCurrentValue/iProgressBarMaxValue)* 100);
				  
					  oProgressBar.innerHTML =
					  // Generate an image name
					  "<table width=100%><tr>" +
					  "<td align=center><img src='./img/ProgressBar_" +
					  // Every 5%
					  (iProgress - (iProgress % 5)) + ".png'></td></tr>" + 
					  // Display real percentage
					  "<tr><td align=right>" + iProgress + "%</td>" +
					  "</tr></table>\n";
				}
				
				if (aSelection.length>0)
					{
					_updateAnalysesOfSelectedRowsOnScreen(aSelection, iSentenceNr, iProgressBarCurrentValue, iProgressBarMaxValue);
					}
				else
					{
					// make progress bar invisible again
					oProgressBar.style.visibility = 'hidden';
					document.body.style.cursor = "default";
					}
				
			}
		
		}
	}
	
	xmlHttp.open("POST", sPage, true);
	xmlHttp.setRequestHeader("Content-type",
				 "application/x-www-form-urlencoded");
	xmlHttp.setRequestHeader("Content-length", sParams.length);
	xmlHttp.setRequestHeader("Connection", "close");

	xmlHttp.send(sParams);
}

// check if the selection contains a give sentence number
function isPartOfSelection( iSentenceNr ) {
    for(var i = 0, len = aSelected.length; i < len; i++) {
		if (typeof aSelected[i] == 'undefined') 
			continue;
		// beware of content of aSelected : array of arrays [ [sentenceNr, sTitle],. [] ]
        if( aSelected[i][0] == iSentenceNr )
            return true;
    }
    return false;
}


function selectAllSentences() {
  // Whatever else was selected, isn't anymore
  aSelected = [];
  var iSentenceNr = 0;
  var oSentence = document.getElementById('sentence_' + iSentenceNr);
  while( oSentence ) {
    oSentence.className = sentenceClassSelect(oSentence.className);
    aSelected.push([iSentenceNr, oSentence.title]);
    iSentenceNr++;
    oSentence = document.getElementById('sentence_' + iSentenceNr);
  }
  iSelectionExtremum = -1;
}

// Called on mouse down
function startSelection(iSentenceNr) {
  var oSentence = document.getElementById('sentence_' + iSentenceNr);
  // Keep track of whether it was selected already on its own
  bStartRowWasSolelySelected =
    ( (oSentence.className.charAt(oSentence.className.length - 1) == '-') &&
      (aSelected.length == 1) );
  bStartRowWasSelected =
    (oSentence.className.charAt(oSentence.className.length - 1) == '-');
  
  // Deselect everything else, unless we are multi-selecting
  if( iHeldDownKey != iCtrlKey )
    deselectAllSelected();
  bInSelection = true;
  iSelectionStart = iSentenceNr;
  iSelectionExtremum = iSentenceNr;
  // Select it
  oSentence.className = sentenceClassSelect(oSentence.className);
  // It is empty when we deselected all, but not otherwise
  aSelected.push([iSentenceNr, oSentence.title]);
}

// Called on mouse over
function addToSelection(iSentenceNr) {
  if( bInSelection ) {
    if( iSentenceNr != iSelectionStart) {   
      // Find out what direction the user is moving the mouse in with
      // respect to the sentence first selected (this could change in one
      // sweep).
      if( iSentenceNr > iSelectionStart) // Swiping down
	sSelectionDirection = 'down';
      else // Going upwards
	sSelectionDirection = 'up';

      // If the mouse is sweeping back to the first selected sentence, we
      // deselect the one we just selected
      if( ((sSelectionDirection == 'up') && (iSentenceNr > iSelectionExtremum))
	  ||
	  ((sSelectionDirection=='down') && (iSentenceNr < iSelectionExtremum))
	 ) {
	var oSentence= document.getElementById('sentence_'+iSelectionExtremum);
	oSentence.className = sentenceClassDeselect(oSentence.className);
	aSelected.pop();
      }
      else {
	var oSentence = document.getElementById('sentence_' + iSentenceNr);
	oSentence.className = sentenceClassSelect(oSentence.className);
	aSelected.push([iSentenceNr, oSentence.title]);
      }
      
      // The new one is always the extremum
      iSelectionExtremum = iSentenceNr;  
    }
    else {
      if( iSelectionStart != iSelectionExtremum) {
	var oSentence = document.getElementById('sentence_' +
						iSelectionExtremum);
	oSentence.className = sentenceClassDeselect(oSentence.className);
	aSelected.pop();
      }
    }
  }
}

// Called on mouse up
// If the end row is the same as the row we started with (e.g. because somebody
// just clicked) we check we have to select or de-selected.
//
function endSelection() {
  bInSelection = false;
  sSelectionDirection = '';

  // If we stopped where we started and it was the only row selected
  if( bStartRowWasSolelySelected && (aSelected.length == 1) )  {
    // Deselect it
    var oSentence = document.getElementById('sentence_'+ aSelected[0][0]);
    oSentence.className = sentenceClassDeselect(oSentence.className);
    
    // Empty the array
    aSelected = [];
  }
  else {
    if( bStartRowWasSelected &&
	((iHeldDownKey == iCtrlKey) ) && // Multi selection
	(iSelectionStart == iSelectionExtremum) ) {
      var oSentence = document.getElementById('sentence_'+ iSelectionExtremum);
      oSentence.className = sentenceClassDeselect(oSentence.className);
      deleteFromSelected(iSelectionExtremum);
    }
  }
}

function deleteFromSelected(iSentenceNr) {
  for(var i = 0; i < aSelected.length; i++ ) {
    if( aSelected[i] && (aSelected[i][0] == iSentenceNr) )
      delete aSelected[i];
  }
}

function deselectAllSelected() {
  var oSentence;
  for(var i = 0; i < aSelected.length; i++) {
    if( aSelected[i]) {
      // Every element is an array itself: [sentenceId, sentenceTitle]
      oSentence = document.getElementById('sentence_' + aSelected[i][0]);
      oSentence.className = sentenceClassDeselect(oSentence.className);
    }
  }
  // Empty the array
  aSelected = [];
}

// If the class ends in a dash, we delete it
function sentenceClassDeselect(sClassName) {
  if( sClassName.charAt(sClassName.length - 1) == '-')
    return sClassName.substring(0, sClassName.length - 1);
  return sClassName;
}

// If the class doesn't end in a dash, we add one
function sentenceClassSelect(sClassName) {
  if( sClassName.charAt(sClassName.length - 1) != '-')
    return sClassName + '-';
  return sClassName;
}

function toggleGroup(oWordForm, iDocumentId, iSentenceNr, iHeadWordOnset,
		     iHeadWordOffset, iOnset, iOffset, bInGroup) {
  // Get word form id
  var iWordFormId =
    document.getElementById('wordRow_' + iSelectedRow).title.split("\t")[0];

  var sPage = "./php/toggleGroup.php";
  var sParams = "sDatabase=" + sDatabase + "&iUserId=" + iUserId +
    "&iDocumentId=" + iDocumentId + "&iWordFormId=" + iWordFormId +
    "&iSentenceNr=" + iSentenceNr + "&iHeadWordOnset=" + iHeadWordOnset +
    "&iHeadWordOffset=" + iHeadWordOffset + "&iOnset=" + iOnset +
    "&iOffset=" + iOffset + "&bInGroup=" + bInGroup + "&" + uniqueString();
  var sErrorMessage = "<b>ERROR</b> in adding word to group.";
  
  // Custom AJAX bit
  var oXmlHttp = getXMLHttpObject();

  oXmlHttp.onreadystatechange=function() {
    if(oXmlHttp.readyState == 4) {
      if(oXmlHttp.responseText.substr(0, 8) == '<b>ERROR') {
	oWordForm.innerHTML = sErrorMessage + '<br>' + oXmlHttp.responseText;
      }
      else {
	// Fill the right column with any new token attestations (that belong
	// to group members)
	document.getElementById('tokenAtts_' + iDocumentId + "_" +
				iHeadWordOnset).innerHTML
	= oXmlHttp.responseText;
	
	// Here we finally really toggle.
	// If it was in the group it isn't anymore, and vice versa
	oWordForm.className = (bInGroup) ? "contextWord" : "contextWord_";

	// If it all went correctly, this row is also verified by now
	var oCheckBox = document.getElementById("checkBox_tokenAtt_" +
						iDocumentId + "_" +
						iHeadWordOnset);
	oCheckBox.className = 'checked';
	oCheckBox.innerHTML = "<img src='./img/checked.gif'>";
      }
    }
  }
   
  oXmlHttp.open("POST", sPage, true);
  oXmlHttp.setRequestHeader("Content-type",
			    "application/x-www-form-urlencoded");
  oXmlHttp.setRequestHeader("Content-length", sParams.length);
  oXmlHttp.setRequestHeader("Connection", "close");
  
  oXmlHttp.send(sParams);
}



