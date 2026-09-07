/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19  Distrib 10.11.14-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: formation_ia
-- ------------------------------------------------------
-- Server version	10.11.14-MariaDB-0ubuntu0.24.04.1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `etapes`
--

DROP TABLE IF EXISTS `etapes`;
CREATE TABLE `etapes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cle` varchar(32) NOT NULL,
  `titre` varchar(120) NOT NULL,
  `ordre` int(10) unsigned NOT NULL,
  `ouverte` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cle` (`cle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

LOCK TABLES `etapes` WRITE;
INSERT INTO `etapes` (`cle`, `titre`, `ordre`, `ouverte`) VALUES
('cv',       'Le CV de Sam',                 1, 0),
('offre',    'L\'offre d\'emploi',           2, 0),
('missions', 'Défi chrono — les 3 missions', 3, 0),
('quiz',     'Les quiz',                     4, 0),
('avis',     'Votre avis',                   5, 0);
UNLOCK TABLES;

--
-- Table structure for table `questions`
--

DROP TABLE IF EXISTS `questions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `questions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `quiz_id` int(10) unsigned NOT NULL,
  `ordre` int(10) unsigned NOT NULL,
  `texte` text NOT NULL,
  `bonne_reponse` enum('VRAI','FAUX') NOT NULL,
  `explication` text NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_quiz_ordre` (`quiz_id`,`ordre`),
  CONSTRAINT `fk_q_quiz` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `questions`
--

LOCK TABLES `questions` WRITE;
/*!40000 ALTER TABLE `questions` DISABLE KEYS */;
INSERT INTO `questions` VALUES
(1,1,1,'L\'IA générative peut inventer des informations fausses, même en ayant l\'air très sûre d\'elle.','VRAI','Ce sont les « hallucinations » — on l\'a prouvé pendant le défi « Piège ton IA ».'),
(2,1,2,'Je peux donner mon numéro de sécurité sociale ou mon RIB à une IA pour qu\'elle remplisse un dossier.','FAUX','Jamais de données sensibles : n° de sécu, RIB, mots de passe, informations médicales.'),
(3,1,3,'Si la première réponse de l\'IA ne me convient pas, je peux lui demander de la modifier.','VRAI','L\'IA se corrige à la demande : plus court, plus poli, plus simple…'),
(4,1,4,'L\'IA comprend ce qu\'elle dit, exactement comme un humain.','FAUX','Elle prédit des suites de mots probables, elle ne pense pas.'),
(5,1,5,'Plus ma question est précise (contexte, besoin, ton), meilleure est la réponse.','VRAI','C\'est la leçon du « téléphone de la question » du matin.'),
(6,1,6,'Les outils d\'IA générative sont tous payants.','FAUX','Les versions gratuites couvrent tous les usages vus aujourd\'hui.'),
(7,1,7,'Je peux parler à l\'IA avec ma voix au lieu d\'écrire.','VRAI','Le mode vocal, très utile quand écrire est difficile.'),
(8,1,8,'Une lettre de motivation écrite par l\'IA peut être envoyée telle quelle, sans relecture.','FAUX','On relit, on personnalise, on décide : c\'est vous qui signez.'),
(9,1,9,'Suivre une initiation à l\'IA générative peut s\'ajouter sur un CV.','VRAI','Cette journée en est la preuve — pensez à l\'ajouter !'),
(10,1,10,'Je dois vérifier les informations importantes que me donne l\'IA avant de m\'en servir.','VRAI','Règle d\'or n° 1 de la journée.'),
(11,2,1,'Si l\'IA répond avec beaucoup d\'assurance, c\'est que la réponse est forcément juste.','FAUX','Le ton confiant n\'est pas un gage de vérité — c\'est même souvent le piège de la « chasse aux hallucinations ».'),
(12,2,2,'Une IA se souvient de vos échanges précédents même dans un nouvel onglet, sans mémoire activée.','FAUX','Chaque conversation repart de zéro, sauf fonction mémoire explicitement activée.'),
(13,2,3,'Poser la même question avec des mots différents peut donner une réponse différente.','VRAI','La formulation compte — d\'où l\'intérêt de reformuler quand la première réponse ne convient pas.'),
(14,2,4,'Un CV en tableaux ou colonnes complexes se lit aussi bien par un logiciel de tri qu\'un CV en une seule colonne.','FAUX','Certains outils de tri automatique lisent mal les mises en page trop créatives — vu pendant l\'opération CV.'),
(15,2,5,'Toutes les IA génératives connaissent les informations du jour, même sans recherche web activée.','FAUX','Beaucoup ont une date limite de connaissances ; sans recherche activée, elles peuvent l\'ignorer.'),
(16,2,6,'Si l\'IA se trompe une fois, il ne faut plus jamais lui faire confiance.','FAUX','Ni confiance aveugle, ni rejet total : on vérifie systématiquement, comme la Règle d\'or n° 1.'),
(17,2,7,'Un CV généré par l\'IA puis personnalisé a plus de chances d\'être retenu qu\'un CV envoyé tel quel.','VRAI','C\'est vous qui signez : l\'IA aide, elle ne remplace pas la relecture.'),
(18,2,8,'Un recruteur peut repérer une lettre de motivation trop générique, même écrite avec l\'IA.','VRAI','Le style trop lisse ou impersonnel se voit — on l\'a vu pendant le jeu du recruteur.');
/*!40000 ALTER TABLE `questions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quizzes`
--

DROP TABLE IF EXISTS `quizzes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `quizzes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(64) NOT NULL,
  `titre` varchar(255) NOT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quizzes`
--

LOCK TABLES `quizzes` WRITE;
/*!40000 ALTER TABLE `quizzes` DISABLE KEYS */;
INSERT INTO `quizzes` VALUES
(1,'quiz-final','Le grand quiz de la journée',1),
(2,'quiz-bonus','Quiz bonus — pour aller plus loin',1);
/*!40000 ALTER TABLE `quizzes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `resultats`
--

DROP TABLE IF EXISTS `resultats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `resultats` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `session_code` varchar(32) NOT NULL,
  `quiz_id` int(10) unsigned NOT NULL,
  `prenom` varchar(40) NOT NULL,
  `score` int(10) unsigned NOT NULL,
  `total` int(10) unsigned NOT NULL,
  `reponses` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`reponses`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_r_quiz` (`quiz_id`),
  KEY `idx_session_quiz` (`session_code`,`quiz_id`),
  CONSTRAINT `fk_r_quiz` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `resultats`
--

LOCK TABLES `resultats` WRITE;
/*!40000 ALTER TABLE `resultats` DISABLE KEYS */;
INSERT INTO `resultats` VALUES
(1,'SAINTPETERSBOURG-2007',1,'Nicolas',10,10,'[{\"q\":1,\"r\":\"VRAI\",\"ok\":true},{\"q\":2,\"r\":\"FAUX\",\"ok\":true},{\"q\":3,\"r\":\"VRAI\",\"ok\":true},{\"q\":4,\"r\":\"FAUX\",\"ok\":true},{\"q\":5,\"r\":\"VRAI\",\"ok\":true},{\"q\":6,\"r\":\"FAUX\",\"ok\":true},{\"q\":7,\"r\":\"VRAI\",\"ok\":true},{\"q\":8,\"r\":\"FAUX\",\"ok\":true},{\"q\":9,\"r\":\"VRAI\",\"ok\":true},{\"q\":10,\"r\":\"VRAI\",\"ok\":true}]','2026-07-18 08:03:23'),
(2,'PARIS-2047',1,'Larisa',10,10,'[{\"q\":1,\"r\":\"VRAI\",\"ok\":true},{\"q\":2,\"r\":\"FAUX\",\"ok\":true},{\"q\":3,\"r\":\"VRAI\",\"ok\":true},{\"q\":4,\"r\":\"FAUX\",\"ok\":true},{\"q\":5,\"r\":\"VRAI\",\"ok\":true},{\"q\":6,\"r\":\"FAUX\",\"ok\":true},{\"q\":7,\"r\":\"VRAI\",\"ok\":true},{\"q\":8,\"r\":\"FAUX\",\"ok\":true},{\"q\":9,\"r\":\"VRAI\",\"ok\":true},{\"q\":10,\"r\":\"VRAI\",\"ok\":true}]','2026-07-20 14:55:29'),
(3,'PARIS-2007',1,'Amandine',10,10,'[{\"q\":1,\"r\":\"VRAI\",\"ok\":true},{\"q\":2,\"r\":\"FAUX\",\"ok\":true},{\"q\":3,\"r\":\"VRAI\",\"ok\":true},{\"q\":4,\"r\":\"FAUX\",\"ok\":true},{\"q\":5,\"r\":\"VRAI\",\"ok\":true},{\"q\":6,\"r\":\"FAUX\",\"ok\":true},{\"q\":7,\"r\":\"VRAI\",\"ok\":true},{\"q\":8,\"r\":\"FAUX\",\"ok\":true},{\"q\":9,\"r\":\"VRAI\",\"ok\":true},{\"q\":10,\"r\":\"VRAI\",\"ok\":true}]','2026-07-20 14:56:16'),
(4,'PARIS-2007',1,'Chiara',10,10,'[{\"q\":1,\"r\":\"VRAI\",\"ok\":true},{\"q\":2,\"r\":\"FAUX\",\"ok\":true},{\"q\":3,\"r\":\"VRAI\",\"ok\":true},{\"q\":4,\"r\":\"FAUX\",\"ok\":true},{\"q\":5,\"r\":\"VRAI\",\"ok\":true},{\"q\":6,\"r\":\"FAUX\",\"ok\":true},{\"q\":7,\"r\":\"VRAI\",\"ok\":true},{\"q\":8,\"r\":\"FAUX\",\"ok\":true},{\"q\":9,\"r\":\"VRAI\",\"ok\":true},{\"q\":10,\"r\":\"VRAI\",\"ok\":true}]','2026-07-20 14:57:27'),
(5,'PARIS-2007',1,'Paule',10,10,'[{\"q\":1,\"r\":\"VRAI\",\"ok\":true},{\"q\":2,\"r\":\"FAUX\",\"ok\":true},{\"q\":3,\"r\":\"VRAI\",\"ok\":true},{\"q\":4,\"r\":\"FAUX\",\"ok\":true},{\"q\":5,\"r\":\"VRAI\",\"ok\":true},{\"q\":6,\"r\":\"FAUX\",\"ok\":true},{\"q\":7,\"r\":\"VRAI\",\"ok\":true},{\"q\":8,\"r\":\"FAUX\",\"ok\":true},{\"q\":9,\"r\":\"VRAI\",\"ok\":true},{\"q\":10,\"r\":\"VRAI\",\"ok\":true}]','2026-07-20 14:57:31'),
(6,'PARIS-2007',1,'HELENE',10,10,'[{\"q\":1,\"r\":\"VRAI\",\"ok\":true},{\"q\":2,\"r\":\"FAUX\",\"ok\":true},{\"q\":3,\"r\":\"VRAI\",\"ok\":true},{\"q\":4,\"r\":\"FAUX\",\"ok\":true},{\"q\":5,\"r\":\"VRAI\",\"ok\":true},{\"q\":6,\"r\":\"FAUX\",\"ok\":true},{\"q\":7,\"r\":\"VRAI\",\"ok\":true},{\"q\":8,\"r\":\"FAUX\",\"ok\":true},{\"q\":9,\"r\":\"VRAI\",\"ok\":true},{\"q\":10,\"r\":\"VRAI\",\"ok\":true}]','2026-07-20 15:05:54'),
(7,'PARIS-2007',1,'Saliha',10,10,'[{\"q\":1,\"r\":\"VRAI\",\"ok\":true},{\"q\":2,\"r\":\"FAUX\",\"ok\":true},{\"q\":3,\"r\":\"VRAI\",\"ok\":true},{\"q\":4,\"r\":\"FAUX\",\"ok\":true},{\"q\":5,\"r\":\"VRAI\",\"ok\":true},{\"q\":6,\"r\":\"FAUX\",\"ok\":true},{\"q\":7,\"r\":\"VRAI\",\"ok\":true},{\"q\":8,\"r\":\"FAUX\",\"ok\":true},{\"q\":9,\"r\":\"VRAI\",\"ok\":true},{\"q\":10,\"r\":\"VRAI\",\"ok\":true}]','2026-07-20 15:06:36'),
(8,'PARIS-2907',1,'Nicolas',10,10,'[{\"q\":1,\"r\":\"VRAI\",\"ok\":true},{\"q\":2,\"r\":\"FAUX\",\"ok\":true},{\"q\":3,\"r\":\"VRAI\",\"ok\":true},{\"q\":4,\"r\":\"FAUX\",\"ok\":true},{\"q\":5,\"r\":\"VRAI\",\"ok\":true},{\"q\":6,\"r\":\"FAUX\",\"ok\":true},{\"q\":7,\"r\":\"VRAI\",\"ok\":true},{\"q\":8,\"r\":\"FAUX\",\"ok\":true},{\"q\":9,\"r\":\"VRAI\",\"ok\":true},{\"q\":10,\"r\":\"VRAI\",\"ok\":true}]','2026-07-29 10:50:38'),
(9,'PARIS-2907',2,'Nicolas',6,8,'[{\"q\":11,\"r\":\"FAUX\",\"ok\":true},{\"q\":12,\"r\":\"FAUX\",\"ok\":true},{\"q\":13,\"r\":\"VRAI\",\"ok\":true},{\"q\":14,\"r\":\"FAUX\",\"ok\":true},{\"q\":15,\"r\":\"FAUX\",\"ok\":true},{\"q\":16,\"r\":\"VRAI\",\"ok\":false},{\"q\":17,\"r\":\"FAUX\",\"ok\":false},{\"q\":18,\"r\":\"VRAI\",\"ok\":true}]','2026-07-29 10:52:59');
/*!40000 ALTER TABLE `resultats` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `satisfaction`
--

DROP TABLE IF EXISTS `satisfaction`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `satisfaction` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `session_code` varchar(32) NOT NULL,
  `prenom` varchar(100) DEFAULT NULL,
  `sat_accueil` tinyint(3) unsigned NOT NULL,
  `sat_animation` tinyint(3) unsigned NOT NULL,
  `sat_clarte` tinyint(3) unsigned NOT NULL,
  `sat_contenu` tinyint(3) unsigned NOT NULL,
  `sat_exercices` tinyint(3) unsigned NOT NULL,
  `sat_supports` tinyint(3) unsigned NOT NULL,
  `sat_duree` tinyint(3) unsigned NOT NULL,
  `capable_utiliser_ia` tinyint(3) unsigned NOT NULL,
  `capable_prompt` tinyint(3) unsigned NOT NULL,
  `capable_cv` tinyint(3) unsigned NOT NULL,
  `capable_lettre` tinyint(3) unsigned NOT NULL,
  `capable_recherche_emploi` tinyint(3) unsigned NOT NULL,
  `rythme` enum('LENT','BIEN','RAPIDE') NOT NULL,
  `apprecie` text DEFAULT NULL,
  `ameliore` text DEFAULT NULL,
  `recommande` varchar(20) NOT NULL,
  `interesse_formations` varchar(3) NOT NULL,
  `autres_formations_lesquelles` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_session` (`session_code`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `satisfaction`
--

LOCK TABLES `satisfaction` WRITE;
/*!40000 ALTER TABLE `satisfaction` DISABLE KEYS */;
INSERT INTO `satisfaction` VALUES
(1,'PARIS-2007',NULL,0,0,0,0,0,0,0,0,0,0,0,0,'BIEN',NULL,NULL,'OUI','',NULL,'2026-07-20 15:07:08'),
(2,'PARIS-2007',NULL,0,0,0,0,0,0,0,0,0,0,0,0,'BIEN',NULL,NULL,'OUI','',NULL,'2026-07-20 15:10:38'),
(3,'PARIS-2907','Nicolas',4,4,4,4,4,4,4,3,3,3,3,3,'BIEN','Tout','Tout','OUI-SANS-HESITER','OUI','automatisation','2026-07-29 09:30:49');
/*!40000 ALTER TABLE `satisfaction` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `satisfaction_backup_avant_migration`
--

DROP TABLE IF EXISTS `satisfaction_backup_avant_migration`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `satisfaction_backup_avant_migration` (
  `id` int(10) unsigned NOT NULL DEFAULT 0,
  `session_code` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `note_globale` tinyint(3) unsigned NOT NULL,
  `clarte` tinyint(3) unsigned NOT NULL,
  `utilite` tinyint(3) unsigned NOT NULL,
  `autonomie` tinyint(3) unsigned NOT NULL,
  `rythme` enum('LENT','BIEN','RAPIDE') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `moment_prefere` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `recommande` enum('OUI','PEUT-ETRE','NON') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `commentaire` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `satisfaction_backup_avant_migration`
--

LOCK TABLES `satisfaction_backup_avant_migration` WRITE;
/*!40000 ALTER TABLE `satisfaction_backup_avant_migration` DISABLE KEYS */;
INSERT INTO `satisfaction_backup_avant_migration` VALUES
(1,'PARIS-2007',5,5,5,5,'BIEN','prise','OUI','Groupe de travail par niveau','2026-07-20 15:07:08'),
(2,'PARIS-2007',5,5,5,5,'BIEN','quiz','OUI','Rallonger d\'une demi-heure ou d\'une heure','2026-07-20 15:10:38');
/*!40000 ALTER TABLE `satisfaction_backup_avant_migration` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-07-29 13:23:11

--
-- Évaluation à froid (suivi après formation). Mêmes tables que
-- migration-eval-froide.sql, incluses ici pour une installation neuve.
--

CREATE TABLE IF NOT EXISTS `eval_froide_tokens` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `token`      CHAR(32)     NOT NULL,
  `libelle`    VARCHAR(120) DEFAULT NULL,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `used_at`    TIMESTAMP    NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `eval_froide` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `token_id`       INT UNSIGNED NOT NULL,
  `nom_prenom`     VARCHAR(120) DEFAULT NULL,
  `q2_usages`      VARCHAR(255) DEFAULT NULL,
  `q2_autre`       VARCHAR(255) DEFAULT NULL,
  `q3_autonomie`   VARCHAR(20)  DEFAULT NULL,
  `q4_prompt`      VARCHAR(20)  DEFAULT NULL,
  `q5_candidature` VARCHAR(20)  DEFAULT NULL,
  `q5_usages`      VARCHAR(255) DEFAULT NULL,
  `q5_autre`       VARCHAR(255) DEFAULT NULL,
  `q6_changement`  VARCHAR(20)  DEFAULT NULL,
  `q7_entretiens`  VARCHAR(30)  DEFAULT NULL,
  `q7_ia_prepa`    VARCHAR(10)  DEFAULT NULL,
  `q8_utile`       VARCHAR(20)  DEFAULT NULL,
  `q9_autonomie`   VARCHAR(20)  DEFAULT NULL,
  `q10_apprise`    TEXT,
  `q11_freins`     VARCHAR(255) DEFAULT NULL,
  `q11_autre`      VARCHAR(255) DEFAULT NULL,
  `q12_manque`     TEXT,
  `q13_poursuivre` VARCHAR(20)  DEFAULT NULL,
  `q13_sujets`     VARCHAR(255) DEFAULT NULL,
  `q13_autre`      VARCHAR(255) DEFAULT NULL,
  `q14_situation`  VARCHAR(40)  DEFAULT NULL,
  `q14_autre`      VARCHAR(255) DEFAULT NULL,
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_token` (`token_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
