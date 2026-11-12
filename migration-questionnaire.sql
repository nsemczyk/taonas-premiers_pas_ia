-- Questionnaires de satisfaction décrits par la formation (clé 'questionnaire'
-- de formations/<slug>/formation.php, niveau 2 pour commencer) : une ligne par
-- avis, réponses en JSON. Le questionnaire historique du niveau 1 garde sa
-- table `satisfaction`. Nominatif : l'avis porte le participant de la séance
-- (prénom saisi à l'arrivée) ; nécessite exercices/equipes/migration.sql.
--
-- À jouer avec un compte administrateur. Sans effet s'il est rejoué.
--
--   mysql --default-character-set=utf8mb4 -u root -p formation_ia < migration-questionnaire.sql

CREATE TABLE IF NOT EXISTS `satisfaction_reponses` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `formation`    VARCHAR(32)  NOT NULL,
  `session_code` VARCHAR(32)  NOT NULL,               -- journée (date locale), comme `satisfaction`
  `participant_id` INT UNSIGNED DEFAULT NULL,         -- participant de la séance (le prénom reste si on le retire)
  `prenom`       VARCHAR(100) DEFAULT NULL,           -- prénom saisi à l'arrivée, conservé avec l'avis
  `nom`          VARCHAR(100) DEFAULT NULL,           -- inutilisé
  `reponses`     TEXT         NOT NULL,               -- JSON : clé de question => réponse
  `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_formation` (`formation`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Base qui avait déjà la table, avant le passage au questionnaire nominatif
ALTER TABLE `satisfaction_reponses`
  ADD COLUMN IF NOT EXISTS `participant_id` INT UNSIGNED DEFAULT NULL AFTER `session_code`,
  ADD KEY IF NOT EXISTS `idx_participant` (`participant_id`);
