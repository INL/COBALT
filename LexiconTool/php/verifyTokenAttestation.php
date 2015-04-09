<?php

require_once('lexiconToolBox.php');

chooseDb($_REQUEST['sDatabase']);

verifyTokenAttestation(
		       $_REQUEST['sSelecteds'],
		       $_REQUEST['iWordformId'],
		       $_REQUEST['iNewValue'], $_REQUEST['iUserId'] );

?>
