-- 2026-10-13：AI の返事で聞かせた項目を残す列を足す（同じ項目を何度も聞かせないため）。
-- 2026-10-13 より前に schema.sql で表を作った DB で1回だけ流す。
USE suisen;
ALTER TABLE messages ADD COLUMN topic VARCHAR(50) NULL AFTER content;
