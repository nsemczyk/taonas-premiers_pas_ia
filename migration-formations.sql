-- Plusieurs formations sur le même site : chaque étape, quiz, avis et lien
-- d'évaluation à froid appartient à une formation (dossier formations/<slug>/).
-- Les résultats de quiz suivent leur quiz, les évaluations à froid leur lien.
--
-- Tout l'existant est rattaché au niveau 1 ('n1') par la valeur par défaut :
-- rien n'est à ressaisir, et le code d'avant cette migration fonctionne encore
-- une fois qu'elle est jouée. Ordre de mise en production : d'abord ce fichier,
-- ensuite le code.
--
-- À jouer une seule fois, avec un compte administrateur (l'utilisateur
-- applicatif n'a que SELECT/INSERT/UPDATE/DELETE). Sans effet s'il est rejoué.
--
--   mysql --default-character-set=utf8mb4 -u root -p formation_ia < migration-formations.sql

ALTER TABLE `etapes`
  ADD COLUMN IF NOT EXISTS `formation` varchar(32) NOT NULL DEFAULT 'n1',
  DROP INDEX IF EXISTS `cle`,
  ADD UNIQUE KEY IF NOT EXISTS `uq_formation_cle` (`formation`, `cle`);

-- Deux formations peuvent chacune avoir leur « quiz-final »
ALTER TABLE `quizzes`
  ADD COLUMN IF NOT EXISTS `formation` varchar(32) NOT NULL DEFAULT 'n1',
  DROP INDEX IF EXISTS `slug`,
  ADD UNIQUE KEY IF NOT EXISTS `uq_formation_slug` (`formation`, `slug`);

ALTER TABLE `satisfaction`
  ADD COLUMN IF NOT EXISTS `formation` varchar(32) NOT NULL DEFAULT 'n1',
  ADD KEY IF NOT EXISTS `idx_formation` (`formation`, `created_at`);

ALTER TABLE `eval_froide_tokens`
  ADD COLUMN IF NOT EXISTS `formation` varchar(32) NOT NULL DEFAULT 'n1',
  ADD KEY IF NOT EXISTS `idx_formation` (`formation`);
