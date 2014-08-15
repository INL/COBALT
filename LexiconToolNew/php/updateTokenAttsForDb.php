<?php

require_once('./lexiconToolBox.php');

chooseDb($_REQUEST['sDatabase']);

updateTokenAttsForDb($_REQUEST['iWordFormId'], $_REQUEST['iRowNr']);

?>