-- Le candidat qui répond à côté (niveau 2) : chaque équipe, par la main de son
-- secrétaire, liste ce qui manquait au prompt, le réécrit et colle la réponse
-- de son IA ; vote du groupe sur les copies anonymes, points à l'équipe gagnante.
--
-- Nécessite exercices/equipes/migration.sql (table participants). À jouer avec
-- un compte administrateur. Sans effet s'il est rejoué.
--
--   mysql --default-character-set=utf8mb4 -u root -p formation_ia < exercices/candidat/migration.sql

CREATE TABLE IF NOT EXISTS `candidat_parties` (
  `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `formation` VARCHAR(32)  NOT NULL,
  `seance`    DATE         NOT NULL,
  `etat`      VARCHAR(10)  NOT NULL DEFAULT 'redaction',   -- redaction | vote | revele
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_partie` (`formation`, `seance`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La copie de chaque équipe, saisie par son secrétaire
CREATE TABLE IF NOT EXISTS `candidat_copies` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `formation`     VARCHAR(32)  NOT NULL,
  `seance`        DATE         NOT NULL,
  `equipe`        VARCHAR(16)  NOT NULL,
  `secretaire_id` INT UNSIGNED DEFAULT NULL,
  `manques`       TEXT         NOT NULL,                      -- ce qui manquait au recruteur
  `prompt`        TEXT         NOT NULL,                      -- le prompt réécrit
  `resultat`      TEXT         NOT NULL,                      -- la réponse de l'IA
  `lettre`        CHAR(1)      DEFAULT NULL,                  -- A, B… au hasard à l'ouverture du vote
  `updated_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_copie` (`formation`, `seance`, `equipe`),
  CONSTRAINT `fk_candidat_secretaire` FOREIGN KEY (`secretaire_id`) REFERENCES `participants` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Un bulletin par votant ; il peut changer d'avis tant que le vote est ouvert
CREATE TABLE IF NOT EXISTS `candidat_votes` (
  `formation`      VARCHAR(32)  NOT NULL,
  `seance`         DATE         NOT NULL,
  `participant_id` INT UNSIGNED NOT NULL,
  `copie_id`       INT UNSIGNED NOT NULL,
  PRIMARY KEY (`formation`, `seance`, `participant_id`),
  CONSTRAINT `fk_candidat_vote_participant` FOREIGN KEY (`participant_id`) REFERENCES `participants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_candidat_vote_copie` FOREIGN KEY (`copie_id`) REFERENCES `candidat_copies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
