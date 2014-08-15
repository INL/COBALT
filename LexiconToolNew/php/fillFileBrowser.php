<?php

require_once('lexiconToolBox.php');

chooseDb($_REQUEST['sDatabase']);

$iCorpusAddedTo = isset($_REQUEST['iCorpusAddedTo']) ?
    $_REQUEST['iCorpusAddedTo'] : 0;

fillFileBrowser($_REQUEST['sDatabase'], $_REQUEST['iUserId'],
		$_REQUEST['sUserName'], $iCorpusAddedTo);