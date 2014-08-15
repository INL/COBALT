<?php

require_once('lexiconToolBox.php');

chooseDb($_REQUEST['sDatabase']);

updateTokenAttsForCorpus($_REQUEST['iWordFormId'], $_REQUEST['sMode'],
			 $_REQUEST['iId']);

?>