#!/usr/bin/perl -w

use strict;

# E.g say:
#
# $ ./xmlParser.pl Example testExample.xml

use xmlParser::xmlParser;

my $sFormat = shift;
my $sFileName = shift;

die "\n $0 FORMAT XML_FILE\n\n" unless($sFileName);

binmode(STDOUT, "utf8");

# Here we generate the string that just says:
#
# use xmlParser::EventHandler::myFormat.pm
#
# for whatever the user specified as 'myFormat'.
#
eval "use xmlParser::EventHandler::$sFormat;";
die "ERROR couldn't load xmlParser::EventHandler::$sFormat.pm: " . $@ if $@;

my $oXmlParser;

# Here we generate the string that does the equivalent to:
#
# my $oEventHandler = xmlParser::EventHandler::myFormat->new();
#
# $oXmlParser = xmlParser::xmlParser->new();
# $oXmlParser->{oEventHandler} = $oEventHandler;
#
eval "\$oXmlParser = " .
  "xmlParser::xmlParser->new( oEventHandler => " .
  "xmlParser::EventHandler::$sFormat->new() )";

die "ERROR while loading event handler: " . $@ if $@;

$oXmlParser->setInputFileHandle($sFileName);
# parseFile() also closes the file handle at the end
$oXmlParser->parseFile();
