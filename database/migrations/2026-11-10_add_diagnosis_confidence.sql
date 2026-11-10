-- 2026-11-10：診断の信頼度（高・中・低）と、聞いた項目の数を保存する列を足す。
-- この日より前に schema.sql で表を作った DB で1回だけ流す。前の診断は NULL（信頼度を出さない）。
USE suisen;
ALTER TABLE diagnoses
  ADD COLUMN confidence VARCHAR(6) NULL AFTER sources,
  ADD COLUMN covered_topics TINYINT UNSIGNED NULL AFTER confidence;
