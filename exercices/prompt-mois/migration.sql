-- Un prompt pour dans un mois (niveau 2) : chaque stagiaire écrit un prompt
-- réutilisable en 7 blocs, l'analyse et l'améliore (version 1, version 2).
-- Une fiche par participant et par séance, en JSON ; la dernière demande de
-- retour au formateur.
--
-- Nécessite exercices/equipes/migration.sql (table participants). À jouer avec
-- un compte administrateur. Sans effet s'il est rejoué.
--
--   mysql --default-character-set=utf8mb4 -u root -p formation_ia < exercices/prompt-mois/migration.sql

CREATE TABLE IF NOT EXISTS `prompt_mois_fiches` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `formation`      VARCHAR(32)  NOT NULL,
  `seance`         DATE         NOT NULL,
  `participant_id` INT UNSIGNED NOT NULL,
  `donnees`        MEDIUMTEXT   NOT NULL,               -- JSON : tâche, version 1, analyse, version 2
  `updated_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fiche` (`formation`, `seance`, `participant_id`),
  CONSTRAINT `fk_prompt_mois_participant` FOREIGN KEY (`participant_id`)
    REFERENCES `participants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Retour du formateur : la dernière demande de chaque participant (copie de
-- ce qu'il a envoyé), l'analyse préparée avec Claude et relue, ou le refus.
CREATE TABLE IF NOT EXISTS `prompt_mois_retours` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `formation`      VARCHAR(32)  NOT NULL,
  `seance`         DATE         NOT NULL,
  `participant_id` INT UNSIGNED NOT NULL,
  `etat`           VARCHAR(10)  NOT NULL DEFAULT 'attente',   -- attente | brouillon | envoye | refuse
  `tache`          TEXT         NOT NULL,
  `prompt`         TEXT         NOT NULL,                     -- version 1 assemblée
  `resultat`       MEDIUMTEXT   NOT NULL,                     -- collé par le stagiaire
  `bon`            TEXT         NOT NULL,
  `manque`         TEXT         NOT NULL,
  `analyse`        TEXT         NOT NULL,                     -- proposée par Claude, relue par le formateur
  `message`        VARCHAR(500) NOT NULL DEFAULT '',          -- mot du formateur en cas de refus
  `version`        TINYINT UNSIGNED NOT NULL DEFAULT 1,       -- version du prompt envoyée (la plus récente)
  `supplement`     TINYINT(1)   NOT NULL DEFAULT 0,           -- 1 : le formateur autorise une demande de plus
  `demande_at`     DATETIME     NOT NULL,
  `updated_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_retour` (`formation`, `seance`, `participant_id`),
  CONSTRAINT `fk_prompt_mois_retour_participant` FOREIGN KEY (`participant_id`)
    REFERENCES `participants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table créée par une version précédente de ce script : colonnes ajoutées depuis
ALTER TABLE `prompt_mois_retours`
  ADD COLUMN IF NOT EXISTS `version` TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER `message`,
  ADD COLUMN IF NOT EXISTS `supplement` TINYINT(1) NOT NULL DEFAULT 0 AFTER `version`;
