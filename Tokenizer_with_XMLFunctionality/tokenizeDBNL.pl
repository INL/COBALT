#!/usr/bin/perl -w

use strict;

my $sDbnlDir = "/mnt/Archief/Projecten/Impact/NELexicon/historisch/dbnl/" .
  "alleteksten/5_uitTool_metLOCLemmata_ronde2";
my $sOutputDir = "./outputDBNL";

my @aSubDirs = ("1755-1815", "1815-1900");
foreach my $sSubDir( @aSubDirs ) {
  opendir(my $dhDir, "$sDbnlDir/$sSubDir")
    or die "can't opendir'$sDbnlDir/$sSubDir': $!";
  my @aDbnlFiles = grep { /\.fixed$/ } readdir($dhDir);
  closedir($dhDir);

  foreach my $sDbnlFile (@aDbnlFiles) {
    print "Doing $sDbnlDir/$sSubDir/$sDbnlFile.\n";
    system("./tokenizeXML.pl" .
	   " -l ned" .
	   " -o $sOutputDir/$sSubDir/${sDbnlFile}_tokenized.tab" .
	   " $sDbnlDir/$sSubDir/$sDbnlFile");
  }
}
