package xmlParser::EventHandler::IGT2TXT;

use strict;

use HTML::Entities;

use impactok::impactok;

# NOTE that we never 'die' as that will print to stderr which the
# Lexicon Tool's php scripts won't print.
# In stead, we use impactok::impactok::endProgram()

# Constructor
sub new {
  my ($class, %hOptions) = @_;

  my $self = \%hOptions;
  bless $self, $class;

  # Initialise
  $self->{sText} = '';
  $self->{sCurrentRelevantBlock} = undef;
  $self->{bInIgnoreTag} = undef;

  # AD HOC
  $self->{sLanguage} = 'ned';

  $self->{oImpactok} = impactok::impactok->new(sLanguage =>$self->{sLanguage});

  # Make the event handler print to the right stream.
  $self->{oImpactok}->{fhOut} = *STDOUT;

  return $self;
}

# This also sets the file handle for the impactok object.
sub setOutputFileHandle {
  my ($self, $fhOut) = @_;

  $self->{fhOut} = $fhOut;
  $self->{oImpactok}->{fhOut} = $self->{fhOut};
}

# This one is called when a tag has been read completely
sub atTag {
  my ($self, $hrTag) = @_;

  if( ($hrTag->{sTagName} eq "IGT:TextBlock") ||
	 ($hrTag->{sTagName} eq "IGT:TableBlock") ||
	 ($hrTag->{sTagName} eq "IGT:IllustrationBlock") ||
	 ($hrTag->{sTagName} eq "IGT:UnknownBlock") ) {
    $self->{sCurrentRelevantBlock} = $hrTag->{sTagName};
  }
  elsif( ($hrTag->{sTagName} eq "/IGT:TextBlock") ||
	 ($hrTag->{sTagName} eq "/IGT:TableBlock") ||
	 ($hrTag->{sTagName} eq "/IGT:IllustrationBlock") ||
	 ($hrTag->{sTagName} eq "/IGT:UnknownBlock") ) {
    $self->{oImpactok}->endProgram("ERROR: Mismatching tag $hrTag->{sTagName} "
				   . "in file $self->{sInputFileName}\n")
      unless( $self->{sCurrentRelevantBlock} &&
	      ($hrTag->{sTagName} eq "/$self->{sCurrentRelevantBlock}") );

    $self->{hrText}->{iEndPos} = $hrTag->{iStartPos} - 1;
    $self->{sCurrentRelevantBlock} = undef;
  }
  elsif( $self->{sCurrentRelevantBlock} ) { # Tags inside the TextBlock tag
    # Ignore tags
    if( $hrTag->{sTagName} eq "IGT:Ignore") {
      $self->{bInIgnoreTag} = 1;
    }
    elsif( $hrTag->{sTagName} eq "/IGT:Ignore" ) {
      $self->{bInIgnoreTag} = undef;
    }
  }
}

# Gets called when some text has been read
sub atText {
  my ($self, $hrText) = @_;

  if( $self->{sCurrentRelevantBlock} ) {
    if( ! $self->{bInIgnoreTag} ) {
      $self->{sText} .= $hrText->{sText};
    }
  }
}

sub tokenizeTextBlock {
  my ($self) = @_;

  if( defined($self->{sText}) ) {
    $self->{oImpactok}->makeTokenArray($self->{sText},
				       0); # <- Deliberate nonsense value
    $self->{oImpactok}->analyseTokenArray();
    $self->{oImpactok}->printTokenArray();
  }
  $self->emptyTextBlock();
}

sub emptyTextBlock {
  my ($self) = @_;

  $self->{hrText}->{sText} = '';
  $self->{hrText}->{iStartPos} = undef;
  $self->{hrText}->{iEndPos} = undef;
}

# Additional sub routines #####################################################

sub cleanseWord {
  my ($sWord) = @_;

  $sWord =~ s/&[rl]dquor;//g;
  $sWord =~ s!<[^<>]*>!!g;
  return decode_entities ($sWord);
}

sub atStartOfFile {
  my ($self, $sInputFileName) = @_;

  $self->{sInputFileName} = $sInputFileName;
}

sub atEndOfFile {
  my ($self) = @_;

  $self->tokenizeTextBlock();
}

1;
