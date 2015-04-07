package xmlParser::EventHandler::TEIBlock2Words;

use strict;

# NOTE that we never 'die' as that will print to stderr which the
# Lexicon Tool's php scripts won't print.
# In stead, we use impactok::endProgram()

# Constructor
sub new {
  my ($class, %hOptions) = @_;

  my $self = \%hOptions;
  bless $self, $class;

  # Initialise
  #$self->{bInSTag} = undef;
  #$self->{bInWTag} = undef;
  # $self->{bInPcTag} = undef;
  $self->{bInCTag} = undef;
  $self->{sText} = '';

  # Initialize
  # $self->emptyTextBlock();

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
    # NOTE that we append the line breaks right away as it is a unary tag
    # my $iNrOfLineBreaks = ( $hrTag->{hrAttributes}->{n} )
    #  ? $hrTag->{hrAttributes}->{n} : 1;
    # $self->{sText} .= "\n" x $iNrOfLineBreaks;
    ## Actually, newlines don't make for very readible text.
    ## So we print a single space in stead.
    $self->{sText} .= " ";
  }
}

# Gets called when some text has been read
sub atText {
  my ($self, $hrText) = @_;

##  if( $self->{bInWTag} || $self->{bInPcTag} || $self->{bInCTag}) {
  if( $self->{bInCTag}) {
    $self->{sText} .= $hrText->{sText};
  }
}

# Additional sub routines #####################################################

sub atStartOfFile {
  my ($self, $sInputFileName) = @_;

  #$self->{sInputFileName} = $sInputFileName;
  $self->{sText} = '';
}

sub atEndOfFile {
  my ($self) = @_;

}

1;
