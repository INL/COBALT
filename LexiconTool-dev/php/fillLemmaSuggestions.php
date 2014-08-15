<?php

require_once('lexiconToolBox.php');

chooseDb($_REQUEST['sDatabase']);

fillLemmaSuggestions($_REQUEST['sMenuMode'],rawurldecode($_REQUEST['sValue']));

?>