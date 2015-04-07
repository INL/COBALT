<?php

/******************************************************************************

In the old database structure, the analysis of a word group was attached
to a random member of the group. In the new version of Cobalt, the analysis 
of the group is attached to each of its members.

This script takes care of converting the old situation into the new one.

First, a query gathers for each group member all the token attestations available.
Later on, an analysis and a token attestation is built for each group member.

******************************************************************************/

if( $argc < 2) {
  print "\nERROR: No database found.\n\n $argv[0] DATABASE\n\n";
  exit;
}

require_once('lexiconToolBox.php');

chooseDb($argv[1]);

$aGroup = array();
$sKey = $sValue = '';
$iPreviousWordformGroupId = -1;
if( $oResult = doSelectQuery(getSelectQuery()) ) {
  while(($aRow = mysql_fetch_assoc($oResult)) ) {
    if( $aRow['wordform_group_id'] != $iPreviousWordformGroupId) {
      roundupGroup($aGroup);
    }
    else {
      $sKey = $aRow['document_id'] . ', ' . $aRow['onset'] . ', ' .
	$aRow['offset'];
      $aValue = ($aRow['lemma_id'] || $aRow['derivation_id'] ||
		 $aRow['multiple_lemmata_analysis_id'])
	? array('iWordformId' => $aRow['wordform_id'],
		'sValue' => $aRow['lemma_id'] . ", " . $aRow['derivation_id']
		. ', ' . $aRow['multiple_lemmata_analysis_id'],
		'aValue' => array($aRow['lemma_id'], $aRow['derivation_id'],
				  $aRow['multiple_lemmata_analysis_id']))
	: array('iWordformId' => $aRow['wordform_id'],
		'sValue' => false,
		'aValue' => false);
      if( isset($aGroup[$sKey]))
	array_push($aGroup[$sKey], $aValue);
      else
	$aGroup[$sKey] = array($aValue);
    }
    $iPreviousWordformGroupId = $aRow['wordform_group_id'];
  }
  mysql_free_result($oResult);
}

// Functions //////////////////////////////////////////////////////////////////

function roundupGroup(&$aGroup) {
  // Here, we loop twice through the list, for each value, each key
  foreach($aGroup as $sKey1 => $aValues1) {
    foreach($aGroup as $sKey2 => $aValues2) {    
      if( $sKey1 != $sKey2) {
	foreach($aValues1 as $aValue1) {
	  // We take the first one as a basis

	  // Value check, because the analysis might exist already (i.e.
	  // it might be a 'new database structure' case)
	  if( $aValue1['sValue'] && (! valueExists($aValue1['sValue'],
						   $aValues2)) ) {
	    foreach($aValues2 as $aValue2) {
	      $sAnalyzedWordFormId_DerivationId = 
		getAnalysedWordformIdDerivationId($aValue2['iWordformId'],
						  $aValue1['aValue'],
						  $aValue1['sValue']);

	      insertTokenAttestation($sKey2,$sAnalyzedWordFormId_DerivationId);
	    }
	  }
	}
      }
    }
  }
  $aGroup = array(); // Empty array
}

function valueExists($sValue, $aValueArr) {
  foreach($aValueArr as $aValue)
    if($aValue['sValue'] == $sValue)
      return true;
  return false;
}

function insertTokenAttestation($sKey, $sAnalyzedWordFormId_DerivationId) {
  // Key: documentId, onset, offset
  $sInsertQuery = "INSERT INTO token_attestations" .
    "(analyzed_wordform_id, derivation_id, document_id, start_pos, end_pos) " .
    "VALUES ($sAnalyzedWordFormId_DerivationId, $sKey) " .
    "ON DUPLICATE KEY UPDATE attestation_id = attestation_id";
  doNonSelectQuery($sInsertQuery);
}

function getAnalysedWordformIdDerivationId($iWordFormId, $aValue, $sValue) {
  // aValue: [lemmaId, derivationId, multipleLemmataAnalysisId]

  // First check if it exists already
  $sAnalyzedWordFormId_DerivationId =
    getExistsingAnalyzedWordform($iWordFormId, $aValue);
  if( $sAnalyzedWordFormId_DerivationId)
    return $sAnalyzedWordFormId_DerivationId;

  // Not found, so do an insert
  // All verified by user 'Tom' (user_id 50)
  $sInsertQuery = "INSERT INTO analyzed_wordforms" .
    "(wordform_id, lemma_id, derivation_id, multiple_lemmata_analysis_id," .
    " verified_by, verification_date) VALUES " .
    "($iWordFormId, $sValue, 50, NOW())";
  doNonSelectQuery($sInsertQuery);

  // And try again
  return getExistsingAnalyzedWordform($iWordFormId, $aValue);
}

function getExistsingAnalyzedWordform($iWordFormId, $aValue) {
  $sSelectQuery =
    "SELECT analyzed_wordform_id, derivation_id" .
    "  FROM analyzed_wordforms " .
    " WHERE wordform_id = $iWordFormId" .
    "   AND lemma_id = $aValue[0]" .
    "   AND derivation_id = $aValue[1]" .
    "   AND multiple_lemmata_analysis_id = $aValue[2]";
  
  $sAnalyzedWordFormId_DerivationId = false;
  if( ($oResult = doSelectQuery($sSelectQuery)) ) {
    if( ($aRow = mysql_fetch_assoc($oResult)) )
      $sAnalyzedWordFormId_DerivationId = 
	$aRow['analyzed_wordform_id'] . ", " . $aRow['derivation_id'];
    mysql_free_result($oResult);
  }
  return $sAnalyzedWordFormId_DerivationId;
}

// Query which gives analyses per group member
//
function getSelectQuery() {
  return
    "SELECT wordform_groups.wordform_group_id, wordform_groups.document_id, ".
    "       wordform_groups.onset, wordform_groups.offset,tokens.wordform_id,".
    "       wfAnalyses.analyzed_wordform_id, wfAnalyses.lemma_id," .
    "       wfAnalyses.derivation_id, wfAnalyses.multiple_lemmata_analysis_id".
    "  FROM lexiconToolTokenDb.tokens, wordform_groups" .
    "  LEFT JOIN (SELECT wordform_groups.wordform_group_id," .
    "                    wordform_groups.document_id," .
    "                    wordform_groups.onset," .
    "                    wordform_groups.offset," .
    "                    analyzed_wordforms.analyzed_wordform_id," .
    "                    analyzed_wordforms.lemma_id," .
    "                    analyzed_wordforms.derivation_id," .
    "                    analyzed_wordforms.multiple_lemmata_analysis_id" .
    "               FROM token_attestations, analyzed_wordforms," .
    "                    wordform_groups" .
    "              WHERE wordform_groups.document_id" .
    "                           = token_attestations.document_id" .
    "                AND wordform_groups.onset = token_attestations.start_pos".
    "                AND wordform_groups.offset = token_attestations.end_pos" .
    "                AND token_attestations.analyzed_wordform_id" .
    "                   = analyzed_wordforms.analyzed_wordform_id) wfAnalyses".
    "       ON (wfAnalyses.wordform_group_id" .
    "                  = wordform_groups.wordform_group_id AND" .
    "           wfAnalyses.document_id = wordform_groups.document_id AND" .
    "           wfAnalyses.onset = wordform_groups.onset)" .
    " WHERE tokens.document_id = wordform_groups.document_id" .
    "   AND tokens.onset = wordform_groups.onset" .
    " ORDER BY wordform_groups.wordform_group_id," .
    "          wfAnalyses.analyzed_wordform_id DESC";
}

?>