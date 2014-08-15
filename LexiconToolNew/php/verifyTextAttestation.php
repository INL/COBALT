<?php

require_once('./lexiconToolBox.php');

chooseDb($_REQUEST['sDatabase']);

verifyTextAttestation($_REQUEST['iId'], $_REQUEST['sMode'],
		      $_REQUEST['iWordformId'], $_REQUEST['iNewValue'],
		      $_REQUEST['iUserId']);

?>
