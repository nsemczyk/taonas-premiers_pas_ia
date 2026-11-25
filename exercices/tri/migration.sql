-- Jeu de tri (niveau 2) : chaque stagiaire range 12 cartes dans 5 catégories ;
-- score d'équipe = moyenne de ses membres. Correction carte par carte au
-- vidéoprojecteur.
--
-- Nécessite exercices/equipes/migration.sql (table participants). À jouer avec
-- un compte administrateur. Sans effet s'il est rejoué.
--
--   mysql --default-character-set=utf8mb4 -u root -p formation_ia < exercices/tri/migration.sql

-- La partie de la séance : tri ouvert ou clos, et où en est la correction
CREATE TABLE IF NOT EXISTS `tri_parties` (
  `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `formation` VARCHAR(32)  NOT NULL,
  `seance`    DATE         NOT NULL,
  `clos`      TINYINT(1)   NOT NULL DEFAULT 0,        -- 1 : plus de modification
  `carte`     TINYINT UNSIGNED NOT NULL DEFAULT 0,    -- 0 : correction pas commencée ; 1…N : carte projetée ; N+1 : résultats
  `revele`    TINYINT(1)   NOT NULL DEFAULT 0,        -- 1 : réponse de la carte projetée dévoilée
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_partie` (`formation`, `seance`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Le tri de chaque joueur
CREATE TABLE IF NOT EXISTS `tri_copies` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `formation`      VARCHAR(32)  NOT NULL,
  `seance`         DATE         NOT NULL,
  `participant_id` INT UNSIGNED NOT NULL,
  `reponses`       VARCHAR(500) NOT NULL DEFAULT '{}',   -- JSON : n° de carte => clé de catégorie
  `updated_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_copie` (`formation`, `seance`, `participant_id`),
  CONSTRAINT `fk_tri_participant` FOREIGN KEY (`participant_id`)
    REFERENCES `participants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
