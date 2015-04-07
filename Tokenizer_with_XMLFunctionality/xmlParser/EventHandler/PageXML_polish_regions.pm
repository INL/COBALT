package xmlParser::EventHandler::PageXML_polish;

# Inherit database functionality
use xmlParser::EventHandler::databaseFunctions;
@ISA = (xmlParser::EventHandler::databaseFunctions);

use strict;

use HTML::Entities;

use impactok::impactok;

# NOTE that we never 'die' as that will print to stderr which the
# Lexicon Tool's php scripts won't print.
# Instead, we use impactok::impactok::endProgram()

sub initDatabase {
  my ($self, $sDatabase) = @_;

  # This function is intentionally left empty. It is here because the Lexicon
  # Tool calls tokenizeXml.pl with option -d, even though in the JSI case
  # we don't do anything with a database.
}
  
sub closeDatabase {
  my ($self) = @_;

  $self->{dbh}->disconnect() if( exists($self->{dbh}) );
} 


# Constructor
sub new {
  my ($class, %hOptions) = @_;

  # Call the constructor of the class we inherit from.
  my $self = $class->SUPER::new(%hOptions);
  bless $self, $class;

  # Initialise
  $self->{sLanguage} =
    ( exists($hOptions{sLanguage}) ) ? $hOptions{sLanguage} : undef;
  $self->{sLemmata} = '';
  $self->{bInUnicodeTag} = undef;
  $self->{bInTextRegionTag} = undef; # For coordinates
  $self->{bInCoordsTag} = undef; # For coordinates
  $self->emptyTextBlock();

  $self->{oImpactok} = impactok::impactok->new(sLanguage =>$self->{sLanguage});
  $self->{oImpactok}->{frAlternativeAddToken} = \&addToken;
  $self->{iPointNr} = 0;
  $self->{xMax}=0;
  $self->{xMin}=0;
  $self->{yMax}=0;
  $self->{yMin}=0;

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

  if ($hrTag->{sTagName} eq "Page") 
  {
    $self->{imageHeight}=$hrTag->{hrAttributes}->{imageHeight};
    $self->{imageWidth}=$hrTag->{hrAttributes}->{imageWidth}; 
    $self->{bInUnicodeTag} = 1;
  }
  elsif( $hrTag->{sTagName} eq "Unicode") {
    $self->{bInUnicodeTag} = 1;
  }
  elsif( $hrTag->{sTagName} eq "/Unicode") 
  {
    $self->attachCoordinates(); 
    $self->tokenizeTextBlock();
    $self->{bInUnicodeTag} = undef;
  }
  elsif( $hrTag->{sTagName} eq "Coords") {
    # Only take coordinates into account of text regions
    $self->{iPointNr}=0;
    $self->{bInCoordsTag} = 1 if($self->{bInTextRegionTag});
  }
  elsif( $hrTag->{sTagName} eq "/Coords") {
    $self->{bInCoordsTag} = undef;
  }
  elsif( $hrTag->{sTagName} eq "Point") {
    $self->handleCoordinate($hrTag) if($self->{bInCoordsTag});
  }
  elsif( $hrTag->{sTagName} eq "TextRegion") {
    $self->{bInTextRegionTag} = 1;
  }
  elsif( $hrTag->{sTagName} eq "/TextRegion") {
    $self->{bInTextRegionTag} = undef;

  }
}

# Gets called when some text has been read
sub atText {
  my ($self, $hrText) = @_;

  if( $self->{bInUnicodeTag} ) {
    $self->{hrText}->{iStartPos} = $hrText->{iStartPos}
      unless(defined($self->{hrText}->{iStartPos}));
    $self->{hrText}->{sText} .= $hrText->{sText};
    $self->{hrText}->{iEndPos} = $hrText->{iEndPos};
  }
}

sub tokenizeTextBlock {
  my ($self) = @_;

  if( defined($self->{hrText}->{iEndPos}) ) {
    # Coordinates are neglected for the moment
    my $arCoordinates = (exists($self->{hrText}->{arCoordinates}))
      ? $self->{hrText}->{arCoordinates} : undef;
    $self->{oImpactok}->makeTokenArray($self->{hrText}->{sText},
				       $self->{hrText}->{iStartPos},
				       $arCoordinates );
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
  # We empty the coordinates at the end of a <IGT:TextBlock> tag
  $self->{hrText}->{arCoordinates} = []
    unless(exists($self->{hrText}->{arCoordinates}));
}

# Additional sub routines #####################################################


# Attach the bounding box of the coordinates to the text region...
# beware: in the djvu images, the (0,0) dot is the bottom left.
# The imageHeight is not stored in the datbase, so we have to compute it here

sub attachCoordinates
{
  my $self = shift;
  if ($self->{iPointNr} >0)
  {
    my $H = $self->{imageHeight};
    $self->{hrText}->{arCoordinates} = [$self->{xMin}, $H - $self->{yMax}, $self->{yMax} - $self->{yMin}, $self->{xMax} - $self->{xMin}];
    
  }
}

sub handleCoordinate 
{
  my ($self, $hrTag) = @_;

  if ($hrTag->{sTagName} eq "Point") 
  {
    # Coordinates = x, y, h, w
    if  ($self->{iPointNr} == 0)
    {
       $self->{xMax} = $self->{xMin} = $hrTag->{hrAttributes}->{x};
       $self->{yMax} = $self->{yMin} = $hrTag->{hrAttributes}->{y}; 
    } else 
    {
      foreach my $axis ("x","y")
      {
         my $v = $hrTag->{hrAttributes}->{$axis};
         if ($v > $self->{$axis . "Max"})
           {    $self->{$axis . "Max"} = $v; }
         if ($v < $self->{$axis . "Min"})
           {  $self->{$axis . "Min"} = $v; }
      }
    }
    $self->{iPointNr}++;
  }
}

sub emptyCoordinates {
  my ($self) = @_;

}

# $hrState is a hash that you can use for whatever you want and that will keep
# its value over different calls.
sub addToken {
  my ($arTokens, $iOnset, $sWord, $arCoordinates, $hrState) = @_;

  my $iOffset = $iOnset + length($sWord);
  my $arNewToken = ($arCoordinates) ?
    [$iOnset, $iOffset, $iOnset, $iOffset, cleanseWord($sWord), $sWord,
     $sWord, @$arCoordinates]
      : [$iOnset, $iOffset, $iOnset, $iOffset, cleanseWord($sWord), $sWord,
	 $sWord];
  push(@$arTokens, $arNewToken);
}

sub cleanseWord {
  my ($sWord) = @_;

  $sWord =~ s/&[rl]dquor;//g;
  $sWord =~ s!<[^<>]*>!!g;
  return decode_entities ($sWord);
}

sub atStartOfFile {
  my ($self, $sInputFileName) = @_;

  $self->{sInputFileName} = $sInputFileName;
  $self->insertDocumentInDb();
}

sub atEndOfFile {
  my ($self) = @_;
}

1;
