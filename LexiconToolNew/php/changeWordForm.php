<?php

require_once('./lexiconToolBox.php');

chooseDb($_REQUEST['sDatabase']);

printLog("changeWordForm(" . $_REQUEST['iUserId'] . ", " .
	 $_REQUEST['iOldWordFormId'] . ", '" . $_REQUEST['sOldWordForm'] .
	 "', '" . $_REQUEST['sNewWordForm'] . "', '" .
	 $_REQUEST['sSelectedSentences'] . "')\n");

changeWordForm($_REQUEST['iUserId'], $_REQUEST['iOldWordFormId'],
	       $_REQUEST['sOldWordForm'], $_REQUEST['sNewWordForm'],
	       $_REQUEST['sSelectedSentences']);

?>