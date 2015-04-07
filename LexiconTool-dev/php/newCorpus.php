<?php

require_once('lexiconToolBox.php');

chooseDb($_REQUEST['sDatabase']);

// We don't check whether the right variables are provided for.
// They just should be there.

newCorpus($_REQUEST['sDatabase'], $_REQUEST['sNewCorpusName']);


?>