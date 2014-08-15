<?php

require_once('./lexiconToolBox.php');

chooseDb($_REQUEST['sDatabase']);

fillTextAttestations($_REQUEST['iDocumentId'], $_REQUEST['iWordFormId']);

?>