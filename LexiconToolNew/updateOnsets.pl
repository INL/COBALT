#!/usr/bin/perl -w

# Extreem ad hoc!

use strict;

my $iVanafOnset = 1044;
my $iAlterOnset = 0;
my $iAlterCoords = 1;

my $bChange = undef;
while( <> ) {
  chomp;
  # 0          1      2         3          4           5     6     7     8
  # can wf<TAB>wf<TAB>onset<TAB>offset<TAB>notInDb<TAB>x<TAB>y<TAB>w<TAB>h
  my @aCols = split(/\t/);

  if( (! $bChange) && ($aCols[2] == $iVanafOnset) ) {
    $bChange = 1;
  }
  if($bChange) {
    print $aCols[0] . "\t" . $aCols[1] . "\t" .
      # Onset - offset -> change
      ($aCols[2] + $iAlterOnset) . "\t" . ($aCols[3] + $iAlterOnset) . "\t" .
	$aCols[4] . "\t" . $aCols[5] . "\t" . $aCols[6] . "\t" .
	  # last two coordinates -> unchange
	  ($aCols[7] - $iAlterCoords) . "\t" .($aCols[8] - $iAlterCoords)."\n";
  }
  else {
    print "$_\n";
  }
}
