<?php

require_once('./php/lemmaTupleString2array.php');
require_once('./php/databaseUtils.php');

$sDatabase = "LexiconTool_testDb";
chooseDb($sDatabase);

$sLemmaTuple = "odeja, <odejo> Ncf";

$aLemmaArr = lemmaTupleString2array($sLemmaTuple, false, 1);

print "<p>\n";
print "lemma headword: '" . escape_brackets($aLemmaArr[0]) . "'<br>\n";
print "modern wordform: '$aLemmaArr[1]'<br>\n";
print "patterns: '";
if($aLemmaArr[2])
  print implode("', '", $aLemmaArr[2]);
print "'<br>\n";
print "pos: '$aLemmaArr[3]'<br>\n";
print "language_id: '$aLemmaArr[4]'<br>\n";
print "gloss: '$aLemmaArr[5]'<br>\n";

function escape_brackets($sString) {
  $sString = preg_replace("/</", "&lt;", $sString);
  $sString = preg_replace("/>/", "&gt;", $sString);
  return $sString;
}

?>