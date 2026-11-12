// Tableau blanc de l'atelier workflow : dessin SVG d'un schéma (déclencheurs,
// étapes, conditions, flèches), éditeur au pointeur (souris et tactile) et
// export PNG. Injecté tel quel dans workflow.php et workflow-tableau.php.
//
// Schéma : { titre, noeuds: [{id, type, x, y, texte}], liens: [{de, vers, etiquette}] }
// type : 'declencheur' (rond), 'etape' (boîte), 'condition' (losange, sorties oui / non).
window.Schema = (function () {
  var NS = 'http://www.w3.org/2000/svg';
  var L = 1600, H = 1000;
  // Demi-dimensions des formes, et nombre de caractères par ligne de texte
  var FORMES = {
    declencheur: { rx: 62, ry: 62, car: 11, defaut: 'Déclencheur' },
    etape:       { rx: 100, ry: 44, car: 20, defaut: 'Étape' },
    condition:   { rx: 105, ry: 66, car: 13, defaut: 'Condition ?' }
  };
  // Styles embarqués dans le SVG : ils suivent le dessin dans l'export PNG
  var STYLE =
    '.fond{fill:#F9FCFF}' +
    '.grille{stroke:#E2F3FA;stroke-width:1}' +
    '.forme{stroke-width:3}' +
    '.declencheur .forme{fill:#DDF1FB;stroke:#47B4E8}' +
    '.etape .forme{fill:#FFFFFF;stroke:#1B3E90}' +
    '.condition .forme{fill:#FFF6D6;stroke:#C9A227}' +
    '.selectionne .forme{stroke:#8A3B14;stroke-width:5}' +
    'text{font-family:Arial,Helvetica,sans-serif;font-size:16px;fill:#15162C;text-anchor:middle;dominant-baseline:central;pointer-events:none}' +
    '.vide{fill:#7193CC;font-style:italic}' +
    '.lien{stroke:#292F6C;stroke-width:2.5;fill:none}' +
    '.lien.selectionne{stroke:#8A3B14;stroke-width:4}' +
    '.zone-lien{stroke:transparent;stroke-width:18;fill:none;cursor:pointer}' +
    '.etiquette rect{fill:#FFFFFF;stroke:#292F6C;stroke-width:1.5}' +
    '.etiquette text{font-size:14px;font-weight:bold}' +
    '.etiquette.oui text{fill:#636E24}.etiquette.non text{fill:#8A3B14}' +
    '.poignee{fill:#47B4E8;stroke:#FFFFFF;stroke-width:3;cursor:crosshair}' +
    '.trace{stroke:#47B4E8;stroke-width:3;stroke-dasharray:8 6;fill:none}' +
    '.noeud{cursor:move}';

  function el(nom, attrs, parent) {
    var e = document.createElementNS(NS, nom);
    for (var k in attrs) { e.setAttribute(k, attrs[k]); }
    if (parent) { parent.appendChild(e); }
    return e;
  }

  // Texte réparti en lignes de largeur limitée (4 lignes au plus)
  function lignes(texte, car) {
    var mots = texte.split(/\s+/).filter(Boolean), out = [], ligne = '';
    mots.forEach(function (m) {
      while (m.length > car) { if (ligne) { out.push(ligne); ligne = ''; } out.push(m.slice(0, car)); m = m.slice(car); }
      if ((ligne + ' ' + m).trim().length > car) { out.push(ligne); ligne = m; } else { ligne = (ligne + ' ' + m).trim(); }
    });
    if (ligne) { out.push(ligne); }
    if (out.length > 4) { out = out.slice(0, 4); out[3] = out[3].slice(0, car - 1) + '…'; }
    return out;
  }

  // Point du bord de la forme n dans la direction (dx, dy) depuis son centre
  function bord(n, dx, dy) {
    var f = FORMES[n.type], t;
    if (!dx && !dy) { return { x: n.x, y: n.y }; }
    if (n.type === 'declencheur') { t = f.rx / Math.sqrt(dx * dx + dy * dy); }
    else if (n.type === 'etape') { t = Math.min(dx ? f.rx / Math.abs(dx) : Infinity, dy ? f.ry / Math.abs(dy) : Infinity); }
    else { t = 1 / (Math.abs(dx) / f.rx + Math.abs(dy) / f.ry); }
    return { x: n.x + dx * t, y: n.y + dy * t };
  }

  function forme(n, g) {
    var f = FORMES[n.type];
    if (n.type === 'declencheur') { el('circle', { 'class': 'forme', cx: n.x, cy: n.y, r: f.rx }, g); }
    else if (n.type === 'etape') { el('rect', { 'class': 'forme', x: n.x - f.rx, y: n.y - f.ry, width: 2 * f.rx, height: 2 * f.ry, rx: 8 }, g); }
    else {
      el('polygon', { 'class': 'forme', points: [n.x, n.y - f.ry, n.x + f.rx, n.y, n.x, n.y + f.ry, n.x - f.rx, n.y].join(' ') }, g);
    }
    var ls = n.texte ? lignes(n.texte, f.car) : [f.defaut];
    ls.forEach(function (l, i) {
      var t = el('text', { x: n.x, y: n.y + (i - (ls.length - 1) / 2) * 19 }, g);
      if (!n.texte) { t.setAttribute('class', 'vide'); }
      t.textContent = l;
    });
  }

  // Dessine le schéma dans le SVG ; sel : {noeud: id} ou {lien: index} ; poignees : éditeur
  function dessiner(svg, schema, opts) {
    opts = opts || {};
    svg.setAttribute('viewBox', '0 0 ' + L + ' ' + H);
    while (svg.firstChild) { svg.removeChild(svg.firstChild); }
    el('style', {}, svg).textContent = STYLE;
    var defs = el('defs', {}, svg);
    [['fleche', '#292F6C'], ['fleche-sel', '#8A3B14']].forEach(function (m) {
      var mk = el('marker', { id: m[0], viewBox: '0 0 10 10', refX: 9, refY: 5, markerWidth: 9, markerHeight: 9, orient: 'auto-start-reverse' }, defs);
      el('path', { d: 'M0,0 L10,5 L0,10 z', fill: m[1] }, mk);
    });
    el('rect', { 'class': 'fond', x: 0, y: 0, width: L, height: H }, svg);
    if (opts.grille) {
      for (var x = 50; x < L; x += 50) { el('line', { 'class': 'grille', x1: x, y1: 0, x2: x, y2: H }, svg); }
      for (var y = 50; y < H; y += 50) { el('line', { 'class': 'grille', x1: 0, y1: y, x2: L, y2: y }, svg); }
    }
    var sel = opts.selection || {};
    var parId = {};
    schema.noeuds.forEach(function (n) { parId[n.id] = n; });

    var gLiens = el('g', {}, svg);
    schema.liens.forEach(function (l, i) {
      var a = parId[l.de], b = parId[l.vers];
      if (!a || !b) { return; }
      var p1 = bord(a, b.x - a.x, b.y - a.y), p2 = bord(b, a.x - b.x, a.y - b.y);
      var choisi = sel.lien === i;
      var g = el('g', { 'data-lien': i }, gLiens);
      el('line', { 'class': 'lien' + (choisi ? ' selectionne' : ''), x1: p1.x, y1: p1.y, x2: p2.x, y2: p2.y,
                   'marker-end': 'url(#' + (choisi ? 'fleche-sel' : 'fleche') + ')' }, g);
      if (opts.editable) { el('line', { 'class': 'zone-lien', x1: p1.x, y1: p1.y, x2: p2.x, y2: p2.y }, g); }
      if (l.etiquette) {
        var mx = (p1.x + p2.x) / 2, my = (p1.y + p2.y) / 2;
        var e = el('g', { 'class': 'etiquette ' + l.etiquette }, g);
        el('rect', { x: mx - 22, y: my - 13, width: 44, height: 26, rx: 13 }, e);
        el('text', { x: mx, y: my }, e).textContent = l.etiquette === 'oui' ? 'Oui' : 'Non';
      }
    });

    schema.noeuds.forEach(function (n) {
      var g = el('g', { 'class': 'noeud ' + n.type + (sel.noeud === n.id ? ' selectionne' : ''), 'data-id': n.id }, svg);
      forme(n, g);
      if (opts.editable) {
        // poignée pour tirer une flèche, sur le bord droit
        el('circle', { 'class': 'poignee', cx: n.x + FORMES[n.type].rx, cy: n.y, r: 11, 'data-poignee': n.id }, g);
      }
    });
  }

  // Export PNG du SVG (2 × la taille du repère), téléchargé sous le nom donné
  function exporter(svg, nom, schema) {
    var copie = svg.cloneNode(true);
    copie.setAttribute('xmlns', NS);
    copie.setAttribute('width', L);
    copie.setAttribute('height', H);
    // pas de poignées ni de sélection sur l'image
    if (schema) { dessiner(copie, schema, {}); }
    var donnees = new XMLSerializer().serializeToString(copie);
    var img = new Image();
    img.onload = function () {
      var c = document.createElement('canvas');
      c.width = 2 * L; c.height = 2 * H;
      var ctx = c.getContext('2d');
      ctx.scale(2, 2);
      ctx.drawImage(img, 0, 0);
      c.toBlob(function (b) {
        var a = document.createElement('a');
        a.href = URL.createObjectURL(b);
        a.download = nom;
        document.body.appendChild(a); a.click(); a.remove();
        setTimeout(function () { URL.revokeObjectURL(a.href); }, 1000);
      }, 'image/png');
    };
    img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(donnees);
  }

  // Éditeur : palette (glisser une forme sur le tableau, ou toucher pour la poser
  // au centre), déplacement, flèches tirées depuis la poignée, sélection,
  // texte et suppression. surChangement(schema) est appelé à chaque modification.
  function editeur(cfg) {
    var svg = cfg.svg, schema = cfg.schema, sel = {};
    var compteur = schema.noeuds.reduce(function (m, n) { return Math.max(m, +n.id.slice(1)); }, 0);
    var action = null;   // {type: 'deplacer'|'relier'|'poser', ...}

    function point(ev) {
      var p = svg.createSVGPoint();
      p.x = ev.clientX; p.y = ev.clientY;
      p = p.matrixTransform(svg.getScreenCTM().inverse());
      return { x: Math.max(0, Math.min(L, p.x)), y: Math.max(0, Math.min(H, p.y)) };
    }
    function noeud(id) { return schema.noeuds.filter(function (n) { return n.id === id; })[0]; }
    function changer() { rendu(); cfg.surChangement(schema); }
    function rendu() {
      dessiner(svg, schema, { editable: true, grille: true, selection: sel });
      cfg.surSelection(sel.noeud ? noeud(sel.noeud) : null, sel.lien !== undefined ? schema.liens[sel.lien] : null);
    }

    // Une flèche est-elle permise ? Renvoie un message d'erreur, ou '' si oui
    function interdit(de, vers) {
      if (de === vers) { return ''; }
      var a = noeud(de), b = noeud(vers);
      if (a.type === 'declencheur' && b.type === 'declencheur') { return 'Deux déclencheurs ne se relient pas entre eux.'; }
      if (schema.liens.some(function (l) { return l.de === de && l.vers === vers; })) { return 'Ces deux formes sont déjà reliées.'; }
      if (a.type === 'condition' && schema.liens.filter(function (l) { return l.de === de; }).length >= 2) {
        return 'Une condition a deux sorties : Oui et Non.';
      }
      return null;
    }

    function relier(de, vers) {
      var refus = interdit(de, vers);
      if (refus !== null) { if (refus) { cfg.message(refus); } return; }
      var etiquette = null;
      if (noeud(de).type === 'condition') {
        etiquette = schema.liens.some(function (l) { return l.de === de && l.etiquette === 'oui'; }) ? 'non' : 'oui';
      }
      schema.liens.push({ de: de, vers: vers, etiquette: etiquette });
      sel = { lien: schema.liens.length - 1 };
      changer();
    }

    function ajouter(type, p) {
      var n = { id: 'n' + (++compteur), type: type, x: Math.round(p.x), y: Math.round(p.y), texte: '' };
      schema.noeuds.push(n);
      sel = { noeud: n.id };
      changer();
      cfg.editerTexte();
    }

    function supprimer() {
      if (sel.noeud) {
        schema.noeuds = schema.noeuds.filter(function (n) { return n.id !== sel.noeud; });
        schema.liens = schema.liens.filter(function (l) { return l.de !== sel.noeud && l.vers !== sel.noeud; });
      } else if (sel.lien !== undefined) {
        schema.liens.splice(sel.lien, 1);
      } else { return; }
      sel = {};
      changer();
    }

    // Inverse Oui / Non sur la sortie sélectionnée d'une condition (et sur sa sœur)
    function inverser() {
      var l = schema.liens[sel.lien];
      if (!l || !l.etiquette) { return; }
      schema.liens.forEach(function (x) {
        if (x.de === l.de && x.etiquette) { x.etiquette = x.etiquette === 'oui' ? 'non' : 'oui'; }
      });
      changer();
    }

    svg.addEventListener('pointerdown', function (ev) {
      if (ev.button > 0) { return; }
      var cible = ev.target;
      var poignee = cible.getAttribute && cible.getAttribute('data-poignee');
      var gNoeud = cible.closest && cible.closest('[data-id]');
      var gLien = cible.closest && cible.closest('[data-lien]');
      if (poignee) {
        var depart = noeud(poignee);
        action = { type: 'relier', de: poignee, trace: el('line', { 'class': 'trace', x1: depart.x, y1: depart.y, x2: depart.x, y2: depart.y }, svg) };
      } else if (gNoeud) {
        var n = noeud(gNoeud.getAttribute('data-id')), p = point(ev);
        sel = { noeud: n.id };
        action = { type: 'deplacer', noeud: n, dx: n.x - p.x, dy: n.y - p.y, bouge: false };
        rendu();
      } else if (gLien) {
        sel = { lien: +gLien.getAttribute('data-lien') };
        rendu();
        return;
      } else {
        sel = {};
        rendu();
        return;
      }
      svg.setPointerCapture(ev.pointerId);
      ev.preventDefault();
    });
    svg.addEventListener('pointermove', function (ev) {
      if (!action) { return; }
      var p = point(ev);
      if (action.type === 'deplacer') {
        action.noeud.x = Math.round(p.x + action.dx);
        action.noeud.y = Math.round(p.y + action.dy);
        action.bouge = true;
        rendu();
      } else if (action.type === 'relier') {
        action.trace.setAttribute('x2', p.x);
        action.trace.setAttribute('y2', p.y);
      }
    });
    function fin(ev) {
      if (!action) { return; }
      var a = action;
      action = null;
      if (a.type === 'deplacer' && a.bouge) {
        changer();
      } else if (a.type === 'relier') {
        a.trace.remove();
        var sous = document.elementFromPoint(ev.clientX, ev.clientY);
        var g = sous && sous.closest && sous.closest('[data-id]');
        if (g && svg.contains(g)) { relier(a.de, g.getAttribute('data-id')); }
      }
    }
    svg.addEventListener('pointerup', fin);
    svg.addEventListener('pointercancel', function () { if (action && action.trace) { action.trace.remove(); } action = null; rendu(); });

    // Palette : glisser une forme sur le tableau, ou la toucher pour la poser au centre
    cfg.palette.forEach(function (bouton) {
      bouton.addEventListener('pointerdown', function (ev) {
        ev.preventDefault();
        var type = bouton.getAttribute('data-type');
        var fantome = bouton.cloneNode(true);
        fantome.className += ' fantome';
        document.body.appendChild(fantome);
        var deplace = false, x0 = ev.clientX, y0 = ev.clientY;
        function suivre(e) {
          if (Math.abs(e.clientX - x0) + Math.abs(e.clientY - y0) > 6) { deplace = true; }
          fantome.style.left = (e.clientX - fantome.offsetWidth / 2) + 'px';
          fantome.style.top = (e.clientY - fantome.offsetHeight / 2) + 'px';
        }
        function lacher(e) {
          window.removeEventListener('pointermove', suivre);
          window.removeEventListener('pointerup', lacher);
          window.removeEventListener('pointercancel', lacher);
          fantome.remove();
          var r = svg.getBoundingClientRect();
          var dedans = e.clientX >= r.left && e.clientX <= r.right && e.clientY >= r.top && e.clientY <= r.bottom;
          if (deplace && dedans) {
            ajouter(type, point(e));
          } else if (!deplace) {
            // posée au centre de la partie visible du tableau, un peu décalée à chaque fois
            var c = point({ clientX: (Math.max(r.left, 0) + Math.min(r.right, innerWidth)) / 2,
                            clientY: (Math.max(r.top, 0) + Math.min(r.bottom, innerHeight)) / 2 });
            var d = (schema.noeuds.length % 6) * 25;
            ajouter(type, { x: c.x + d, y: c.y + d });
          }
        }
        suivre(ev);
        window.addEventListener('pointermove', suivre);
        window.addEventListener('pointerup', lacher);
        window.addEventListener('pointercancel', lacher);
      });
    });

    document.addEventListener('keydown', function (ev) {
      var t = ev.target.tagName;
      if ((ev.key === 'Delete' || ev.key === 'Backspace') && t !== 'INPUT' && t !== 'TEXTAREA') {
        ev.preventDefault();
        supprimer();
      }
    });

    rendu();
    return {
      supprimer: supprimer,
      inverser: inverser,
      // texte de la forme sélectionnée
      texte: function (v) { var n = noeud(sel.noeud); if (n) { n.texte = v; changer(); } },
      vider: function () { schema.noeuds = []; schema.liens = []; sel = {}; compteur = 0; changer(); }
    };
  }

  return { dessiner: dessiner, exporter: exporter, editeur: editeur };
})();
