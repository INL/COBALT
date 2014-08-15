<table border=0>
<tr>
<?php

require_once('databaseUtils.php');

chooseDb($_REQUEST['sDatabase']);

$sUserName = isset($_REQUEST['sUserName']) ? $_REQUEST['sUserName'] : false;
$iUserId = isset($_REQUEST['iUserId']) ? $_REQUEST['iUserId'] : false;

if( ! $iUserId ) {
  print "You appear not to be logged in. Please do...";
  return;
}

$sSelectQuery = "SELECT document_id, title FROM documents";

$bPrintedSomething = false;
if( ($oResult = doSelectQuery($sSelectQuery)) ) {
  if( mysql_num_rows($oResult) > 0 ) {
    print "<td class=chooseCol>Choose file</td>";
    print "<td>\n<form id=fileForm action='./lexiconTool.php' " .
      "method='POST'>\n" .
      "<input type=hidden name=sUserName value='$sUserName'>\n" .
      "<input type=hidden name=iUserId value='$iUserId'>\n" .
      "<select name=sFile " .
      "        onChange='javascript: document.forms[\"fileForm\"].submit();'>".
      "\n <option value=0>&nbsp;</option>\n";
    while( ($oRow = mysql_fetch_assoc($oResult)) ) {
      // NOTE that the value is the id and the title with a TAB in between
      print ' <option value="' . $oRow['document_id'] . "\t" . $oRow['title'] .
	'">' . $oRow['title'] .
	"</option>\n";
      $bPrintedSomething = true;
    }
    print "</select>\n</form>\n</td>\n";
  }
  mysql_free_result($oResult);
}

if( ! $bPrintedSomething ) {
  print "<td>Didn't find any files in the database.</td>";
}
?>
</tr>
</table>