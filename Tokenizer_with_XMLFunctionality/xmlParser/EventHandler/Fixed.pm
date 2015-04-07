package xmlParser::EventHandler::Fixed;

# Inherit database functionality
use xmlParser::EventHandler::databaseFunctions;
@ISA = (xmlParser::EventHandler::databaseFunctions);

use strict;

use HTML::Entities;
use DBI;

use impactok::impactok;

# NOTE that we never 'die' as that will print to stderr which the
# Lexicon Tool's php scripts won't print.
# Instead, we use impactok::impactok::endProgram()

# Constructor
sub new {
  my ($class, %hOptions) = @_;

  # Call the constructor of the class we inherit from
  my $self = $class->SUPER::new(%hOptions);
  bless $self, $class;

  # Initialise
  $self->{sLanguage} =
    ( exists($hOptions{sLanguage}) ) ? $hOptions{sLanguage} : undef;
  $self->{bInLocTag} = undef;
  $self->{iGroupId} = 0;
  $self->{arNameTokens} = [];
  $self->{bAddToGroup} = undef;
  $self->{iGroupId} = 0;
  $self->emptyText();

  $self->{oImpactok} = impactok::impactok->new(sLanguage =>$self->{sLanguage});

  return $self;
}

# This one is called when a tag has been read completely
sub atTag {
  my ($self, $hrTag) = @_;

  if( $hrTag->{sTagName} eq "NE" &&
      exists($hrTag->{hrAttributes}->{type}) &&
      $hrTag->{hrAttributes}->{type} eq 'LOC' ) {
    if( exists($hrTag->{hrAttributes}->{gid}) ) {
      # If we have an NE type="LOC" with a group id, that means that there
      # are others with the same id that make up a group.
      # There can be text in between though so we collect them all before
      # we put them in the database.

      # We flush the tokenized output anyway
      $self->tokenizeText();

      # If it is the same group we add it to the collected tokens
      if( $hrTag->{hrAttributes}->{gid} == $self->{iGroupId} ) {
	$self->{bAddToGroup} = 1;
      }
      else { # Not a new group member, the we flush everything before
	$self->putLocTokensInDb($self->{arNameTokens})
	  if(scalar(@{$self->{arNameTokens}}));
	$self->{arNameTokens} = []; # New name
	$self->{bAddToGroup} = 1; # Add the next ones to the group
      }
      $self->{iGroupId} = $hrTag->{hrAttributes}->{gid};
    }
    else { # No gid
      # If we have previous names, add them to the database
      if(scalar(@{$self->{arNameTokens}})) {
	$self->putLocTokensInDb($self->{arNameTokens});
	$self->{arNameTokens} = []; # New name
      }

      $self->{iGroupId} = 0;
      $self->{bAddToGroup} = undef;
      # We flush everything before, so any text in the tag can be treated
      # as one name.
      $self->tokenizeText();
    }

    # The lemma should always be there
    if(exists($hrTag->{hrAttributes}->{lemmata})) {
      $self->{sLemmata} = $hrTag->{hrAttributes}->{lemmata};
    }
    else {
      print "ERROR: No lemmata for '$hrTag->{sTagName}' " .
	"in file $self->{sInputFileName}\n";
    }
    $self->{bInLocTag} = 1;
  }
  elsif( $hrTag->{sTagName} eq "/NE") {
    $self->{bInLocTag} = undef;
  }
}

# Is called when some text has been read
#
# In the Fixed case we tokenize every non-location piece of text separately
# from text between <NE type="loc"> tags.
# This way we can also add the tokens between the <NE type="loc"> tags
# separately to the database as tokens attestations.
#
sub atText {
  my ($self, $hrText) = @_;

  # We tokenize every text straight away
  $self->{hrText}->{iStartPos} = $hrText->{iStartPos};
  $self->{hrText}->{sText} = $hrText->{sText};
  $self->tokenizeText();

  if( $self->{bInLocTag} ) {
    # If we are in a group tag, we remember what we have
    if( $self->{bAddToGroup} ) {
      foreach my $arToken ( @{$self->{oImpactok}->{arTokens}} ) {
	push(@{$self->{arNameTokens}}, $arToken) if length($arToken->[2]);
      }
    }
    else { # If not in a group, we immediately put it in the database
      $self->putLocTokensInDb();
      # We are done with the text for this tag, so if we remembered the lemma
      # we can forget it now
      $self->{sLemmata} = '';
    }
  }
}

# This also sets the file handle for the impactok object.
sub setOutputFileHandle {
  my ($self, $fhOut) = @_;

  $self->{fhOut} = $fhOut;
  $self->{oImpactok}->{fhOut} = $self->{fhOut};
}

sub tokenizeText {
  my ($self) = @_;

  if( defined($self->{hrText}->{iStartPos}) ) {
    $self->{oImpactok}->makeTokenArray($self->{hrText}->{sText},
				       $self->{hrText}->{iStartPos});
    $self->{oImpactok}->analyseTokenArray();
    $self->{oImpactok}->printTokenArray();
  }
  $self->emptyText();
}

sub emptyText {
  my ($self) = @_;

  $self->{hrText} = {sText => '',
		     iStartPos => undef,
		     iEndPos => undef,
		    };
}

sub atStartOfFile {
  my ($self, $sInputFileName) = @_;

  $self->{sInputFileName} = $sInputFileName;
  $self->insertDocumentInDb();
}

sub atEndOfFile {
  my ($self) = @_;

  # If there is still text left
  if(defined($self->{hrText}->{iStartPos})) {
    $self->{oImpactok}->endProgram("ERROR: the last <NE_LOC> tag appears " .
				   "not to have been closed\n")
      if( $self->{bInLocTag} );
    $self->tokenizeText();
  }

  # If we still have a name in memory, put it in the database.
  $self->putLocTokensInDb($self->{arNameTokens})
    if(scalar(@{$self->{arNameTokens}}));
}

# Additional sub routines #####################################################

1;
