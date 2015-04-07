<?php

require_once('lexiconToolBox.php');

chooseDb($_REQUEST['sDatabase']);

printLog("tokenAttest(" .$_REQUEST['iUserId'] . ", " .
	 $_REQUEST['iWordFormId'] . ", " . $_REQUEST['iAnalyzedWordFormId'] .
	 ", '" . $_REQUEST['sSelecteds'] . "', '" . $_REQUEST['sClassName'] .
	 "')\n");

tokenAttest($_REQUEST['iUserId'], 
	    $_REQUEST['iWordFormId'], $_REQUEST['iAnalyzedWordFormId'],
	    $_REQUEST['sSelecteds'],
	    $_REQUEST['sClassName']
	    );

?>