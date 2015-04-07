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
##  it under the terms of the GNU General Public License as published by
##  the Free Software Foundation; either version 2 of the License, or
##  (at your option) any later version.

##  This program is distributed in the hope that it will be useful,
##  but WITHOUT ANY WARRANTY; without even the implied warranty of
##  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
##  GNU General Public License for more details.

##  You should have received a copy of the GNU General Public License
##  along with this program; if not, write to the Free Software
##  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

##  This program is called ILKTOK and is a rule-based sentence splitter and
##  tokenizer with provisions for English, Dutch, German and French. The user
##  may want to extend the language specific provisions such as e.g. the known
## abbreviation list for the particular language.

###############################################################################

# EXAMPLE USAGE:

# perl ILKTOK.pl -b ' ' -e'\n' -d PATH -f in.txt > out.txt

# Where: PATH is the path to the directory where you put the tokenizer.
# The above example usage will put each sentence on its own line.

# The default language is Dutch. Should one wish to tokenize English text, one
# needs to specify 'eng' as follows:

# perl ILKTOK.pl -b ' ' -e'\n' -l eng -d PATH -f in.txt >out.txt

# Begin and end markers might will be <utt> and </utt>, respectively:

# perl ILKTOK.pl -b '<utt>' -e '</utt>' -l eng -d PATH -f in.txt > out.txt

#######################################################################

use strict;
use Getopt::Std;

use utf8;

# This bit is needed so \w also matches [äìé...] etc.
use locale;
use POSIX qw(locale_h);
setlocale(LC_CTYPE, 'eu_ES');

use impactok::impactok;

# Options / Parameters
our ($opt_b, $opt_d, $opt_e, $opt_f, $opt_i, $opt_l, $opt_o, $opt_p, $opt_r,
     $opt_t, $opt_v);
getopts('b:d:e:f:il:o:pr:t:v');

my $sHelpText = <<HELP_TXT;

 $0 [OPTIONS] -f input_file

 OPTIONS:

 -b MARKER
    Begin utterance marker.
    Default: ' ' (a space)
 -d PATH
    Path the tokenizer is in.
    If not specified the current directory (.) is assumed.
 -e ENCODING
    Input and output character encoding (iso-8859-1, utf8, etc) (needed to
    match whitespace and interpunction c with ord(c) > 127).
    Default utf8.
 -f filename
    The file being tokenized.
    This is the only obligatory argument.
 -r
    Print returns.
 -l LANGUAGE
    The language setting influences the tokenzing, and it is used for the
    abbreviation file and apostroph file.
    E.g. 'ned', 'esp'.
 -o FILE
    Output file.
    If not specified, STDOUT is used.
 -t TABLE
    Table with input to output mapping.
 -i
    Map everyting to lowercase.
 -p
    Output position info as well. This only works when you are tokenizing IGT
    XML files. The x and y coordinates plus the width and height of text blocks
    are displayed in four extra columns next to the onset/offset columns.
 -v
    Be verbose

HELP_TXT

die $sHelpText unless($opt_f);

# Set defaults
$opt_e ||= 'utf8';
$opt_b ||= ' ';
$opt_r ||= 0;
$opt_l ||= 'ned';
$opt_d ||= '';

if (!$opt_d) { # Try to guess location
  my $sScriptPath = $0;
  $sScriptPath =~ s/[\/\\][^\/\\]*$//;
  $opt_d = $sScriptPath;
}
my $lang=$opt_l;
my $path=$opt_d;
my $file=$opt_f;
my $output = $opt_o;

print STDERR "Language is \"$lang\"\nNewline switch is \"$opt_r\"\n" .
  "Begin marker is \"$opt_b\"\nPath is \"$opt_d\"\n" if($opt_v);

if ($opt_b eq '\n') {
  $opt_b="\n";
}
elsif ($opt_b eq ' ') {
  $opt_b='';
}

# Main Process ################################################################

# Construct an impactok object
my $oImpactok = impactok::impactok->new(sLanguage => $opt_l);
$oImpactok->{bMapToLowercase} = $opt_i;
$oImpactok->setAbbreviations($path);
$oImpactok->setApostrophes($path);

if ( ! $opt_t || ($opt_t eq "") ) {
  tokfile ($file, $output);
}
else {
  if( ! open(TABLE, "< $opt_t") ) {
    die "Unable to open mapping table $opt_t!\n";
  }
  my $mapping = "";
  while ($mapping = <TABLE>) {
    chomp ($mapping);
    tokfile (split ("\t", $mapping));
  }
  close (TABLE);
}

# Subs ########################################################################

sub tokfile {
  my ($infile, $outfile) = @_;
  my $tmpeol = $/;
  undef $/;

  open (INFILE, "<:encoding($opt_e)", $infile) or
    die "Unable to open file $infile\n";
  my $sDoc = <INFILE>;
  close (INFILE);
  $/ = $tmpeol;

  if( $outfile ) {
    open (OUTFILE, ">:encoding($opt_e)", $outfile) or
      die "Unable to open output file $outfile\n";
  }
  else { # Redirect to stdout
    binmode(STDOUT, ":encoding(utf8)");
    open(OUTFILE, ">&STDOUT") or die "Couldn't tie to standard output\n";
  }
  $oImpactok->{fhOut} = *OUTFILE;

  $oImpactok->makeTokenArray($sDoc, 0);

  $oImpactok->analyseTokenArray();

  $oImpactok->printTokenArray();

  close(OUTFILE);
}
