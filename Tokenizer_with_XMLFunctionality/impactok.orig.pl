#!/usr/bin/perl -w

## Version for Impact-INL by Bob Boelhouwer
## Adjustments by Jesse De Does and Tom Kenter (INL)
##
## See also http://www.unicode.org/reports/tr29/#Word_Boundaries

## Copyright Sabine Buchholz and Erik Tjong Kim Sang, 1999-2007
## Current maintainer at ILK: Martin Reynaert: reynaert AT uvt DOT nl
## ILK is the Induction of Linguistic Knowledge research team
## at Tilburg University, The Netherlands.
## 01-05-2007

##  This program is free software; you can redistribute it and/or modify
##    it under the terms of the GNU General Public License as published by
##    the Free Software Foundation; either version 2 of the License, or
##    (at your option) any later version.

##    This program is distributed in the hope that it will be useful,
##    but WITHOUT ANY WARRANTY; without even the implied warranty of
##    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
##    GNU General Public License for more details.

##    You should have received a copy of the GNU General Public License
##    along with this program; if not, write to the Free Software
##    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
##  This program is called ILKTOK and is a rule-based sentence splitter and tokenizer with provisions for English, Dutch, German and French. The user may want to extend the language specific provisions such as e.g. the known abbreviation list for the particular language. 

#######################################################################

##EXAMPLE USAGE:

## perl ILKTOK.pl -b ' ' -e'\n' -d PATH < in.txt >out.txt

## Where: PATH is the path to the directory where you put the tokenizer.
## The above example usage will put each sentence on its own line. 

## The default language is Dutch. Should one wish to tokenize English text, one needs to specify 'eng' as follows:

## perl ILKTOK.pl -b ' ' -e'\n' -l eng -d PATH < in.txt >out.txt

##Begin and end markers might well be <utt> and </utt>, respectively:

## perl ILKTOK.pl -b '<utt>' -e '</utt>' -l eng -d PATH < in.txt >out.txt

#######################################################################

##Limited documentation is to be found further in-line.

#######################################################################

use strict;
use HTML::Entities;
### TK 17 aug 2009
### $opt_e wordt kennelijk niet meer ondersteund...
### En verder komt de newline swith ($opt_r) nergens meer voor...
### use vars qw($opt_b $opt_e $opt_r $opt_l $opt_d $opt_f);
use vars qw($opt_b $opt_r $opt_l $opt_d $opt_f);
use Getopt::Std;

############ Options / Parameters ###############
getopts('ib:r:l:d:f:o:t:e:');
### TK 17 aug 2009
### $opt_e wordt kennelijk niet meer ondersteund...
### our ($opt_b, $opt_e, $opt_r, $opt_l, $opt_d, $opt_f, $opt_o, $opt_t);
our ($opt_b, $opt_r, $opt_l, $opt_d, $opt_f, $opt_o, $opt_t, $opt_e, $opt_i);

# b : begin utterance marker
# r : print returns
# l : language
# d : path
# f : filename
# o : output file
# t : table with input to output mapping
# e : input and output character encoding (iso-8859-1, utf8, etc) (needed to match whitespace and interpunction c with ord(c) > 127)
# i : map everyting to lowercase

# defaults: set $opt_X unless already defined (Perl Cookbook p. 6):

$opt_e ||= 'utf8';
$opt_b ||= ' ';
$opt_r ||= 0;
$opt_l ||= 'ned';
$opt_d ||= '';
$opt_o ||= 'tmp';

if (!$opt_d) { # try to guess location
  my $scriptPath = $0;
  $scriptPath =~ s/[\/\\][^\/\\]*$//;
  $opt_d = $scriptPath;
  warn "setting opt_d to $opt_d";
}
my $lang=$opt_l;
my $path=$opt_d;
my $file=$opt_f;
my $output = $opt_o;

my %ts = ("onset" => 0, "offset" => 1, "word" => 2, "string" => 3);
my $doc = "";			# contains file content
my @doc = ();			# contains tokenized file

print STDERR "Language is \"$lang\"\n";
print STDERR "Newline switch is \"$opt_r\"\n";
print STDERR "Begin marker is \"$opt_b\"\n";
print STDERR "Path is \"$opt_d\"\n";

if ($opt_b eq '\n') {
  $opt_b="\n";
}
elsif ($opt_b eq ' ') {
  $opt_b='';
}

my %abbr=();
my %apo=();

my $PING = "_PING_";
my $PANG = "_PANG_";
my %pingpang = ( "(" => $PING, ")" => $PANG);

####################### Main Process ##########################################

initialize();

if ( ! $opt_t || ($opt_t eq "") ) {
  tokfile ($file, $output);
}
else {
  if (!open (TABLE, "<" . $opt_t)) {
    die "Unable to open mapping table $opt_t!\n";
  }
  my $mapping = "";
  while ($mapping = <TABLE>) {
    chomp ($mapping);
    tokfile (split ("\t", $mapping));
  }
  close (TABLE);
}

########################## Subs ################################

sub tokfile {
  my ($infile, $outfile) = @_;
  my $tmpeol = $/;
  undef $/;

  if (!open (INFILE, "<:encoding($opt_e)", $infile)) {
    die "Unable to open file $infile\n";
  }
  if (!open (OUTFILE, ">:encoding($opt_e)", $outfile)) {
    die "Unable to open output file $outfile\n";
  }

  $doc = <INFILE>;
  close (INFILE);
  $/ = $tmpeol;

  # Do a simple tokenization using spaces and line endings
  my $sSepChars = " \t\n\240";
  # We treat brackets differently for Spanish
  $sSepChars .= "\\(\\)\\[\\]" if($lang eq 'esp');
  #bb; note: use tokenize from 'matchXML.pl' jesse: en tabjes?
  @doc = splitLine($doc, $sSepChars);

  ### TK 18 augustus 2009
  ### Is het echt handig om 3 keer door hetzelfde array heen te gaan..?!?

  # adjust the tokenization according to more specific rules
  for (my $wordNr = 0; $wordNr < scalar @doc; $wordNr++) {
    handle_hyphenation ($wordNr);
    handle_punct_at_start ($wordNr);
    handle_punct_at_end ($wordNr);
    handle_brackets($wordNr);
  }

  foreach my $wrd (@doc) {
    ### TK 17 aug 2009
    ### Volgorde aangepast. Nu:
    ### canonicalForm<TAB>wordform<TAB>onset<offset
    print OUTFILE
      $wrd->[$ts{"word"}] . "\t" . # Canonical form
	# Word as it appeared in the text
	noNewlines($wrd->[$ts{"string"}]) . "\t" .
	  $wrd->[$ts{"onset"}] . "\t" .
	    $wrd->[$ts{"offset"}] . "\n" if( length($wrd->[$ts{"word"}]) );
  }
  close (OUTFILE);
}


sub removeBrackets {
  my $token = shift;
  ###
  # return ($token,0,0);
  ###
  return ($token,0,0) if( $token !~ /[\(\)]/ );

  # warn "IN: $token";
  # replace unremovable brackets by some escape code

  $token =~ s/(\w.*?)\(/$1$PING/g; # ? <- Emacs syntax highlighting
  $token =~ s/\)(.*?\w)/$PANG$1/g;

  my @stack;
  my %unsafe;

  # now: brackets matching an unremovable bracket are unremovable

  while ($token =~ /(\(|\)|($PING)|($PANG))/g) {
    my $match = $&;
    my $p = pos $token;
    if ($match eq "(" || $match eq $PING) {
      push(@stack,"$p:$match");
    }
    if ($match eq ")" || $match eq $PANG) {
      my $z = pop @stack;
      if (!$z) {
      } else {
	my ($mpos, $mbrack) = split(/:/,$z);
	#warn "$z($mbrack) $match";
	#warn $mbrack;
	if ($mbrack eq $PING) {
	  # warn "Matching unsafe bracket at $p";
	  $unsafe{$p}++;
	}
	elsif ($match eq $PANG && $mbrack eq "(") {
	  $unsafe{$mpos}++;
	}
      }
    }
  }
  #warn "UNSAFE: " . join(", ", sort keys %unsafe) . "\n";

  # Dit crasht perl: $token =~ s/[\(\)]/my $brack = $&; my $p = pos $token; warn $p; if ($unsafe{$p+1}) { $pingpang{$brack}; } else { $brack; }/eg;
  my @chars = split(//,$token);
  for (my $i=0; $i < @chars; $i++) {
    if ($unsafe{$i+1}) {
      $chars[$i] = $pingpang{$chars[$i]};
    }
  }
  $token = join("",@chars);
  # warn "GOED: dat hebben we ook weer overleefd: $token!";
  my $pre=0;
  my $post = 0;
  $token =~ s/^.*\(+/$pre = length($&); ""/e;
  $token =~ s/\)+.*/$post = length($&); ""/e;
  $token =~ s/$PING/(/g;
  $token =~ s/$PANG/)/g;
  #warn "OUT: $token";
  return ($token,$pre,$post);
}

### TK 18 aug 2009
### toegevoegd. op een of andere manier kunnen er allerlei newlines in de
### string staan. Aan het begin, maar ook middenin.
### Ook als -b op \n staat...
sub noNewlines {
  my ($sString) = @_;
  $sString =~ s/^\n/_/;
  return $sString;
}

sub initialize {
  #print STDERR " Tokenizer: Initializing ...   ";
  abbr();
  apostrof();

  #print STDERR "Init done\n";
}

# Read in the known abbreviations
sub abbr {
  my $line;
  open(FH,"<$path/abbr.$lang") or die "Cannot open $path/abbr.$lang!\n";
  while (defined($line=<FH>)) {	# for each line
    if (substr($line,0,1) eq '#') { # except comment
      next;
    }
    chomp($line);		#bb; chop/chomp
    $abbr{$line}=1;
  }
  close(FH);
}

# read in the known words starting with "'"
# e.g. 's morgens => 's morgens
sub apostrof {
  my $line;
  open(FH,"<$path/apostrof.$lang") or
    die "Cannot open $path/apostrof.$lang!\n";
  while (defined($line=<FH>)) {	# for each line
    next if (substr($line,0,1) eq '#'); # except comment
    chop($line);		#bb; chop/chomp
    $apo{$line}=1;
  }
  close(FH);
}

# word starts with punctuation # don't separate ,, ... -- '' etc.

sub handle_punct_at_start {
  my ($wordNr) = @_;
  if ((substr ($doc[$wordNr][$ts{"word"}], 0, 1) eq "'") &&
      (!exists($apo{$doc[$wordNr][$ts{"word"}]}))) { # 's morgens
    substr ($doc[$wordNr][$ts{"word"}], 0, 1) = "";
    $doc[$wordNr][$ts{"onset"}]++;
  }
}

# word ends in punctuation # don't separate ,, ... -- '' 5,- etc.

sub handle_punct_at_end {
  my ($wordNr) =  @_;

  if( ! (exists($abbr{$doc[$wordNr][$ts{"word"}]}) # etc.   e.g.
	 || ($doc[$wordNr][$ts{"word"}] =~ /\S\.\S/) # e.g. s.v.o.p.
	 # word contains no vowels and ends with a period e.g. svp.
	 || ($doc[$wordNr][$ts{"word"}] =~ /^[^aeiouy]+\.$/i) )
    ) {
    # Remove punctuation from end of word
    ## 20091106 MB en FL vinden dit stom :/
    ## TK 23 sep 2010: Reguliere expressie aangepast, want de punt was niet
    ## ge-escaped (?!?) en de '-' stond niet aan het eind...
    $doc[$wordNr][$ts{"word"}] =~ s/([,\.=\"-]+)$//;
    $doc[$wordNr][$ts{"offset"}] -= length $1 if ($1 && ($1 ne "") );
  }
}

sub handle_brackets {
  my ($wordNr) = @_;
  my ($w,$pre,$post) = removeBrackets($doc[$wordNr][$ts{"word"}]);
  $doc[$wordNr][$ts{"word"}] = $w;
  $doc[$wordNr][$ts{"onset"}] += $pre;
  $doc[$wordNr][$ts{"offset"}] -= $post;
}

sub handle_hyphenation {
  my ($wordNr) = @_;

  if (($wordNr < $#doc) &&	# not the last word of a doc
      # string was at the end of a line
      (($doc[$wordNr][$ts{"string"}] =~ m!()?\n$!) ||
       # or next string was at the start of a line
       ($doc[$wordNr + 1][$ts{"string"}] =~ m!^()?\n!)) &&
      # word ends with a hyphen preceded by a letter
      ($doc[$wordNr][$ts{"word"}] =~ m!\w­$!)) {
    my @first = @{$doc[$wordNr]};
    my @second = @{$doc[$wordNr + 1]};
    splice (@doc, $wordNr, 2,
	    [$first[$ts{"onset"}], $second[$ts{"offset"}],
	     $first[$ts{"word"}]. $second[$ts{"word"}],
	     $first[$ts{"string"}] . $second[$ts{"string"}]]);
  }
}

## Additions for Impact ########################################################

sub splitLine {
  my ($q, $sepChr) = @_;
  my $tempQ = $q;
  $tempQ =~ s!<([^<>]*)>!&replaceTags($1)!eg;
  return map {handle_token ($_)} splitAtChar ($q, $tempQ, $sepChr);
}

sub cleanseWord {
  my ($word) = @_;
  $word =~ s/&(ldquor|rdquor);//g;
  $word =~ s!<[^<>]*>!!g;
  if ($opt_i) {
    return lc decode_entities ($word);
  }
  else {
    return decode_entities ($word);
  }
}

sub splitAtChar {
  my ($str, $tmpStr, $chr) = @_;
  my @result = ();
  my $part = "";
  my $onset = 0;
  #my $inChr = 0; # mode: 0=not in white space, 1=in white space
  # TK 4-dec-2009: Ja, ik heb de variabele naam daarom even verandert in
  # $bInWhiteSpace, want dan vind ik het iets beter (nl. wel ;-) ) te volgen.
  my $bInWhiteSpace = 0;
  for (my $pos = 0; $pos < length $tmpStr; $pos++) {
    my $nextChr = substr($tmpStr, $pos, 1);
    if ($nextChr =~ m![$chr]!) {
      if ($bInWhiteSpace) {
	$part .= $nextChr;
      }
      else {
	$bInWhiteSpace = 1;
	push (@result, [$onset, $part]);
	$onset = $pos;
	$part = $nextChr;
      }
    }
    else {			# dit ging niet lekker
      if ($bInWhiteSpace) {	# toegevoeg Jesse
	$part="";
	$onset=$pos;
      }
      $part .= substr ($str, $pos, 1);
      $bInWhiteSpace = 0;
    }

    #	else {
    #	    $part .= substr ($str, $pos, 1);
    #	    $bInWhiteSpace = 0;
    #	}
  }

  #$DB::single = 1;

  # TK 4-dec-2009: Dit toegoevoegd zodat het ook goed gaat als de file
  # eindigt op bijvoorbeeld een newline
  push (@result, [$onset, $part]) if(! $bInWhiteSpace );
  return @result;
}

sub replaceTags {
  my ($str) = @_;
  return sprintf "<%s>", "-" x length $str;
}

# (« or »),
# ¿ ? ¿ principio y fin de interrogación ¿ question marks
# ¡ ! ¿ principio y fin de exclamación o admiración ¿ 
sub handle_token {
  my ($onset, $token) = @{shift @_};
  my $tmpToken = $token;
  my $offset = $onset + (length $token);
  # TK 17 jun 2010: added colon and semi colon
  # TK 17 jun 2010: made patterns language specific (i.e. hyphens are treated
  #                 differently for Spanish)
  my $sEndPat = ($lang eq 'esp')
    ? "((?:<[^<>]*>|[ \n»,¿¡\'\"\?\!;:-])+)" :
      "((?:<[^<>]*>|[ \n»,¿¡\'\"\?\!;:])+)";
  my $sStartPat = ($lang eq 'esp')
    ? "((?:<[^<>]*>|[«,\.?¿¡\!; \"\n;:-]*)+)" :
      "((?:<[^<>]*>|[«,\.?¿¡\!; \"\n;:]*)+)";

  $offset -= length $1 if ($tmpToken =~ s/$sEndPat$//);
  $onset += length $1 if ($tmpToken =~ s/^$sStartPat//);

  return [$onset, $offset, cleanseWord ($tmpToken), $token];
}
