<?php

// Global variabels
// Most of these need to be set once at installation time to reflect the local
// environment

// Database connection
  //$sDbHostName = "localhost";
$sDbHostName = "lexicontool-db.inl.loc"; 
$sDbUserName = "lexitool";
$sDbPassword = "M2rk2Ork";

// There is a separate token database.
// It needs to have the same user name/password combinations as the lexicon database.
// This token database can be configured two ways:
//  1. All available lexicon databases use one single token database
//     In this case, this single token database must be declared in variable $sTokenDbName
//     ex: $sTokenDbName = 'some_token_dBname';
//  or:
//  2. Each available lexicon database uses its own token database
//     In this case, each token database must be declared in associative array $asTokenDbName
//     To do so, set the lexicon database name as a key, and the token database name as its value.
//     ex: $asTokenDbName['some_lexicon_dBname'] = 'some_token_dBname';

$sTokenDbName = 'dummy';
$asTokenDbName['LexiconTool_NL_BUB'] = 'TokenDb_NL_BUB';
$asTokenDbName['LexiconTool_GentseSpelen'] = 'TokenDb_GentseSpelen';


// Document root to save uploaded files to
// NOTE: NO SLASH at the end
$sDocumentRoot = "/mnt/Projecten/Taalbank/Toolbestanden/CoBaLT";
//$sDocumentRoot = "/mnt/Archief/Projecten/Impact/LexiconTool/uploadedDocuments-svowdb02"; // <-- Dev
//$sDocumentRoot = "/mnt/Archief/Projecten/Impact/LexiconTool/voorbeeldenIsabel/LexiconToolUploadedFiles";
//$sDocumentRoot = "/Users/mactommy/Sites/INL/uploadedDocuments"; // Thuis

// Next one used globally, which is why it is in this file.
// In general there is no need to change it however...
$sZipExtractDir = 'zipExtractDir';

// This variable should hold the absolute path the tokenizer is at.
// NOTE that there is no slash at the end...
$sTokenizerDir = '/mnt/Projecten/Impact/Tokenizer_metXMLFunctionaliteit';
// Thuis:
//$sTokenizerDir = "/Users/mactommy/Sites/INL/Tokenizer_metXMLFunctionaliteit";

// The tokenizer can do things differently depending on the language it deals
// with.
// NOTE that for the default IMPACT tokenizer the value stated here should
// appear exactly the same as extension to the abbr.LANG and apostrof.LANG
// files in the tokenizer directory (see above).
$sTokenizerLanguage = 'ned';
//$sTokenizerLanguage = 'esp';

// Under Ubuntu PHP is not permitted to execute external programs (like the
// tokenizer. Therefore it is necessary to include the user the web server
// runs as in the sudo'ers file and execute the tokenizer using 'sudo'.
// Just give the variable an empty string as value if this not required.
$sSudo = '';

// If the next variable is set to a true value, every lemma part of speech the
// the user types in is uppercased (unless the lemma in question already
// exists).
// So, a new lemma 'chair, Nou' will become 'chair, NOU'.
$bUppercaseLemmaPos = TRUE;

// There can be an additional check on the validity of any new analyses the
// user types in.
// Set this to 'true' (NOTE the quotes!) if this is desired. Set to 'false'
// otherwise.
// If set to 'true' a file called js/analysisFormatChecker.js is expected to
// exist which should contain a function called analysisFormatIsValid().
// This function should take a string as input and should return a boolean.
//$bCheckAnalysisFormatValidity = 'true';
$bCheckAnalysisFormatValidity = 'false';

// Normalize tokens when they are changed.
// In order for this to work there should be a file called
// php/normalizeToken.php that has a function in it called normalizeToken().
$bNormalizeToken = FALSE;

// A pop-up appears when someone wants to edit a word that has recently been
// edited by someone else. But what is recently...?!?
// Right now it is 5 minutes (300 seconds)
$iMaxLastViewedDiff1 = 300; // <-- In seconds
// Also when it is less recent, but still *rather* recent, the last view span
// is presented to the user in a more striking(/alarming) manner (colour)
// The next figure defines 'rather recent', which is an hour (3600 seconds)
$iMaxLastViewedDiff2 = 3600; // <-- In seconds




// BEGIN OF LOGGING PART

// If you want all the queries the application issues to be logged for a while,
// give this variable a value (an absolute path name).
// Otherwise, make it FALSE.
//
//          \\\\============================================////
//           |||       DON'T FORGET TO TURN THIS OFF        |||
//           ||| The log file VERY quickly becomes VERY big |||
//          ////============================================\\\\
//
//$sLogFile = "/mnt/Projecten/Impact/LexiconStructure/logs/log.txt";
$sLogFile = FALSE; "/vol1/sites/lexicontool.inl.loc/logs/cobalt.log"; // <-- Use this one to turn off logging

// if this is set, the application will only log information about this user
// (this is especially convenient when a tester only wants to see his/her own operations in the log file)
// set to FALSE if no distinctive logging is needed
$iTesterId = FALSE;

// END OF LOGGING PART




// This array shows as a dropdown list in the tool.
// If you want different values, alter here.
// You can use any (natural) number you want, but you should put them in
// quotes, as they are treated as strings.
// The only non-number you can use is 'all', which means... well, all!
$aNrOfWordFormsPerPage = array('10', '20', '50', '100', 'all');

// This array shows as a dropdown list in the tool.
// If you want different values, alter here.
// You can use any (natural) number you want, but you should put them in
// quotes, as they are treated as strings.
// The only non-number you can use is 'all', which means... well, all!
//
// NOTE that there a default (the first value) is also set in lexiconTool.php
$aNrOfSentencesPerWordform = array('10','15','100', '150', '250', '500', 'all');

// This is the next drop down list about the amount of context
// For the context a window of characters is taken in the >>tokenized<< files.
// The numbers in this list determine how big this window is.
// NOTE that there is no way of telling how many words this amounts to.
$aAmountOfContext = array('normal' => 220, 'a bit more' => 420,
			  'a lot more' => 820, 'truckload' => 1800);

// When you start typing in an analysis box a drop down will appear of the
// lemmata/analyses the already exist in the database.
// Here you can specify how many suggestions you will see at most.
// Do not set this too high, as this will slow things down and hence will lead
// to unexpected/irritating behaviour.
$iNrOfLemmaSuggestions = 20;

// These get filled with the relevant value at runtime
$aLanguages = FALSE;
$iTokenDbDatabaseId = FALSE;

// settings for possiple linking to UWAR djvu server
$bDisplayImageInDJVU = TRUE;
$sDJVULocation="http://kanji.klf.uw.edu.pl/LexTool-djvus/";

// setting for context sentences in quotation screen (new tab upon clicking on quote in lower screen part)
// set FALSE for sentences without tag information, or TRUE for sentences with tag information
$bFullAnalyses = TRUE;

// Allow editing wordforms in lower part of screen (upon click + CTRL)
$bAllowEditingWordforms = FALSE;

?>
