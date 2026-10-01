# Formation IA générative — mini-site quiz & documents

Zéro dépendance, PHP ≥ 8.0 + MariaDB + Apache.

## Installation

```bash
# 1. Base de données
mysql -u root -p -e "CREATE DATABASE formation_ia CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'formation'@'localhost' IDENTIFIED BY 'MOT_DE_PASSE';
GRANT SELECT, INSERT, UPDATE, DELETE ON formation_ia.* TO 'formation'@'localhost';"
mysql --default-character-set=utf8mb4 -u formation -p formation_ia < schema.sql

# 2. Fichiers
cp config.example.php config.php   # puis renseigner DB_* et CLE_ANIMATEUR
# déposer le tout dans le vhost, HTTPS obligatoire (navigator.clipboard l'exige)
```

`config.php` ne doit pas être versionné. Le `.htaccess` fourni en interdit
l'accès direct et donne les adresses par formation (`/n2/…`). Pour qu'il soit
lu, le vhost doit autoriser `AllowOverride All` (ou au moins `FileInfo AuthConfig`)
et `mod_rewrite` doit être actif (`a2enmod rewrite`). Sans eux, le site
fonctionne quand même, avec des adresses en `?f=n2`.

## Jour de formation

1. Générer un QR code vers `https://votredomaine.fr/` et le mettre dans les
   slides. Rien d'autre à préparer : plus de code de session à choisir ni à
   projeter, les participants n'ont que leur prénom à saisir. Les résultats
   sont regroupés par date d'enregistrement.
2. Ouvrir la télécommande sur votre téléphone :
   `https://votredomaine.fr/pilotage.php?cle=VOTRE_CLE`
3. Ouvrir le tableau de bord (pour vous, pas au projecteur pendant le quiz) :
   `https://votredomaine.fr/resultats.php?cle=VOTRE_CLE`

## Plusieurs formations

Le site sert plusieurs formations — niveau 1, niveau 2… — avec un socle commun.
Trois étages :

- **À la racine** : uniquement des pages (accueil, quiz, pilotage, tableau de
  bord, exports…). Elles sont communes à toutes les formations.
- **`core/`** : le socle PHP inclus par les pages (formation courante, étapes,
  heures locales, briques de l'accueil, évaluation à froid). Jamais servi
  directement.
- **`formations/<slug>/`** : ce qui est propre à une formation.
  `formation.php` porte ses réglages (titre, accroche, quiz bonus),
  `accueil.php` ses cartes, dans l'ordre de la journée.

La formation se choisit dans l'adresse, comme un dossier : `/n2/`,
`/n2/equipes.php`, `/n2/pilotage.php`… Ces dossiers n'existent pas sur le
disque : le `.htaccess` sert les pages communes de la racine en leur indiquant
la formation. À la racine, sans préfixe, c'est le niveau 1 (`n1`) : toutes les
adresses d'avant, liens d'évaluation à froid déjà envoyés compris, fonctionnent
à l'identique, et `/n1/…` y mène aussi. Une formation inconnue répond
« Formation inconnue » plutôt que d'enregistrer au mauvais endroit.

Sans `mod_rewrite` (serveur intégré de PHP, hébergement qui l'interdit), la même
formation s'atteint par `?f=n2` : `/equipes.php?f=n2`. Cette forme reste
acceptée partout, et les pages s'adaptent seules au mode disponible. Le site
fonctionne à la racine du domaine comme dans un sous-dossier, sans réglage.
Pour une autre formation par défaut : `define('FORMATION_DEFAUT', 'n2');` dans
`config.php`.

Côté participants, le QR code d'une journée de niveau 2 pointe donc vers
`https://votredomaine.fr/n2/`. Côté animateur, dès que deux formations sont
installées, des onglets apparaissent en tête de la télécommande, du tableau de
bord et des pages d'évaluation à froid ; chaque page ne montre et ne modifie
que la formation choisie. Un lien d'évaluation à froid, lui, porte sa formation :
le participant n'a rien à ajouter.

En base, `etapes`, `quizzes`, `satisfaction` et `eval_froide_tokens` ont une
colonne `formation` ; les résultats de quiz suivent leur quiz, les évaluations à
froid leur lien. Deux formations peuvent chacune avoir leur `quiz-final` et
leur étape `quiz`.

### Ajouter une formation

1. Copier `formations/n1/` vers `formations/n2/`, puis adapter `formation.php`
   (titres) et `accueil.php` (cartes). Les briques disponibles sont dans
   `core/blocs.php` : `texte_a_copier()`, `bloc_quiz()`, `bloc_avis()`,
   `bloc_arrivee()`, `bloc_objectif()`, `bloc_boule()`, `bloc_battle()`. Un
   dossier préfixé par `_` (brouillon) est ignoré.
2. Déclarer ses étapes dans `formation.php`, dans l'ordre de la journée :

```php
'etapes' => [
    'intro' => 'Pour commencer',
    'quiz'  => 'Les quiz',
    'avis'  => 'Votre avis',
],
```

La télécommande les crée en base à son ouverture, fermées, et aligne ensuite
titres et ordre sur cette déclaration sans toucher à ce qui est ouvert. Les clés
(`intro`, `quiz`…) sont celles qu'utilise `accueil.php`. Les quiz, eux, restent
en SQL :

```sql
INSERT INTO quizzes (formation, slug, titre) VALUES ('n2', 'quiz-final', 'Le grand quiz du niveau 2');
```

Si une table manque (migration non jouée), la télécommande l'affiche en tête,
avec la commande à lancer.

Limite actuelle, assumée : le questionnaire de satisfaction et celui de
l'évaluation à froid sont encore ceux du niveau 1. Chaque réponse est bien
rattachée à sa formation, mais les questions sont communes ; elles deviendront
propres à chaque formation quand celles du niveau 2 seront arrêtées.

### Passer une base existante en multi-formations

```bash
mysql --default-character-set=utf8mb4 -u root -p formation_ia < migration-formations.sql
```

**D'abord la migration, ensuite le code** : l'ancien code fonctionne encore
une fois la migration jouée, le nouveau a besoin de la colonne `formation`.
Tout l'existant est rattaché au niveau 1. À jouer une seule fois avec un compte
administrateur ; sans effet s'il est rejoué. Au déploiement, supprimer à la
racine les anciens `etapes.php`, `horodatage.php` et `eval-froide-*.php` (sauf
`eval-froide-liens.php` et `eval-froide-resultats.php`), désormais dans `core/`.

## Participants et équipes

Pour les formations qui en déclarent (le niveau 2), chaque participant indique
son prénom en arrivant sur l'accueil. Son téléphone garde un cookie qui le
relie à lui pour la journée : pas de compte, pas de mot de passe, et le lien
tombe de lui-même le lendemain. Ce même lien servira aux exercices individuels
et d'équipe.

`/n2/equipes.php?cle=VOTRE_CLE`, accessible aussi depuis la télécommande, liste
les arrivées en direct. Toucher une équipe y place la personne, toucher de
nouveau son équipe l'en retire. Une équipe complète refuse un membre de plus.
« Au hasard en 2 (ou 3) équipes » répartit tout le monde de façon équilibrée.
La croix retire de la séance un doublon ou une erreur de prénom. Côté
participant, l'équipe et les coéquipiers s'affichent sur l'accueil, avec un
bandeau quand l'affectation change. Un participant qui s'est trompé de prénom
touche « Ce n'est pas moi » et se déclare de nouveau.

Les équipes ne sont pas en base : elles sont déclarées dans
`formations/<slug>/formation.php`.

```php
'equipes'    => [
    'glacier' => ['nom' => 'Glacier', 'couleur' => '#47B4E8'],
    'indigo'  => ['nom' => 'Indigo',  'couleur' => '#292F6C'],
    'olive'   => ['nom' => 'Olive',   'couleur' => '#636E24'],
],
'equipe_max' => 5,
```

Le nom et la couleur se changent librement. La clé (`glacier`…) est stockée
en base : la changer en cours de journée détache les membres de l'équipe.
Une séance correspond à une formation et une journée en heure locale.

```bash
mysql --default-character-set=utf8mb4 -u root -p formation_ia < migration-equipes.sql
```

À jouer une seule fois, avec un compte administrateur. Sans effet s'il est rejoué.

## Le mur des objectifs (niveau 2)

En début de séance, chaque stagiaire complète la phrase « Ce soir, je veux
repartir avec une IA qui m'aide à… ». Sa réponse devient un post-it sur un
tableau blanc projeté.

**Côté stagiaire** : sur l'accueil, le bouton « Écrire mon objectif du jour »
apparaît quand l'étape « Mon objectif du jour » est ouverte depuis la
télécommande. Il faut s'être déclaré (prénom) juste au-dessus : le prénom signe
le post-it. Un aperçu du post-it suit la saisie. L'objectif reste modifiable tant
que l'étape est ouverte ; le post-it se met alors à jour au tableau sans bouger.

**Côté formateur** : `/n2/mur.php?cle=VOTRE_CLE`, aussi accessible depuis la
télécommande, bouton « Afficher le mur des objectifs ».

- Les post-its arrivent en direct (toutes les 4 s), collés au hasard sur une
  zone libre, légèrement de travers, dans des couleurs pastel variées.
- **Glisser** un post-it le déplace et le met au premier plan.
- La **poignée ronde** (coin haut droit, au survol) le fait tourner ; avec Maj
  enfoncé, par crans de 15°.
- Un **double-clic** l'affiche en grand, pour le lire à voix haute. Clic ou
  Échap pour refermer.
- La **croix** (coin haut gauche) le retire du tableau.
- L'**œil** (coin bas gauche) masque le prénom de ce post-it au tableau, par
  exemple quand un stagiaire ne veut pas être cité ; l'œil devient bleu plein.
  Un nouveau clic le réaffiche. Le choix est enregistré, il survit à une
  modification de l'objectif par le stagiaire et vaut aussi pour les exports.
  **Masquer les prénoms** les cache tous d'un coup, le temps d'une projection
  (réglage d'affichage, rien n'est enregistré).
- La télécommande liste les **objectifs du jour** avec leur auteur, prénoms
  masqués compris : c'est la vue privée du formateur, sur son téléphone. Le
  même bouton y masque ou réaffiche un prénom au tableau, qui suit en direct.
- **Lisibilité** : chaque texte prend automatiquement la plus grande taille qui
  tient dans son post-it, sans couper les mots. **A− / A+** (ou les touches − et
  +, y compris d'une télécommande de présentation) réduisent ou agrandissent tous
  les post-its, de 60 à 200 %, texte compris. **Masquer la phrase** retire
  l'amorce répétée sur chaque post-it, déjà écrite en titre du tableau, pour
  laisser plus de place au texte. Ces réglages sont mémorisés par le navigateur
  qui projette ; ils ne changent rien aux positions enregistrées.
- **Image PNG** télécharge le tableau tel quel. **PDF** ouvre l'impression, en
  paysage : choisir « Enregistrer au format PDF ».

Positions, rotations et ordre d'empilement sont enregistrés : un
rechargement ou un autre PC retrouvent le tableau à l'identique. Les positions
sont relatives au tableau, qui garde un format 16/9 sur tout écran.

La police manuscrite (Caveat, licence OFL) et html2canvas (licence MIT, pour
l'export PNG) sont embarqués dans le site : le mur fonctionne sans Internet en
salle, pourvu que le serveur soit joignable.

```bash
mysql --default-character-set=utf8mb4 -u root -p formation_ia < migration-objectifs.sql
```

À jouer après `migration-equipes.sql`, et à rejouer sur une base qui avait
déjà la table : il n'ajoute que ce qui manque (colonne `anonyme` du masquage
des prénoms). La télécommande signale s'il reste à le faire. Crée la table
`objectifs` et l'étape « Mon objectif du jour » du niveau 2, fermée. Sans effet
s'il est rejoué. Supprimer un participant (croix de `equipes.php`) retire aussi
son post-it.

Les boutons de la carte « Outils de la journée » de la télécommande sont
déclarés dans `formations/<slug>/formation.php`, clé `outils`
(`'mur.php' => 'Afficher le mur des objectifs'`).

## Le prompt boule de neige (niveau 2)

Un prompt écrit à plusieurs mains, en équipe : la tâche, puis le contexte, le
destinataire, les contraintes et le format ; un 5e tour passe le prompt à l'IA
et colle sa réponse.

**Côté stagiaire** : sur l'accueil, « Rejoindre la partie de mon équipe »
quand l'étape « Le prompt boule de neige » est ouverte (il faut être placé dans
une équipe). En haut de l'écran : la situation de l'équipe et l'ordre de
passage. Le joueur dont c'est le tour voit tout ce qui précède, la consigne de
son étape et une zone de saisie ; au 5e tour, un bouton **Copier le prompt**
(les quatre briques bout à bout) et une zone pour coller la réponse de l'IA.
Les autres ne voient que leurs propres briques. Tout se dévoile à l'équipe à la
fin. Les pages suivent la partie toutes seules (toutes les 3 s).

**Ordre de passage** : les membres de l'équipe dans l'ordre d'arrivée, rebouclé
quand l'équipe compte moins de 5 personnes (à 4, le 5e tour revient au joueur
1 ; à 3, les tours 4 et 5 reviennent aux joueurs 1 et 2). Un absent ? Le
retirer de l'équipe dans `equipes.php` : la main passe au suivant.

**Côté formateur** : `/n2/boule-tableau.php?cle=VOTRE_CLE`, aussi sur la
télécommande. Une colonne par équipe, une ligne par étape, remplie en direct,
lisible au vidéoprojecteur. **Masquer le contenu** n'affiche que l'avancement
(« ✅ Validé ») pendant le jeu ; on dévoile tout au débrief. **Annuler la
dernière étape** rend la main au même joueur (faute de frappe, validation par
erreur).

**Situations** : déclarées dans `formations/n2/formation.php`, clé
`boule_taches`, attribuées une par équipe dans l'ordre des équipes. Tant que
l'équipe n'a pas commencé, la liste déroulante du tableau permet d'en changer.

```bash
mysql --default-character-set=utf8mb4 -u root -p formation_ia < migration-boule.sql
```

À jouer une seule fois après `migration-equipes.sql`. Sans effet s'il est rejoué.

## Points et classement (niveau 2)

Des points pour les équipes et des points pour les joueurs, **comptés
séparément** : les uns ne s'additionnent pas aux autres. Activé par
`'points' => true` dans `formations/<slug>/formation.php`.

- **Prompt boule de neige** : sur son tableau, chaque équipe a un bouton
  **+10 pts**. Un nouveau clic les reprend ; une équipe ne peut pas les toucher
  deux fois pour la même partie.
- **`/n2/scores.php?cle=VOTRE_CLE`** (télécommande → « Points et classement ») :
  boutons −1, +1, +5, +10 pour chaque équipe et chaque joueur, et un montant
  libre avec un motif facultatif (négatif pour retirer).
- **Classement projetable** : bouton « Afficher le classement », plein écran,
  équipes à gauche, joueurs à droite avec podium, ex-aequo compris. Il suit les
  changements en direct.
- **Côté stagiaire** : son score et celui de son équipe s'affichent sur
  l'accueil, sous son prénom, et se mettent à jour tout seuls.

```bash
mysql --default-character-set=utf8mb4 -u root -p formation_ia < migration-points.sql
```

À jouer une seule fois après `migration-equipes.sql`. Sans effet s'il est rejoué.
Retirer un participant de la séance retire aussi ses points individuels.

## Prompt Battle (niveau 2)

Par manche, deux équipes s'affrontent : un volontaire chacune, une tâche tirée
au sort, 5 minutes chrono ; le reste du groupe vote pour la meilleure réponse
de l'IA, et l'équipe gagnante prend **20 points**.

**Côté formateur** : `/n2/battle-tableau.php?cle=VOTRE_CLE`, aussi sur la
télécommande. Déroulé d'une manche :

1. **Préparer une nouvelle manche**, puis **Tirer une tâche surprise** (une
   tâche déjà jouée dans la séance ne ressort pas ; on peut en retirer une autre) ;
2. choisir les deux équipes et leur volontaire (un membre de l'équipe) ;
3. **Top départ !** : la tâche s'affiche sur le téléphone des volontaires, le
   chrono démarre. **+1 minute** et **Terminer maintenant et ouvrir le vote** au besoin ;
4. à la fin du chrono, le vote s'ouvre tout seul : les copies sont projetées
   anonymement (A, B, ordre tiré au hasard). Le décompte des voix en direct au
   vidéoprojecteur est facultatif ;
5. **Clore le vote et révéler** : copie gagnante, équipes, prompts, et les
   points attribués. Un bouton par équipe les reprend (ou les donne) en cas d'erreur.

Le bouton **Ouvrir l'écran de projection** ouvre `?vue=projection` : tirage au
sort animé, chrono géant, duel, copies, puis podium. Il suit la manche en
direct.

**Côté stagiaire** : « Suivre la battle » sur l'accueil. Le volontaire écrit
son prompt et colle la réponse de son IA : tout est enregistré au fil de la
frappe, figé à la fin du chrono (3 s de grâce). Les autres votent, sauf les
deux volontaires, et peuvent changer d'avis tant que le vote est ouvert.

**Règles** : une équipe sans copie à la fin du chrono déclare forfait ; sans
aucun vote, pas de vainqueur ; ex-aequo, les deux équipes prennent 20 points.
Une manche en cours peut être abandonnée (aucun point). Les tâches sont
déclarées dans `formations/n2/formation.php`, clé `battle_taches`.

```bash
mysql --default-character-set=utf8mb4 -u root -p formation_ia < migration-battle.sql
```

À jouer une seule fois après `migration-equipes.sql`. Sans effet s'il est rejoué.

## La télécommande

`pilotage.php?cle=VOTRE_CLE` ouvre les sections de l'accueil au rythme du
groupe, pour éviter que les plus rapides prennent de l'avance sur les
exercices. Un interrupteur par étape, plus « Ouvrir l'étape suivante » et
« Tout refermer » à lancer en début de journée.

Une étape fermée **n'est pas envoyée** au navigateur : le participant n'en voit
que le titre, grisé et cadenassé. Rien à inspecter dans le code source, rien à
contourner. Un sondage toutes les 10 s fait apparaître un bandeau « Une
nouvelle étape est disponible » dès que vous ouvrez quelque chose ; le
participant l'affiche quand il veut, sans rechargement subi en pleine lecture.

La même page pilote aussi `quizzes.actif`, qui se réglait jusqu'ici en SQL :
l'étape « Les quiz » commande l'affichage du bloc sur l'accueil, les
interrupteurs du bas ouvrent et ferment chaque quiz individuellement.

Les étapes vivent dans la table `etapes` (`cle`, `titre`, `ordre`, `ouverte`).
Si la table est absente, l'accueil affiche tout comme avant et la télécommande
le signale — le site ne tombe jamais à cause d'une migration oubliée.

## Heure locale

Le serveur stocke les horodatages dans son propre fuseau, souvent UTC. Rien
n'est modifié en base : la conversion se fait à la lecture, dans
`core/horodatage.php`. Les heures affichées — tableau de bord comme PDF — sont donc
en heure locale, et le rattachement d'un enregistrement à une journée suit lui
aussi l'heure locale. Un quiz rempli à 00 h 30 appartient bien au jour même,
pas à la veille, y compris au changement d'heure.

Par défaut : serveur en `UTC`, affichage en `Europe/Paris`. Pour un autre
réglage, ajoutez dans `config.php` :

```php
define('FUSEAU_SERVEUR', 'UTC');
define('FUSEAU_AFFICHAGE', 'Europe/Paris');
```

## Le tableau de bord

`resultats.php?cle=VOTRE_CLE` s'ouvre sur la liste des journées de formation,
de la plus récente à la plus ancienne. Un clic sur une journée charge en AJAX
tout ce qui a été enregistré ce jour-là, toutes sessions et tous quiz
confondus : participants, score moyen, questions les plus ratées, satisfaction
et verbatims. La journée en cours se rafraîchit toute seule toutes les 10 s.

Le bouton « Bilan toutes sessions » donne les statistiques cumulées :
nombre de journées et de participants, score moyen global, évolution journée
par journée, moyennes par quiz, questions les plus ratées depuis le début et
satisfaction consolidée.

Les anciens avis migrés (notes à 0) sont comptés mais exclus des moyennes ;
la page le signale quand c'est le cas.

Deux paramètres facultatifs : `&d=2026-07-29` ouvre directement une journée,
`&s=PARIS-0726` ouvre la journée de cette session (compatibilité avec les
anciens liens).

### La colonne `session_code`

La saisie d'un code de session a été supprimée : le serveur écrit désormais
la date du jour (`AAAA-MM-JJ`) dans `session_code`. La colonne est conservée
telle quelle — aucune migration à jouer — et les enregistrements antérieurs
gardent leurs codes d'origine, que le tableau de bord affiche encore pour les
journées concernées. Le regroupement, lui, s'appuie sur `created_at` et non
sur cette colonne.

## Ajouter ou modifier un quiz

Tout passe par SQL (phpMyAdmin ou CLI), aucun code à toucher. Sans colonne
`formation`, le quiz va au niveau 1 :

```sql
INSERT INTO quizzes (formation, slug, titre) VALUES ('n1', 'quiz-matin', 'Quiz de mi-journée');
INSERT INTO questions (quiz_id, ordre, texte, bonne_reponse, explication)
VALUES (LAST_INSERT_ID(), 1, 'Texte de la question ?', 'VRAI', 'Explication affichée après la réponse.');
```

### Formulaire de satisfaction

Lien sur l accueil (bloc « Votre avis ») ou URL directe a projeter en fin de
journee : `https://votredomaine.fr/satisfaction.php`.
Anonyme : aucune donnee personnelle, seule la date est rattachee.
Les moyennes, repartitions et commentaires remontent en direct dans
`resultats.php`, journée par journée et en cumulé.

Installation existante (base deja creee avant cet ajout) : rejouer uniquement
le bloc `CREATE TABLE IF NOT EXISTS satisfaction` de `schema.sql`.

### Exporter les questionnaires en PDF

`export-satisfaction.php?cle=VOTRE_CLE` liste les journées ayant recueilli des
avis ; le lien figure aussi en bas du bloc Satisfaction du tableau de bord.
Le PDF restitue le questionnaire tel qu'il a été rempli — une page par avis,
cases cochées d'une croix, zones de commentaire encadrées — pour l'archivage
ou la remise au financeur.

Un avis très bavard peut déborder sur une seconde page : le commentaire est
conservé en entier plutôt que tronqué, et l'avis suivant repart toujours d'une
page neuve.

La génération repose sur **FPDF**, déposé dans `lib/fpdf/` (fichier `fpdf.php`
et dossier `font/`, qui doivent rester côte à côte). Aucun gestionnaire de
dépendances, aucun autoloader. FPDF écrit en CP1252, qui couvre l'intégralité
du français ; les caractères hors de ce jeu — emojis saisis dans un
commentaire, par exemple — sont retirés sans casser le reste du texte.

### Activer l'ouverture progressive sur une base existante

```bash
mysql --default-character-set=utf8mb4 -u root -p formation_ia < migration-etapes.sql
```

À jouer une seule fois, avec un compte administrateur : l'utilisateur
applicatif n'a que `SELECT/INSERT/UPDATE/DELETE` et ne peut pas créer la table.
Le script est sans effet s'il est rejoué.

Désactiver un quiz sans le supprimer : `UPDATE quizzes SET actif = 0 WHERE slug = '...';`
Il disparaît de l'accueil et `save.php` refuse les enregistrements.

## Évaluation à froid (suivi après formation)

Questionnaire envoyé quelques semaines après la formation, pour mesurer ce que
les participants ont réellement réutilisé. **Il n'est accessible depuis aucune
page publique** : chaque participant reçoit un lien personnel à usage unique.

### Générer et suivre les liens

`eval-froide-liens.php?cle=VOTRE_CLE` génère un lien par participant (avec un
libellé facultatif : nom, email…) et affiche leur statut — *en attente* ou
*répondu*. Copiez chaque lien dans le message de relance envoyé au participant.

Un lien vaut `evaluation-froide.php?t=TOKEN`. **Une seule réponse par lien** :
après envoi, le token est consommé (colonne `used_at`) et une nouvelle visite
affiche « Vous avez déjà répondu ». La consommation est atomique — un double-clic
ou un rechargement n'enregistre jamais deux fois.

### Consulter et exporter les réponses

`eval-froide-resultats.php?cle=VOTRE_CLE` (aussi accessible depuis le tableau de
bord) présente une synthèse (répartition de chaque question) puis le détail de
chaque réponse. Le lien « Exporter en PDF » produit `export-eval-froide.php`,
un questionnaire par page, cases cochées — même rendu FPDF que l'export de
satisfaction, pour l'archivage ou le financeur.

Le champ Nom / Prénom est facultatif ; le libellé du lien permet de retrouver
qui a répondu même sans nom saisi.

### Être prévenu par mail à chaque réponse

Facultatif. Une fois configuré, chaque questionnaire rempli déclenche un mail
portant en pièce jointe **la page PDF de cette réponse seulement** — même mise
en page que l'export complet — nommée d'après le token du lien utilisé, par
exemple `a1b2c3d4e5f60718293a4b5c6d7e8f90.pdf`. Le corps du message se limite à
l'essentiel (qui, quand, compteur de réponses) : le détail est dans le PDF.

Le mail part après l'affichage de la page de remerciement : si le serveur de
mail est lent ou injoignable, la réponse est déjà enregistrée et le participant
n'attend pas. Un échec d'envoi est seulement journalisé (`error_log`) et ne
casse jamais le questionnaire. Si FPDF est absent, le mail part quand même,
sans pièce jointe et avec les réponses en clair dans le corps.

L'envoi passe par un serveur SMTP authentifié (`lib/smtp.php`), pas par la
fonction `mail()` de PHP : la plupart des hébergements mutualisés n'ont pas de
serveur de mail local, et les messages envoyés sans expéditeur authentifié
finissent en indésirables.

**Avec une adresse Gmail**, le mot de passe du compte ne fonctionne pas : il
faut un *mot de passe d'application*.

1. Activer la validation en deux étapes sur le compte Google
   (https://myaccount.google.com/security) — sans elle, l'étape suivante
   n'apparaît pas.
2. Créer un mot de passe d'application sur
   https://myaccount.google.com/apppasswords. Google affiche 16 caractères ;
   c'est cette valeur qui va dans `SMTP_PASS` (les espaces sont acceptés).
3. Reporter le bloc « Notification par mail » de `config.example.php` dans
   `config.php`, avec `MAIL_ACTIF` à `true`, l'adresse Gmail dans `SMTP_USER`
   et `MAIL_EXPEDITEUR`, et la ou les adresses à prévenir dans `MAIL_DEST`.

Gmail impose que `MAIL_EXPEDITEUR` soit l'adresse du compte `SMTP_USER` (ou un
alias validé dans « Envoyer des e-mails en tant que ») : toute autre valeur est
réécrite. Quota : 500 messages par jour, très au-delà des besoins ici.

Vérifier la configuration avant la vraie campagne :

```bash
php test-mail.php              # envoie un message d'essai
php test-mail.php derniere     # renvoie la notification de la dernière réponse reçue
```

Sans accès SSH, les mêmes tests depuis un navigateur :
`test-mail.php?cle=VOTRE_CLE` et `test-mail.php?cle=VOTRE_CLE&derniere=1`.
Le script affiche la configuration lue et, en cas d'échec, le message exact du
serveur. `test-mail.php` n'est utile qu'à la mise en place et peut être
supprimé ensuite.

Pannes les plus fréquentes :

| Message | Cause |
|---|---|
| `535` / authentification refusée | mot de passe d'application incorrect, ou validation en deux étapes non activée |
| `connexion … impossible` | port 587 (ou 465) bloqué en sortie par l'hébergeur — demander son ouverture, ou utiliser le serveur SMTP de l'hébergeur au lieu de Gmail |
| `passage en TLS refusé` | extension `openssl` absente, ou certificats racine du système manquants |

Pour un serveur SMTP autre que Gmail, seules changent les valeurs de
`SMTP_HOTE`, `SMTP_PORT`, `SMTP_SECURITE` (`tls` pour 587, `ssl` pour 465) et
les identifiants ; le reste est identique.

### Activer sur une base existante

```bash
mysql --default-character-set=utf8mb4 -u root -p formation_ia < migration-eval-froide.sql
```

À jouer une seule fois avec un compte administrateur (l'utilisateur applicatif
ne peut pas créer de table). Sans effet s'il est rejoué. Le questionnaire lui-même
(intitulés, options) vit dans `core/eval-froide-questions.php`.

## RGPD

- Collecte minimale : prénom, réponses, score, horodatage. Ni nom, ni IP, ni cookie.
- Mention d'information affichée sur l'accueil et avant chaque quiz.
- Purge (cron mensuel conseillé) :

```
0 4 1 * * mysql -u formation -pMOT_DE_PASSE formation_ia -e "DELETE FROM resultats WHERE created_at < NOW() - INTERVAL 12 MONTH;"
```

- Suppression à la demande : `DELETE FROM resultats WHERE DATE(created_at)='2026-07-29' AND prenom='...';`
- Participants (prénom et équipe du jour) : purge avec le même cron,
  `DELETE FROM participants WHERE seance < CURDATE() - INTERVAL 12 MONTH;`
  Les post-its du mur des objectifs partent avec leur participant. Les parties
  du prompt boule de neige : `DELETE FROM boule_parties WHERE seance < CURDATE() - INTERVAL 12 MONTH;`
  Les points : `DELETE FROM points WHERE seance < CURDATE() - INTERVAL 12 MONTH;`
  Les manches de la Prompt Battle (copies et votes compris) :
  `DELETE FROM battle_manches WHERE seance < CURDATE() - INTERVAL 12 MONTH;`

## Fichiers

| Fichier | Rôle |
|---|---|
| `schema.sql` | Tables + quiz final pré-rempli (10 questions) |
| `migration-etapes.sql` | Ajout de la table `etapes` sur une base existante |
| `migration-eval-froide.sql` | Ajout des tables de l'évaluation à froid sur une base existante |
| `migration-equipes.sql` | Table des participants de la séance (prénom, équipe) sur une base existante |
| `migration-objectifs.sql` | Table du mur des objectifs et étape correspondante du niveau 2 |
| `migration-boule.sql` | Tables du prompt boule de neige (parties par équipe, briques) |
| `migration-points.sql` | Table des points de la séance (équipes et joueurs) |
| `migration-battle.sql` | Tables de la Prompt Battle (manches, copies, votes) |
| `migration-formations.sql` | Passage d'une base existante en multi-formations (colonne `formation`) |
| `config.example.php` | Identifiants BDD + clé animateur + helpers |
| `formations/<slug>/formation.php` | Réglages d'une formation : titres, accroche, quiz bonus |
| `formations/<slug>/accueil.php` | Cartes de l'accueil de cette formation, dans l'ordre de la journée |
| `.htaccess` | Adresses par formation (`/n2/…` → pages communes) et accès interdit à `config.php` |
| `core/formation.php` | Formation courante (`/n2/…` ou `?f=`), liens qui la conservent, onglets animateur — helpers seuls |
| `core/blocs.php` | Briques de l'accueil : texte à copier, bloc quiz, bloc avis — helpers seuls |
| `core/participants.php` | Participant de ce téléphone, participants de la séance, équipes — helpers seuls |
| `core/objectifs.php` | Post-its du mur : lecture, emplacement libre, couleurs — helpers seuls |
| `core/boule.php` | Prompt boule de neige : étapes, ordre de passage, validation, annulation — helpers seuls |
| `core/points.php` | Points d'équipe et individuels : attribution, totaux, rangs — helpers seuls |
| `core/battle.php` | Prompt Battle : manches, tirage, chrono, copies, votes, vainqueurs — helpers seuls |
| `core/etapes.php` | Lecture des étapes ouvertes de la formation (aucune sortie, helpers seuls) |
| `core/horodatage.php` | Conversion des horodatages serveur vers l'heure locale (helpers seuls) |
| `arrivee.php` | Arrivée d'un participant (prénom → cookie de séance), et « Ce n'est pas moi » |
| `equipes.php` | Constitution des équipes de la séance, en direct (protégé par clé) |
| `objectif.php` | Saisie de l'objectif du jour par le stagiaire, avec aperçu du post-it |
| `mur.php` | Tableau blanc des objectifs au vidéoprojecteur : déplacer, tourner, agrandir, exporter (protégé par clé) |
| `boule.php` | Prompt boule de neige côté joueur : son tour, sa brique, le prompt final |
| `boule-tableau.php` | Tableau du prompt boule de neige, une colonne par équipe, en direct (protégé par clé) |
| `battle.php` | Prompt Battle côté stagiaire : copie du volontaire, vote, résultat |
| `battle-tableau.php` | Prompt Battle côté formateur, et `?vue=projection` pour le vidéoprojecteur (protégé par clé) |
| `scores.php` | Points et classement : gestion, et `?vue=projection` pour le vidéoprojecteur (protégé par clé) |
| `pilotage.php` | Télécommande animateur : ouvre les étapes et les quiz (protégée par clé) |
| `index.php` | Accueil : en-tête commun + cartes de la formation, dévoilées au fur et à mesure |
| `quiz.php` | Le quiz : prénom → questions une par une → feedback → score |
| `save.php` | Enregistrement du résultat (POST JSON, validations serveur) |
| `resultats.php` | Tableau de bord animateur : détail par journée + bilan global (protégé par clé) |
| `export-satisfaction.php` | Export PDF des questionnaires d'une journée, un par page (protégé par clé) |
| `core/eval-froide-questions.php` | Définitions du questionnaire à froid (intitulés, options, disposition) — helpers seuls |
| `evaluation-froide.php` | Formulaire d'évaluation à froid, accès par lien unique `?t=TOKEN` (invisible depuis l'accueil) |
| `eval-froide-liens.php` | Génération et suivi des liens uniques (protégé par clé) |
| `eval-froide-resultats.php` | Consultation des réponses à froid : synthèse + détail (protégé par clé) |
| `export-eval-froide.php` | Export PDF des évaluations à froid, une par page (protégé par clé) |
| `core/eval-froide-pdf.php` | Mise en page PDF des évaluations à froid, partagée par l'export et la notification — helpers seuls |
| `core/eval-froide-notification.php` | Mail au formateur à chaque réponse à froid, page PDF en pièce jointe (inerte sans configuration) |
| `test-mail.php` | Vérification de la configuration d'envoi de mail (CLI ou `?cle=`), supprimable après |
| `lib/smtp.php` | Client SMTP minimal (serveur authentifié, TLS, pièces jointes), sans dépendance |
| `lib/html2canvas/` | html2canvas 1.4.1 (licence MIT), export PNG du mur des objectifs |
| `lib/fpdf/` | Bibliothèque FPDF (fpdf.php + font/), licence permissive, à conserver telle quelle |
| `style.css` | Styles partagés — Design System TAONAS (palette bleue, Eric Machat / Effra CC, angles vifs) |
| `assets/` | Polices TAONAS (Eric Machat, Effra CC) et logos ; référencés par `style.css` |
