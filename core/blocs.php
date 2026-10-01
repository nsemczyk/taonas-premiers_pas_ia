<?php
// Briques de l'accueil, partagées par toutes les formations : chaque
// formations/<slug>/accueil.php les assemble dans l'ordre de sa journée.
// Aucune sortie : ces fonctions rendent du HTML, à afficher avec echo / <?=.
require_once __DIR__ . '/etapes.php';
require_once __DIR__ . '/participants.php';
require_once __DIR__ . '/objectifs.php';
require_once __DIR__ . '/boule.php';
require_once __DIR__ . '/points.php';
require_once __DIR__ . '/battle.php';

// Bouton « Copier » + confirmation + texte dépliable. Le script de copie est
// dans index.php ; $id doit être unique sur la page.
function texte_a_copier(string $id, string $bouton, string $confirmation, string $texte, string $style = ''): string
{
    return '<button class="btn btn-gold"' . ($style !== '' ? ' style="' . e($style) . '"' : '')
         . ' data-copy="' . e($id) . '">' . e($bouton) . '</button>' . "\n"
         . '    <div class="copied-note" id="note-' . e($id) . '">' . e($confirmation) . '</div>' . "\n"
         . '    <details class="doc">' . "\n"
         . '      <summary>Voir le texte</summary>' . "\n"
         . '      <pre id="txt-' . e($id) . '">' . e($texte) . '</pre>' . "\n"
         . '    </details>';
}

// Les quiz actifs de la formation courante, derrière l'étape $cle
function bloc_quiz(string $cle = 'quiz'): string
{
    if (!etape_ouverte($cle)) {
        return carte_verrouillee($cle);
    }
    $st = db()->prepare('SELECT slug, titre FROM quizzes WHERE actif = 1 AND formation = ? ORDER BY id');
    $st->execute([formation_slug()]);
    $quizzes = $st->fetchAll();

    $h = '<section class="card">' . "\n"
       . '    <h2>Les quiz</h2>' . "\n"
       . '    <p class="lead">Touchez un quiz pour commencer. Votre prénom suffit.</p>' . "\n";
    foreach ($quizzes as $q) {
        $h .= '      <a class="btn btn-primary" href="' . e(avec_f('quiz.php?slug=' . $q['slug'])) . '">' . "\n"
            . '        ' . e($q['titre']) . "\n"
            . '      </a>' . "\n";
    }
    if (!$quizzes) {
        $h .= '      <p class="lead">Aucun quiz ouvert pour le moment.</p>' . "\n";
    }
    return $h . '  </section>';
}

// Lien vers le questionnaire de satisfaction, derrière l'étape $cle
function bloc_avis(string $cle = 'avis'): string
{
    if (!etape_ouverte($cle)) {
        return carte_verrouillee($cle);
    }
    return '<section class="card">' . "\n"
         . '    <h2>Votre avis</h2>' . "\n"
         . '    <p class="lead">En fin de journée : 2 minutes, anonyme, pour améliorer la prochaine session.</p>' . "\n"
         . '    <a class="btn btn-ghost" href="' . e(avec_f('satisfaction.php')) . '">Donner mon avis sur la journée</a>' . "\n"
         . '  </section>';
}

// Accueil du participant : son prénom à l'arrivée, puis son équipe dès que le
// formateur l'a placé. Hors étapes : c'est la première chose à faire.
function bloc_arrivee(): string
{
    if (!participants_table_ok()) {
        return '';
    }
    $p = participant_courant();

    if (!$p) {
        return '<section class="card">' . "\n"
             . '    <h2>Bienvenue !</h2>' . "\n"
             . '    <p class="lead">Pour commencer, indiquez votre prénom : il servira à former les équipes.</p>' . "\n"
             . '    <form method="post" action="' . e(avec_f('arrivee.php')) . '">' . "\n"
             . '      <input type="hidden" name="action" value="arriver">' . "\n"
             . '      <label class="field" for="prenom-arrivee">Votre prénom</label>' . "\n"
             . '      <input class="field" id="prenom-arrivee" name="prenom" type="text" maxlength="40" required' . "\n"
             . '             autocomplete="given-name" placeholder="Par exemple : Sam">' . "\n"
             . '      <button class="btn btn-primary">C\'est moi !</button>' . "\n"
             . '    </form>' . "\n"
             . '  </section>';
    }

    $h = '<section class="card">' . "\n"
       . '    <h2>Bonjour ' . e($p['prenom']) . ' !</h2>' . "\n";
    if (equipe_existe($p['equipe'])) {
        // Par id, pas par prénom : deux Marie peuvent être dans la même équipe
        $autres = array_column(array_filter(participants_seance(),
            fn($x) => $x['equipe'] === $p['equipe'] && (int)$x['id'] !== (int)$p['id']), 'prenom');
        $h .= '    <p class="lead">Vous êtes dans l\'équipe ' . badge_equipe($p['equipe']) . '</p>' . "\n"
            . '    <p>' . ($autres ? 'Avec : ' . e(implode(', ', $autres)) . '.' : 'Vos coéquipiers arrivent.') . '</p>' . "\n";
    } else {
        $h .= '    <p class="lead">Le formateur va constituer les équipes : votre équipe s\'affichera ici.</p>' . "\n";
    }
    if (points_actifs() && points_table_ok()) {
        $pts = points_de($p);
        $h .= '    <p class="mes-points">Mes points : <strong id="points-joueur">' . $pts['joueur'] . '</strong>'
            . ($pts['equipe'] !== null ? ' · Mon équipe : <strong id="points-equipe">' . $pts['equipe'] . '</strong>' : '')
            . '</p>' . "\n";
    }
    return $h
         . '    <form method="post" action="' . e(avec_f('arrivee.php')) . '">' . "\n"
         . '      <input type="hidden" name="action" value="oublier">' . "\n"
         . '      <button class="btn-lien">Ce n\'est pas moi</button>' . "\n"
         . '    </form>' . "\n"
         . '  </section>';
}

// Mur des objectifs : le bouton vers objectif.php, derrière l'étape $cle
function bloc_objectif(string $cle = 'objectifs'): string
{
    if (!etape_ouverte($cle)) {
        return carte_verrouillee($cle);
    }
    $p        = participant_courant();
    $objectif = $p ? objectif_de((int)$p['id']) : null;

    $h = '<section class="card">' . "\n"
       . '    <h2>' . e(etape_titre($cle)) . '</h2>' . "\n";
    if (!$p) {
        return $h . '    <p class="lead">Indiquez d\'abord votre prénom ci-dessus : il signera votre post-it.</p>' . "\n"
                  . '  </section>';
    }
    if ($objectif) {
        return $h . '    <p class="lead">Votre post-it est au tableau : « ' . e($objectif['texte']) . ' »</p>' . "\n"
                  . '    <a class="btn btn-ghost" href="' . e(avec_f('objectif.php')) . '">Modifier mon objectif</a>' . "\n"
                  . '  </section>';
    }
    return $h . '    <p class="lead">« ' . e(OBJECTIF_AMORCE) . '… » Complétez la phrase : votre post-it rejoindra ceux du groupe au tableau.</p>' . "\n"
              . '    <a class="btn btn-primary" href="' . e(avec_f('objectif.php')) . '">Écrire mon objectif du jour</a>' . "\n"
              . '  </section>';
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

// Prompt Battle : le bouton vers battle.php, derrière l'étape $cle
function bloc_battle(string $cle = 'battle'): string
{
    if (!etape_ouverte($cle)) {
        return carte_verrouillee($cle);
    }
    $h = '<section class="card">' . "\n"
       . '    <h2>' . e(etape_titre($cle)) . '</h2>' . "\n";
    if (!participant_courant()) {
        return $h . '    <p class="lead">Indiquez d\'abord votre prénom ci-dessus.</p>' . "\n" . '  </section>';
    }
    return $h . '    <p class="lead">Deux volontaires, une tâche surprise, 5 minutes : le groupe vote pour la meilleure réponse.</p>' . "\n"
              . '    <a class="btn btn-primary" href="' . e(avec_f('battle.php')) . '">Suivre la battle</a>' . "\n"
              . '  </section>';
}
