<?php

require_once('lexiconToolBox.php');

chooseDb($_REQUEST['sDatabase']);

removeCorpus($_REQUEST['iCorpusId']);

?>