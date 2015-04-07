#!/usr/bin/perl -w

use strict;

use Getopt::Std;

# For printing if necessary
binmode(STDOUT, ":encoding(utf8)");

my $sFile = shift;
my $iOnset = shift;
my $iOffset = shift;

my $sHelptext = <<HELP_TEXT;

 $0 FILE ONSET OFFSET

HELP_TEXT

die $sHelptext unless($iOffset);

open(FH_INPUT, "<$sFile") or die "Couldn't open $sFile for reading: $!\n";
binmode(FH_INPUT, ":encoding(utf8)");

undef $/; #Slurp mode
my $sFileText = <FH_INPUT>;
print substr($sFileText, $iOnset, $iOffset - $iOnset) . "\n";

close(FH_INPUT);
