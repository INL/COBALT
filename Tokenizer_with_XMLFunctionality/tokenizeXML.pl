#!/usr/bin/perl -w

use strict;
use Getopt::Std;

use xmlParser::xmlParser;

# NOTE that we never 'die' as that will print to stderr which the
# Lexicon Tool's php scripts won't print.

my $sHelpText = <<HELP_TEXT;

 $0 [-d DATABASE] [-l LANGUAGE] [-o OUTPUT_FILE] [-t TEST_PACKAGE] XML_FILE

 Reads XML input and outputs tokenized file that can be used for e.g. the
 Lexicon Tool.

 -d DATABASE
    When handling e.g. .fixed files, token attestations can be added to a
    database.
 -l LANGUAGE
    Depends on the setting the external tokenizer module needs (e.g. for
    impactok, which is  used for PageXML, it can be 'esp', 'ned', etc.).
 -o OUTPUT_FILE
    File the output is written.
    If not provided output is to stdout.
 -v
    Validate XML.
    Usually XML is not validated at all, which makes it possible to also handle
    XML-ish texts. By specifying this option xmllint is called. When it gives 
    errors these are presented and this program stops.
 -t TEST_PACKAGE
    For testing purposes.
    Use xmlParser::EvantHandler::TEST_PACKAGE.pm.
    If this option is not used (which is usually the case) the format is
    deduced from the input file.

HELP_TEXT

our ($opt_d, $opt_l, $opt_o, $opt_t, $opt_v);
getopts('d:l:o:t:v');

my $sFileName = shift;

endProgram($sHelpText) unless($sFileName);

# If necessary, first validate
validateXml($sFileName) if( $opt_v);

my $oXmlParser = xmlParser::xmlParser->new();

$oXmlParser->setInputFileHandle($sFileName, "UTF-8");
$oXmlParser->determineXmlFormat($sFileName, $opt_t);

# NOTE that we only include the relevant event handler
my $sEventHandler = "xmlParser::EventHandler::" . $oXmlParser->{sXmlFormat};
eval "use $sEventHandler;";
endProgram("ERROR while handling file '$sFileName': " . $@) if $@;
my $sLanguagePart = ($opt_l) ? "sLanguage => '$opt_l'" : '';
eval "\$oXmlParser->{oEventHandler} = $sEventHandler->new($sLanguagePart);";
endProgram("ERROR while handling file '$sFileName': " . $@) if $@;

# When it is necessary, we call some extra functions having to
# do with impactok.
# We assume that we are in the right folder for the impactok files...
if( exists($oXmlParser->{oEventHandler}->{oImpactok}) ) {
  $oXmlParser->{oEventHandler}->{oImpactok}->setAbbreviations(".");
  $oXmlParser->{oEventHandler}->{oImpactok}->setApostrophes(".");
}

if( $opt_o ) {
  open (FH_OUT, ">:encoding(utf8)", $opt_o) or
    endProgram("ERROR while handling file '$sFileName': " .
	       "Couldn't open output file $opt_o: $!\n");
}
else { # Redirect to stdout
  open(FH_OUT, ">&STDOUT") or
    endProgram("ERROR while handling file '$sFileName': " .
	       "Couldn't tie to standard output: $!\n");
  binmode(FH_OUT, "utf8");
}
# Make the event handler print to the right stream.
$oXmlParser->{oEventHandler}->setOutputFileHandle(*FH_OUT);
# If necessary, initialise a database connection, etc.
$oXmlParser->{oEventHandler}->initDatabase($opt_d) if($opt_d);

# Works from the input file handle if it is defined already
$oXmlParser->parseFile();

# Close everyting.
# The input file needs no closing as it is closed at the end of parseFile().
close(FH_OUT) if($opt_o);
$oXmlParser->{oEventHandler}->closeDatabase($opt_d) if($opt_d);

# Subs ########################################################################

sub validateXml {
  my ($sFileName) = @_;

  # This is done with open because somehow backticks wouldn't allow
  # redirecting stderr...
  open(FH_EXEC, "xmllint --noout $sFileName 2>&1 |")
    or endProgram("ERROR while handling file '$sFileName': " .
		  "Couldn't run /usr/bin/xmllint: $!\n");
  my $bDie = undef;
  while( <FH_EXEC> ) {
    # NOTE that we (ab)use the bDie flag to only print this once
    print "ERROR while handling file '$sFileName': " .
      "Can not validate XML\n" unless($bDie);
    print $_;
    $bDie = 1;
  }
  close(FH_EXEC);
  endProgram('') if($bDie);
}

# NOTE that we never 'die' as that will print to stderr which the
# Lexicon Tool's php scripts won't print.
sub endProgram {
  my ($sMessage) = @_;

  print $sMessage;
  exit;
}
