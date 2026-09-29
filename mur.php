<?php
// Le mur des objectifs, au vidéoprojecteur : un tableau blanc où arrivent les
// post-its des participants. Glisser pour déplacer, poignée ronde pour tourner,
// double-clic pour lire en grand, croix pour retirer. Tout est enregistré.
require __DIR__ . '/core/objectifs.php';

// Accès réservé à l'animateur
$cle = (string)($_GET['cle'] ?? '');
if (!hash_equals(CLE_ANIMATEUR, $cle)) {
    http_response_code(403);
    exit('Accès réservé. Ajoutez ?cle=... à l\'adresse.');
}

function repondre(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------------------------------------------------------------- API JSON --
$api = $_GET['json'] ?? '';

if ($api === 'liste') {
    repondre(['postits' => objectifs_seance()]);
}

if ($api === 'placer' || $api === 'retirer') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        repondre(['ok' => false], 405);
    }
    $in = json_decode(file_get_contents('php://input'), true);
    $id = (int)($in['id'] ?? 0);
    // Uniquement un post-it de la séance en cours de cette formation
    $seance = [formation_slug(), aujourdhui_local()];

    if ($api === 'placer') {
        $borne = fn($v, $min, $max) => max($min, min($max, (float)$v));
        $st = db()->prepare(
            'UPDATE objectifs o JOIN participants p ON p.id = o.participant_id
                SET o.x = ?, o.y = ?, o.rotation = ?, o.z = ?
              WHERE o.id = ? AND p.formation = ? AND p.seance = ?'
        );
        $st->execute([
            round($borne($in['x'] ?? 0, 0, 1 - POSTIT_LARGEUR), 4),
            round($borne($in['y'] ?? 0, 0, 1 - POSTIT_HAUTEUR), 4),
            round($borne($in['rotation'] ?? 0, -180, 180), 1),
            (int)$borne($in['z'] ?? 0, 0, 1000000),
            $id, ...$seance,
        ]);
    } else {
        $st = db()->prepare(
            'DELETE o FROM objectifs o JOIN participants p ON p.id = o.participant_id
              WHERE o.id = ? AND p.formation = ? AND p.seance = ?'
        );
        $st->execute([$id, ...$seance]);
    }
    repondre(['ok' => true]);
}

$table_ok = true;
try {
    $postits = objectifs_seance();
} catch (PDOException $e) {
    $table_ok = false;
    $postits  = [];
}
$api_url = avec_f('mur.php?cle=' . rawurlencode(CLE_ANIMATEUR));
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="assets/favicon-couleur.png">
<meta name="robots" content="noindex">
<title>Le mur des objectifs</title>
<link rel="stylesheet" href="style.css">
<style>
/* La salle : un mur clair, le tableau blanc au centre, en 16/9 */
html, body { height: 100%; }
body.mur {
  margin: 0; overflow: hidden;
  background: radial-gradient(ellipse at 50% 30%, #eceae4 0%, #d8d4cb 70%, #cbc6bb 100%);
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  font-family: var(--font-corps);
  user-select: none; -webkit-user-select: none;
}
.outils {
  position: fixed; top: 10px; right: 12px; z-index: 10;
  display: flex; gap: 8px; align-items: center;
  opacity: .25; transition: opacity .2s;
}
.outils:hover, .outils:focus-within { opacity: 1; }
.outils button, .outils a {
  font: inherit; font-size: .85rem; padding: 7px 12px; cursor: pointer; text-decoration: none;
  background: var(--paper); color: var(--marque); border: 2px solid var(--marque);
}
.outils .compte { font-size: .85rem; color: var(--indigo); background: rgba(255,255,255,.7); padding: 7px 10px; }

/* Le tableau : cadre aluminium, surface émaillée, reflets et traces de feutre effacé */
.cadre {
  --largeur: min(calc(100vw - 48px), calc((100vh - 48px) * 16 / 9));
  width: var(--largeur);
  padding: calc(var(--largeur) * 0.012);
  background: linear-gradient(145deg, #f4f5f6 0%, #b9bdc2 18%, #e9ebed 40%, #a9aeb4 62%, #dfe2e5 85%, #9ea3a9 100%);
  box-shadow: 0 18px 40px -14px rgba(40,35,25,.55), 0 3px 6px rgba(40,35,25,.25);
  border-radius: 6px;
  position: relative;
}
.tableau {
  position: relative; width: 100%; aspect-ratio: 16 / 9; overflow: hidden;
  container-type: size;
  isolation: isolate;   /* l'empilement des post-its reste dans le tableau : loupe et outils passent devant */
  background:
    radial-gradient(ellipse 30% 12% at 22% 70%, rgba(120,140,170,.07), transparent 70%),
    radial-gradient(ellipse 22% 9% at 74% 38%, rgba(110,120,150,.06), transparent 70%),
    radial-gradient(ellipse 35% 10% at 60% 88%, rgba(150,150,160,.06), transparent 70%),
    linear-gradient(115deg, rgba(255,255,255,0) 30%, rgba(255,255,255,.55) 42%, rgba(255,255,255,0) 55%),
    linear-gradient(180deg, #ffffff 0%, #f7f8f9 60%, #eff1f3 100%);
  box-shadow: inset 0 0 0 1px rgba(0,0,0,.08), inset 0 2px 8px rgba(0,0,0,.08);
}
/* Rebord porte-feutres, sous le tableau */
.rebord {
  position: absolute; left: 8%; right: 8%; bottom: calc(var(--largeur) * -0.018);
  height: calc(var(--largeur) * 0.014);
  background: linear-gradient(#c9ccd0, #8e9398);
  border-radius: 0 0 4px 4px; box-shadow: 0 4px 6px -2px rgba(0,0,0,.35);
}
.feutre {
  position: absolute; bottom: 100%; height: 60%; width: 7%;
  border-radius: 3px; box-shadow: 0 1px 1px rgba(0,0,0,.3);
}
.feutre::after { content: ''; position: absolute; right: -8%; top: 15%; width: 14%; height: 70%; background: #222; border-radius: 0 3px 3px 0; }
.titre-mur {
  position: absolute; left: 3cqw; right: 3cqw; top: 1.6cqw; margin: 0;
  font-family: 'Caveat', 'Segoe Print', cursive; font-weight: 700;
  font-size: 3.4cqw; line-height: 1.1; color: #1d4a9a; letter-spacing: .02em;
  transform: rotate(-.6deg); pointer-events: none;
}
.vide {
  position: absolute; inset: 40% 10% auto; text-align: center; margin: 0;
  font-family: 'Caveat', cursive; font-size: 2.4cqw; color: rgba(29,74,154,.35);
  pointer-events: none;
}

/* Post-its sur le tableau : taille et position relatives au tableau */
.tableau .postit {
  --taille: <?= POSTIT_LARGEUR * 100 ?>cqw;
  position: absolute; cursor: grab; touch-action: none;
  transform-origin: 50% 50%;
}
.tableau .postit.saisi { cursor: grabbing; box-shadow: 0 1px 1px rgba(0,0,0,.12), 0 1.4em 1.6em -0.6em rgba(0,0,0,.4); }
.tableau .postit.nouveau { animation: coller .5s cubic-bezier(.2,1.4,.4,1); }
@keyframes coller {
  from { opacity: 0; transform: rotate(var(--rotation)) scale(1.35) translateY(-8%); }
  to   { opacity: 1; transform: rotate(var(--rotation)) scale(1); }
}
.poignee, .retirer {
  position: absolute; width: 1.1em; height: 1.1em; border-radius: 50%;
  display: none; align-items: center; justify-content: center;
  font-family: var(--font-corps); font-size: .6em; line-height: 1;
  background: var(--paper); border: 2px solid var(--marque); color: var(--marque);
  box-shadow: 0 1px 3px rgba(0,0,0,.3);
}
.poignee { top: -.45em; right: -.45em; cursor: grab; }
.retirer { top: -.45em; left: -.45em; cursor: pointer; color: var(--brique); border-color: var(--brique); }
.tableau .postit:hover .poignee, .tableau .postit:hover .retirer,
.tableau .postit.saisi .poignee { display: flex; }
.capture .poignee, .capture .retirer { display: none !important; }

/* Lecture en grand (double-clic) */
.loupe {
  position: fixed; inset: 0; z-index: 20; display: none;
  align-items: center; justify-content: center;
  background: rgba(20,22,44,.55); cursor: zoom-out;
}
.loupe.ouverte { display: flex; }
.loupe .postit { --taille: min(70vh, 70vw); --rotation: -1.5deg; cursor: zoom-out; }

/* PDF : le tableau seul, en paysage, couleurs conservées */
@media print {
  @page { size: A4 landscape; margin: 8mm; }
  body.mur { background: #fff; display: block; height: auto; }
  .outils, .loupe { display: none !important; }
  .cadre { --largeur: 277mm; box-shadow: none; margin: 0 auto; }
  .poignee, .retirer { display: none !important; }
  * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
</style>
</head>
<body class="mur">

<div class="outils">
  <span class="compte" id="compte"></span>
  <button type="button" id="btn-png">Image PNG</button>
  <button type="button" id="btn-pdf">PDF</button>
  <a href="<?= e(avec_f('pilotage.php?cle=' . rawurlencode(CLE_ANIMATEUR))) ?>">Pilotage</a>
</div>

<div class="cadre">
  <div class="tableau" id="tableau">
    <p class="titre-mur"><?= e(OBJECTIF_AMORCE) ?>…</p>
    <p class="vide" id="vide"><?= $table_ok
        ? 'Les objectifs du groupe vont apparaître ici.'
        : 'Table absente : jouez migration-objectifs.sql' ?></p>
  </div>
  <div class="rebord">
    <span class="feutre" style="left:12%;background:#1d4a9a"></span>
    <span class="feutre" style="left:22%;background:#b3261e"></span>
    <span class="feutre" style="left:32%;background:#2e7d32"></span>
  </div>
</div>

<div class="loupe" id="loupe"></div>

<script src="lib/html2canvas/html2canvas.min.js"></script>
<script>
(function () {
  var API     = <?= json_encode($api_url) ?>;
  var LARGEUR = <?= POSTIT_LARGEUR ?>, HAUTEUR = <?= POSTIT_HAUTEUR ?>;
  var tableau = document.getElementById('tableau');
  var vide    = document.getElementById('vide');
  var loupe   = document.getElementById('loupe');
  var notes   = {};      // id -> { el, data }
  var zMax    = 0;
  var enMain  = null;    // id du post-it manipulé : le sondage ne le touche pas

  function longueur(t) { return t.length > 100 ? 'long' : (t.length > 50 ? 'moyen' : 'court'); }

  function remplir(el, d) {
    el.querySelector('.postit-texte').textContent = d.texte;
    el.querySelector('.postit-texte').dataset.longueur = longueur(d.texte);
    el.querySelector('.postit-prenom').textContent = d.prenom;
    el.style.setProperty('--papier', d.couleur);
  }

  function placer(el, d) {
    el.style.left = (d.x * 100) + '%';
    el.style.top  = (d.y * 100) + '%';
    el.style.setProperty('--rotation', d.rotation + 'deg');
    el.style.zIndex = d.z;
  }

  function creer(d, anime) {
    var el = document.createElement('div');
    el.className = 'postit' + (anime ? ' nouveau' : '');
    el.dataset.id = d.id;
    el.innerHTML = '<span class="retirer" title="Retirer ce post-it">✕</span>' +
                   '<span class="poignee" title="Tourner">⟳</span>' +
                   '<p class="postit-amorce"></p><p class="postit-texte"></p><p class="postit-prenom"></p>';
    el.querySelector('.postit-amorce').textContent = <?= json_encode(OBJECTIF_AMORCE . '…') ?>;
    remplir(el, d);
    placer(el, d);
    el.addEventListener('animationend', function () { el.classList.remove('nouveau'); });
    tableau.appendChild(el);
    notes[d.id] = { el: el, data: d };
    zMax = Math.max(zMax, d.z);
    brancher(el, d.id);
  }

  function enregistrer(id) {
    var d = notes[id].data;
    fetch(API + '&json=placer', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: d.id, x: d.x, y: d.y, rotation: d.rotation, z: d.z })
    }).catch(function () {});
  }

  // ---- manipulation à la souris ou au doigt ----
  function brancher(el, id) {
    el.addEventListener('pointerdown', function (ev) {
      if (ev.button !== 0) { return; }
      var n = notes[id], d = n.data, rect = tableau.getBoundingClientRect();
      ev.preventDefault();
      el.setPointerCapture(ev.pointerId);
      enMain = id;
      d.z = ++zMax;                       // au premier plan
      el.style.zIndex = d.z;
      el.classList.add('saisi');

      var mode = ev.target.classList.contains('poignee') ? 'tourner'
               : ev.target.classList.contains('retirer') ? 'retirer' : 'deplacer';
      var bouge = false;
      var depart = { px: ev.clientX, py: ev.clientY, x: d.x, y: d.y, r: d.rotation };
      var c = el.getBoundingClientRect();
      var centre = { x: c.left + c.width / 2, y: c.top + c.height / 2 };
      var angle0 = Math.atan2(ev.clientY - centre.y, ev.clientX - centre.x);

      function bouger(e) {
        bouge = true;
        if (mode === 'deplacer') {
          d.x = Math.max(0, Math.min(1 - LARGEUR, depart.x + (e.clientX - depart.px) / rect.width));
          d.y = Math.max(0, Math.min(1 - HAUTEUR, depart.y + (e.clientY - depart.py) / rect.height));
        } else if (mode === 'tourner') {
          var a = Math.atan2(e.clientY - centre.y, e.clientX - centre.x);
          var r = depart.r + (a - angle0) * 180 / Math.PI;
          if (e.shiftKey) { r = Math.round(r / 15) * 15; }    // Maj : crans de 15°
          d.rotation = Math.round(((r + 540) % 360 - 180) * 10) / 10;
        }
        placer(el, d);
      }
      function lacher() {
        el.removeEventListener('pointermove', bouger);
        el.removeEventListener('pointerup', lacher);
        el.removeEventListener('pointercancel', lacher);
        el.classList.remove('saisi');
        enMain = null;
        if (mode === 'retirer' && !bouge) {
          if (confirm('Retirer ce post-it du tableau ?')) { retirer(id); }
          return;
        }
        enregistrer(id);                  // position, rotation et premier plan
      }
      el.addEventListener('pointermove', bouger);
      el.addEventListener('pointerup', lacher);
      el.addEventListener('pointercancel', lacher);
    });

    el.addEventListener('dblclick', function () {
      var d = notes[id].data;
      loupe.innerHTML = '';
      var grand = document.createElement('div');
      grand.className = 'postit';
      grand.innerHTML = '<p class="postit-amorce"></p><p class="postit-texte"></p><p class="postit-prenom"></p>';
      grand.querySelector('.postit-amorce').textContent = el.querySelector('.postit-amorce').textContent;
      remplir(grand, d);
      loupe.appendChild(grand);
      loupe.classList.add('ouverte');
    });
  }

  function retirer(id) {
    fetch(API + '&json=retirer', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: id })
    }).then(function () { oublier(id); }).catch(function () {});
  }

  function oublier(id) {
    if (!notes[id]) { return; }
    notes[id].el.remove();
    delete notes[id];
  }

  loupe.addEventListener('click', function () { loupe.classList.remove('ouverte'); });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { loupe.classList.remove('ouverte'); }
  });

  // ---- arrivées et modifications en direct ----
  function synchroniser(liste, anime) {
    var vus = {};
    liste.forEach(function (d) {
      vus[d.id] = true;
      var n = notes[d.id];
      if (!n) { creer(d, anime); return; }
      if (enMain === d.id) { return; }    // on ne tire pas un post-it de la main du formateur
      if (n.data.texte !== d.texte || n.data.prenom !== d.prenom) {
        n.data.texte = d.texte; n.data.prenom = d.prenom;
        remplir(n.el, n.data);
      }
      if (n.data.x !== d.x || n.data.y !== d.y || n.data.rotation !== d.rotation || n.data.z !== d.z) {
        n.data.x = d.x; n.data.y = d.y; n.data.rotation = d.rotation; n.data.z = d.z;
        placer(n.el, n.data);
        zMax = Math.max(zMax, d.z);
      }
    });
    Object.keys(notes).forEach(function (id) {
      if (!vus[id] && +id !== enMain) { oublier(id); }
    });
    var nb = Object.keys(notes).length;
    vide.hidden = nb > 0;
    document.getElementById('compte').textContent = nb + ' post-it' + (nb > 1 ? 's' : '');
  }

  synchroniser(<?= json_encode($postits, JSON_UNESCAPED_UNICODE) ?>, false);
  setInterval(function () {
    fetch(API + '&json=liste')
      .then(function (r) { return r.json(); })
      .then(function (d) { synchroniser(d.postits, true); })
      .catch(function () {});
  }, 4000);

  // ---- exports ----
  var jour = <?= json_encode(aujourdhui_local()) ?>;
  document.getElementById('btn-png').addEventListener('click', function () {
    var cadre = document.querySelector('.cadre');
    cadre.classList.add('capture');
    document.fonts.ready.then(function () {
      return html2canvas(cadre, { scale: 2, backgroundColor: null, logging: false });
    }).then(function (canvas) {
      cadre.classList.remove('capture');
      var a = document.createElement('a');
      a.download = 'mur-des-objectifs-' + jour + '.png';
      a.href = canvas.toDataURL('image/png');
      a.click();
    }).catch(function () { cadre.classList.remove('capture'); });
  });
  document.getElementById('btn-pdf').addEventListener('click', function () { window.print(); });
})();
</script>

</body>
</html>
