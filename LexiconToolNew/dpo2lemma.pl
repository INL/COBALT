#!/usr/bin/perl -w

# Dit script leest getokeniseerde tekst in en maakt lemmata van alle getagde
# namen.
#
# Het idee is dat de teksten al in de Lexicon Tool geladen zijn, en dat alle
# woordvormen dus al bestaan.
#
# Het is Snel & Smerig dus bij de volgende keer weer even kijken of het wel
# allemaal goed gaat.

use strict;
use Getopt::Std;
use DBI;

our($opt_d );
getopts('d:');

my $sHelpText = <<HELP;

 $0 -d DATABASE FILE [FILE2 ...]

 -d DATABASE
    Database that the Lexicon Tool uses.

HELP

die $sHelpText unless($opt_d && scalar(@ARGV));

my $sHost = "impactdb";  #"localhost";
my $sUser = "impact";
my $sPassword = "impact";

my $dbh = DBI->connect("dbi:mysql:$opt_d:$sHost:3306", $sUser, $sPassword,
		       {RaiseError => 1});
$dbh->{'mysql_enable_utf8'} = 1;
$dbh->do('SET NAMES utf8');

my $sImageDir =
  "http://impactdb.inl.loc/Archief/Projecten/Impact/Data/DPO35PNG_Kleur";

foreach(@ARGV) {
  print "Doing $_\n";
  open(FH_FILE, "< $_") or die "Couldn't open $_ for reading: $!\n";

  handleFile($_);

  close(FH_FILE);
}

$dbh->disconnect();

# SUBS ########################################################################

sub handleFile {
  my ($sFileName) = @_;

  my $iDocId = getDocId($sFileName);
  die "No document id for $sFileName\n" unless($iDocId);

  my @aNames;
  my $sGroupId = 'first';
  my $iPartNr = 0;
  my $bInTag = undef;
  while( <FH_FILE> ) {
    if( /^([^\t]+)\t[^\t]*<(NE_PERS|NE_LOC|NE_ORG)(_gid=\w+| part="(\d+)")?[^>]*>([^\t]+)?\t(\d+)\t(\d+)$/sg ) {
      # De namen kunnen op twee manieren gegroepeerd zijn. met dezelfde
      # groep identifier (bijvoorbeeld: gid="3") of met een oplopend deelnummer
      # (bijvoorbeeld: part="3").
      roundUp($iDocId, \@aNames)
	unless( $3 && ( ( (substr($3, 0, 1) eq '_') && ($sGroupId eq $3) )
			||
			( $4 && ($4 == ($iPartNr+1)) ) ) );
      if( $3 ) {
	if( substr($3, 0, 1) eq '_') {
	  $sGroupId = $3;
	}
	else {
	  $iPartNr = $4;
	}
      }
      #print "Name: $1 $sGroupId, $5 - $6\n\t($_)\n";
      push(@aNames, {name => $1, onset => $6, offset => $7, pos => $2});
      $bInTag = ( $5 && ($5 =~ /<\/NE>/) ) ? undef : 1;
    }
    elsif( $bInTag ) {
      if( /^([^\t]+)\t[^\t]+\t(\d+)\t(\d+)$/ ) {
	# print "Name: $1, (in tag) $2 - $3\n\t($_)\n";
	push(@aNames, {name => $1, onset => $2, offset => $3});
      }
      else {
	print "ERROR at line: $_\n";
      }
      $bInTag = undef if( /.+<\/NE>/ );
    }
    else {
      roundUp($iDocId, \@aNames);
    }
  }
  roundUp($iDocId, \@aNames);
}

sub roundUp {
  my ($iDocId, $arNames) = @_;

  my $iNrOfNames = scalar(@$arNames);
  if( $iNrOfNames == 0) {
    return;
  }
  elsif( $iNrOfNames == 1) {
    print "Single name: ";
    addSingleName($iDocId, $arNames->[0]);
  }
  else {
    print "Multiple name: ";
    addMultipleName($iDocId, $arNames);
  }
  for( @$arNames) {
    print "$_->{name}, $_->{onset}, $_->{offset} ";
  }
  print "\n";
  @$arNames = ();
}

sub addSingleName {
  my ($iDocId, $hrName) = @_;

  my $sQuotedName = $dbh->quote($hrName->{name});

  my $iLemmaId = addLemma($sQuotedName, $hrName->{pos});
  die "No lemma id for $sQuotedName" unless($iLemmaId);
  my $iAnalyzedWordFormId = addAnalyzedWordFormId($sQuotedName, $iLemmaId);
  die "No analyzed word form id $sQuotedName" unless($iAnalyzedWordFormId);

  addTokenAttestation($iDocId, $iAnalyzedWordFormId, $hrName->{onset},
		      $hrName->{offset});
}

sub addMultipleName {
  my ($iDocId, $arNames) = @_;

  my $iWordFormGroupId = undef;

  my $sName = makeName($arNames);
  my $iLemmaId = addLemma($dbh->quote($sName), $arNames->[0]->{pos});
  for (@$arNames) {
    my $sQuotedName = $dbh->quote($_->{name});
    my $iAnalyzedWordFormId = addAnalyzedWordFormId($sQuotedName, $iLemmaId);
    addTokenAttestation($iDocId, $iAnalyzedWordFormId, $_->{onset},
			$_->{offset});
    # Just do this once
    $iWordFormGroupId = getNewWordFormGroupId($iDocId,$_->{onset}, $_->{offset})
      unless($iWordFormGroupId);
    # And insert
    addToWordFormGroup($iWordFormGroupId, $iDocId, $_->{onset}, $_->{offset});
  }
}

sub makeName {
  my ($arNames) = @_;

  my ($sName, $sSpace) = ('', '');
  for ( @$arNames) {
    $sName .= $sSpace . $_->{name};
    $sSpace = ' ';
  }
  return $sName;
}

sub getDocId {
  my ($sFileName) = @_;

  my $iDocId = undef;

  $sFileName =~ s/^.+\///;

  my $sSelectQuery = "SELECT document_id FROM documents " .
    "WHERE title LIKE '%$sFileName'";
  my $qhSelectQuery = $dbh->prepare($sSelectQuery);
  $qhSelectQuery->execute();
  my $hrRow;
  if( $hrRow = $qhSelectQuery->fetchrow_hashref()) {
    $iDocId = $hrRow->{document_id};
  }
  $qhSelectQuery->finish();

  # EXTRA #
  # We zetten er ook een image locatie in
  $sFileName =~ s/\.xml_tokenized.tab$/_master.png/i;
  my $sUpdateQuery =
    "UPDATE documents SET image_location = '$sImageDir/$sFileName'" .
      " WHERE document_id = $iDocId";
  $dbh->do($sUpdateQuery);

  return $iDocId;
}

sub addLemma {
  my ($sQuotedName, $sPos) = @_;
  my $iLemmaId = undef;

  my $sInsertQuery = 
    "INSERT INTO lemmata (modern_lemma, lemma_part_of_speech, gloss, ne_label)".
      " VALUES($sQuotedName, '$sPos', NULL, 'NE') " .
	"ON DUPLICATE KEY UPDATE lemma_id = lemma_id";
  $dbh->do($sInsertQuery);

  my $sSelectQuery = "SELECT lemma_id FROM lemmata " .
    "WHERE modern_lemma = $sQuotedName" .
      " AND lemma_part_of_speech = '$sPos' AND gloss IS NULL AND ne_label = 'NE'";
  my $qhSelectQuery = $dbh->prepare($sSelectQuery);
  $qhSelectQuery->execute();
  my $hrRow;
  if( $hrRow = $qhSelectQuery->fetchrow_hashref()) {
    $iLemmaId = $hrRow->{lemma_id};
  }
  $qhSelectQuery->finish();

  return $iLemmaId;
}

sub addAnalyzedWordFormId {
  my ($sQuotedName, $iLemmaId) = @_;
  my $iAnalyzedWordFormId = undef;

  my $iWordFormId = getWordFormId($sQuotedName);
  my $sInsertQuery = 
    "INSERT INTO analyzed_wordforms (lemma_id, wordform_id)".
      " VALUES($iLemmaId, $iWordFormId) " .
	"ON DUPLICATE KEY UPDATE analyzed_wordform_id = analyzed_wordform_id";
  $dbh->do($sInsertQuery);

  my $sSelectQuery = "SELECT analyzed_wordform_id FROM analyzed_wordforms " .
    "WHERE lemma_id = $iLemmaId AND wordform_id = $iWordFormId";
  my $qhSelectQuery = $dbh->prepare($sSelectQuery);
  $qhSelectQuery->execute();
  my $hrRow;
  if( $hrRow = $qhSelectQuery->fetchrow_hashref()) {
    $iAnalyzedWordFormId = $hrRow->{analyzed_wordform_id};
  }
  $qhSelectQuery->finish();

  return $iAnalyzedWordFormId;
}

sub getWordFormId {
  my ($sQuotedName) = @_;

  my $iWordFormId = undef;

  my $sSelectQuery =
    "SELECT wordform_id FROM wordforms WHERE wordform = $sQuotedName";
  my $qhSelectQuery = $dbh->prepare($sSelectQuery);
  $qhSelectQuery->execute();
  my $hrRow;
  if( $hrRow = $qhSelectQuery->fetchrow_hashref()) {
    $iWordFormId = $hrRow->{wordform_id};
  }
  $qhSelectQuery->finish();

  return $iWordFormId;
}

sub addTokenAttestation {
  my ($iDocId, $iAnalyzedWordFormId, $iOnset, $iOffset) = @_;

  my $sInsertQuery = "INSERT INTO token_attestations " .
    "(analyzed_wordform_id, document_id, start_pos, end_pos) ".
      "VALUES($iAnalyzedWordFormId, $iDocId, $iOnset, $iOffset) " .
	"ON DUPLICATE KEY UPDATE attestation_id = attestation_id";
  $dbh->do($sInsertQuery);
}

sub getNewWordFormGroupId {
  my ($iDocId, $iOnset, $iOffset) = @_;

  my $iWordFormGroupId = undef;

  my $sSelectQuery = "SELECT " .
    "IF(MAX(wordform_group_id) IS NULL, 1, MAX(wordform_group_id) + 1) " .
      "wordformGroupId FROM wordform_groups";

  my $qhSelectQuery = $dbh->prepare($sSelectQuery);
  $qhSelectQuery->execute();
  my $hrRow;
  if( $hrRow = $qhSelectQuery->fetchrow_hashref()) {
    $iWordFormGroupId = $hrRow->{wordformGroupId};
  }
  $qhSelectQuery->finish();

  return $iWordFormGroupId;
}

sub addToWordFormGroup {
  my ($iWordFormGroupId, $iDocId, $iOnset, $iOffset) = @_;

  my $sInsertQuery = "INSERT INTO wordform_groups " .
    "(wordform_group_id, document_id, onset, offset)" .
      " VALUES($iWordFormGroupId, $iDocId, $iOnset, $iOffset) ";
  # DUPLICATE KEY niet nodig als het goed is...
  $dbh->do($sInsertQuery);
}
