#!/usr/bin/perl -w

use strict;

my $sHelp = <<HELP;

 $0 FILE [FILE2 [FILE3 [...]]]

HELP

die $sHelp unless(scalar(@ARGV));

foreach my $sInputFile (@ARGV) {
  open(FH_INPUT, "<:encoding(UTF8)", $sInputFile)
    or die "Couldn't open '$sInputFile' for reading: $!\n";
  open(FH_TMP, ">:encoding(UTF8)", "tmp.txt")
    or die "Couldn't open 'tmp.txt' for writing: $!\n";

  while( <FH_INPUT> ) {
    s/[\r\n]+$//; # Chomp
    if( /^[^\t]+\t[^\t]+\t[^\t]+\t[^\t]+$/ ) {
      print FH_TMP "$_\t\n"; # Add a tab
    }
    elsif( /^[^\t]+\t[^\t]+\t[^\t]+\t[^\t]+\tisNotAWordformInDb$/ ) {
      print FH_TMP "$_\n"; # Leave as is
    }
    else {
      die "ERROR in $sInputFile: line '$_' doesn't match.\n";
    }
  }

  close(FH_INPUT);
  close(FH_TMP);

  system("mv tmp.txt $sInputFile");
}
