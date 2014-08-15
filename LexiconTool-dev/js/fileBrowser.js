function fillFileBrowser(iUserId, sUserName, iCorpusAddedTo) {
  var sPage = "./php/fillFileBrowser.php?sDatabase=" + sDatabase +
    "&iUserId=" + iUserId + "&sUserName=" + sUserName + "&" + uniqueString();
  var oDiv = document.getElementById('fileBrowser');
  // Custom AJAX part
  var oXmlHttp = getXMLHttpObject();

  oXmlHttp.onreadystatechange=function() {
    if(oXmlHttp.readyState == 4) {
      if( oXmlHttp.responseText.length ) {
		oDiv.innerHTML = oXmlHttp.responseText;
		// If we just added a file to a corpus, keep that corpus extended
		if( iCorpusAddedTo ) {
		  toggleCorpusFiles(document.getElementById('corpus_'+ iCorpusAddedTo),
					iCorpusAddedTo);
		}
      }
      else {
		oDiv.innerHTML = 'No files in database yet';
      }
    }
  }
  oXmlHttp.open("GET", sPage, true);

  oXmlHttp.send(null);
}

function toggleNewCorpus() {
  var oNewCorpusInput = document.getElementById('newCorpusInput');

  if( oNewCorpusInput.style.display == 'inline')
    oNewCorpusInput.style.display = 'none';
  else {
    // Fill the innerHTML afresh (it might have been used to display some
    // status message about file/corpora having been removed/added
    oNewCorpusInput.innerHTML = "<input name=newCorpusName type=text size=25" +
      /// Deze check moet nog goed...
      " onChange=\"javascript: " +
      'if( checkCorpusName(this.value) ) ' +
      'newCorpus(this.value);  '+
      'else { alert(\'No valid corpus name\'); return false;}\">';
    oNewCorpusInput.style.display = 'inline';
  }
}

function checkCorpusName(sString) {
  if(sString.length &&
     sString.match(/[A-Za-z]/) &&
     (! sString.match(/['"]/)) ) // '])))// <-- Emacs syntax coloring
    return true;
  else
    return false;
}

function toggleCorpusFiles(oToggle, iCorpusId) {
  var oCorpusFiles = document.getElementById('corpusFiles_' + iCorpusId);

  if(oToggle.className == 'showCorpusFiles') { // Expand
    oToggle.className = 'showCorpusFiles_';
    ajaxCall("./php/showCorpusFiles.php?sDatabase=" + sDatabase +
	     "&iUserId=" + iUserId + "&sUserName=" + sUserName + "&iCorpusId="
	     + iCorpusId + "&" + uniqueString(),
	     oCorpusFiles,
	     "Couldn't show files...");
    oCorpusFiles.style.display = 'inline';
  }
  else { // Hide
    oToggle.className = 'showCorpusFiles';
    oCorpusFiles.innerHTML = '';
    oCorpusFiles.style.display = 'none';
  }
}

function toggleNewFileForm(iCorpusId) {
  var oNewFileForm = document.getElementById('newFileForm_' + iCorpusId);
  if( oNewFileForm.style.display == 'inline')
    oNewFileForm.style.display = 'none';
  else
    oNewFileForm.style.display = 'inline';
}

function showProgress(oProgressBar, sString) {
  if( ! oProgressBar) 
    oProgressBar = document.getElementById('progressBar');

  oProgressBar.innerHTML = sString + "...&nbsp;&nbsp;&nbsp;" +
    "<img src='./img/circle-ball-dark-antialiased.gif'>";
  // Make visible so the AJAX messages can be shown as well
  oProgressBar.style.visibility = 'visible';
}

function newCorpus(sNewCorpusName) {
  var oNewCorpusInput = document.getElementById('newCorpusInput');

  // The page prints the corpus id if everything goes as it should
  var sPage = "./php/newCorpus.php?sDatabase=" + sDatabase +
    "&sNewCorpusName=" + sNewCorpusName + "&" + uniqueString();

  // Custom AJAX part
  var oXmlHttp = getXMLHttpObject();

  oXmlHttp.onreadystatechange=function() {
    if(oXmlHttp.readyState == 4) {
      if( ! oXmlHttp.responseText.match(/^\d+$/) ) {
		if (oXmlHttp.responseText == '') 
			oNewCorpusInput.innerHTML = "ERROR: response is empty [no corpus id was returned]";
		else
			oNewCorpusInput.innerHTML = "ERROR: " + oXmlHttp.responseText;
      }
      else {
		oNewCorpusInput.innerHTML = "Added corpus '" + sNewCorpusName + "'";
		// Fill the browser again
		ajaxCall("./php/fillFileBrowser.php?sDatabase=" + sDatabase +
			 "&iUserId=" + iUserId + "&sUserName="+ sUserName +
			 "&iCorpusAddedTo=" + oXmlHttp.responseText +
			 "&" + uniqueString(),
			 document.getElementById('fileBrowser'),
			 "Couldn't fill the file browser");
      }
    }
  }
  // NOTE that we append a "unique string" at the end, to make sure that e.g.
  // IE will actually carry out AJAX calls more than once as well...
  oXmlHttp.open("GET", sPage, true);

  oXmlHttp.send(null);
}

function removeCorpus(iCorpusId, sCorpusName) {
  var oNewCorpusInput = document.getElementById('newCorpusInput');

  var sPage = "./php/removeCorpus.php?sDatabase=" + sDatabase +
    "&iCorpusId=" + iCorpusId + "&" + uniqueString();

  // Custom AJAX part
  var oXmlHttp = getXMLHttpObject();

  var oProgressBar = document.getElementById('progressBar');

  oXmlHttp.onreadystatechange=function() {
    if(oXmlHttp.readyState == 1 ) {
      showProgress(oProgressBar, "Deleting");
    }
    if(oXmlHttp.readyState == 2 ) {
      oProgressBar.innerHTML ="The request has been sent.";
    }
    if(oXmlHttp.readyState == 3 ) {
      oProgressBar.innerHTML ="The request is in progress.";
    }
    if(oXmlHttp.readyState == 4) {
      if( oXmlHttp.responseText.length ) {
	oNewCorpusInput.innerHTML = oXmlHttp.responseText;
      }
      else {
	oProgressBar.innerHTML = 'Done';
	// So it can go invisible again
	oProgressBar.style.visibility = 'hidden';

	oNewCorpusInput.innerHTML = "Removed corpus '" + sCorpusName + "'";
	// Fill the browser again
	ajaxCall("./php/fillFileBrowser.php?sDatabase=" + sDatabase +
		 "&iUserId=" + iUserId + "&sUserName="+ sUserName +
		 "&" + uniqueString(),
		 document.getElementById('fileBrowser'),
		 "Couldn't fill the file browser");
      }
      oNewCorpusInput.style.display = 'inline';
    }
  }
  // NOTE that we append a "unique string" at the end, to make sure that e.g.
  // IE will actually carry out AJAX calls more than once as well...
  oXmlHttp.open("GET", sPage, true);

  oXmlHttp.send(null);
}

function removeFileFromCorpus(iCorpusId, iDocumentId) {
  var oFileDiv = document.getElementById('corpusFile_' + iDocumentId);

  var sPage = "./php/removeFileFromCorpus.php?sDatabase=" + sDatabase +
    "&iCorpusId=" + iCorpusId + "&iDocumentId=" + iDocumentId + "&" +
    uniqueString();

  // Custom AJAX part
  var oXmlHttp = getXMLHttpObject();

  oXmlHttp.onreadystatechange=function() {
    if(oXmlHttp.readyState == 4)
    if( oXmlHttp.responseText.length )
    oFileDiv.innerHTML = "ERROR: " + oXmlHttp.responseText;
    else // Fill the browser again
    fillFileBrowser(iUserId, sUserName, iCorpusId);
  }
  oXmlHttp.open("GET", sPage, true);

  oXmlHttp.send(null); 
}
