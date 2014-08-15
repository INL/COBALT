<?php

require_once('lexiconToolBox.php');

chooseDb($_REQUEST['sDatabase']);

// This is called on loading the main page to fill the attestations in the
// corpus/database per 100/so many rows

fillTokenAttestations($_REQUEST['iUserId'], $_REQUEST['sUserName'],
		      $_REQUEST['sMode'], $_REQUEST['iId'],
		      $_REQUEST['sWordFormIds']);

?>