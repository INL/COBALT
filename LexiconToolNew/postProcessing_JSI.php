<?php

require_once('./php/lexiconToolBox.php');

$GLOBALS['iUserid'] = 15; // Denk ik...

$sDatabase = 'lexiconTool_testDb';

// Rest van de settings komt uit globals.php
chooseDb($sDatabase);

// v'borsteh, etc.
splitWords("'");

// 180º, etc.
splitWords("º");

// Functions ///////////////////////////////////////////////////////////////////

function splitWords($cSplitChar) {
  $oResult = getWordsToSplit($cSplitChar);

  while( ($aRow = mysql_fetch_assoc($oResult)) ) {
    $aNewWordForms = preg_split("/$cSplitChar/", $aRow['wordform']);
    $aNewPosses = preg_split("/ & /", $aRow['analysis']);
    if( (count($aNewWordForms) == 2) &&
	(count($aNewPosses) == 2) ) {
      // Change the word forms. This does exactly the same as when a user would
      // have done it in the tool.
      changeWordForm($GLOBALS['iUserId'], $aRow['wordform_id'],
		     $aRow['wordform'],
		     $aNewWordForms[0] . "|" . $aNewWordForms[1],
		     $hrRow['tokenAtts']);

      // So now the words are split, but without analysis (the previous step
      // deleted anay analyses they had).
      // We know the onset/offsets however so we can just add the new analyses
      // per wordform.
      $aTypes = array();
      getWordFormIds("'" . $aNewWordForms[0] . "', '" . $aNewWordForms[1] . "'",
		     $aTypes);
          
      addTokenAttestations($GLOBALS['iUserId'],
			   $aTypes[$aNewWordForms[0]]['wordFormId'],
			   $hrRow['tokenAtts'], $aNewPosses[0],
			   1);
      addTokenAttestations($GLOBALS['iUserId'],
			   $aTypes[$aNewWordForms[1]]['wordFormId'],
			   $hrRow['tokenAtts'], $aNewPosses[1],
			   1);
    }
    else {
      print "ERROR: problem with '" . $aRow['wordform'] .
	"', part of speeches do not correspond '" . $aRow['analyses'] .
	". Skipping...\n";
    }
  }
  mysql_free_result($oResult);
}

function getWordFormIdsToSplit($cSplitChar) {
  $sWordFormIds;

  // Actually in the "'" case we are looking for words that start with a single
  // character followed by an '.
  $sSplitPattern = ($cSplitChar == "'") ? "'_''%'" : "'$cSplitChar'";

  $sSelectQuery =
   "SELECT GROUP_CONCAT(wordform_id) wordFormIds" .
    "  FROM wordforms"
    " WHERE wordform LIKE $sSplitPattern";

  if( ($oResult = doSelectQuery($sSelectQuery)) ) {
    if( ($aRow = mysql_fetch_assoc($oResult)) ) {
      $sWordFormIds = $aRow['wordFormIds'];
    }
    mysql_free_result($oResult);
  }
  else {
    print "ERROR: no results.\n";
    return '';
  }
}

function getWordsToSplit($cSplitChar) {
  $oResult;

  $sWordFormIds = getWordFormIdsToSplit($cSplitChar);

  $sSelectQuery = <<<SELECT_QUERY
SELECT analyzed_wordforms.wordform_id,
       token_attestations.document_id,
       token_attestations.start_pos,
       token_attestations.end_pos,
       GROUP_CONCAT(DISTINCT mla.mla SEPARATOR ' | ')
                                               multipleLemmataAnalysesInDocument
  FROM token_attestations, analyzed_wordforms
       LEFT JOIN (SELECT multiple_lemmata_analyses.multiple_lemmata_analysis_id, GROUP_CONCAT(CONCAT(lemmata.modern_lemma, IF(myPatterns.normalized_wordform IS NULL, '', CONCAT(', ', myPatterns.normalized_wordform)), IF(myPatterns.patterns IS NULL, '', CONCAT(', ', myPatterns.patterns)), ', ', lemmata.lemma_part_of_speech, IF(languages.language IS NULL, '', CONCAT(', ',languages.language))), IF(lemmata.gloss = '', '', CONCAT(', ', REPLACE(lemmata.gloss, ' ', ' '))) ORDER BY multiple_lemmata_analyses.part_number ASC SEPARATOR ' & ') mla FROM multiple_lemmata_analyses, multiple_lemmata_analysis_parts mlapartsOuter LEFT JOIN (SELECT multiple_lemmata_analyses.part_number, mlapartsInner.multiple_lemmata_analysis_part_id, CONCAT('<', normalized_form, '>') AS normalized_wordform, CONCAT('[', GROUP_CONCAT(CONCAT('(', left_hand_side, '_', right_hand_side, ', ', pattern_applications.position,')')), ']') AS patterns FROM analyzed_wordforms, multiple_lemmata_analyses, multiple_lemmata_analysis_parts mlapartsInner, derivations LEFT JOIN pattern_applications ON(pattern_applications.pattern_application_id = derivations.pattern_application_id) LEFT JOIN patterns ON (pattern_applications.pattern_id = patterns.pattern_id) WHERE mlapartsInner.derivation_id = derivations.derivation_id AND analyzed_wordforms.wordform_id IN ($sWordFormIds) AND analyzed_wordforms.multiple_lemmata_analysis_id = multiple_lemmata_analyses.multiple_lemmata_analysis_id AND multiple_lemmata_analyses.multiple_lemmata_analysis_part_id = mlapartsInner.multiple_lemmata_analysis_part_id GROUP BY mlapartsInner.multiple_lemmata_analysis_part_id) myPatterns ON (myPatterns.multiple_lemmata_analysis_part_id = mlapartsOuter.multiple_lemmata_analysis_part_id), analyzed_wordforms, token_attestations, lemmata LEFT JOIN languages ON (languages.language_id = lemmata.language_id) WHERE multiple_lemmata_analyses.multiple_lemmata_analysis_part_id = mlapartsOuter.multiple_lemmata_analysis_part_id AND token_attestations.analyzed_wordform_id = analyzed_wordforms.analyzed_wordform_id AND analyzed_wordforms.multiple_lemmata_analysis_id = multiple_lemmata_analyses.multiple_lemmata_analysis_id AND analyzed_wordforms.wordform_id IN ($sWordFormIds) AND mlapartsOuter.lemma_id = lemmata.lemma_id GROUP BY analyzed_wordforms.wordform_id, attestation_id, multiple_lemmata_analysis_id) mla ON (mla.multiple_lemmata_analysis_id = analyzed_wordforms.multiple_lemmata_analysis_id)
 WHERE analyzed_wordforms.wordform_id IN ($sWordFormIds)
   AND analyzed_wordforms.lemma_id = 0
   AND token_attestations.analyzed_wordform_id = analyzed_wordforms.analyzed_wordform_id
 GROUP BY analyzed_wordforms.wordform_id, token_attestations.document_id,
          token_attestations.start_pos
SELECT_QUERY

  if( ($oResult = doSelectQuery($sSelectQuery)) ) {
    return $oResult;
  }
  else {
    print "ERROR: no results.\n";
    exit(1);
  }
}

?>