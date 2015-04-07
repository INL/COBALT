--
-- File to design queries ...
--


(SELECT 'single' mode,
       modern_lemma, lemma_part_of_speech, gloss,
       IF(wordform IS NULL, '', wordform) wordform,
       IF(normalized_form IS NULL, '', normalized_form) modern_form,
       IF(documents.title IS NULL, '', documents.title) document_title,
       token_attestations.document_id,
       token_attestations.start_pos,
       token_attestations.end_pos,
       wordform_group_id , token_attestation_verifications.verified_by tokenAttVerifiedBy
  FROM lemmata, wordforms, token_attestation_verifications,
       documents, analyzed_wordforms
       LEFT JOIN derivations
          ON (analyzed_wordforms.derivation_id = derivations.derivation_id),
       token_attestations
       LEFT JOIN wordform_groups
          ON (token_attestations.document_id = wordform_groups.document_id
               AND token_attestations.start_pos = wordform_groups.onset)
 WHERE multiple_lemmata_analysis_id = 0
   AND analyzed_wordforms.lemma_id = lemmata.lemma_id

   AND analyzed_wordforms.wordform_id = wordforms.wordform_id
   AND analyzed_wordforms.analyzed_wordform_id =
        token_attestations.analyzed_wordform_id
   AND token_attestations.document_id = documents.document_id
   AND token_attestation_verifications.document_id = token_attestations.document_id AND token_attestation_verifications.start_pos = token_attestations.start_pos
  LIMIT 10000)

UNION

(SELECT 'multiple' mode,
       GROUP_CONCAT(modern_lemma ORDER BY part_number SEPARATOR '       ')
         modern_lemma,
       GROUP_CONCAT(lemma_part_of_speech ORDER BY part_number SEPARATOR '       ')
         lemma_part_of_speech,
       GROUP_CONCAT(gloss ORDER BY part_number SEPARATOR '      ') gloss,
       IF(wordform IS NULL, '', wordform) wordform,
       GROUP_CONCAT(IF(normalized_form IS NULL, '', normalized_form)
                    ORDER BY part_number SEPARATOR ' ') modern_form,
       IF(documents.title IS NULL, '', documents.title) document_title,
       token_attestations.document_id,
       token_attestations.start_pos,
       token_attestations.end_pos,
       wordform_group_id , token_attestation_verifications.verified_by tokenAttVerifiedBy
  FROM lemmata, multiple_lemmata_analyses, multiple_lemmata_analysis_parts
       LEFT JOIN derivations
          ON (multiple_lemmata_analysis_parts.derivation_id =
               derivations.derivation_id),
       wordforms, documents, token_attestation_verifications,
       analyzed_wordforms, token_attestations
       LEFT JOIN wordform_groups
          ON (token_attestations.document_id = wordform_groups.document_id
               AND token_attestations.start_pos = wordform_groups.onset)
 WHERE analyzed_wordforms.wordform_id = wordforms.wordform_id
   AND analyzed_wordforms.analyzed_wordform_id =
        token_attestations.analyzed_wordform_id
   AND token_attestations.document_id = documents.document_id
   AND analyzed_wordforms.multiple_lemmata_analysis_id =
        multiple_lemmata_analyses.multiple_lemmata_analysis_id

   AND multiple_lemmata_analyses.multiple_lemmata_analysis_part_id =
        multiple_lemmata_analysis_parts.multiple_lemmata_analysis_part_id
   AND multiple_lemmata_analysis_parts.lemma_id = lemmata.lemma_id
   AND token_attestation_verifications.document_id = token_attestations.document_id AND token_attestation_verifications.start_pos = token_attestations.start_pos
 GROUP BY analyzed_wordforms.analyzed_wordform_id,
          multiple_lemmata_analyses.multiple_lemmata_analysis_id,
          attestation_id
  LIMIT 10000)
 ORDER BY modern_lemma, lemma_part_of_speech, wordform_group_id, document_id,
          start_pos, modern_form, wordform