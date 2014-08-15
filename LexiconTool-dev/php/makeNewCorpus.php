<?php

/// DEze hele file kan weg volgens mij... ////

// 2013: MF:  seems not to be in use anymore //

require_once('databaseUtils.php');

chooseDb($_REQUEST['sDatabase']);

// We don't check whether the right variables are provided for.
// They just should be there.

// Insert a new corpus
$sInsertQuery = "INSERT INTO corpora (name) VALUES ('" .
  $_REQUEST['sCorpusName'] . "')";

doNonSelectQuery($sInsertQuery);

// Make an array again of the document identifiers
$aDocumentIds = explode(",", $_REQUEST['sDocumentIds']);

// Get the identifier of the record just created
$iCorpusId = 0;
if( ($oResult = doSelectQuery("SELECT LAST_INSERT_ID();") ) ) {
  if( ($oRow = mysql_fetch_assoc($oResult)) )
    $iCorpusId = $oRow['LAST_INSERT_ID()'];
  mysql_free_result($oResult);
}

if( $iCorpusId ) {
  // Build the query
  $sInsertQuery = 
    "INSERT INTO corpusId_x_documentId (corpus_id, document_id) VALUES";
  $cSeparator = '';
  foreach( $aDocumentIds as $iDocumentId ) {
    $sInsertQuery .= "$cSeparator ($iCorpusId, $iDocumentId)";
    $cSeparator = ",";
  }
  // Insert the lot
  doNonSelectQuery($sInsertQuery);
  // Return the id
  print "New corpus: " . $iCorpusId;
}
else {
  print "Something went wrong in creating a new corpus\n";
}


