<?php

require_once('databaseUtils.php');

require_once('pclzip.lib.php');

require_once('alignment/alignTokens.php');

/***** Function for the main file (lexiconTool.php ***************************/

function printTitle($sDatabase, $sUserName, $sMode, $sFileOrCorpusName) {
  print "<title>CoBaLT - IMPACT Corpus-Based Lexicon Tool";

  if($sUserName) {
    print " - User $sUserName working ";
    if( $sMode) {
      // Chop off the document root which isn't very informative as it is
      // always the same
      if( $sMode == 'file')
		$sFileOrCorpusName = substr($sFileOrCorpusName, strlen($GLOBALS['sDocumentRoot']) + 1);
      print "on $sMode '$sFileOrCorpusName' (";
    }
    else
      print "on ";
    print "database '$sDatabase'";
    if($sMode)
      print ")";
    print "</title>\n</head>";
  }
  else
    print "</title>\n";
}

/***** Functions for middle part *********************************************/

function fillWordsToAttest($iId, $sMode, $sSortBy, $sSortMode, $bSortReverse,
			   $sFilter, $bCaseInsensitivity, $sLemmaFilter,
			   $bDoShowAll, $bDoShowCorpus, $bDoShowDocument,
			   $iStartAt, $iStepValue, $iNrOfWordFormsPerPage,
			   $iUserId) {
  $aTypes;
  $iDocumentId;
  $bPrintedSomething = FALSE;
  
  list($oResult, $iLemmaFilter_lemmaId, $sLemmaFilterWordformIds) =
    getWordsToAttest($iId, $sMode, $sSortBy, $sSortMode, $bSortReverse,
		     $sFilter, $bCaseInsensitivity, $sLemmaFilter, $bDoShowAll,
		     $bDoShowCorpus, $bDoShowDocument, $iStartAt, $iStepValue,
		     $iNrOfWordFormsPerPage);

  // NOTE that every batch is in a separate table. This is because
  // Firefox adds a </table> closing tag itself if it isn't there on returning.
  // If it wouldn't the HTML is incomplete/incorrect which makes rendering
  // hard.
  // This can however result in (small) differences in column widths every
  // 1000 or so rows...
  if( $oResult ) {
    $i = $iStartAt;
    while( ($aRow = mysql_fetch_assoc($oResult)) ) {
      if( ! $bPrintedSomething ) {
	// NOTE that we first print the total number of results (to be able to
	// tell the progress).
	// This is chopped off again in Javascript...
	printTotalNrOfWords($iId, $sMode, $sFilter, $sLemmaFilterWordformIds,
			    $bDoShowAll, $bDoShowCorpus, $bDoShowDocument,
			    $bCaseInsensitivity);

	/// NEW (04 march 2011)
	// Next we also print the lemma id of the lemma filter (which is an
	// empty string if the lemma doesn't exist, or there is no filter)
	// This is chopped off again in Javascript.
	print "$iLemmaFilter_lemmaId\n";
	///

	// Then table that holds the words to be attested
	print "<table width=100% border=0 cellspacing=0>\n";
	$bPrintedSomething = TRUE;
      }
      $sWordForm = $aRow['wordform']; 
      // NOTE that the title is wordformId<TAB>wordform
      print "<tr id=wordRow_$i title='" . $aRow['wordform_id'] . "\t" .
	urlencode($sWordForm) .	"' ";

      // See if the row maybe was hidden.
      $bRowIsHidden = rowIsHidden($aRow, $sMode, $iId, $bDoShowAll,
				  $bDoShowCorpus, $bDoShowDocument);
      if( $bRowIsHidden )
	print "class=wordRow_hidden_" . ($i % 2) . " ";
      else 
	print "class=wordRow_" . ($i % 2) . " ";
      print
	"onClick=\"javascript: if( iSelectedWordId != " .
	$aRow['wordform_id'] . ") selectTextAttestationRow($i);\">\n" .
	// Don't show icons
	"<td class=dontShowCol>";
      if( $sMode == 'corpus') {
	print
	  "<div onMouseOver=\"javascript: this.style.cursor = 'pointer';\" ".
	  "id=dontShowCorpDoc_$i class=dontShow";
	// It is actually hidden for the corpus
	if( $bRowIsHidden && $aRow['corpus_id'] )
	  print "_ " .
	    "title=\"Do show this word again for this corpus\" " .
	    "class=dontShowLink" .
	    " onClick=\"javascript:" .
	    " dontShow('corpus', $iId, $i, " . $aRow['wordform_id'] .
	    ");\">c</div>";
	else // We do have to show it
	  print " " .
	    "title=\"Don't show this word again for this corpus\" " .
	    "class=dontShowLink " .
	    "onClick=\"javascript:" .
	    " dontShow('corpus', $iId, $i, " . $aRow['wordform_id'] .
	    ");\">c</div>";
      }
      else { // Document mode
	print
	  "<div onMouseOver=\"javascript: this.style.cursor = 'pointer';\" " .
	  "id=dontShowCorpDoc_$i class=dontShow";
	
	if( $bRowIsHidden && $aRow['document_id'] )
	  print "_ " .
	    "title=\"Do show this word again for this document\" " .
	    "class=dontShowLink " .
	    "onClick=\"javascript:" .
	    " dontShow('file', $iId, $i, " . $aRow['wordform_id'] .
	    ");\">d</div>";
	else
	  print " " .
	    "title=\"Don't show this word again for this document\" " .
	    "class=dontShowLink " .
	    "onClick=\"javascript:" .
	    " dontShow('file', $iId, $i, " . $aRow['wordform_id'] .
	    ");\">d</a></div>"; 
      }
      print
	"<div onMouseOver=\"javascript: this.style.cursor = 'pointer';\" " .
	"id=dontShowAtAll_$i class=dontShow";
      
      if( $bRowIsHidden && $aRow['at_all'] )
	print "_ " .
	  "title=\"Show this word again\" class=dontShowLink " .
	  "onClick=\"javascript:" .
	  " dontShow('at_all', 1, $i, " . $aRow['wordform_id'].
	  ");\">a</div>";
      else
	print " " .
	  "title=\"Don't ever show this word again\" class=dontShowLink " .
	  "onClick=\"javascript:" .
	  " dontShow('at_all', 1, $i, " . $aRow['wordform_id'].
	  ");\">a</div>";
      print "</td>\n" .
	// the word form itself
	"<td class=wordCol>$sWordForm</td>\n" .
	// Frequency
	"<td class=freqCol>" . $aRow['frequency'] . "</td>\n" .
	// Token attestations in this corpus
	"<td id=tokenAttsInCorpus_" . $aRow['wordform_id'] .
	" title='$iId\t$sMode\t" . $aRow['wordform_id'] . "\t$i' " .
	"class=tokenAttsInCorpus>" . $aRow['analysesInCorpus'];
      if($aRow['analysesInCorpus'] && $aRow['multipleLemmataAnalysesInCorpus'])
	print " | ";
      print $aRow['multipleLemmataAnalysesInCorpus'];
      print "</td>\n" .
	// Analyses in database
	"<td id=tokenAttsInDb_" . $aRow['wordform_id'] .
	" class=tokenAttsInDb>";
      printClickableTokens($aRow['wordform_id'], $aRow['analysesInDb'], $i);
      if( $aRow['analysesInDb'] && $aRow['multipleLemmataAnalysesInDb'])
	print " | ";
      printClickableTokens($aRow['wordform_id'],
			   $aRow['multipleLemmataAnalysesInDb'], $i);
      print "</td>\n\n</tr>\n";
      $i++;
    }

    if( $bPrintedSomething)
      print "</table>";
    mysql_free_result($oResult);
  }
  else { // If there wer no results, but there was a relavant lemma filter,
    // print it
    print "0\n$iLemmaFilter_lemmaId\n";
  }
}

function rowIsHidden($aRow, $sMode, $iId, $bDoShowAll, $bDoShowCorpus,
		     $bDoShowDocument) {
  if( $bDoShowAll ) {
    if( $sMode == 'corpus') {
      if( $aRow['corpus_id'] )
	return ($aRow['corpus_id'] == $iId);
      else
	return ($aRow['at_all']);
    }
    else { // mode = 'document'
      if( $aRow['document_id'] )
	return ($aRow['document_id'] == $iId);
      else
	return ($aRow['at_all']);
    }
  }
  else { // Not 'do show all'
    if( $sMode == 'corpus') {
      // If we have a do show corpus, then the corpus id (if there at all) is
      // always the current one (so no need to check).
      if( $bDoShowCorpus )
	return ($aRow['corpus_id']);
      return false;
    }
    else { // Document mode
      if( $bDoShowDocument )
	return ($aRow['document_id']);
      return false;
    }
  }
}

function updateTokenAttsForCorpus($iWordFormId, $sMode, $iId) {
  $oResult = getAnalysesInCorpus($iWordFormId, $sMode, $iId);

  if( $oResult ) {
    if( ($aRow = mysql_fetch_assoc($oResult)) ) {
      print $aRow['analysesInCorpus'];
      if($aRow['analysesInCorpus'] && $aRow['multipleLemmataAnalysesInCorpus'])
	print " | ";
      print $aRow['multipleLemmataAnalysesInCorpus'];
    }
    mysql_free_result($oResult);
  }
}

function updateTokenAttsForDb($iWordFormId, $iRowNr) {
  $oResult = getAnalysesInDb($iWordFormId);

  if( $oResult ) {
    if( ($aRow = mysql_fetch_assoc($oResult)) ) {
      printClickableTokens($iWordFormId, $aRow['analysesInDb'], $iRowNr);
      if( $aRow['analysesInDb'] && $aRow['multipleLemmataAnalysesInDb'] )
	print " | ";
      printClickableTokens($iWordFormId,
			   $aRow['multipleLemmataAnalysesInDb'], $iRowNr);
    }
    mysql_free_result($oResult);
  }
}

// The token attestations should come as '|' separated tuples
// This function prints them as items you can click on the attest all selected
// attestations as such.
//
function printClickableTokens($iWordFormId, $sAnalysesInDb, $iRowNr) {
  if(strlen($sAnalysesInDb) ) {
    $aTokenTuples = explode('|', $sAnalysesInDb);
    $sSeparator = '';
    foreach( $aTokenTuples as $sTuple) {
      // Tuple is: bVerified,AnalyzedwordFormId,... rest
      // The rest is: lemma, (modern_wf,) (pattern,) pos, (language,) (, gloss)
      // where all the parts in brackets are optional
      $aTuple = explode(',', $sTuple);
      $sPrintTuple = substr($sTuple,
			    (strlen($aTuple[0]) + strlen($aTuple[1]) + 2));
      print "$sSeparator<span class=clickableTokenAtt ";
      if( $aTuple[0] == 1) // If it is verified
	print "style='font-weight: bold' ";
      print
	"title=\"[click]: Attest all selected rows below as '$sPrintTuple'." .
	"[Ctrl-click]: Delete this analysis. " .
	"[Shift-click]: (Un)verify this analysis.\"" .
	" onMouseOver=\"javascript: this.style.cursor = 'pointer';" .
	" this.className = 'clickableTokenAtt_';\" " .
	"onMouseOut=\"javascript: this.className = 'clickableTokenAtt';\" " .
	"onClick=\"javascript: if( iHeldDownKey == iCtrlKey ) {" .
	" var bYes = confirm('Do you really want to delete this analysis " .
	"for the entire database?');" . 
	" if( bYes) deleteAnalysis($iWordFormId, $aTuple[1], $iRowNr); " .
	"}" .
	// 16 = Shift
	"else if(iHeldDownKey == 16)" .
	" verifyAnalysis(this, $iWordFormId, $aTuple[1], $iRowNr); " .
	// No relevant key held down
	
	"else ".
	"{".
	"updateAnalysesOfSelectedRows($iWordFormId, $aTuple[1], $iRowNr); " .
	"}\">" .
	
	"$sPrintTuple</span>";
      $sSeparator = ' | ';
    }
  }
}

// Tokenizing functions ///////////////////////////////////////////////////////

function processFile($sDocumentRoot, $sZipExtractDir, $sDatabase,
		     $iCorpusAddedTo, $sNewFile, $sAuthor) {
  printLog("Processing file '$sNewFile' for database '$sDatabase'\n");

  // See if it is a zip file (just by looking at the extension...)
  if( substr($sNewFile, -4) == '.zip') // If it is, tokenize every file in it
    tokenizeZipFile($sNewFile, $sDocumentRoot . "/" . $sZipExtractDir,
		    $sDatabase, $iCorpusAddedTo, $sAuthor);
  else // If it is not a zip file, just tokenize it
    tokenize($sDatabase, $iCorpusAddedTo, $sNewFile, $sAuthor);
}

function tokenizeZipFile($sZipFile, $sExtractPath, $sDatabase, $iCorpusAddedTo,
			 $sAuthor) {
  
  printLog("Unzipping '$sZipFile'\n");
  
  $zip = new PclZip($sZipFile);

  if ( ($aFilesList = $zip->listContent()) != 0) {

    // First get a list of relevant items
    // NOTE that this is a rather stupid test just on file name extension
    // .txt or .tab or .xml.
    
	$aAllTextFiles = array();
	
    for($i = 0; $i < sizeof($aFilesList); $i++) {
	
	  $sFileName = $aFilesList[$i]['filename'];
	  
      $sExtension = substr($sFileName, -4);
	  
      if( ($sExtension == '.txt') || ($sExtension == '.tab') || ($sExtension == '.xml') || ($sExtension == 'ixed') ) {
	  
			  // Keep a list of all files: we will tokenize those later on
			  array_push( $aAllTextFiles, $sFileName );  
		  }
    }
	
	$sFilesList = implode(",", $aAllTextFiles);
	
	if ($zip->extract(PCLZIP_OPT_PATH, $sExtractPath) == 0) {
		$sZipError = "Error : ".$zip->errorInfo(true);
		print "ERROR: Error in unzipping '$sZipFile': $sZipError<br>\n";
	}

    // Tokenize them one by one
	$iCountOfAllFiles = count($aAllTextFiles);
	
	// remember doc title (we will use the doc title to show the progress)
	print "<script type=\"text/javascript\">".
		"var sRememberDocTitle = document.title;".
		"</script>";
	
    for($i=0; $i < $iCountOfAllFiles; $i++ ) {
	
	// show the progress
	$iProgress = intval(100*($i / $iCountOfAllFiles));
	print "<script type=\"text/javascript\">".
		"document.title = '".$iProgress."%';".
		"</script>";
	
      tokenize($sDatabase, $iCorpusAddedTo, "$sExtractPath/" . $aAllTextFiles[$i],
	       $sAuthor);
    }
	// put back the document title
	print "<script type=\"text/javascript\">".
		"document.title = sRememberDocTitle;".
		"</script>";
	
    return TRUE;
  }
  else {
  
    $sZipError = "Error : ".$zip->errorInfo(true);
	
    printLog("ERROR in unzipping '$sZipFile': $sZipError\n");
    // Also notify the user
    print "ERROR: Error in unzipping '$sZipFile': $sZipError<br>\n";
    return FALSE;
  }
}

function getZipError($iErrorCode) {
  if( $iErrorCode == ZIPARCHIVE::ER_MULTIDISK)
    return "ZipArchive ERROR: Multi-disk zip archives not supported.";
  if( $iErrorCode == ZIPARCHIVE::ER_RENAME)
    return "ZipArchive ERROR: Renaming temporary file failed.";
  if( $iErrorCode == ZIPARCHIVE::ER_CLOSE)
    return "ZipArchive ERROR: Closing zip archive failed.";
  if( $iErrorCode == ZIPARCHIVE::ER_SEEK)
    return "ZipArchive ERROR: Seek error.";
  if( $iErrorCode == ZIPARCHIVE::ER_READ)
    return "ZipArchive ERROR: Read error.";
  if( $iErrorCode == ZIPARCHIVE::ER_WRITE)
    return "ZipArchive ERROR: Write error.";
  if( $iErrorCode == ZIPARCHIVE::ER_CRC)
    return "ZipArchive ERROR: CRC error.";
  if( $iErrorCode == ZIPARCHIVE::ER_ZIPCLOSED)
    return "ZipArchive ERROR: Containing zip archive was closed.";
  if( $iErrorCode == ZIPARCHIVE::ER_NOENT)
    return "ZipArchive ERROR: No such file.";
  if( $iErrorCode == ZIPARCHIVE::ER_EXISTS)
    return "ZipArchive ERROR: File already exists.";
  if( $iErrorCode == ZIPARCHIVE::ER_OPEN)
    return "ZipArchive ERROR: Can't open file.";
  if( $iErrorCode == ZIPARCHIVE::ER_TMPOPEN)
    return "ZipArchive ERROR: Failure to create temporary file.";
  if( $iErrorCode == ZIPARCHIVE::ER_ZLIB)
    return "ZipArchive ERROR: Zlib error.";
  if( $iErrorCode == ZIPARCHIVE::ER_MEMORY)
    return "ZipArchive ERROR: Memory allocation failure.";
  if( $iErrorCode == ZIPARCHIVE::ER_CHANGED)
    return "ZipArchive ERROR: Entry has been changed.";
  if( $iErrorCode == ZIPARCHIVE::ER_COMPNOTSUPP)
    return "ZipArchive ERROR: Compression method not supported.";
  if( $iErrorCode == ZIPARCHIVE::ER_EOF)
    return "ZipArchive ERROR: Premature EOF.";
  if( $iErrorCode == ZIPARCHIVE::ER_INVAL)
    return "ZipArchive ERROR: Invalid argument.";
  if( $iErrorCode == ZIPARCHIVE::ER_NOZIP)
    return "ZipArchive ERROR: Not a zip archive.";
  if( $iErrorCode == ZIPARCHIVE::ER_INTERNAL)
    return "ZipArchive ERROR: Internal error.";
  if( $iErrorCode == ZIPARCHIVE::ER_INCONS)
    return "ZipArchive ERROR: Zip archive inconsistent.";
  if( $iErrorCode == ZIPARCHIVE::ER_REMOVE)
    return "ZipArchive ERROR: Can't remove file.";
  if( $iErrorCode == ZIPARCHIVE::ER_DELETED)
    return "ZipArchive ERROR: Entry has been deleted.";
}

function tokenize($sDatabase, $iCorpusAddedTo, $sNewFile, $sAuthor) {
  $sTokenizedFile = $sNewFile;

  // Look in the file to see if it is tokenized already
  // If it is we just take the file as it is
  // Otherwise we tokenize it ourselves
  if( ! fileIsTokenized($sNewFile) ) {
    // Make a new file name: myFile.txt -> myFile.txt_tokenized.tab
    $sTokenizedFile = $sNewFile . "_tokenized.tab";

    // Check if it hasn't been (uploaded and) tokenized before
    if( ! file_exists($sTokenizedFile) ) {
      // Tokenize it with an external Perl script
      // NOTE that we first cd to the right directory. This saves us from
      // having to have the directory in the global PATH variable.
      $sCommand = "cd " . $GLOBALS['sTokenizerDir'] . "; ";
      if( strlen($GLOBALS['sSudo'])) // Needed for Ubuntu
		$sCommand = $GLOBALS['sSudo'] . " ";

      // If it is an XML file, use the XML tokenizer
      if( preg_match("/\.(xml|fixed)$/i", $sNewFile) ) {
		$sCommand .= "./tokenizeXML.pl" .
		  " -l " . $GLOBALS['sTokenizerLanguage'] .
		  " -o " . escapeshellarg($sTokenizedFile) .
		  " -d " . escapeshellarg($sDatabase) .
		  " " . escapeshellarg($sNewFile);
      }
      else { // Not an XML file
		$sCommand .= "./impactok.pl " .
		  /// "-p " . /// Extra option for position info
		  "-b '\n' " .
		  "-l " . $GLOBALS['sTokenizerLanguage'] .
		  " -d " . $GLOBALS['sTokenizerDir'] . "/ " .
		  "-f " . escapeshellarg($sNewFile) .
		  " -o " . escapeshellarg($sTokenizedFile);
      }
      # We redirect the stderr to the stdout
      $sCommand .=  " 2>&1";
      printLog("Executing '$sCommand'\n");
      $sExecOutput = exec($sCommand);

      if(strlen($sExecOutput)) // That means that there was an error
	print $sExecOutput;
    }
  }

  // Add file to corpus table.
  // If in full database mode, we will be loading the file into the database now!
  $iDocumentId =
    addFileToCorpus($sDatabase, $iCorpusAddedTo, $sTokenizedFile, $sAuthor);

  // The document was not in the db at all, then the document id is returned
  // If it was, but not for this corpus, then addFileToCorpus already inserted
  // the right entry in corpusId_x_documentId
  if( $iDocumentId != -1 )
    parseAndAddDocToDb($sDatabase, $iDocumentId, $sTokenizedFile);
}

// This function checks if a file looks like it is tokenized.
// For speed it just checks the first couple of lines. This may result in
// non-tokenized files to be erroneously classified as tokenized, though
// actually, this is not very likely.
//
function fileIsTokenized($sDocumentPath) {
  $i = 0;
  $fh = fopen($sDocumentPath, 'r');
  if( $fh ) {
    while( (! feof($fh)) && ($i <= 10) ) { // Look at the first 10 lines at most
      $sLine = fgets($fh);
      // Here is a regular expression that checks whether or not the line looks
      // like something tokenized
      if( strlen($sLine) &&
	  ! preg_match("/^[^\t\n]+\t[^\t\n]+\t\d+\t\d+/", $sLine) ) {
	fclose($fh);
	return FALSE;
      }
      $i++;
    }
    fclose($fh);
    return TRUE; // If we get here it means we didn't fail earlier.
  }
  
  
  echo("We couldn't check if the files was tokenized");
  return FALSE;
}

// Put the document in the database
// First we parse the tokenized document, then we go through the tokens to calculate
// the frequencies in this document.
// For optimisation, the word forms are inserted per 1000 regardless of whether
// or not they are in the database already (this saves a lot of looking up)
// Then, also per these 1000 we look up their id's and inserted them into
// the type frequencies table.
function parseAndAddDocToDb($sDatabase, $iDocumentId, $sDocumentPath) {
  list($aTokens, $aOnOffsets) = parseTokenizedDocument($sDocumentPath);
  $aTypes = array();
  $sWordFormInsertValues = '';
  $sWordFormSelectValues = '';
  $cSeparator = '';
  $i = 0;
  $iInsertMax = 1000;

  // Get the frequencies
  foreach( $aTokens as $sToken) {
    if( array_key_exists($sToken, $aTypes) )
      $aTypes[$sToken]['freq']++;
    else { // First time we see this word for this document
      $aTypes[$sToken]['freq'] = 1;
      // Add the word to the word form array once
      $sEscapedToken = addslashes($sToken);
      $sWordFormInsertValues .=
	"$cSeparator ('$sEscapedToken', LOWER('$sEscapedToken'))";
      $sWordFormSelectValues .= "$cSeparator '$sEscapedToken'";
      $cSeparator = ',';
      $i++;
      if($i == $iInsertMax) {
	insertWordForms($sWordFormInsertValues);
	getWordFormIds($sWordFormSelectValues, $aTypes);
	// Reset
	$i = 0;
	$cSeparator = '';
	$sWordFormInsertValues = '';
	$sWordFormSelectValues = '';
      }
    }
  }

  // Any left overs
  if($i) {
    insertWordForms($sWordFormInsertValues);
    getWordFormIds($sWordFormSelectValues, $aTypes);
  }  

  // Fill the type frequency table and the token database
  addTypeFrequencies_tokens($sDatabase, $iDocumentId, $aTypes, $aOnOffsets);
}

function parseTokenizedDocument($sDocumentPath) {
  $aResult = array();
  $aOnOffsets = array();

  // Read the file
  $fh = fopen($sDocumentPath, 'r');
  // The input should be a tokenized file looking like this:
  // canonical_wordform_1<TAB>wordform_1<TAB>onset_1<TAB>offset_1
  // canonical_wordform_2<TAB>wordform_2<TAB>onset_2<TAB>offset_2
  // etc...
  // In this phase we are only interested in the first column
  if( $fh) { 
    while( ! feof($fh) ) {
      // Chop off newlines
      $sLine = preg_replace("/[\n\r]+$/", "", fgets($fh));
      $aLine = explode("\t", $sLine);
      // Avoid empty words at end of file or something...
      if( strlen($aLine[0])&&
	  // New punctuation part
	  (! (isset($aLine[4]) && ($aLine[4] == 'isNotAWordformInDb')) )
	  ///
	  ) {

	array_push($aResult, $aLine[0]);

	// Here we build the values that will feature in an INSERT statement
	// for the token database later on.
	// The TOKEN_DB_ID, WF_ID and DOC_ID are placeholders which will be
	// replaced later on with the real values (that we don't know yet
	// here).
	// It is done like this so we only have to go through the file once.
	if(array_key_exists($aLine[0], $aOnOffsets))
	  $aOnOffsets[$aLine[0]] .=
	    ", (TOKEN_DB_ID, DOC_ID, WF_ID, ".$aLine[2].", " . $aLine[3] . ")";
	else // There is no entry yet
	  $aOnOffsets[$aLine[0]] =
	    "(TOKEN_DB_ID, DOC_ID, WF_ID, " . $aLine[2] . ", " . $aLine[3].")";
      }
    }
    fclose($fh);
  }

  return array($aResult, $aOnOffsets);
}

// End of tokenizing functions ////////////////////////////////////////////////

// This is where the actual filling of the lemma suggestions box takes place
function fillLemmaSuggestions($sMenuMode, $sValue) {
  printLog("fillLemmaSuggestions('$sMenuMode', '$sValue')\n");

  // First, find the last entry of what the user is typing
  $aTmpValues = explode("|", $sValue);
  $aValues = explode("&", $aTmpValues[count($aTmpValues)-1]);
  $iPos = strrpos($aTmpValues[count($aTmpValues)-1], '&');
  $sFirstPartOfMultiple = ( $iPos )
    ? substr($aTmpValues[count($aTmpValues)-1], 0, $iPos + 1) . ' ' : '';
 
  if( strlen($aValues[count($aValues) - 1]) )
    printLemmaSuggestions($sMenuMode, $sFirstPartOfMultiple,
			  $aValues[count($aValues) - 1]);
}

// saveTokenAttestations.php
//
// What happens is that we first throw away any token level attestations 
// associated with this word form (as the user might have deleted a couple) and
// then add the new one(s).
function saveTokenAttestations($iUserId, $iId, $sMode, $iWordFormId,
			       $sSelected, $sValue, $iRowNr, $bVerify) {
  // First delete any token attestations we have now for these documents
  deleteTokenAttestations($iId, $sMode, $iWordFormId, $sSelected);
  // Then add at the current/new ones
  // This functions also prints the token attestations per sentence
  addTokenAttestations($iUserId, $iWordFormId, $sSelected, $sValue, $bVerify);
}

// This one resembles the one above quite closely but it is called when someone
// clicks on a clickable token attestations in the right row (attestations in
// the database).
function saveTokenAttestations2($iUserId, $iId, $sMode, $iWordFormId,
				$sTokenAttestations, $bVerify) {
  // Add to the current/new ones
  // This functions also prints the token attestations per sentence
  addTokenAttestations2($iUserId, $iWordFormId, $sTokenAttestations, $bVerify);
}

function printMainPage($sDatabase, $iId, $sName, $sMode, $iUserId, $sUserName,
		       $sFilter, $bCaseInsensitivity, $sLemmaFilter,
		       $bDoShowAll, $bDoShowCorpus, $bDoShowDocument,
		       $iNrOfWordFormsPerPage, $iNrOfSentencesPerWordform,
		       $iAmountOfContext, $sSortSentencesBy,
		       $sSortSentencesMode, $sGoto) {
  print "<div id=lemmaEditDiv></div>"; // Invisible by default
  // The top line/table, with sorting buttons, user info and back button
  print "<table width=100% border=0 " .
    "onClick=\"javascript: sClickedInWindowPart = 'topBar';\">" .
    "<tr><td><span id=sortBox>" .
    // The sorting works like this: first we set the global variables for
    // sorting, then we call fillWordsToAttest() which uses those.
    // This is done globally so we can apply the same sort settings for
    // filtering
    "<span class=sortSpan>\n" . // Sort by wordform
    // Left-to-right asc
    "<span class=sortArrow id=sort_wordForm_lr_asc onClick=" .
    "\"javascript: sortWordsToAttest('wordForm', 'asc', false);\" " .
    " title='Sort word forms left to right ascending' " .
    "onMouseOver=" .
    "\"javascript: this.style.cursor = 'pointer';\">" .
    "<img src='./img/sortArrow_leftToRightAsc.png'></span>\n" .
    // Right-to-left asc
    "<span id=sort_wordForm_rl_asc class=sortArrow onClick=" .
    "\"javascript: sortWordsToAttest('wordForm', 'asc', true);\" " .
    " title='Sort word forms right to left ascending' " .
    "onMouseOver=" .
    "\"javascript: this.style.cursor = 'pointer';\">" .
    "<img src='./img/sortArrow_rightToLeftAsc.png'></span>\n".
    // Word form
    "word form\n" .
    // Left-to-right descending
    "<span id=sort_wordForm_lr_desc class=sortArrow " .
    " title='Sort word forms left to right descending' " .
    "onClick=" .
    "\"javascript: sortWordsToAttest('wordForm', 'desc', false);\" ".
    "onMouseOver=" .
    "\"javascript: this.style.cursor = 'pointer';\">" .
    "<img src='./img/sortArrow_leftToRightDesc.png'></span>\n".
    // Right-to-left descending
    "<span id=sort_wordForm_rl_desc class=sortArrow " .
    " title='Sort word forms right to left descending' " .
    "onClick=" .
    "\"javascript: sortWordsToAttest('wordForm', 'desc', true);\" ".
    "onMouseOver=" .
    "\"javascript: this.style.cursor = 'pointer';\">" .
    "<img src='./img/sortArrow_rightToLeftDesc.png'></span>".
    "</span>&nbsp;\n" .  // End of wordform sortSpan span

    // Frequency sorting
    "<span class=sortSpan>" .
    // Frequency ascending
    " <span id=sort_frequency_asc class=sortArrow " .
    "title='Sort wordform by frequency ascending' " .
    "onClick=" .
    "\"javascript: sortWordsToAttest('frequency', 'asc', false);\" " .
    "onMouseOver=" .
    "\"javascript: this.style.cursor = 'pointer';\">" .
    "<img src='./img/sortArrow_freqAsc.png'></span>\n" .
    "frequency" .
    // Frequency descending
    "<span id=sort_frequency_desc class=sortArrow " .
    "title='Sort wordform by frequency descending' " .
    "onClick=" .
    "\"javascript: sortWordsToAttest('frequency', 'desc', false);\" " .
    "onMouseOver=" . 
    "\"javascript: this.style.cursor = 'pointer';\">" .
    "<img src='./img/sortArrow_freqDesc.png'></span>".
    "</span>&nbsp;" . // End of frequency sortSpan 
 
	"<span>". // get not yet lemmatized wordforms
	"<a target='_blank' href='./php/getUnlemmatizedWordforms.php?sDatabase=$sDatabase' title='Get list of wordforms that were not yet lemmatized (at all)'>&hearts;</a></span>". 
    "</span>&nbsp;".
	"<span>". 
	"<a target='_blank' href='./php/getPartiallyUnlemmatizedWordforms.php?sDatabase=$sDatabase' title='Get list of wordforms that were but partly lemmatized'>&diams;</a></span>". 
    "</span>".
	"</td>";
  // Don't show options
  print "<td width=20px>";
  // The current url without the do show options. Is set dependent on the mode
  // in the next sections
  $sBaseUrl = '';
  if( $sMode == 'corpus') {
    $sBaseUrl = "./lexiconTool.php?sDatabase=$sDatabase&iUserid=" .
      "$iUserId&sUserName=$sUserName&iCorpusId=$iId&sCorpusName=$sName" .
      "&sFilter=" . urlencode($sFilter) .
      "&sLemmaFilter=" . urlencode($sLemmaFilter);
    if( $bCaseInsensitivity)
      $sBaseUrl .= "&bCaseInsensitivity=true";
    else
      $sBaseUrl .= "&bCaseInsensitivity=false";
    print "<div onMouseOver=\"javascript: this.style.cursor = 'pointer';\" ".
      "class=dontShowButton";
    if($bDoShowCorpus) {
      print '_ title="Don\'t show words hidden for this corpus" ';
      print "onClick='javascript: location.href=" .
	"\"$sBaseUrl&bDoShowAll=$bDoShowAll" .
	"&bDoShowDocument=$bDoShowDocument&bDoShowCorpus=0" .
	"&iNrOfWordFormsPerPage=$iNrOfWordFormsPerPage" .
	"&sSortBy=\" + sSortBy + \"&sSortMode=\" + sSortMode + " .
	"\"&bSortReverse=\" + bSortReverse + " .
	"\"&iNrOfSentencesPerWordform=\" + iNrOfSentencesPerWordform + \"" .
	"&iAmountOfContext=\" + iAmountOfContext;'" .
	">c</div>\n";
    }
    else {
      print " title=\"Show words hidden for this corpus\" " .
	"onClick='javascript: location.href=" .
	"\"$sBaseUrl&bDoShowAll=$bDoShowAll" .
	"&bDoShowDocument=$bDoShowDocument&bDoShowCorpus=1"  .
	"&iNrOfWordFormsPerPage=$iNrOfWordFormsPerPage" .
	"&iNrOfSentencesPerWordform=\" + iNrOfSentencesPerWordform + \"" .
	"&sSortBy=\" + sSortBy + \"&sSortMode=\" + sSortMode + " .
	"\"&bSortReverse=\" + bSortReverse + " .
	"\"&iAmountOfContext=\" + iAmountOfContext;'" .
	">c</div>\n";
    }
  }
  else { // document mode
    $sBaseUrl = "./lexiconTool.php?sDatabase=$sDatabase&iUserid=" .
      "$iUserId&sUserName=$sUserName&iFileId=$iId&sFileName=$sName" .
      "&sFilter=" . urlencode($sFilter) .
      "&sLemmaFilter=" . urlencode($sLemmaFilter);
    if( $bCaseInsensitivity)
      $sBaseUrl .= "&bCaseInsensitivity=true";
    else
      $sBaseUrl .= "&bCaseInsensitivity=false";
    print "<div onMouseOver=\"javascript: this.style.cursor = 'pointer';\" ".
      "class=dontShowButton";
    if($bDoShowDocument) {
      print '_ title="Don\'t show words hidden for this document" ' .
	"onClick='javascript: location.href=" .
	"\"$sBaseUrl&bDoShowAll=$bDoShowAll" .
	"&bDoShowDocument=0&bDoShowCorpus=$bDoShowCorpus" .
	"&iNrOfSentencesPerWordform=\" + iNrOfSentencesPerWordform + \"" .
	"&iNrOfWordFormsPerPage=$iNrOfWordFormsPerPage" .
	"&sSortBy=\" + sSortBy + \"&sSortMode=\" + sSortMode + " .
	"\"&bSortReverse=\" + bSortReverse + " .
	"\"&iAmountOfContext=\" + iAmountOfContext;'" .
	">d</div>\n";
    }
    else
      print " title=\"Show words hidden for this document\" " .
	"onClick='javascript: location.href=" .
	"\"$sBaseUrl&bDoShowAll=$bDoShowAll" .
	"&bDoShowDocument=1&bDoShowCorpus=$bDoShowCorpus"  .
	"&iNrOfSentencesPerWordform=\" + iNrOfSentencesPerWordform + \"" .
	"&iNrOfWordFormsPerPage=$iNrOfWordFormsPerPage" .
	"&sSortBy=\" + sSortBy + \"&sSortMode=\" + sSortMode + " .
	"\"&bSortReverse=\" + bSortReverse + " .
	"\"&iAmountOfContext=\" + iAmountOfContext;'" .
	">d</div>\n";
  }
  print "<div onMouseOver=\"javascript: this.style.cursor = 'pointer';\" ".
    "class=dontShowButton";
  if($bDoShowAll) {
    print "_ title=\"Hide all hidden words\" " .
      "onClick='javascript: location.href=\"$sBaseUrl&bDoShowAll=0" .
      "&bDoShowDocument=$bDoShowDocument&bDoShowCorpus=$bDoShowCorpus" .
      "&iNrOfWordFormsPerPage=$iNrOfWordFormsPerPage" .
      "&iNrOfSentencesPerWordform=\" + iNrOfSentencesPerWordform + \"" .
      "&sSortBy=\" + sSortBy + \"&sSortMode=\" + sSortMode + " .
      "\"&bSortReverse=\" + bSortReverse + " .
      "\"&iAmountOfContext=\" + iAmountOfContext;'" .
      ">a</div>\n";
  }
  else
    print " title=\"Show all hidden words\" " .
      "onClick='javascript: location.href= \"$sBaseUrl&bDoShowAll=1" .
      "&bDoShowDocument=$bDoShowDocument&bDoShowCorpus=$bDoShowCorpus" .
      "&iNrOfWordFormsPerPage=$iNrOfWordFormsPerPage" .
      "&iNrOfSentencesPerWordform=\" + iNrOfSentencesPerWordform + \"" .
      "&sSortBy=\" + sSortBy + \"&sSortMode=\" + sSortMode + " .
      "\"&bSortReverse=\" + bSortReverse + " .
      "\"&iAmountOfContext=\" + iAmountOfContext;'" .
      ">a</div>\n";
  print "</td>\n";
  
  // Goto box
  $sPrintGotoValue = 'Go to wordform...' ;
  print "<script type=\"text/javascript\">var sGoto = '';</script>\n";
  print "<td><input title='Go to a given wordform' type=text value='$sPrintGotoValue' length=32 " .
    "onFocus=\"javascript: " .
    // Next line is necessary because the very first time you click in these
    // filter boxes, the body onClick event somehow doesn't fire
    "sClickedInWindowPart = 'topBar'; hideMenus(); arrangeKeydown(); " .
    "if(this.value == 'Go to wordform...') this.value = '';\" " .
    "onKeyPress=\"javascript: e = event || window.event; " . 
    "if( (e.keyCode == 13) && (sGoto != this.value) ) {" .
	"showProcessingMsg('Hang on... I\'m looking for the right page...'); " .
    "top.location.href='./lexiconTool.php" .
    "?sDatabase=$sDatabase&iUserId=$iUserId&sUserName=$sUserName&sGoto=' + ".
    "encodeURIComponent(this.value) + '" .
    "&iNrOfWordFormsPerPage=$iNrOfWordFormsPerPage" .
    "&iNrOfSentencesPerWordform=' + iNrOfSentencesPerWordform + '" .
    "&sSortBy=' + sSortBy + '&sSortMode=' + sSortMode + " .
    "'&bSortReverse=' + bSortReverse + " .
    "'&sSortSentencesBy=' + sSortSentencesBy + " .
    "'&sSortSentencesMode=' + sSortSentencesMode + " .
    "'&iAmountOfContext=' + iAmountOfContext + ";
  if( $sMode == 'corpus')
    print "'&bDoShowAll=$bDoShowAll&bDoShowCorpus=$bDoShowCorpus" .
      "&iCorpusId=$iId&sCorpusName=$sName'";
  else // File mode
    print "'&bDoShowAll=$bDoShowAll&bDoShowDocument=$bDoShowDocument" .
      "&iFileId=$iId&sFileName=$sName'";
  print "}\">&nbsp;";  
  print "</td>\n";

  // Filter box
  $sPrintFilter = ($sFilter == '') ? 'Filter wordforms...' :
    preg_replace('/\\\"/', '&#34;', preg_replace("/\\\'/", "&#39", $sFilter));
  print "<td><input title='Wordform filter' type=text value='$sPrintFilter' length=32 " .
    "onFocus=\"javascript: " .
    // Next line is necessary because the very first time you click in these
    // filter boxes, the body onClick event somehow doesn't fire
    "sClickedInWindowPart = 'topBar'; hideMenus(); arrangeKeydown(); " .
    "if(this.value == 'Filter wordforms...') this.value = '';\" " .
    "onKeyPress=\"javascript: e = event || window.event; " . 
    "if( (e.keyCode == 13) && ((sFilter != this.value) || " .
    "(bCaseInsensitivity != " .
    "document.getElementById('caseInsensitivity').checked)) ) " .
    "top.location.href='./lexiconTool.php" .
    "?sDatabase=$sDatabase&iUserId=$iUserId&sUserName=$sUserName&sFilter=' + ".
    "encodeURIComponent(this.value) + '&sLemmaFilter=" .
    urlencode($sLemmaFilter) .
    "&iNrOfWordFormsPerPage=$iNrOfWordFormsPerPage" .
    "&iNrOfSentencesPerWordform=' + iNrOfSentencesPerWordform + '" .
    "&sSortBy=' + sSortBy + '&sSortMode=' + sSortMode + " .
    "'&bSortReverse=' + bSortReverse + " .
    "'&sSortSentencesBy=' + sSortSentencesBy + " .
    "'&sSortSentencesMode=' + sSortSentencesMode + " .
    "'&iAmountOfContext=' + iAmountOfContext + ";
  if( $sMode == 'corpus')
    print "'&bDoShowAll=$bDoShowAll&bDoShowCorpus=$bDoShowCorpus" .
      "&iCorpusId=$iId&sCorpusName=$sName";
  else // File mode
    print "'&bDoShowAll=$bDoShowAll&bDoShowDocument=$bDoShowDocument" .
      "&iFileId=$iId&sFileName=$sName";
  // Case insensitiviy
  // NOTE that we pass the real boolean value in the string.
  print "&bCaseInsensitivity=' + " . 
    "document.getElementById('caseInsensitivity').checked;\">&nbsp;";
  $sCkecked = ($bCaseInsensitivity) ? 'checked ' : '';
  print "<input type=checkbox id=caseInsensitivity $sCkecked".
    "title='Check for case insensitivity'>";
  print "</td>\n";


  $sPrintLemmaFilter = ($sLemmaFilter == '') ? 'Filter lemmata...' :
    preg_replace('/\\\"/', '&#34',
		 preg_replace("/\\\'/", "&#39", $sLemmaFilter));
  print "<td><input title='Lemma filter' id=lemmaFilterInput type=text value='$sPrintLemmaFilter' " .
    "length=32 " .
    // onFocus
    "onFocus=\"javascript: " .
    // Next line is necessary because the very first time you click in these
    // filter boxes, the body onClick event somehow doesn't fire
    "sClickedInWindowPart = 'topBar'; hideMenus(); arrangeKeydown(); " .
    "if(this.value == 'Filter lemmata...') this.value = '';\" " .
    // onKeyUp
    "onKeyUp=\"javascript: " .
    " if(iLastKeyPressed == 13)" .
    "  hideMenus();" .
    " else if( (iHeldDownKey != 16) && (iHeldDownKey != iCtrlKey) && " .
    "(iLastKeyPressed != 40) && " . // Up arrow
    "(iLastKeyPressed != 38)) {" .  // Down arrow
    " iSelectedLemmaSuggestionRow = -1; iMaxLemmaSuggestionRow = -1; " .
    "fillLemmaSuggestions(this, 'lemmaFilter');}\" " .
    " value=''>";

  // Edit lemma
  print "<span id=editLemma title='Show/hide lemma edit box'>" .
    "<img src='./img/16x16.png'></span>\n";

  print "</td>";

  // Nr of word forms per page
  print 
    "<td title='Number of word forms per page' width=60px>" .
    "<form onChange=\"top.location.href = '$sBaseUrl&bDoShowAll=$bDoShowAll" .
    "&bDoShowDocument=$bDoShowDocument&bDoShowCorpus=$bDoShowCorpus" .
    "&sSortBy=' + sSortBy + '&sSortMode=' + sSortMode + " .
    "'&bSortReverse=' + bSortReverse + " .
    "'&sSortSentencesBy=' + sSortSentencesBy + " .
    "'&sSortSentencesMode=' + sSortSentencesMode + " .
    "'&iNrOfWordFormsPerPage=' + this.nrOfWordFormsPerPage.value + " .
    "'&iNrOfSentencesPerWordform=' + iNrOfSentencesPerWordform + " .
    "'&iAmountOfContext=' + iAmountOfContext;\">" .
    "<select name=nrOfWordFormsPerPage>";
  $sSelected = '';
  foreach($GLOBALS['aNrOfWordFormsPerPage'] as $iNOWFPP) {
    $sSelected = ($iNOWFPP == $iNrOfWordFormsPerPage) ? "selected" : '';
    print "<option value=$iNOWFPP $sSelected>$iNOWFPP</option>";
  }
  print "</select></form></td>";

  // Nr of sentences to show per word form
  print "<td title='Number of sentences to show per word form' width=60px>" .
    "<form><select name=nrOfSentencesPerWordform " .
    "onChange=\"javascript: onNrOfSentencesPerWordformChange(this.value);\">";
  $sSelected = '';
  foreach($GLOBALS['aNrOfSentencesPerWordform'] as $iNOSPWF) {
    $sSelected = ($iNOSPWF == $iNrOfSentencesPerWordform) ? "selected" : '';
    print "<option value=$iNOSPWF $sSelected>$iNOSPWF</option>";
  }
  print "</select></form></td>";

  // Amount of context
  print "<td title='Amount of context' width=100px>" .
    "<form onChange='iAmountOfContext = this.amountOfContext.value;" .
    " var oWordRow = document.getElementById(\"wordRow_\" + iSelectedRow);" .
    " if( oWordRow) { var aIdAndWf = oWordRow.title.split(\"\\t\"); " .
    " fillSentences(aIdAndWf[0],aIdAndWf[1],true);}'>".
    "<select name=amountOfContext>";
  $sSelected = '';
  foreach($GLOBALS['aAmountOfContext'] as $sAmountDescription => $iAmount) {
    $sSelected = ($GLOBALS['iAmountOfContext'] == $iAmount) ? "selected" : '';
    print "<option value=$iAmount $sSelected>$sAmountDescription</option>";
  }
  print "</select></form></td>" .
    // Corpora/Start page buttons
    "<td width=5% align=right>" .
    " <form action='./lexiconTool.php' method=POST>";
  // Expand this corpus when the user goes to the corpus page
  if( $sMode == 'corpus')
    print " <input type=hidden name=iCorpusAddedTo value=$iId>";
  print " <input type=hidden name=sDatabase value='$sDatabase'> " .
    " <input type=hidden name=sUserName value='$sUserName'> " .
    " <input type=hidden name=iUserId value=$iUserId> " .
    " <input type=submit value='< Corpora'></form>" .
    "</td>";
  print "<td width=5% align=right>" .
    "<form action='./lexiconTool.php' method=POST>" .
    " <input type=submit value='<< Start page'></form>" .
    "</td>" .
    "</tr></table>\n\n";

  // The rest of the divs, most of which are filled by Javascript later on
  print
    // Scroll bar
    "<div id=pageLinks></div>\n" .
    // Words to attest
    "<div id=wordsToAttest" . // Is filled when user clicks a word row
    " onClick=\"javascript: sClickedInWindowPart = 'wordsToAttest';\">" .
    "</div>\n\n" .
    // Suggestions menu in upper part of the screen
    "<div id=textAttSuggestions></div>\n\n" .

    // Buttons for sorting sentences
    "<div id=sortingButtons><table width=100% border=0><tr>" .
    // Buttons at the left side
    "<td id=leftSort width=100px>&nbsp;";
  // Sort by document
  $sClassAffix = ( $sSortSentencesBy == 'doc') ? '_' : '';
  print 
    "<span id=docasc class=sortByContextButton$sClassAffix>" .
    "<a href='javascript: sortSentences(\"doc\", \"asc\");' " .
    "title='Sort by document (default)'>" .
    "<img src='./img/sortDoc.png' border=0></a></span>&nbsp;";
  // Sort by verification descending
  $sClassAffix = ($sSortSentencesBy == 'verif' && $sSortSentencesMode =='desc')
    ? '_' : '';
  print
    "<span id=verifdesc class=sortByContextButton$sClassAffix>" .
    "<a href='javascript: sortSentences(\"verif\", \"desc\");' " .
    "title='Sort the sentences by verification (descending)'>" .
    "<img src='./img/sortByVerificationDesc.png' border=0></a></span>&nbsp;";
  // Sort by verification ascending
  $sClassAffix = ($sSortSentencesBy == 'verif' && $sSortSentencesMode == 'asc')
    ? '_' : '';
  print
    "<span id=verifasc class=sortByContextButton$sClassAffix>" .
    "<a href='javascript: sortSentences(\"verif\", \"asc\");' " .
    "title='Sort the sentences by verification (ascending)'>" .
    "<img src='./img/sortByVerificationAsc.png' border=0></a></span>&nbsp;";
  $sClassAffix = ($sSortSentencesBy == 'left' && $sSortSentencesMode == 'desc')
    ? '_' : '';
  print
    // Sort by left context descending
    "<span id=leftdesc class=sortByContextButton$sClassAffix>" .
    "<a href='javascript: sortSentences(\"left\", \"desc\");' " .
    "title='Sort the sentences by left context (descending)'>" .
    "<img src='./img/sortDesc.png' border=0></a></span>&nbsp;";
  // Sort by left context ascending
  $sClassAffix = ($sSortSentencesBy == 'left' && $sSortSentencesMode == 'asc')
    ? '_' : '';
  print
    "<span id=leftasc class=sortByContextButton$sClassAffix>" .
    "<a href='javascript: sortSentences(\"left\", \"asc\");' " .
    "title='Sort the sentences by left context (ascending)'>" .
    "<img src='./img/sortAsc.png' border=0></a></span>" .
    //
    "</td>" .
    // Held down key notifier
    "<td width=26px><div title='The key you appear to have held down'" .
    " id=heldDownKeyNotifier></div></td>" .
    // Last viewed
    "<td align=center><span id=lastViewed></span>\n" .
    "</td>" .
    // Resizer
    "<td width=26px id=resizerCol title='Drag to resize the screens'>" .
    "<div id=resizer><img src='./img/resize.png'></div></td>" .
    // Buttons at the right side
    "<td id=rightSort width=80px>";
  // Sort by right context ascending
  $sClassAffix = ($sSortSentencesBy == 'right' && $sSortSentencesMode == 'asc')
    ? '_' : '';
  print
    "<span id=rightasc class=sortByContextButton$sClassAffix>" .
    "<a href='javascript: sortSentences(\"right\", \"asc\");' " .
    "title='Sort the sentences by right context (ascending)'>" .
    "<img src='./img/sortAsc.png' border=0></a></span>&nbsp;";
  // Sort by right context descending
  $sClassAffix = ($sSortSentencesBy == 'right' && $sSortSentencesMode =='desc')
    ? '_' : '';
  print
    "<span id=rightdesc class=sortByContextButton$sClassAffix>" .
    "<a href='javascript: sortSentences(\"right\", \"desc\");' " .
    "title='Sort the sentences by right context (descending)'>" .
    "<img src='./img/sortDesc.png' border=0></a></span>" .
    // Some room
    "&nbsp;&nbsp;&nbsp;";
  // Sort by lemma descending
  $sClassAffix = ($sSortSentencesBy == 'lemma' && $sSortSentencesMode =='desc')
    ? '_' : '';
  print
    "<span id=lemmadesc class=sortByContextButton$sClassAffix>" .
    "<a href='javascript: sortSentences(\"lemma\", \"desc\");' " .
    "title='Sort the sentences by lemma (descending)'>" .
    "<img src='./img/sortByLemmaDesc.png' border=0></a></span>&nbsp;";
  // Sort by lemma ascending
  $sClassAffix = ($sSortSentencesBy == 'lemma' && $sSortSentencesMode == 'asc')
    ? '_' : '';
  print
    "<span id=lemmaasc class=sortByContextButton$sClassAffix>" .
    "<a href='javascript: sortSentences(\"lemma\", \"asc\");' " .
    "title='Sort the sentences by lemma (ascending)'>" .
    "<img src='./img/sortByLemmaAsc.png' border=0></a></span>&nbsp;".
    //
    "</td>" .
    // Close tables/divs
    "</tr></table></div>" .

    // Sentence links scroll bar
    "<div id=sentenceLinks></div>\n" .

    // Sentences, is filled when someone clicks on a word
    "<div id=sentences" .
    " onScroll=\"javascript: hideMenus();\" " .
    " onClick=\"javascript: sClickedInWindowPart = 'sentences';\"></div>\n\n" .
    // Suggestions menu in bottom part of the screen
    "<div id=tokenAttSuggestions>\n</div>" .

    // Needed for html entities decoding in Javascript for IE
    // Cf: js/menuFunctions.js html_entity_decode()
    "<div id=htmlconverter style='display:none;'></div>" .

    // Progress bar
    "<div id=progressBar style='position:absolute; top:50%; left:45%; width:10%;'></div>";
}

/***** End functions for middle part *******************************/

/***** Functions for sentence/token attestation part *************************/

// The function prints the sentences as it finds them if no sorting is
// needed. If it is, first an array is created of all the rows, this array is
// sorted, and then printed (so that is SLOWER).
//
function fillSentences($sDatabase, $iId, $sMode, $sWordForm, $iWordFormId,
		       $sSelectedSentences, $iUserId,
		       $iNrOfSentencesPerWordform, $iStartAtSentence,
		       $iAmountOfContext, $sSortSentencesBy,
		       $sSortSentencesMode) {
  $iNrOfPrintedSentences = 0;
  $iNrOfNeglectedSentences = 0;

  // First, set the last view date/user of the word
  setLastView($iWordFormId, $iUserId);

  $aRows = ( $sSortSentencesBy && ($sSortSentencesBy != 'doc')) ? array() : -1;
  // Here we make
  // 'documentId1,startPos1,endPos1|documentId2,startPos2,endPos2|etc...'
  // into a hash so we can look up the combination more easily
  $aSelectedSentences = array_unique(explode('|', $sSelectedSentences));

  // getDocumentIdsAndTitles also gives you the start- and end_pos's of the
  // token attestations that have been verified.
  $iSentenceNr = 0;
  $oResult = getDocumentIdsAndTitles($iId, $sMode, $iWordFormId);
  if( $oResult ) {
    // First go through the results once to get all the document id's
    // This is quicker presumably than doing the entire (intricate) query
    // again
    // Also, while we are at it, we calculate the total amount of sentences.
    // This is used when printing the scrollbar of clickable links to
    // next/previous sentences
    $sDocIds = $sSeparator = $sDocIdsForGroupMembers = $sDocIdFGMSep = '';
    $iTotalNrOfSentences = 0;
    while( ($aRow = mysql_fetch_assoc($oResult)) ) {
      // Optimization, only works when not sorting
      if( ($sSortSentencesBy == 'doc') &&
	  ($iTotalNrOfSentences <= $iStartAtSentence) &&
	  ( ($iTotalNrOfSentences + $aRow['frequency']) >= $iStartAtSentence)) {
	$sDocIdsForGroupMembers .= $sDocIdFGMSep . $aRow['document_id'];
	$sDocIdFGMSep = ", ";
      }
      $sDocIds .= $sSeparator . $aRow['document_id'];
      $sSeparator = ", ";
      $iTotalNrOfSentences += $aRow['frequency'];
    }

    # In case an error occurred somehow, quit rightaway
    if( ! strlen($sDocIds) ) {
      print "No documents for '$sWordForm'<br>\n";
      return;
    }

    mysql_data_seek($oResult, 0); // Put the pointer back
    printLog("Doc ids: $sDocIds.\n" .
	     "Doc ids for group: $sDocIdsForGroupMembers\n");

    if( $sSortSentencesBy != 'doc') // Set to normal value if we can't optimize
      $sDocIdsForGroupMembers = $sDocIds;
    $aGroupMemberOnsetsPerDocId =
      getGroupMemberOnsetsPerDocId(
				   $sDocIdsForGroupMembers, $iWordFormId);

    while( ($aRow = mysql_fetch_assoc($oResult)) ) {
      if( ($sSortSentencesBy == 'doc') ) {
	if( ($iNrOfSentencesPerWordform != 'all') &&
	    ($iNrOfPrintedSentences == $iNrOfSentencesPerWordform) )
	  break;
	if(($sSortSentencesBy == 'doc') &&
	   ($iNrOfNeglectedSentences+ $aRow['frequency'])<= $iStartAtSentence){
	  $iNrOfNeglectedSentences += $aRow['frequency'];
	  continue;
	}
      }

      $sDocumentHeader = "<div class=document>\n<div class=docTitle>" .
	"<table width=100% cellpadding=0 cellspacing=0 border=0><tr>" .
	"<td class=leftCorner>&nbsp;</td>";
      if( $aRow['image_location'] ) {
	// Image location
	$sDocumentHeader .= "<td width=12px align=center>" .
	  "<a href='./php/displayImage.php?sImage=" .
	  rawurlencode($aRow['image_location']) . "&sSize=small' " .
	  "target='_blank'><img src='./img/pic.png' border=0" .
	  " title='Click to see the picture of the page'></a></td>";
      }
      $sDocumentHeader .=
	// Document title/link
	"<td><span class=docTitleCol " .
	"onMouseOver=\"javascript: this.style.cursor = 'pointer'; " .
	"this.className='docTitleCol_';\" " .
	"onMouseOut=\"javascript: this.className='docTitleCol';\" " .
	// NOTE that in the next bit we mix up global Javascript variables
	// with the ones we have access to in this function.
	// This isn't really being consequent of course, but also it doesn't
	// matter whatsoever.
	"onClick=\"javascript: location.href='./lexiconTool.php?sDatabase=' "
	. "+ sDatabase + '&iUserId=$iUserId&sUserName=' + sUserName + " .
	"'&iFileId=" . $aRow['document_id'] .
	"&sFileName=' + encodeURIComponent('" . $aRow['title'] . "') + " .
	"'&sFilter=' + encodeURIComponent(sFilter) + " .
	"'&bCaseInsensitivity=' + bCaseInsensitivity + " .
	"'&sLemmaFilter=' + encodeURIComponent(sLemmaFilter) + " .
	"'&iNrOfWordFormsPerPage=' + iNrOfWordFormsPerPage + " .
	"'&iAmountOfContext=' + iAmountOfContext + " .
	"'&bDoShowAll=' + bDoShowAll + '&bDoShowCorpus=' + bDoShowCorpus + ".
	"'&bDoShowDocument=' + bDoShowDocument;\">" .
	// We chop off the document root as it is always the same
	substr($aRow['title'], strlen($GLOBALS['sDocumentRoot'])+1) .
	"</span></td>" .
	// End of document table and div
	"<td class=rightCorner>&nbsp;</td></tr></table></div>\n";
      if( $sSortSentencesBy == 'doc')
	print $sDocumentHeader;

      fillSentencesForDoc($sDatabase, $aRow['document_id'], $aRow['title'],
			  $aRow['verifiedTokenAtts'],
			  $aRow['containsTokenAtts'],
			  $sWordForm, $iWordFormId,
			  $iUserId, $iSentenceNr, $aSelectedSentences,
			  $iNrOfSentencesPerWordform, $iStartAtSentence,
			  $iNrOfPrintedSentences, $iNrOfNeglectedSentences,
			  $iAmountOfContext,
			  $sSortSentencesMode, $sSortSentencesBy, $aRows,
			  $sDocumentHeader, $aRow['document_id'],
			  $aGroupMemberOnsetsPerDocId,
			  rawurlencode($aRow['image_location']) );
      if( $sSortSentencesBy == 'doc')
	print "</div>\n";
    }
    mysql_free_result($oResult);
  }

  // Here comes the sorting which is done by some index that we maintained
  // above
  if( $sSortSentencesBy && ($sSortSentencesBy != 'doc') ) {
    if( $sSortSentencesMode == 'asc')
      usort($aRows, 'sortSentences');
    else
      usort($aRows, 'sortSentences_reverse');

    $iPreviousDocId = -1;
    $sSentenceClassPostfix = '';
    $iNewSentenceNr = 0;
    $iNrOfNeglectedSentences = $iNrOfPrintedSentences = 0;
    while (list($key, $value) = each($aRows)) {
      // Check if we actually have to print
      if( $iNrOfNeglectedSentences < $iStartAtSentence ) {
	$iNrOfNeglectedSentences++;
	continue;
      }
      if( ($iNrOfPrintedSentences != 'all') &&
	  ($iNrOfPrintedSentences == $iNrOfSentencesPerWordform) ) {
	if( $iPreviousDocId != -1)
	  print "</table>\n</div>\n";
	break;
      }

      // Find out if we have to print the document header
      if( $iPreviousDocId != $value['documentId']) {
	if( $iPreviousDocId != -1 ) // Close the previous table and div
	  print "</table>\n</div>\n"; // but not the very first time

	print $value['documentHeader'] .
	  "<table width=100% cellspacing=0px border=0>\n";
	$sSentenceClassPostfix = '';
      }

      // Print the row itself.
      // NOTE that re-assign the sentence numbers here, so selecting rows, etc.
      // still works as it should.
      // Also NOTE that we do the sentence classes again (so we keep the nice
      // alternating pattern)

      print preg_replace("/(id=sentence_|" .
			 "startSelection\(|" .
			 "showTokenAttSuggestions\(this\, [0-9]+\,|" .
			 "removeTokenAttestation\(|" .
			 "toggleGroup\(this\, [0-9]+\, |" .
			 "addToSelection\()[0-9]+/",
			 "$01$iNewSentenceNr",
			 preg_replace("/class=sentence_?/",
				      "class=sentence$sSentenceClassPostfix",
				      $value['html']));
      $sSentenceClassPostfix = (strlen($sSentenceClassPostfix)) ? '' : '_';
      $iPreviousDocId = $value['documentId'];
      $iNewSentenceNr++;
      $iNrOfPrintedSentences++;
    }
    if( $iPreviousDocId != -1)
      print "</table>\n</div>\n";
  }
  
  // Now that we are done, print a hidden div that says so, so the javascript
  // can check this
  // NOTE that we give the total nr of sentences as title (so we can use it in
  // javascript to print the scrollbar of clickable links to next/previous
  // sentences)
  print "<div id=done_$iWordFormId style='visibility: hidden' ".
    "title=$iTotalNrOfSentences></div>";
}


// read a file given its 'title' which is its absolute path
function readFileContent($sDocumentTitle){

  $iDocumentSize = filesize($sDocumentTitle);
  $fh = fopen($sDocumentTitle, 'r');
  $sFileContents = fread($fh, $iDocumentSize);
  fclose($fh);
  return $sFileContents;
}

function fillSentencesForDoc($sDatabase, $iDocumentId, $sDocumentTitle,
			     $sVerifiedTokenAtts, $bContainsTokenAtts,
			     $sWordForm, $iWordFormId, $iUserId, &$iSentenceNr,
			     $aSelectedSentences,
			     $iNrOfSentencesPerWordform, $iStartAtSentence,
			     &$iNrOfPrintedSentences,&$iNrOfNeglectedSentences,
			     $iWindowWidth,
			     $sSortSentencesMode, $sSortSentencesBy, &$aRows,
			     $sDocumentHeader, $iDocumentId,
			     $aGroupMemberOnsetsPerDocId,
			     $sImageLocation) {
  $aVerifiedTokenAtts = explodeTokenAtts($sVerifiedTokenAtts);
  $iWordFormLength = strlen($sWordForm);
  
  $bFullDatabaseMode = fullDatabaseMode($sDatabase);
  
  $iDocumentSize = 0;

  // if in full database mode, get the document content from the database
  // otherwise get it from the physical file
  if ($bFullDatabaseMode){
	  $sFileContents = getDocumentContentFromDb($sDocumentTitle);
  } 
  else {
	  $iDocumentSize = filesize($sDocumentTitle);
	  $fh = fopen($sDocumentTitle, 'r');
	  $sFileContents = fread($fh, $iDocumentSize);
	  fclose($fh);
  }	
  
  // To make the match pattern easier we surround the file contents by newlines
  $sFileContents = "\n" . $sFileContents . "\n";

  printLog("Opened $sDocumentTitle for '$sWordForm' (in ".($bFullDatabaseMode ? "full database mode" : "file mode").")\n");

  $oResult = ($bContainsTokenAtts) ?
    getTokenAttestations($iDocumentId, $iWordFormId, -1) : FALSE;

  // Get the on-/offsets of group members group members for this word form
  // in this document
  $oGroupOnOffsets = getGroupOnOffsets($iDocumentId, $iWordFormId,
				       $aGroupMemberOnsetsPerDocId);
  // Get the first row (if there are any)
  $aGroupOnOffsetsRow = ($oGroupOnOffsets)
    ? mysql_fetch_assoc($oGroupOnOffsets) : false;

  // NOTE the file needs to be a tokenized file
  // I.e. it needs one line per token, reading:
  // canonical wordform<TAB>wordform<TAB>onset<TAB>offset
  $iSearchOffset = 0;
  $oLastRow = 0;
  $sSentenceClassPostfix = '';
  $iVerifiedTokenAttsIndex = 0;

  // We are doing this with strpos() and substr() rather than with regular
  // expressions because it is a LOT faster
  $sSearchWordForm = "\n" . $sWordForm . "\t";
  $iSearchWordLength = strlen($sSearchWordForm);

  $sGroupAnalyses;
  $bNeglectedSomeSentences = false;
  $bPrintedSomething = false;
  while( ($iPos = strpos($sFileContents, $sSearchWordForm, $iSearchOffset)) !==
	 false) {
    // Initiate
    if( $sSortSentencesBy && ($sSortSentencesBy != 'doc') ) {
      $aRows[$iSentenceNr] = array();
      $aRows[$iSentenceNr]['html'] = '';
      $aRows[$iSentenceNr]['documentHeader'] = $sDocumentHeader;
      // For quicker comparison we store the id as well
      $aRows[$iSentenceNr]['documentId'] = $iDocumentId;
    }
    else { // If we print right ahead
      // Check if we actually have to print
      if( $iNrOfNeglectedSentences < $iStartAtSentence ) {
	$iNrOfNeglectedSentences++;
	$iSearchOffset = ($iPos + 1 + $iSearchWordLength);
	$bNeglectedSomeSentences = true;
	continue;
      }
      if( ($iNrOfPrintedSentences != 'all') &&
	  ($iNrOfPrintedSentences == $iNrOfSentencesPerWordform) )
	break;
    }
    $sGroupAnalyses = '';

    if( ($sSortSentencesBy == 'doc') && 
	($bNeglectedSomeSentences ||($iSearchOffset == 0)) ) { // First time
      $bNeglectedSomeSentences = false; // Switch it off again
      print "<table width=100% cellspacing=0px border=0>\n";
    }
    
    $iSearchOffset = ($iPos + 1 + $iSearchWordLength);

    $iOnOffset_WindowStart = $iSearchOffset - 1;

    // We take a substring of the file contents to look for the onset/offset
    // The right window is the current line. If we can't find a newline
    // anywhere we take the entire string from $iOnOffset_WindowStart.
    //
    // We do this because we want to avoid having to match regular expressions
    // on long strings.
    $iPos = strpos($sFileContents, "\n", $iOnOffset_WindowStart);
    $iOnset = $iOffset = $iPosX = $iPosY = $iPosHeight = $iPosWidth = 0;
    if( $iPos !== FALSE ) // If we found a newline
      list($iOnset, $iOffset, $iPosX, $iPosY, $iPosHeight, $iPosWidth) =
	getOnOffset(substr($sFileContents, $iOnOffset_WindowStart,
			   $iPos - $iOnOffset_WindowStart) );
    else // No newline found, take the rest of the string
      list($iOnset, $iOffset, $iPosX, $iPosY, $iPosHeight, $iPosWidth) =
	getOnOffset(substr($sFileContents, $iOnOffset_WindowStart) );
    
    // Print the table row
    // Title = <documentId>,<startPos>,<endPos>
    printToRows("<tr id=sentence_$iSentenceNr " .
		"title='${iDocumentId},${iOnset},$iOffset' " .
		// We are doing 'name' here as well so we can use
		// getElementsByName() later on when recalculating the selected
		// sentences in Javascript
		"name='${iDocumentId},${iOnset},$iOffset' " .
		"class=sentence$sSentenceClassPostfix", $aRows, $iSentenceNr);
    if( in_array("$iDocumentId,$iOnset,$iOffset", $aSelectedSentences) ) {
      printToRows("-", $aRows, $iSentenceNr);

    }
    printToRows(">", $aRows, $iSentenceNr);

    // Toggle odd/even rows
    $sSentenceClassPostfix = (strlen($sSentenceClassPostfix)) ? '' : '_';
    // NOTE that we also use this className for the image src
    // E.g: ./img/unchecked.gif
    $sClassName = 'unchecked';
   
    # The $aVerifiedTokenAtts array lists all verified token attestations
    # It might be that we have to skip some when we are not on the first page
    while( ($iVerifiedTokenAttsIndex < count($aVerifiedTokenAtts)) &&
	   ($aVerifiedTokenAtts[$iVerifiedTokenAttsIndex][0] < $iOnset)) {
      $iVerifiedTokenAttsIndex++;
    }

    if( $aVerifiedTokenAtts &&
	$iVerifiedTokenAttsIndex < count($aVerifiedTokenAtts) &&
	$aVerifiedTokenAtts[$iVerifiedTokenAttsIndex][0] == $iOnset &&
	$aVerifiedTokenAtts[$iVerifiedTokenAttsIndex][1] == $iOffset) {
      if($aVerifiedTokenAtts[$iVerifiedTokenAttsIndex][2] == $iUserId) {
	$sClassName = 'checked';
	if( $sSortSentencesBy == 'verif' )
	  $aRows[$iSentenceNr]["index"] = "1_${iDocumentId}_2";
      }
      else {
	$sClassName = 'grayChecked';
	if( $sSortSentencesBy == 'verif' )
	  $aRows[$iSentenceNr]["index"] = "1_${iDocumentId}_1";
      }
      $iVerifiedTokenAttsIndex++;
    }
    
    if( ($sClassName == 'unchecked') && ($sSortSentencesBy == 'verif') )
      $aRows[$iSentenceNr]["index"] = "0_$iDocumentId";

    // If there is position info, print that as well
    if( $iPosX != 0 ) {
      printToRows("<td class=positionInfo align=right>" .
		  "<a href=\"./php/displayImage.php?sImage=$sImageLocation" .
		  "&iPosX=$iPosX&iPosY=$iPosY&iPosHeight=$iPosHeight" .
		  "&iPosWidth=$iPosWidth&sSize=small\" target='_blank'>" .
		  "<img src='./img/picForWordRow.png' border=0" .
		  " title='Click to see the picture of the page'></a>" .
		  "</td>", $aRows, $iSentenceNr);
    }
    else { // If there is no position info
      if( strlen($sImageLocation) ) { // But there might be for other words
	// Print an empty column so the table doesn't get messed up
	printToRows("<td class=positionInfo></td>", $aRows, $iSentenceNr);
      }
    }
    // The left part of the sentence
    printToRows("<td class=matchedLeftPart" .
		" ondblclick=\"javascript: selectAllSentences();\"" .
		" onMouseDown=\"javascript: " .
		"if( (iHeldDownKey != 120) && (iHeldDownKey != 113))" .
		"  startSelection($iSentenceNr);\"" .
		" onMouseOver=\"javascript: if(bInSelection)" .
		"  addToSelection($iSentenceNr);\">", $aRows, $iSentenceNr);
    $iWindowStart = $iSearchOffset - $iSearchWordLength - $iWindowWidth;
    $iWindowLength = $iWindowWidth;
    if( $iWindowStart < 0) {
      $iWindowLength += $iWindowStart; // Shorten it
      $iWindowStart = 0;
    }
    // The words of the left part
    printToRows(makePrintable($iDocumentId, $iOnset, $iOffset,
			      substr($sFileContents, $iWindowStart,
				     $iWindowLength),
			      $oGroupOnOffsets, $aGroupOnOffsetsRow,
			      $sGroupAnalyses, $iSentenceNr, $aRows,
			      ($sSortSentencesBy == 'left'),
			      $sSortSentencesBy),
		$aRows, $iSentenceNr);
    $sWordFormInText =
      substr($sFileContents, $iSearchOffset - 1,
	     strpos(substr($sFileContents, $iSearchOffset - 1, 300), "\t"));

    // NOTE that depending on whether there is normalize function we work on
    // the canonical word from or not.
    $sWordFormToChange =
      (isset($GLOBALS['bNormalizeToken']) && $GLOBALS['bNormalizeToken'])
      ? $sWordFormInText : $sWordForm;
      
	  
	// Check if editing wordforms is allowed in current installation (globals)
	if ( !$GLOBALS['bAllowEditingWordforms'])
		$editableFunction = "";
	else
		$editableFunction = " if( iHeldDownKey == iCtrlKey ) " .
		" makeMatchedPartEditable($iSentenceNr, $iDocumentId, " .
		"$iWordFormId, $iOnset, $iOffset, '" .
		rawurlencode($sWordFormToChange) . "', '" .
		rawurlencode($sWordFormInText) ."'); " .
		" else " ;
	
    printToRows("</td>" .
		// The word itself
		"<td id=matchedPartCol_${iDocumentId}_$iOnset>" .
		/// NOTE that this string also appears in menuFunctions.js:
		/// changeWordForm()
		"<div id=matchedPart_${iDocumentId}_$iOnset" .
		" class=matchedPart " .
		" onClick=\"javascript: " .
		$editableFunction .
		" showTokenAttSuggestions(this, $iDocumentId, $iSentenceNr," .
		"  $iWordFormId, $iOnset, $iOffset);\">" .
		// NOTE that tokens can never be longer than 255 characters,
		// so we take some characters extra (because there might be
		// comma's or something).
		// Also, we subsitute dashes for &#8209; which are
		// non-breakable which looks better in the interface.
		str_replace('-', '&#8209;', $sWordFormInText) .
		"</div></td>" .
		// The right part of the sentence
		"<td class=matchedRightPart" .
		" ondblclick=\"javascript: selectAllSentences();\"" .
		" onMouseDown=\"javascript: " .
		"if( (iHeldDownKey != 120) && (iHeldDownKey != 113))" .
		"  startSelection($iSentenceNr);\"" .
		" onMouseOver=\"javascript: if(bInSelection)" .
		"  addToSelection($iSentenceNr);\"" .
		">" .
		makePrintable($iDocumentId, $iOnset, $iOffset,
			      substr($sFileContents, $iSearchOffset,
				     $iWindowWidth),
			      $oGroupOnOffsets, $aGroupOnOffsetsRow,
			      $sGroupAnalyses, $iSentenceNr, $aRows,
			      ($sSortSentencesBy == 'right'),
			      $sSortSentencesBy) .
		" <a href=\"./php/moreContext.php?" .
		"sDatabase=$sDatabase" .
		"&iDocumentId=$iDocumentId" .
		"&sDocumentTitle=$sDocumentTitle" .
		"&iDocumentSize=$iDocumentSize&iSearchOffset=$iSearchOffset" .
		"&sWordForm=" . urlencode($sWordForm) .
		"&iSearchWordLength=$iSearchWordLength" .
		"&iWindowWidth=$iWindowWidth&iOrigWindowWidth=$iWindowWidth\"".
		" target='_blank' class=moreContext " .
		"title='More context'>&raquo;</a>" .
		"</td>\n" .
		// The current token attestations
		// (id = tokenAtts_<docId>_<onset>)
		"<td class=currentTokenAtts " .
		"id=tokenAtts_${iDocumentId}_$iOnset>", $aRows, $iSentenceNr);
    $sTokenAttestations = false;
    if( $oResult ) {
      $sTokenAttestations =
	currentTokenAttestations($iSentenceNr, $oResult, $iOnset, $iOffset,
				 $oLastRow, ($sSortSentencesBy == 'lemma'),
				 $iDocumentId, $aRows);
      printToRows($sTokenAttestations, $aRows, $iSentenceNr);
    }
    else {
      if($sSortSentencesBy == 'lemma')
	$aRows[$iSentenceNr]["index"] = "${iDocumentId}_$iSentenceNr";
    }
    
    printToRows("&nbsp;</td>\n", $aRows, $iSentenceNr);

    // Token attestation verification box    
    printToRows("<td>" .
		"<span class=$sClassName " .
		"title=\"Click to (un)verify [F8]\" " .
		"onMouseOver=\"javascript: this.style.cursor = 'pointer';\" " .
		" id=checkBox_tokenAtt_${iDocumentId}_$iOnset " .
		" onClick='javascript:" .
		" toggleTokenAttestation(this, $iDocumentId, " .
		" $iOnset, $iOffset);'>" .
		// NOTE that the classname is also the image source
		"<img src='./img/$sClassName.gif'>&nbsp;</span></td>",
		$aRows, $iSentenceNr);

    printToRows("</tr>\n", $aRows, $iSentenceNr);

    $iSentenceNr++;
    $iNrOfPrintedSentences++;
    $bPrintedSomething = true;
  }
  
  // If something went wrong while changing a word form e.g. this could result
  // in expected words not showing up in the files.
  // That is a bad sign and it should be noted.
  if( ! $bPrintedSomething ) {
    print "ERROR: '$sWordForm' doesn't appear in '$sDocumentTitle'. " .
      "Try reloading the tool (Ctrl-r). " .
      "If the problem persists maybe something went wrong while a word from " .
      "was being changed in this file (either by you or another user). " .
      "Please report the problem!";
  }

  if( $oResult )
    mysql_free_result($oResult);
  if( $oGroupOnOffsets )
    mysql_free_result($oGroupOnOffsets);
  // If something was found (and hence printed)
  if( ($iSearchOffset != 0) && ($sSortSentencesBy == 'doc') )
    print "</table>\n";
}

function sortSentences($a, $b) {
  return strcasecmp($a['index'], $b['index']);
}

function sortSentences_reverse($a, $b) {
  return strcasecmp($b['index'], $a['index']);
}

function printToRows($sString, &$aRows, $iRowNr) {
  if( $aRows == -1)
    print $sString;
  else {
    $aRows[$iRowNr]['html'] .= $sString;
  }
}

// The input is the part after the matched wordform, containing
// non-canonical form<TAB>onset<TAB>offset[...]
//
// Return ($iOnset, $iOffset, $iPosX, $iPosY, $iPosHeight, $iPosWidth)
//
function getOnOffset($sString) {

  // Old situation
  if( preg_match("/^[^\t]+\t(\d+)\t(\d+)[\r\n]?$/", $sString, $aMatch)) {
    return array($aMatch[1], $aMatch[2], 0, 0, 0, 0);
  } // With position info
  else {
    //                         1      2    3  4                      5  6
    if( preg_match("/^[^\t]+\t(\d+)\t(\d+)(\t(isNotAWordformInDb)?)?(\t(\d+)\t(\d+)\t(\d+)\t(\d+))?$/",
		   $sString, $aMatch) ) {
      if(isset($aMatch[5]) )
	return array($aMatch[1], $aMatch[2],
		     ### Position info
		     $aMatch[6], $aMatch[7], $aMatch[8], $aMatch[9]);
      else
	return array($aMatch[1], $aMatch[2], 0, 0, 0, 0);
    }
  }

  return array(-1, -1, 0, 0, 0, 0); // <- This should never happen!
}

// This string is used to make a left and right part to show in the lower
// part of the window (it is called from fillSentencesForDoc())
//
// The string comes in as a (possibly broken) series of
// canonical wordform<TAB>wordform<TAB>onset<TAB>offset
//
// The text always begins with a newline (see above).
function makePrintable($iDocumentId, $iHeadWordOnset, $iHeadWordOffset,
		       $sString, $oGroupOnOffsets, &$aGroupOnOffsetsRow,
		       &$sGroupAnalyses, $iSentenceNr, &$aRows, $bBuildIndex,
		       $sSortSentencesBy) {
  if($bBuildIndex)
    $aRows[$iSentenceNr]["index"] = '';
  $sPart = '';
  $sPartWithTags = '';
  // This regular expression takes the newline, the canonical word form and 
  // the wordform as in the text, which printed.
  // Only complete words are printed. This is to avoid things like
  //  <ne tag="">Hello</ne>
  // to mess up things (especially with quotes)
  
  // OLD: preg_match_all("/[\r\n]+[^\n\r\t]+\t([^\n\r\t]+)\t(\d+)\t(\d+)(\tisNotAWordformInDb)?/",
  //  FIRST PART of the REGEX removed because otherwise the first word was never shown
  preg_match_all("/[^\n\r\t]+\t([^\n\r\t]+)\t(\d+)\t(\d+)(\tisNotAWordformInDb)?/",
		 $sString, $aMatches);
  for($i=0 ; $i < count($aMatches[1]); $i++) {
    // Here we print:
    // <span id=contextWord_ONSET_OFFSET onClick=...>wordForm</span>
    //
    $sClassName =
      (isGroupMember($oGroupOnOffsets, $aGroupOnOffsetsRow, $iHeadWordOnset,
		     $iHeadWordOffset, $aMatches[2][$i], $aMatches[3][$i],
		     $sGroupAnalyses, $iSentenceNr))
      ? "contextWord_" : "contextWord";

	  
    $sPart .= " <span class=$sClassName id=contextWord_" . $aMatches[2][$i] .
      "_" . $aMatches[3][$i] . " onClick=\"" .
      "if( (iHeldDownKey == 120) || (iHeldDownKey == 113) ) {";
    if( strlen($aMatches[4][$i]) == 0 )
      $sPart .= " var bInGroup = (this.className == 'contextWord_'); ".
	" toggleGroup(this, $iDocumentId, $iSentenceNr, $iHeadWordOnset, ".
	"$iHeadWordOffset, " . $aMatches[2][$i] . ", " . $aMatches[3][$i] .
	"," . " bInGroup);}\"";
    else
      $sPart .= " alert('This token is not a word form in the " .
	"database (maybe it is only punctuation?). " .
	"You can not add it to a group');}\"";
	
    $sPart .= ">" . $aMatches[1][$i] . "</span>";
	
	// ***** begin part for getLateralTokenAttestations ***** 
	
	// functionality is built, but not yet in use
	//                         ------------------
	// BEWARE: this particular piece of code is NOT UP-TO-DATE anymore. It has a BUG since it makes use of untokenized words
	//    so looking up the words in the wordforms tables sometimes fails due to punctuation attached to word forms.
	//    This causes the context information to be sometimes incomplete.
	// Solution:
	//    A CORRECT implementation of this code is to be found in function 'makePrintableWithTags' in this file.
	//    Code correction implies: one more group in regex that catches tokens and onset/offsets
	//    and also change of indexes in $aMatches[..] since one more group was added in the regex!
	$useLateralTokenAttestations = false;
	
	if ($useLateralTokenAttestations) // BEWARE:   buggy, read comment hereabove!
	{
		$sLateralToken = $aMatches[1][$i]."&nbsp;&lt;?&gt;";
		if( $GLOBALS['bFullAnalyses'] )
		{
			$oLateralResult = getLateralTokenAttestations($iDocumentId, $aMatches[1][$i], $aMatches[2][$i]);
			
			if ($oLateralResult)
				{
				$oLastRow = 0;
				while( ($aRow = getRow($oLateralResult, $aMatches[2][$i], $oLastRow)) ) {
					$sLateralToken = $aRow['analysesForSentence'];
					};
				}
		}
		
		$sPartWithTags .= " <span class=$sClassName id=contextWord_" . $aMatches[2][$i] .
		  "_" . $aMatches[3][$i] . " onClick=\"" .
		  "if( (iHeldDownKey == 120) || (iHeldDownKey == 113) ) {";
		if( strlen($aMatches[4][$i]) == 0 )
		  $sPartWithTags .= " var bInGroup = (this.className == 'contextWord_'); ".
		" toggleGroup(this, $iDocumentId, $iSentenceNr, $iHeadWordOnset, ".
		"$iHeadWordOffset, " . $aMatches[2][$i] . ", " . $aMatches[3][$i] .
		"," . " bInGroup);}\"";
		else
		  $sPartWithTags .= " alert('This token is not a word form in the " .
		"database (maybe it is only punctuation?). " .
		"You can not add it to a group');}\"";
		
		$sPartWithTags .= ">" . $sLateralToken . "</span><br>"; 
	}
	
	// ***** end part for getLateralTokenAttestations ***** 
	
	
    if( $bBuildIndex) {
      // NOTE that we delete any tags that are there because they mess up the
      // (apparent) sorting order
      $sWord = preg_replace("/<[^>]+>/", '', $aMatches[1][$i]);
      if( $sSortSentencesBy == 'right' )
	// Append
	$aRows[$iSentenceNr]["index"] .= " $sWord";
      else
	// Prepend
	$aRows[$iSentenceNr]["index"]="$sWord ". $aRows[$iSentenceNr]["index"];
    }
  }
  return $sPart . "<br>" . $sPartWithTags;
}




function makePrintableWithTags($sDatabase, $iDocumentId, $sString, $centralWordNumber) {
  
  chooseDb($sDatabase);  
  
  $sPartWithTags = '';
  // This regular expression takes the newline, the canonical word form and 
  // the wordform as in the text, which printed.
  // Only complete words are printed. This is to avoid things like
  //  <ne tag="">Hello</ne>
  // to mess up things (especially with quotes)
  
  // OLD: preg_match_all("/[\r\n]+[^\n\r\t]+\t([^\n\r\t]+)\t(\d+)\t(\d+)(\tisNotAWordformInDb)?/",
  //  FIRST PART of the REGEX removed because otherwise the first word was never shown
  preg_match_all("/([^\n\r\t]+)\t([^\n\r\t]+)\t(\d+)\t(\d+)(\tisNotAWordformInDb)?/",
		 $sString, $aMatches, PREG_OFFSET_CAPTURE);
  for($i=0 ; $i < count($aMatches[2]); $i++) {
    // Here we print:
    // <span id=contextWord_ONSET_OFFSET onClick=...>wordForm</span>
    //
    $sClassName = "contextWord";
	
	// ***** begin part for getLateralTokenAttestations *****  	
	$sTokenWithTag = $aMatches[2][$i][0]."&nbsp;&lt;?&gt;";
	if( $GLOBALS['bFullAnalyses'] )
	{
	
		$oLateralResult = getLateralTokenAttestations($iDocumentId, $aMatches[1][$i][0], $aMatches[3][$i][0]);
		
		if ($oLateralResult)
			{
			$oLastRow = 0;
			while( ($aRow = getRow($oLateralResult, $aMatches[3][$i][0], $oLastRow)) ) {
				$sTokenWithTag = $aRow['analysesForSentence'];
				};
			}
	}
	// if the current word is the main word, get it highlighted
	if ($i == $centralWordNumber)
		$sClassName = "matchedPart";
		
	// span of the word
	$sPartWithTags .= " <span class=$sClassName id=contextWord_" . $i . "_" . $centralWordNumber. "_" .
	$aMatches[3][$i][0] . "_" . $aMatches[4][$i][0] . ">" . $sTokenWithTag . "</span><br>"; 
	  
	// ***** end part for getLateralTokenAttestations ***** 

  }
  return $sPartWithTags;
}



// For use in moreContext.php
function makePrintable_simple( $sString) {
  $sPart = '';
  // This regular expression takes the newline, the canonical word form and 
  // the wordform as in the text, which printed.
  // Only complete words are printed. This is to avoid things like
  //  <ne tag="">Hello</ne>
  // to mess up things (especially with quotes)
  
  // OLD: preg_match_all("/[\r\n]+[^\n\r\t]+\t([^\n\r\t]+)\t(\d+)\t(\d+)/",
  //  FIRST PART of the REGEX removed because otherwise the first word was never shown
  preg_match_all("/[^\n\r\t]+\t([^\n\r\t]+)\t(\d+)\t(\d+)/",
		 $sString, $aMatches);
  foreach($aMatches[1] as $aMatch) {
    $sPart .= " $aMatch";
  }
  return $sPart;
}

// It comes in |-separated tuples of <start_pos, end_pos, verified_by>
//
// A string like "1,3,234|6,8,234" becomes a multi-dimensional array like:
//  [0] => [1, 3, 234]
//  [1] => [6, 8, 234] 
// Sadly enough MySQL can't deliver the list sorted, so we sort it here. 
function explodeTokenAtts($sVerifiedTokenAtts) {
  $aVerifiedTokenAtts = array();
  if( $sVerifiedTokenAtts ) {
    $aTokenAtts = explode("|", $sVerifiedTokenAtts);
    sort($aTokenAtts, SORT_NUMERIC);
    foreach( $aTokenAtts as $sPosses) {
      array_push($aVerifiedTokenAtts, explode(",", $sPosses));
    }
    return $aVerifiedTokenAtts;
  }
  return 0;
}

// Actually, the offsets are redundant. Just checking the onsets suffices.
//
function isGroupMember($oGroupOnOffsets, &$aGroupOnOffsetsRow, $iHeadWordOnset,
		       $iHeadWordOffset, $iWordFormOnset, $iWordFormOffset,
		       &$sGroupAnalyses, $iSentenceNr) {
  // No need to go checking everything when it's irrelevant
  if( $iHeadWordOnset < $aGroupOnOffsetsRow['currentWordOnset'])
    return false;

  if( $aGroupOnOffsetsRow ) {
    // Go through all the ones of the previous sentence (that were beyond
    // context window)
    while( $aGroupOnOffsetsRow &&
	   ($aGroupOnOffsetsRow['currentWordOnset'] < $iHeadWordOnset) ) {
      printLog("Skipping " . $aGroupOnOffsetsRow['currentWordOnset'] .
	       "< $iHeadWordOnset (headword)\n");
      $aGroupOnOffsetsRow = mysql_fetch_assoc($oGroupOnOffsets);
    }
    // Go through all the ones of the current sentence which are before the
    // context window
    while( $aGroupOnOffsetsRow &&
	   ($aGroupOnOffsetsRow['currentWordOnset'] == $iHeadWordOnset) && 
	   ($aGroupOnOffsetsRow['groupMemberOnset'] < $iWordFormOnset) ) {
      printLog("Skipping ($iSentenceNr)" .
	       $aGroupOnOffsetsRow['groupMemberOnset'] .
	       "< $iWordFormOnset (word form)\n");
      $aGroupOnOffsetsRow = mysql_fetch_assoc($oGroupOnOffsets);
    }
    if( $aGroupOnOffsetsRow &&
	($aGroupOnOffsetsRow['currentWordOnset'] == $iHeadWordOnset) &&
	($aGroupOnOffsetsRow['groupMemberOnset'] == $iWordFormOnset) ) {
      return 1;
    }
  }
  return false;
}


// this function seems to be abandonned! Never called
function addGroupAnalyses(&$sGroupAnalyses, $sGroupAnalysesForWf,
			  $iSentenceNr) {
  $aGroupAnalyses = explode('|', $sGroupAnalysesForWf);
  $sSeparator = (strlen($sGroupAnalyses)) ? '<br>': '';
  foreach($aGroupAnalyses as $sGroupAnalysis) {
    // It comes as analyzed_wordfrom_id<pound-sign>analysis
    $aAnalysis = explode('#', $sGroupAnalysis);
    // Clickable token attestations
    $sGroupAnalyses .= "$sSeparator<span style=\"cursor: 'pointer';\" " .
      "class=clickableTokenAtt onmouseover=\"javascript: ".
      "this.style.cursor = 'pointer'; this.className = 'clickableTokenAtt_';\""
      . " onmouseout=\"javascript: this.className = 'clickableTokenAtt';\" " .
      "onclick=\"javascript: removeTokenAttestation($iSentenceNr, " .
      $aAnalysis[0] . ");\">". $aAnalysis[1] . "</span>";
    $sSeparator = '<br>';
  }
}



function makeNonBreakable($sString) {
  return str_replace(array(" ", "\n", "\t"), "&nbsp;", $sString);
}

function toggleGroup($iUserId, $iDocumentId, $iWordFormId, $iSentenceNr,
		     $iHeadWordOnset, $iHeadWordOffset, $iOnset, $iOffset,
		     $bInGroup) {
  if( $bInGroup )
    deleteFromGroup($iUserId, $iDocumentId, $iHeadWordOnset, $iHeadWordOffset,
		    $iOnset, $iOffset);
  else
    addToGroup($iUserId, $iDocumentId, $iHeadWordOnset, $iHeadWordOffset,
	       $iOnset, $iOffset);

  printTokenAttestations($iDocumentId, $iSentenceNr, $iWordFormId,
			 $iHeadWordOnset, $iHeadWordOffset);
}

function changeWordForm($sDatabase, $iUserId, $iOldWordFormId, $sOldWordForm,
			$sNewWordForm, $sSelectedSentences) {
			
			
  // Are we operating in full database mode (no files)?
  $bFullDatabaseMode = fullDatabaseMode($sDatabase);

  printLog("changeWordForm($iUserId, $iOldWordFormId, '$sOldWordForm', " .
	   "'$sNewWordForm', '$sSelectedSentences')\n");

  // For some robustness, normalise the input.
  // Leading and trailing spaces are already deleted in the Javascript.

  // First, if something typed in something totally meaningless, such as
  // some '|'s and spaces in a row, replace that by just one '|'.
  // This will take care of any spaces surrounding a '|' and of multiple
  // '|'s in a row.
  $sNewWordForm = preg_replace("/(\s*\|\s*)+/", '|', $sNewWordForm);
  // Consecutive spaces should just be one space. 
  $sNewWordForm = preg_replace("/\s+/", ' ', $sNewWordForm);

  $iOffsetChange = 0;
  // First make a new folder (if necessary) to store a temporary file in.
  $sTmpFolder = $GLOBALS['sDocumentRoot'] . "/tmpFolder";
  if( ! is_dir($sTmpFolder) )
	mkdir($sTmpFolder);
  $sTmpFile = "$sTmpFolder/tmp.tab"; // Tmp file

  // Here we split
  // 'documentId1,startPos1,endPos1|documentId2,startPos2,endPos2|etc...'
  // into an array.
  $aSelectedSentences = array_unique(explode('|', $sSelectedSentences));
  // Sort selecteds by docId, and then onset
  usort($aSelectedSentences, "cmpSelecteds");

  $iPreviousDocId = -1;
  $sLine;
  $fhFile = FALSE;
  $fhTmpFile = FALSE;
  $sDocPath;
  // when in full database mode, the sDocPath refers to a copy of the database document on disk,
  // but when writting this working copy back into the database, we need to know the original
  // $sDocPath. So we store $sDocPath into $sOriginalDocPath before changing $sDocPath to the working copy.
  $sOriginalDocPath; 
  $aChanges = array();
  $aNewOnsetOffsets = array();
  $bError = false;
  for($i = 0; $i < count($aSelectedSentences); $i++ ) {
	list($iDocId, $iOnset, $iOffset) = explode(',', $aSelectedSentences[$i]);

	if( $iPreviousDocId != $iDocId ) { // We are done with this file
	  if( $fhFile) { // Not the case the very first time
		roundUp($bFullDatabaseMode, $fhFile, $iOffsetChange, $sOriginalDocPath, $sDocPath, $fhTmpFile,
			$sTmpFile); // Print the rest
	  }
	  $sDocPath = getDocumentPath($iDocId); // Open new file
	  printLog("Opening $sDocPath\n");		  
	  
	  // If in full database mode, we need to make a temp copy of the database file.
	  // This way, we can process the document content the same way
	  // as in the 'normal' mode, t.i. when files are NOT stored in the database
	  // (so we have to read those from the disk)
	  if ($bFullDatabaseMode)
		{
		printLog("Getting $sDocPath from the database\n");
		$sDocContent = getDocumentContentFromDb($sDocPath);
		$sOriginalDocPath = $sDocPath;
		$sDocPath = "$sTmpFolder/tmp_file_from_db.tab";
		printLog("The temp copy of $sOriginalDocPath \n will be $sDocPath\n");
		$fhTmpFileFromDb = fopen($sDocPath, "w+");
		fwrite($fhTmpFileFromDb, $sDocContent);
		fclose($fhTmpFileFromDb);
		}
	  else
	    {
		printLog("Getting $sDocPath from disk\n");
		}
	  // read the document from the disk (in full database mode, a copy of the database file on disk was made just now)
	  $fhFile = fopen($sDocPath, "r");
		
		
	  if( $fhFile === FALSE ) {
		printToScreenAndLog("ERROR: Error while trying to change " .
					"'$sOldWordForm' to '$sNewWordForm'. " .
					"Unable to open file '$sDocPath' " .
					"(docId: $iDocId) for reading" .
					($bFullDatabaseMode ? " (this file is a temp copy of the database content)":"").". " .
					"Maybe somebody else changed a word form in this " .
					"file just now. In that case reloading and " .
					"trying again might help. If the problem persists ".
					"something else went wrong. Quitting now. " .
					"Please report the problem!\n");
		$bError = true;
		break;
	  }
	  $fhTmpFile = fopen($sTmpFile, "w+");
	  if( $fhTmpFile === false) {
		printToScreenAndLog("ERROR: Error while trying to change " .
					"'$sOldWordForm' to '$sNewWordForm'. " .
					"Unable to open file '$sTmpFile' for writing".
					($bFullDatabaseMode ? " (this file is a temp working copy of the database content)":"").". " .
					"Maybe somebody else changed a word form in this " .
					"file just now. In that case reloading and " .
					"trying again might help. If the problem persists ".
					"something else went wrong. Quitting now. " .
					"Please report the problem!\n");
		$bError = true;
		break;
	  }
	  $iPreviousDocId = $iDocId;
	  $iOffsetChange = 0; // 0 again for the new file
	}

	while(!feof($fhFile)) {
	  $sLine = fgets($fhFile);

	  // Here we do some strpos'ing and substringing to fetch the onset from
	  // the line. We do it like this rather than with regular expression
	  // matching  because we have to do it possibly on every line in the file.
	  $iSecondTabPos = strpos($sLine, "\t", strpos($sLine, "\t") + 1) + 1;
	  $iThirdTabPos = strpos($sLine, "\t", $iSecondTabPos);
	  $iLineOnset =
		substr($sLine, $iSecondTabPos, $iThirdTabPos - $iSecondTabPos);
	  if( $iLineOnset == $iOnset ) {
		$iOffsetChange += alignTokens($iDocId, $sLine, $sNewWordForm,
					  $fhTmpFile, $iOffsetChange,
					  $aNewOnsetOffsets);
		updateChangeArray($aChanges, $iDocId, $iOnset, $iOffsetChange);
		break;
	  }
	  elseif( $iLineOnset > $iOnset) { // Something went wrong
		printToScreenAndLog("ERROR: Error while trying to change " .
					"'$sOldWordForm' to '$sNewWordForm'. There was no ".
					"line with onset $iOnset in '$sDocPath'. " .
					"Maybe somebody else changed a word form in this " .
					"file just now. In that case reloading and " .
					"trying again might help. If the problem persists ".
					"something else went wrong. Quitting now. " .
					"Please report the problem!\n");
		$bError = true;
		break;
	  }
	  else
		writeLine($fhTmpFile, $iOffsetChange, $sLine);
	}
	if($bError)
	  break ;
  }

  if(! $bError) {
	// A file should still be open
	roundUp($bFullDatabaseMode, $fhFile, $iOffsetChange, $sOriginalDocPath, $sDocPath, $fhTmpFile, $sTmpFile);

	if( $GLOBALS['sLogFile'] ) {
	  printLog("Van tevoren aNewOnsetOffsets:\n");
	  foreach($aNewOnsetOffsets as $sDcIdOnset => $aNwOnsetOffsets) {
		printLog("\t$sDcIdOnset =>\n");
		foreach($aNwOnsetOffsets as $aNwOnsetOffset) {
		  printLog("\t\t". $aNwOnsetOffset[0] . ", " . $aNwOnsetOffset[1] .
			   "\n");
		}
	  }

	  printLog("aChanges:\n");
	  foreach( $aChanges as $iDocId => $aChange) {
		printLog("$iDocId =>\n");
		for($i = 0; $i < count($aChange); $i++) {
		  printLog("\t". $aChange[$i][0] . " => " . $aChange[$i][1] . "\n");
		}
	  }
	}

	$aNewWordForms = preg_split("/[\s\|]+/", $sNewWordForm);

	printLog("aNewWordForms:\n");
	for($i = 0; $i < count($aNewWordForms); $i++) {
	  // A bit ad hoc 
	  if( isset($GLOBALS['bNormalizeToken']) && $GLOBALS['bNormalizeToken'] )
		$aNewWordForms[$i] = normalizeToken($aNewWordForms[$i]);
	  printLog("\t'" . $aNewWordForms[$i] . "'\n");
	}

	cwUpdateDatabase($iUserId, $aChanges, $aNewWordForms, $iOldWordFormId,
			 $iOffsetChange, $aNewOnsetOffsets);
  }

  
  
}




function updateChangeArray(&$aChanges, $iDocId, $iOnset, $iOffsetChange) {
  printLog("updateChangeArray: $iDocId, $iOnset, $iOffsetChange\n");
  if( array_key_exists($iDocId, $aChanges) ) {
    array_push($aChanges[$iDocId], array($iOnset, $iOffsetChange));
  }
  else
    $aChanges[$iDocId] = array(array($iOnset, $iOffsetChange));    
}

function roundUp($bFullDatabaseMode, $fhFile, $iOffsetChange, $sOriginalDocPath, $sDocPath, $fhTmpFile, $sTmpFile) {
  printLog("roundUp($bFullDatabaseMode, FH_FILE, $iOffsetChange, '$sOriginalDocPath', '$sDocPath', FH_TMP, " .
	   "'$sTmpFile')\n");
	
	// write the end of the file (with modified offset) and close it
  while(!feof($fhFile)) {
    $sLine = fgets($fhFile);
    if( strlen($sLine) > 1) // More than just \n
      writeLine($fhTmpFile, $iOffsetChange, $sLine);
  }
  printLog("Closing $sDocPath\n");
  fclose($fhFile);
  fclose($fhTmpFile);

  // If we are NOT in full database mode, but in file mode
  // make a backup of the original file, and make of the working copy the active file
  if ( !$bFullDatabaseMode)
	  {
	  // We move the original file (if this didn't happen already)
	  $sOriginal_backUp = $sDocPath . "_original.tab";
	  if( ! is_file($sOriginal_backUp) ) {
		  rename($sDocPath, $sOriginal_backUp) or
		  printLog("ERROR: Couldn't move $sDocPath to $sOriginal_backUp.\n");
		}
	  // Now, move the the new file to the original
	  rename($sTmpFile, $sDocPath) or
	  printLog("ERROR: Couldn't move $sTmpFile to $sDocPath.\n");
	  }
	  
  // If in full database mode, we copy the temp file, which is a 
  // modified working copy of document stored in the database,
  // back into the database, and delete the working copy
  else
  {
    // read the working copy and put it back into the database
	$sFileContent = readFileContent($sTmpFile);
	updateDocumentContentInDb($sOriginalDocPath, $sFileContent);
	// Delete the temp files	
	$sPathOfDocFromDb = str_replace("/tmp.tab", "/tmp_file_from_db.tab", $sTmpFile);
	unlink($sPathOfDocFromDb);
	unlink($sTmpFile);
  }  
  
}



function writeLine($fhTmpFile, $iOffsetChange, $sLine) {
  if( $iOffsetChange == 0)
    fwrite($fhTmpFile, $sLine);
  else {
    // Made compliant with position information
    // OLD: if(preg_match("/^(.+\t)(\d+)\t(\d+)(\t\d+\t\d+\t\d+\t\d+)$/",
    if(preg_match("/^([^\t]+\t[^\t]+\t)(\d+)\t(\d+)(\t.*)$/",
		  $sLine, $aMatches) ) {
      fwrite($fhTmpFile,
	     $aMatches[1] . ($aMatches[2] + $iOffsetChange) . "\t" .
	     ($aMatches[3] + $iOffsetChange) . $aMatches[4] . "\n");
    }
    else {
      // Non-position info case
      // OLD: if(preg_match("/^(.+\t)(\d+)\t(\d+)$/", $sLine, $aMatches) )
      if(preg_match("/^([^\t]+\t[^\t]+\t)(\d+)\t(\d+)$/", $sLine, $aMatches) )
		fwrite($fhTmpFile,
			   $aMatches[1] . ($aMatches[2] + $iOffsetChange) . "\t" .
			   ($aMatches[3] + $iOffsetChange) . "\n");
    }
  }
}

function cmpSelecteds($a, $b) {
  $iPos_a = strpos($a, ",");
  $iDocId_a = substr($a, 0, $iPos_a);

  $iPos_b = strpos($b, ",");
  $iDocId_b = substr($b, 0, $iPos_b);

  if( $iDocId_a != $iDocId_b)
    return $iDocId_a - $iDocId_b;

  $iPos_a++;
  $iPos_b++;

  $iOnset_a = substr($a, $iPos_a, strpos($a, ",", $iPos_a) - $iPos_a);
  $iOnset_b = substr($b, $iPos_b, strpos($b, ",", $iPos_b) - $iPos_b);

  return $iOnset_a - $iOnset_b;
}

/**** End functions for sentence/token attestation part **********************/


/**** File browser stuff *****************************************************/

function fillFileBrowser($sDatabase, $iUserId, $sUserName, $iCorpusAddedTo) {

  if( ($oResult = getCorpora()) ) {
    while( ($aRow = mysql_fetch_assoc($oResult)) ) {
      print "<div class=corpusRow>" .
		"<span class=showCorpusFiles id=corpus_" . $aRow['corpus_id'] .
		" onmouseOver=\"javascript: this.style.cursor = 'pointer';\"" .
		" onClick=\"javascript: toggleCorpusFiles(this, " . $aRow['corpus_id'].
		// White space for the background image
		");\">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>" .
		// Link for working on the corpus in the tool
		"<a href='./lexiconTool.php?sDatabase=$sDatabase&iUserId=$iUserId" . 
		"&sUserName=$sUserName&iCorpusId=" . $aRow['corpus_id'] .
		"&sCorpusName=" . $aRow['name'] . "'>" . $aRow['name'] . "</a>" .
		// Remove corpus
		"<span title='Remove corpus'" .
		" onmouseOver=\"javascript: this.style.cursor = 'pointer';\"" .
		" onClick=\"javascript: " .
		"var bYes = confirm('Do you really want to delete corpus \\'" .
		$aRow['name'] . "\\'');" .
		"if( bYes ) removeCorpus(" . $aRow['corpus_id'] . ", '" .
		$aRow['name'] . "');\">&nbsp;<img src='./img/remove.png'></span>" .
		// End div
		"</div>";
		  if( $iCorpusAddedTo == $aRow['corpus_id']) { // Expand it if necessary
		print "<div id=corpusFiles_" . $aRow['corpus_id'] . ">\n";
		showCorpusFiles($sDatabase, $iUserId, $sUserName, $iCorpusAddedTo);
		print "</div>\n";
      }
      else // Hidden stub for the files
		print "<div id=corpusFiles_" . $aRow['corpus_id'] .
		  " style='display: none;'></div>\n";
    }
    mysql_free_result($oResult);

    // Progress bar
    print "<div id=progressBar></div>";
  }
}

function handleUploadFileError($iErrorCode) {
  switch ($iErrorCode) {
  case UPLOAD_ERR_INI_SIZE:
    $sMessage = "The uploaded file exceeds the upload_max_filesize directive".
      "in php.ini";
    break;
  case UPLOAD_ERR_FORM_SIZE:
    $sMessage = "The uploaded file exceeds the MAX_FILE_SIZE directive " .
      "that was specified in the HTML form";
    break;
  case UPLOAD_ERR_PARTIAL:
    $sMessage = "The uploaded file was only partially uploaded";
    break;
  case UPLOAD_ERR_NO_FILE:
    $sMessage = "No file was uploaded";
    break;
  case UPLOAD_ERR_NO_TMP_DIR:
    $sMessage = "Missing a temporary folder";
    break;
  case UPLOAD_ERR_CANT_WRITE:
    $sMessage = "Failed to write file to disk";
    break;
  case UPLOAD_ERR_EXTENSION:
    $sMessage = "File upload stopped by extension";
    break;
  default:
    $sMessage = "Unknown upload error";
    break;
  }
  print "$sMessage<br>\n";
}

/****************************/

// check if we are in full database mode (physical files are loaded into the database)
// or not (physical files are NOT loaded into the database)
// The default value is 'true'
function fullDatabaseMode($sDatabase){

	$aTmpFullDatabaseMode = $GLOBALS['aFullDatabaseMode'];
	
	if ( isset($aTmpFullDatabaseMode) )
		{
		if ( isset($aTmpFullDatabaseMode[ $sDatabase ]) )
			{			
			return $aTmpFullDatabaseMode[ $sDatabase ];
			}
		}		
	
	return true;
}

/*****************************************************************************/

function printJavascriptGlobals($sDatabase, $iUserId, $sUserName, $iId, $sName,
				$sMode, $sFilter, $bCaseInsensitivity,
				$sLemmaFilter, $sSortBy, $sSortMode,
				$bSortReverse, $sSortSentencesBy,
				$sSortSentencesMode, $bDoShowAll,
				$bDoShowCorpus, $bDoShowDocument,
				$iNrOfWordFormsPerPage,
				$iNrOfSentencesPerWordform, $iAmountOfContext, $sGoto){
  $sCaseInsensitivity = ($bCaseInsensitivity) ? 'true' : 'false';
  print "<script type=\"text/javascript\">\n" .
    "var sDatabase = '$sDatabase'; var iUserId = $iUserId;\n" .
    "var sUserName = '$sUserName';\n" .
    "var iId = $iId; var sName = '$sName'; var sMode = '$sMode';\n" .
    "var sGoto = '$sGoto';\n" .
    "var sFilter = '$sFilter';\n" .
    "var bCaseInsensitivity = $sCaseInsensitivity;\n" .
    "var bCheckAnalysisFormatValidity = " .
    $GLOBALS['bCheckAnalysisFormatValidity'] . ";\n" .
    "var sLemmaFilter = '$sLemmaFilter';\n" .
    "var sSortBy = '$sSortBy'; var sSortMode = '$sSortMode';\n" .
    "var bDoShowAll = $bDoShowAll; var bDoShowCorpus = $bDoShowCorpus;\n" .
    "var bDoShowDocument = $bDoShowDocument;\n" .
    "var iNrOfWordFormsPerPage = '$iNrOfWordFormsPerPage';\n" .
    "var iNrOfSentencesPerWordform = '$iNrOfSentencesPerWordform';\n" .
    "var sSortSentencesBy = '$sSortSentencesBy';\n" .
    "var sSortSentencesMode = '$sSortSentencesMode';\n" .
    "var iAmountOfContext = '$iAmountOfContext'; var bSortReverse = ";
  // No quotes, these are booleans
  print ($bSortReverse) ? "true;" : "false;";
  print "\n</script>\n";
}

?>