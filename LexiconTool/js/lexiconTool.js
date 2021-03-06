/******************************************************************************
*                                                                             *
* NOTE that global variables are set in globals.js and at run time (see       *
* the bottom of lexiconToolBox.php)                                           *
*                                                                             *
******************************************************************************/

// Very simple, just check if somebody typed in something at all.
function checkCorpusForm(oForm) {
  if( ! oForm.sCorpusName.value.match(/\w/) ) {
    alert("Please fill in a name...");
    return false;
  }

  if( ! optionSelected(oForm.aDocumentIds) ) {
    alert("Please select one or more documents...");
    return false;
  }
  
  return true;
}

function optionSelected(aDocumentIds) {
  for(var i = 0; i < aDocumentIds.length; i++ )
    if (aDocumentIds[i].selected === true)
      return true;
  return false;
}

// AJAX functions

function getXMLHttpObject() {
  var oXmlHttp;
  try {
    // Firefox, Opera 8.0+, Safari
    oXmlHttp=new XMLHttpRequest();
  }
  catch (e) {
    // Internet Explorer
    try {
      oXmlHttp=new ActiveXObject("Msxml2.XMLHTTP");
    }
    catch (e) {
      try {
	oXmlHttp=new ActiveXObject("Microsoft.XMLHTTP");
      }
      catch (e) {
	alert("Your browser does not support AJAX!");
	return false;
      }
    }
  }
  return oXmlHttp;
}

function ajaxCall(sPage, oDiv, sErrorMessage) {
  var oXmlHttp = getXMLHttpObject();

  oXmlHttp.onreadystatechange=function() {
    if(oXmlHttp.readyState == 4) {
      // If output was requested, print it. Otherwise nothings happens when the
      // AJAX call is ready
      if( oDiv )
      oDiv.innerHTML = ( oXmlHttp.responseText.length ) 
      ? oXmlHttp.responseText : sErrorMessage;
    }
  }
  oXmlHttp.open("GET", sPage, true);

  oXmlHttp.send(null);
}

// As the number of words can get pretty large we do this step by step (200
// by 200). This way the queries won't take too long and the rendering can 
// begin at the first 200 so the user can start right away while all kind of
// stuff is still happening in the background and at the bottom of the 
// the word list which is usually out of sight unless you scroll down really
// fast.
//
// If the two paramaters are set (i.e. not -1) we look for a row in
// neighbourhood of the iOldSelectedRow that has sOldSelectedRowTitle as its
// title.
// This is needed when the word form was changed.
function fillWordsToAttest(iOldSelectedRow, sOldSelectedRowTitle,
			   sNewWordForm) {
  // Reset the global variables. Whatever word was selected isn't anymore.
  iSelectedWordId = -1;
  iSelectedRow = -1;

  // Here we can set the step value (200).
  // If the the number of word forms per page is lower than 200 we set it to
  // that value.
  var iStepValue, bRecurse;
  if(iNrOfWordFormsPerPage == 'all') {
    iStartAt = 0; // Reset the global variable
    iStepValue = 200;
    bRecurse = true;
  }
  else {
    iStepValue = parseInt(iNrOfWordFormsPerPage);
    bRecurse = false;
  }

  // Start the recursion
  fillWordsToAttest_(iStartAt, iStepValue, bRecurse,
		     iOldSelectedRow, sOldSelectedRowTitle, sNewWordForm);
}

// The real function
//
// NOTE, if we do things recursively we show a progress bar.
// Otherwise we just show 'Loading...'.
//
function fillWordsToAttest_(iNewStartAt, iStepValue, bRecurse, iOldSelectedRow,
			    sOldSelectedRowTitle, sNewWordForm) {
  // Set the global variable
  iStartAt = iNewStartAt;

  // NOTE that nearly all variables are global
  var sPage = "./php/fillWordsToAttest.php?sDatabase=" + sDatabase + "&iId=" +
    iId + "&sMode=" + sMode + "&sSortBy=" + sSortBy + "&sSortMode=" +
    sSortMode + "&bSortReverse=" + bSortReverse + "&iUserId=" + iUserId +
	"&sGoto=" + encodeURIComponent(sGoto) +
    "&sFilter=" + encodeURIComponent(sFilter) +
    "&sLemmaFilter=" + encodeURIComponent(sLemmaFilter) +
    "&bCaseInsensitivity=" + bCaseInsensitivity +
    "&bDoShowAll=" + bDoShowAll + "&bDoShowCorpus=" + bDoShowCorpus +
    "&bDoShowDocument=" + bDoShowDocument + "&iStartAt=" + iNewStartAt +
    "&iStepValue=" + iStepValue + "&iNrOfWordFormsPerPage=" +
    iNrOfWordFormsPerPage + "&" + uniqueString();
  var oDiv = document.getElementById('wordsToAttest');
  var sErrorMessage = "Error in getting words to attest.";

  // Custom AJAX bit
  var oXmlHttp = getXMLHttpObject();
  
  var oProgressBar = document.getElementById('progressBar');
  // Make visible so the AJAX messages can be shown as well
  oProgressBar.style.visibility = 'visible';

  var bSelectedRow = false;

  oXmlHttp.onreadystatechange=function() {
    // We show all intermediate stages because the entire request can take
    // quite some time
    if(oXmlHttp.readyState == 0 ) {
      if( ! bRecurse ) {
	oProgressBar.innerHTML ="The request is not initialized";
      }
    }
    if(oXmlHttp.readyState == 1 ) {
      if( ! bRecurse ) {
	oProgressBar.innerHTML = "Loading...&nbsp;&nbsp;&nbsp;" +
	"<img src='./img/circle-ball-dark-antialiased.gif'>";
      }
    }
    if(oXmlHttp.readyState == 2 ) {
      if( ! bRecurse ) {
	oProgressBar.innerHTML ="The request has been sent.";
      }
    }
    if(oXmlHttp.readyState == 3 ) {
      if( ! bRecurse ) {
	oProgressBar.innerHTML ="The request is in progress.";
      }
    }
    if(oXmlHttp.readyState == 4) {
      var sEmptyPageLinkTable =
      '<table width=100% border=0 cellpadding=0 cellspacing=0>' +
      '<tr><td id=leftOfPageLinks class=nextToPageLinks></td>' +
      '<td></td>' + // Empty page links
      '<td id=rightOfPageLinks class=nextToPageLinks></td></tr></table>';
      
      var iIndexOfFirstNewline = -1;
      var iTotalNrOfWords = -1;
      if( oXmlHttp.responseText.length ) {
	iIndexOfFirstNewline = oXmlHttp.responseText.indexOf("\n");
	iTotalNrOfWords = oXmlHttp.responseText.substr(0, iIndexOfFirstNewline);
	// The first line of the response is the total nr of words
	// The second line is the lemma id of the lemma filter, in case
	// there is one (empty string otherwise)
	// The rest is the table with the words
	var iIndexOfSecondNewLine =
	  oXmlHttp.responseText.indexOf("\n", iIndexOfFirstNewline + 1);

	var sLemmaId =
	  oXmlHttp.responseText.substr(iIndexOfFirstNewline + 1,
				       iIndexOfSecondNewLine -
				       iIndexOfFirstNewline - 1);
	if( sLemmaId.length ) {
	  var sScript;
	  if( sLemmaId.substr(0,1) == 'm')
	    sScript = "alert('Edit lemma doesn\\'t work yet " +
	      "with multiple lemma analyses');";
	  else
	    sScript = "fillEditLemmaDiv(" + sLemmaId.substr(1) + ");";
	  document.getElementById('editLemma').innerHTML =
	    "<a href=\"javascript:" + sScript + "\" >" +
	    "<img src=\"./img/editLemma.png\" border=0></a>";
	}
	else 
	  document.getElementById('editLemma').innerHTML =
	    "<img src='./img/16x16.png'>";
      }

      if( (! oXmlHttp.responseText.length) || (iTotalNrOfWords == 0) ) {
	// We are done 
	oProgressBar.innerHTML = 'Done';
	// So it can go invisible again
	oProgressBar.style.visibility = 'hidden';
	
	if( (iOldSelectedRow != -1 ) && bRecurse && (! bSelectedRow) &&
	    (! selectNewRow(0, sNewWordForm)) )
	  clearSentences();
	
	// If there is no return value while we are not in recursion, that
	// means that there are no words at all/answering the filter/whatever
	if( ! bRecurse) {
	  oDiv.innerHTML = '';
	  clearSentences();
	}

	// If there just was no result.
	if(document.getElementById('pageLinks').innerHTML.length == 0) {
	  document.getElementById('pageLinks').innerHTML = sEmptyPageLinkTable;
	}
      }
      else {
	if( oXmlHttp.responseText.indexOf("<b>ERROR</b>") == 0 ) {
	  oProgressBar.innerHTML = oXmlHttp.responseText;
	}
	else {
	  if( (iNewStartAt == 0) || (! bRecurse) ) { // First time or page view
	    oDiv.innerHTML = 
	      oXmlHttp.responseText.substr(iIndexOfSecondNewLine+1);
	    oDiv.scrollTop = 0;

	    // Highlight the right arrow button
	    // NOTE that the previous button was already lowlighted when the
	    // current button was clicked in sortWordsToAttest()
	    var sReverse = (sSortBy == 'wordForm') ?
	      (bSortReverse) ? 'rl_': 'lr_' : '';
	    var oSortButton =
	      document.getElementById('sort_' + sSortBy + '_' + sReverse +
				      sSortMode).className = 'sortArrow_'
	    
	    if( ! bRecurse ) {
	      var sPageLinks = sClass = '';
	      var iNr = 0;
	      var iNrOfSteps = (iTotalNrOfWords/iStepValue);
	      if( iNrOfSteps > 1) {
		// The current page without the iNewStartAt
		var sCorpusOrDoc = (sMode == 'corpus') ?
		  "&iCorpusId=" + iId + "&sCorpusName=" + sName :
		  "&iFileId=" + iId + "&sFileName=" + sName;
		var sCurrentPage =
		  "./lexiconTool.php?sDatabase=" + sDatabase + sCorpusOrDoc +
		  "&sUserName=" + sUserName + "&iUserId=" + iUserId +
		  "&sMode=" + sMode + "&sSortBy=" + sSortBy +
		  "&sSortMode=" + sSortMode + "&bSortReverse=" + bSortReverse +
		  "&iUserId=" + iUserId +
		  "&sGoto=" + encodeURIComponent(sGoto) +
		  "&sFilter=" +
		  encodeURIComponent(sFilter).replace("'", "%27") +
		  "&sLemmaFilter=" +
		  encodeURIComponent(sLemmaFilter).replace("'", "%27") +
		  "&bCaseInsensitivity=" + bCaseInsensitivity + "&bDoShowAll="
		  + bDoShowAll + "&bDoShowCorpus=" + bDoShowCorpus +
		  "&bDoShowDocument=" + bDoShowDocument + "&iStepValue=" +
		  iStepValue + "&iNrOfWordFormsPerPage=" +
		  iNrOfWordFormsPerPage + "&iAmountOfContext=" +
		  iAmountOfContext + "&" + uniqueString();

		sPageLinks = "<center>" + 
		  "<table cellspacing=0 cellpadding=1>" +
		  "<tr>" +
		  // Previous page
		  "<td valign=top title='Go to the previous page' " +
		  " id=scrollJumpToPrev class=scrollerPrevNext " +
		  " onMouseOver=\"javascript:" +
		  " this.style.cursor = 'pointer';\" " +
		  "onClick=\"javascript: top.location.href = '" +
		  sCurrentPage +
		  "&sSortSentencesBy=' + sSortSentencesBy + " +
		  "'&sSortSentencesMode=' + sSortSentencesMode + " +
		  "'&iNrOfSentencesPerWordform=' + " +
		  "iNrOfSentencesPerWordform + '&iStartAt=" +
		  Math.max(0, iNewStartAt - iStepValue) + "';\">" +
		  "<img src='./img/bgScrollerPrev.png'></td>" +
		  // Jump to start of list
		  "<td valign=top title='Scroll to the start of the list' " +
		  " id=scrollJumpToBegin " +
		  "onMouseOver=\"javascript:" +
		  " this.style.cursor = 'pointer';\" " +
		  "onClick=\"javascript: " +
		  "scrollToNr(document.getElementById('scroller'), 0, " +
		  " 'scrollSpan_');\">" +
		  "<img src='./img/bgScrollerArrowLeft.png'>" +
		  "</td>" +
		  // The scroller div
		  "<td id=scrollerCol valign=bottom><div id=scroller "+ 
		  "onMouseOver='javascript: " + 
		  "document.onmouseover = startScrolling;'" +
		  "onMouseOut='javascript: " +
		  "document.onmouseover = stopScrolling;'>";
		var iMax = 0;
		for(var i = 0; i <= iNrOfSteps ; i++) {
		  iNr = iStepValue * i;
		  sClass = (iNr == iNewStartAt)
		    ? 'class=thisPage ' : 'class=somePage ';
		  sPageLinks += "<span id=scrollSpan_" + iNr + " " + sClass +
		    "onMouseOver=\"javascript:" +
		    " this.style.cursor = 'pointer';\" " +
		    "onClick=\"javascript: top.location.href = '" +
		    sCurrentPage +
		    "&sSortSentencesBy=' + sSortSentencesBy + " +
		    "'&sSortSentencesMode=' + sSortSentencesMode + " +
		    "'&iNrOfSentencesPerWordform=' + iNrOfSentencesPerWordform + " +
            "'&iAmountOfContext=' + iAmountOfContext + " +
		    "'&iStartAt=" + iNr +
		    "';\">" + (iNr+1) + "</span>&nbsp;";
		  iMax = iNr;
		}
		sPageLinks += "</div></td>" +
		  // Jump to end of list
		  "<td valign=top title='Scroll to the end of the list' " +
		  " id=scrollJumpToEnd " +
		  "onMouseOver=\"javascript:" +
		  " this.style.cursor = 'pointer';\" " +
		  "onClick=\"javascript: " +
		  "scrollToNr(document.getElementById('scroller'), " + iNr +
		  ", 'scrollSpan_');\">" +
		  "<img src='./img/bgScrollerArrowRight.png'>"+
		  "</td>" +
		  // Next page
		  "<td valign=top title='Go to the next page' " +
		  " id=scrollJumpToNext class=scrollerPrevNext " +
		  "onMouseOver=\"javascript:" +
		  " this.style.cursor = 'pointer';\" " +
		  "onClick=\"javascript: top.location.href = '" +
		  sCurrentPage +
		  "&sSortSentencesBy=' + sSortSentencesBy + " +
		  "'&sSortSentencesMode=' + sSortSentencesMode + " +
		  "'&iNrOfSentencesPerWordform=' + " +
		  "iNrOfSentencesPerWordform + '&iStartAt=" +
		  Math.min(iMax, iNewStartAt + iStepValue)
		  + "';\"><img src='./img/bgScrollerNext.png'></td>" +
		  "</tr></table>" +
		  "</center>";

		document.getElementById('pageLinks').innerHTML = 
		  '<table width=100% border=0 cellpadding=0 cellspacing=0>' +
		  '<tr><td id=leftOfPageLinks class=nextToPageLinks></td>' +
		  '<td>' + sPageLinks + '</td>' +
		  '<td id=rightOfPageLinks class=nextToPageLinks></td>' +
		  '</tr></table>';

		setScrollerBackground(iNr); // Set the background if necessary
		scrollToNr(document.getElementById('scroller'), iNewStartAt,
			   'scrollSpan_');
	      }
	      else { // Nr of steps is 1, so no page links
		document.getElementById('pageLinks').innerHTML =
		sEmptyPageLinkTable;
	      }
	    }
	    else { // Recursive, so no page links
	      document.getElementById('pageLinks').innerHTML =
		sEmptyPageLinkTable;
	    }
	  }
	  else { // Append
	    oDiv.innerHTML +=
	    oXmlHttp.responseText.substr(iIndexOfFirstNewline+1);
	  }

	  // Look for the row that was previously selected (this can be the case
	  // if a word form has been changed).
	  if( (iOldSelectedRow != -1) &&
	      (iOldSelectedRow >= iNewStartAt) &&
	      (iOldSelectedRow <= (iNewStartAt + iStepValue)) ) {
	    // Try to find the row selected
	    bSelectedRow = selectOldRow(iNewStartAt, iOldSelectedRow,
					sOldSelectedRowTitle);
	    
	    if( ! bSelectedRow ) { // If we couldn't find the old one
	      // Try finding the new one
	      if( ! selectNewRow(iNewStartAt, sNewWordForm) ) {
		console.log('Could not select new row: ' + iNewStartAt + 
			    "'" + sNewWordForm + "'");
		clearSentences();
	      }
	    }
	  }

	  var iProgress = Math.floor((iNewStartAt/iTotalNrOfWords)* 100);
	  // Now that we have everything, fill the progress bar
	  oProgressBar.innerHTML =
	  // Generate an image name
	  "<table width=100%><tr>" +
	  "<td align=center><img src='./img/ProgressBar_" +
	  // Every 5%
	  (iProgress - (iProgress % 5)) + ".png'></td></tr>" + 
	  // Display real percentage
	  "<tr><td align=right>" + iProgress + "%</td>" +
	  "</tr></table>\n";
 
	  // Recurse
	  if( bRecurse )
	    fillWordsToAttest_(iNewStartAt + iStepValue, iStepValue, bRecurse);
	  else {
	    oProgressBar.innerHTML = 'Done';
	    // We are done so it can go invisible again
	    oProgressBar.style.visibility = 'hidden';
	  }
	}
      }
    }
  }

  oXmlHttp.open("GET", sPage, true);

  oXmlHttp.send(null);
}

function fillAllTokenAttestations(iRowNr) {
  var iOriginalStartRow = iRowNr;
  var oWordRow = document.getElementById('wordRow_' + iRowNr);
  var aTitle;
  var oSentences = document.getElementById('sentences');
  var sWordFormIds = '';
  var sSeparator = '';
  while( oWordRow ) {
    // NOTE that the title is wordformId<TAB>wordform
    aTitle = oWordRow.title.split("\t");

    sWordFormIds += sSeparator + aTitle[0];
    sSeparator = ",";

    // Update every so many
    // We continue only after we have completed this
    if( (iRowNr % 1000) == 0 ) {
      ajaxCall_fillTokenAttestations(sWordFormIds, iOriginalStartRow,
				     iRowNr + 1, 'going');
      sWordFormIds = '';
      oWordRow = false; // Stop condition
    }
    else { // New row
      iRowNr++;
      oWordRow = document.getElementById('wordRow_' + iRowNr);
    }
  }
  if( sWordFormIds.length ) // Last ones
    ajaxCall_fillTokenAttestations(sWordFormIds, iOriginalStartRow,
				   iRowNr + 1, 'done');
}

function corpusAttestations(iRowNr, iWordFormId, sTuple) {
  var aAttestations = sTuple.split('|');

  var sAttestations = '';
  var sSeparator = '';
  for(var i = 0; i < aAttestations.length; i++) {
    var aAttestation = aAttestations[i].split(',');
    sAttestations += sSeparator + aAttestation[1] + ", " + aAttestation[2];
    if( aAttestation[3]) // Gloss
      sAttestations += ", " + aAttestation[3];
    sSeparator = " | ";
  }
  return sAttestations;
}

// NOTE that we make clickable items of the attestations, so the user can
// select some rows in the bottom part of the screen and attest them all by
// clicking one of these.
function dbAttestations(iWordFormId, sTuple, iRowNr) {
  var aAttestations = sTuple.split('|');

  var sAttestations = '';
  var sSeparator = '';
  for(var i = 0; i < aAttestations.length; i++) {
    var aAttestation = aAttestations[i].split(',');
    var sPrintTuple = aAttestation[1] + ", " + aAttestation[2];
    if( aAttestation[3]) // Gloss
      sPrintTuple += ", " + aAttestation[3];

    sAttestations += sSeparator + "<span class=clickableTokenAtt " +
      "title='Attest all selected rows below as \"" + sPrintTuple + "\"'" +
      "onMouseOver=\"javascript: this.style.cursor = 'pointer';" +
      " this.className = 'clickableTokenAtt_';\" " +
      "onMouseOut=\"javascript: this.className = 'clickableTokenAtt';\" " +
      "onClick=\"javascript: updateAnalysesOfSelectedRows(" + iWordFormId + ", " +
      aAttestation[0] + ", " + iRowNr + ")\">" + sPrintTuple + "</span>";

    sSeparator = " | ";
  }
  return sAttestations;
}

function fillNewCorpus(iUserId, sUserName) {
  ajaxCall("./php/fillNewCorpus.php?sDatabase=" + sDatabase + "&iUserId=" +
	   iUserId + "&sUserName=" + sUserName + "&" + uniqueString(),
	   document.getElementById('newCorpus'),
	   "Couldn't find new corpus code\n");
}

function dontShow(sDontShowMode, iDontShowId, iRowNr, iWordFormId) {
  var oRow = document.getElementById('wordRow_' + iRowNr);
  // Find out if we have to show/hide the word and the row
  var oDontShowCorpDoc = document.getElementById('dontShowCorpDoc_' + iRowNr);
  var oDontShowAtAll = document.getElementById('dontShowAtAll_' + iRowNr);
  var bShow = 0;
  var sNewRowClass = 'wordRow_hidden_' + (iRowNr % 2);
  var sNewButtonClass = 'dontShow_';
  if( (sDontShowMode == 'corpus') || (sDontShowMode == 'file') ) {
    if(oDontShowCorpDoc.className == 'dontShow_') {
      bShow = 1;
      sNewButtonClass = 'dontShow';
      if(oDontShowAtAll.className == 'dontShow')
	sNewRowClass = 'wordRow_selected'; // Nothing hidden anymore
    }
  }
  else { // 'at_all'
    if(oDontShowAtAll.className == 'dontShow_') {
      bShow = 1;
      sNewButtonClass = 'dontShow';
      if(oDontShowCorpDoc.className == 'dontShow')
	sNewRowClass = 'wordRow_selected'; // Nothing hidden anymore
    }
  }

  sPage = "./php/dontShow.php?sDatabase=" + sDatabase + "&iUserId=" +
    iUserId + "&iDontShowId=" + iDontShowId + "&bShow=" + bShow +
    "&sDontShowMode=" + sDontShowMode + "&iRowNr=" + iRowNr +
    "&iWordFormId=" + iWordFormId + "&" + uniqueString();

  // Custom AJAX bit
  var oXmlHttp = getXMLHttpObject();

  oXmlHttp.onreadystatechange=function() {
    if(oXmlHttp.readyState == 4) {
      if( oXmlHttp.responseText.length ) {
		// Show the error
		critical_alert("Something went wrong in hiding this word form: " +
			  oXmlHttp.responseText);
      }
      else {
	oRow.className = sNewRowClass;
	if( (sDontShowMode == 'corpus') || (sDontShowMode == 'file') ) {
	  oDontShowCorpDoc.className = sNewButtonClass;
	}
	else { // at_all
	  oDontShowAtAll.className = sNewButtonClass;
	}
      }
    }
  }
  oXmlHttp.open("GET", sPage, true);

  oXmlHttp.send(null);
}

// End of AJAX functions //////////////////////////////////////////////////////

// This function is called if somebody typed in a filter
function applyFilter(sNewFilter) {
  alert("Applying filter");
  // We set the global variable holding the filter.
  // This way, fillWordsToAttest can use it as well, also if the user previously
  // applied a filter and then wants to sort the filtered set
  sFilter = sNewFilter;
  iStartAt = 0; // Reset the global variable
  fillWordsToAttest(-1, -1, -1);
  // Empty the lower part of the screen
  document.getElementById('sentences').innerHTML = '';
}

function addNewTokenAttestation(iDocumentId, iSentenceNr, iWordFormId,
				iStartPos, iEndPos, sValue) {
				
	// tell the user (s)he has to wait (make progressbar visible)
	var oProgressBar = document.getElementById('progressBar');
	oProgressBar.innerHTML = "Processing...&nbsp;&nbsp;&nbsp;" +
	"<img src='./img/circle-ball-dark-antialiased.gif'>";
	oProgressBar.style.visibility = 'visible';	
	document.body.style.cursor = "wait";
		
	
  // If some rows were selected we attest for all these rows including the one
  // that was clicked in (which isn't necessarily selected).
  // NOTE that this might result in a double entry in the sSelecteds list, in
  // case it *was* selected.
  var sSelecteds = iDocumentId + "," + iStartPos + "," + iEndPos;

  if( aSelected.length) {
    for( var i = 0; i < aSelected.length; i++ ) {
      // aSelected is an array of arrays. So aSelected[i] is an array:
      // 0: sentence number
      // 1: the title, which is documentId,startPos,endPos
      if(aSelected[i] )
	sSelecteds += '|' + aSelected[i][1];
    }
  }

  if(sValue.indexOf(',') != -1) { // If the input makes at least a bit sense
    sPage = "./php/addNewTokenAttestation.php?sDatabase=" + sDatabase +
      "&iUserId=" + iUserId + "&iWordFormId=" + iWordFormId + "&sSelecteds=" +
      sSelecteds + "&sLemmaTuple=" + encodeURIComponent(sValue) +
      "&" + uniqueString();

    // Custom AJAX bit
    var oXmlHttp = getXMLHttpObject();

    oXmlHttp.onreadystatechange=function() {
      if(oXmlHttp.readyState == 4) {
	var oWordRow = document.getElementById('wordRow_' + iSelectedRow);
	var aIdAndWf = oWordRow.title.split("\t");
	
	// Update the sentences part of the screen 
	
	// OLD WAY: update whole view, which is often slow (and makes modified analyses disappear in some sorting modes)
	//   fillSentences(iWordFormId, aIdAndWf[1], false); 	
	
	// NEW WAY: update only the selection and don't change sorting (as the update is per row)
	updateAnalysesOfSelectedRowsOnScreen(aSelected, iSentenceNr);
	updateCheckBoxes(aSelected, "checked");
		

	// Hide the menu with the currently available ones
	document.getElementById('tokenAttSuggestions').style.visibility =
	'hidden';
	// Hide the list of suggestions that appear as you type
	document.getElementById('textAttSuggestions').style.visibility =
	'hidden';
	
	// Update the token attestations for corpus in the upper part of the
	// screen
	updateTokenAttsForCorpus(iWordFormId, iSelectedRow);

	// And do the same for the token attestations in db
	ajaxCall("./php/updateTokenAttsForDb.php?sDatabase=" + sDatabase +
		 "&iUserId=" + iUserId +
		 "&sUserName=" + sUserName + "&iWordFormId=" + iWordFormId +
		 "&iRowNr=" + iSelectedRow + "&" + uniqueString(),
		 document.getElementById('tokenAttsInDb_' + iWordFormId),
		 "");
      }
    }

    oXmlHttp.open("GET", sPage, true);
    oXmlHttp.send(null);
  }
}

// This function is called when the contents of the input box need to be saved
function saveTextAttestations(iWordFormId, iRowNr, oPosInput) {
  var sSelected = '';

  // If some rows were selected we get those. Otherwise we take all tokens.
  // (So none == all)
  var cSeparator = '';
  if( aSelected.length ) {
    for( var i = 0; i < aSelected.length; i++ ) {
      if( aSelected[i] ) {
	// The title is documentId,startPos,endPos
	sSelected += cSeparator + aSelected[i][1];
	cSeparator = "|";
      }
    }
  }
  else { // None selected, which means we take all
    /// Dit is eigenlijk niet echt lekker want dat zou weleens een lange string
    /// kunnen worden. Ook als POST variabele...
    var i = 0;
    var oWordForm = document.getElementById('sentence_' + i);
    while( oWordForm ) {
      sSelected += cSeparator + oWordForm.title;
      cSeparator = "|";
      i++;
      oWordForm = document.getElementById('sentence_' + i);
    }
  }

  // The oPosInput can contain &nbsp; which end up here looking as a space
  // while they are actually \xA0 characters, that lemmaTupleString2array
  // can't handle
  var sPage = "./php/saveTokenAttestations.php";
  var sParams = "sDatabase=" + sDatabase +
    "&iUserId=" + iUserId + '&iId=' + iId + "&sMode=" + sMode +
    '&iWordFormId=' + iWordFormId + "&sSelected=" + sSelected + "&sValue=" +
    encodeURIComponent(oPosInput.value) + "&iRowNr=" + iRowNr +
    // We use the length of aSelected array to see if we have to verify or
    // not.
    "&bVerify=" + aSelected.length + "&" + uniqueString();
  var oDiv = oPosInput.offsetParent;
  var sErrorMessage =
    "<b>ERROR</b>: Saving token attestations didn't work out somehow";
  
  // Custom AJAX bit
  var oXmlHttp = getXMLHttpObject();
  
  oXmlHttp.onreadystatechange=function() {
    if(oXmlHttp.readyState == 4) {
      // Check if the response is as expected.
      if(oXmlHttp.responseText.length) {
	oDiv.innerHTML = oXmlHttp.responseText;
      }
      else {
	goToNextRow(iRowNr, iWordFormId);
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

function goToNextRow(iRowNr, iWordFormId) {
  // The returned line contains the token attestations per sentence
  // We want to know this if the sentences are still being displayed
  // (i.e. the user hasn't clicked on another row).
  if(iSelectedRow == iRowNr) {
    var oWordRow = document.getElementById('wordRow_' + iRowNr);
    var aIdAndWf = oWordRow.title.split("\t");
    
    fillSentences(iWordFormId, aIdAndWf[1], true);
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

// Check when the word was last viewed.
// If that was rather recent, throw a pop-up to warn the user
//
function getLastView(iWordFormId) {
  var sPage = "./php/getLastView.php?sDatabase=" + sDatabase +
    "&iWordFormId=" + iWordFormId + "&iUserId=" + iUserId +
    "&" + uniqueString();

  // Custom AJAX bit
  var oXmlHttp = getXMLHttpObject();

  oXmlHttp.onreadystatechange=function() {
    if(oXmlHttp.readyState == 4) {
      var oLastViewed = document.getElementById('lastViewed');
      if( oXmlHttp.responseText.length ) {
	if( oXmlHttp.responseText.indexOf("<b>ERROR</b>") == 0 )
	  critical_alert(oXmlHttp.responseText); // if an error occured
	else {
	  // This comes as userId<TAB>userName<TAB>lastViewDate
	  var aLastViewed = oXmlHttp.responseText.split("\t");
	  var sName = (aLastViewed[0] == iUserId) ? 'you' : aLastViewed[1];
	  oLastViewed.innerHTML = "This word was viewed last by " + sName +
	    " at " + aLastViewed[2];	  
	  if( aLastViewed[0] != iUserId ) {
	    if(aLastViewed[4] == 1)
	      oLastViewed.className = 'lastViewed_';
	    else
	      oLastViewed.className = '';
	    if(aLastViewed[3] == 1)
	      alert("ALERT!!! User " + sName + " has also viewed this word " +
		    "quite recently. Be aware that simultaneous edits might " +
		    "cause conflicts...");
	  }
	  else
	    oLastViewed.className = '';
	}
      }
      else {
	oLastViewed.innerHTML = '';
	oLastViewed.className = '';
      }
    }
  }
  oXmlHttp.open("GET", sPage, true);

  oXmlHttp.send(null);

}

// We look in the neighbourhood of the old row. We do minus ten just as a
// gamble, so it can be that we should look even further back, though this is
// highly unlikely.
// The reason we look ik the neighbourhood of the old row is that word forms
// might have been added or deleted in front of it. 
function selectOldRow(iNewStartAt, iOldSelectedRow, sOldSelectedRowTitle) {
  var iRowsLookedAt = 0;
  var i = Math.max(iNewStartAt, iOldSelectedRow -10);
  var oWordRow = document.getElementById('wordRow_' + i);

  while( oWordRow && (iRowsLookedAt < 20)) {
    if(oWordRow.title == sOldSelectedRowTitle) {
      selectTextAttestationRow(i);
      return true;
    }
    else {
      i++;
      iRowsLookedAt++;
      oWordRow = document.getElementById('wordRow_' + i);
    }
  }

  return false;
}

// Sadly, we don't know anything about the new word form row, except the new
// word form. So, we have to look at every row on the screen.
function selectNewRow(iNewStartAt, sNewWordForm) {
  var i = iNewStartAt;
  var oWordRow = document.getElementById('wordRow_' + i);

  // If we extract the word forms from the titles below, they come URL encoded.
  // The sNewWordForm however is not encoded. So, because it is quicker to
  // encode that just once (rather than decoding the word forms from the titles)
  // we encodeURIComponent() it here.
  var sEncodedNewWordForm = encodeURIComponent(sNewWordForm);

  while( oWordRow ) {
    if(oWordRow.title.substr(oWordRow.title.indexOf("\t") + 1) == 
       // sNewWordForm
       sEncodedNewWordForm
       ) {
      selectTextAttestationRow(i);
      return true;
    }
    else {
      i++;
      oWordRow = document.getElementById('wordRow_' + i);
    }
  }
  
  return false;
}

function selectTextAttestationRow(iRowNr) {
  // Reset global variable
  iStartAtSentence = 0;

  // This is just a detail to have a clean screen at start-up
  // These buttons only appear once you start working.
  document.getElementById('sortingButtons').style.visibility = 'visible';

  hideMenus(); // Could be open while someone hits 'Enter'

  // Try to get the new row
  var oWordRow = document.getElementById('wordRow_' + iRowNr);
  if( oWordRow ) { // If we found the new row
    aSelected = []; // No selection anymore
    bStartRowWasSelected = false;
    bStartRowWasSolelySelected = false;

    /// EVEN WEG omdat de kolom niet meer editable wordt ///
    // Make the attestations in corpus column un-editable again 
    if( (iSelectedRow != -1) && (iSelectedRow != iRowNr) ) {
      var iPreviousWordFormId = 
 	document.getElementById('wordRow_'+ iSelectedRow).title.split("\t")[0]; 
      
      document.getElementById('tokenAttsInCorpus_' + 
 			      iPreviousWordFormId).innerHTML =
 	sPosInputBeforeEditing; 
      sPosInputBeforeEditing = ''; 
    }
    ///

    // Deselect the currently selected row if it isn't hidden
    if( iSelectedRow != -1 ) {
      var oCurSel = document.getElementById('wordRow_' + iSelectedRow);
      if( oCurSel && ( (oCurSel.className != 'wordRow_hidden_0') &&
		       (oCurSel.className != 'wordRow_hidden_1')) )
	oCurSel.className = 'wordRow_' + (iSelectedRow % 2);
    }
    if( (oWordRow.className != 'wordRow_hidden_0') &&
	(oWordRow.className != 'wordRow_hidden_1') )
      oWordRow.className = 'wordRow_selected';
    var aIdAndWf = oWordRow.title.split("\t");
    
    iSelectedRow = iRowNr; // Make the new one the current one
	
    iSelectedWordId = aIdAndWf[0];
    iSelectionStart = -1;
    iSelectionEnd = -1;

    makePosColEditable(aIdAndWf[0], 'tokenAttsInCorpus_' + aIdAndWf[0],
		       iRowNr);

    getLastView(aIdAndWf[0]);
    fillSentences(aIdAndWf[0], aIdAndWf[1], true);
  }
}


function onNrOfSentencesPerWordformChange(iNewNrOfSentencesPerWordform) {
  iNrOfSentencesPerWordform = iNewNrOfSentencesPerWordform;
  iStartAtSentence = 0; // Reset

  if( iSelectedRow != -1) {
    var oWordRow = document.getElementById('wordRow_' + iSelectedRow);
    var aIdAndWf = oWordRow.title.split("\t");

    fillSentences(aIdAndWf[0], aIdAndWf[1], true);
  }
}

function clearSentences() {
  // Sentences themselves
  document.getElementById('sentences').innerHTML = '';
  // The sorting buttons
  document.getElementById('sortingButtons').style.visibility = 'hidden';
  // The sentence links
  document.getElementById('sentenceLinks').innerHTML = '';
}

// This one fills the sentences div in the lower part of the screen
function fillSentences(iWordFormId, sWordForm, bResetScrollTop) {
  // If sentences were selected, we want to keep them selected after the reload
  // (NOTE that fillSentences() is also called when we reload sentences, so
  // checking for selected sentences always makes sense. If another word row is
  // clicked on, selectTextAttestationRow is called, which fills the sentences
  // by itself (by calling this function)).
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
  
  // Get the sentences with this word
  var sPage = "./php/fillSentences.php?sDatabase=" + sDatabase +
    "&iId=" + iId + "&sMode=" + sMode + "&iWordFormId=" + iWordFormId +
    "&sWordForm=" + encodeURIComponent(sWordForm) + "&iUserId=" + iUserId +
    "&sSelectedSentences=" + sSelectedSentences +
    "&iNrOfSentencesPerWordform=" + iNrOfSentencesPerWordform +
    "&iStartAtSentence="+ iStartAtSentence +
    "&iAmountOfContext=" + iAmountOfContext +
    "&sSortSentencesBy=" + sSortSentencesBy +
    "&sSortSentencesMode=" + sSortSentencesMode + "&" + uniqueString();
  var oDiv = document.getElementById('sentences');
  var sErrorMessage = "No sentences found for '" + sWordForm + "'";

  // Reset, or keep it scrolled to where it was.
  var iNewScrollTop = (bResetScrollTop) ? 0 : oDiv.scrollTop;

  // Custom AJAX bit
  var oXmlHttp = getXMLHttpObject();

  oXmlHttp.onreadystatechange=function() {
    if(oXmlHttp.readyState == 0 ) {
      oDiv.innerHTML ="The request is not initialized";
    }
    if(oXmlHttp.readyState == 1) {
      oDiv.innerHTML = "Loading...<p>" +
      "<img src='./img/circle-ball-dark-antialiased.gif'>";
    }
    if( oXmlHttp.readyState == 2) {
      oDiv.innerHTML = "The request has been sent";
    }
    if( oXmlHttp.readyState == 3) {
      oDiv.innerHTML = "The request is in process<p>" +
      "<img src='./img/circle-ball-dark-antialiased.gif'>";
    }
    if(oXmlHttp.readyState == 4) {
      if( oXmlHttp.responseText.length ) {
	oDiv.innerHTML = oXmlHttp.responseText;

	// Reset, or keep it scrolled to where it was.
	oDiv.scrollTop = iNewScrollTop;
	// As pointed out above, the order of sentences is very likely to have
	// been changed, so keeping the menu where it was doesn't really make
	// sense
	if( sSortSentencesBy == 'lemma') {
	  document.getElementById('tokenAttSuggestions').style.visibility =
	    'hidden';
	}	
	oDoneDiv = document.getElementById('done_' + iWordFormId);

	var sSentenceLinks = sClass = '';
	var iNr = 0;
	var iNrOfSteps = (oDoneDiv.title/iNrOfSentencesPerWordform);
	var oSentenceLinks = document.getElementById('sentenceLinks');
	if( iNrOfSteps > 1) {
	  
	  sSentenceLinks = "<center>" + 
	    "<table cellspacing=0 cellpadding=1 border=0>" +
	    "<tr>"+
	    // Prev page
	    "<td valign=top title='Go to the previous page' " +
	    " id=sentenceScrollJumpToPrev class=scrollerPrevNext " +
	    "onMouseOver=\"javascript:" +
	    " this.style.cursor = 'pointer';\" " +
	    "onClick=\"javascript: " +
	    "iStartAtSentence = " +
	    Math.max(0, (iStartAtSentence - iNrOfSentencesPerWordform)) + "; " +
	    "aSelected = []; " +
	    "fillSentences(" + iWordFormId + ", '" + sWordForm +
	    "', true);\"><img src='./img/bgScrollerPrev.png'></td>" +
	    // Jump to start of list
	    "<td valign=top title='Jump to the start of the list' " +
	    " id=sentenceScrollJumpToBegin " +
	    "onMouseOver=\"javascript:" +
	    " this.style.cursor = 'pointer';\" " +
	    "onClick=\"javascript: " +
	    "scrollToNr(document.getElementById('sentenceScroller'), 0, " +
	    " 'scrollSentenceSpan_');\">" +
	    "<img src='./img/bgScrollerArrowLeft.png'>"+
	    "</td>" +
	    // Scroller div
	    "<td id=sentenceScrollerCol valign=bottom>" +
	    "<div id=sentenceScroller " +
	    "onMouseOver='javascript: " +
	    "document.onmouseover = startSentenceScrolling;'" +
	    "onMouseOut='javascript: " +
	    "document.onmouseover = stopScrolling;'>";
	  var iMax;
	  for(var i = 0; i <= iNrOfSteps ; i++) {
	    iNr = iNrOfSentencesPerWordform * i;
	    sClass = (iNr == iStartAtSentence)
	      ? 'class=thisPage ' : 'class=somePage ';
	    sSentenceLinks +=
	      "<span id=scrollSentenceSpan_" + iNr + " " + sClass +
	      "onMouseOver=\"javascript:" +
	      " this.style.cursor = 'pointer';\" " +
	      "onClick=\"javascript: stopScrolling();" +
	      "iStartAtSentence = " + iNr + "; " +
	      "aSelected = []; " +
	      "fillSentences(" + iWordFormId +
	      ", '" + sWordForm + "', true);\">" + (iNr+1) +
	      "</span>&nbsp;";
	    iMax = iNr;
	  }
	  sSentenceLinks += "</div></td>" +
	    // Jump to end of list
	    "<td valign=top title='Jump to the start of the list' " +
	    " id=sentenceScrollJumpToEnd " +
	    "onMouseOver=\"javascript:" +
	    " this.style.cursor = 'pointer';\" " +
	    "onClick=\"javascript: " +
	    "scrollToNr(document.getElementById('sentenceScroller'), " +
	    iNr + ", 'scrollSentenceSpan_');\">" +
	    "<img src='./img/bgScrollerArrowRight.png'></td>" +
	    // Next page
	    "<td valign=top title='Go to the next page' " +
	    " id=sentenceScrollJumpToNext class=scrollerPrevNext " +
	    "onMouseOver=\"javascript:" +
	    " this.style.cursor = 'pointer';\" " +
	    "onClick=\"javascript: " +
	    "iStartAtSentence = " +
	    Math.min(iMax, (parseInt(iStartAtSentence) +
			    parseInt(iNrOfSentencesPerWordform)) ) +"; "
	    + "aSelected = []; " +
	    "fillSentences(" + iWordFormId + ", '" + sWordForm +
	    "', true);\"><img src='./img/bgScrollerNext.png'></td>" +
	    //
	    "</tr></table>" +
	    "</center>";
	
	  oSentenceLinks.innerHTML = sSentenceLinks;
	  

	  // Set the background if necessary
	  setSentenceScrollerBackground(iNr);
	  scrollToNr(document.getElementById('sentenceScroller'),
		     iStartAtSentence, 'scrollSentenceSpan_');
	}
	else
	oSentenceLinks.innerHTML = '';

	// As the selected sentences might have moved and/or disappeared
	// we calculate them again
	recalculateSelecteds();
      }
      else {
	oDiv.innerHTML = sErrorMessage;
      }
    }
  }
  ///
  /// alert("Page: " + sPage);
  ///
  oXmlHttp.open("GET", sPage, true);

  oXmlHttp.send(null);
}

// Check if all the selected sentences are still on the page.
// If they are, they get a (possibly) new sentence number.
// If not, they are removed form the selected sentences array.
// 
function recalculateSelecteds() {
  var aNewSelected = new Array();

  var oObj;
  ///var sStr = '';
  for( var i = 0; i < aSelected.length; i++ ) {
    // aSelected is an array of arrays. So aSelected[i] is an array:
    // 0: sentence number
    // 1: the title, which is documentId,startPos,endPos
    if(aSelected[i]) {
      oObj = document.getElementsByName(aSelected[i][1])[0];
      if( oObj) {
	var iSentenceNr = parseInt(oObj.id.substr(9));
	/// sStr += aSelected[i][1] + " sentence nr: " + iSentenceNr + ". ";
	aNewSelected.push([iSentenceNr, aSelected[i][1]]);
      }
      ///      else
      ///	sStr += aSelected[i][1] + " doesn't occur. ";
    }
  }
  // Replace the old one by the new one
  aSelected = aNewSelected;
}

function deleteAnalysis(iWordFormId, iAnalyzedWordFormId, iRowNr) {
  // To avoid errors we first wait for the sentences to have loaded completely
  if( ! document.getElementById('done_' + iWordFormId) )
    setTimeout("deleteAnalysis(" + iWordFormId + " , " +
	       iAnalyzedWordFormId + ", " + iRowNr + ")", 10);
  else {
    var sPage = "./php/deleteAnalysis.php?sDatabase=" + sDatabase +
      "&iWordFormId=" + iWordFormId + "&iAnalyzedWordFormId=" +
      iAnalyzedWordFormId + "&" + uniqueString();
  
    // Custom AJAX bit
    var oXmlHttp = getXMLHttpObject();

    oXmlHttp.onreadystatechange=function() {
      if(oXmlHttp.readyState == 4) {
	// Check if the response is as expected.
	if(oXmlHttp.responseText.length)
	  critical_alert(oXmlHttp.responseText); // if an error occured
	else {
	  var oWordRow = document.getElementById('wordRow_' + iRowNr);
	  var aIdAndWf = oWordRow.title.split("\t");
	  fillSentences(iWordFormId, aIdAndWf[1], false);
	
	  // Update the token attestations for corpus in the upper part of the
	  // screen
	  updateTokenAttsForCorpus(iWordFormId, iRowNr);
	
	  // And do the same for the token attestations in db
	  ajaxCall("./php/updateTokenAttsForDb.php?sDatabase=" + sDatabase +
		   "&iRowNr=" + iRowNr +
		   "&iWordFormId=" + iWordFormId + "&" + uniqueString(),
		   document.getElementById('tokenAttsInDb_' + iWordFormId),
		   "");
	}
      }
    }
    oXmlHttp.open("GET", sPage, true);

    oXmlHttp.send(null);
  }
}

function verifyAnalysis(oObj, iWordFormId, iAnalyzedWordFormId, iRowNr) {
  var bVerify = (oObj.style.fontWeight == 'bold') ? 0 : 1;
  var sPage = "./php/verifyAnalysis.php?sDatabase=" + sDatabase +
    "&iUserId=" + iUserId + "&iAnalyzedWordFormId=" +
    iAnalyzedWordFormId + "&bVerify=" + bVerify + "&" + uniqueString();
  
  // Custom AJAX bit
  var oXmlHttp = getXMLHttpObject();

  oXmlHttp.onreadystatechange=function() {
    if(oXmlHttp.readyState == 4) {
      // Check if the response is as expected.
      ///if(oXmlHttp.responseText.match(/error/i)) {
      if(oXmlHttp.responseText.length)
		critical_alert(oXmlHttp.responseText); // if an error occured
      else {
	var oWordRow = document.getElementById('wordRow_' + iRowNr);
	var aIdAndWf = oWordRow.title.split("\t");

	// Only update the relevant column
	ajaxCall("./php/updateTokenAttsForDb.php?sDatabase=" + sDatabase +
		 "&iRowNr=" + iRowNr +
		 "&iWordFormId=" + iWordFormId + "&" + uniqueString(),
		 document.getElementById('tokenAttsInDb_' + iWordFormId),
		 "");
      }
    }
  }

  oXmlHttp.open("GET", sPage, true);

  oXmlHttp.send(null);
}

// this is the size of a selection above which updating the whole screen view is 
// more efficient than updating just the selection.
// The outcome determine which update method will be used.
function selectionIsNotTooLarge(){

	// first clear selection from undefined elements
	var temp = [];
	for (var i=0; i < aSelected.length; i++) {
		if ( typeof aSelected[i] != 'undefined' ) {
			temp.push(aSelected[i])
		}
	}

	var iSelectionSize = temp.length;
	var threshold = .3 * iNrOfWordFormsPerPage; // percentage of whole view size to allow
	if (threshold < 15) threshold = 15;
		
	return iSelectionSize <= threshold;
}

// show critical alert and prevent the user from carrying on working, since database information integrity might be in danger.
function critical_alert(msg){
	
	while(true)
	{
	alert("### Some critical error has occured ###\n\n\nPlease copy the error information below and paste it into an e-mail to the developer. \n\nThis is very important to guarantee the integrity of your work! \n\nThank you.\n\n--------------------------------------------------------------------------------------\n\n"+msg);
	}
}

// tell the user (s)he has to wait (make progressbar visible)
function showProcessingMsg(msg, movingElement){

	// if no message was given, show default message
	if (msg == null) 
		msg = "Processing...&nbsp;&nbsp;&nbsp;";
	
	// should we show some moving graphics, to suggest "progression" of the process
	if (movingElement == null)
		movingElement = true;

	var oProgressBar = document.getElementById('progressBar');
	oProgressBar.innerHTML = msg +
	(movingElement ? "<img src='./img/circle-ball-dark-antialiased.gif'>" : "");
	oProgressBar.style.visibility = 'visible';
	
	// mouse pointer should show the user (s)he has to wait
	document.body.style.cursor = "wait";
}

// make progress bar invisible again
function removeProcessingMsg(){
	oProgressBar.style.visibility = 'hidden';
	
	// mouse pointer should show normal arrow again
	document.body.style.cursor = "default";
}
