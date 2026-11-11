/* =====================================================================
 *  fdm-api.js — Couche d'intégration entre le site (front) et l'API PHP.
 *
 *  Deux usages :
 *   1) Client prêt à l'emploi : window.FDM.actualites(), .evenements(),
 *      .partenaires(), .article(slug), .commentaires(slug),
 *      .posterCommentaire(...), .contact(...), .benevole(...),
 *      .newsletter(email), .don(...), .recherche(q).
 *   2) Câblage automatique des formulaires publics : il suffit d'ajouter
 *      l'attribut  data-fdm="contact|benevole|newsletter|commentaire|don"
 *      sur une balise <form> pour que l'envoi passe par l'API (sans
 *      rechargement de page), avec gestion des erreurs et message de succès.
 *
 *  Configuration : définir window.FDM_BASE avant de charger ce script si
 *  l'API n'est pas à l'URL « <origine>/api ». Exemple :
 *     <script>window.FDM_BASE = '/fdm/api';</script>
 *     <script src="assets/fdm-api.js"></script>
 * ===================================================================== */
(function () {
  'use strict';

  var BASE = (window.FDM_BASE || (location.pathname.replace(/\/[^\/]*$/, '') + '/api')).replace(/\/+$/, '');

  /* -------- Appels HTTP -------- */
  function getJSON(url) {
    return fetch(url, { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); });
  }
  function postJSON(url, data) {
    return fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify(data || {})
    }).then(function (r) { return r.json().then(function (j) { return { status: r.status, body: j }; }); });
  }
  function qs(params) {
    var p = Object.keys(params || {})
      .filter(function (k) { return params[k] !== undefined && params[k] !== null && params[k] !== ''; })
      .map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]); });
    return p.length ? ('?' + p.join('&')) : '';
  }

  /* -------- Client public -------- */
  var FDM = {
    base: BASE,
    actualites: function (opts) { return getJSON(BASE + '/actualites.php' + qs(opts)); },
    article:    function (slug) { return getJSON(BASE + '/actualites.php' + qs({ slug: slug })); },
    evenements: function (opts) { return getJSON(BASE + '/evenements.php' + qs(opts)); },
    partenaires:function ()     { return getJSON(BASE + '/partenaires.php'); },
    medias:     function ()     { return getJSON(BASE + '/medias.php'); },
    commentaires:function (slug){ return getJSON(BASE + '/commentaires.php' + qs({ slug: slug })); },
    recherche:  function (q)    { return getJSON(BASE + '/recherche.php' + qs({ q: q })); },
    posterCommentaire: function (d) { return postJSON(BASE + '/commentaires.php', d); },
    contact:    function (d)    { return postJSON(BASE + '/contact.php', d); },
    benevole:   function (d)    { return postJSON(BASE + '/benevoles.php', d); },
    newsletter: function (email){ return postJSON(BASE + '/newsletter.php', { email: email }); },
    don:        function (d)    { return postJSON(BASE + '/don_init.php', d); }
  };
  window.FDM = FDM;

  /* -------- Utilitaires d'affichage -------- */
  function echapper(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  /**
   * Rendu d'une liste d'actualités dans un conteneur.
   * container : élément ou sélecteur ; items : tableau renvoyé par l'API.
   * Utilise les classes du prototype pour un rendu cohérent.
   */
  FDM.rendreActualites = function (container, items) {
    var el = typeof container === 'string' ? document.querySelector(container) : container;
    if (!el) { return; }
    if (!items || !items.length) {
      el.innerHTML = '<p style="color:#A99490">Aucune actualité pour le moment.</p>';
      return;
    }
    el.innerHTML = items.map(function (a) {
      var media = a.image_url
        ? '<div class="fdm-media" style="width:100%;height:200px;border-radius:20px;background:#F5EFEA center/cover no-repeat url(' + echapper(a.image_url) + ')"></div>'
        : '<div class="fdm-media" style="width:100%;height:200px;border-radius:20px;background:#F5EFEA;border:1.5px dashed #DCC9C4"></div>';
      return '<article class="fdm-card" data-slug="' + echapper(a.slug) + '" data-cat="' + echapper(a.categorie) + '" '
        + 'style="cursor:pointer;background:#fff;border:1px solid #EDE4DE;border-radius:28px;padding:16px 16px 26px;display:flex;flex-direction:column;gap:16px">'
        + media
        + '<div style="display:flex;flex-direction:column;gap:12px;padding:0 12px">'
        + '<div style="display:flex;align-items:center;gap:10px">'
        + '<span style="padding:5px 12px;border-radius:999px;background:#FBF2F2;color:#990103;font-size:11px;font-weight:700">' + echapper(a.categorie) + '</span>'
        + '<span style="font-size:12.5px;color:#A99490">' + echapper(a.date_fr) + '</span></div>'
        + '<h3 style="font-size:19px;font-weight:700;line-height:1.3">' + echapper(a.titre) + '</h3>'
        + '<p style="font-size:14px;line-height:1.65;color:#7A6462">' + echapper(a.chapeau || '') + '</p>'
        + '</div></article>';
    }).join('');
  };

  FDM.rendreEvenements = function (container, items) {
    var el = typeof container === 'string' ? document.querySelector(container) : container;
    if (!el) { return; }
    if (!items || !items.length) { el.innerHTML = '<p style="color:#A99490">Aucun événement.</p>'; return; }
    el.innerHTML = items.map(function (ev) {
      var fond = ev.affiche_url
        ? 'background:#F5EFEA center/cover no-repeat url(' + echapper(ev.affiche_url) + ')'
        : 'background:#F5EFEA;border:1.5px dashed #DCC9C4';
      return '<article class="fdm-poster" data-id="' + ev.id + '" data-mois="' + ev.mois + '" '
        + 'title="' + echapper(ev.titre) + '" '
        + 'style="cursor:pointer;width:100%;aspect-ratio:1/1;border-radius:24px;' + fond
        + ';display:flex;flex-direction:column;align-items:center;justify-content:flex-end;padding:14px;color:#fff;text-shadow:0 1px 6px rgba(0,0,0,.5)">'
        + '<div style="font-weight:700;font-size:13px">' + echapper(ev.titre) + '</div>'
        + '<div style="font-size:11px;opacity:.85">' + echapper(ev.date_fr) + '</div></article>';
    }).join('');
  };

  FDM.rendrePartenaires = function (container, items) {
    var el = typeof container === 'string' ? document.querySelector(container) : container;
    if (!el) { return; }
    el.innerHTML = (items || []).map(function (p) {
      var inner = p.logo_url
        ? '<img src="' + echapper(p.logo_url) + '" alt="' + echapper(p.nom) + '" style="max-height:48px;max-width:140px;object-fit:contain">'
        : '<span style="font-weight:700;color:#7A6462">' + echapper(p.nom) + '</span>';
      return p.site_web
        ? '<a href="' + echapper(p.site_web) + '" target="_blank" rel="noopener" title="' + echapper(p.nom) + '">' + inner + '</a>'
        : '<span title="' + echapper(p.nom) + '">' + inner + '</span>';
    }).join('');
  };

  /* -------- Câblage automatique des formulaires -------- */
  function valeurs(form) {
    var o = {};
    Array.prototype.forEach.call(form.elements, function (el) {
      if (!el.name) { return; }
      if (el.type === 'checkbox') { o[el.name] = el.checked ? 1 : 0; }
      else { o[el.name] = el.value; }
    });
    return o;
  }
  function message(form, texte, ok) {
    var box = form.querySelector('.fdm-msg');
    if (!box) {
      box = document.createElement('div');
      box.className = 'fdm-msg';
      box.style.cssText = 'margin-top:14px;padding:12px 16px;border-radius:12px;font-size:14px;font-weight:600';
      form.appendChild(box);
    }
    box.style.background = ok ? '#EFF6F0' : '#FBECEC';
    box.style.color = ok ? '#2F6B37' : '#A4161A';
    box.textContent = texte;
  }

  var ROUTES = {
    contact: FDM.contact, benevole: FDM.benevole,
    commentaire: FDM.posterCommentaire, don: FDM.don,
    newsletter: function (d) { return FDM.newsletter(d.email); }
  };

  function cabler() {
    document.querySelectorAll('form[data-fdm]').forEach(function (form) {
      if (form.__fdmCable) { return; }
      form.__fdmCable = true;
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        var type = form.getAttribute('data-fdm');
        var fn = ROUTES[type];
        if (!fn) { return; }
        var data = valeurs(form);
        var btn = form.querySelector('[type=submit],button');
        if (btn) { btn.disabled = true; }
        fn(data).then(function (res) {
          var body = res.body || res;
          if (body && body.ok) {
            message(form, body.message || 'Merci, votre demande a bien été envoyée.', true);
            if (type !== 'don') { form.reset(); }
            if (type === 'don' && body.moyen === 'dohone' && body.dohone) {
              // Point d'intégration paiement : rediriger/soumettre vers Dohone ici.
              form.dispatchEvent(new CustomEvent('fdm:don', { detail: body }));
            }
          } else {
            var msg = (body && body.erreur) || 'Une erreur est survenue. Veuillez réessayer.';
            if (body && body.champs) {
              msg = Object.keys(body.champs).map(function (k) { return body.champs[k]; }).join(' ');
            }
            message(form, msg, false);
          }
        }).catch(function () {
          message(form, 'Connexion au serveur impossible. Réessayez plus tard.', false);
        }).finally(function () { if (btn) { btn.disabled = false; } });
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', cabler);
  } else { cabler(); }
  FDM.cabler = cabler; // à rappeler si des formulaires sont ajoutés dynamiquement
})();
