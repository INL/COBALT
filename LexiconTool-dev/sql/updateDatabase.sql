ALTER TABLE multiple_lemmata_analysis_parts ADD COLUMN derivation_id BIGINT UNSIGNED NOT NULL;
ALTER TABLE multiple_lemmata_analysis_parts DROP INDEX mlapKey;
ALTER TABLE multiple_lemmata_analysis_parts ADD UNIQUE KEY mlapKey (part_of_speech, lemma_id, derivation_id);
