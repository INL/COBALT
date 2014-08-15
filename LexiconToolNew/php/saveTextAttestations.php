<?php

require_once('./lexiconToolBox.php');

chooseDb($_REQUEST['sDatabase']);

printLog("Doing saveTextAttestations('" . $_REQUEST['iUserId'] . ", " .
	 $_REQUEST['iId'] . "', '" . $_REQUEST['sMode'] . "', " .
	 $_REQUEST['iWordFormId'] . ", " . $_REQUEST['iRowNr'] . ", " .
	 rawurldecode($_REQUEST['sValue']) . ")\n");

saveTextAttestations($_REQUEST['iUserId'], $_REQUEST['iId'],
		     $_REQUEST['sMode'], $_REQUEST['iWordFormId'],
		     $_REQUEST['iRowNr'], rawurldecode($_REQUEST['sValue']));

?>