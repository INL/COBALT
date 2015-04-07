package xmlParser::EventHandler::TEIBlock2Words;

use strict;

# NOTE that we never 'die' as that will print to stderr which the
# Lexicon Tool's php scripts won't print.
# Instead, we use impactok::endProgram()

# Constructor
sub new {
  my ($class, %hOptions) = @_;

  my $self = \%hOptions;
  bless $self, $class;

  # Initialise
  $self->{bInCTag} = undef;
  $self->{sText} = '';

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

  if( $hrTag->{sTagName} eq "c" ) {
    $self->{bInCTag} = 1;
  }
  elsif( $hrTag->{sTagName} eq "/c" ) {
    $self->{bInCTag} = undef;
  }
  elsif( $hrTag->{sTagName} eq "lb" ) {

    $self->{sText} .= " ";
  }
}

# Gets called when some text has been read
sub atText {
  my ($self, $hrText) = @_;

  if( $self->{bInCTag}) {
    $self->{sText} .= $hrText->{sText};
  }
}

# Additional sub routines #####################################################

sub atStartOfFile {
  my ($self, $sInputFileName) = @_;

  $self->{sText} = '';
}

sub atEndOfFile {
  my ($self) = @_;

}

1;
