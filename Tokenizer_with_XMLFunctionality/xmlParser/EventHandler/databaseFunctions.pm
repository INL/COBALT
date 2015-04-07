package xmlParser::EventHandler::databaseFunctions;

use strict;

use Encode;
use DBI;

# This package can be inherited from by EventHandlers that need database
# support.

# NOTE that every sub checks whether or not the database handler is set.
# This way, dependent package can also easily be called without database
# support while all the code stays relatively clean.

sub new {
  my ($class, %hOptions) = @_;

  my $self = \%hOptions;
  bless $self, $class;

  return $self;
}

sub initDatabase {
  my ($self, $sDatabase) = @_;

  $self->{dbh} =
    DBI->connect("dbi:mysql:$sDatabase:impactdb:3306", 'impact', 'impact',
		 {RaiseError => 1} );
  $self->{dbh}->{'mysql_enable_utf8'} = 1;
  $self->{dbh}->do('SET NAMES utf8');

  # Prepare SELECT queries
  $self->{qhSelectDocId} =
    $self->{dbh}->prepare("SELECT document_id FROM documents WHERE title = ?");
  $self->{qhSelectWordFormId} =
    $self->{dbh}->prepare("SELECT wordform_id FROM wordforms " .
			  "WHERE wordform = ?");
  $self->{qhGetNewWordFormGroupId} =
    $self->{dbh}->prepare("SELECT IF(MAX(wordform_group_id) IS NULL, " .
			  "1, MAX(wordform_group_id) + 1) newWfGroupId " .
			  "FROM wordform_groups");
}

sub closeDatabase {
  my ($self) = @_;

  $self->{dbh}->disconnect() if( exists($self->{dbh}) );
}

# This sub assumes that all the tokens in arTokens make up >>one<< name.
sub putLocTokensInDb {
  my ($self, $arTokens) = @_;

  # This next line is there so scripts can more easily support
  # database/non-database behaviour (simply, when $self->{dbh} exists there is
  # database support, otherwise there is not).
  return unless( exists($self->{dbh}) );

  # If the function is called with its own set of tokens, we use that
  # otherwise, we take the tokenizer's tokens.
  $arTokens = $self->{oImpactok}->{arTokens} unless(defined($arTokens));

  my $iWordFormGroupId = '';
  my $iNrOfTokens = scalar(@$arTokens);
  foreach my $arToken ( @$arTokens ) {
    if( $iNrOfTokens > 1) {
      unless(length($iWordFormGroupId)) {
	# Get a new word form group id if it is the first word of the group
	$iWordFormGroupId = $self->getNewWordFormGroupId();
      }
    }
    # onset => 0, offset => 1, iOriginalOnset => 2,  iOriginalOffset => 3,
    # word => 4, string => 5,  sOriginalToken => 6, iPosX => 7, iPosY => 8,
    # iPosHeight => 9, iPosWidth => 10
    my $arAnalyzedWordFormIds = $self->getAnalyzedWordFormIds($arToken->[4]);
    my ($sInsertTokAttValues, $sInsertWordGrValues, $sComma) = ('', '', '');
    for my $iAnalyzedWordFormId (@$arAnalyzedWordFormIds) {
      $sInsertTokAttValues .= "$sComma($self->{iDocumentId}, $arToken->[0], " .
	"$arToken->[1], $iAnalyzedWordFormId, 0)";
      # NOTE that we also make this in case there actually is no word form
      # group. 
      # ALSO NOTE that we use the comma to see if we already have this one
      # (we only have to insert it once for a group).
      $sInsertWordGrValues .= "$sComma($iWordFormGroupId, " .
	"$self->{iDocumentId}, $arToken->[0], $arToken->[1])"
	  unless(length($sComma));
      $sComma = ", ";
    }
    $self->insertTokenAttestations($sInsertTokAttValues);

    # Make a group of it if relevant
    $self->insertWordformGroup($sInsertWordGrValues) if( $iNrOfTokens > 1);
  }
}

sub getNewWordFormGroupId {
  my ($self) = @_;

  return unless( exists($self->{dbh}) );

  my $iNewWordFormGroupId = undef;
  $self->{qhGetNewWordFormGroupId}->execute();
  if(my $hrRow = $self->{qhGetNewWordFormGroupId}->fetchrow_hashref()) {
    $iNewWordFormGroupId = $hrRow->{newWfGroupId};
  }
  $self->{qhGetNewWordFormGroupId}->finish();

  $self->{oImpactok}->endProgram("ERROR: " .
				 "Couldn't get new word form group id\n")
    unless($iNewWordFormGroupId);

  return $iNewWordFormGroupId;
}

sub getAnalyzedWordFormIds {
  my ($self, $sWord) = @_;

  return unless( exists($self->{dbh}) );

  $self->{oImpactok}->endProgram("ERROR: no database specified.\n")
    unless(exists($self->{dbh}));

  # Get the lemma id's
  my $arLemmaIds = $self->getLemmaIds();
  # Get the word form id
  my $iWordFormId = $self->getWordFormId($sWord);

  # First insert analyzed word forms
  my ($sComma, $sInsertValues, $sOr, $sSelectCondition) = ('', '', '', '');
  for my $iLemmaId (@$arLemmaIds) {
    $sInsertValues .= "$sComma($iLemmaId, $iWordFormId)";
    $sComma = ", ";
    $sSelectCondition .= "${sOr}(wordform_id = $iWordFormId " .
      "AND lemma_id = $iLemmaId)";
    $sOr = " OR ";
  }
  $self->{dbh}->do("INSERT INTO analyzed_wordforms (lemma_id, wordform_id) " .
		   "VALUES $sInsertValues " .
		   "ON DUPLICATE KEY UPDATE analyzed_wordform_id =" .
		   " analyzed_wordform_id")
    or $self->{oImpactok}->endProgram("Couldn't do insert statement\n");

  my @aAnalyzedWordFormIds;

  my $qhSelectAnalyzedWordFormIds =
    $self->{dbh}->prepare("SELECT analyzed_wordform_id " .
			  "FROM analyzed_wordforms " .
			  "WHERE $sSelectCondition");
  $qhSelectAnalyzedWordFormIds->execute() or
    $self->{oImpactok}->endProgram("ERROR: Couldn't execute select query " .
				   $qhSelectAnalyzedWordFormIds->errstr);
  while(my $hrRow = $qhSelectAnalyzedWordFormIds->fetchrow_hashref()) {
    push(@aAnalyzedWordFormIds, $hrRow->{analyzed_wordform_id});
  }
  $qhSelectAnalyzedWordFormIds->finish();

  return \@aAnalyzedWordFormIds;
}

sub getWordFormId {
  my ($self, $sWordform) = @_;

  return unless( exists($self->{dbh}) );

  $sWordform = encode_utf8($sWordform);

  # First insert it
  $self->{dbh}->do("INSERT INTO wordforms (wordform, wordform_lowercase) " .
		   "VALUES (" . $self->{dbh}->quote($sWordform) . ", " .
		   $self->{dbh}->quote(lc($sWordform)) . ") " .
		   "ON DUPLICATE KEY UPDATE wordform_id = wordform_id")
    or $self->{oImpactok}->endProgram("Couldn't do insert statement\n");

  # Then select the id
  $self->{qhSelectWordFormId}->execute($sWordform) or
    $self->{oImpactok}->endProgram("ERROR: Couldn't execute select query " .
				   $self->{qhSelectWordFormId}->errstr . "\n");

  my $iWordFormId = undef;
  if(my $hrRow = $self->{qhSelectWordFormId}->fetchrow_hashref()) {
    $iWordFormId = $hrRow->{wordform_id};
  }
  $self->{qhSelectWordFormId}->finish();

  $self->{oImpactok}->endProgram("ERROR: No word form id for '$sWordform', " .
				 "file: '$self->{sInputFileName}'.\n")
    unless(defined($iWordFormId));

  return $iWordFormId;
}

sub getLemmaIds {
  my ($self) = @_;

  return unless( exists($self->{dbh}) );

  my ($sComma, $sSelectCondition, $sInsertValues, $sOr) = ('', '', '', '');
  my @aLemmata = split(/,/, $self->{sLemmata});
  for my $sLemma (@aLemmata) {
    my $sQuotedLemma = $self->{dbh}->quote($sLemma);
    $sInsertValues .= "$sComma($sQuotedLemma, 'NE_LOC')";
    $sComma = ", ";
    $sSelectCondition .= "${sOr}modern_lemma = $sQuotedLemma";
    $sOr = " OR ";
  }
  # Insert all the lemmata
  $self->{dbh}->do("INSERT INTO lemmata (modern_lemma, lemma_part_of_speech) ".
		   "VALUES $sInsertValues ".
		   "ON DUPLICATE KEY UPDATE lemma_id = lemma_id")
    or $self->{oImpactok}->endProgram("Couldn't do insert statement\n");
  # Select their id's
  my $qhSelectLemmaIds =
    $self->{dbh}->prepare("SELECT lemma_id FROM lemmata " .
			  "WHERE $sSelectCondition");
  $qhSelectLemmaIds->execute() or
    $self->{oImpactok}->endProgram("ERROR: Couldn't execute select query " .
				   $qhSelectLemmaIds->errstr . "\n");

  my @aLemmaIds;
  while(my $hrRow = $qhSelectLemmaIds->fetchrow_hashref()) {
    push(@aLemmaIds, $hrRow->{lemma_id});
  }
  $qhSelectLemmaIds->finish();

  return \@aLemmaIds;
}

sub insertDocumentInDb {
  my ($self) = @_;

  return unless( exists($self->{dbh}) );

  $self->{oImpactok}->endProgram("ERROR: no database specified.\n")
    unless(exists($self->{dbh}));

  my $sDocumentName = $self->{sInputFileName};
  # Chop off the path
  $sDocumentName =~ /^(.*[\\\/])([^\\\/]+)$/;
  my $sUploadDir = $1;
  $sDocumentName = $sUploadDir . $2 . "_tokenized.tab";

  # Zet het document erin
  $self->{dbh}->do("INSERT INTO documents (title) VALUES (" .
		   $self->{dbh}->quote($sDocumentName) . ")" .
		   "ON DUPLICATE KEY UPDATE document_id = document_id")
    or $self->{oImpactok}->endProgram("Couldn't do insert statement\n");

  # Haal de id op
  $self->{qhSelectDocId}->execute($sDocumentName) or
    $self->{oImpactok}->endProgram("ERROR: Couldn't execute select statement: "
				   . $self->{qhSelectDocId}->errstr . "\n");

  if( my $hrRow = $self->{qhSelectDocId}->fetchrow_hashref()) {
    $self->{iDocumentId} = $hrRow->{document_id};
    $self->{qhSelectDocId}->finish();
  }
  else {
    $self->{oImpactok}->endProgram("ERROR: Couldn't retrieve document id " .
				   "for $sDocumentName.\n");
  }
}

sub insertTokenAttestations {
  my ($self, $sInsertTokAttValues) = @_;

  return unless( exists($self->{dbh}) );

  $self->{dbh}->do("INSERT INTO token_attestations " .
		   "(document_id, start_pos, end_pos," .
		   " analyzed_wordform_id, " .
		   "derivation_id) VALUES $sInsertTokAttValues");
}

sub insertWordformGroup {
  my ($self, $sInsertWordGrValues) = @_;

  return unless( exists($self->{dbh}) );

  $self->{dbh}->do("INSERT INTO wordform_groups " .
		   "(wordform_group_id, document_id, onset, offset)".
		   " VALUES $sInsertWordGrValues");
}

1;
