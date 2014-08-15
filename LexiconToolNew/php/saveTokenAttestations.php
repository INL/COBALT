<?php

require_once('./lexiconToolBox.php');

chooseDb($_REQUEST['sDatabase']);

$sValue = rawurldecode($_REQUEST['sValue']);

printLog("Doing saveTokenAttestations(" . $_REQUEST['iUserId'] . ", " .
	 $_REQUEST['iId'] . ", '" . $_REQUEST['sMode'] . "', " .
	 $_REQUEST['iWordFormId'] . ", '" . $_REQUEST['sSelected'] . "', '" .
	 $sValue . "', " . $_REQUEST['iRowNr'] . ")\n");

saveTokenAttestations($_REQUEST['iUserId'], $_REQUEST['iId'],
		      $_REQUEST['sMode'], $_REQUEST['iWordFormId'],
		      $_REQUEST['sSelected'], $sValue, $_REQUEST['iRowNr'],
		      $_REQUEST['bVerify']);

?>