-- Ouverture progressive de l'accueil : à jouer une seule fois sur une base existante.
-- Sans effet si la table est déjà en place (IF NOT EXISTS / INSERT IGNORE).
--
--   mysql --default-character-set=utf8mb4 -u root -p formation_ia < migration-etapes.sql
--
-- L'utilisateur applicatif n'a que SELECT/INSERT/UPDATE/DELETE : il ne peut pas
-- créer la table lui-même, d'où ce fichier à jouer avec un compte administrateur.

CREATE TABLE IF NOT EXISTS `etapes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cle` varchar(32) NOT NULL,
  `titre` varchar(120) NOT NULL,
  `ordre` int(10) unsigned NOT NULL,
  `ouverte` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cle` (`cle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `etapes` (`cle`, `titre`, `ordre`, `ouverte`) VALUES
('cv',       'Le CV de Sam',                 1, 0),
('offre',    'L''offre d''emploi',           2, 0),
('missions', 'Défi chrono — les 3 missions', 3, 0),
('quiz',     'Les quiz',                     4, 0),
('avis',     'Votre avis',                   5, 0);
