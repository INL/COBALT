#!/usr/bin/perl -w

use strict;

my $sDpoDir = "/tmp/DPOfile";
my $sOutputDir = "./outputDPO";

opendir(my $dhDir, $sDpoDir) || die "can't opendir $sDpoDir: $!";
my @aDpoFiles = grep { /\.xml$/ } readdir($dhDir);
closedir($dhDir);

foreach my $sDpoFile (@aDpoFiles) {
  print "Doing $sDpoDir/$sDpoFile.\n";
  system("./tokenizeXML.pl" .
	 " -l ned" .
	 " -o $sOutputDir/${sDpoFile}_tokenized.tab" .
	 " $sDpoDir/$sDpoFile");
}
