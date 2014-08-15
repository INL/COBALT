<?php

require_once('./lexiconToolBox.php');

chooseDb($_REQUEST['sDatabase']);

deleteAnalysis($_REQUEST['iWordFormId'], $_REQUEST['iAnalyzedWordFormId']);

?>