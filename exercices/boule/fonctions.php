<?php
// Le prompt boule de neige : chaque équipe construit un prompt brique par
// brique, un joueur par étape, puis le passe à l'IA.
//
// Tour de rôle : membres de l'équipe dans l'ordre d'arrivée, rebouclé quand
// l'équipe compte moins de 5 personnes (à 4, l'étape 5 revient au joueur 1).
// L'ordre est recalculé à chaque requête : retirer un absent de l'équipe
// (equipes.php) passe la main au suivant.
//
// Les situations à traiter sont déclarées dans formations/<slug>/formation.php,
// clé 'boule_taches' ; chaque équipe reçoit la sienne, dans l'ordre des équipes.
//
// Aucune sortie : ce fichier ne définit que des helpers.
require_once __DIR__ . '/../../core/etapes.php';
require_once __DIR__ . '/../../core/participants.php';

const BOULE_ETAPES = [
    1 => ['titre' => 'La tâche',
          'consigne' => 'Écris la tâche telle que tu la demanderais spontanément à une IA, en une phrase.'],
    2 => ['titre' => 'Le contexte',
          'consigne' => 'Lis ce qui précède et ajoute le CONTEXTE : qui parle, dans quelle situation, pourquoi, avec quels faits.'],
    3 => ['titre' => 'Le destinataire',
          'consigne' => 'Ajoute le DESTINATAIRE : à qui c\'est adressé, ce qu\'on sait de lui, ce qu\'on attend de lui.'],
    4 => ['titre' => 'Contraintes et format',
          'consigne' => 'Ajoute les CONTRAINTES et le FORMAT : longueur, ton, ce qu\'il ne faut pas dire, forme attendue.'],
    5 => ['titre' => 'La réponse de l\'IA',
          'consigne' => 'Copie le prompt de ton équipe dans ton outil d\'IA, puis colle ici sa réponse.'],
];
const BOULE_DERNIERE_BRIQUE = 4;       // au-delà : la réponse de l'IA
const BOULE_MAX_BRIQUE      = 1000;    // caractères
const BOULE_MAX_REPONSE     = 10000;   // les réponses d'IA sont longues

function boule_taches(): array
{
    return array_values(formation()['boule_taches'] ?? []);
}

// Situation attribuée d'office : une par équipe, dans l'ordre des équipes
function boule_tache_defaut(string $equipe): string
{
    $taches = boule_taches();
    if (!$taches) {
        return '';
    }
    $rang = array_search($equipe, array_keys(equipes()), true);
    return $taches[(int)$rang % count($taches)];
}

// La partie de l'équipe pour la séance, créée au premier accès
function boule_partie(string $equipe): array
{
    $seance = [formation_slug(), aujourdhui_local(), $equipe];
    $st = db()->prepare('INSERT IGNORE INTO boule_parties (formation, seance, equipe, tache) VALUES (?, ?, ?, ?)');
    $st->execute([...$seance, boule_tache_defaut($equipe)]);
    $st = db()->prepare('SELECT id, tache FROM boule_parties WHERE formation = ? AND seance = ? AND equipe = ?');
    $st->execute($seance);
    return $st->fetch();
}

// Membres de l'équipe, dans l'ordre de passage (ordre d'arrivée)
function boule_membres(string $equipe): array
{
    return array_values(array_filter(participants_seance(), fn($p) => $p['equipe'] === $equipe));
}

// Qui joue l'étape $etape (1 à 5) : roulement sur les membres
function boule_joueur(array $membres, int $etape): ?array
{
    return $membres ? $membres[($etape - 1) % count($membres)] : null;
}

// Tout l'état d'une équipe : partie, membres, briques validées, étape en cours
function boule_etat(string $equipe): array
{
    $partie  = boule_partie($equipe);
    $membres = boule_membres($equipe);
    $st = db()->prepare('SELECT etape, participant_id, prenom, texte FROM boule_briques
                         WHERE partie_id = ? ORDER BY etape');
    $st->execute([$partie['id']]);
    $briques = [];
    foreach ($st as $b) {
        $briques[(int)$b['etape']] = $b;
    }
    $etape = count($briques) + 1;               // les briques se valident dans l'ordre
    $fini  = $etape > count(BOULE_ETAPES);
    return [
        'equipe'  => $equipe,
        'partie'  => (int)$partie['id'],
        'tache'   => $partie['tache'],
        'membres' => $membres,
        'briques' => $briques,
        'etape'   => $fini ? null : $etape,
        'joueur'  => $fini ? null : boule_joueur($membres, $etape),
        'fini'    => $fini,
    ];
}

// Le prompt tel qu'il sera collé dans l'IA : les briques bout à bout
function boule_prompt(array $briques): string
{
    $lignes = [];
    for ($i = 1; $i <= BOULE_DERNIERE_BRIQUE; $i++) {
        if (isset($briques[$i])) {
            $lignes[] = $briques[$i]['texte'];
        }
    }
    return implode("\n", $lignes);
}

// Valide la brique du joueur. Refusée si ce n'est pas son tour ou si l'étape
// a déjà été validée entre-temps (double envoi) : rend false.
function boule_valider(string $equipe, array $joueur, int $etape, string $texte): bool
{
    $etat = boule_etat($equipe);
    if ($etat['etape'] !== $etape || (int)($etat['joueur']['id'] ?? 0) !== (int)$joueur['id']) {
        return false;
    }
    try {
        $st = db()->prepare('INSERT INTO boule_briques (partie_id, etape, participant_id, prenom, texte)
                             VALUES (?, ?, ?, ?, ?)');
        $st->execute([$etat['partie'], $etape, $joueur['id'], $joueur['prenom'], $texte]);
        return true;
    } catch (PDOException $e) {
        return false;   // clé unique (partie, étape) : quelqu'un a validé juste avant
    }
}

// Annule la dernière étape validée : elle revient au même joueur
function boule_annuler(string $equipe): void
{
    $partie = boule_partie($equipe);
    $st = db()->prepare('DELETE FROM boule_briques WHERE partie_id = ? ORDER BY etape DESC LIMIT 1');
    $st->execute([$partie['id']]);
}

// Change la situation d'une équipe, tant que la partie n'a pas commencé
function boule_changer_tache(string $equipe, string $tache): void
{
    $etat = boule_etat($equipe);
    if (!$etat['briques'] && in_array($tache, boule_taches(), true)) {
        $st = db()->prepare('UPDATE boule_parties SET tache = ? WHERE id = ?');
        $st->execute([$tache, $etat['partie']]);
    }
}

// Nettoie une saisie : espaces de début et de fin retirés, longueur bornée,
// retours à la ligne conservés (une réponse d'IA a des paragraphes)
function boule_texte_clean(?string $texte, int $max): string
{
    $texte = str_replace("\r\n", "\n", trim((string)$texte));
    return mb_substr($texte, 0, $max);
}

// Prompt boule de neige : le bouton vers boule.php, derrière l'étape $cle
function bloc_boule(string $cle = 'boule'): string
{
    if (!etape_ouverte($cle)) {
        return carte_verrouillee($cle);
    }
    $p = participant_courant();
    $h = '<section class="card">' . "\n"
       . '    <h2>' . e(etape_titre($cle)) . '</h2>' . "\n";
    if (!$p) {
        return $h . '    <p class="lead">Indiquez d\'abord votre prénom ci-dessus.</p>' . "\n" . '  </section>';
    }
    if (!equipe_existe($p['equipe'])) {
        return $h . '    <p class="lead">Un jeu en équipe : attendez d\'être placé dans la vôtre.</p>' . "\n" . '  </section>';
    }
    return $h . '    <p class="lead">Un prompt à plusieurs mains : chacun ajoute sa brique, à son tour, sans voir la suite.</p>' . "\n"
              . '    <a class="btn btn-primary" href="' . e(avec_f('boule.php')) . '">Rejoindre la partie de mon équipe</a>' . "\n"
              . '  </section>';
}

