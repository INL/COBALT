<?php

require_once('globals.php');
require_once('lemmaTupleString2array.php');

// Set the global database handler
$dbh = mysql_connect($GLOBALS['sDbHostName'], $GLOBALS['sDbUserName'],
		     $GLOBALS['sDbPassword'])
  or trigger_error(mysql_error(), E_USER_ERROR);

function chooseDb($sDatabase) {
  mysql_select_db($sDatabase, $GLOBALS['dbh']);
  mysql_query('SET NAMES utf8');

  getTokenDbDatabaseId($sDatabase);
}

function getUserId($sDatabase, $sUserName) {
  chooseDb($sDatabase);
  $iReturn = false;
  $sSelectQuery = "SELECT user_id FROM users WHERE name = '$sUserName'";
  if( ($oResult = doSelectQuery($sSelectQuery)) ) {
    if( ($aRow = mysql_fetch_assoc($oResult)) )
      $iReturn = $aRow['user_id'];
    mysql_free_result($oResult);
  }
  return $iReturn;
}

function getWordFormIds($sWordFormValues, &$aTypes) {
  $sSelectQuery = "SELECT wordform_id, wordform FROM wordforms " .
    "WHERE wordform COLLATE utf8_bin IN ($sWordFormValues)";
  if( ($oResult = doSelectQuery($sSelectQuery) ) ) {
    while( ($aRow = mysql_fetch_assoc($oResult)) ) {
      $aTypes[$aRow['wordform']]['wordFormId'] = $aRow['wordform_id']; 
    }
  }
}

function insertWordForms($sWordFormValues) {
  $sInsertQuery = "INSERT INTO wordforms (wordform, wordform_lowercase) " .
    "VALUES $sWordFormValues " .
    "ON DUPLICATE KEY UPDATE wordform_id = wordform_id";
  doNonSelectQuery($sInsertQuery);
}

// Get the identifier of the analyzed_wordforms record for the lemma
// associated with this word form and lemma pos
// (NOTE that the latter is NOT the analyzed word form pos)
function getAnalysedWordFormIdDerivationId_forLemmaTuple($iUserId,
							 $iWordFormId,
							 $aWordformIdsForGroups,
							 $sLemmaTuple) {
  printLog("Get analyzed word form id for '$sLemmaTuple'\n");

  $aLemmaArr = lemmaTupleString2array($sLemmaTuple, '', 0);

  if( $aLemmaArr )
    return getAnalysedWordFormIdDerivationId_forLemmaArr($iUserId,
							 $iWordFormId,
							 $aWordformIdsForGroups
							 , $aLemmaArr);
  else {
    printLog("No lemma array for '$sLemmaTuple'\n");
    return false;
  }
}

// Fill the global language array
function fillLanguages() {
  printLog("Filling language array.\n");
  $sSelectQuery = "SELECT language_id, language FROM languages";

  $GLOBALS['aLanguages'] = Array();
  if( ($oResult = doSelectQuery($sSelectQuery) ) ) {
    while( ($aRow = mysql_fetch_assoc($oResult)) ) {
      $GLOBALS['aLanguages'][$aRow['language']] = $aRow['language_id'];
    }
    mysql_free_result($oResult);
  }
}

// This function gets you an analysed word form identifier. If it is not there
// yet or derivations are missing, it generates records for the missing parts.
function getAnalysedWordFormIdDerivationId_forLemmaArr($iUserId, $iWordFormId,
						       $aWordformIdsForGroups,
						       $aLemmaArr) {
  // First get/make a lemma record, based on headword, pos, language, gloss
  $iLemmaId = getLemmaId_forLemmaArr($aLemmaArr, 'add');

  // Get/make derivation for modern wordform and/or patterns
  $iDerivationId = ( $aLemmaArr[1] || $aLemmaArr[2] )
    ? getDerivationId_forLemmaArr($aLemmaArr, 'add') : 0;

  // Then get/make an analysed wordform id
  $aAnalyzedWordFormIdsDerivationIds =
    array(array(getAnalysedWordFormId_forLemmaId($iUserId, $iWordFormId,
						 $iLemmaId, $aLemmaArr,
						 $iDerivationId),
		$iDerivationId));

  foreach($aWordformIdsForGroups as $aWordFormGroupId) {
    $iAnalyzedWordFormId =
      getAnalysedWordFormId_forLemmaId($iUserId,
				       $aWordFormGroupId[0],
				       $iLemmaId, $aLemmaArr,
				       $iDerivationId);
    array_push($aAnalyzedWordFormIdsDerivationIds,
	       array($iAnalyzedWordFormId,
		     $iDerivationId,
		     $aWordFormGroupId[1]));
  }

  return $aAnalyzedWordFormIdsDerivationIds;
}

// Get/make derivation for patterns
function getDerivationId_forLemmaArr($aLemmaArr, $sAddMode) {
  $iPatternApplicationId = false;
  // Get the pattern id's if needed
  if( $aLemmaArr[2] )  // Patterns
    $iPatternApplicationId =
      getPatternApplicationId($aLemmaArr);
 
  // See if it is in already
  $sSelectQuery = "SELECT derivation_id FROM derivations d " .
    "WHERE normalized_form ";
  $sSelectQuery .= ($aLemmaArr[1]) ? "= '$aLemmaArr[1]'" : "IS NULL";
  // Also, check if the patterns are associated or not as appropriate
  if( $aLemmaArr[2] ) // There are patterns
    $sSelectQuery .= " AND pattern_application_id = $iPatternApplicationId";
  else // No patterns
    $sSelectQuery .= " AND pattern_application_id = 0";

  if( ($oResult = doSelectQuery($sSelectQuery) ) ) {
    if( ($aRow = mysql_fetch_assoc($oResult)) ) {
      $iDerivationId = $aRow['derivation_id'];
      mysql_free_result($oResult);
      return $iDerivationId;
    }
  }

  // Stop if we don't have to add it.
  if( ($sAddMode == 'dontAdd') && ($iDerivationId === false) )
    return false;

  // If no result was returned, make new entries
  $sInsertQuery =
    "INSERT INTO derivations (normalized_form, pattern_application_id) " .
    "VALUES ( " ;
  $sInsertQuery .= ($aLemmaArr[1]) ? "'$aLemmaArr[1]', " : "NULL, ";
  $sInsertQuery .= ($aLemmaArr[2]) ? "$iPatternApplicationId)" : "0)";
  doNonSelectQuery($sInsertQuery);

  // We need the id of this last inserted row.
  // Sadly, we can't use PHP's built in mysql_insert_id() because we use
  // BIGINT's for the identifier.
  // (Please refer to the caution section on this page
  // http://php.net/manual/en/function.mysql-insert-id.php for additional info)
  $sSelectQuery = "SELECT LAST_INSERT_ID() AS last_insert_id";
  $iLastInsertId = 0;
  if( ($oResult = doSelectQuery($sSelectQuery) ) ) {
    if( ($aRow = mysql_fetch_assoc($oResult)) ) {
      $iLastInsertId = $aRow['last_insert_id'];
      mysql_free_result($oResult);
    }
  }
  else
    return false;

  return $iLastInsertId;
}

// Look up/make patterns
function getPatternApplicationId($aLemmaArr) {
  $aPatternIds = Array();
  $sInsertValues = $sInsertSeparator = $sConditionSeparator = $sCondition1 =
    $sCondition2 = '';
  $iPatternApplicationId = false;
  $iNrOfPatterns = count($aLemmaArr[2]);

  // The patterns come as an array of arrays of [lhs, rhs, position]. E.g:
  // [['ee','ey',0], ['th','t', 3], ...]
  foreach($aLemmaArr[2] as $aPattern) {
    $sInsertValues .= "$sInsertSeparator('$aPattern[0]', '$aPattern[1]')";
    $sInsertSeparator = ", ";
    
    $sCondition1 .= "$sConditionSeparator(left_hand_side = '$aPattern[0]' " .
      "AND right_hand_side = '$aPattern[1]')";
    $sConditionSeparator = " OR ";
  }
  
  // NOTE that we don't first select and then insert. We just insert right away
  // with an ON DUPLICATE KEY UPDATE part.
  $sInsertQuery = "INSERT INTO patterns (left_hand_side, right_hand_side) " .
    "VALUES $sInsertValues ON DUPLICATE KEY UPDATE pattern_id = pattern_id";
  doNonSelectQuery($sInsertQuery);
  
  // Get the pattern ids
  $sSelectQuery = "SELECT " .
    "CONCAT(left_hand_side, '_', right_hand_side) AS fullPattern, pattern_id" .
    " FROM patterns WHERE $sCondition1";
  if( ($oResult = doSelectQuery($sSelectQuery)) ) {
    while( ($aRow = mysql_fetch_assoc($oResult)) ) {
      $aPatternIds[strtolower($aRow['fullPattern'])] = $aRow['pattern_id'];
    }
    mysql_free_result($oResult);
  }

  // Make the right condition for the next query using the pattern id's
  for($i = 0; $i < $iNrOfPatterns; $i++) {
    $sCondition2 .= " AND EXISTS(SELECT pa.*" .
      " FROM pattern_applications pa, patterns p " .
      "WHERE patterns.pattern_id = " .
      $aPatternIds[strtolower($aLemmaArr[2][$i][0]."_".$aLemmaArr[2][$i][1])] .
      "  AND pa.position = " . $aLemmaArr[2][$i][2] .
      "  AND pa.pattern_application_id" .
      " = pattern_applications.pattern_application_id)";
  }

  // See if there is a pattern application with exactly these patterns, and
  // no others
  $sSelectQuery = "SELECT pattern_application_id" .
    " FROM pattern_applications, patterns " .
    "WHERE number_of_patterns = $iNrOfPatterns $sCondition2";
  if( ($oResult = doSelectQuery($sSelectQuery)) )
    if( ($aRow = mysql_fetch_assoc($oResult)) ) {
      $iPatternApplicationId = $aRow['pattern_application_id'];
      mysql_free_result($oResult);
      return $iPatternApplicationId;
    }

  // If not, INSERT a new pattern application
  // First one to get a new id
  $sFirstFullPattern = strtolower($aLemmaArr[2][0][0]."_".$aLemmaArr[2][0][1]);
  $sInsertQuery = "INSERT INTO pattern_applications" .
    " (pattern_application_id, position, pattern_id, number_of_patterns) " .
    " SELECT " .
    // Check if it MAX() is NULL (which it is if there is nothing there yet)
    "IF(MAX(pattern_application_id) IS NULL,1,MAX(pattern_application_id)+1)" .
    ", " . $aLemmaArr[2][0][2] . ", " . $aPatternIds[$sFirstFullPattern] .
    ", $iNrOfPatterns FROM pattern_applications";
  doNonSelectQuery($sInsertQuery);

  // Get the new pattern_application_id
  $sSelectQuery = "SELECT pattern_application_id FROM pattern_applications " .
    "WHERE position = " . $aLemmaArr[2][0][2] .
    "  AND pattern_id = " . $aPatternIds[$sFirstFullPattern] . 
    "  AND number_of_patterns = $iNrOfPatterns" .
    // There might be more but we need the one added last
    " ORDER BY pattern_application_id DESC LIMIT 1";

  if( ($oResult = doSelectQuery($sSelectQuery)) ) {
    if( ($aRow = mysql_fetch_assoc($oResult)) )
      $iPatternApplicationId = $aRow['pattern_application_id'];
    mysql_free_result($oResult);
  }
  
  // Now add the other ones if they are there
  if( $iNrOfPatterns > 1) {
    $sInsertQuery = "INSERT INTO pattern_applications " .
      "(pattern_application_id, position, pattern_id, number_of_patterns) " .
      "VALUES";
    $sInsertSeparator = '';
    for( $i = 1; $i < $iNrOfPatterns ; $i++) {
      $sInsertQuery .= "$sInsertSeparator($iPatternApplicationId, " .
	$aLemmaArr[2][$i][2] . ", " .
	$aPatternIds[strtolower($aLemmaArr[2][$i][0]."_".$aLemmaArr[2][$i][1])]
	. ", $iNrOfPatterns)";
      $sInsertSeparator = ', ';
    }
    doNonSelectQuery($sInsertQuery);
  }

  return $iPatternApplicationId;
}

// Get the identifier of the analyzed_wordforms record for the lemma associated
// with this word form
function getAnalysedWordFormId_forLemmaId($iUserId, $iWordFormId, $iLemmaId,
					  $aLemmaArr, $iDerivationId){
  $iAnalyzedWordFormId;

  // See if it is in already
  $sSelectQuery =
    "SELECT analyzed_wordforms.analyzed_wordform_id" .
    " FROM analyzed_wordforms " .
    "WHERE analyzed_wordforms.wordform_id = $iWordFormId" .
    " AND analyzed_wordforms.lemma_id = $iLemmaId" .
    " AND derivation_id = $iDerivationId";

  if( ($oResult = doSelectQuery($sSelectQuery) ) )
    if( ($aRow = mysql_fetch_assoc($oResult)) ) {
      $iAnalyzedWordFormId = $aRow['analyzed_wordform_id'];
      mysql_free_result($oResult);
      return $iAnalyzedWordFormId;
    }
 
  // If not, insert it (and verify it for this user)
  $sInsertQuery = "INSERT INTO analyzed_wordforms " .
    "(wordform_id, lemma_id, derivation_id, verified_by, verification_date) ".
    "VALUES ($iWordFormId, $iLemmaId, $iDerivationId, $iUserId, NOW())";
  doNonSelectQuery($sInsertQuery);

  // And try again
  if( ($oResult = doSelectQuery($sSelectQuery)) )
    if( ($aRow = mysql_fetch_assoc($oResult)) ) {
      $iAnalyzedWordFormId = $aRow['analyzed_wordform_id'];
      mysql_free_result($oResult);
      return $iAnalyzedWordFormId;
    }
  
  return false;
}

// Get the identifier of the multiple_analyses_parts record for the lemma
function getPartId($sLemmaTuple, $sAddMode) {
  // NOTE that we use lemmaTupleString2array here so somebody could
  // type in a derivation as well. It will be neglected completely though
  // as the multiple_analyses_parts table doesn't support it.
  // Languages *are* possible as they are stored at lemma level
  $aLemmaArr = lemmaTupleString2array($sLemmaTuple, '', 0);

  if( ! $aLemmaArr )
    return false;

  // First get/make a lemma record, based on headword, pos, language, gloss
  $iLemmaId = getLemmaId_forLemmaArr($aLemmaArr, $sAddMode);

  /// NEW
  // Make a derivation if necessary
  $iDerivationId = ( $aLemmaArr[1] || $aLemmaArr[2] )
    ? getDerivationId_forLemmaArr($aLemmaArr, $sAddMode) : 0;

  // Stop if we are just filtering and couldn't find it
  if( ($sAddMode == 'dontAdd') &&
      ( ($iLemmaId === false) || ($iDerivationId === false)) )
    return false;

  // Then get/make an multiple_lemmata_analysis_part
  $iPartId = getPartId_forLemmaId($iLemmaId, $iDerivationId, $sAddMode);

  return $iPartId;
}

function getPartId_forLemmaId($iLemmaId, $iDerivationId, $sAddMode) {
  // First see if it is there already
  $sSelectQuery = "SELECT multiple_lemmata_analysis_part_id " .
    "FROM multiple_lemmata_analysis_parts " .
    "WHERE lemma_id = $iLemmaId AND derivation_id = $iDerivationId";

  $iReturn = false;
  if( ($oResult = doSelectQuery($sSelectQuery) ) ) {
    if( ($aRow = mysql_fetch_assoc($oResult)) )
      $iReturn = $aRow['multiple_lemmata_analysis_part_id'];
    mysql_free_result($oResult);
  }
  printLog("Found derivation id: $iReturn.\n");

  if( ($iReturn === false) || (strlen($iReturn) == 0) ) {
    if($sAddMode == 'dontAdd') // If we are just checking, return 'not found'
      return false;
  }
  else // If a meaningful value was found
    return $iReturn;

  // If iReturn was false, but the add mode is 'add', then we insert
  $sInsertQuery = "INSERT INTO multiple_lemmata_analysis_parts " .
    "(lemma_id, derivation_id) VALUES ($iLemmaId, $iDerivationId)";
  doNonSelectQuery($sInsertQuery);

  // And try again
  if( ($oResult = doSelectQuery($sSelectQuery) ) ) {
    if( ($aRow = mysql_fetch_assoc($oResult)) )
      $iReturn = $aRow['multiple_lemmata_analysis_part_id'];
    mysql_free_result($oResult);
  }
  
  return $iReturn;
}

// Get the lemma id based on headword, pos, language, gloss
function getLemmaId_forLemmaArr($aLemmaArr, $sMode) {
  // See if it is in already
  $sSelectQuery = "SELECT lemma_id FROM lemmata " .
    "WHERE modern_lemma = '$aLemmaArr[0]'" .
    "  AND lemma_part_of_speech = '$aLemmaArr[3]'";
  // Language
  // Language id IS NULL is left out (i.e. no backwards compatibility)
  $sSelectQuery .= ($aLemmaArr[4])
    ? " AND language_id = $aLemmaArr[4]" : " AND language_id = 0";
  // Gloss
  $sSelectQuery .= ($aLemmaArr[5])
    ? " AND gloss = '$aLemmaArr[5]'" : " AND gloss = ''";

  if( ($oResult = doSelectQuery($sSelectQuery) ) )
    if( ($aRow = mysql_fetch_assoc($oResult)) )
      return $aRow['lemma_id'];
  
  if( $sMode == 'dontAdd') // E.g. when you filter on a lemma you just want to
    return false;          // know if it's there or not...

  // If it isn't, create one
  // Uppercase the POS if necessary.
  $sLemmaPos = ($GLOBALS['bUppercaseLemmaPos']) ? strtoupper($aLemmaArr[3]) :
    $aLemmaArr[3];
  $sInsertQuery = "INSERT INTO lemmata " .
    "(modern_lemma, lemma_part_of_speech, language_id, gloss) " .
    "VALUES ('$aLemmaArr[0]', '$sLemmaPos', ";

  // Language
  $sInsertQuery .= ( $aLemmaArr[4] ) ? "$aLemmaArr[4], " : "0, ";
  // Gloss
  $sInsertQuery .= ($aLemmaArr[5]) ? "'$aLemmaArr[5]')" : "'')";
  
  doNonSelectQuery($sInsertQuery);

  // And try again
  if( ($oResult = doSelectQuery($sSelectQuery) ) )
    if( ($aRow = mysql_fetch_assoc($oResult)) )
      return $aRow['lemma_id'];

  return false;
}

// Token database functions ///////////////////////////////////////////////////

function addTypeFrequencies_tokens($sDatabase, $iDocumentId, $aTypes,
				   $aOnOffsets) {
  $sValues = $sTokenInsertValues = '';
  $cSeparator = '';
  $iInsertMax = 100;
  $i = 0;

  // Set the global token database id variable and make a new one if necessary
  getTokenDbDatabaseId($sDatabase);

  $sTokenInsertQuery = "INSERT INTO " . $GLOBALS['sTokenDbName'] . ".tokens " .
    "(lexicon_database_id, document_id, wordform_id, onset, offset) VALUES ";
  foreach( $aTypes as $sType=>$aTypeData) {
    $sValues .= "$cSeparator ($iDocumentId, " . $aTypeData['wordFormId'] .
      ", " . $aTypeData['freq']  . ")";

    $sTokenInsertValues .= $cSeparator .
      str_replace("TOKEN_DB_ID, DOC_ID, WF_ID",
		  getTokenDbDatabaseId($sDatabase) . ", $iDocumentId, " .
		  $aTypeData['wordFormId'],
		  $aOnOffsets[$sType]);
    $cSeparator = ',';
    $i++;
    
    if( $i == $iInsertMax ) {
      insertTypeFrequencies($sValues);
      insertTokens($sTokenInsertQuery . $sTokenInsertValues);
      $sValues = $sTokenInsertValues = '';
      $cSeparator = '';
      $i = 0;
    }
  }

  if($i) { // Left overs
    insertTypeFrequencies($sValues);
    insertTokens($sTokenInsertQuery . $sTokenInsertValues);
  }
}

// This one sets the global variable and makes a new entry if necessary
function getTokenDbDatabaseId($sDatabase) {
  if( ! $GLOBALS['iTokenDbDatabaseId'] ) {
    $sSelectQuery = "SELECT lexicon_database_id" .
      " FROM " . $GLOBALS['sTokenDbName'] . ".lexicon_databases " .
      "WHERE name = '$sDatabase'";
    printLog("Doing (" . $GLOBALS['sTokenDbName'] . ") $sSelectQuery<br>\n");
    $oResult = mysql_query($sSelectQuery /*, $GLOBALS['dbhTokenDb']*/);
    printMySQLError($sSelectQuery);

    if( $oResult ) {
      if ($aRow = mysql_fetch_assoc($oResult))
	$GLOBALS['iTokenDbDatabaseId'] = $aRow['lexicon_database_id'];
      mysql_free_result($oResult);
    }

    if( $GLOBALS['iTokenDbDatabaseId'] )
      return $GLOBALS['iTokenDbDatabaseId'];

    // It didn't exist... we insert it
    $sInsertQuery = "INSERT INTO " . $GLOBALS['sTokenDbName'] .
      ".lexicon_databases (name) VALUES ('$sDatabase')";
    mysql_query($sInsertQuery /*, $GLOBALS['dbhTokenDb']*/ );
    printMySQLError($sInsertQuery);

    // And try selecting again
    printLog("Doing (" . $GLOBALS['sTokenDbName'] . ") $sSelectQuery<br>\n");
    $oResult = mysql_query($sSelectQuery /*, $GLOBALS['dbhTokenDb'] */);
    printMySQLError($sSelectQuery);

    if( $oResult ) {
      if ($aRow = mysql_fetch_assoc($oResult))
	$GLOBALS['iTokenDbDatabaseId'] = $aRow['lexicon_database_id'];
      mysql_free_result($oResult);
    }

    if( $GLOBALS['iTokenDbDatabaseId'] )
      return $GLOBALS['iTokenDbDatabaseId'];
  }
  else
    return $GLOBALS['iTokenDbDatabaseId'];
}

function insertTokens($sInsertQuery) {
  printLog("Doing (" . $GLOBALS['sTokenDbName'] . ") $sInsertQuery<br>\n");
  mysql_query($sInsertQuery);
  printMySQLError($sInsertQuery);
}

// End of token database function /////////////////////////////////////////////

function insertTypeFrequencies($sValues) {
  $sInsertQuery = "INSERT " .
    "INTO type_frequencies (document_id, wordform_id, frequency) " .
    "VALUES $sValues";
  printLog("Doing $sInsertQuery<br>\n");
  doNonSelectQuery($sInsertQuery);
  printMySQLError($sInsertQuery);
}

// Get all current attestations out of the database.
// Every word form in the document is in the type_frequencies table (also the
// unattested ones).
// If they are attested this means there is an analyzed wordform entry.
//
// Returns an array: [$oResult, $sLemmaFilterWordformIds]
//
function getWordsToAttest($iId, $sMode, $sSortBy, $sSortMode, $bSortReverse,
			  $sFilter, $bCaseInsensitivity, $sLemmaFilter,
			  $bDoShowAll, $bDoShowCorpus, $bDoShowDocument,
			  $iStartAt, $iStepValue, $iNrOfWordFormsPerPage) {
  printLog("getWordsToAttest($iId, '$sMode', '$sSortBy', '$sSortMode', " .
	   "'$sFilter', $bCaseInsensitivity, '$sLemmaFilter', $bDoShowAll, " .
	   "$bDoShowCorpus, $bDoShowDocument, $iStartAt, $iStepValue, " .
	   "$iNrOfWordFormsPerPage)\n");

  $sLemmaFilterWordformIds = false;
  $iLemmaFilter_lemmaId = '';
  if( strlen($sLemmaFilter) ) {
    list($sLemmaFilterWordformIds, $iLemmaFilter_lemmaId) =
      getLemmaFilterWordformsIds($sLemmaFilter);
    // If the user made typo or something, or the lemma just doesn't exist
    // we don't have to go through all the trouble below. We are done quickly
    if( ! $sLemmaFilterWordformIds)
      return array(false, $iLemmaFilter_lemmaId, false);
  }
  
  $sSortByClause = '';
  if( $sSortBy == 'wordForm')
    $sSortByClause = ( $bSortReverse)
      ? "REVERSE(wordForm_lowercase)" : "wordForm_lowercase";
  else // We sort by frequency
    $sSortByClause = "SUM(frequency)";

  $sExtraSort = ($sSortBy == 'frequency')
    ? ", wordForm_lowercase $sSortMode" : '';

  $sWordformIds =
    getWordFormIdsToAttest($iId, $sMode, $sSortBy, $sSortMode, $sSortByClause,
			   $sExtraSort, $sFilter, $bCaseInsensitivity,
			   $sLemmaFilterWordformIds, $bDoShowAll,
			   $bDoShowCorpus, $bDoShowDocument, $iStartAt,
			   $iStepValue, $iNrOfWordFormsPerPage);
  if( strlen($sWordformIds) == 0)
    return array(false, '', false);

  // Now that we know the relevant wordform id's let's find their analyses
  $sSelectQuery = "SELECT wordforms.wordform_id, wordforms.wordform, " .
    "analysesInCorpus.analysesInCorpus, analysesInDb.analysesInDb, " .
    "multipleLemmataAnalysesInCorpus.multipleLemmataAnalysesInCorpus, " .
    "multipleLemmataAnalysesInDb.multipleLemmataAnalysesInDb, " .
    "SUM(frequency) AS frequency, " .
    "dont_show.document_id, dont_show.corpus_id, dont_show.at_all " .
    "FROM ";
  if( $sMode == 'corpus')
    $sSelectQuery .= "corpusId_x_documentId, ";
  $sSelectQuery .= "type_frequencies " .
    // LEFT JOIN for dont_show
    " LEFT JOIN dont_show ON " .
    "    (dont_show.wordform_id = type_frequencies.wordform_id";

  /// Dit veranderd.
  if( $sMode == 'corpus' ) {
    $sSelectQuery .= " AND (dont_show.corpus_id = $iId " .
      "OR dont_show.at_all != 0) ";
  }
  else { // document mode
    $sSelectQuery .= " AND (dont_show.document_id = $iId " .
      "OR dont_show.at_all != 0) ";
  }

  $sSelectQuery .= "), " .
    // NOTE that wordforms is mentioned last for the LEFT JOINs
    "wordforms " .
    // LEFT JOIN for the multiple lemmata analyses in this corpus
    "LEFT JOIN (SELECT analyzed_wordforms.wordform_id," .
    "    GROUP_CONCAT(DISTINCT mla.mla SEPARATOR ' | ')".
    " multipleLemmataAnalysesInCorpus".
    " FROM token_attestations, ";
  if( $sMode == 'corpus')
    $sSelectQuery .= "corpusId_x_documentId, ";
  $sSelectQuery .= "analyzed_wordforms " .
    " LEFT JOIN (SELECT" .
    "  multiple_lemmata_analyses.multiple_lemmata_analysis_id,".
    //    "  GROUP_CONCAT(DISTINCT" .
    "  GROUP_CONCAT(" .
    " CONCAT(REPLACE(REPLACE(lemmata.modern_lemma, '>', '&gt;'), '<', '&lt;'),".
    "    IF(myPatterns.normalized_wordform IS NULL, ''," .
    "       CONCAT(',&nbsp;', myPatterns.normalized_wordform))," .
    "    IF(myPatterns.patterns IS NULL, ''," .
    "       CONCAT(',&nbsp;', myPatterns.patterns))," .
    "    ',&nbsp;', lemmata.lemma_part_of_speech," .
    "    IF(languages.language IS NULL, ''," .
    "       CONCAT(',&nbsp;',languages.language))), ".
    "    IF(lemmata.gloss = '', ''," .
    "       CONCAT(',&nbsp;', REPLACE(lemmata.gloss, ' ', '&nbsp;')))" .
    ///
    "   ORDER BY multiple_lemmata_analyses.part_number ASC" .
    ///
    "   SEPARATOR '&nbsp;&&nbsp;') mla" .
    " FROM multiple_lemmata_analyses," .
    "      multiple_lemmata_analysis_parts mlapartsOuter" .

    " LEFT JOIN (SELECT multiple_lemmata_analyses.part_number," .
    "                   mlapartsInner.multiple_lemmata_analysis_part_id," .
    "  CONCAT('&lt;', normalized_form, '&gt;') AS normalized_wordform," .
    "  CONCAT('[', GROUP_CONCAT(CONCAT('(', left_hand_side, '_'," .
    "   right_hand_side, ',&nbsp;', pattern_applications.position,')')), ']')".
    "    AS patterns" .
    " FROM analyzed_wordforms, multiple_lemmata_analyses, " .
    "      multiple_lemmata_analysis_parts mlapartsInner, derivations" .
    // Two LEFT JOINs here because derivations can occur without patterns
    " LEFT JOIN pattern_applications ON".
    "(pattern_applications.pattern_application_id" .
    " = derivations.pattern_application_id)" .
    " LEFT JOIN patterns ON" .
    "    (pattern_applications.pattern_id = patterns.pattern_id) " .
    "WHERE mlapartsInner.derivation_id = derivations.derivation_id " .
    // These tables are only in to narrow it down somewhat
    "  AND analyzed_wordforms.wordform_id IN ($sWordformIds)" .
    "  AND analyzed_wordforms.multiple_lemmata_analysis_id" .
    "      = multiple_lemmata_analyses.multiple_lemmata_analysis_id" .
    "  AND multiple_lemmata_analyses.multiple_lemmata_analysis_part_id" .
    "      = mlapartsInner.multiple_lemmata_analysis_part_id " .
    "GROUP BY mlapartsInner.multiple_lemmata_analysis_part_id)".
    " myPatterns ON (myPatterns.multiple_lemmata_analysis_part_id" .
    "                = mlapartsOuter.multiple_lemmata_analysis_part_id)," .

    "      analyzed_wordforms, token_attestations, ";
  if($sMode == 'corpus')
    $sSelectQuery .= "corpusId_x_documentId, ";
  $sSelectQuery .= "lemmata " .
    "LEFT JOIN languages ON (languages.language_id = lemmata.language_id) " .
    "WHERE ";
  if( $sMode == 'corpus')
    $sSelectQuery .= "corpusId_x_documentId.corpus_id = $iId" .
      " AND corpusId_x_documentId.document_id= token_attestations.document_id";
  else
    $sSelectQuery .= "token_attestations.document_id = $iId";
  $sSelectQuery .=
    " AND multiple_lemmata_analyses.multiple_lemmata_analysis_part_id" .
    "  = mlapartsOuter.multiple_lemmata_analysis_part_id" .
    " AND token_attestations.analyzed_wordform_id" .
    "  = analyzed_wordforms.analyzed_wordform_id" .
    // Added 2011-07-14
    " AND analyzed_wordforms.wordform_id IN ($sWordformIds) " .
    //
    " AND analyzed_wordforms.multiple_lemmata_analysis_id" .
    "  = multiple_lemmata_analyses.multiple_lemmata_analysis_id" .
    " AND analyzed_wordforms.wordform_id IN ($sWordformIds)" .
    " AND mlapartsOuter.lemma_id = lemmata.lemma_id " .
    "GROUP BY multiple_lemmata_analysis_id) mla " .
    "ON (mla.multiple_lemmata_analysis_id" .
    " = analyzed_wordforms.multiple_lemmata_analysis_id) " .
    "WHERE analyzed_wordforms.wordform_id IN ($sWordformIds) " .
    // Next condition is necessary because the multiple_lemmata_analysis_id
    // is also 0 for other analyzed_wordforms (it should be NULL, but then
    // it can't feature in a UNIQUE KEY...).
    "  AND analyzed_wordforms.lemma_id = 0 ";
  if( $sMode == 'corpus')
    $sSelectQuery .= "AND corpusId_x_documentId.corpus_id = $iId " .
      "AND token_attestations.document_id= corpusId_x_documentId.document_id ";
  else
    $sSelectQuery .= "AND token_attestations.document_id = $iId ";
  $sSelectQuery .=
    "AND token_attestations.analyzed_wordform_id" .
    " = analyzed_wordforms.analyzed_wordform_id " .
    "GROUP BY analyzed_wordforms.wordform_id) multipleLemmataAnalysesInCorpus".
    " ON (multipleLemmataAnalysesInCorpus.wordform_id=wordforms.wordform_id) ".

    // LEFT JOIN for the multiple lemmata analyses in this database
    "LEFT JOIN (SELECT analyzed_wordforms.wordform_id," .
    " GROUP_CONCAT(mla.mla SEPARATOR ' | ') multipleLemmataAnalysesInDb";
  $sSelectQuery .=
    " FROM analyzed_wordforms" .
    " LEFT JOIN (SELECT analyzed_wordforms.analyzed_wordform_id," .
    // The mla column
    "          CONCAT(IF(analyzed_wordforms.verified_by IS NULL, 0, 1), ', ',".
    "              analyzed_wordforms.analyzed_wordform_id, ', ',".
    /// 2011-09-05: geen DISTINCT
    ///"              GROUP_CONCAT(DISTINCT CONCAT(REPLACE(REPLACE(lemmata.modern_lemma, '>', '&gt;'), '<', '&lt;')," .
    "              GROUP_CONCAT(" .
    "CONCAT(REPLACE(REPLACE(lemmata.modern_lemma, '>', '&gt;'), '<', '&lt;')," .

    "    IF(myPatterns.normalized_wordform IS NULL, ''," .
    "       CONCAT(',&nbsp;', myPatterns.normalized_wordform))," .
    "    IF(myPatterns.patterns IS NULL, ''," .
    "       CONCAT(',&nbsp;', myPatterns.patterns))," .

    "                ',&nbsp;', lemmata.lemma_part_of_speech,".
    "                IF(languages.language IS NULL,".
    "                                 '', CONCAT(', ', languages.language))),".
    "                IF(lemmata.gloss = '',''," .
    "                   CONCAT(', ',REPLACE(gloss, ' ', '&nbsp;')))" .
    ///
    "                ORDER BY multiple_lemmata_analyses.part_number ASC" .
    ///
    "                SEPARATOR '&nbsp;&&nbsp;') ) mla";
  $sSelectQuery .=
    "          FROM analyzed_wordforms, multiple_lemmata_analyses,".
    "               multiple_lemmata_analysis_parts mlapartsOuter" .

    " LEFT JOIN (SELECT multiple_lemmata_analyses.part_number," .
    "                   mlapartsInner.multiple_lemmata_analysis_part_id," .
    "  CONCAT('&lt;', normalized_form, '&gt;') AS normalized_wordform," .
    "  CONCAT('[', GROUP_CONCAT(CONCAT('(', left_hand_side, '_'," .
    "   right_hand_side, ',&nbsp;', pattern_applications.position,')')), ']')".
    "    AS patterns" .
    " FROM analyzed_wordforms, multiple_lemmata_analyses, " .
    "      multiple_lemmata_analysis_parts mlapartsInner, derivations" .
    // Two LEFT JOINs here because derivations can occur without patterns
    " LEFT JOIN pattern_applications ON".
    "(pattern_applications.pattern_application_id" .
    " = derivations.pattern_application_id)" .
    " LEFT JOIN patterns ON" .
    "    (pattern_applications.pattern_id = patterns.pattern_id) " .
    "WHERE mlapartsInner.derivation_id = derivations.derivation_id " .
    // These tables are only in to narrow it down somewhat
    "  AND analyzed_wordforms.wordform_id IN ($sWordformIds)" .
    "  AND analyzed_wordforms.multiple_lemmata_analysis_id" .
    "      = multiple_lemmata_analyses.multiple_lemmata_analysis_id" .
    "  AND multiple_lemmata_analyses.multiple_lemmata_analysis_part_id" .
    "      = mlapartsInner.multiple_lemmata_analysis_part_id " .
    "GROUP BY mlapartsInner.multiple_lemmata_analysis_part_id)".
    " myPatterns ON (myPatterns.multiple_lemmata_analysis_part_id" .
    "                = mlapartsOuter.multiple_lemmata_analysis_part_id)," .
    "          lemmata" .

    "          LEFT JOIN languages ON" .
    "                           (languages.language_id = lemmata.language_id)".
    "          WHERE analyzed_wordforms.lemma_id = 0" .
    // Added 2011-07-14
    "            AND analyzed_wordforms.wordform_id IN ($sWordformIds) " .
    //
    "            AND analyzed_wordforms.multiple_lemmata_analysis_id" .
    "                = multiple_lemmata_analyses.multiple_lemmata_analysis_id".
    "         AND multiple_lemmata_analyses.multiple_lemmata_analysis_part_id".
    "     = mlapartsOuter.multiple_lemmata_analysis_part_id".
    "         AND mlapartsOuter.lemma_id = lemmata.lemma_id".
    "        GROUP BY analyzed_wordforms.analyzed_wordform_id)".
    "    mla ON (mla.analyzed_wordform_id".
    "              = analyzed_wordforms.analyzed_wordform_id)".
    "        WHERE analyzed_wordforms.wordform_id IN ($sWordformIds)" .
    "          AND analyzed_wordforms.lemma_id = 0" .
    "        GROUP BY analyzed_wordforms.wordform_id)" .
    " multipleLemmataAnalysesInDb ON (multipleLemmataAnalysesInDb.wordform_id".
    "  = wordforms.wordform_id) " .

    // LEFT JOIN for tokenAtts in corpus
    "LEFT JOIN (SELECT a1.wordform_id, " .
    " GROUP_CONCAT(DISTINCT " .
    " CONCAT(REPLACE(REPLACE(lemmata.modern_lemma, '>', '&gt;'), '<', '&lt;')," .
    " IF(myPatterns.normalized_wordform IS NULL, ''," .
    "    CONCAT(',&nbsp;',myPatterns.normalized_wordform))," .
    " IF(myPatterns.patterns IS NULL, ''," .
    "    CONCAT(',&nbsp;', myPatterns.patterns)),".
    " ',&nbsp;', lemma_part_of_speech, " .
    // NOTE that the language_id IS NULL is only there for backwards
    // compatibility
    " IF(lemmata.language_id IS NULL OR lemmata.language_id = 0, ''," .
    "    CONCAT(',&nbsp;',languages.language))," .
    " IF(lemmata.gloss ='', ''," .
    "    CONCAT(',&nbsp;', REPLACE(gloss, ' ', '&nbsp;'))))" .
    "    SEPARATOR ' | ') AS analysesInCorpus" .
    "           FROM token_attestations, ";
  if( $sMode == 'corpus' )
    $sSelectQuery .= "corpusId_x_documentId, ";
  $sSelectQuery .= "analyzed_wordforms a1" .
    " LEFT JOIN (SELECT a2.analyzed_wordform_id," .
    "  CONCAT('&lt;', normalized_form, '&gt;') AS normalized_wordform," .
    "  CONCAT('[', GROUP_CONCAT(CONCAT('(', left_hand_side, '_'," .
    "    right_hand_side, ', ', pattern_applications.position, ')')), ']')" .
    "    AS patterns" .
    " FROM analyzed_wordforms a2, derivations" .
    // Two LEFT JOINs here because derivations can occur without patterns
    " LEFT JOIN pattern_applications ON ".
    " (pattern_applications.pattern_application_id" .
    "  = derivations.pattern_application_id)" .
    " LEFT JOIN patterns ON" .
    "    (pattern_applications.pattern_id = patterns.pattern_id) " .
    "WHERE a2.derivation_id = derivations.derivation_id " .
    // Added for speed 2011-04-04
    "  AND a2.wordform_id IN ($sWordformIds) " .
    //
    /// "GROUP BY derivations.derivation_id)". /// <- FOUT !!!
    "GROUP BY a2.analyzed_wordform_id)".
    /// 
    " myPatterns ON (myPatterns.analyzed_wordform_id=a1.analyzed_wordform_id),"
    . " lemmata " .
    "LEFT JOIN languages ON (languages.language_id = lemmata.language_id)".
    // For the LEFT JOIN, only do it for the relevant word ids, so here comes
    // a subquery with basically the same query as the outer one    
    " WHERE a1.wordform_id IN ($sWordformIds) ";
  if( $sMode == 'corpus') 
    $sSelectQuery .= "AND corpusId_x_documentId.corpus_id = $iId " .
      "AND corpusId_x_documentId.document_id = token_attestations.document_id";
  else
    $sSelectQuery .= "AND token_attestations.document_id = $iId";
  $sSelectQuery .=
    " AND token_attestations.analyzed_wordform_id = " .
    "    a1.analyzed_wordform_id" .
    " AND a1.lemma_id = lemmata.lemma_id " .
    " GROUP BY a1.wordform_id) analysesInCorpus" .
    " ON (analysesInCorpus.wordform_id = wordforms.wordform_id) ";

  // LEFT JOIN for analyzed wordforms in entire db
  $sSelectQuery .=
    "LEFT JOIN (SELECT a.wordform_id," .
    // analysesInDb column
    " GROUP_CONCAT(DISTINCT " .
    " CONCAT(IF(verified_by IS NULL, 0, 1), ','," .
    " a.analyzed_wordform_id, ',', " .
    " REPLACE(REPLACE(lemmata.modern_lemma, '>', '&gt;'), '<', '&lt;'),".
    " IF(myPatterns.normalized_wordform IS NULL, ''," .
    "    CONCAT(',&nbsp;',myPatterns.normalized_wordform))," .
    " IF(myPatterns.patterns IS NULL, ''," .
    "    CONCAT(',&nbsp;', myPatterns.patterns)),".
    " ',&nbsp;', lemma_part_of_speech, " .
    // NOTE that the lemmata.language_id IS NULL is only there for backwards
    // compatibility
    " IF(lemmata.language_id IS NULL OR lemmata.language_id = 0, ''," .
    "    CONCAT(',&nbsp;',languages.language))," .
    " IF(lemmata.gloss = '', ''," .
    "    CONCAT(',&nbsp;', REPLACE(gloss, ' ', '&nbsp;'))))" .
    "    SEPARATOR '|') AS analysesInDb";
  $sSelectQuery .=
    " FROM analyzed_wordforms a" .
    " LEFT JOIN (SELECT analyzed_wordforms.analyzed_wordform_id," .
    "  CONCAT('&lt;', normalized_form, '&gt;') AS normalized_wordform," .
    "  CONCAT('[', GROUP_CONCAT(CONCAT('(', left_hand_side, '_'," .
    "   right_hand_side, ',&nbsp;', pattern_applications.position,')')), ']')".
    "    AS patterns" .
    " FROM analyzed_wordforms, derivations" .
    // Two LEFT JOINs here because derivations can occur without patterns
    " LEFT JOIN pattern_applications ON".
    "(pattern_applications.pattern_application_id" .
    " = derivations.pattern_application_id)" .
    " LEFT JOIN patterns ON" .
    "    (pattern_applications.pattern_id = patterns.pattern_id) " .
    "WHERE analyzed_wordforms.derivation_id =" .
    "        derivations.derivation_id " .
    // Added for speed 2011-04-04
    "  AND analyzed_wordforms.wordform_id IN ($sWordformIds) " .
    "GROUP BY analyzed_wordforms.analyzed_wordform_id)".
    " myPatterns ON (myPatterns.analyzed_wordform_id=a.analyzed_wordform_id),"
    . " lemmata " .
    "LEFT JOIN languages ON (languages.language_id = lemmata.language_id)".
    // For the LEFT JOIN, only do it for the relevant word ids, so here comes
    // the same IN(...) again as in the outer query
    " WHERE a.wordform_id IN ($sWordformIds) " .
    "   AND a.lemma_id = lemmata.lemma_id" .
    " GROUP BY a.wordform_id) analysesInDb " .
    " ON (analysesInDb.wordform_id = wordforms.wordform_id) ";

  // And on with the main query...
  $sSelectQuery .=
    "WHERE type_frequencies.wordform_id = wordforms.wordform_id " .
    "AND wordforms.wordform_id IN ($sWordformIds) "; 
  if( $sMode == 'corpus')
    $sSelectQuery .= "AND corpusId_x_documentId.corpus_id = $iId " .
      "AND type_frequencies.document_id = corpusId_x_documentId.document_id ";
  else
    $sSelectQuery .= "AND type_frequencies.document_id = $iId ";
  // Don't show part
  if( $sMode == 'corpus') {
    if( ! $bDoShowCorpus && ! $bDoShowAll) 
      $sSelectQuery .=
	"AND ((dont_show.corpus_id IS NULL OR dont_show.corpus_id != $iId) " .
	"AND (dont_show.at_all IS NULL OR dont_show.at_all = 0) ) ";
    if( $bDoShowCorpus && ! $bDoShowAll )
      $sSelectQuery .=
	" AND (dont_show.corpus_id = $iId OR dont_show.corpus_id IS NULL) ";
  }
  else { // mode is 'document'
    if( ! $bDoShowDocument && ! $bDoShowAll) 
      $sSelectQuery .=
	"AND ((dont_show.document_id IS NULL OR dont_show.corpus_id != $iId)) ".
	"AND (dont_show.at_all IS NULL OR dont_show.at_all = 0))";
    if( $bDoShowDocument && ! $bDoShowAll )
      $sSelectQuery .=
	" AND (dont_show.document_id = $iId OR dont_show.document_id IS NULL) ";
  }
  /// Weg volgens mij
  /// if( ! $bDoShowAll )
  ///  $sSelectQuery .=
  ///    "AND (dont_show.at_all IS NULL OR dont_show.at_all = 0) ";
  // End of the dont_show part

  // GROUP BY, ORDER BY
  $sSelectQuery .= "GROUP BY type_frequencies.wordform_id " .
    "ORDER BY $sSortByClause $sSortMode$sExtraSort";
  // No LIMIT clause as the wordform_id IN (...) already gives us the right
  // ones

  if( ($oResult = doSelectQuery($sSelectQuery)) )
    return array($oResult, $iLemmaFilter_lemmaId, $sLemmaFilterWordformIds);
  return array(false, '', false);
}

function getWordFormIdsToAttest($iId, $sMode, $sSortBy, $sSortMode,
				$sSortByClause, $sExtraSort, $sFilter,
				$bCaseInsensitivity, $sLemmaFilterWordformIds,
				$bDoShowAll, $bDoShowCorpus, $bDoShowDocument,
				$iStartAt, $iStepValue, $iNrOfWordFormsPerPage){
  // First we get all relevant wordform id's. Then we do a query that gets all
  // info for these id's
  // We do this because otherwise we have to do the selection of the relevant
  // id's in a subquery, but:
  //
  //     ERROR 1235 (42000): This version of MySQL doesn't yet support
  //     'LIMIT & IN/ALL/ANY/SOME subquery'
  //
  // In WHERE clauses, that is. Because in FROM clauses it clearly does work,
  // as the next query demonstrates
  $sSelectQuery =
    "SELECT wordforms.wordform_id " .
    "  FROM ";
  // NOTE that we link the wordforms table at different points depending on
  // whether we are filtering or not.
  if( ! $sFilter) { // Introduce another sub query
    $sSelectQuery .= "wordforms, (SELECT type_frequencies.wordform_id ";
    if( $sSortBy == 'frequency') 
      $sSelectQuery .= ", SUM(frequency) freq ";
    $sSelectQuery .= "FROM ";
  }

  if( $sMode == 'corpus' )
    $sSelectQuery .= "corpusId_x_documentId, ";
  if( $sFilter)
    $sSelectQuery .= "wordforms, ";
  $sSelectQuery .=
    "type_frequencies";

  $sSelectQuery .=
    " LEFT JOIN dont_show ON " .
    "(dont_show.wordform_id = type_frequencies.wordform_id";
  /// Dit veranderd.
  if( $sMode == 'corpus' ) {
    $sSelectQuery .= " AND (dont_show.corpus_id = $iId " .
      "OR dont_show.at_all != 0) ";
  }
  else { // document mode
    $sSelectQuery .= " AND (dont_show.document_id = $iId " .
      "OR dont_show.at_all != 0) ";
  }
  
  $sSelectQuery .= ") ";
  if( $sFilter)
    $sSelectQuery .=
      "WHERE type_frequencies.wordform_id = wordforms.wordform_id AND ";
  else
    $sSelectQuery .= "WHERE ";
  if( $sMode == 'corpus')
    $sSelectQuery .= "corpusId_x_documentId.corpus_id = $iId " .
      "AND type_frequencies.document_id = corpusId_x_documentId.document_id ";
  else
    $sSelectQuery .= "type_frequencies.document_id = $iId ";
  if( $sFilter) {
    $sFilter = addslashes($sFilter);
    if( $bCaseInsensitivity )
      $sSelectQuery .=
	"AND wordforms.wordform_lowercase LIKE LOWER('$sFilter') ";
    else
      $sSelectQuery .= "AND wordforms.wordform LIKE '$sFilter' ";
  }
  if( $sLemmaFilterWordformIds ) {
    $sSelectQuery .=
      "AND type_frequencies.wordform_id IN ($sLemmaFilterWordformIds) ";
  }
  // The dont_show part if necessary
  if( $sMode == 'corpus') {
    if( ! $bDoShowCorpus && ! $bDoShowAll) 
      $sSelectQuery .=
	"AND (dont_show.corpus_id IS NULL OR dont_show.corpus_id = 0) ";
  }
  else { // mode is 'document'
    if( ! $bDoShowDocument && ! $bDoShowAll) 
      $sSelectQuery .=
	"AND (dont_show.document_id IS NULL OR dont_show.document_id = 0) ";
  }
  if( ! $bDoShowAll )
    $sSelectQuery .=
      "AND (dont_show.at_all IS NULL OR dont_show.at_all = 0) ";
  // End of the dont_show part

  // LIMIT, ORDER BY, GROUP BY
  // NOTE that we try to get one more than the requested amount, which is
  // unnecessary in fact in the case where we have to print 'all'.
  // It *is* usefull however in the other cases where we can deduct from it
  // whether or not we have to print a '>> Next' link on the page
  $sSelectQuery .= "GROUP BY type_frequencies.wordform_id ";
  if( ! $sFilter)
    $sSelectQuery .= ") tmp WHERE tmp.wordform_id = wordforms.wordform_id ";
  // Even anders
  if( (! $sFilter) && ($sSortBy == 'frequency') )
    $sSortByClause = 'freq';
  $sSelectQuery .= "ORDER BY $sSortByClause $sSortMode$sExtraSort " .
    "LIMIT $iStartAt, $iStepValue";

  $sWordformIds = $sComma = '';
  if( ($oResult = doSelectQuery($sSelectQuery)) ) {
    while($aRow = mysql_fetch_assoc($oResult)) {
      $sWordformIds .= $sComma . $aRow['wordform_id'];
      $sComma = ", ";
    }
    mysql_free_result($oResult);
  }

  return $sWordformIds;
}

function getLemmaFilterWordformsIds($sLemmaFilter) {
  if( strpos($sLemmaFilter, "&") === false ) {
    return getLemmaFilterWordformsIds_singleLemma($sLemmaFilter);
  }
  else {
    return getLemmaFilterWordformsIds_multipleLemmata($sLemmaFilter);
  }
}

function getLemmaFilterWordformsIds_multipleLemmata($sLemmaFilter) {
  $iNrOfParts = 0;

  // Find the part id's of the different parts. If one of them doesn't exist
  // we stop right away.
  $aLemmaTuples = explode("&", $sLemmaFilter);
  $aPartIds = array();
  foreach( $aLemmaTuples as $sLemmaTuple) {
    $iPartId = getPartId($sLemmaTuple, 'dontAdd');

    if( $iPartId )
      array_push($aPartIds, $iPartId);
    else
      return array(false, '');

    printLog("Found multiple lemma part id for '$sLemmaTuple': $iPartId\n");
    $iNrOfParts++;
  }

  $iMultipleLemmaAnalysisId =
    getMultipleLemmataAnalysisId($aPartIds, $iNrOfParts);

  if( $iMultipleLemmaAnalysisId === false)
    return array(false, '');

  $sSelectQuery = "SELECT GROUP_CONCAT(wordform_id) wordform_ids" .
    "  FROM analyzed_wordforms" .
    " WHERE multiple_lemmata_analysis_id = $iMultipleLemmaAnalysisId";

  $sLemmaFilterWordformIds = false;
  if( ($oResult = doSelectQuery($sSelectQuery)) ) {
    if( ($aRow = mysql_fetch_assoc($oResult)) )
      $sLemmaFilterWordformIds = $aRow['wordform_ids'];
    mysql_free_result($oResult);
  }
  printLog("Found lemma filter wordform ids: $sLemmaFilterWordformIds\n");
  
  return array($sLemmaFilterWordformIds, "m$iMultipleLemmaAnalysisId");
}

function getLemmaFilterWordformsIds_singleLemma($sLemmaFilter) {
  $sLemmaFilterWordformIds = '';
  $aLemmaFilterArr = lemmaTupleString2array($sLemmaFilter, '', 0);
  if( ! $aLemmaFilterArr)
    return array(false, '');

  $iLemmaFilterId = getLemmaId_forLemmaArr($aLemmaFilterArr, 'dontAdd');
  if( ! $iLemmaFilterId ) // Can't be 0 (zero)
    return array(false, '');
 
  printLog("Found lemma filter id: $iLemmaFilterId\n");

  $sSelectQuery = "SELECT GROUP_CONCAT(wordform_id) wordform_ids " .
    "FROM analyzed_wordforms WHERE lemma_id = $iLemmaFilterId";

  $sLemmaFilterWordformIds = false;
  if( ($oResult = doSelectQuery($sSelectQuery)) ) {
    if( ($aRow = mysql_fetch_assoc($oResult)) )
      $sLemmaFilterWordformIds = $aRow['wordform_ids'];
    mysql_free_result($oResult);
  }

  printLog("Found lemma filter wordform ids: $sLemmaFilterWordformIds\n");

  // NEW
  // We also try look for multiple lemmata analyses with this lemma
  $sMultiples = getMultipleLemmataAnalysesWordIdsForLemmaId($iLemmaFilterId);
  if( strlen($sMultiples) ) {
    if( strlen($sLemmaFilterWordformIds) )
      $sLemmaFilterWordformIds .= ", ";
    $sLemmaFilterWordformIds .= $sMultiples;
  }
  
  return array($sLemmaFilterWordformIds, "s$iLemmaFilterId");
}

function getMultipleLemmataAnalysesWordIdsForLemmaId($iLemmaId) {
  $sSelectQuery = "SELECT GROUP_CONCAT(awf.wordform_id) wordform_ids".
    "  FROM analyzed_wordforms awf, multiple_lemmata_analyses," .
    "       multiple_lemmata_analysis_parts" .
    " WHERE awf.multiple_lemmata_analysis_id =" .
    "         multiple_lemmata_analyses.multiple_lemmata_analysis_id" .
    "   AND multiple_lemmata_analyses.multiple_lemmata_analysis_part_id = " .
    "        multiple_lemmata_analysis_parts.multiple_lemmata_analysis_part_id".
    "   AND multiple_lemmata_analysis_parts.lemma_id = $iLemmaId";

  $sWordFormIds = $sComma = '';
  if( ($oResult = doSelectQuery($sSelectQuery)) ) {
    if( ($aRow = mysql_fetch_assoc($oResult)) )
      $sWordFormIds .= $aRow['wordform_ids'];
    mysql_free_result($oResult);
  }
  return $sWordFormIds;
}

function printTotalNrOfWords($iId, $sMode, $sFilter, $sLemmaFilterWordformIds,
			     $bDoShowAll, $bDoShowCorpus, $bDoShowDocument,
			     $bCaseInsensitivity) {
  $sSelectQuery =
    "SELECT COUNT(DISTINCT type_frequencies.wordform_id) AS totalNrOfWords " .
    "FROM ";
  if( strlen($sFilter))
    $sSelectQuery .= "wordforms, ";
  $sSelectQuery .= "type_frequencies ";

  if( ! $bDoShowAll || ! $bDoShowCorpus || ! $bDoShowDocument)
    $sSelectQuery .=
      "LEFT JOIN dont_show" .
      "  ON (dont_show.wordform_id = type_frequencies.wordform_id), ";

  if( $sMode == 'corpus')
    $sSelectQuery .= "corpusId_x_documentId " .
      "WHERE corpusId_x_documentId.corpus_id = $iId " .
      "AND type_frequencies.document_id = corpusId_x_documentId.document_id";
  else
    $sSelectQuery .= "documents WHERE type_frequencies.document_id = $iId";
  if( strlen($sFilter) ) {
    $sFilter = addslashes($sFilter);
    $sSelectQuery .=
      " AND type_frequencies.wordform_id = wordforms.wordform_id ";
    if( $bCaseInsensitivity )
      $sSelectQuery .=
	" AND wordform_lowercase LIKE '" . strtolower($sFilter) ."'";
    else
      $sSelectQuery .= " AND wordforms.wordform LIKE '$sFilter'";
  }
  if( $sLemmaFilterWordformIds)
    $sSelectQuery .=
      " AND type_frequencies.wordform_id IN ($sLemmaFilterWordformIds)";

  // The dont_show part if necessary
  if( $sMode == 'corpus') {
    if( ! $bDoShowCorpus && ! $bDoShowAll) 
      $sSelectQuery .=
	" AND (dont_show.corpus_id IS NULL OR dont_show.corpus_id = 0)";
  }
  else { // mode is 'document'
    if( ! $bDoShowDocument && ! $bDoShowAll) 
      $sSelectQuery .=
	" AND (dont_show.document_id IS NULL OR dont_show.document_id = 0)";
  }
  if( ! $bDoShowAll )
    $sSelectQuery .=
      " AND (dont_show.at_all IS NULL OR dont_show.at_all = 0)";

  $bPrintedSomething = false;
  if( ($oResult = doSelectQuery($sSelectQuery)) &&
      ($aRow = mysql_fetch_assoc($oResult)) &&
      $aRow['totalNrOfWords'] ) {
    print $aRow['totalNrOfWords'];
    $bPrintedSomething = true;
  }
  if( ! $bPrintedSomething)
    print 0;

  print "\n"; // Print a newline anyway
}

function getAnalysesInCorpus($iWordFormId, $sMode, $iId) {
  printLog("getAnalysesInCorpus($iWordFormId, '$sMode', $iId)\n");
  
  // NOTE that this query very much resembles the first LEFT JOIN subquery
  // above
  $sSelectQuery = "SELECT analysesInCorpus, multipleLemmataAnalysesInCorpus " .
    "FROM token_attestations";
  if( $sMode == 'corpus')
    $sSelectQuery .= ", corpusId_x_documentId";
  $sSelectQuery .= ", analyzed_wordforms " .
    "LEFT JOIN(SELECT a1.wordform_id, GROUP_CONCAT(DISTINCT " .
    " CONCAT(REPLACE(REPLACE(lemmata.modern_lemma, '>', '&gt;'), '<', '&lt;'),".
    " IF(myPatterns.normalized_wordform IS NULL, ''," .
    "    CONCAT(',&nbsp;',myPatterns.normalized_wordform))," .
    " IF(myPatterns.patterns IS NULL," .
    "    '', CONCAT(',&nbsp;', myPatterns.patterns)),".
    " ',&nbsp;', lemma_part_of_speech, " .
    // NOTE that the lemmata.language_id IS NULL is opnly there for backwards
    // compatibility
    " IF(lemmata.language_id IS NULL OR lemmata.language_id = 0," .
    "    '', CONCAT(',&nbsp;',languages.language))," .
    " IF(lemmata.gloss = '', ''," .
    "    CONCAT(',&nbsp;', REPLACE(gloss, ' ', '&nbsp;'))))" .
    "    SEPARATOR ' | ') AS analysesInCorpus" .
    // FROM
    "  FROM token_attestations, corpusId_x_documentId, analyzed_wordforms a1 ".
    // LEFT JOIN for patterns/derivations
    " LEFT JOIN (SELECT a2.analyzed_wordform_id," .
    "  CONCAT('&lt;', normalized_form, '&gt;') AS normalized_wordform," .
    "  CONCAT('[', GROUP_CONCAT(CONCAT('(', left_hand_side, '_'," .
    "  right_hand_side, ',&nbsp;', pattern_applications.position, ')')), ']')".
    "    AS patterns" .
    " FROM analyzed_wordforms a2, derivations" .
    // Two LEFT JOINs here because derivations can occur without patterns
    " LEFT JOIN pattern_applications ON ".
    "(pattern_applications.pattern_application_id" .
    " = derivations.pattern_application_id)" .
    " LEFT JOIN patterns ON" .
    "    (pattern_applications.pattern_id = patterns.pattern_id) " .
    "WHERE a2.derivation_id = derivations.derivation_id " .
    "  AND a2.wordform_id = $iWordFormId " .

    "GROUP BY a2.analyzed_wordform_id)". 
    " myPatterns ON (myPatterns.analyzed_wordform_id=a1.analyzed_wordform_id),"
    // On with the FROM from the main query
    . " lemmata " .
    // LEFT JOIN for languages
    "LEFT JOIN languages ON (languages.language_id = lemmata.language_id)".
    " WHERE ";
  if( $sMode == 'corpus') 
    $sSelectQuery .= "corpusId_x_documentId.corpus_id = $iId " .
      "AND corpusId_x_documentId.document_id = token_attestations.document_id";
  else
    $sSelectQuery .= "token_attestations.document_id = $iId";
  $sSelectQuery .= " AND token_attestations.analyzed_wordform_id = " .
    "  a1.analyzed_wordform_id" .
    " AND a1.wordform_id = $iWordFormId " .
    " AND a1.lemma_id = lemmata.lemma_id " .
    " GROUP BY a1.wordform_id) analysesInCorpus ON " .
    " (analysesInCorpus.wordform_id = analyzed_wordforms.wordform_id) " .

    // LEFT JOIN for the multiple lemmata analyses in this corpus

    "LEFT JOIN (SELECT analyzed_wordforms.wordform_id," .
    "    GROUP_CONCAT(DISTINCT mla.mla SEPARATOR ' | ')".
    " multipleLemmataAnalysesInCorpus".
    " FROM token_attestations, ";
  if( $sMode == 'corpus')
    $sSelectQuery .= "corpusId_x_documentId, ";
  $sSelectQuery .= "analyzed_wordforms " .
    " LEFT JOIN (SELECT" .
    "  multiple_lemmata_analyses.multiple_lemmata_analysis_id,".
    /// 2011-09-05: geen DISTINCT
    /// "  GROUP_CONCAT(DISTINCT CONCAT(REPLACE(REPLACE(lemmata.modern_lemma, '>', '&gt;'), '<', '&lt;'),".
    "  GROUP_CONCAT(CONCAT(" .
    "       REPLACE(REPLACE(lemmata.modern_lemma, '>', '&gt;'), '<', '&lt;')," .
    "    IF(myPatterns.normalized_wordform IS NULL, ''," .
    "       CONCAT(',&nbsp;', myPatterns.normalized_wordform))," .
    "    IF(myPatterns.patterns IS NULL, ''," .
    "       CONCAT(',&nbsp;', myPatterns.patterns))," .

    "    ',&nbsp;', lemmata.lemma_part_of_speech," .
    "    IF(lemmata.gloss = '', '', CONCAT(', ', lemmata.gloss))," .
    "    IF(languages.language IS NULL,'', CONCAT(', ', languages.language)))".
    ///
    "   ORDER BY multiple_lemmata_analyses.part_number ASC" .
    ///
    "   SEPARATOR '&nbsp;&&nbsp;') mla" .
    " FROM multiple_lemmata_analyses, " .
    "      multiple_lemmata_analysis_parts mlapartsOuter" .

    " LEFT JOIN (SELECT multiple_lemmata_analyses.part_number," .
    "                   mlapartsInner.multiple_lemmata_analysis_part_id," .
    "  CONCAT('&lt;', normalized_form, '&gt;') AS normalized_wordform," .
    "  CONCAT('[', GROUP_CONCAT(CONCAT('(', left_hand_side, '_'," .
    "   right_hand_side, ',&nbsp;', pattern_applications.position,')')), ']')".
    "    AS patterns" .
    " FROM analyzed_wordforms, multiple_lemmata_analyses, " .
    "      multiple_lemmata_analysis_parts mlapartsInner, derivations" .
    // Two LEFT JOINs here because derivations can occur without patterns
    " LEFT JOIN pattern_applications ON".
    "(pattern_applications.pattern_application_id" .
    " = derivations.pattern_application_id)" .
    " LEFT JOIN patterns ON" .
    "    (pattern_applications.pattern_id = patterns.pattern_id) " .
    "WHERE mlapartsInner.derivation_id = derivations.derivation_id " .
    // These tables are only in to narrow it down somewhat
    "  AND analyzed_wordforms.wordform_id = $iWordFormId" .
    "  AND analyzed_wordforms.multiple_lemmata_analysis_id" .
    "      = multiple_lemmata_analyses.multiple_lemmata_analysis_id" .
    "  AND multiple_lemmata_analyses.multiple_lemmata_analysis_part_id" .
    "      = mlapartsInner.multiple_lemmata_analysis_part_id " .
    "GROUP BY mlapartsInner.multiple_lemmata_analysis_part_id)".
    " myPatterns ON (myPatterns.multiple_lemmata_analysis_part_id" .
    "                = mlapartsOuter.multiple_lemmata_analysis_part_id)," .

    "   analyzed_wordforms, token_attestations, ";
  if($sMode == 'corpus')
    $sSelectQuery .= "corpusId_x_documentId, ";
  $sSelectQuery .= "lemmata " .
    "LEFT JOIN languages ON (languages.language_id = lemmata.language_id) " .
    "WHERE ";
  if( $sMode == 'corpus')
    $sSelectQuery .= "corpusId_x_documentId.corpus_id = $iId" .
      " AND corpusId_x_documentId.document_id= token_attestations.document_id";
  else
    $sSelectQuery .= "token_attestations.document_id = $iId";
  $sSelectQuery .=
    " AND analyzed_wordforms.wordform_id = $iWordFormId" .
    " AND token_attestations.analyzed_wordform_id" .
    "  = analyzed_wordforms.analyzed_wordform_id" .
    " AND analyzed_wordforms.multiple_lemmata_analysis_id" .
    "  = multiple_lemmata_analyses.multiple_lemmata_analysis_id " .
    " AND multiple_lemmata_analyses.multiple_lemmata_analysis_part_id" .
    "  = mlapartsOuter.multiple_lemmata_analysis_part_id" .
    " AND mlapartsOuter.lemma_id = lemmata.lemma_id " .
    "GROUP BY multiple_lemmata_analysis_id) mla " .
    "ON (mla.multiple_lemmata_analysis_id" .
    " = analyzed_wordforms.multiple_lemmata_analysis_id) " .
    "WHERE analyzed_wordforms.wordform_id = $iWordFormId " .
    // Next condition is necessary because the multiple_lemmata_analysis_id
    // is also 0 for other analyzed_wordforms (it should be NULL, but then
    // it can't feature in a UNIQUE KEY...).
    "  AND analyzed_wordforms.lemma_id = 0 ";
  if( $sMode == 'corpus')
    $sSelectQuery .= "AND corpusId_x_documentId.corpus_id = $iId " .
      "AND token_attestations.document_id= corpusId_x_documentId.document_id ";
  else
    $sSelectQuery .= "AND token_attestations.document_id = $iId ";
  $sSelectQuery .=
    "AND token_attestations.analyzed_wordform_id" .
    " = analyzed_wordforms.analyzed_wordform_id " .
    "GROUP BY analyzed_wordforms.wordform_id) multipleLemmataAnalysesInCorpus".
    " ON (multipleLemmataAnalysesInCorpus.wordform_id" .
    " = analyzed_wordforms.wordform_id) ".

    // WHERE clause of the main query
    "WHERE analyzed_wordforms.wordform_id = $iWordFormId" .
    "  AND token_attestations.analyzed_wordform_id" .
    "   = analyzed_wordforms.analyzed_wordform_id ";
  if( $sMode == 'corpus')
    $sSelectQuery .= " AND corpusId_x_documentId.corpus_id = $iId " .
      "AND token_attestations.document_id= corpusId_x_documentId.document_id ";
  else
    $sSelectQuery .= " AND token_attestations.document_id = $iId ";
  // We get as many rows as there are token attestations, but they are all the
  // same...
  $sSelectQuery .= "LIMIT 1";
  
  if( ($oResult = doSelectQuery($sSelectQuery) ) )
    return $oResult;
  return false;
}

function getAnalysesInDb($iWordFormId) {
  printLog("getAnalysesInDb($iWordFormId)\n");
  
  // NOTE that this query very much resembles the second LEFT JOIN subquery
  // above
  $sSelectQuery = "SELECT wordforms.wordform_id, " .
    "analysesInDb.analysesInDb, multipleLemmataAnalysesInDb FROM wordforms " .
    // LEFT JOIN for analysesInDb
    "LEFT JOIN (SELECT a.wordform_id," .
    " GROUP_CONCAT(DISTINCT " .
    " CONCAT(IF(verified_by IS NULL, 0, 1), ','," .
    " a.analyzed_wordform_id, ',', " .
    " REPLACE(REPLACE(lemmata.modern_lemma, '>', '&gt;'), '<', '&lt;'),".
    " IF(myPatterns.normalized_wordform IS NULL, ''," .
    "    CONCAT(',&nbsp;',myPatterns.normalized_wordform))," .
    " IF(myPatterns.patterns IS NULL," .
    "    '', CONCAT(',&nbsp;', myPatterns.patterns)),".
    " ',&nbsp;', lemma_part_of_speech, " .
    // NOTE that the lemmata.language_id IS NULL is only there for backwards
    // compatibility
    " IF(lemmata.language_id IS NULL OR lemmata.language_id = 0," .
    "    '', CONCAT(',&nbsp;',languages.language))," .
    " IF(lemmata.gloss = '', ''," .
    "    CONCAT(',&nbsp;', REPLACE(lemmata.gloss, ' ', '&nbsp;'))))" .
    "    SEPARATOR '|') AS analysesInDb" .
    " FROM analyzed_wordforms a" .
    " LEFT JOIN (SELECT analyzed_wordforms.analyzed_wordform_id," .
    "  CONCAT('&lt;', normalized_form, '&gt;') AS normalized_wordform," .
    "  CONCAT('[', GROUP_CONCAT(CONCAT('(', left_hand_side, '_'," .
    "  right_hand_side, ',&nbsp;', pattern_applications.position, ')')), ']')".
    "  AS patterns" .
    " FROM analyzed_wordforms, derivations" .
    // Two LEFT JOINs here because derivations can occur without patterns
    " LEFT JOIN pattern_applications ON " .
    "(pattern_applications.pattern_application_id" .
    " = derivations.pattern_application_id)" .
    " LEFT JOIN patterns ON" .
    "    (pattern_applications.pattern_id = patterns.pattern_id) " .
    "WHERE analyzed_wordforms.derivation_id = derivations.derivation_id " .
    "  AND analyzed_wordforms.wordform_id = $iWordFormId " .
    "GROUP BY analyzed_wordforms.analyzed_wordform_id)".
    " myPatterns ON (myPatterns.analyzed_wordform_id=a.analyzed_wordform_id),"
    . " lemmata " .
    "LEFT JOIN languages ON (languages.language_id = lemmata.language_id)".
    // For the LEFT JOIN, only do it for the relevant word ids, so here comes
    // the same IN(...) again as in the outer query
    " WHERE a.wordform_id = $iWordFormId " .
    "   AND a.lemma_id = lemmata.lemma_id" .
    " GROUP BY a.wordform_id) analysesInDb " .
    "ON (analysesInDb.wordform_id = wordforms.wordform_id) " .

    // LEFT JOIN for multipleLemmataAnalysesInDb
    "LEFT JOIN (SELECT analyzed_wordforms.wordform_id," .
    " GROUP_CONCAT(mla.mla SEPARATOR ' | ') multipleLemmataAnalysesInDb" .
    " FROM analyzed_wordforms" .
    " LEFT JOIN (SELECT analyzed_wordforms.analyzed_wordform_id," .
    "     CONCAT(IF(analyzed_wordforms.verified_by IS NULL, 0, 1), ',&nbsp;',".
    "              analyzed_wordforms.analyzed_wordform_id, ',&nbsp;',".
    /// 2011-09-05 geen DISTINCT
    ///"            GROUP_CONCAT(DISTINCT CONCAT(REPLACE(REPLACE(lemmata.modern_lemma, '>', '&gt;'), '<', '&lt;'), " .
    "            GROUP_CONCAT(CONCAT(REPLACE(REPLACE(lemmata.modern_lemma, '>', '&gt;'), '<', '&lt;'), " .

    "    IF(myPatterns.normalized_wordform IS NULL, ''," .
    "       CONCAT(',&nbsp;', myPatterns.normalized_wordform))," .
    "    IF(myPatterns.patterns IS NULL, ''," .
    "       CONCAT(',&nbsp;', myPatterns.patterns))," .

    "                ',&nbsp;', lemmata.lemma_part_of_speech,".
    "                IF(lemmata.gloss = '',''," .
    "               CONCAT(',&nbsp;', REPLACE(lemmata.gloss, ' ', '&nbsp;'))),".
    "                IF(languages.language IS NULL,".
    "                              '', CONCAT(',&nbsp;', languages.language)))".
    ///
    "                ORDER BY multiple_lemmata_analyses.part_number ASC" .
    ///
    "                SEPARATOR '&nbsp;&&nbsp;') ) mla".
    "          FROM analyzed_wordforms, multiple_lemmata_analyses,".
    "               multiple_lemmata_analysis_parts mlapartsOuter" .

    " LEFT JOIN (SELECT multiple_lemmata_analyses.part_number," .
    "                   mlapartsInner.multiple_lemmata_analysis_part_id," .
    "  CONCAT('&lt;', normalized_form, '&gt;') AS normalized_wordform," .
    "  CONCAT('[', GROUP_CONCAT(CONCAT('(', left_hand_side, '_'," .
    "   right_hand_side, ',&nbsp;', pattern_applications.position,')')), ']')".
    "    AS patterns" .
    " FROM analyzed_wordforms, multiple_lemmata_analyses, " .
    "      multiple_lemmata_analysis_parts mlapartsInner, derivations" .
    // Two LEFT JOINs here because derivations can occur without patterns
    " LEFT JOIN pattern_applications ON".
    "(pattern_applications.pattern_application_id" .
    " = derivations.pattern_application_id)" .
    " LEFT JOIN patterns ON" .
    "    (pattern_applications.pattern_id = patterns.pattern_id) " .
    "WHERE mlapartsInner.derivation_id = derivations.derivation_id " .
    // These tables are only in to narrow it down somewhat
    "  AND analyzed_wordforms.wordform_id = $iWordFormId" .
    "  AND analyzed_wordforms.multiple_lemmata_analysis_id" .
    "      = multiple_lemmata_analyses.multiple_lemmata_analysis_id" .
    "  AND multiple_lemmata_analyses.multiple_lemmata_analysis_part_id" .
    "      = mlapartsInner.multiple_lemmata_analysis_part_id " .
    "GROUP BY mlapartsInner.multiple_lemmata_analysis_part_id)".
    " myPatterns ON (myPatterns.multiple_lemmata_analysis_part_id" .
    "                = mlapartsOuter.multiple_lemmata_analysis_part_id)," .

    "               lemmata" .
    "          LEFT JOIN languages ON" .
    "                           (languages.language_id = lemmata.language_id)".
    "          WHERE analyzed_wordforms.lemma_id = 0" .
    // Added 2011-07-14
    "            AND analyzed_wordforms.wordform_id = $iWordFormId" .
    //
    "            AND analyzed_wordforms.multiple_lemmata_analysis_id" .
    "                = multiple_lemmata_analyses.multiple_lemmata_analysis_id".
    "         AND multiple_lemmata_analyses.multiple_lemmata_analysis_part_id".
    "     = mlapartsOuter.multiple_lemmata_analysis_part_id".
    "         AND mlapartsOuter.lemma_id = lemmata.lemma_id".
    "        GROUP BY analyzed_wordforms.analyzed_wordform_id)".
    "    mla ON (mla.analyzed_wordform_id".
    "              = analyzed_wordforms.analyzed_wordform_id)".
    "        WHERE analyzed_wordforms.wordform_id = $iWordFormId " .
    "          AND analyzed_wordforms.lemma_id = 0" .
    "        GROUP BY analyzed_wordforms.wordform_id) ".
    "multipleLemmataAnalysesInDb ".
    "ON (multipleLemmataAnalysesInDb.wordform_id = wordforms.wordform_id) " .
    "WHERE wordforms.wordform_id = $iWordFormId";
  
  if( ($oResult = doSelectQuery($sSelectQuery) ) )
    return $oResult;
  return false;
}

function getTokenAttestations($iDocumentId, $iWordFormId, $iStartPos) {
  printLog("Doing getTokenAttestations($iDocumentId, $iWordFormId, $iStartPos)"
	   . "\n");
  // NOTE that the single and multiple lemmata analyses are mutually exclusive.
  // Whichever one is there ends up in the analysesForSentence column

  $sSelectQuery = "(SELECT token_attestations.analyzed_wordform_id," .
    " token_attestations.derivation_id, start_pos," .
    " CONCAT(REPLACE(REPLACE(REPLACE(lemmata.modern_lemma, '>', '&gt;'), '<', '&lt;'), ' ', '&nbsp;')," .
    " IF(myPatterns.normalized_wordform IS NULL, ''," .
    " CONCAT(',&nbsp;',myPatterns.normalized_wordform))," .
    " IF(myPatterns.patterns IS NULL, '', CONCAT(',&nbsp;'," .
    " myPatterns.patterns)), ',&nbsp;', lemma_part_of_speech," .
    // NOTE that the lemmata.language_id IS NULL is only there for backwards
    // compatibility
    " IF(lemmata.language_id IS NULL OR lemmata.language_id = 0," .
    "    '', CONCAT(',&nbsp;'," .
    " languages.language))," .
    " IF(lemmata.gloss = '', ''," .
    "    CONCAT(',&nbsp;', REPLACE(gloss, ' ', '&nbsp;'))))" .
    " analysesForSentence " .
    "FROM token_attestations, analyzed_wordforms a1 " .
    "LEFT JOIN (SELECT a2.analyzed_wordform_id," .
    " CONCAT('&lt;', normalized_form, '&gt;') AS normalized_wordform," .
    " CONCAT('[', GROUP_CONCAT(CONCAT('(', left_hand_side, '_'," .
    " right_hand_side, ',&nbsp;', pattern_applications.position, ')')), ']')" .
    "   AS patterns ".
    "FROM analyzed_wordforms a2, derivations " .
    "LEFT JOIN pattern_applications ON " .
    "(pattern_applications.pattern_application_id" .
    " = derivations.pattern_application_id) " .
    "LEFT JOIN patterns ON" .
    "    (pattern_applications.pattern_id = patterns.pattern_id) " .
    "WHERE a2.derivation_id = derivations.derivation_id " .
    "  AND a2.wordform_id = $iWordFormId " .
    "GROUP BY a2.analyzed_wordform_id) " .
    "myPatterns ON (myPatterns.analyzed_wordform_id=a1.analyzed_wordform_id),".
    " lemmata " .
    "LEFT JOIN languages ON (languages.language_id = lemmata.language_id) " .
    "WHERE token_attestations.analyzed_wordform_id = a1.analyzed_wordform_id" .
    "  AND a1.lemma_id = lemmata.lemma_id" .
    "  AND token_attestations.document_id = $iDocumentId";
  if( $iStartPos != -1)
    $sSelectQuery .= "  AND start_pos = $iStartPos";
  $sSelectQuery .=
    "  AND a1.wordform_id = $iWordFormId)" .
    " UNION " .

    // LEFT JOIN for multiple lemmata analyses
    "(SELECT analyzed_wordforms.analyzed_wordform_id," .
    " token_attestations.derivation_id," . // <-- Klopt dat wel bij multiples?!?
    " token_attestations.start_pos," .
    " mla.analysesForSentence" .
    " FROM token_attestations, analyzed_wordforms " .
    "LEFT JOIN (SELECT multiple_lemmata_analyses.multiple_lemmata_analysis_id,"
    /// 2011-09-05: geen DISTINCT
    /// . "  GROUP_CONCAT(DISTINCT " .
    . "  GROUP_CONCAT(" .
    "   CONCAT(REPLACE(REPLACE(REPLACE(lemmata.modern_lemma, '>', '&gt;'), '<', '&lt;'), ' ', '&nbsp;'), " .

    "    IF(myPatterns.normalized_wordform IS NULL, ''," .
    "       CONCAT(',&nbsp;', myPatterns.normalized_wordform))," .
    "    IF(myPatterns.patterns IS NULL, ''," .
    "       CONCAT(',&nbsp;', myPatterns.patterns))," .

    "   ',&nbsp;', lemmata.lemma_part_of_speech," .
    "   IF(lemmata.gloss = '', ''," .
    "      CONCAT(',&nbsp;', REPLACE(lemmata.gloss, ' ', '&nbsp;')))," .
    "   IF(languages.language IS NULL, ''," .
    "              CONCAT(',&nbsp;', languages.language)))".
    ///
    "   ORDER BY multiple_lemmata_analyses.part_number ASC" .
    ///
    "   SEPARATOR '&nbsp;&&nbsp;') analysesForSentence " .
    " FROM analyzed_wordforms, multiple_lemmata_analyses," .
    "      multiple_lemmata_analysis_parts mlapartsOuter" .

    " LEFT JOIN (SELECT multiple_lemmata_analyses.part_number," .
    "                   mlapartsInner.multiple_lemmata_analysis_part_id," .
    "  CONCAT('&lt;', normalized_form, '&gt;') AS normalized_wordform," .
    "  CONCAT('[', GROUP_CONCAT(CONCAT('(', left_hand_side, '_'," .
    "   right_hand_side, ',&nbsp;', pattern_applications.position,')')), ']')".
    "    AS patterns" .
    " FROM analyzed_wordforms, multiple_lemmata_analyses, " .
    "      multiple_lemmata_analysis_parts mlapartsInner, derivations" .
    // Two LEFT JOINs here because derivations can occur without patterns
    " LEFT JOIN pattern_applications ON".
    "(pattern_applications.pattern_application_id" .
    " = derivations.pattern_application_id)" .
    " LEFT JOIN patterns ON" .
    "    (pattern_applications.pattern_id = patterns.pattern_id) " .
    "WHERE mlapartsInner.derivation_id = derivations.derivation_id " .
    // These tables are only in to narrow it down somewhat
    "  AND analyzed_wordforms.wordform_id = $iWordFormId" .
    "  AND analyzed_wordforms.multiple_lemmata_analysis_id" .
    "      = multiple_lemmata_analyses.multiple_lemmata_analysis_id" .
    "  AND multiple_lemmata_analyses.multiple_lemmata_analysis_part_id" .
    "      = mlapartsInner.multiple_lemmata_analysis_part_id " .
    "GROUP BY mlapartsInner.multiple_lemmata_analysis_part_id)".
    " myPatterns ON (myPatterns.multiple_lemmata_analysis_part_id" .
    "                = mlapartsOuter.multiple_lemmata_analysis_part_id)," .

    "      token_attestations, lemmata " .
    "LEFT JOIN languages ON (languages.language_id = lemmata.language_id) " .
    "WHERE token_attestations.document_id = $iDocumentId" .
    "  AND multiple_lemmata_analyses.multiple_lemmata_analysis_part_id" .
    "  = mlapartsOuter.multiple_lemmata_analysis_part_id" .
    // Added 2011-07-14
    "  AND analyzed_wordforms.wordform_id = $iWordFormId" .
    //
    "  AND analyzed_wordforms.multiple_lemmata_analysis_id" .
    "   = multiple_lemmata_analyses.multiple_lemmata_analysis_id" .
    "  AND analyzed_wordforms.analyzed_wordform_id" .
    "   = token_attestations.analyzed_wordform_id" .
    "  AND analyzed_wordforms.wordform_id = $iWordFormId" .
    "  AND mlapartsOuter.lemma_id = lemmata.lemma_id " .
    "GROUP BY multiple_lemmata_analysis_id) mla " .
    "ON (mla.multiple_lemmata_analysis_id" .
    "  = analyzed_wordforms.multiple_lemmata_analysis_id) " .
    "WHERE analyzed_wordforms.wordform_id = $iWordFormId" .
    "  AND analyzed_wordforms.lemma_id = 0" .
    "  AND token_attestations.document_id = $iDocumentId";
  if( $iStartPos != -1 )
    $sSelectQuery .= "  AND token_attestations.start_pos = $iStartPos";
  $sSelectQuery .=
    "  AND token_attestations.analyzed_wordform_id" .
    "   = analyzed_wordforms.analyzed_wordform_id)" .
    " ORDER BY start_pos";

  if( ($oResult = doSelectQuery($sSelectQuery) ) )
    return $oResult;
  return false;
}

// 2011-0610: Klopt dit wel?!? Zie geen multiple lemmata analyses..?!?!?!?
//
// This one is called on loading the page to fill every 100/so many token
// attestations in the top half of the screen
// We make a query for every analyzed word in the database and a left join
// for the ones just in this corpus (which is always a subset)
function fillTokenAttestations($iUserId, $iUserName, $sMode, $iId,
			       $sWordFormIds) {
  $sSelectQuery = "SELECT analyzed_wordforms.wordform_id, " .
    "tokenAttsInCorpus.tokenAttsInCorpus,  " .
    "GROUP_CONCAT(DISTINCT" .
    " CONCAT(IF(verified_by IS NULL, 0, 1), ','," .
    " analyzed_wordforms.analyzed_wordform_id," .
    " ',', REPLACE(REPLACE(lemmata.modern_lemma, '>', '&gt;'), '<', '&lt;'), ',', lemma_part_of_speech, " .
    "IF(lemmata.gloss = '', '',CONCAT(',', gloss))) SEPARATOR '|') " .
    "AS analysesInDb " .
    "FROM token_attestations, lemmata, analyzed_wordforms " .
    // LEFT JOIN
    "LEFT JOIN (SELECT analyzed_wordforms.wordform_id AS wfId, " .
    "GROUP_CONCAT(DISTINCT CONCAT(analyzed_wordforms.analyzed_wordform_id, " .
    "',', REPLACE(REPLACE(lemmata.modern_lemma, '>', '&gt;'), '<', '&lt;'), ',', lemma_part_of_speech, " .
    "IF(lemmata.gloss = '', '', CONCAT(',', gloss))) SEPARATOR '|') " .
    "AS tokenAttsInCorpus " .
    "FROM token_attestations, analyzed_wordforms, lemmata ";
  if( $sMode == 'corpus')
    $sSelectQuery .= ", corpusId_x_documentId " .
      "WHERE corpusId_x_documentId.corpus_id = $iId " .
      "AND corpusId_x_documentId.document_id = token_attestations.document_id";
  else
    $sSelectQuery .= "WHERE token_attestations.document_id = $iId";
  $sSelectQuery .= " AND analyzed_wordforms.wordform_id IN ($sWordFormIds) " .
    " AND token_attestations.analyzed_wordform_id = " .
    "analyzed_wordforms.analyzed_wordform_id" .
    " AND analyzed_wordforms.lemma_id = lemmata.lemma_id " .
    "GROUP BY analyzed_wordforms.wordform_id) tokenAttsInCorpus " .
    "ON (tokenAttsInCorpus.wfId = analyzed_wordforms.wordform_id) " .
    // End of LEFT JOIN
    // Rest of the main query 
    "WHERE token_attestations.analyzed_wordform_id = " .
    "analyzed_wordforms.analyzed_wordform_id " .
    "AND analyzed_wordforms.wordform_id IN ($sWordFormIds) " .
    "AND analyzed_wordforms.lemma_id = lemmata.lemma_id " .
    "GROUP BY analyzed_wordforms.wordform_id";

  // Now we put everything together to give back to Javascript.
  // The reason we don't do this directly in the MySQL query is because the
  // fields could become too long.
  $sSeparator = '';
  if( ($oResult = doSelectQuery($sSelectQuery) ) ) {
    while( ($aRow = mysql_fetch_assoc($oResult)) ) {
      print $sSeparator . $aRow['wordform_id'] . "#" .
	$aRow['tokenAttsInCorpus'] . "#" . $aRow['analysesInDb'];
      $sSeparator = "\t";
    }
    mysql_free_result($oResult);
  }
}

// This function gets you all the wordform_id's for group members of the 
// attestations in $aSelected (excluding the selecteds themselves)
//
function getWordformIdsForGroups($iUserId, $aSelected, &$aVerificationValues){
  $sSelectQuery =
    "SELECT tokens.wordform_id, CONCAT(wordform_groups.document_id, ',', " .
    "       wordform_groups.onset, ',', wordform_groups.offset) tuple" .
    "  FROM wordform_groups, " . $GLOBALS['sTokenDbName'] . ".tokens" .
    " WHERE wordform_groups.document_id = tokens.document_id" .
    "   AND wordform_groups.onset = tokens.onset" .
    "   AND wordform_groups.offset = tokens.offset" .
    "   AND tokens.lexicon_database_id = " . $GLOBALS['iTokenDbDatabaseId'] .
    "   AND wordform_group_id IN" .
    "   (SELECT wordform_group_id" .
    "      FROM wordform_groups" .
    "     WHERE ";
  $sOr = $sAnd = $sCondition = '';
  foreach($aSelected as $sSelected) {
    $aTuple = explode(',', $sSelected);
    $sSelectQuery .= "$sOr(document_id = $aTuple[0]" .
      " AND onset = $aTuple[1] AND offset = $aTuple[2])";
    $sCondition .= "$sAnd(NOT (wordform_groups.document_id = $aTuple[0] " .
      "AND wordform_groups.onset = $aTuple[1] " .
      "AND wordform_groups.offset = $aTuple[2]))";
    $sOr = ' OR ';
    $sAnd = ' AND ';
  }
  $sSelectQuery .= ") AND $sCondition";
  
  $aWordformIdsForGroups = false;
  if( ($oResult = doSelectQuery($sSelectQuery) ) ) {
    $aWordformIdsForGroups = array();
    while( ($aRow = mysql_fetch_assoc($oResult)) ) {
      array_push($aWordformIdsForGroups, array($aRow['wordform_id'],
					       $aRow['tuple']));
      array_push($aVerificationValues,
		 "(". $aRow['wordform_id'].", $iUserId, NOW(), " .
		 $aRow['tuple'] . ")");
    }
    mysql_free_result($oResult);
  }
  return $aWordformIdsForGroups;
}

// This function is called when somebody (supposedly) updated the posInput
// text box.
// Some checking is done to see if there is anything actually there...
function addTokenAttestations($iUserId, $iWordFormId,$sSelected, $sLemmaTuples,
			      $bVerify) {
  printLog("Doing addTokenAttestations($iUserId, $iWordFormId, '$sSelected', ".
	   "'$sLemmaTuples', $bVerify)\n");
  $aLemmaTuples = explode("|", $sLemmaTuples);
  $aSelected = explode("|", $sSelected);
  $aValues = array();
  $aVerificationValues = array();
  $aAnalyzedWordFormsToVerify = array();
  $aWordformIdsForGroups = getWordformIdsForGroups($iUserId, $aSelected,
						   $aVerificationValues);

  // Token tuple: documentId, onset, offset
  foreach( $aSelected as $sTokenTuple ) {
    array_push($aVerificationValues,
	       "($iWordFormId, $iUserId, NOW(), $sTokenTuple)"); 
  }
  
  foreach( $aLemmaTuples as $sLemmaTuple) {
    if( preg_match("/\w/", $sLemmaTuple) ) {
      $aAnalyzedWordFormIdsDerivationIds = (strpos($sLemmaTuple, '&')) ?
	addMultipleLemmataAnalysis($iUserId, $iWordFormId,
				   $aWordformIdsForGroups, $sLemmaTuple) :
	getAnalysedWordFormIdDerivationId_forLemmaTuple($iUserId,
							$iWordFormId,
							$aWordformIdsForGroups,
							$sLemmaTuple);

      if( $aAnalyzedWordFormIdsDerivationIds) {
	foreach($aAnalyzedWordFormIdsDerivationIds as
		$aAnalyzedWordFormIdDerivationId) {
	  // If the third argument has a value, it was group member
	  if( isset($aAnalyzedWordFormIdDerivationId[2]) ) {
	    array_push($aValues,
		       "($aAnalyzedWordFormIdDerivationId[0], " .
		       "$aAnalyzedWordFormIdDerivationId[1], " .
		       "$aAnalyzedWordFormIdDerivationId[2])");
	    $aAnalyzedWordFormsToVerify[$aAnalyzedWordFormIdDerivationId[0]]=1;
	  }
	  else { // It is the word form itself
	    // Token tuple: documentId,startPos,endPos
	    foreach( $aSelected as $sTokenTuple ) {
	      array_push($aValues,
			 "($aAnalyzedWordFormIdDerivationId[0], " .
			 "$aAnalyzedWordFormIdDerivationId[1], " .
			 "$sTokenTuple)");
	      $aAnalyzedWordFormsToVerify[$aAnalyzedWordFormIdDerivationId[0]]
		= 1;
	    }
	  }
	}
      }
    }
  }

  if( count($aValues) ) {
    // NOTE the ON DUPLICATE key bit, which doesn't really add anything but 
    // which makes it possible for people to add a lemma more than once
    // as an attestation (even though this doesn't result in anything new)
    // without the query giving errors
    $sInsertQuery = "INSERT INTO token_attestations " .
      "(analyzed_wordform_id, derivation_id, document_id, start_pos, end_pos)".
      " VALUES " . implode(", ", $aValues) .
      " ON DUPLICATE KEY UPDATE document_id = document_id";
    doNonSelectQuery($sInsertQuery);

    // Verify the analyses
    // NOTE that this is regardless of whether the token attestations are
    // being verified or not.
    $sUpdateQuery = "UPDATE analyzed_wordforms SET verified_by = $iUserId, " .
      "verification_date = NOW() WHERE analyzed_wordform_id IN (" .
      implode(", ", array_keys($aAnalyzedWordFormsToVerify)) . ")";
    doNonSelectQuery($sUpdateQuery);
  }

  // Verify the token attestations.
  // NOTE that this also happens when there are no token attestations...
  if( $bVerify ) {
    $sInsertQuery = "INSERT INTO token_attestation_verifications " .
      "(wordform_id, verified_by, verification_date, document_id, start_pos, ".
      " end_pos) VALUES " . implode(", ", $aVerificationValues) .
      " ON DUPLICATE KEY " .
      "UPDATE verified_by = $iUserId, verification_date = NOW()";
    doNonSelectQuery($sInsertQuery);
  }
}

function addMultipleLemmataAnalysis($iUserId, $iWordFormId,
				    $aWordformIdsForGroups, $sLemmaTuples) {
  printLog("Getting data for multiple lemma tuple; '$sLemmaTuples'\n");

  $aLemmaTuples = explode("&", $sLemmaTuples);
  $aPartIds = array();
  foreach( $aLemmaTuples as $sLemmaTuple) {
    printLog("Getting data for lemma tuple; '$sLemmaTuple'\n");

    $iPartId = getPartId($sLemmaTuple, 'add');

    if( $iPartId )
      array_push($aPartIds, $iPartId);
    else
      return false;
  }

  // Insert into the multiple lemmata analysis table
  $aAnalyzedWordFormIdsDerivationIds =
    array(array(insertMultipleLemmataAnalysis($iUserId,
					      $iWordFormId,
					      $aPartIds),
		0) );

  foreach($aWordformIdsForGroups as $aWordFormGroupId) {
    $iAnalyzedWordFormId = insertMultipleLemmataAnalysis($iUserId,
							 $aWordFormGroupId[0],
							 $aPartIds);
    array_push($aAnalyzedWordFormIdsDerivationIds,
	       array($iAnalyzedWordFormId, 0, $aWordFormGroupId[1]));
  }

  return $aAnalyzedWordFormIdsDerivationIds;
}

function insertMultipleLemmataAnalysis($iUserId, $iWordFormId, $aPartIds) {
  $iNrOfParts = count($aPartIds);

  $sExistsConditions = '';
  //foreach ($aPartIds as $iPartId) {
  for($i = 0; $i < count($aPartIds); $i++) {
    $sExistsConditions .=
      " AND EXISTS (SELECT * FROM multiple_lemmata_analyses mla2" .
      "              WHERE mla2.multiple_lemmata_analysis_id = " .
      "                                     mla.multiple_lemmata_analysis_id" .
      ///
      "                AND mla2.part_number = " . ($i + 1) . 
      ///
      "                AND mla2.multiple_lemmata_analysis_part_id = " .
      ///"                                     $iPartId)";
      $aPartIds[$i] . ")";
  }

  // First see if it is in already for this wordform.
  // The query returns as many rows as there are parts in the analyses, so we
  // LIMIT it to 1...
  $sSelectQuery = "SELECT analyzed_wordforms.analyzed_wordform_id " .
    "FROM analyzed_wordforms, multiple_lemmata_analyses mla " .
    // This is where the nr_of_parts column comes in handy
    " WHERE analyzed_wordforms.wordform_id = $iWordFormId" .
    "   AND analyzed_wordforms.multiple_lemmata_analysis_id" .
    "  = mla.multiple_lemmata_analysis_id" .
    "   AND mla.nr_of_parts = $iNrOfParts" .
    $sExistsConditions; /// . " LIMIT 1";
  /// TK 2011-09-05: LIMIT is niet meer nodig met part_numbers erbij

  $iAnalyzedWordFormId = false;
  if( ($oResult = doSelectQuery($sSelectQuery) ) ) {
    if( ($aRow = mysql_fetch_assoc($oResult)) )
      $iAnalyzedWordFormId = $aRow['analyzed_wordform_id'];
    mysql_free_result($oResult);
  }
  if( $iAnalyzedWordFormId )
    return $iAnalyzedWordFormId;
 
  // The analyzed wordform wasn't in, so we have to insert it

  // First insert try to find if the multiple lemmata analysis record exists
  // already for this combination
  $iMultipleLemmaAnalysisId =
    getMultipleLemmataAnalysisId($aPartIds, $iNrOfParts);

  // If not, insert it
  if( ! $iMultipleLemmaAnalysisId ) {
    $sInsertQuery = "INSERT INTO multiple_lemmata_analyses " .
      "(multiple_lemmata_analysis_id, " .
      " multiple_lemmata_analysis_part_id, part_number, nr_of_parts) " .
      // New part that ensures that the id is always > 0
      "SELECT IF(MAX(multiple_lemmata_analysis_id) IS NULL, 1," .
      "  MAX(multiple_lemmata_analysis_id) + 1), $aPartIds[0], 1, $iNrOfParts" .
      " FROM multiple_lemmata_analyses";
    doNonSelectQuery($sInsertQuery);

    // Get the id
    // We do an ORDER BY and LIMIT because there might be more than one of these
    // and we want the last one
    $sSelectQuery = "SELECT multiple_lemmata_analysis_id " .
      "FROM multiple_lemmata_analyses " .
      "WHERE multiple_lemmata_analysis_part_id = $aPartIds[0] " .
      "  AND part_number = 1 AND nr_of_parts = $iNrOfParts " .
      "ORDER BY multiple_lemmata_analysis_id DESC LIMIT 1";
    if( ($oResult = doSelectQuery($sSelectQuery) ) ) {
      if( ($aRow = mysql_fetch_assoc($oResult)) )
	$iMultipleLemmaAnalysisId = $aRow['multiple_lemmata_analysis_id'];
      mysql_free_result($oResult);
    }

    if( $iMultipleLemmaAnalysisId === false)
      return false;

    // Generate the right values for the insert query
    $sValues = '';
    $sSeparator = '';
    for( $iPartNr = 2; $iPartNr <= $iNrOfParts; $iPartNr++) {
      $sValues .=
	"$sSeparator($iMultipleLemmaAnalysisId, " .
	$aPartIds[$iPartNr-1] . ", $iPartNr, $iNrOfParts)";
      $sSeparator = ", ";
    }
  
    $sInsertQuery = "INSERT INTO multiple_lemmata_analyses " .
      "(multiple_lemmata_analysis_id, " .
      " multiple_lemmata_analysis_part_id, part_number, nr_of_parts) " .
      "VALUES $sValues";
    doNonSelectQuery($sInsertQuery);
  }

  // Now that we have a multiple lemmata analysis id, insert an analyzed
  // wordform
  $sInsertQuery = "INSERT INTO analyzed_wordforms " .
    "(lemma_id, wordform_id, multiple_lemmata_analysis_id, verified_by," .
    " verification_date) " .
    "VALUES (0, $iWordFormId, $iMultipleLemmaAnalysisId, $iUserId, NOW())" .
    "ON DUPLICATE KEY UPDATE verified_by= $iUserId, verification_date = NOW()";
  doNonSelectQuery($sInsertQuery);

  // Get its id
  $sSelectQuery = "SELECT analyzed_wordform_id FROM analyzed_wordforms " .
    "WHERE lemma_id = 0 AND wordform_id = $iWordFormId" .
    "  AND multiple_lemmata_analysis_id = $iMultipleLemmaAnalysisId";

  $iAnalyzedWordFormId = false;
  if( ($oResult = doSelectQuery($sSelectQuery) ) ) {
    if( ($aRow = mysql_fetch_assoc($oResult)) )
      $iAnalyzedWordFormId = $aRow['analyzed_wordform_id'];
    mysql_free_result($oResult);
  }

  return $iAnalyzedWordFormId;
}

// Here we go through some trouble to construct a query that gets you the right
// multiple lemmata analysis id.
function getMultipleLemmataAnalysisId($aPartIds, $iNrOfParts) {
  $sTables = $sConditions = '';
  $iPartNr = 1;
  $sComma = '';
  foreach( $aPartIds as $iPartId) {
    $sTableName = "mla$iPartNr";
    $sTables .= $sComma . "multiple_lemmata_analyses $sTableName";
    $sComma = ", ";
    $sConditions .=
      " AND $sTableName.multiple_lemmata_analysis_part_id = $iPartId" .
      // 2011-09-05: part_number erbij
      " AND $sTableName.part_number = $iPartNr";
    if( $iPartNr > 1) 
      $sConditions .= " AND $sTableName.multiple_lemmata_analysis_id =" .
	"       mla1.multiple_lemmata_analysis_id";
    $iPartNr++;
  }

  $sSelectQuery = "SELECT mla1.multiple_lemmata_analysis_id" .
    "  FROM $sTables" .
    " WHERE mla1.nr_of_parts = $iNrOfParts $sConditions";

  $iMultipleLemmaAnalysisId = false;
  if( ($oResult = doSelectQuery($sSelectQuery) ) ) {
    if( $aRow = mysql_fetch_assoc($oResult) ) {
      $iMultipleLemmaAnalysisId = $aRow['multiple_lemmata_analysis_id'];
    }
    mysql_free_result($oResult);
  }
  return $iMultipleLemmaAnalysisId;
}

// This function does the same as the one above but based on data which is
// provided differently.
// It is called when someone clicks on a clickable token attestation (in the
// right column of the upper part of the screen, the token attestations for
// the database).
function addTokenAttestations2($iUserId, $iWordFormId, $sTokenAttestations,
			       $bVerify) {
  printLog("addTokenAttestations2($iUserId, $iWordFormId, " .
	   "'$sTokenAttestations', $bVerify)\n");
  $aValues = array();
  $aVerificationValues = array();
  $aAnalyzedWordFormsToVerify = array();

  // We go through the token attestations once to get the right string for both
  // queries
  // The token attestations come as a '|' separated list of tuples
  // wordformId,docId,onset,offset,analyzedWordformId
  $aTokenAttestations = explode("|", $sTokenAttestations);

  $sGroupCondition1 = $sGroupCondition2 = $sAnd = $sOr = '';
  // Each tuple is: wordformId, docId, startPos, endPos, analyzedWordformId
  $iAnalyzedWordFormId;
  foreach( $aTokenAttestations as $sTokenTuple ) {
    $aTokenTuple = explode(",", $sTokenTuple);   
    $iAnalyzedWordFormId = $aTokenTuple[4]; // It's always the same, but well..
    // analyzed_wordform_id, document_id, start_pos, end_pos
    array_push($aValues, "(" . $aTokenTuple[4] . ", " . $aTokenTuple[1] . "," .
	       $aTokenTuple[2] . "," . $aTokenTuple[3] . ")");
    $aAnalyzedWordFormsToVerify[$aTokenTuple[4]] = 1;
    
    $sGroupCondition1 .= $sOr .
      "(document_id = $aTokenTuple[1] AND onset = $aTokenTuple[2])";
    $sGroupCondition2 .= $sAnd .
      "(NOT (wordform_groups.document_id = $aTokenTuple[1]" .
      " AND wordform_groups.onset = $aTokenTuple[2]))";
    $sOr = ' OR ';
    $sAnd = ' AND ';

    if( $bVerify) 
      //wordform_id,verified_by,verification_date,document_id,start_pos,end_pos
      array_push($aVerificationValues,
		 "($iWordFormId, $iUserId, NOW(), " . $aTokenTuple[1] . "," .
		 $aTokenTuple[2] . "," . $aTokenTuple[3] . ")");
  }

  // Now we have the right strings for the queries.
  if( count($aValues) ) {
    expandForGroups($iUserId, $iAnalyzedWordFormId, $sGroupCondition1,
		    $sGroupCondition2, $aValues, $aVerificationValues,
		    $aAnalyzedWordFormsToVerify, $bVerify);

    // NOTE that ON DUPLICATE key bit, which doesn't really add anything but 
    // which makes it possible for people to add a lemma more than once
    // as an attestation (even though this doesn't result in anything new)
    // without the query giving errors
    $sInsertQuery = "INSERT INTO token_attestations " .
      "(analyzed_wordform_id, document_id, start_pos, end_pos) VALUES " .
      implode(", ", $aValues) .
      " ON DUPLICATE KEY UPDATE document_id = document_id";
    doNonSelectQuery($sInsertQuery);

    // Verify the analyses
    // NOTE that this is regardless of whether the token attestations are
    // being verified or not.
    $sUpdateQuery = "UPDATE analyzed_wordforms SET verified_by = $iUserId, " .
      "verification_date = NOW() WHERE analyzed_wordform_id IN (" .
      implode(", ", array_keys($aAnalyzedWordFormsToVerify)) . ")";
    doNonSelectQuery($sUpdateQuery);
  }

  // Verify the token attestations.
  // NOTE that this also happens when there are no token attestations...
  if( $bVerify ) {
    $sInsertQuery = "INSERT INTO token_attestation_verifications " .
      "(wordform_id, verified_by, verification_date, document_id, start_pos, ".
      " end_pos) VALUES " . implode(", ", $aVerificationValues) .
      " ON DUPLICATE KEY " .
      "UPDATE verified_by = $iUserId, verification_date = NOW()";
    doNonSelectQuery($sInsertQuery);
  }
}

function expandForGroups($iUserId, $iAnalyzedWordFormId, $sGroupCondition1,
			 $sGroupCondition2, &$aValues, &$aVerificationValues,
			 &$aAnalyzedWordFormsToVerify, $bVerify) {
  // Get the right values so we can copy them
  // There is no need to go into any detail here about multiple lemmata
  // analyses or not, because we are just copying.
  $sSelectQuery =
    "SELECT lemma_id, derivation_id, multiple_lemmata_analysis_id" .
    "  FROM analyzed_wordforms" .
    " WHERE analyzed_wordform_id = $iAnalyzedWordFormId";
  
  $aAWFTuple = false;
  if( ($oResult = doSelectQuery($sSelectQuery) ) )
    if( $aRow = mysql_fetch_assoc($oResult) )
      $aAWFTuple = array($aRow['lemma_id'], $aRow['derivation_id'],
			 $aRow['multiple_lemmata_analysis_id']);
  if( ! $aAWFTuple ) // This shouldn't happen of course...
    return false;

  // Get all wordformIds, docIds, onset, offsets for groupmembers
  // (NOT including the headwords). 
  $sSelectQuery = "SELECT tokens.wordform_id, wordform_groups.document_id, " .
    "wordform_groups.onset, wordform_groups.offset" .
    "  FROM wordform_groups, " . $GLOBALS['sTokenDbName'] . ".tokens" .
    " WHERE wordform_groups.document_id = tokens.document_id" .
    "   AND tokens.lexicon_database_id = " . $GLOBALS['iTokenDbDatabaseId'] .
    "   AND wordform_groups.onset = tokens.onset" .
    "   AND wordform_groups.wordform_group_id IN".
    "       (SELECT wordform_group_id FROM wordform_groups" .
    "         WHERE $sGroupCondition1)" .
    "   AND $sGroupCondition2";
  $sInsertValues = $sSelectCondition = $sComma = $sOr = '';
  $aTokens = false;;
  if( ($oResult = doSelectQuery($sSelectQuery) ) ) {
    while( $aRow = mysql_fetch_assoc($oResult) ) {
      if( ! $aTokens)
	$aTokens = array();
      if( ! isset($aTokens[$aRow['wordform_id']]) )
	$aTokens[$aRow['wordform_id']] =
	  array(array($aRow['document_id'], $aRow['onset'], $aRow['offset']));
      else
	array_push($aTokens[$aRow['wordform_id']],
		   array($aRow['document_id'],$aRow['onset'],$aRow['offset']));

      $sInsertValues .= $sComma .
	"(" . $aRow['wordform_id'] . ", " . $aAWFTuple[0] . ", " .
	$aAWFTuple[1] . ", " . $aAWFTuple[2] . ", $iUserId, NOW())";
      $sSelectCondition .= $sOr .
	"(wordform_id = " . $aRow['wordform_id'] . " AND lemma_id = " .
	$aAWFTuple[0] . " AND derivation_id = " . $aAWFTuple[1] .
	" AND multiple_lemmata_analysis_id = " . $aAWFTuple[2] . ")";
      $sComma = ', ';
      $sOr = ' OR ';
    }
    mysql_free_result($oResult);
  }

  if( strlen($sInsertValues) ) { // If we found a group
    $sInsertQuery = "INSERT INTO analyzed_wordforms " .
      "(wordform_id, lemma_id, derivation_id, multiple_lemmata_analysis_id," .
      " verified_by, verification_date) VALUES $sInsertValues " .
      "ON DUPLICATE KEY UPDATE verified_by = $iUserId, " .
      " verification_date = NOW()";
    doNonSelectQuery($sInsertQuery);
    
    $sSelectQuery = "SELECT analyzed_wordform_id, wordform_id" .
      "  FROM analyzed_wordforms" .
      " WHERE $sSelectCondition";
    if( ($oResult = doSelectQuery($sSelectQuery)) ) {
      while( ($aRow = mysql_fetch_assoc($oResult)) ) {
	foreach( $aTokens[$aRow['wordform_id']] as $aTokenTuple) {
	  array_push($aValues, "(" . $aRow['analyzed_wordform_id'] . ", " .
		     $aTokenTuple[0] . "," . $aTokenTuple[1] . "," .
		     $aTokenTuple[2] . ")");
	  // wordformId, verifiedBy, verificationDate,documentId,startPos,endPos
	  if( $bVerify) 
	    array_push($aVerificationValues,
		       "(" . $aRow['wordform_id'] . ", $iUserId, NOW(), " .
		       $aTokenTuple[0] . "," . $aTokenTuple[1] . "," .
		       $aTokenTuple[2] . ")");
	}
	$aAnalyzedWordFormsToVerify[$aRow['analyzed_wordform_id']] = 1;
      }
    }
  }
}

// This function deletes all token attestations for this corpus/file
// It is called when a user alters something in the corpusAttestations in the 
// upper part of the screen
//
function deleteTokenAttestations($iId, $sMode, $iWordFormId, $sSelected) {
  printLog("Doing deleteTokenAttestations" .
	   "($iId, '$sMode', $iWordFormId, '$sSelected')\n");

  $aSelected = explode("|", $sSelected);
  $aConditions = array();
  $sGroupCondition = '';
  $sGroupConditionSeparator = '';

  // Token tuple: documentId, onset, offset
  foreach( $aSelected as $sTokenTuple ) {
    $aTokenTuple = explode(',', $sTokenTuple);
    // Offset er nog bij..?!?
    array_push($aConditions,
	       "(token_attestations.document_id = $aTokenTuple[0] AND" .
	       " token_attestations.start_pos = $aTokenTuple[1])");
    $sGroupCondition .= $sGroupConditionSeparator .
      "(document_id = $aTokenTuple[0] AND onset = $aTokenTuple[1])";
    $sGroupConditionSeparator = " OR ";
  }

  // First, delete all token attestations attached to group members (including
  // the word form itself)
  $sDeleteGroupQuery = "DELETE token_attestations.* " .
    " FROM token_attestations," .
    "      (SELECT *" .
    "         FROM wordform_groups" .
    "        WHERE wordform_group_id IN (SELECT wordform_group_id" .
    "                                    FROM wordform_groups" .
    "                                  WHERE $sGroupCondition)) groupOnsets ".
    "WHERE groupOnsets.document_id = token_attestations.document_id" .
    "  AND groupOnsets.onset = token_attestations.start_pos"; 
  doNonSelectQuery($sDeleteGroupQuery);

  $sDeleteQuery;
  /// Wat doet het verschil tussen corpus en file hier ertoe..?!? ///
  if( $sMode == 'file') {
    $sDeleteQuery = "DELETE token_attestations.* ".
      "FROM token_attestations, analyzed_wordforms " .
      "WHERE ";
    if(count($aConditions))
      $sDeleteQuery .= "(" . implode(" OR ", $aConditions) . ") ";
    else 
      $sDeleteQuery .= "token_attestations.document_id = $iId";
    $sDeleteQuery .=
      "  AND token_attestations.analyzed_wordform_id = " .
      "                              analyzed_wordforms.analyzed_wordform_id".
      "  AND analyzed_wordforms.wordform_id = $iWordFormId";
  }
  else { // corpus mode
    $sDeleteQuery = "DELETE token_attestations.* ".
      "FROM token_attestations, analyzed_wordforms";
    if( count($aConditions))
      $sDeleteQuery .=  " WHERE (" . implode(" OR ", $aConditions) . ") ";
    else
      $sDeleteQuery .=  ", corpusId_x_documentId " .
	"WHERE token_attestations.document_id = " .
        "                                  corpusId_x_documentId.document_id".
	"  AND corpusId_x_documentId.corpus_id = $iId";
    $sDeleteQuery .=
      "  AND token_attestations.analyzed_wordform_id = " .
      "                              analyzed_wordforms.analyzed_wordform_id".
      "  AND analyzed_wordforms.wordform_id = $iWordFormId";
  }
  doNonSelectQuery($sDeleteQuery);

  // Also, if there are no text or token attestations left for this word form
  // we can delete the analyzed word form record
  // But we do this later on separately
}

function verifyTokenAttestation(// $iDocumentId,
				$sSelecteds,
				$iWordFormId,
				// $iStartPos, $iEndPos,
				$iNewValue, $iUserId) {
  printLog("verifyTokenAttestation('$sSelecteds', $iWordFormId, $iNewValue, ".
	   "$iUserId)\n");
  // Selecteds come as docId1,startPos1,endPos1|docId2,startPos2,endPos2|etc..
  $aSelecteds = explode("|", $sSelecteds);

  $sQuery;
  if( $iNewValue == 1 ) {
    $sInsertValues =  $sInsertSeparator = $sVerifyCondition =
      $sVerifySeparator = $sInsertGroupCondition = '';
    foreach($aSelecteds as $sSelected) {
      // Selected is tuple: docId,startPos,endPos
      $aSelected = explode(",", $sSelected);

      $sInsertValues .=
	"$sInsertSeparator($sSelected, $iWordFormId, $iUserId, NOW())";
      // NOTE that is remarkably similar to the delete condition below
      $sVerifyCondition .= "$sVerifySeparator(document_id = $aSelected[0]" .
	" AND start_pos = $aSelected[1] AND end_pos = $aSelected[2])";
      $sInsertGroupCondition .= $sVerifySeparator .
	"(document_id = $aSelected[0] AND onset = $aSelected[1])";
      $sInsertSeparator = ", ";
      $sVerifySeparator = " OR ";
    }

    // NOTE that due to the ON DUPLICATE KEY UPDATE bit the attestation will
    // be verified by this user, even if it was verified by someone else
    // before...
    $sQuery = "INSERT INTO token_attestation_verifications " .
      "(document_id, start_pos, end_pos, wordform_id, verified_by, " .
      "verification_date) " .
      "VALUES $sInsertValues" .
      " ON DUPLICATE KEY UPDATE verified_by = $iUserId," .
      " verification_date = NOW()";

    // Insert verifications for group members
    $sInsertQuery = "INSERT INTO token_attestation_verifications " .
      "(wordform_id, document_id, start_pos, end_pos, verified_by, " .
      "verification_date) " .
      "SELECT wordform_id, wordform_groups.document_id,wordform_groups.onset,".
      "       wordform_groups.offset, $iUserId, NOW()" .
      "  FROM wordform_groups, " . $GLOBALS['sTokenDbName'] . ".tokens " .
      " WHERE tokens.document_id = wordform_groups.document_id" .
      "   AND tokens.lexicon_database_id = " . $GLOBALS['iTokenDbDatabaseId'] .
      "   AND tokens.onset = wordform_groups.onset" .
      "   AND wordform_group_id IN" .
      "     (SELECT wordform_group_id" .
      "        FROM wordform_groups" .
      "       WHERE $sInsertGroupCondition) " .
      " ON DUPLICATE KEY UPDATE verified_by = $iUserId," .
      " verification_date = NOW()";
    doNonSelectQuery($sInsertQuery);

    // If there are indeed token attestations that the user appearantly
    // approves of, we should also verify the analyzed wordforms associated
    //
    // NOTE that this one goes first (before the query above...).
    verifyAnalyzedWfForTokenAtt($iUserId, $sVerifyCondition);
  }
  else {
    $sDeleteCondition = $sGroupDeleteCondition = $sDeleteSeparator = '';
    foreach($aSelecteds as $sSelected) {
      // Selected is tuple: docId,startPos,endPos
      $aSelected = explode(",", $sSelected);

      // NOTE that is remarkably similar to the verify condition above
      $sDeleteCondition .= "$sDeleteSeparator(document_id = $aSelected[0]" .
	" AND start_pos = $aSelected[1] AND end_pos = $aSelected[2])";
      $sGroupDeleteCondition .=
	"$sDeleteSeparator(document_id = $aSelected[0]" .
	" AND onset = $aSelected[1] AND offset = $aSelected[2])";
      $sDeleteSeparator = " OR ";
    }
    $sQuery = "DELETE FROM token_attestation_verifications " .
      "WHERE $sDeleteCondition";
    
    // And another one for the groups
    $sDeleteQuery = "DELETE token_attestation_verifications " .
      "  FROM token_attestation_verifications, wordform_groups" .
      " WHERE token_attestation_verifications.document_id" .
      "        = wordform_groups.document_id" .
      "  AND token_attestation_verifications.start_pos= wordform_groups.onset".
      "  AND wordform_group_id IN" .
      "     (SELECT wordform_group_id" .
      "        FROM wordform_groups" .
      "       WHERE $sGroupDeleteCondition)";
    doNonSelectQuery($sDeleteQuery);
  }
  doNonSelectQuery($sQuery);
}

// The verify condition should pick out the analyzed word form ids from the
// token attestations table
//
function verifyAnalyzedWfForTokenAtt($iUserId, $sVerifyCondition) {
  $sValues = '';
  $cSeparator = '';

  // For some reason it is a LOT faster if we do this in two queries, rather
  // than with one involving a subquery.

  // Get all the analyzed word forms
  $sSelectQuery = "SELECT analyzed_wordform_id FROM token_attestations " .
    "  WHERE $sVerifyCondition";
  if( ($oResult = doSelectQuery($sSelectQuery) ) ) {
    while( ($aRow = mysql_fetch_assoc($oResult)) ) {
      $sValues .= $cSeparator . $aRow['analyzed_wordform_id'];
      $cSeparator = ', ';
    }
    mysql_free_result($oResult);
  }
  if( strlen($sValues) ) {
    $sUpdateQuery = "UPDATE analyzed_wordforms SET verified_by = $iUserId, " .
      "verification_date = NOW() WHERE analyzed_wordform_id IN ($sValues)";
    doNonSelectQuery($sUpdateQuery);
  }
}

function isTokenAttestation($iDocumentId, $iWordFormId) {
  $sSelectQuery = "SELECT token_attestations.attestation_id " .
    "FROM token_attestations, analyzed_wordforms" .
    " WHERE token_attestations.analyzed_wordform_id = " .
    "         analyzed_wordforms.analyzed_wordform_id " .
    "   AND analyzed_wordforms.wordform_id = $iWordFormId" .
    "   AND document_id = $iDocumentId" .
    " LIMIT 1";
  doSelectQuery($sSelectQuery);
 
  $iResult = 0;
  if( ($oResult = doSelectQuery($sSelectQuery) ) ) {
    if( ($aRow = mysql_fetch_assoc($oResult)) ) {
      $iResult = 1;
    }
    mysql_free_result($oResult);
  }
  return $iResult;
}

function deleteAnalysis($iWordFormId, $iAnalyzedWordFormId) {
  $sCondition;

  // First find out if the analysis we are deleting happens to be a
  // multiple lemmata analysis or not...
  $sSelectQuery = "SELECT lemma_id, multiple_lemmata_analysis_id " .
    "FROM analyzed_wordforms WHERE analyzed_wordform_id= $iAnalyzedWordFormId";
  if( ($oResult = doSelectQuery($sSelectQuery)) ) {
    if( $aRow = mysql_fetch_assoc($oResult) ) {  # There is only one row
      if( $aRow['lemma_id'] == 0) // It is a multiple lemmata analysis
	$sCondition = " AND analyzed_wordforms.multiple_lemmata_analysis_id =".
	  $aRow['multiple_lemmata_analysis_id'];
      else
	$sCondition = " AND analyzed_wordforms.lemma_id =". $aRow['lemma_id'];
    }
    mysql_free_result($oResult);
  }
  
  // There might be token attestations with this analyzed_wordform_id that
  // are part of a group. E.g. "Noord Holland", Noord-Holland, NE_LOC.
  // Now, if we delete Noord-Amerika, NE_LOC for 'Noord' we should also
  // do it for any other group members (i.e. 'Holland').
  $sSelectQuery = 
    // Outer query gives all analyzed wordform id's of
    // word forms in the same group as the selected word, where these analyzed
    // word forms refer to the same lemma.
    "SELECT token_attestations.attestation_id," .
    "       token_attestations.analyzed_wordform_id" .
    "  FROM wordform_groups, token_attestations, analyzed_wordforms " .
    " WHERE wordform_groups.wordform_group_id" .
    // Sub query gives the group ids of all the groups this analyzed wordform
    // belongs to
    "                 IN (SELECT wordform_group_id" .
    "                       FROM wordform_groups, token_attestations" .
    "                      WHERE token_attestations.analyzed_wordform_id = " .
    "                                                   $iAnalyzedWordFormId" .
    "                        AND wordform_groups.document_id =" .
    "                                         token_attestations.document_id" .
    "                        AND wordform_groups.onset =" .
    "                                           token_attestations.start_pos" .
    "                    )" .
    "  AND token_attestations.document_id = wordform_groups.document_id" .
    "  AND token_attestations.start_pos = wordform_groups.onset" .
    "  AND token_attestations.analyzed_wordform_id != $iAnalyzedWordFormId" .
    "  AND analyzed_wordforms.analyzed_wordform_id =" .
    "                                token_attestations.analyzed_wordform_id" .
    // Last condition, depending on multiple/single lemma analysis.
    $sCondition;


  if( ($oResult = doSelectQuery($sSelectQuery)) ) {
    $sAttestationIds = $sComma = '';
    $hAnalyzedWfs = array();
    while( $aRow = mysql_fetch_assoc($oResult) ) {
      $sAttestationIds .= $sComma . $aRow['attestation_id'];
      // These can be the same, when you have e.g. the same two  words forming a
      // group all the time
      $hAnalyzedWfs[$aRow['analyzed_wordform_id']] = 1;
      $sComma = ', ';
    }
    mysql_free_result($oResult);

    if(strlen($sAttestationIds) ) {
      // Delete all these token attestations
      $sDeleteQuery = "DELETE FROM token_attestations " .
	"WHERE attestation_id IN ($sAttestationIds)";
      doNonSelectQuery($sDeleteQuery);

      // Now, for every analyzed_wordform_id of these other words (word group
      // members), see if there are token attestations left for these analysis
      // we are deleting here. If not, they may be deleted as well...
      // So if 'Holland' happens to have another occurence (without 'Noord')
      // where it also is analysed as 'Noord-Amerika' we shouldn't delete that
      // one.
      $sSelectQuery =
	"SELECT analyzed_wordform_id FROM token_attestations" .
	" WHERE analyzed_wordform_id IN (" .
	implode(', ', array_keys($hAnalyzedWfs)) . ")" .
	" GROUP BY analyzed_wordform_id";

      if( ($oResult = doSelectQuery($sSelectQuery)) ) {
	# We shouldn't delete any awf that still occurs in a token attestation
	while( $aRow = mysql_fetch_assoc($oResult) )
	  $hAnalyzedWfs[$aRow['analyzed_wordform_id']] = 0;
	mysql_free_result($oResult);
	
	$sAwfsToDelete = $sComma = '';
	foreach( $hAnalyzedWfs as $iAwfId => $bDelete) {
	  if( $bDelete) {
	    $sAwfsToDelete .= $sComma . $iAwfId;
	    $sComma = ', ';
	  }
	}

	if( strlen($sAwfsToDelete) ) {
	  $sDeleteQuery = "DELETE FROM analyzed_wordforms" .
	    " WHERE analyzed_wordform_id IN ($sAwfsToDelete)";
	  doNonSelectQuery($sDeleteQuery);
	}
      }
    }
  }
  
  // token_attestation_verifications
  // These are intentionally left out because their status should not change.

  // Finally , just delete any token attestations for the word with this
  // analysis
  $sDeleteQuery = "DELETE FROM token_attestations " .
    "WHERE analyzed_wordform_id = $iAnalyzedWordFormId";
  doNonSelectQuery($sDeleteQuery);

  // Delete the analyzed_wordform itself
  $sDeleteQuery = "DELETE FROM analyzed_wordforms " .
    "WHERE analyzed_wordform_id = $iAnalyzedWordFormId";
  doNonSelectQuery($sDeleteQuery);
}

// (De)verify an analysis
//
// This function is called when a user c-clicks an analysis in the right
// column of the middle part of the screen
function verifyAnalysis($iUserId, $iAnalyzedWordFormId, $bVerify) {
  $sUpdateQuery  = "UPDATE analyzed_wordforms ";
  if( $bVerify)
    $sUpdateQuery .= "SET verified_by = $iUserId, verification_date = NOW() ";
  else // Deverify
    $sUpdateQuery .= "SET verified_by = NULL, verification_date = NULL ";
  $sUpdateQuery .= "WHERE analyzed_wordform_id = $iAnalyzedWordFormId";

  doNonSelectQuery($sUpdateQuery);
}

function dontShow($iUserId, $iDontShowId, $sDontShowMode, $bShow, $iRowNr,
		  $iWordFormId) {
  if($sDontShowMode == 'at_all') {
    // If we never have to show this word form again, we might as well delete
    // any other dont-show entries for it, since they won't be needed anymore.
    $sDeleteQuery = "DELETE FROM dont_show WHERE wordform_id = $iWordFormId";
    doNonSelectQuery($sDeleteQuery);
    // Set the right column name
    if( ! $bShow )
      insertDontShowRow($iWordFormId, 'at_all', $iDontShowId, $iUserId);
    // NOTE that in the case where we have to unhide (bShow == TRUE) the
    // previous DELETE query has already done the job.
  }
  else {
    $sColName = ($sDontShowMode == 'corpus') ? 'corpus_id' : 'document_id';
    if( $bShow ) // We have to unhide it
      deleteDontShowRow($iWordFormId, $sColName, $iDontShowId);
    else
      insertDontShowRow($iWordFormId, $sColName, $iDontShowId, $iUserId);
  }
}

function deleteDontShowRow($iWordFormId, $sColName, $iDontShowId) {
  $sDeleteQuery =
    "DELETE FROM dont_show " .
    "WHERE wordform_id = $iWordFormId AND $sColName = $iDontShowId";
  doNonSelectQuery($sDeleteQuery);
}

function insertDontShowRow($iWordFormId, $sColName, $iDontShowId, $iUserId) {
  $sInsertQuery =
    "INSERT INTO dont_show (wordform_id, $sColName, user_id, date) " .
    "VALUES ($iWordFormId, $iDontShowId, $iUserId, NOW())";

  doNonSelectQuery($sInsertQuery);
}

// We take both lemmata that exist as a single lemma, but also the ones that
// appear in multiple lemmata anlyses.
// NOTE that there is no need to de-double, because the UNION does so for us.
// Also NOTE that, in single lemma analysis case, when a lemma occurs in
// an analyzed wordorm record, and has a modern wordform/and or pattern,
// that form will be taken. If it doesn't occur in an analyzed wordform, its
// bare form will be taken (just modern_lemma, pos, language, gloss).
function getLemmaSuggestionsQuery($sHeadword, $sModernWordForm, $sPatterns,
				  $sPos, $sLanguage, $sGloss) {
  return getSingleLemmaSuggestionsQuery($sHeadword, $sModernWordForm,
					$sPatterns, $sPos, $sLanguage, $sGloss).
    " UNION " .
    getMultipleLemmaSuggestionsQuery($sHeadword, $sModernWordForm,
    				     $sPatterns, $sPos, $sLanguage, $sGloss);
}

// This one is the same as the one below, but it checks the lemmata featured
// in the multiple lemmata analyses
// 
function getMultipleLemmaSuggestionsQuery($sHeadword, $sModernWordForm,
					  $sPatterns, $sPos, $sLanguage,
					  $sGloss) {
  $sSelectQuery = "SELECT " .
    //  "SELECT DISTINCT " . // No distinct as we have a UNION
    "REPLACE(REPLACE(modern_lemma, '>', '&gt;'), '<', '&lt;') modern_lemma, " .
    // NOTE that the normalizedForm will automatically be NULL if there is none
    "myPatterns.normalized_wordform, myPatterns.patterns, " .
    "lemma_part_of_speech, language, gloss " .
    "FROM lemmata " .
    "LEFT JOIN languages ON (languages.language_id = lemmata.language_id) " .
    "LEFT JOIN multiple_lemmata_analysis_parts mlapartsOuter" .
    " ON (mlapartsOuter.lemma_id = lemmata.lemma_id) " .
    // Sub query for the derivational stuff
    "LEFT JOIN " .
    " (SELECT mlapartsInner.multiple_lemmata_analysis_part_id, " .
    " CONCAT('&lt;', normalized_form, '&gt;') AS normalized_wordform," .
    " CONCAT('[', GROUP_CONCAT(CONCAT('(', left_hand_side, '_'," .
    "    right_hand_side, ',&nbsp;',pattern_applications.position,')')), ']')".
    "    AS patterns" .
    " FROM multiple_lemmata_analysis_parts mlapartsInner," .
    "     lemmata, derivations" .
    // Two LEFT JOINs here because derivations can occur without patterns
    " LEFT JOIN pattern_applications ON ".
    "(pattern_applications.pattern_application_id" .
    " = derivations.pattern_application_id)" .
    " LEFT JOIN patterns ON" .
    "    (pattern_applications.pattern_id = patterns.pattern_id) " .
    "WHERE mlapartsInner.lemma_id = lemmata.lemma_id" .
    "  AND modern_lemma ";
  // Narrow it down somewhat for only the relavant lemma
  if( (! $sPos) && (! $sModernWordForm) && (! $sPatterns) )
    $sSelectQuery .= "LIKE '" . addslashes($sHeadword) . "%'";
  else // If there is POS, the head word has been matched already
    $sSelectQuery .= "= '" . addslashes($sHeadword) . "'";
  $sSelectQuery .=
    "  AND mlapartsInner.derivation_id = derivations.derivation_id " .
    "GROUP BY mlapartsInner.multiple_lemmata_analysis_part_id) myPatterns" .
    " ON (myPatterns.multiple_lemmata_analysis_part_id" .
    " = mlapartsOuter.multiple_lemmata_analysis_part_id) " .
    // End of the sub query for the derivational stuff
    "WHERE modern_lemma ";
  
  // Headword
  // If there is no POS, the head word should still be looked for
  if( (! $sPos) && (! $sModernWordForm) && (! $sPatterns) )
    $sSelectQuery .= "LIKE '" . addslashes($sHeadword) . "%'";
  else // If there is POS, the head word has been matched already
    $sSelectQuery .= "= '" . addslashes($sHeadword) . "'";
  
  // Normalized word form
  if($sModernWordForm) {
    $sSelectQuery .= " AND normalized_wordform ";
    if( (! $sPatterns) && (! $sPos) )
      $sSelectQuery .= "LIKE '&lt;" . addslashes($sModernWordForm) . "%'";
    if( $sPatterns || $sPos)
      $sSelectQuery .= "= '&lt;" . addslashes($sModernWordForm) . "&gt;'";
  }
  else
    if( $sPatterns || $sPos)
      $sSelectQuery .= " AND normalized_wordform IS NULL";
  
  // Patterns
  if( $sPatterns ) {
    $sSelectQuery .= " AND patterns ";
    if( ! $sPos)
      $sSelectQuery .= "LIKE '$sPatterns%'";
    else // NOTE the ')]' which is there intentionally...
      $sSelectQuery .= "= '$sPatterns)]'";
  }
  else
    if( $sPos )
      $sSelectQuery .= " AND patterns IS NULL";
  
  // Pos
  if( $sPos) {
    $sSelectQuery .= " AND lemma_part_of_speech ";
    if( (! $sGloss) && (! $sLanguage) )
      $sSelectQuery .= "LIKE '$sPos%'";
    else
      $sSelectQuery .= "= '$sPos'";
  }
  
  // Language
  if( $sLanguage )
    $sSelectQuery .= " AND language = '$sLanguage'";
  
  // Gloss
  if( $sGloss )
    $sSelectQuery .= " AND gloss LIKE '" . addslashes($sGloss) . "%'";
  
  $sSelectQuery .= " LIMIT " . $GLOBALS['iNrOfLemmaSuggestions'];

  return $sSelectQuery;
}

function getSingleLemmaSuggestionsQuery($sHeadword, $sModernWordForm,
					$sPatterns, $sPos, $sLanguage,
					$sGloss) {
  $sSelectQuery = "SELECT " .
    // "SELECT DISTINCT " . No distinct as we have a UNION
    "REPLACE(REPLACE(modern_lemma, '>', '&gt;'), '<', '&lt;') modern_lemma, " .
    // NOTE that the normalizedForm will automatically be NULL if there is none
    "myPatterns.normalized_wordform, myPatterns.patterns, " .
    "lemma_part_of_speech, language, gloss " .
    "FROM lemmata " .
    "LEFT JOIN languages ON (languages.language_id = lemmata.language_id) " .
    "LEFT JOIN analyzed_wordforms" .
    " ON (analyzed_wordforms.lemma_id = lemmata.lemma_id) " .
    // Sub query for the derivational stuff
    "LEFT JOIN " .
    " (SELECT a2.analyzed_wordform_id, " .
    " CONCAT('&lt;', normalized_form, '&gt;') AS normalized_wordform," .
    " CONCAT('[', GROUP_CONCAT(CONCAT('(', left_hand_side, '_'," .
    "    right_hand_side, ',&nbsp;',pattern_applications.position,')')), ']')".
    "    AS patterns" .
    " FROM analyzed_wordforms a2, lemmata, derivations" .
    // Two LEFT JOINs here because derivations can occur without patterns
    " LEFT JOIN pattern_applications ON ".
    "(pattern_applications.pattern_application_id" .
    " = derivations.pattern_application_id)" .
    " LEFT JOIN patterns ON" .
    "    (pattern_applications.pattern_id = patterns.pattern_id) " .
    "WHERE a2.lemma_id = lemmata.lemma_id" .
    "  AND modern_lemma ";
  // Narrow it down somewhat for only the relavant lemma
  if( (! $sPos) && (! $sModernWordForm) && (! $sPatterns) )
    $sSelectQuery .= "LIKE '" . addslashes($sHeadword) . "%'";
  else // If there is POS, the head word has been matched already
    $sSelectQuery .= "= '" . addslashes($sHeadword) . "'";
  $sSelectQuery .=
    "  AND a2.derivation_id = derivations.derivation_id " .
    "GROUP BY a2.analyzed_wordform_id) myPatterns" .
    " ON (myPatterns.analyzed_wordform_id" .
    " = analyzed_wordforms.analyzed_wordform_id) " .
    // End of the sub query for the derivational stuff
    "WHERE modern_lemma ";
  
  // Headword
  // If there is no POS, the head word should still be looked for
  if( (! $sPos) && (! $sModernWordForm) && (! $sPatterns) )
    $sSelectQuery .= "LIKE '" . addslashes($sHeadword) . "%'";
  else // If there is POS, the head word has been matched already
    $sSelectQuery .= "= '" . addslashes($sHeadword) . "'";
  
  // Normalized word form
  if($sModernWordForm) {
    $sSelectQuery .= " AND normalized_wordform ";
    if( (! $sPatterns) && (! $sPos) )
      $sSelectQuery .= "LIKE '&lt;" . addslashes($sModernWordForm) . "%'";
    if( $sPatterns || $sPos)
      $sSelectQuery .= "= '&lt;" . addslashes($sModernWordForm) . "&gt;'";
  }
  else
    if( $sPatterns || $sPos)
      $sSelectQuery .= " AND normalized_wordform IS NULL";
  
  // Patterns
  if( $sPatterns ) {
    $sSelectQuery .= " AND patterns ";
    if( ! $sPos)
      $sSelectQuery .= "LIKE '$sPatterns%'";
    else // NOTE the ')]' which is there intentionally...
      $sSelectQuery .= "= '$sPatterns)]'";
  }
  else
    if( $sPos )
      $sSelectQuery .= " AND patterns IS NULL";
  
  // Pos
  if( $sPos) {
    $sSelectQuery .= " AND lemma_part_of_speech ";
    if( (! $sGloss) && (! $sLanguage) )
      $sSelectQuery .= "LIKE '$sPos%'";
    else
      $sSelectQuery .= "= '$sPos'";
  }
  
  // Language
  if( $sLanguage )
    $sSelectQuery .= " AND language = '$sLanguage'";
  
  // Gloss
  if( $sGloss )
    $sSelectQuery .= " AND gloss LIKE '" . addslashes($sGloss) . "%'";
  
  $sSelectQuery .= " LIMIT " . $GLOBALS['iNrOfLemmaSuggestions'];

  return $sSelectQuery;
}

function printLemmaSuggestions($sMenuMode, $sFirstPartOfMultiple,
			       $sLemmaTuple) {
  $aLemmaArr = lemmaTupleString2array($sLemmaTuple, 'partial', 0);

  // If someone just types in spaces a lemma arr will come back, but searching
  // for lemmata meeting the criteria makes very little sense
  if( strlen($aLemmaArr[0]) ) {
    // First, get the right query
    // The (partial) pattern comes as an array, so it needs some processing.
    $sPatterns = false;
    if( $aLemmaArr[2] ) {
      $sPatterns = '[';
      foreach( $aLemmaArr[2] as $aPattern ) {
	if( strlen($sPatterns) > 1 )
	  $sPatterns .= "),&nbsp;";
	$sPatterns .= "($aPattern[0]";
	if( count($aPattern) > 1 )
	  $sPatterns .= "_$aPattern[1]";
	if( count($aPattern) > 2 )
	  $sPatterns .= ",&nbsp;$aPattern[2]";
      }
      // We don't close the ')]' because that depends on whether there is a POS
      // behind it or not (we deal with that when making the query)
    }
    
    $sSelectQuery = getLemmaSuggestionsQuery($aLemmaArr[0], $aLemmaArr[1],
					     $sPatterns, $aLemmaArr[3],
					     $aLemmaArr[4], $aLemmaArr[5]);
    
    if( ($oResult = doSelectQuery($sSelectQuery) ) ) {
      $iRowNr = 1;
      while( ($aRow = mysql_fetch_assoc($oResult)) ) {
	list($sLemmaSuggestion, $sPrintLemmaSuggestion) =
	  row2lemmaString($aRow);
	$sTitle =
	  str_replace('\\\"', '&#34',
		       str_replace("\\\'", "&#39;",
				   "$sFirstPartOfMultiple$sLemmaSuggestion"));
	print "<div class=lemmaSuggestion id=lemmaSuggestionRow_$iRowNr " .
	  " title='$sTitle' onClick=\"javascript: ";
	if( $sMenuMode == 'tokenAttSuggestion')
	  print " updateTokenAttInput(" .
	    "'$sTitle');\" ";
	else
	  if($sMenuMode == 'lemmaFilter')
	    print " applyLemmaFilter('$sTitle');\" ";
	  else // Mode is 'posInput'
	    print " updatePosInput('$sTitle');\" ";
	# Just before printing... replace any <>'s modern wordforms by
	# HTML entities
	$sFirstPartOfMultiple =
	  str_replace("<", "&lt;",
		      str_replace(">", "&gt;", $sFirstPartOfMultiple));
	print
	  "onMouseOver=\"javascript: highlightLemmaRow('', $iRowNr);\">" .
	  "$sFirstPartOfMultiple$sPrintLemmaSuggestion</div>";
	$iRowNr++;
      }
      mysql_free_result($oResult);
    }
  }
}

function row2lemmaString($aRow) {
  // Headword
  $sLemmaSuggestion = addslashes($aRow['modern_lemma']) . ", ";
  // The same, but without the slashes
  $sPrintLemmaSuggestion = $aRow['modern_lemma'] . ", ";
  // Modern/normalized word form
  if( $aRow['normalized_wordform'] ) {
    $sLemmaSuggestion .= addslashes($aRow['normalized_wordform']) . ", ";
    $sPrintLemmaSuggestion .= $aRow['normalized_wordform'] . ", ";
  }
  // Patterns
  if( $aRow['patterns'] ) {
    $sLemmaSuggestion .= addslashes($aRow['patterns']) . ", ";
    $sPrintLemmaSuggestion .= $aRow['patterns'] . ", ";
  }
  // Part of speech
  $sLemmaSuggestion .=  $aRow['lemma_part_of_speech'];
  $sPrintLemmaSuggestion .= $aRow['lemma_part_of_speech'];
  // Language
  if( $aRow['language'] ) {
    $sLemmaSuggestion .= ", " . $aRow['language'];
    $sPrintLemmaSuggestion .= ", " . $aRow['language'];
  }
  // Gloss
  if( $aRow['gloss'] ) {
    $sLemmaSuggestion .= ", " . addslashes($aRow['gloss']);
    $sPrintLemmaSuggestion .= ", " . $aRow['gloss'];
  }

  return array($sLemmaSuggestion, $sPrintLemmaSuggestion);
}

// This function is the one that fills the dropdown menu when you click on
// a word in the lower part of the screen.
function printTokenAttSuggestions($iDocumentId, $iSentenceNr, $iWordFormId,
				  $iStartPos, $iEndPos) {
  // NOTE that analysesInDb and multipleLemmataAnalysesInDb are mutually 
  // exclusive we merge the column into one analysisInDb
  $sSelectQuery = "SELECT analyzed_wordforms.analyzed_wordform_id," .
    "IF(analysesInDb.analysesInDb IS NOT NULL," .
    " analysesInDb, multipleLemmataAnalysesInDb) analysisInDb, " .
    "token_attestations.document_id " .
    "FROM analyzed_wordforms " .

    // LEFT JOIN for the normal (single) analyses
    "LEFT JOIN (SELECT DISTINCT a.analyzed_wordform_id, " .
    "CONCAT(REPLACE(REPLACE(lemmata.modern_lemma, '>', '&gt;'), '<', '&lt;'), " .
    "IF(myPatterns.normalized_wordform IS NULL, '', " .
    "CONCAT(',&nbsp;',myPatterns.normalized_wordform)), " .
    "IF(myPatterns.patterns IS NULL, " .
    "'', CONCAT(',&nbsp;', myPatterns.patterns)), " .
    "',&nbsp;', lemma_part_of_speech, " .
    // NOTE that the lemmata.language_id IS NULL is only there for backwards
    // compatibility
    "IF(lemmata.language_id IS NULL OR lemmata.language_id = 0, " .
    "'', CONCAT(',&nbsp;',languages.language)), " .
    "IF(lemmata.gloss = '', '',CONCAT(',&nbsp;', gloss))) AS analysesInDb ".
    "FROM analyzed_wordforms a " .
    "LEFT JOIN (SELECT analyzed_wordforms.analyzed_wordform_id, " .
    "CONCAT('&lt;', normalized_form, '&gt;') AS normalized_wordform, " .
    "CONCAT('[', GROUP_CONCAT(CONCAT('(', left_hand_side, '_', " .
    "right_hand_side, ',&nbsp;', pattern_applications.position, ')')), ']') " .
    "AS patterns " .
    "FROM analyzed_wordforms, derivations " .
    "LEFT JOIN pattern_applications ON " .
    "(pattern_applications.pattern_application_id" .
    "  = derivations.pattern_application_id) " .
    "LEFT JOIN patterns ON " .
    "(pattern_applications.pattern_id = patterns.pattern_id) " .
    "WHERE analyzed_wordforms.derivation_id = derivations.derivation_id " .
    "GROUP BY analyzed_wordforms.analyzed_wordform_id) " .
    "myPatterns ON (myPatterns.analyzed_wordform_id=a.analyzed_wordform_id), ".
    "lemmata " .
    "LEFT JOIN languages ON (languages.language_id = lemmata.language_id) " .
    "WHERE a.wordform_id = $iWordFormId " .
    "AND a.lemma_id = lemmata.lemma_id) analysesInDb " .
    "ON (analysesInDb.analyzed_wordform_id" .
    "  = analyzed_wordforms.analyzed_wordform_id) " .

    // LEFT JOIN for multiple analyses
    "LEFT JOIN (SELECT analyzed_wordforms.analyzed_wordform_id, " .
    "mla.mla multipleLemmataAnalysesInDb " .
    "FROM analyzed_wordforms " .
    "LEFT JOIN (SELECT analyzed_wordforms.analyzed_wordform_id," .
    /// 2011-09-05: geen DISTINCT
    ///    "        GROUP_CONCAT(DISTINCT CONCAT(REPLACE(REPLACE(lemmata.modern_lemma, '>', '&gt;'), '<', '&lt;'), " .
    "        GROUP_CONCAT(CONCAT(REPLACE(REPLACE(lemmata.modern_lemma, '>', '&gt;'), '<', '&lt;'), " .

    "    IF(myPatterns.normalized_wordform IS NULL, ''," .
    "       CONCAT(',&nbsp;', myPatterns.normalized_wordform))," .
    "    IF(myPatterns.patterns IS NULL, ''," .
    "       CONCAT(',&nbsp;', myPatterns.patterns))," .

    "            ',&nbsp;', lemmata.lemma_part_of_speech," .
    "            IF(lemmata.gloss = '','',CONCAT(',&nbsp;',lemmata.gloss)),".
    "            IF(languages.language IS NULL," .
    "                              '', CONCAT(',&nbsp;', languages.language)))".
    ///
    "            ORDER BY multiple_lemmata_analyses.part_number ASC" .
    ///
    "            SEPARATOR '&nbsp;&&nbsp;') mla" .
    "      FROM analyzed_wordforms, multiple_lemmata_analyses," .
    "           multiple_lemmata_analysis_parts mlapartsOuter" .

    " LEFT JOIN (SELECT multiple_lemmata_analyses.part_number," .
    "                   mlapartsInner.multiple_lemmata_analysis_part_id," .
    "  CONCAT('&lt;', normalized_form, '&gt;') AS normalized_wordform," .
    "  CONCAT('[', GROUP_CONCAT(CONCAT('(', left_hand_side, '_'," .
    "   right_hand_side, ',&nbsp;', pattern_applications.position,')')), ']')".
    "    AS patterns" .
    " FROM analyzed_wordforms, multiple_lemmata_analyses, " .
    "      multiple_lemmata_analysis_parts mlapartsInner, derivations" .
    // Two LEFT JOINs here because derivations can occur without patterns
    " LEFT JOIN pattern_applications ON".
    "(pattern_applications.pattern_application_id" .
    " = derivations.pattern_application_id)" .
    " LEFT JOIN patterns ON" .
    "    (pattern_applications.pattern_id = patterns.pattern_id) " .
    "WHERE mlapartsInner.derivation_id = derivations.derivation_id " .
    // These tables are only in to narrow it down somewhat
    "  AND analyzed_wordforms.wordform_id = $iWordFormId" .
    "  AND analyzed_wordforms.lemma_id = 0" .
    "  AND analyzed_wordforms.multiple_lemmata_analysis_id" .
    "      = multiple_lemmata_analyses.multiple_lemmata_analysis_id" .
    "  AND multiple_lemmata_analyses.multiple_lemmata_analysis_part_id" .
    "      = mlapartsInner.multiple_lemmata_analysis_part_id " .
    "GROUP BY mlapartsInner.multiple_lemmata_analysis_part_id)".
    " myPatterns ON (myPatterns.multiple_lemmata_analysis_part_id" .
    "                = mlapartsOuter.multiple_lemmata_analysis_part_id)," .

    "      lemmata" .
    "      LEFT JOIN languages ON" .
    "                       (languages.language_id = lemmata.language_id)" .
    "      WHERE analyzed_wordforms.lemma_id = 0" .
    // Added 2011-07-14
    "        AND analyzed_wordforms.wordform_id = $iWordFormId" .
    //
    "        AND analyzed_wordforms.multiple_lemmata_analysis_id" .
    "            = multiple_lemmata_analyses.multiple_lemmata_analysis_id" .
    "      AND multiple_lemmata_analyses.multiple_lemmata_analysis_part_id" .
    "  = mlapartsOuter.multiple_lemmata_analysis_part_id" .
    "      AND mlapartsOuter.lemma_id = lemmata.lemma_id" .
    "     GROUP BY analyzed_wordforms.analyzed_wordform_id)" .
    " mla ON (mla.analyzed_wordform_id" .
    "          = analyzed_wordforms.analyzed_wordform_id)" .
    "    WHERE analyzed_wordforms.wordform_id = $iWordFormId" .
    "      AND analyzed_wordforms.lemma_id = 0) multipleLemmataAnalysesInDb " .
    "ON (multipleLemmataAnalysesInDb.analyzed_wordform_id" .
    "  = analyzed_wordforms.analyzed_wordform_id) " .
    "LEFT JOIN token_attestations " .
    "ON (token_attestations.analyzed_wordform_id" .
    "  = analyzed_wordforms.analyzed_wordform_id" .
    "  AND token_attestations.document_id = $iDocumentId" .
    "  AND token_attestations.start_pos = $iStartPos" .
    "  AND token_attestations.end_pos = $iEndPos) " .
    "WHERE analyzed_wordforms.wordform_id = $iWordFormId";

  $iRowNr = 1;
  if( ($oResult = doSelectQuery($sSelectQuery) ) ) {
    while( ($aRow = mysql_fetch_assoc($oResult)) ) {
      $sIsTokenAtt = ($aRow['document_id']) ? '_isAtt' : '';
      print "<div class=lemmaSuggestion$sIsTokenAtt " .
	" id=lemmaSuggestionRow_$iRowNr " .
	// Title = 'docId|sentenceNr|wordFormId|analyzedWfId|startPos|endPos'
	" title='$iDocumentId|$iSentenceNr|$iWordFormId|" .
	$aRow['analyzed_wordform_id'] .	"|$iStartPos|$iEndPos' " .
	// NOTE that we keep the menu open here if someone clicks in it
	"onClick=\"javascript: sOpenMenu='tokenAttSuggestions'; " .
	" this.className = tokenAttest($iDocumentId, $iSentenceNr, " .
	"$iWordFormId, " . $aRow['analyzed_wordform_id'] .
	", $iStartPos, $iEndPos, this.className);\" " .
	"onMouseOver=\"javascript: highlightLemmaRow('tok', $iRowNr);\" " .
	//"onMouseOut=\"javascript: this.className = " .
	//"this.className.substr(0, this.className.length-1);\"".
	">" .
	$aRow['analysisInDb'] . "</div>";
      $iRowNr++;
    }
  }
  // New...
  // NOTE that we (mis)use the title here to store the highest row number,
  // plus all the arguments needed for makeTokenAttSuggestionEditable
  print "<div id=newTokenAtt title=" . ($iRowNr-1) .
    "|$iDocumentId|$iSentenceNr|$iWordFormId|$iStartPos|$iEndPos " .
    "onMouseOver=\"javascript: highlightLemmaRow('tok', 'new'); \" " .
    "onClick=\"javascript: makeTokenAttSuggestionEditable(this, " .
    "$iDocumentId, $iSentenceNr, $iWordFormId, $iStartPos, $iEndPos);\">" .
    "New...</div>";
}

// This function is called when somebody clicked on a suggestion in the token
// attestation suggestion box
// If the token was attested already it will be de-attested.
function tokenAttest($iUserId, $iWordFormId, $iAnalyzedWordFormId,
		     $sSelecteds, $sClassName) {
  // The selecteds are like this:
  // docId1,startPos1,startPos2|docId2,startPos2,endPos2
  $aSelecteds = explode("|", $sSelecteds);

  // NOTE that in both cases we build the insert values string the token
  // attestation verifications query (TAV)
  $sGroupCondition1 = $sGroupCondition2 = '';
  $aAnalyzedWordFormsToVerify = array();
  $aVerificationValues = array();

  // If it was already attested, then de-attest it
  if( $sClassName == 'lemmaSuggestion_isAtt_' ) {
    $aVerificationValues =
      tokenUnattest($iUserId, $iWordFormId, $iAnalyzedWordFormId, $aSelecteds);
  }
  else { // It is to be token attested
    $aInsertValues = array();
    $sOr = $sAnd = '';

    foreach($aSelecteds as $sSelected) {
      // sSelected is: docId,startPos,endPos
      $aSelected = explode(",", $sSelected);
      $sVerifyCondition = $sVerifySeparator = '';

      // Maintain $aVerificationValues. Same as above
      array_push($aVerificationValues,
		 "($iWordFormId, $iUserId, NOW(), $sSelected)");
      $sVerifyCondition .= "$sVerifySeparator(document_id = $aSelected[0]" .
	" AND start_pos = $aSelected[1] AND end_pos = $aSelected[2])";
      $sVerifySeparator = " OR ";

      array_push($aInsertValues, "($iAnalyzedWordFormId, $sSelected)");
      $aAnalyzedWordFormsToVerify[$iAnalyzedWordFormId] = 1;
      $sGroupCondition1 .= $sOr .
	"(document_id = $aSelected[0] AND onset = $aSelected[1])";
      $sGroupCondition2 .= $sAnd .
	"(NOT (wordform_groups.document_id = $aSelected[0]" .
	" AND wordform_groups.onset = $aSelected[1]))";
      $sOr = ' OR ';
      $sAnd = ' AND ';
    }

    expandForGroups($iUserId, $iAnalyzedWordFormId, $sGroupCondition1,
		    $sGroupCondition2, $aInsertValues, $aVerificationValues,
		    $aAnalyzedWordFormsToVerify, true);

    // NOTE that ON DUPLICATE key bit, which doesn't really add anything but 
    // which makes it possible for people to add a lemma more than once to the
    // as a token level attestation, without the query giving errors
    $sInsertQuery = "INSERT INTO token_attestations " .
      "(analyzed_wordform_id, document_id, start_pos, end_pos) " .
      "VALUES " . implode(',', $aInsertValues) .
      "ON DUPLICATE KEY UPDATE document_id = document_id";
    doNonSelectQuery($sInsertQuery);
    
    // If someone approved of a certain analysis, this analysis should be
    // verified
    /// verifyAnalyzedWfForTokenAtt($iUserId, $sVerifyCondition);
    /// Nu beneden
    
    // Verify the analyses
    // NOTE that this is regardless of whether the token attestations are
    // being verified or not.
    $sUpdateQuery = "UPDATE analyzed_wordforms SET verified_by = $iUserId, " .
      "verification_date = NOW() WHERE analyzed_wordform_id IN (" .
      implode(", ", array_keys($aAnalyzedWordFormsToVerify)) . ")";
    doNonSelectQuery($sUpdateQuery);

    // If someone approved of a certain analysis, this analysis should be
    // verified
    verifyAnalyzedWfForTokenAtt($iUserId, $sVerifyCondition);
  }

  // Verify the token attestation. Also if something was deleted.
  $sInsertQuery = "INSERT INTO token_attestation_verifications " .
    "(wordform_id, verified_by, verification_date, document_id," .
    " start_pos, end_pos) " .
    "VALUES " . implode(', ', $aVerificationValues) .
    " ON DUPLICATE KEY UPDATE verified_by = $iUserId," .
    " verification_date = NOW()";
  doNonSelectQuery($sInsertQuery);
}

function tokenUnattest($iUserId, $iWordFormId, $iAnalyzedWordFormId,
		       $aSelecteds) {
  $sTAV = $sTAVSep = '';
  // First build the condition
  $sDeleteCondition = '';
  $sDeleteSeparator = '';
  $sGroupMemberSelectCondition = '';
  $aVerificationValues = array();
  foreach($aSelecteds as $sSelected) {
    // sSelected is: docId,startPos,endPos
    $sTAV .= "$sTAVSep($sSelected, $iWordFormId, $iUserId, NOW())";
    $sTAVSep = ',';
    $aSelected = explode(",", $sSelected);
    
    // Maintain $aVerificationValues. Same as above
    array_push($aVerificationValues,
	       "($iWordFormId, $iUserId, NOW(), $sSelected)");
    
    $sDeleteCondition .= $sDeleteSeparator .
      "(token_attestations.document_id = $aSelected[0]" .
      "  AND token_attestations.analyzed_wordform_id = " .
      "      $iAnalyzedWordFormId" .
      "  AND token_attestations.start_pos = $aSelected[1]" .
      "  AND token_attestations.end_pos = $aSelected[2])";
    
    $sGroupMemberSelectCondition .= $sDeleteSeparator .
      "(document_id = $aSelected[0]" . " AND onset = $aSelected[1])";
    
    $sDeleteSeparator = " OR ";
  }
  
  $sDeleteQuery = "DELETE token_attestations.* FROM token_attestations " .
    "WHERE $sDeleteCondition";
  doNonSelectQuery($sDeleteQuery);

  $sGroupMemberWfIds = getWfIdsForGroupMembers($sGroupMemberSelectCondition,
					       $iAnalyzedWordFormId);
  
  if( strlen($sGroupMemberWfIds) ) { // If there are group members
    $sGroupMemberDeleteCondition =
      getGroupMemberDeleteCondition($sGroupMemberSelectCondition);
    
    if( $sGroupMemberDeleteCondition ) {
      // Delete token attestations for any group members
      $sDeleteQuery = "DELETE token_attestations.*" .
	" FROM analyzed_wordforms aAll, token_attestations," .
	"     (SELECT lemma_id, derivation_id, multiple_lemmata_analysis_id" .
	"        FROM analyzed_wordforms" .
	"       WHERE analyzed_wordform_id = $iAnalyzedWordFormId) aOne" .
	" WHERE aAll.lemma_id = aOne.lemma_id" .
	"   AND aAll.derivation_id = aOne.derivation_id" .
	"   AND aAll.multiple_lemmata_analysis_id" .
	"        = aOne.multiple_lemmata_analysis_id" .
	"   AND token_attestations.analyzed_wordform_id" .
	"        = aAll.analyzed_wordform_id" .
	"   AND ($sGroupMemberDeleteCondition)";
      doNonSelectQuery($sDeleteQuery);
    }

    // This is special group behaviour.
    // Suppose we have the word group 'Noord Holland' and we are currently at
    // 'Noord' where we deleted some analyses, e.g. 'noord, ADJ'.
    // Now, this analysis will remain on an analyzed wordform level for 'Noord'
    // (or the user should have ctrl-clicked it in the upper part). However, it
    // might be a bit surprising if it also remains on that level for 'Holland',
    // as the user just deleted the token attestation, and can not control
    // the higher level analyses form his/her current screen.
    // So >>as special group behaviour<< we throw away the analyzed wordform
    // corresponding to the one being thrown away just now for every group
    // member that has no token attestation for it.
    deleteAwfsForGroupMembers($sGroupMemberWfIds, $iAnalyzedWordFormId);
  }

  return $aVerificationValues;
}

function deleteAwfsForGroupMembers($sGroupMemberWfIds, $iAnalyzedWordFormId) {
  $sSelectQuery = "SELECT awf.analyzed_wordform_id," .
    "       GROUP_CONCAT(token_attestations.attestation_id) isATokenAtt" .
    "  FROM analyzed_wordforms awfOne, analyzed_wordforms awf" .
    "       LEFT JOIN token_attestations ON " .
    "     (token_attestations.analyzed_wordform_id = awf.analyzed_wordform_id)".
    " WHERE awfOne.analyzed_wordform_id = $iAnalyzedWordFormId" .
    "   AND awf.lemma_id = awfOne.lemma_id" .
    "   AND awf.derivation_id = awfOne.derivation_id" .
    "   AND awf.multiple_lemmata_analysis_id = " .
    "            awfOne.multiple_lemmata_analysis_id" .
    "   AND awf.wordform_id IN ($sGroupMemberWfIds)" .
    " GROUP BY awf.wordform_id";

  $sAnalyzedWordFormIds = $sComma = '';
  if( ($oResult = doSelectQuery($sSelectQuery)) ) {
    while( ($aRow = mysql_fetch_assoc($oResult)) ) {
      if( strlen($aRow['isATokenAtt']) == 0 ) {
	$sAnalyzedWordFormIds .= $sComma . $aRow['analyzed_wordform_id'];
	$sComma = ", ";
      }
    }
    mysql_free_result($oResult);
  }

  if( strlen($sAnalyzedWordFormIds)) {
    $sDeleteQuery = "DELETE FROM analyzed_wordforms " .
      "WHERE analyzed_wordform_id IN ($sAnalyzedWordFormIds)";
    doNonSelectQuery($sDeleteQuery);
  }
}

// Note that we can potentially miss something when a certain word forms has no
// token attestations whatsoever.
function getWfIdsForGroupMembers($sGroupMemberSelectCondition,
				 $iAnalyzedWordFormId) {
  $sSelectQuery = "SELECT GROUP_CONCAT(DISTINCT awf.wordform_id)" .
    "    groupMemberWfIds" .
    "  FROM analyzed_wordforms awf, analyzed_wordforms awfOne," .
    "       token_attestations, wordform_groups, " .
    "       (SELECT wordform_group_id FROM wordform_groups " .
    "        WHERE $sGroupMemberSelectCondition) groupMembers" .
    " WHERE wordform_groups.wordform_group_id = groupMembers.wordform_group_id".
    "   AND wordform_groups.onset = token_attestations.start_pos" .
    "   AND wordform_groups.document_id = token_attestations.document_id" .
    "   AND token_attestations.analyzed_wordform_id = awf.analyzed_wordform_id".
    "   AND awfOne.analyzed_wordform_id = $iAnalyzedWordFormId" .
    "   AND awf.wordform_id != awfOne.wordform_id";

  $sGroupMemberWfIds = false;
  if( ($oResult = doSelectQuery($sSelectQuery)) ) {
    if( ($aRow = mysql_fetch_assoc($oResult)) )
      $sGroupMemberWfIds = $aRow['groupMemberWfIds'];
    mysql_free_result($oResult);
  }
  return $sGroupMemberWfIds;
}

function getGroupMemberDeleteCondition($sGroupMemberSelectCondition) {
  $sSelectQuery =
    "SELECT GROUP_CONCAT(" .
    "CONCAT('(token_attestations.document_id= ',wordform_groups.document_id,".
    " ' AND token_attestations.start_pos = ', wordform_groups.onset, ')')" .
    " SEPARATOR ' OR ') " .
    "groupMemberCondition " .
    "  FROM wordform_groups," .
    "       (SELECT wordform_group_id FROM wordform_groups " .
    "        WHERE $sGroupMemberSelectCondition) groupMembers" .
    " WHERE wordform_groups.wordform_group_id=groupMembers.wordform_group_id";

  $sGroupMemberDeleteCondition = false;
  if( ($oResult = doSelectQuery($sSelectQuery)) ) {
    if( ($aRow = mysql_fetch_assoc($oResult)) )
      $sGroupMemberDeleteCondition = $aRow['groupMemberCondition'];
    mysql_free_result($oResult);
  }
  return $sGroupMemberDeleteCondition;
}

// What this function prints is shown in the current token attestations box
// right of the sentence in the lower part of the screen
function printTokenAttestations($iDocumentId, $iSentenceNr, $iWordFormId,
				$iStartPos, $iEndPos) {
  $oResult = getTokenAttestations($iDocumentId, $iWordFormId, $iStartPos);
  $oLastRow = 0;
  if( $oResult ) {
    $aRows = false; // Otherwise you can't pass it by reference...
    print currentTokenAttestations($iSentenceNr, $oResult, $iStartPos,
				   $iEndPos, $oLastRow, false, false,
				   $aRows);
    mysql_free_result($oResult);
  }
}

// The $oResult object contains all token attestations ordered by onset
// The getRow() functions gives you rows for the particular onset
// This way we can query the database just once, and we only have to iterate
// through the results once.
function currentTokenAttestations($iSentenceNr, $oResult, $iOnset, $iOffset,
				  &$oLastRow, $bBuildIndex, $iDocumentId,
				  &$aRows) {
  $sReturn = '';
  // Every sentence has its document id and sentence number as an index, so
  // sentences of the same document are grouped together and will always
  // appear in the same order.
  if( $bBuildIndex)
    $aRows[$iSentenceNr]["index"] = "${iDocumentId}_$iSentenceNr";
  if( $oResult ) {
    while( ($aRow = getRow($oResult, $iOnset, $oLastRow)) ) {
      // Sometimes the analysesForSentence column is NULL..?!?
      //if( strlen($aRow['analysesForSentence']) == 0)
      //	continue;
      if( $bBuildIndex) {
	// If there is a quote at the start of the lemma, neglect it for the
	// index (so "'s Hertogenbosch" -> "s Hertogenbosch").
	// If we don't do that, they end up AFTER the words without analyses
	// as digits come before the quote, alphabetically (apparently...).
	$sAnalysesForIndex = (substr($aRow['analysesForSentence'], 0, 1) == "'")
	  ? substr($aRow['analysesForSentence'], 1)
	  : $aRow['analysesForSentence'];
	$aRows[$iSentenceNr]["index"] =
	  $sAnalysesForIndex . " " . $aRows[$iSentenceNr]["index"];
      }
      if( strlen($sReturn) )
	$sReturn .= "<br>\n";
      $sReturn .= clickableTokenAtt($iSentenceNr,
				    $aRow['analyzed_wordform_id'],
				    $aRow['analysesForSentence']);
    }
    return $sReturn;
  }
  return ''; // When there was no result
}

function clickableTokenAtt($iSentenceNr, $iAnalyzedWordFormId,
			   $sAnalysisForSentence) {
  return "<span class=clickableTokenAtt " .
    "onMouseOver=\"javascript: this.style.cursor = 'pointer';" .
    " this.className = 'clickableTokenAtt_';\" " .
    "onMouseOut=\"javascript: this.className = 'clickableTokenAtt';\" " .
    "onClick=\"javascript: removeTokenAttestation($iSentenceNr, " .
    "$iAnalyzedWordFormId);\">$sAnalysisForSentence</span>";
}

// This function is called when somebody typed in a new lemma in the token
// attestation menu
function addNewTokenAttestation($iUserId, $iWordFormId, $sSelecteds,
				$sLemmaTuple) {
  addTokenAttestations($iUserId, $iWordFormId, $sSelecteds, $sLemmaTuple,
		       true);
}

// Get document identifiers in a certain corpus, bit only when a certain word
// occurs
function getDocumentIdsInCorpus($iCorpusId, $iWordFormId) {
  $sSelectQuery = "SELECT document_id FROM type_frequencies " .
    "WHERE wordform_id = $iWordFormId";

  $aDocumentIds = array();
  if( ($oResult = doSelectQuery($sSelectQuery)) )
    while( ($aRow = mysql_fetch_assoc($oResult)) )
      $aDocumentIds[] = $aRow['document_id'];
  return $aDocumentIds;
}

// If we are in document mode we just get a title. Otherwise, we get all ids
// and titles for a corpus
//
function getDocumentIdsAndTitles($iId, $sMode, $iWordFormId) {
  $sSelectQuery = ($sMode == 'file') ?
    // File query
    "SELECT documents.document_id, title, image_location, frequency, " .
    "       tmp.verifiedTokenAtts, tmp2.docId AS containsTokenAtts" .
    "  FROM type_frequencies, documents " .
    // Sub query for the verified token attestations
    "LEFT JOIN ".
    "(SELECT token_attestation_verifications.document_id AS docId," .
    "        GROUP_CONCAT(CONCAT(start_pos, ',', end_pos, ',', verified_by) " .
    "                     SEPARATOR '|') AS verifiedTokenAtts" .
    "  FROM token_attestation_verifications" .
    " WHERE document_id = $iId AND wordform_id = $iWordFormId" .
    " GROUP BY document_id) tmp ON (documents.document_id = tmp.docId)" .
    // Separate LEFT JOIN to see if there are token attestations at all
    // (for effeciency later on).
    "LEFT JOIN (SELECT DISTINCT token_attestations.document_id AS docId" .
    "           FROM token_attestations, analyzed_wordforms" .
    "           WHERE token_attestations.document_id = $iId" .
    "             AND token_attestations.analyzed_wordform_id =" .
    "                   analyzed_wordforms.analyzed_wordform_id" .
    "             AND analyzed_wordforms.wordform_id = $iWordFormId) tmp2" .
    " ON (documents.document_id = tmp2.docId)" .
    // Rest of the query
    " WHERE type_frequencies.wordform_id = $iWordFormId" .
    "   AND type_frequencies.document_id = $iId" .
    "   AND documents.document_id = type_frequencies.document_id"
    : // Corpus query
    "SELECT documents.document_id, title, image_location, frequency, " .
    "       tmp.verifiedTokenAtts, tmp2.docId AS containsTokenAtts" .
    " FROM type_frequencies, corpusId_x_documentId, documents" .
    // Separate left join for the verified token attestations
    " LEFT JOIN (SELECT token_attestation_verifications.document_id AS docId,".
    "          GROUP_CONCAT(CONCAT(start_pos, ',', end_pos, ',', verified_by)".
    "                                     SEPARATOR '|') AS verifiedTokenAtts".
    "              FROM token_attestation_verifications, corpusId_x_documentId"
    . "            WHERE corpusId_x_documentId.corpus_id = $iId" .
    "                AND corpusId_x_documentId.document_id =" .
    "                      token_attestation_verifications.document_id" .
    "                AND wordform_id = $iWordFormId" .
    "           GROUP BY docId) tmp ON (documents.document_id = tmp.docId)" .
    // Separate LEFT JOIN to see if there are token attestations at all
    // (for effeciency later on).
    "LEFT JOIN (SELECT DISTINCT token_attestations.document_id AS docId" .
    "           FROM token_attestations, corpusId_x_documentId," .
    "                analyzed_wordforms" .
    "           WHERE corpusId_x_documentId.corpus_id = $iId" .
    "             AND corpusId_x_documentId.document_id =" .
    "                  token_attestations.document_id " .
    "             AND token_attestations.analyzed_wordform_id =" .
    "                   analyzed_wordforms.analyzed_wordform_id" .
    "             AND analyzed_wordforms.wordform_id = $iWordFormId) tmp2" .
    " ON (documents.document_id = tmp2.docId)" .
    // And the rest of the query
    " WHERE corpusId_x_documentId.corpus_id = $iId" .
    "   AND type_frequencies.wordform_id = $iWordFormId" .
    "   AND type_frequencies.document_id = corpusId_x_documentId.document_id" .
    "   AND documents.document_id = type_frequencies.document_id" .
    " ORDER BY title";

  // Return the result
  return doSelectQuery($sSelectQuery);
}

// Corpus/document functions ///////////////////////////////////////////////////

// Return ids and names of the corpora in the database
function getCorpora() {
  return doSelectQuery("SELECT corpus_id, name FROM corpora");
}

// Insert a new corpus in the database and return its id
function newCorpus($sNewCorpusName) {
  // Insert a new corpus
  $sInsertQuery = "INSERT INTO corpora (name) VALUES ('$sNewCorpusName') " .
    "ON DUPLICATE KEY UPDATE corpus_id = corpus_id";

  doNonSelectQuery($sInsertQuery);

  $iCorpusId = 0;
  if( ($oResult = doSelectQuery("SELECT LAST_INSERT_ID();") ) ) {
    if( ($aRow = mysql_fetch_assoc($oResult)) )
      $iCorpusId = $aRow['LAST_INSERT_ID()'];
    mysql_free_result($oResult);
  }
  print $iCorpusId;
}

// Remove a corpus, and all the files that have become obsolete because of it
function removeCorpus($iCorpusId) {
  // First delete from token database (has te be done while the records are
  // still in the documents table)
  deleteCorpusFromTokenDb($iCorpusId);

  // Delete dont_show entries
  $sDeleteQuery = "DELETE FROM dont_show WHERE corpus_id = $iCorpusId";
  doNonSelectQuery($sDeleteQuery);

  $sSelectQuery =
    "SELECT outerCxd.document_id, title " .
    "FROM corpusId_x_documentId outerCxd, documents" .
    // Sub query listing all the documents occuring just once
    "  WHERE NOT EXISTS (SELECT * FROM corpusId_x_documentId innerCxd" .
    "                     WHERE innerCxd.document_id = outerCxd.document_id" .
    "                   AND NOT innerCxd.corpus_id = outerCxd.corpus_id)" .
    // The outer query selects just the ones for this corpus
    "    AND corpus_id = $iCorpusId" .
    "    AND outerCxd.document_id = documents.document_id";
  $sDocumentIds = '';
  $sComma = '';
  $aTitles = array();
  if( ($oResult = doSelectQuery($sSelectQuery)) ) {
    while( ($aRow = mysql_fetch_assoc($oResult)) ) {
      $sDocumentIds .= $sComma . $aRow['document_id'];
      $sComma = ", ";
      array_push($aTitles, $aRow['title']);
    }
    mysql_free_result($oResult);
  }

  // No need to do all the queries when there is no result...
  if(strlen($sDocumentIds))
    deleteFilesFromDb($sDocumentIds);

  // Delete the corpus itself
  $sDeleteQuery = "DELETE FROM corpusId_x_documentId ".
    "WHERE corpus_id = $iCorpusId";
  doNonSelectQuery($sDeleteQuery);

  $sDeleteQuery = "DELETE FROM corpora WHERE corpus_id = $iCorpusId";
  doNonSelectQuery($sDeleteQuery);
}

function showCorpusFiles($sDatabase, $iUserId, $sUserName, $iCorpusId) {
  // Always give the opportunity to add a file to the corpus
  // The image and upload form are in a table because otherwise the form will
  // start on a new line
  print "<div class=corpusFile>" .
    "<table cellspacing=0 cellpadding=0><tr>" .
    "<td onClick=\"javascript: toggleNewFileForm($iCorpusId);\">" .
    "<img src='./img/fileNew.png'> <i>Add a file...</i>&nbsp;" .
    "</td><td>" .
    "<span id=newFileForm_$iCorpusId style='display: none;'>" .
    "<form style='margin: 0px;' enctype=\"multipart/form-data\"" .
    " action=\"./lexiconTool.php\" method=POST>" .
    // "<input type=hidden name=MAX_FILE_SIZE value=10000000>" .
    // Not needed, I think. It is handled in the php.ini file (08 mar 2011)
    "<input type=hidden name=sDatabase value='$sDatabase'>" .
    "<input type=hidden name=sUserName value='$sUserName'>" .
    "<input type=hidden name=iUserId value=$iUserId>" .
    "<input type=hidden name=iCorpusAddedTo value=$iCorpusId>" .
    "<input name=sNewUploadFile type=file>" . 
    "<input type=submit value='Upload file'" .
    " onClick=\"javascript: showProgress(false, 'Uploading');\">" .
    "</form></span>" .
    "</td></tr></table></div>";

  $sSelectQuery = "SELECT documents.document_id, title " .
    "FROM documents, corpusId_x_documentId " .
    "WHERE corpusId_x_documentId.corpus_id = $iCorpusId" .
    "  AND corpusId_x_documentId.document_id = documents.document_id " .
    " ORDER BY title";
  if( ($oResult = doSelectQuery($sSelectQuery)) ) {
    while( ($aRow = mysql_fetch_assoc($oResult)) ) {
      // NOTE that we don't display the document root, which is always the
      // same...
      $sDisplayName =
	substr($aRow['title'], strlen($GLOBALS['sDocumentRoot']) + 1);
      print "<div class=corpusFile id=corpusFile_" . $aRow['document_id'].'>'.
	// File icon
	"<img src='./img/file.png'> " .
	// Click to see just this file
	"<a href='./lexiconTool.php?sDatabase=$sDatabase&iUserId=$iUserId&" .
	"sUserName=$sUserName&iFileId=" .
	$aRow['document_id'] . "&sFileName=" . $aRow['title'] .
	"'>$sDisplayName</a>" .
	// Remove file from corpus
	"<span title='Remove file from corpus'" .
	" onmouseOver=\"javascript: this.style.cursor = 'pointer';\"" .
	" onClick='javascript: " .
	"var bYes = confirm(\"Do you really want to remove " .
	"\\\"$sDisplayName\\\" from this corpus?\"); if( bYes) " .
	"removeFileFromCorpus($iCorpusId, " . $aRow['document_id'] . ");'>" .
	"&nbsp;<img src='./img/remove.png'>" .
	"</span>" .
	// Close the div
	"</div>\n";
    }
    mysql_free_result($oResult);
  }
}

// We don't check whether the variables have legal values.
// The Javascript/HTML interface should have taken care of that.
function makeNewCorpus($sCorpusName, $aDocumentIds) {
  // First check if a corpus with this name already exists
  $sSelectQuery = "SELECT corpus_id FROM corpora WHERE name = '$sCorpusName'";
  if( ($oResult = doSelectQuery($sSelectQuery)) )
    if( ($aRow = mysql_fetch_assoc($oResult)) ) {
      mysql_free_result($oResult);
      return -1;
    }
  
  // If it is a new name, insert a new corpus
  $sInsertQuery = "INSERT INTO corpora (name) VALUES ('$sCorpusName')";

  doNonSelectQuery($sInsertQuery);

  // Get the identifier of the record just created
  $iCorpusId = 0;
  if( ($oResult = doSelectQuery("SELECT LAST_INSERT_ID();") ) ) {
    if( ($aRow = mysql_fetch_assoc($oResult)) )
      $iCorpusId = $aRow['LAST_INSERT_ID()'];
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
    return $iCorpusId;
  }
  else {
    print "Something went wrong in creating a new corpus\n";
    return 0;
  }
}

// This function returns -1 if the document is already in the database
// (for this corpus, or another, doesn't matter)
function addFileToCorpus($sDatabase, $iCorpusAddedTo, $sNewFile, $sAuthor) {
  chooseDb($sDatabase);
  // Check if the file is in the database already
  $sSelectQuery = "SELECT corpus_id, corpusId_x_documentId.document_id" .
    " FROM documents, corpusId_x_documentId " .
    "WHERE documents.title = '$sNewFile'" .
    "  AND documents.document_id = corpusId_x_documentId.document_id";
  $iDocumentId = -1;
  $bInDbAlreadyForThisCorpus = false;
  if( ($oResult = doSelectQuery($sSelectQuery)) ) {
    while( ($aRow = mysql_fetch_assoc($oResult)) ) {
      $iDocumentId = $aRow['document_id'];
      if( $aRow['corpus_id'] == $iCorpusAddedTo) {
	$bInDbAlreadyForThisCorpus = true;
	break;
      }
    }
    mysql_free_result($oResult);
  }
  if( $bInDbAlreadyForThisCorpus )
    return -1;
  
  // It wasn't in the database yet for this corpus, also add it to the
  // token db
  insertDocumentInTokenDb($sNewFile, $iCorpusAddedTo);

  // Else, it was not in the database for this corpus
  if( $iDocumentId != -1 ) { // But if it was in the database...
    $sInsertQuery =
      "INSERT INTO corpusId_x_documentId (corpus_id, document_id)" .
      "VALUES($iCorpusAddedTo, $iDocumentId) " . 
      "ON DUPLICATE KEY UPDATE document_id = document_id";
    doNonSelectQuery($sInsertQuery);
    return -1;
  }
  
  // If it wasn't in yet, insert it
  //
  // We have an ON DUPLICATE KEY bit because if we are e.g. dealing with a
  // pre-tagged .fixed file, a document entry was already inserted during
  // tokenizing in order to be able to add token attestations at that stage.
  $sInsertQuery = "INSERT INTO documents (title, author) " .
    "VALUES ('$sNewFile', '" . addslashes($sAuthor) . "')" .
    "ON DUPLICATE KEY UPDATE author = '" . addslashes($sAuthor) . "'";
  doNonSelectQuery($sInsertQuery);

  // Get the id of the document just inserted
  $sSelectQuery = "SELECT document_id FROM documents " .
    "WHERE title = '$sNewFile'";
  if( ($oResult = doSelectQuery($sSelectQuery)) ) {
    if( ($aRow = mysql_fetch_assoc($oResult)) ) {
      $iDocumentId = $aRow['document_id'];
      mysql_free_result($oResult);
      $sInsertQuery = "INSERT INTO corpusId_x_documentId ".
	"(corpus_id, document_id) VALUES($iCorpusAddedTo, $iDocumentId) " .
	"ON DUPLICATE KEY UPDATE document_id = document_id";
      doNonSelectQuery($sInsertQuery);
      return $iDocumentId;
    }
  }

  // If we somehow end up here something went wrong
  return false;
}

function insertDocumentInTokenDb($sFilePath, $iCorpusId) {
  $iDocumentPathId = -1;

  // First, see if the file is already in the token db
  $sSelectQuery = "SELECT document_path_id" .
    " FROM " . $GLOBALS['sTokenDbName']. ".document_paths ".
    "WHERE path = '$sFilePath'";
  if( ($oResult = doSelectQuery($sSelectQuery)) ) {
    if( ($aRow = mysql_fetch_assoc($oResult)) )
      $iDocumentPathId = $aRow['document_path_id'];
    mysql_free_result($oResult);
  }

  // If it wasn't in yet, insert it
  if( $iDocumentPathId == -1) {
    $sInsertQuery = "INSERT INTO " .
      $GLOBALS['sTokenDbName'] . ".document_paths (path) VALUES " .
      "('$sFilePath')";
    doNonSelectQuery($sInsertQuery);
    // Try again
    if( ($oResult = doSelectQuery($sSelectQuery)) ) {
      if( ($aRow = mysql_fetch_assoc($oResult)) )
	$iDocumentPathId = $aRow['document_path_id'];
      mysql_free_result($oResult);
    }
  }

  // The ON DUPLICATE KEY UPDATE allows for uploading the same documents
  // again and again (which will be silently ignored).
  $sInsertQuery =
    "INSERT INTO " . $GLOBALS['sTokenDbName'] . ".document_indices " .
    "(document_path_id, lexicon_database_id, corpus_id) VALUES " .
    "($iDocumentPathId, " . $GLOBALS['iTokenDbDatabaseId'] .
    ", $iCorpusId) " .
    "ON DUPLICATE KEY UPDATE document_path_id = document_path_id";
  doNonSelectQuery($sInsertQuery);
}

function removeFileFromCorpus($iCorpusId, $iDocumentId) {
  // First delete from token database (has te be done while the records are
  // still in the documents table)
  deleteFilesFromTokenDb($iDocumentId, $iCorpusId);

  // Remove from corpus
  $sDeleteQuery = "DELETE FROM corpusId_x_documentId " .
    "WHERE corpus_id = $iCorpusId AND document_id = $iDocumentId";
  doNonSelectQuery($sDeleteQuery);

  // Remove dont_show entries
  $sDeleteQuery = "DELETE FROM dont_show " .
    "WHERE document_id = $iDocumentId AND corpus_id = $iCorpusId";
  doNonSelectQuery($sDeleteQuery);

  // See if the document is featured in another corpus
  // If it is, we quit here.
  // If it isn't, we delete any trace of the file completely.
  $sSelectQuery = "SELECT corpus_id FROM corpusId_x_documentId " .
    "WHERE document_id = $iDocumentId";
  if( $oResult = doSelectQuery($sSelectQuery) )
    if( ($aRow = mysql_fetch_assoc($oResult)) ) {    
      mysql_free_result($oResult);
      return;
    }

  // If it isn't delete every trace of it
  deleteFilesFromDb($iDocumentId);
}

// Not to be mistaken with deleteFilesFromDb().
// This one really throws the file away from the file system.
// 
function unlinkFile($sDocumentPath) {
  if(unlink($sDocumentPath) ) {
    printLog("Deleted file '$sDocumentPath'.\n");
    // Now, it could be, when the file was uploaded in a zip-file, that the
    // folder it is in is now empty.
    // In that case, we can delete the entire folder. And possibly the folder
    // that folder was contained in, etc...
    preg_match_all("/([^\/]+)\//", $sDocumentPath, $aMatches, PREG_SET_ORDER);

    // First we determine which folders are relevant to check, then we go
    // through them from last to first (because deleting the last folder may
    // result in its parent-folder becoming empty, which can then also
    // be deleted, etc...).
    $sPreviousPath = '/'; // It must be absolute paths!
    $bAddForDeletion = false;
    $aCheckForDeletion = array();
    foreach($aMatches as $aMatch) {
      if($bAddForDeletion)
	array_unshift($aCheckForDeletion, "${sPreviousPath}$aMatch[0]");
      $sPreviousPath .= $aMatch[0];
      if( $sPreviousPath ==
	  $GLOBALS['sDocumentRoot'] ."/" . $GLOBALS['sZipExtractDir'] . "/" )
	$bAddForDeletion = true;
    }

    // Since we unshifted them to the array, they come in the right order
    // (i.e. deepest path first).
    foreach( $aCheckForDeletion as $sPath) {
      printLog("Check: $sPath.\n");
      if(dirIsEmpty($sPath)) {
	printLog("$sPath is empty. Deleting.\n");
	rmdir($sPath);
      }
      else {
	printLog("$sPath is not empty\n");
      }
    }
  }
  else
    printLog("ERROR: Couldn't delete file '$sDocumentPath'.\n");
}

function dirIsEmpty($sDirPath) {
  $bDirIsEmpty = true;
  if ($dhHandle = opendir($sDirPath) ) {
    while(false !== ($sFile = readdir($dhHandle))) {
      if ($sFile != "." && $sFile != "..")
	$bDirIsEmpty = false;
    }
    closedir($dhHandle);
  }
  return $bDirIsEmpty;
} 

// Not to be mistaken for unlinkFile() which deletes a file from the file
// system. 
// This one removes one or more files from the database (and any traces of
// them).
function deleteFilesFromDb($sDocumentIds) {
  // Dont_show entries should already be deleted

  // Delete the tokens from the token db for the documents to be deleted
  $sDeleteQuery = "DELETE FROM " . $GLOBALS['sTokenDbName'] . ".tokens" .
    " WHERE document_id IN ($sDocumentIds)" .
    "   AND lexicon_database_id = " . $GLOBALS['iTokenDbDatabaseId'];
  doNonSelectQuery($sDeleteQuery);

  // Delete token attestation verifications
  $sDeleteQuery = "DELETE FROM token_attestation_verifications " .
    "WHERE document_id IN ($sDocumentIds)";
  doNonSelectQuery($sDeleteQuery);

  // Delete token attestations
  $sDeleteQuery = "DELETE FROM token_attestations " .
    "WHERE document_id IN ($sDocumentIds)";
  doNonSelectQuery($sDeleteQuery);

  // Delete from type frequencies
  $sDeleteQuery =
    "DELETE FROM type_frequencies WHERE document_id IN ($sDocumentIds)";
  doNonSelectQuery($sDeleteQuery);

  // Delete from wordform groups
  $sDeleteQuery =
    "DELETE FROM wordform_groups WHERE document_id IN ($sDocumentIds)";
  doNonSelectQuery($sDeleteQuery);

  // Delete from documents 
  $sDeleteQuery = "DELETE FROM documents WHERE document_id IN ($sDocumentIds)";
  doNonSelectQuery($sDeleteQuery);

  // NOTE that it is possible that analyzed wordforms exist which are not
  // associated with any document (anymore)...
}

// Token database functions ////////////////////////////////////////////////////

function deleteCorpusFromTokenDb($iCorpusId) {
  // First get all the document ids
  $sSelectQuery = "SELECT GROUP_CONCAT(document_id) documentIds" .
    " FROM corpusId_x_documentId " .
    "WHERE corpus_id = $iCorpusId";
  
  $sDocumentIds = '';
  if( ($oResult = doSelectQuery($sSelectQuery)) ) {
    if( ($aRow = mysql_fetch_assoc($oResult)) )
      $sDocumentIds .= $aRow['documentIds'];
    mysql_free_result($oResult);
  }
  if( strlen($sDocumentIds) )
    deleteFilesFromTokenDb($sDocumentIds, $iCorpusId);
}

function deleteFilesFromTokenDb($sDocumentIds, $iCorpusId) {
  // Look for the documents that do not occur anywhere else anymore
  // That is, now that they are being deleted from this corpus, they can
  // be deleted on the files system as well.
  $sSelectQuery =
    "SELECT document_paths.document_path_id, document_paths.path," .
    "       COUNT(*) AS nrOfCorpora " .
    "  FROM " . $GLOBALS['sTokenDbName'] . ".document_paths, " .
    "       " . $GLOBALS['sTokenDbName'] . ".document_indices, documents " .
    " WHERE documents.document_id IN($sDocumentIds)" .
    "  AND document_indices.document_path_id = document_paths.document_path_id"
    ." AND documents.title = path" .
    "  AND lexicon_database_id = " . $GLOBALS['iTokenDbDatabaseId'] . " " .
    "GROUP BY document_paths.document_path_id";

  $sDocumentPathIdsToBeDeleted = '';
  $sDocumentPathIds = '';
  $sComma1 = '';
  $sComma2 = '';
  if( ($oResult = doSelectQuery($sSelectQuery)) ) {
    while( ($aRow = mysql_fetch_assoc($oResult)) ) {
      // If it only appears once (i.e. only in this corpus), we can throw it
      // away entirely
      if( $aRow['nrOfCorpora'] == 1) {
	unlinkFile($aRow['path']);
	
	// If someone altered a word, the original tokenized file was copied
	// by the tool. We should clean that one up as well.
	$sOriginalTokenizedFile = $aRow['path'] . "_original.tab";
	if( file_exists($sOriginalTokenizedFile) )
	  unlinkFile($sOriginalTokenizedFile);

	if( substr($aRow['path'], -14) == '_tokenized.tab') {
	  $sOriginalFile = substr($aRow['path'], 0, -14);
	  // If the original was tokenized already, no other original exists
	  if( file_exists($sOriginalFile) )
	    unlinkFile($sOriginalFile);
	}
	
	$sDocumentPathIdsToBeDeleted .= $sComma1 . $aRow['document_path_id'];
	$sComma1 = ", ";
      }
      $sDocumentPathIds .= $sComma2 . $aRow['document_path_id'];
      $sComma2 = ", ";
    }
    mysql_free_result($oResult);
  }
  // Now that that is done, delete the superfluous entries from the
  // document_paths table
  if( strlen($sDocumentPathIdsToBeDeleted) ) {
    $sDeleteQuery =
      "DELETE FROM " . $GLOBALS['sTokenDbName'].".document_paths " .
      "      WHERE document_path_id IN ($sDocumentPathIdsToBeDeleted)";
    doNonSelectQuery($sDeleteQuery);
  }

  // And now we can delete the entries in the document_indices table
  if( strlen($sDocumentPathIds) ) {
    $sDeleteQuery =
      "DELETE FROM " . $GLOBALS['sTokenDbName'] . ".document_indices ".
      "WHERE corpus_id = $iCorpusId" .
      "  AND lexicon_database_id = " . $GLOBALS['iTokenDbDatabaseId'] .
      "  AND document_path_id IN ($sDocumentPathIds)";
    doNonSelectQuery($sDeleteQuery);
  }
}

// Group functions /////////////////////////////////////////////////////////////

function getGroupMemberOnsetsPerDocId($sDocIds, $iWordFormId) {
  $sSelectQuery = "SELECT document_id, GROUP_CONCAT(onset) tokenOnsets" .
    "  FROM " . $GLOBALS['sTokenDbName'] . ".tokens" .
    " WHERE document_id IN ($sDocIds)" .
    "   AND tokens.lexicon_database_id = " . $GLOBALS['iTokenDbDatabaseId'] .
    "   AND wordform_id = $iWordFormId" .
    " GROUP BY document_id";

  $sCondition = '';
  $sSeparator = '';
  if( ($oResult = doSelectQuery($sSelectQuery)) ) {
    while( ($aRow = mysql_fetch_assoc($oResult)) ) {
      $sCondition .= $sSeparator . "(document_id = " .
	$aRow['document_id'] . " AND onset IN (" . $aRow['tokenOnsets'] ."))";
      $sSeparator = " OR ";
    }
    mysql_free_result($oResult);
  }
  
  if( ! strlen($sCondition) )
    return false;
  
  $sSelectQuery =
    "SELECT groupMembers.document_id," .
    "       GROUP_CONCAT(groupMembers.onset) groupMemberOnsets ".
    " FROM (SELECT wordform_group_id, onset, offset" .
    "         FROM wordform_groups" .
    "        WHERE $sCondition) currentWords,".
    "       wordform_groups groupMembers " .
    "WHERE groupMembers.wordform_group_id = currentWords.wordform_group_id" .
    "  AND groupMembers.onset != currentWords.onset " .
    "GROUP BY groupMembers.document_id";
  $aGroupMemberOnsetsPerDocId = false;
  if( ($oResult = doSelectQuery($sSelectQuery)) ) {
    $aGroupMemberOnsetsPerDocId = array();
    while( ($aRow = mysql_fetch_assoc($oResult)) )
      $aGroupMemberOnsetsPerDocId[$aRow['document_id']] =
	$aRow['groupMemberOnsets'];
    mysql_free_result($oResult);
  }

  return $aGroupMemberOnsetsPerDocId;
}

function getGroupOnOffsets($iDocumentId, $iWordFormId,
			   $aGroupMemberOnsetsPerDocId) {
  $sSelectQuery =
    "SELECT currentWords.onset currentWordOnset," .
    "       currentWords.offset currentWordOffset," .
    "       groupMembers.onset groupMemberOnset," .
    "       groupMembers.offset groupMemberOffset" .
    "  FROM (SELECT wordform_group_id, onset, offset" .
    "          FROM wordform_groups" .
    "         WHERE document_id = $iDocumentId" .
    "           AND onset IN (SELECT onset" .
    "                          FROM " . $GLOBALS['sTokenDbName'] . ".tokens" .
    "                         WHERE document_id = $iDocumentId" .
    "      AND tokens.lexicon_database_id = " . $GLOBALS['iTokenDbDatabaseId'] .
    "                           AND wordform_id = $iWordFormId)) currentWords,"
    . "      wordform_groups groupMembers " .

    // On with the main query
    " WHERE groupMembers.wordform_group_id = currentWords.wordform_group_id" .
    "   AND groupMembers.onset != currentWords.onset" .
    // 04 march 2011
    // Next GROUP BY caused trouble when the same group was to be
    // displayed more than once (e.g. because a word appeared twice in it).
    //    " GROUP BY groupMembers.onset" .
    " ORDER BY currentWords.onset, groupMembers.onset";
  return doSelectQuery($sSelectQuery);
}

function addToGroup($iUserId, $iDocumentId, $iHeadWordOnset, $iHeadWordOffset,
		    $iOnset, $iOffset) {
  printLog("addToGroup($iUserId, $iDocumentId, $iHeadWordOnset, " .
	   "$iHeadWordOffset, $iOnset, $iOffset)\n");
  // First find out if there is a group for this wordform. Otherwise, make a
  // new group
  $iWordFormGroupId = getWordFormGroupId($iDocumentId, $iHeadWordOnset,
					 $iHeadWordOffset);
  if( ! $iWordFormGroupId)
    return;

  // Insert the new value
  // The ON DUPLICATE KEY is because if you click really fast this query
  // might be issued twice
  $sInsertQuery = "INSERT INTO wordform_groups " .
    "(wordform_group_id, document_id, onset, offset) VALUES " .
    "($iWordFormGroupId, $iDocumentId, $iOnset, $iOffset) " .
    "ON DUPLICATE KEY UPDATE wordform_group_id = wordform_group_id";
  doNonSelectQuery($sInsertQuery);

  // Token attestation verifications for all group members, including the new
  // one
  $sInsertQuery = "INSERT INTO token_attestation_verifications " .
    "(wordform_id, document_id, start_pos, end_pos, verified_by," .
    " verification_date) " .
    "SELECT wordform_id, wordform_groups.document_id, wordform_groups.onset,".
    "       wordform_groups.offset, $iUserId, NOW()" .
    "  FROM wordform_groups, " . $GLOBALS['sTokenDbName'] . ".tokens " .
    " WHERE tokens.document_id = wordform_groups.document_id" .
    "   AND tokens.lexicon_database_id = " . $GLOBALS['iTokenDbDatabaseId'] .
    "   AND tokens.onset = wordform_groups.onset" .
    "   AND wordform_group_id =" .
    "     (SELECT wordform_group_id" .
    "        FROM wordform_groups" .
    "       WHERE document_id = $iDocumentId AND onset = $iHeadWordOnset) ".
    "ON DUPLICATE KEY UPDATE verified_by = $iUserId,verification_date = NOW()";
  doNonSelectQuery($sInsertQuery);

  // Now any token attestation the headword has should be propagated to this
  // new group member.

  // Get the word form id of the new word
  $sSelectQuery =
    "SELECT wordform_id FROM " . $GLOBALS['sTokenDbName'] . ".tokens " .
    "WHERE document_id = $iDocumentId" .
    "  AND lexicon_database_id = " . $GLOBALS['iTokenDbDatabaseId'] .
    "  AND onset = $iOnset";
  $iNewWordFormId = false;
  if( ($oResult = doSelectQuery($sSelectQuery)) ) {
    if( ($aRow = mysql_fetch_assoc($oResult)) )
      $iNewWordFormId = $aRow['wordform_id'];
    mysql_free_result($oResult);
  }
  if( ! $iNewWordFormId) // That would be strange, but well...
    return false;

  // Insert all analyzed wordforms that the headword has for this new word
  /// Adding awf.part_of_speech..?!?
  $sInsertQuery = "INSERT INTO analyzed_wordforms " .
    "(wordform_id, part_of_speech, derivation_id, lemma_id, " .
    " multiple_lemmata_analysis_id, verified_by, verification_date) " .
    "SELECT $iNewWordFormId, analyzed_wordforms.part_of_speech," .
    "       analyzed_wordforms.derivation_id, lemma_id," .
    "       multiple_lemmata_analysis_id, $iUserId, NOW()" .
    "  FROM analyzed_wordforms, token_attestations ".
    " WHERE document_id = $iDocumentId" .
    "   AND start_pos = $iHeadWordOnset" .
    "   AND analyzed_wordforms.analyzed_wordform_id" .
    "        = token_attestations.analyzed_wordform_id " .
    "ON DUPLICATE KEY UPDATE verified_by= $iUserId, verification_date = NOW()";
  doNonSelectQuery($sInsertQuery);

  // Insert token attestations for the new word 
  $sInsertQuery = "INSERT INTO token_attestations " .
    "(analyzed_wordform_id, document_id, start_pos, end_pos) " .   
    "SELECT a.analyzed_wordform_id, $iDocumentId, $iOnset, $iOffset " .
    "  FROM analyzed_wordforms a, " .
    "       (SELECT analyzed_wordforms.derivation_id, lemma_id," .
    "               multiple_lemmata_analysis_id" .
    "          FROM token_attestations, analyzed_wordforms" .
    "         WHERE token_attestations.document_id = $iDocumentId" .
    "           AND token_attestations.start_pos = $iHeadWordOnset" .
    "           AND analyzed_wordforms.analyzed_wordform_id" .
    "        = token_attestations.analyzed_wordform_id) tokenAttsForHeadWord" .
    " WHERE wordform_id = $iNewWordFormId" .
    "   AND tokenAttsForHeadWord.derivation_id = a.derivation_id" .
    "   AND tokenAttsForHeadWord.lemma_id = a.lemma_id" .
    "   AND tokenAttsForHeadWord.multiple_lemmata_analysis_id".
    "        = a.multiple_lemmata_analysis_id " .
    "ON DUPLICATE KEY UPDATE token_attestations.attestation_id" .
    " = token_attestations.attestation_id";
  doNonSelectQuery($sInsertQuery);

  // And vice versa. Any token attestation this new group member has should be
  // propagated to all the group members.

  // Get all token_attestations that the new group member has (which could
  // include the new one(s) of the previous step, but wel...)
  $sInsertValues = $sSeparator = '';
  /// Adding awf.part_of_speech..?!?
  $sSelectQuery =
    "SELECT lemma_id, analyzed_wordforms.part_of_speech, " .
    "       analyzed_wordforms.derivation_id, " .
    "       multiple_lemmata_analysis_id".
    "  FROM analyzed_wordforms, token_attestations" .
    " WHERE document_id = $iDocumentId" .
    "   AND start_pos = $iOnset" .
    "   AND analyzed_wordforms.analyzed_wordform_id" .
    "        = token_attestations.analyzed_wordform_id";
  $aTokenAttsForNewGroupMember = false;
  if( ($oResult = doSelectQuery($sSelectQuery)) ) {    
    $aTokenAttsForNewGroupMember = array();
    while( ($aRow = mysql_fetch_assoc($oResult)) ) {
      array_push($aTokenAttsForNewGroupMember,
		 array($aRow['lemma_id'], $aRow['part_of_speech'],
		       $aRow['derivation_id'],
		       $aRow['multiple_lemmata_analysis_id']));
    }
  }
  if( ! $aTokenAttsForNewGroupMember)
    return false;
    
  // Get the wordform id's, docId, onset, offset of all group members,
  // except the new one
  $sSelectQuery =
    "SELECT wordform_id, wordform_groups.document_id, wordform_groups.onset,".
    "       wordform_groups.offset" .
    "  FROM wordform_groups, " . $GLOBALS['sTokenDbName'] . ".tokens " .
    " WHERE tokens.document_id = wordform_groups.document_id" .
    "   AND tokens.lexicon_database_id = " . $GLOBALS['iTokenDbDatabaseId'] .
    "   AND tokens.onset = wordform_groups.onset" .
    "   AND wordform_group_id =" .
    "     (SELECT wordform_group_id" .
    "        FROM wordform_groups" .
    "       WHERE document_id = $iDocumentId AND onset = $iHeadWordOnset)" .
    "   AND wordform_groups.onset != $iOnset";
  
  // Insert analyzed wordforms for all group members
  $sInsertValues = $sSelectCondition = $sSelectSeparator = $sInsertSeparator =
    '';
  $aTokenAtts = array();
  if( ($oResult = doSelectQuery($sSelectQuery)) ) {
    while( ($aRow = mysql_fetch_assoc($oResult)) ) {
      foreach($aTokenAttsForNewGroupMember as $aTokenAttTuple) {
	$sInsertValues .= $sInsertSeparator . "(" . $aRow['wordform_id'] .
	  ", '" . $aTokenAttTuple[1] . "', " . $aTokenAttTuple[0] . ", " .
	  $aTokenAttTuple[2] . ", " . $aTokenAttTuple[3] . ", $iUserId, NOW())";
	$sInsertSeparator = ", ";
	$sSelectCondition .= "$sSelectSeparator(lemma_id = " .
	  $aTokenAttTuple[0] . " AND part_of_speech = '" .
	  $aTokenAttTuple[1] . "' AND derivation_id = " .
	  $aTokenAttTuple[2] . " AND multiple_lemmata_analysis_id = " .
	  $aTokenAttTuple[3] .")";
	$sSelectSeparator = " OR ";
	// We store this as an array because a wordform might occur more often 
	// in one group
	// So aTokenAtts is:
	// [wfId1 => ["docId1, onset1, offset1",
	//            "docId2, onset2, offset2"
	//           ],
	//  ...
	//  wfId7 => ["docId1, onset7,offset7",
	//           etc...
	//           ],
	//  ...
	// ]
	//$sTuple = $aTokenAttTuple[0] . ", " . $aTokenAttTuple[1]. ", " .
	// $aTokenAttTuple[2];
	$sTuple = $aRow['document_id'] . ", " . $aRow['onset'] . ", " .
	  $aRow['offset'];
	if( ! isset($aTokenAtts[$aRow['wordform_id']]) )
	  $aTokenAtts[$aRow['wordform_id']] = array($sTuple);
	else
	  array_push($aTokenAtts[$aRow['wordform_id']], $sTuple);
      }
    }
    mysql_free_result($oResult);
  }

  if( count($aTokenAtts) ) { // If there are group members
    // Insert the new analyzed wordforms (i.e. we add any analyses the new
    // group member already has to the other group members as well). 
    $sInsertQuery = "INSERT INTO analyzed_wordforms " .
      "(wordform_id, part_of_speech, lemma_id, derivation_id," .
      " multiple_lemmata_analysis_id, verified_by, verification_date) " .
      "VALUES $sInsertValues ".
      "ON DUPLICATE KEY UPDATE verified_by=$iUserId,verification_date = NOW()";
    doNonSelectQuery($sInsertQuery);

    // Get the analyzed wordform id's of the records just inserted, per wf
    $sSelectQuery = "SELECT wordform_id, analyzed_wordform_id" .
      "  FROM analyzed_wordforms WHERE $sSelectCondition";
    $sInsertValues = $sInsertSeparator = '';
    if( ($oResult = doSelectQuery($sSelectQuery)) ) {
      while( ($aRow = mysql_fetch_assoc($oResult)) ) {
	printLog("Looking for wordform id: " . $aRow['wordform_id'] . "\n");
	// Check if it is set. There might not be token attestations?
	if( isset($aTokenAtts[$aRow['wordform_id']])) {
	  foreach($aTokenAtts[$aRow['wordform_id']] as $sDocIdOnsetOffset) {
	    $sInsertValues .= $sInsertSeparator .
	      "(" . $aRow['analyzed_wordform_id'] . ", $sDocIdOnsetOffset)";
	    $sInsertSeparator = ", ";
	  }
	}
      }
      mysql_free_result($oResult);
    }
  
    if( strlen($sInsertValues) ) {
      $sInsertQuery = "INSERT INTO token_attestations " .
	"(analyzed_wordform_id, document_id, start_pos, end_pos) " .
	"VALUES $sInsertValues " .
	"ON DUPLICATE KEY UPDATE attestation_id = attestation_id";
      doNonSelectQuery($sInsertQuery);
    }
  }
}

function deleteFromGroup($iUserId, $iDocumentId, $iHeadWordOnset,
			 $iHeadWordOffset, $iOnset, $iOffset) {
  printLog("Delete from group\n");
  // Get the wordform group id we are talking about
  $iWordFormGroupId =
    getExistsingWordFormGroupId($iDocumentId,$iHeadWordOnset,$iHeadWordOffset);
  if(! $iWordFormGroupId ) {
    print "<b>ERROR</b> in deleting group<br>\n";
    return;
  }

  // Indeed, delete this wordform from the group
  $sDeleteQuery = "DELETE FROM wordform_groups " .
    "WHERE wordform_group_id = $iWordFormGroupId" .
    "  AND document_id = $iDocumentId" .
    "  AND onset = $iOnset" .
    "  AND offset = $iOffset";
  doNonSelectQuery($sDeleteQuery);

  // This is special group behaviour.
  // Suppose we have a group "de Noord Holland" which has as a lemma
  // "Noord-Holland, NE_LOC". Now, we want to delete 'de' from it as it
  // actually doesn't belong to the NE_LOC.
  // In that case, we don't want 'de' to keep it's "Noord-Holland, NE_LOC"
  // analysis, as we just deliberately deleted 'de' from the group.
  // In fact, we don't want it to have any of the analyses the group has.
  // Any analyses that are not shared with the group (like "the, DET" e.g.)
  // may remain. Mind you, these are only analyzed_wordforms, NOT
  // token_attestations as every group member has the same token_attestations.
  // So, to only throw away the right analyzed_wordforms we look which ones are
  // featured in the token_attestations for this word.
  
  // First, get the wordform id
  $sSelectQuery =
    "SELECT wordform_id" .
    "  FROM token_attestations, analyzed_wordforms a" .
    " WHERE token_attestations.document_id = $iDocumentId" .
    "   AND token_attestations.start_pos = $iOnset" .
    "   AND token_attestations.analyzed_wordform_id = a.analyzed_wordform_id" .
    " LIMIT 1";
  $iWordFormId = 0;
  if( ($oResult = doSelectQuery($sSelectQuery)) ) {
    if( ($aRow = mysql_fetch_assoc($oResult)) ) {
      $iWordFormId = $aRow['wordform_id'];
    }
    mysql_free_result($oResult);
  }
  if( $iWordFormId ) { // It could also be that there where no extra token_atts
    // in which case the query returns no result.

    // First we get the relevant analyzed_wordform_id's
    // Get all the analyzed wordform id's for the current token_attestation
    // except for the ones that occur in other token attestations too.
    $sSelectQuery = 
      "SELECT GROUP_CONCAT(analyzed_wordform_id) awfs" .
      "  FROM token_attestations" .
      " WHERE document_id = $iDocumentId" .
      "   AND start_pos = $iOnset" .
      "   AND analyzed_wordform_id NOT IN" .
      "       (SELECT ta.analyzed_wordform_id" .
      "          FROM token_attestations ta, analyzed_wordforms awf" .
      "         WHERE ta.analyzed_wordform_id = awf.analyzed_wordform_id" .
      "           AND awf.wordform_id = $iWordFormId" .
      "           AND NOT (ta.document_id = $iDocumentId AND" .
      "                    ta.start_pos = $iOnset))";
    $sAwfs_toDelete = '';
    if( ($oResult = doSelectQuery($sSelectQuery)) ) {
      if( ($aRow = mysql_fetch_assoc($oResult)) ) {
	$sAwfs_toDelete = $aRow['awfs'];
      }
      mysql_free_result($oResult);
    }
    // Delete them if there are any
    if( strlen($sAwfs_toDelete) ) {
      $sDeleteQuery = "DELETE FROM analyzed_wordforms " .
	"WHERE analyzed_wordform_id IN ($sAwfs_toDelete)";
      doNonSelectQuery($sDeleteQuery);
    }
  }

  // Delete all the token_attestations for this word (this is safe because it
  // can only have token_attestations that the group also has. But it is
  // no longer a member of the group).
  $sDeleteQuery = "DELETE FROM token_attestations " .
    "WHERE document_id = $iDocumentId" .
    "  AND start_pos = $iOnset";
  doNonSelectQuery($sDeleteQuery);

  // Delete all token_attestation_verifications for this word
  // (This is safe, se above)
  $sDeleteQuery = "DELETE FROM token_attestation_verifications " .
    "WHERE document_id = $iDocumentId" .
    "  AND start_pos = $iOnset";
  doNonSelectQuery($sDeleteQuery);

  // If the group has just one member left, throw it away.
  $sSelectQuery = "SELECT COUNT(*) nrOfMembers FROM wordform_groups " .
    "WHERE wordform_group_id = $iWordFormGroupId";
  if( ($oResult = doSelectQuery($sSelectQuery)) ) {
    if( ($aRow = mysql_fetch_assoc($oResult)) ) {
      if($aRow['nrOfMembers'] == 1) {
	$sDeleteQuery = "DELETE FROM wordform_groups " .
	  "WHERE wordform_group_id = $iWordFormGroupId";
	doNonSelectQuery($sDeleteQuery);
      }
      else {
	// If there were more there still is a group and we have to verify all
	// its members
	$sInsertQuery = "INSERT INTO token_attestation_verifications " .
	  "(wordform_id, document_id, start_pos, end_pos, verified_by," .
	  " verification_date) " .
	  "SELECT wordform_id, wordform_groups.document_id,".
	  "       wordform_groups.onset, wordform_groups.offset," .
	  "       $iUserId, NOW()" .
	  "  FROM wordform_groups, " . $GLOBALS['sTokenDbName'] . ".tokens " .
	  " WHERE tokens.document_id = wordform_groups.document_id" .
	  "   AND tokens.lexicon_database_id = " .
	  $GLOBALS['iTokenDbDatabaseId'] .  
	  "   AND tokens.onset = wordform_groups.onset" .
	  "   AND wordform_group_id =" .
	  "     (SELECT wordform_group_id" .
	  "        FROM wordform_groups" .
	  "       WHERE document_id= $iDocumentId AND onset= $iHeadWordOnset)".
	  " ON DUPLICATE KEY UPDATE verified_by = $iUserId, " .
	  "  verification_date = NOW()";
	doNonSelectQuery($sInsertQuery);
      }
    }
    mysql_free_result($oResult);
  }
}

function getWordFormGroupId($iDocumentId, $iHeadWordOnset, $iHeadWordOffset) {
  $iWordFormGroupId =
    getExistsingWordFormGroupId($iDocumentId,$iHeadWordOnset,$iHeadWordOffset);

  if( $iWordFormGroupId)
    return $iWordFormGroupId;

  // If we haven't returned yet
  $sInsertQuery = "INSERT INTO wordform_groups" .
    " (wordform_group_id, document_id, onset, offset) " .
    " SELECT " .
    // Check if it MAX() is NULL (which it is if there is nothing there yet)
    "IF(MAX(wordform_group_id) IS NULL, 1, MAX(wordform_group_id)+1)" .
    ", $iDocumentId, $iHeadWordOnset, $iHeadWordOffset" .
    " FROM wordform_groups";
  doNonSelectQuery($sInsertQuery);

  // Try again
  $iWordFormGroupId =
    getExistsingWordFormGroupId($iDocumentId,$iHeadWordOnset,$iHeadWordOffset);

  if( $iWordFormGroupId)
    return $iWordFormGroupId;

  print "Error in getting group id<br>;";
  return false;
}

function getExistsingWordFormGroupId($iDocumentId, $iHeadWordOnset,
				     $iHeadWordOffset) {
  $sSelectQuery = "SELECT wordform_group_id FROM wordform_groups " .
    "WHERE document_id = $iDocumentId AND onset = $iHeadWordOnset";

  $iWordFormGroupId = false;
  if( ($oResult = doSelectQuery($sSelectQuery)) ) {
    if( ($aRow = mysql_fetch_assoc($oResult)) )
      $iWordFormGroupId = $aRow['wordform_group_id'];
    mysql_free_result($oResult);
  }
  return $iWordFormGroupId;
}

function getLanguages() {
  $sSelectQuery = "SELECT language_id, language FROM languages";

  $hLanguages = array();
  if( ($oResult = doSelectQuery($sSelectQuery)) ) {
    while( ($aRow = mysql_fetch_assoc($oResult)) )
      $hLanguages[$aRow['language']] = $aRow['language_id'];
    mysql_free_result($oResult);
  }
  return $hLanguages;
}

////////////////////////////////////////////////////////////////////////////////

function getDocumentPath($iDocumentId) {
  $sSelectQuery = "SELECT title FROM documents " .
    "WHERE document_id = $iDocumentId";

  $sDocumentPath = '';
  if( ($oResult = doSelectQuery($sSelectQuery)) ) {
    if( ($aRow = mysql_fetch_assoc($oResult)) )
      $sDocumentPath = $aRow['title'];
    mysql_free_result($oResult);
  }
  return $sDocumentPath;
}

// changeWordForm functions ///////////////////////////////////////////////////

function cwUpdateDatabase($iUserId, $aChanges, $aNewWordForms, $iOldWordFormId,
			  $iOffsetChange, $aNewOnsetOffsets) {
  list($aNewWordFormIds, $aWordFormFrequencies) = 
    cwGetNewWordFormIds($aNewWordForms, $aNewOnsetOffsets);

  cwUpdateTypeFrequencies_dontShows($iOldWordFormId, $aNewWordFormIds,
				    $aWordFormFrequencies, $aChanges);
  if( $iOffsetChange != 0) // If it is not zero, i.e. there *is* a change...
    cwUpdateWordFormGroups($aChanges);

  // Token attestations
  if(count($aNewWordForms) == 1) // No token split
    cwSingleWord($iUserId, $iOldWordFormId,
		 // NOTE the stupid trick to get the one new wf id.
		 $aNewWordFormIds[$aNewWordForms[0]], $aChanges);
  else // There was a token split
    cwMultiWordTokenAttestations($iUserId, $iOldWordFormId, $aChanges,
				 $aNewWordFormIds, $aNewOnsetOffsets,
				 $iOffsetChange);

  // For the entire document, update the token attestations and their
  // verifications if there was a change in offset
  cwUpdateTokens_tokenAttestations($aChanges, $aNewOnsetOffsets,
				   $iOffsetChange);
}

// Update all token attestations in the entire document after the word(s) that
// have changed
function cwUpdateTokens_tokenAttestations($aChanges, $aNewOnsetOffsets,
					  $iOffsetChange) {
  $sStartPosCases= $sEndPosCases = $sOnsetCases = $sOffsetCases =
    $sDeleteClauses = $sOr = '';
  foreach($aChanges as $iDocId => $aChange) {
    $iAmountOfChanges = count($aChanges[$iDocId]);
    for($i = 0; $i < $iAmountOfChanges; $i++ ) {
      $sStartPosCases .=
	" WHEN (document_id = $iDocId AND start_pos > " . $aChange[$i][0];
      $sEndPosCases .=
	" WHEN (document_id = $iDocId AND start_pos >= " . $aChange[$i][0];
      $sOnsetCases .=
	" WHEN (document_id = $iDocId" .
	"  AND lexicon_database_id = " . $GLOBALS['iTokenDbDatabaseId']	.
	"  AND onset > " . $aChange[$i][0];
      $sOffsetCases .=
	" WHEN (document_id = $iDocId" .
	"  AND lexicon_database_id = " . $GLOBALS['iTokenDbDatabaseId'] .
	"  AND onset >= " . $aChange[$i][0];
      if( ($i + 1) < $iAmountOfChanges) {
	# NOTE that we check the start+pos/onset in both cases...
	$sStartPosCases .= " AND start_pos <= " . $aChange[$i + 1][0];
	$sEndPosCases .= " AND start_pos < " . $aChange[$i + 1][0];
	$sOnsetCases .= " AND onset <= " . $aChange[$i + 1][0];
	$sOffsetCases .= " AND onset < " . $aChange[$i + 1][0];
      }
      $sStartPosCases .= ") THEN start_pos + " . $aChange[$i][1];
      $sEndPosCases .= ") THEN end_pos + " . $aChange[$i][1];
      $sOnsetCases .= ") THEN onset + " . $aChange[$i][1];
      $sOffsetCases .= ") THEN offset + " . $aChange[$i][1];

      $sDeleteClauses .=
	"$sOr(document_id = $iDocId AND onset = " . $aChange[$i][0] . ")";
      $sOr = " OR ";
    }
  }

  // First delete the current entries (which have the wrong word form id)
  $sDeleteQuery = "DELETE FROM " . $GLOBALS['sTokenDbName']. ".tokens " .
    "WHERE lexicon_database_id = " . $GLOBALS['iTokenDbDatabaseId']
    . "  AND ($sDeleteClauses)";
  doNonSelectQuery($sDeleteQuery);

  // Update the tokens in the token database
  $sUpdateQuery = "UPDATE " . $GLOBALS['sTokenDbName'] . ".tokens " .
    "SET offset = CASE $sOffsetCases ELSE offset END, " .
    "onset = CASE $sOnsetCases ELSE onset END";
  doNonSelectQuery($sUpdateQuery);

  // Insert the new ones
  $sInsertValues = $sComma = '';
  foreach( $aNewOnsetOffsets as $sDocIdOnset => $aOnsetOffsetWfIds ) {
    $iDocId = substr($sDocIdOnset, 0, strpos($sDocIdOnset, " "));
    foreach($aOnsetOffsetWfIds as $aOnsetOffsetWfId) {
      $sInsertValues .= "$sComma($iDocId, " . $aOnsetOffsetWfId[2] . ", " .
	$aOnsetOffsetWfId[0] . ", " . $aOnsetOffsetWfId[1] . ", " . 
	$GLOBALS['iTokenDbDatabaseId'] . ")";
      $sComma = ", ";
    }
  }
  $sInsertQuery = "INSERT INTO " . $GLOBALS['sTokenDbName'] . ".tokens " .
    "(document_id, wordform_id, onset, offset, lexicon_database_id) " .
    "VALUES $sInsertValues";
  doNonSelectQuery($sInsertQuery);

  // This actually only has to take place if the offset changed
  if( $iOffsetChange != 0) {
    // Token attestations
    $sUpdateQuery = "UPDATE token_attestations " .
      "SET end_pos = CASE $sEndPosCases ELSE end_pos END, " .
      "start_pos = CASE $sStartPosCases ELSE start_pos END";
    doNonSelectQuery($sUpdateQuery);
    
    // Token attestations verifications
    $sUpdateQuery = "UPDATE token_attestation_verifications " .
      "SET end_pos = CASE $sEndPosCases ELSE end_pos END, " .
      "start_pos = CASE $sStartPosCases ELSE start_pos END";
    doNonSelectQuery($sUpdateQuery);
  }
}

// If the token that was split happens to be a member of group, we can
// give any attestations this group has to these new members as well.
// If it was not a member of a group any token attestations will be deleted,
// because it is impossible to tell which one(s) of the new tokens should
// inherit the old analyses.
function cwMultiWordTokenAttestations($iUserId, $iOldWordFormId, $aChanges,
				      $aNewWordFormIds, $aNewOnsetOffsets,
				      $iOffsetChange) {
  $aGroupMembers = array();

  $sCondition = $sOr = '';
  foreach($aChanges as $iDocId => $aChange) {
    for($i=0; $i < count($aChange); $i++ ) {
      $sCondition .= "$sOr(document_id = $iDocId AND onset = " .
	$aChange[$i][0] . ")";
      $sOr = " OR ";
    }
  }
  $sSelectQuery = "SELECT document_id, onset FROM wordform_groups " .
    "WHERE $sCondition";
  if( ($oResult = doSelectQuery($sSelectQuery)) ) {
    if( ($aRow = mysql_fetch_assoc($oResult)) ) {
      $aGroupMembers[$aRow['document_id'] . " " . $aRow['onset']] = 1;
    }
    mysql_free_result($oResult);
  }

  $sDeleteClause = $sOr = '';
  $bFirstOne = TRUE;
  foreach($aChanges as $iDocId => $aChange) {
    for($i=0; $i < count($aChange); $i++ ) {
      if( array_key_exists("$iDocId " . $aChange[$i][0], $aGroupMembers) )
	cwPropagateGroupAnalyses($iUserId, $iDocId, $aChange[$i][0],
				 $aNewWordFormIds, $aNewOnsetOffsets,
				 $bFirstOne, $iOffsetChange);
      else {
	$sDeleteClause .= "$sOr(document_id = $iDocId AND start_pos = " .
	  $aChange[$i][0] . ")";
	$sOr = " OR ";
      }
      $bFirstOne = FALSE;
    }
  }

  // If there were token attestations to be deleted, delete them, plus any
  // verifications
  if( strlen($sDeleteClause) ) {
    $sDeleteQuery = "DELETE FROM token_attestations WHERE $sDeleteClause";
    ///printLog("cwMultiWordTokenAttestations : $sDeleteQuery\n");
    doNonSelectQuery($sDeleteQuery);

    $sDeleteQuery = 
      "DELETE FROM token_attestation_verifications WHERE $sDeleteClause";
    doNonSelectQuery($sDeleteQuery);
  }
}

// The token attestation was part of a group.
// First we get the analyses for the current token attestations and we copy
// those for the new word forms.
// The we delete the current token attestations and add the new ones.
//
// NOTE that this is all remarkably like cwSingleWord, but slightly
// different...
function cwPropagateGroupAnalyses($iUserId, $iDocId, $iOnset,
				  $aNewWordFormIds, $aNewOnsetOffsets,
				  $bFirstOne, $iOffsetChange) {
  // First we get the current analyses
  $sSelectQuery =
    "SELECT token_attestation_verifications.verified_by, " .
    "token_attestations.document_id, token_attestations.start_pos, " .
    "token_attestations.end_pos, " .
    "analyzed_wordforms.derivation_id, analyzed_wordforms.part_of_speech, " .
    "analyzed_wordforms.lemma_id, multiple_lemmata_analysis_id " .
    "  FROM analyzed_wordforms, token_attestations" .
    "  LEFT JOIN token_attestation_verifications" .
    "    ON (token_attestation_verifications.document_id" .
    "         = token_attestations.document_id AND" .
    "        token_attestation_verifications.start_pos" .
    "         = token_attestations.start_pos)" .
    " WHERE analyzed_wordforms.analyzed_wordform_id" .
    "  = token_attestations.analyzed_wordform_id" .
    " AND token_attestations.document_id = $iDocId" .
    " AND token_attestations.start_pos = $iOnset";

  $aAnalyzedWfInsertValues = array();
  $aAnalyzedWfSelectConditions = array();
  $aVerificationValues = array();
  if( ($oResult = doSelectQuery($sSelectQuery)) ) {
    while( ($aRow = mysql_fetch_assoc($oResult)) ) {
      $sTokenAttValue = "'" . $aRow['part_of_speech'] . "' " .
	$aRow['lemma_id'] . " " . $aRow['multiple_lemmata_analysis_id'] . " " .
	$aRow['derivation_id'];
	
      // Keep it unique
      foreach($aNewWordFormIds as $sNewWordForm => $iNewWordFormId) {
	$aAnalyzedWfInsertValues["('" . $aRow['part_of_speech'] . "', " .
				 $aRow['lemma_id'] . ", $iNewWordFormId, " .
				 $aRow['multiple_lemmata_analysis_id'] . ", " .
				 $aRow['derivation_id'] . ", $iUserId, NOW())"]
	  = 1;
	$aAnalyzedWfSelectConditions["(part_of_speech = '" .
				     addslashes($aRow['part_of_speech']) .
				     "' AND lemma_id = " . $aRow['lemma_id'] .
				     " AND wordform_id = $iNewWordFormId" .
				     " AND multiple_lemmata_analysis_id = " .
				     $aRow['multiple_lemmata_analysis_id'] .
				     " AND derivation_id = " .
				     $aRow['derivation_id'] . ")"] = 1;

	# Only verify if it was verified already
	if( $aRow['verified_by'] ) {
	  $aVerificationValues["(document_id = $iDocId, wordform_id = " .
			       "$iNewWordFormId, start_pos = " .
			       $aRow['start_pos'] . ", end_pos = " .
			       $aRow['end_pos'] . ", $iUserId, NOW())"] = 1;
	}
      }
    }
    mysql_free_result($oResult);
  }
  
  $sInsertQuery = "INSERT INTO analyzed_wordforms (part_of_speech, lemma_id, ".
    "wordform_id, multiple_lemmata_analysis_id, derivation_id, verified_by," .
    "verification_date) VALUES " .
    join(', ', array_keys($aAnalyzedWfInsertValues)) .
    " ON DUPLICATE KEY UPDATE analyzed_wordform_id = analyzed_wordform_id";
  /// printLog("cwMultiWordTokenAttestations: $sInsertQuery\n");
  doNonSelectQuery($sInsertQuery);

  // Get (all) the new analyzed_wordform_id's
  // So that is potentially too many
  // NOTE that the tokenAttValue column is exactly the same as the
  // $sTokenAttValue above
  $sSelectQuery = "SELECT analyzed_wordform_id, derivation_id, wordform_id " .
    " FROM analyzed_wordforms WHERE " .
    join(' OR ', array_keys($aAnalyzedWfSelectConditions));

  $aNewAnalyzedWfIds = array();
  if( ($oResult = doSelectQuery($sSelectQuery)) ) {
    while( ($aRow = mysql_fetch_assoc($oResult)) ) {
      if(array_key_exists($aRow['wordform_id']))
	array_push($aNewAnalyzedWfIds[$aRow['wordform_id']],
		   array($aRow['analyzed_wordform_id'],$aRow['derivation_id']));
      else
	$aNewAnalyzedWfIds[$aRow['wordform_id']] = 
	  array(array($aRow['analyzed_wordform_id'],$aRow['derivation_id']));
    }
    mysql_free_result($oResult);
  }

  // Now delete the current token attestations on this spot, plus any
  // verifications
  $sDeleteQuery = "DELETE FROM token_attestations " .
    "WHERE document_id = $iDocId AND start_pos = $iOnset";
  ///printLog("cwMultiWordTokenAttestations: $sDeleteQuery\n");
  doNonSelectQuery($sDeleteQuery);

  $sDeleteQuery = "DELETE FROM token_attestation_verifications " .
    "WHERE document_id = $iDocId AND start_pos = $iOnset";
  ///printLog("cwMultiWordTokenAttestations: $sDeleteQuery\n");
  doNonSelectQuery($sDeleteQuery);

  // Add the new ones
  //
  // NOTE that we do something very UN-elegant here.
  // The first token is inserted as is, but all the ones afterwards are
  // inserted with their onset/offsets diminished by the offset change.
  // This is because all the token attestations with onsets bigger than the
  // original onset will be updated later on.
  //
  $sInsertValues = '';
  $iIndex = 0;
  foreach( $aNewAnalyzedWfIds as $iNewWordFormId => $aNewAnalyzedWfs) {
    foreach( $aNewAnalyzedWfs as $aNewAnalyzedWf) {
      if($bFirstOne) {
	// Original onset, new offset
	$sInsertValues .= "(" . $aNewAnalyzedWf[0] . ", " . $aNewAnalyzedWf[1].
	  ", $iDocId, $iOnset, " . $aNewOnsetOffsets[$iIndex][1] . ")";
	$bFirstOne = FALSE;
      }
      else {
	// The UN-elegant bit: new onset/offset MINUS the offset change
	$sInsertValues .= ", (" . $aNewAnalyzedWf[0] . ", ".$aNewAnalyzedWf[1].
	  ", $iDocId, " .
	  ($aNewOnsetOffsets[$iIndex][0] - $iOffsetChange) . ", " .
	  ($aNewOnsetOffsets[$iIndex][1] - $iOffsetChange) . ")";
      }
    }
    $iIndex++;
  }
  
  $sInsertQuery = "INSERT INTO token_attestations " .
    "(analyzed_wordform_id, derivation_id, document_id, start_pos, end_pos) " .
    "VALUES $sInsertValues";
  doNonSelectQuery($sInsertQuery);

  // Update the verifications for the word forms at the new positions
  if( count($aVerificationValues) ) {
    $sInsertQuery = "INSERT INTO token_attestation_verifications " .
      "(document_id, wordform_id, start_pos, end_pos, verified_by, " .
      ", verification_date) VALUES " .
      join(", ", array_keys($aVerificationValues));
    doNonSelectQuery($sInsertQuery);
  }
}

// NOTE that this is all remarkeably like cwPropagateGroupAnalyses, but
// slightly different...
function cwSingleWord($iUserId, $iOldWordFormId, $iNewWordFormId,
		      $aChanges) {
  // First we copy all the analyzed wordforms.
  cwCopyAnalyzedWordForms($iUserId, $iOldWordFormId, $iNewWordFormId);

  $sCondition = $sOr = '';
  foreach($aChanges as $iDocId => $aChange) {
    for($i=0; $i < count($aChange); $i++ ) {
      $sCondition .= "$sOr(token_attestations.document_id = $iDocId " .
	"AND token_attestations.start_pos = " . $aChange[$i][0] . ")";
      $sOr = " OR ";
    }
  }

  // Get the token attestations
  $sSelectQuery =
    "SELECT token_attestations.document_id, token_attestations.start_pos, " .
    "token_attestations.end_pos, " .
    "analyzed_wordforms.analyzed_wordform_id, " .
    "analyzed_wordforms.derivation_id, analyzed_wordforms.part_of_speech, " .
    "analyzed_wordforms.lemma_id, multiple_lemmata_analysis_id " .
    "  FROM analyzed_wordforms, token_attestations" .
    " WHERE analyzed_wordforms.analyzed_wordform_id" .
    "  = token_attestations.analyzed_wordform_id AND ($sCondition)";

  $sInsertValues = $sComma = '';
  $aTokenAttClauses = array();
  $aTokenAtts = array();
  $aVerificationClauses = array();
  if( ($oResult = doSelectQuery($sSelectQuery)) ) {
    while( ($aRow = mysql_fetch_assoc($oResult)) ) {
      // For deleting, only the document id and start pos are relevant
      // but this combination can occur more often, as there can be more token
      // attestations for a token.
      // We make a hash of them to keep it unique (just to be tidy...).
      $aTokenAttClauses["(document_id = " . $aRow['document_id'] . " AND "
			. "start_pos = " . $aRow['start_pos'] . ")"] = 1;

      $sTokenAttKey = $aRow['document_id'] . ', ' . $aRow['start_pos'] . ', ' .
	$aRow['end_pos'];
      $sTokenAttValue = "'" . $aRow['part_of_speech'] . "' " .
	$aRow['lemma_id'] . " " . $aRow['multiple_lemmata_analysis_id'] . " " .
	$aRow['derivation_id'];
      if(array_key_exists($sTokenAttKey, $aTokenAtts))
	array_push($aTokenAtts[$sTokenAttKey], $sTokenAttValue);
      else
	$aTokenAtts[$sTokenAttKey] = array($sTokenAttValue);
	
      $aVerificationClauses["(document_id = " . $aRow['document_id'] .
			    " AND start_pos = " . $aRow['start_pos'] . ")"]= 1;
    }
    mysql_free_result($oResult);
  }

  // If there were no token attestations, we can quit.
  if( ! count($aVerificationClauses))
    return;

  // Get (all) the new analyzed_wordform_id's
  // So that is potentially too many
  // NOTE that the tokenAttValue column is exactly the same as the
  // $sTokenAttValue above
  $sSelectQuery = "SELECT analyzed_wordform_id, derivation_id, " .
    "CONCAT('\'', part_of_speech, '\' ', lemma_id, ' '," .
    "multiple_lemmata_analysis_id, ' ', derivation_id) AS tokenAttValue" .
    " FROM analyzed_wordforms WHERE wordform_id = $iNewWordFormId";

  $aNewAnalyzedWfIds = array();
  if( ($oResult = doSelectQuery($sSelectQuery)) ) {
    while( ($aRow = mysql_fetch_assoc($oResult)) ) {
      $aNewAnalyzedWfIds[$aRow['tokenAttValue']] =
	array($aRow['analyzed_wordform_id'], $aRow['derivation_id']);
    }
    mysql_free_result($oResult);
  }

  printLog("New analyseds:\n");
  foreach($aNewAnalyzedWfIds as $sTknAttVl => $aArr) {
    printLog("\t$sTknAttVl => " . $aArr[0] . ", " . $aArr[1] . "\n");
  }

  // Now delete the current token attestations on these spots, plus any
  // verifications
  $sDeleteClause = join(' OR ', array_keys($aTokenAttClauses));
  $sDeleteQuery = "DELETE FROM token_attestations WHERE $sDeleteClause";
  /// printLog("cwSingleWord: $sDeleteQuery\n");
  doNonSelectQuery($sDeleteQuery);

  // Add the new ones
  //
  // NOTE that we add them with the original onset/ofsets. These will be
  // updated later on, if needed, in one go with the rest of the token
  // attestations for this file.
  $sInsertValues = $sComma = '';
  foreach($aTokenAtts as $sTokenAttKey => $aTokenAttValues) {
    printLog("Key: $sTokenAttKey\n");
    foreach($aTokenAttValues as $sTokenAttValue ) {
      printLog("   Values: $sTokenAttValue\n");
      $sInsertValues .= "$sComma(" . $aNewAnalyzedWfIds[$sTokenAttValue][0] .
	", " . $aNewAnalyzedWfIds[$sTokenAttValue][1] . ", $sTokenAttKey)";
      $sComma = ", ";
    }
  }

  $sInsertQuery = "INSERT INTO token_attestations " .
    "(analyzed_wordform_id, derivation_id, document_id, start_pos, end_pos) " .
    "VALUES $sInsertValues";
  doNonSelectQuery($sInsertQuery);

  // Also, verify the new token attestation(s)
  // We leave to onsets/offsets as they are at the moment.
  $sUpdateQuery = "UPDATE token_attestation_verifications " .
    "SET wordform_id = $iNewWordFormId, verified_by = $iUserId," .
    " verification_date = NOW() " .
    "WHERE " . join(" OR ", array_keys($aVerificationClauses));
  doNonSelectQuery($sUpdateQuery);
}

// This function is called to copy all analyzed wordforms for one word
// for another word.
function cwCopyAnalyzedWordForms($iUserId, $iOldWordFormId, $iNewWordFormId) {
  $sSelectQuery = "SELECT part_of_speech, lemma_id," .
    " multiple_lemmata_analysis_id, derivation_id" .
    "   FROM analyzed_wordforms " .
    "  WHERE wordform_id = $iOldWordFormId";

  $sValues = $sComma = '';
  if( ($oResult = doSelectQuery($sSelectQuery)) ) {
    while( ($aRow = mysql_fetch_assoc($oResult)) ) {
      $sValues .= $sComma . "('" . $aRow['part_of_speech'] . "', " .
	$aRow['lemma_id'] . ", $iNewWordFormId, " .
	$aRow['multiple_lemmata_analysis_id'] . ", " . $aRow['derivation_id'] .
	", $iUserId, NOW())";
      $sComma = ", ";
    }
    mysql_free_result($oResult);
  }

  if( strlen($sValues)) {
    $sInsertQuery = "INSERT INTO analyzed_wordforms " .
      "(part_of_speech, lemma_id, wordform_id, multiple_lemmata_analysis_id," .
      " derivation_id, verified_by, verification_date) " .
      "VALUES $sValues " .
      "ON DUPLICATE KEY UPDATE analyzed_wordform_id = analyzed_wordform_id";
    doNonSelectQuery($sInsertQuery);
  }
}

function cwGetNewWordFormIds($aNewWordForms, &$aNewOnsetOffsets) {
  /// printLog("Doing cwGetNewWordFormIds()\n");

  // Don't bother checking first, just insert them all right away
  $sCondition = $sInsertValues = $sOr = $sComma = '';
  $aWordFormFrequencies = array();
  for($i= 0; $i < count($aNewWordForms); $i++) {
    $sEscapedWf = addslashes($aNewWordForms[$i]);
    $sInsertValues .= "$sComma('$sEscapedWf', '".strtolower($sEscapedWf). "')";
    $sComma = ", ";
    $sCondition .= "$sOr(wordform = '$sEscapedWf')";
    $sOr = " OR ";
    if(array_key_exists($aNewWordForms[$i], $aWordFormFrequencies))
      $aWordFormFrequencies[$aNewWordForms[$i]]++;
    else
      $aWordFormFrequencies[$aNewWordForms[$i]] = 1;
  }

  $sInsertQuery = "INSERT INTO wordforms (wordform, wordform_lowercase) " .
    "VALUES $sInsertValues ON DUPLICATE KEY UPDATE wordform_id = wordform_id";
  doNonSelectQuery($sInsertQuery);

  $sSelectQuery =
    "SELECT wordform_id, wordform FROM wordforms WHERE $sCondition";

  $aNewWordFormIds = array();
  if( ($oResult = doSelectQuery($sSelectQuery)) ) {
    while( ($aRow = mysql_fetch_assoc($oResult)) ) {
      $aNewWordFormIds[$aRow['wordform']] = $aRow['wordform_id'];
    }
    mysql_free_result($oResult);
  }

  // Now update the new onset/offset array so it lists the word id's next to
  // their onset/offset
  foreach($aNewOnsetOffsets as $sKey => $aOnsetOffsets) {
    for($i = 0; $i < count($aNewWordForms); $i++) {
      // Here we do it in the right order
      printLog("Pushing: '" . $aNewWordForms[$i] . "' to " .
      	       "\$aNewWordFormIds[" . $sKey . "[$i]]\n");
      array_push($aNewOnsetOffsets[$sKey][$i],
		 $aNewWordFormIds[$aNewWordForms[$i]]);
    }
  }

  return array($aNewWordFormIds, $aWordFormFrequencies);
}

// NOTE that this goes wrong when a word form is changed into multiple instances
// of itself...!!!
// So e.g: 'a' -> 'a|a'.
function cwUpdateTypeFrequencies_dontShows($iOldWordFormId, $aNewWordFormIds,
					   $aWordFormFrequencies, $aChanges) {
  $sCases = $sStillExistsClause = $sOr = $sComma = $sInsertValues = '';
  $bKeepWordform = FALSE;
  foreach($aChanges as $iDocId => $aChange) {
    $iFreq = count($aChange);

    $bKeep = FALSE;
    foreach( $aNewWordFormIds as $sNewWordForm => $iNewWordFormId ) {
      // It could be the case that the new word(s) consists of the old word
      // and another one. In that case we shouldn't throw away entries for
      // the old word later on...
      if( $iNewWordFormId == $iOldWordFormId) {
	$bKeepWordform = TRUE; # Used later on
	$bKeep = TRUE; # Used only in this loop
      }
      else {
	// First we insert them with freq = 0. In the next statement we update
	// them (in case they were there already).
	$sInsertValues .= "$sComma($iDocId, $iNewWordFormId, 0)";
	$sComma = ", ";
	$iNewFreq = $iFreq * $aWordFormFrequencies[$sNewWordForm];
	$sCases .= " WHEN (document_id = $iDocId AND wordform_id = " .
	  "$iNewWordFormId) THEN frequency + $iNewFreq";
      }
    }

    if( ! $bKeep )
      $sCases .=
	" WHEN (document_id = $iDocId AND wordform_id = $iOldWordFormId)".
	" THEN frequency - $iFreq";

    $sStillExistsClause .=
      "$sOr(document_id = $iDocId AND wordform_id = $iOldWordFormId)";
    $sOr = " OR ";
  }

  // These could be empty when the (normalized) changed word form is the same as
  // the (normalized) old word form.
  if(strlen($sInsertValues)) {
    $sInsertQuery = "INSERT INTO type_frequencies " .
      "(document_id, wordform_id, frequency) VALUES $sInsertValues " .
      "ON DUPLICATE KEY UPDATE type_frequency_id = type_frequency_id";
    doNonSelectQuery($sInsertQuery);
  }

  if( strlen($sCases) ) {
    $sUpdateQuery =
      "UPDATE type_frequencies SET frequency = CASE $sCases ELSE frequency END";
    doNonSelectQuery($sUpdateQuery);
  }

  if( ! $bKeepWordform ) {
    // It could be that the old word form doesn't occur anymore anywhere.
    // This has repercussions as well for other tables, so we check this.
    $sSelectQuery = "SELECT document_id, frequency FROM type_frequencies ".
      "WHERE $sStillExistsClause";
    $sDeleteClause = $sOr = '';
    if( ($oResult = doSelectQuery($sSelectQuery)) ) {
      while( ($aRow = mysql_fetch_assoc($oResult)) ) {
	if( $aRow['frequency'] == 0) {
	  $sDeleteClause .= "$sOr(document_id = " . $aRow['document_id'] . ")";
	  $sOr = " OR ";
	}
      }
      mysql_free_result($oResult);
    }
  
    if( strlen($sDeleteClause) ) {
      $sDeleteQuery = "DELETE FROM type_frequencies " .
	"WHERE wordform_id = $iOldWordFormId AND ($sDeleteClause)";
      doNonSelectQuery($sDeleteQuery);
      
      // The same for the dont_shows
      // DO NOTE however that any corpus-wide dont_show options will not be
      // deleted....
      $sDeleteQuery = "DELETE FROM dont_show " .
	"WHERE wordform_id = $iOldWordFormId AND ($sDeleteClause)";
      doNonSelectQuery($sDeleteQuery);
    }
  }
}

// Every word form group in the entire file needs to be adjusted for the new
// onset/offset (so this function is only called when there *is* indeed a new
// onset/offset.
function cwUpdateWordFormGroups($aChanges) {
  $sOnsetCases = $sOffsetCases = '';
  foreach($aChanges as $iDocId => $aChange) {
    $iAmountOfChanges = count($aChanges[$iDocId]);
    printLog("change length: $iAmountOfChanges\n");
    for($i = 0; $i < $iAmountOfChanges; $i++ ) {
      $sOnsetCases .=
	" WHEN (document_id = $iDocId AND onset > " . $aChange[$i][0];
      $sOffsetCases .=
	" WHEN (document_id = $iDocId AND onset >= " . $aChange[$i][0];
      if( ($i + 1) < $iAmountOfChanges) {
	$sOnsetCases .= " AND onset <= " . $aChange[$i + 1][0];
	$sOffsetCases .= " AND onset < " . $aChange[$i + 1][0];
      }
      $sOnsetCases .= ") THEN onset + " . $aChange[$i][1];
      $sOffsetCases .= ") THEN offset + " . $aChange[$i][1];
    }
  }

  $sUpdateQuery =
    "UPDATE wordform_groups SET offset = CASE $sOffsetCases ELSE offset END,".
    "onset = CASE $sOnsetCases ELSE onset END";
  doNonSelectQuery($sUpdateQuery);
}

// Edit lemma functions //////////////////////////////////////////////////////

function fillEditLemma($iUserId, $iLemmaId) {
  $hLanguages = getLanguages();
  $bLanguages = count($hLanguages);

  $sSelectQuery =
    "SELECT" .
    " REPLACE(REPLACE(modern_lemma, '>', '&gt;'), '<', '&lt;') modern_lemma,".
    " lemma_part_of_speech, gloss, language" .
    "  FROM lemmata" .
    "       LEFT JOIN languages ON(lemmata.language_id= languages.language_id)".
    " WHERE lemma_id = $iLemmaId";

  print "<form id=editLemmaForm>\n" .
    "<table border=0><tr><td class=editLemmaHeader>Lemma headword</td>" .
    "<td class=editLemmaHeader>Part of speech</td>";
  if( $bLanguages )
    print "<td class=editLemmaHeader>Language</td>";
  print "<td class=editLemmaHeader>Gloss</td><td></td></tr>\n";
  if( ($oResult = doSelectQuery($sSelectQuery)) ) {
    if( ($aRow = mysql_fetch_assoc($oResult)) ) {
      print "<tr>" .
	// Headword
	"<td>" .
	"<input name=el_modernLemma type=text maxlength=255 size=30 value=\"" .
	str_replace('"', "&quot;", $aRow['modern_lemma']) . "\"></td>" .
	// Part of speech
	"<td>" .
	"<input name=el_partOfSpeech type=text maxlength=255 size=15 value=\"" .
	str_replace('"', "&quot;", $aRow['lemma_part_of_speech']) . "\"></td>";
      // Language. Only if defined for this database.
      if( $bLanguages ) {
	print "<td><select name=el_language>";
	$sSelected = (strlen($aRow['language'])) ? '' : 'selected';
	print "<option value='' $sSelected></option>";
	foreach($hLanguages as $sLanguage => $iLanguageId) {
	  $sSelected = ($aRow['language'] == $sLanguage) ? 'selected' : '';
	  print "<option value='$iLanguageId:$sLanguage' " .
	    "$sSelected>$sLanguage</option>\n";
	}
	print "</select>\n</td>\n";
      }
      // Gloss
      print "<td>" .
	"<input name=el_gloss type=text maxlength=255 size=40 value=\"" .
	str_replace('"', "&quot;", $aRow['gloss']) . "\"></td>";
      // (Hidden) lemma id
      print "<input name=el_lemmaId type=hidden value=$iLemmaId>";
    }
    mysql_free_result($oResult);

    print "<td class=editLemmaSubmits>" .
      // Alter
      "<a href=\"javascript: alterLemma($iLemmaId);\">" .
      "<img src='./img/editLemma.png' title='Alter this lemma'" .
      " alt='Alter this lemma' border=0></a>" .
      "&nbsp;&nbsp;" .
      // Delete
      "<a href=\"javascript: deleteLemma($iLemmaId);\">" .
      "<img src='./img/removeLemma.png' title='Delete this lemma'" .
      " alt='Delete this lemma' border=0></a>" .
      "&nbsp;&nbsp;" .
      // Close button
      "<input type=button value=Close" .
      " onClick=\"javascript:" .
      " document.getElementById('lemmaEditDiv').style.display = 'none';\">" .
      "</td></tr>\n";
  }

  print "</table></form>\n";
}

function alterLemma($iLemmaId, $sModernLemma, $sPartOfSpeech, $sGloss,
		    $iLanguageId) {
  // First check if we are not trying to add an already existing lemma
  $sSelectQuery = "SELECT lemma_id, language FROM lemmata " .
    "     LEFT JOIN languages ON (lemmata.language_id = languages.language_id)".
    " WHERE modern_lemma = '" . addslashes($sModernLemma) . "'" .
    "   AND lemma_part_of_speech = '" . addslashes($sPartOfSpeech) . "'" .
    "   AND gloss = '" . addslashes($sGloss) . "'" .
    "   AND lemmata.language_id = $iLanguageId";
  $sPrint = '';
  if( ($oResult = doSelectQuery($sSelectQuery)) ) {
    if( ($aRow = mysql_fetch_assoc($oResult)) ) {
      $sPrint = "<div class=lastViewed_>" .
	"Lemma '$sModernLemma, $sPartOfSpeech";
      if( $aRow['language'] )
	$sPrint .= ", " . $aRow['language'];
      if( strlen($sGloss) )
	$sPrint .= ", $sGloss'";
      $sPrint .= "' already exists.</div>";
    }
    mysql_free_result($oResult);
  }

  if( strlen($sPrint) )
    print $sPrint;
  else {
    $sUpdateQuery = "UPDATE lemmata SET " .
      "modern_lemma = '" . addslashes($sModernLemma) . "', " .
      "lemma_part_of_speech = '" . addslashes($sPartOfSpeech) . "', " .
      "gloss = '" . addslashes($sGloss) . "', " .
      "language_id = $iLanguageId " .
      "WHERE lemma_id = $iLemmaId";
    doNonSelectQuery($sUpdateQuery);
  }
}

function deleteLemma($iLemmaId) {
  // Get all multiple lemmata analyses ids for analyses with this lemma.
  $sMultipleLemmataAnalysisIds =
    getMultipleLemmataAnalysisIds_forLemmaId($iLemmaId);

  // Get all analyzed wordforms that for this lemma (also if it is part of a
  // multiple lemmata analysis.
  $sAnalyzedWordFormIds =
    getAllAnalysedWordFormIds_forLemmaId($iLemmaId,
					 $sMultipleLemmataAnalysisIds);

  if(strlen($sAnalyzedWordFormIds)) {
    // NOTE that we deliberately DON'T delete any
    // token_attestation_verifications.

    // Delete all token attestations for these analyzed wordforms.
    $sDeleteQuery = "DELETE FROM token_attestations" .
      " WHERE analyzed_wordform_id IN ($sAnalyzedWordFormIds)";
    doNonSelectQuery($sDeleteQuery);

    // Delete all analyzed wordforms for this lemma.
    $sDeleteQuery = "DELETE FROM analyzed_wordforms" .
      " WHERE analyzed_wordform_id IN ($sAnalyzedWordFormIds)";
    doNonSelectQuery($sDeleteQuery);
  }

  if( strlen($sMultipleLemmataAnalysisIds) ) {
    // Delete all multiple lemmata analyses that have this lemma as a part.
    // (regardless of whether they are feature in an analyzed wordform).
    $sDeleteQuery = "DELETE FROM multiple_lemmata_analyses" .
      " WHERE multiple_lemmata_analysis_id IN ($sMultipleLemmataAnalysisIds)";
    doNonSelectQuery($sDeleteQuery);
  }

  // Delete all multiple lemmata analysis parts for this lemma.
  $sDeleteQuery = "DELETE FROM multiple_lemmata_analysis_parts" .
    " WHERE lemma_id = $iLemmaId";
  doNonSelectQuery($sDeleteQuery);
  
  // And, oh yes, delete the lemma itself.
  $sDeleteQuery = "DELETE FROM lemmata WHERE lemma_id = $iLemmaId";
  doNonSelectQuery($sDeleteQuery);
}

function getMultipleLemmataAnalysisIds_forLemmaId($iLemmaId) {
  $sSelectQuery =
    "SELECT GROUP_CONCAT(mla.multiple_lemmata_analysis_id) mlaIds" .
    "  FROM multiple_lemmata_analyses mla," .
    "       multiple_lemmata_analysis_parts mlap" .
    " WHERE mla.multiple_lemmata_analysis_part_id" .
    "        = mlap.multiple_lemmata_analysis_part_id" .
    "   AND mlap.lemma_id = $iLemmaId";

  $sMultipleLemmataAnalysesIds = '';
  if( ($oResult = doSelectQuery($sSelectQuery)) ) {
    if( ($aRow = mysql_fetch_assoc($oResult)) )
      $sMultipleLemmataAnalysesIds .= $aRow['mlaIds'];
    mysql_free_result($oResult);
  }

  return $sMultipleLemmataAnalysesIds;
}

function getAllAnalysedWordFormIds_forLemmaId($iLemmaId,
					      $sMultipleLemmataAnalysisIds) {
  // Select the single lemma analyses
  $sSelectQuery =
    "SELECT GROUP_CONCAT(analyzed_wordform_id) awfs" .
    "  FROM analyzed_wordforms" .
    " WHERE lemma_id = $iLemmaId";

  $sAnalyzedWordFormIds = '';
  if( ($oResult = doSelectQuery($sSelectQuery)) ) {
    if( ($aRow = mysql_fetch_assoc($oResult)) )
      $sAnalyzedWordFormIds .= $aRow['awfs'];
    mysql_free_result($oResult);
  }
  
  // Select the multiple lemmata anlyses
  if(strlen($sMultipleLemmataAnalysisIds) ) {
    $sSelectQuery = "SELECT GROUP_CONCAT(analyzed_wordform_id) awfs" .
      "  FROM analyzed_wordforms awf, multiple_lemmata_analyses mla" .
      " WHERE awf.multiple_lemmata_analysis_id" .
      "        = mla.multiple_lemmata_analysis_id" .
      "   AND mla.multiple_lemmata_analysis_id" .
      "        IN ($sMultipleLemmataAnalysisIds)";

    $sComma = (strlen($sAnalyzedWordFormIds)) ? ", " : '';
    if( ($oResult = doSelectQuery($sSelectQuery)) ) {
      if( ($aRow = mysql_fetch_assoc($oResult)) )
	$sAnalyzedWordFormIds .= $sComma . $aRow['awfs'];
      mysql_free_result($oResult);
    }
  }

  return $sAnalyzedWordFormIds;
}

// Last view function ////////////////////////////////////////////////////////

function setLastView($iWordFormId, $iUserId) {
  $sUpdateQuery = "UPDATE wordforms " .
    "SET lastviewed_by = $iUserId, lastview_date = NOW() " .
    "WHERE wordform_id = $iWordFormId";
  doNonSelectQuery($sUpdateQuery);
}

function getLastView($iWordFormId) {
  $sSelectQuery =
    "SELECT lastviewed_by, name, " .
    "       DATE_FORMAT(lastview_date, '%a %d %b %Y, %T') lastviewDate," .
    "       IF(TIMESTAMPDIFF(SECOND, lastview_date, NOW())" .
    "          < " . $GLOBALS['iMaxLastViewedDiff1'] . ", 1, 0) recent1, " .
    "       IF(TIMESTAMPDIFF(SECOND, lastview_date, NOW())" .
    "          < " . $GLOBALS['iMaxLastViewedDiff2'] . ", 1, 0) recent2" .    
    "  FROM users, wordforms ".
    " WHERE wordforms.wordform_id = $iWordFormId" .
    "   AND wordforms.lastviewed_by = users.user_id";

  if( ($oResult = doSelectQuery($sSelectQuery)) ) {
    if( ($aRow = mysql_fetch_assoc($oResult)) )
      print $aRow['lastviewed_by'] . "\t" . $aRow['name'] . "\t" .
	$aRow['lastviewDate'] . "\t" . $aRow['recent1'] . "\t" .
	$aRow['recent2'];;
    mysql_free_result($oResult);
  }
}

// More basic functions ///////////////////////////////////////////////////////

function doSelectQuery($sSelectQuery) {
  printLog("Doing $sSelectQuery<br>\n");
  $oResult = mysql_query($sSelectQuery, $GLOBALS['dbh']);
  printMySQLError($sSelectQuery);
  return $oResult;
}

function doNonSelectQuery($sQuery) {
  printLog("Doing $sQuery<br>\n");
  mysql_query($sQuery, $GLOBALS['dbh']);
  printMySQLError($sQuery);
}

function printMySQLError($sQuery) {
  $sError = mysql_error($GLOBALS['dbh']);
  if( strlen($sError) ) {
    printLog("<b>ERROR</b> in: $sQuery<br><b>ERROR</b> is: $sError<br>\n");
    print "<b>ERROR</b> in: $sQuery<br><b>ERROR</b> is: $sError<br>\n";
  }
}

function printToScreenAndLog($sString) {
  print $sString;
  printLog($sString);
}

function printLog($sString) {
  if( $GLOBALS['sLogFile'] ) {
    $fh = fopen($GLOBALS['sLogFile'], 'a');
    # Next line is there because PHP version 5.3 and up require it.
    # You are supposed to also be able to set it in php.ini but it somehow
    # wouldn't work...
    date_default_timezone_set("Europe/Amsterdam");
    fwrite($fh, date("Y-m-d H:i:s") . "\t" . $sString);
    fclose($fh);
  }
}

function stripSpaces($sString) {
  return preg_replace("/\s+$/", '', preg_replace("/^\s+/", '', $sString));
}

?>