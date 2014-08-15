<?php

require_once('./lexiconToolBox.php');

chooseDb($_REQUEST['sDatabase']);

deleteLemma($_REQUEST['iLemmaId']);

?>