<?php

require_once('lexiconToolBox.php');

chooseDb($_REQUEST['sDatabase']);

removeFileFromCorpus($_REQUEST['iCorpusId'], $_REQUEST['iDocumentId']);