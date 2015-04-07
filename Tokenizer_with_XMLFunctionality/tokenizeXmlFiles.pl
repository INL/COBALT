#!/usr/bin/perl -w

# Very small script to be able to tokenize an arbitrary number of files.

my $sHelpText = <<HELP_TEXT;

 $0 FILE1 [FILE2 [FILE3 [...]]]

 Simply calls tokenizeXml.pl on the files and writes to stdout.

HELP_TEXT

die $sHelpText unless(scalar(@ARGV));

foreach my $sFile (@ARGV) {
  system("./xmlParser.pl IGT2TXT $sFile");
}
