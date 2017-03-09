
--
-- Load a tab-separated full form lexicon into a lexicon DB
-- BEWARE!! load your lexicon with this procedure before loading your documents (loading your documents happens in the GUI)

-- the input format is tab-separated utf8 data like the example below:
--
--                       > > > NOTE < < <
-- NOTE that due to some MySQL idiosyncrasy the last column of the file
-- used as infile should be followed by a tab as well ! ! !
-- (so that is a tab before every newline...)
--                       < < <      > > >

-- file content example: 
--
-- lexème  lexème  commonNoun(Number=singular)     commonNoun
-- lexèmes lexème  commonNoun(Number=plural)       commonNoun
-- lexicale        lexical adjective(Number=singular,Gender=feminine)      adjective
-- lexicales       lexical adjective(Number=plural,Gender=feminine)        adjective


drop table if exists upload;
drop table if exists uploadPlus;

create table upload
(
  wordform varchar(255) collate utf8_bin, 
  lemma varchar(255) collate utf8_bin,
  part_of_speech varchar(255) collate utf8_bin,
  lemma_part_of_speech varchar(255) collate utf8_bin,
  key(wordform),
  key(lemma),
  key(lemma_part_of_speech)
) engine=InnoDB;


-- load document

load data local infile "<put_full_document_path_here>" into table upload;

--
-- first update the lemmata table (only add 'new' lemmata)
--


create temporary table newLemmata select distinct lemma, lemma_part_of_speech from upload;
alter table newLemmata add column deleteMe int(10);
alter table newLemmata add index(lemma), add index(lemma_part_of_speech);

update 
  newLemmata,lemmata
set 
  newLemmata.deleteMe=1 
where
   lemmata.modern_lemma = newLemmata.lemma 
   and lemmata.lemma_part_of_speech = newLemmata.lemma_part_of_speech;

delete from newLemmata where deleteMe=1;

insert into lemmata (modern_lemma, lemma_part_of_speech, gloss) select distinct lemma, lemma_part_of_speech, NULL from newLemmata;


--
-- next update the wordforms table (only add 'new' wordforms)
--


create temporary table newWordforms select distinct wordform from upload;
alter table newWordforms add column deleteMe int(10);
alter table newWordforms add index(wordform), add index(deleteMe);

update newWordforms, wordforms
set newWordforms.deleteMe=1
where wordforms.wordform=newWordforms.wordform;

delete from newWordforms where deleteMe=1;

insert into wordforms (wordform) select distinct wordform from newWordforms;


--
-- join the new information with the uploaded lexicon
--

create temporary table uploadPlus select upload.*, wordforms.wordform_id, lemmata.lemma_id
from
 upload, lemmata, wordforms
where
 upload.wordform = wordforms.wordform
 and lemmata.modern_lemma = upload.lemma
 and lemmata.lemma_part_of_speech = upload.lemma_part_of_speech;


-- again, take some care to insert only NEW analyses into the lexicon


alter table uploadPlus add column deleteMe int(10);
alter table uploadPlus add index(wordform_id), add index(lemma_id);

update uploadPlus, analyzed_wordforms
set 
  uploadPlus.deleteMe=1 
where
  uploadPlus.lemma_id=analyzed_wordforms.lemma_id 
  and uploadPlus.wordform_id=analyzed_wordforms.wordform_id;

delete from uploadPlus where deleteMe=1;

insert into analyzed_wordforms (lemma_id, wordform_id) select distinct lemma_id, wordform_id from uploadPlus;
update wordforms set wordform_lowercase=lcase(wordform);


