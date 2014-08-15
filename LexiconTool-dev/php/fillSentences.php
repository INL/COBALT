<?php

require_once('lexiconToolBox.php');

chooseDb($_REQUEST['sDatabase']);

$sSelectedSentences = isset($_REQUEST['sSelectedSentences']) ?
 $_REQUEST['sSelectedSentences'] : '';

// Convert too boolean again if necassary
$sSortSentencesBy =
($_REQUEST['sSortSentencesBy'] == 'false') ? false
: $_REQUEST['sSortSentencesBy'];
$sSortSentencesMode = ($_REQUEST['sSortSentencesMode'] == 'false') ? false
: $_REQUEST['sSortSentencesMode'];

printLog("fillSentences('" . $_REQUEST['sDatabase'] . "', " .
	 $_REQUEST['iId'] . ", '" . $_REQUEST['sMode'] . "', '" .
	 urldecode($_REQUEST['sWordForm']) . "', " . $_REQUEST['iWordFormId'] .
	 ", '$sSelectedSentences', " . $_REQUEST['iUserId'] . ", " . 
	 $_REQUEST['iAmountOfContext'] . ", '$sSortSentencesBy', " .
	 "'$sSortSentencesMode')\n");

fillSentences($_REQUEST['sDatabase'], $_REQUEST['iId'], $_REQUEST['sMode'],
	      urldecode($_REQUEST['sWordForm']), $_REQUEST['iWordFormId'],
	      $sSelectedSentences, $_REQUEST['iUserId'],
	      $_REQUEST['iNrOfSentencesPerWordform'],
	      $_REQUEST['iStartAtSentence'],
	      $_REQUEST['iAmountOfContext'], $sSortSentencesBy,
	      $sSortSentencesMode);

?>