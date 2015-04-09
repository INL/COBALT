<?php

require_once('lexiconToolBox.php');

chooseDb($_REQUEST['sDatabase']);

// Convert to boolean again
$bInGroup = ($_REQUEST['bInGroup'] == 'false') ? false : true;

printLog("toggleGroup(" . $_REQUEST['iUserId'] . ", " .
	 $_REQUEST['iDocumentId'] . ", " . $_REQUEST['iWordFormId'] . ", " .
	 $_REQUEST['iSentenceNr'] . ", " .
	 $_REQUEST['iHeadWordOnset'] . ", " . $_REQUEST['iHeadWordOffset'] .
	 ", " . $_REQUEST['iOnset'] . ", " . $_REQUEST['iOffset'] .
	 ", $bInGroup)\n");

toggleGroup($_REQUEST['iUserId'], $_REQUEST['iDocumentId'],
	    $_REQUEST['iWordFormId'], $_REQUEST['iSentenceNr'], 
	    $_REQUEST['iHeadWordOnset'], $_REQUEST['iHeadWordOffset'],
	    $_REQUEST['iOnset'], $_REQUEST['iOffset'], $bInGroup);

?>