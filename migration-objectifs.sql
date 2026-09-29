-- Mur des objectifs (niveau 2) : un post-it par participant, affiché sur le
-- tableau blanc du formateur (mur.php).
--
-- Position et taille sont relatives au tableau (0 à 1), pour que la disposition
-- soit la même quel que soit l'écran ou le vidéoprojecteur.
--
-- Nécessite migration-equipes.sql (table participants). À jouer avec un compte
-- administrateur. Sans effet s'il est rejoué ; rejoué sur une base plus
-- ancienne, il ajoute seulement ce qui manque (colonne `anonyme`).
--
--   mysql --default-character-set=utf8mb4 -u root -p formation_ia < migration-objectifs.sql

CREATE TABLE IF NOT EXISTS `objectifs` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `participant_id` INT UNSIGNED NOT NULL,
  `texte`          VARCHAR(160) NOT NULL,
  `anonyme`        TINYINT(1)   NOT NULL DEFAULT 0,       -- 1 : prénom masqué au tableau (choix du formateur)
  `couleur`        TINYINT UNSIGNED NOT NULL DEFAULT 0,   -- rang dans la palette pastel
  `x`              DECIMAL(6,4) NOT NULL DEFAULT 0.1,     -- coin haut-gauche, fraction de la largeur du tableau
  `y`              DECIMAL(6,4) NOT NULL DEFAULT 0.1,     -- fraction de la hauteur
  `rotation`       DECIMAL(5,1) NOT NULL DEFAULT 0,       -- degrés
  `z`              INT UNSIGNED NOT NULL DEFAULT 0,       -- ordre d'empilement
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_participant` (`participant_id`),
  CONSTRAINT `fk_objectif_participant` FOREIGN KEY (`participant_id`)
    REFERENCES `participants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ajout ultérieur : prénom masqué au tableau, sur décision du formateur
ALTER TABLE `objectifs`
  ADD COLUMN IF NOT EXISTS `anonyme` TINYINT(1) NOT NULL DEFAULT 0 AFTER `texte`;

-- L'étape qui ouvre l'exercice sur l'accueil du niveau 2, fermée par défaut
INSERT IGNORE INTO `etapes` (`formation`, `cle`, `titre`, `ordre`, `ouverte`) VALUES
('n2', 'objectifs', 'Mon objectif du jour', 1, 0);
