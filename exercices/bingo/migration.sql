-- Bingo de l'hallucination (niveau 2) : un texte truffé d'erreurs est projeté,
-- chaque stagiaire coche sur sa grille (cases mélangées pour chacun) les
-- hallucinations repérées ; le premier bingo validé par le formateur gagne.
--
-- Nécessite exercices/equipes/migration.sql (table participants). À jouer avec
-- un compte administrateur. Sans effet s'il est rejoué.
--
--   mysql --default-character-set=utf8mb4 -u root -p formation_ia < exercices/bingo/migration.sql

-- La partie de la séance : ce que montre le vidéoprojecteur
CREATE TABLE IF NOT EXISTS `bingo_parties` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `formation`       VARCHAR(32)  NOT NULL,
  `seance`          DATE         NOT NULL,
  `texte_visible`   TINYINT(1)   NOT NULL DEFAULT 0,   -- 1 : texte projeté, les grilles s'ouvrent
  `corrige_visible` TINYINT(1)   NOT NULL DEFAULT 0,   -- 1 : erreurs surlignées et corrigé projetés
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_partie` (`formation`, `seance`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La grille de chaque joueur
CREATE TABLE IF NOT EXISTS `bingo_grilles` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `formation`      VARCHAR(32)  NOT NULL,
  `seance`         DATE         NOT NULL,
  `participant_id` INT UNSIGNED NOT NULL,
  `ordre`          VARCHAR(64)  NOT NULL,               -- JSON : position => numéro de case
  `coches`         VARCHAR(64)  NOT NULL DEFAULT '[]',  -- JSON : positions cochées
  `bingo_at`       DATETIME(6)  DEFAULT NULL,           -- annonce du bingo (ordre d'arrivée)
  `statut`         VARCHAR(10)  DEFAULT NULL,           -- attente | valide | refuse
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_grille` (`formation`, `seance`, `participant_id`),
  CONSTRAINT `fk_bingo_participant` FOREIGN KEY (`participant_id`)
    REFERENCES `participants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
