
SELECT wordform, token_attestations.*
  FROM analyzed_wordforms, wordforms, token_attestations
 WHERE lemma_id = 0
  AND multiple_lemmata_analysis_id = 0
  AND analyzed_wordforms.wordform_id = wordforms.wordform_id
  AND token_attestations.analyzed_wordform_id =
 analyzed_wordforms.analyzed_wordform_id
--> 500 records voor het woord 'del'

SELECT *
  FROM multiple_lemmata_analyses
 WHERE multiple_lemmata_analysis_id = 0
--> 2 records

DELETE FROM multiple_lemmata_analyses
 WHERE multiple_lemmata_analysis_id = 0
--> 2 rijen weg

DELETE FROM token_attestations
 WHERE analyzed_wordform_id = 1432398
--> 500 rijen weg

DELETE FROM analyzed_wordforms
 WHERE analyzed_wordform_id = 1432398
--> 1 rij weg