<?php
/**
 * Page Colisly : gabarit autonome, indépendant du thème.
 *
 * @package Pixfeed_Colisly_Page
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$u   = plugin_dir_url( __FILE__ );
$ver = defined( 'PIXFEED_COLISLY_PAGE_VERSION' ) ? PIXFEED_COLISLY_PAGE_VERSION : '1.0.0';
$url = get_permalink();
$og  = $u . 'assets/img/hero-poster-l.jpg';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Colisly, extension WooCommerce de réexpédition de colis | Pixfeed</title>
<meta name="description" content="Colisly transforme une boutique WooCommerce en plateforme de réexpédition de colis : réception, stockage, groupage, réexpédition et espace client. Extension WordPress gratuite, GPL.">
<link rel="canonical" href="<?php echo esc_url( $url ); ?>">
<meta property="og:type" content="website">
<meta property="og:locale" content="fr_FR">
<meta property="og:title" content="Colisly, la réexpédition de colis dans WooCommerce">
<meta property="og:description" content="Réception des colis, stockage, groupage, réexpédition et espace client. Extension WordPress gratuite.">
<meta property="og:url" content="<?php echo esc_url( $url ); ?>">
<meta property="og:image" content="<?php echo esc_url( $og ); ?>">
<meta name="twitter:card" content="summary_large_image">
<link rel="icon" href="<?php echo esc_url( $u . 'assets/img/icon.svg' ); ?>" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700;12..96,800&family=Instrument+Sans:ital,wght@0,400;0,500;0,600;1,400&family=JetBrains+Mono:wght@500;600&display=swap">
<link rel="stylesheet" href="<?php echo esc_url( $u . 'assets/css/page.css?ver=' . $ver ); ?>">
<script type="application/ld+json">
<?php
echo wp_json_encode(
	array(
		'@context'            => 'https://schema.org',
		'@type'               => 'SoftwareApplication',
		'name'                => 'Colisly Parcel Forwarding',
		'alternateName'       => 'Colisly',
		'applicationCategory' => 'BusinessApplication',
		'operatingSystem'     => 'WordPress, WooCommerce',
		'softwareVersion'     => '1.20.0',
		'inLanguage'          => array( 'fr', 'en', 'es' ),
		'license'             => 'https://www.gnu.org/licenses/gpl-2.0.html',
		'url'                 => $url,
		'downloadUrl'         => 'https://fr.wordpress.org/plugins/colisly/',
		'image'               => $og,
		'description'         => 'Réception des colis, stockage, groupage, réexpédition et espace client pour une activité de réexpédition de colis sur WooCommerce.',
		'offers'              => array( '@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'EUR' ),
		'author'              => array( '@type' => 'Organization', 'name' => 'Pixfeed', 'url' => home_url( '/' ) ),
		'video'               => array(
			'@type'        => 'VideoObject',
			'name'         => 'Colisly en 40 secondes',
			'description'  => 'Réception d’un colis, étiquette, puis le client choisit ses colis, voit le prix et paie.',
			'thumbnailUrl' => $u . 'assets/img/poster-entrepot.jpg',
			'contentUrl'   => $u . 'assets/video/entrepot.mp4',
			'uploadDate'   => '2026-09-11',
			'duration'     => 'PT40S',
		),
	),
	JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
);
?>
</script>
</head>
<body>


<header class="wrap nav">
  <a class="brand" href="#top"><svg class="logo-mark" viewBox="0 0 1024 1024" aria-hidden="true"><rect width="1024" height="1024" rx="226" fill="#7b55ad"/><path d="M512 268 L708 381 L512 494 L316 381 Z" fill="#fff" stroke="#fff" stroke-width="42" stroke-linejoin="round"/><path d="M280 444 L476 557 L476 736 L280 623 Z" fill="#fff" stroke="#fff" stroke-width="42" stroke-linejoin="round"/><path d="M744 444 L548 557 L548 736 L744 623 Z" fill="#FFC24B" stroke="#FFC24B" stroke-width="42" stroke-linejoin="round"/></svg> Colisly</a>
  <ul class="nav-links">
    <li><a href="#flux">Comment ça marche</a></li>
    <li><a href="#reception">Réception</a></li>
    <li><a href="#client">Espace client</a></li>
    <li><a href="#tarif">Tarif</a></li>
    <li><a href="#faq">FAQ</a></li>
  </ul>
  <a class="btn btn-primary" href="https://fr.wordpress.org/plugins/colisly/">Télécharger</a>
</header>

<main class="wrap" id="top">
  <section class="hero">
    <div class="hero-video" id="entrepot">
      <video id="bg" class="hero-bg" muted loop playsinline autoplay preload="auto" poster="<?php echo esc_url( $u . 'assets/img/hero-poster-l.jpg' ); ?>" aria-hidden="true" tabindex="-1"></video>
      <div class="hero-copy">
        <p class="eyebrow eyebrow-light">Extension WordPress et WooCommerce, gratuite</p>
        <h1>Votre entrepôt de réexpédition, dans WooCommerce.</h1>
        <p class="lede lede-light">Réception des colis, stockage, groupage, réexpédition et espace client. Le client paie sur votre boutique, avec vos transporteurs et vos prix.</p>
        <div class="cta-row">
          <a class="btn btn-sun" href="#demo">Voir l’extension en 40 secondes</a>
          <a class="btn btn-glass" href="https://fr.wordpress.org/plugins/colisly/">Installer depuis wordpress.org</a>
        </div>
        <ul class="proofs proofs-light">
          <li>Licence GPL, 0&nbsp;€</li>
          <li>Français, anglais, espagnol</li>
          <li>Commandes WooCommerce natives</li>
        </ul>
      </div>
    </div>

    <div class="screen" id="demo">
      <div class="screen-bar">
        <span class="dots"><span></span><span></span><span></span></span>
        <span>Colisly en 40 secondes</span>
        <div class="chapters" role="tablist" aria-label="Chapitres de la vidéo">
          <button class="chapter" role="tab" type="button" data-i="0" aria-selected="true">Côté entrepôt</button>
          <button class="chapter" role="tab" type="button" data-i="1" aria-selected="false">Côté client</button>
        </div>
      </div>
      <video id="v" playsinline muted preload="none" poster="<?php echo esc_url( $u . 'assets/img/poster-entrepot.jpg' ); ?>" src="<?php echo esc_url( $u . 'assets/video/entrepot.mp4' ); ?>" aria-label="Démonstration de Colisly"></video>
      <div class="screen-foot">
        <span id="vcap">Réception d’un colis, étiquette, liste du stock</span>
        <div class="bar"><i id="vbar"></i></div>
        <button class="chapter" id="vplay" type="button">Lecture</button>
      </div>
    </div>
  </section>

  <section class="flow" id="flux">
    <article class="step">
      <span class="n">1</span>
      <h3>Le colis arrive</h3>
      <p>Poids, dimensions, numéro de suivi, photo, commentaire interne. La référence est générée et l’étiquette s’imprime.</p>
      <span class="tag">COL000123</span>
    </article>
    <article class="step">
      <span class="n">2</span>
      <h3>Il attend en stock</h3>
      <p>Franchise de stockage selon votre politique, 15 jours par défaut. Au-delà, les frais se calculent seuls, jour par jour.</p>
      <span class="tag">15 j offerts</span>
    </article>
    <article class="step">
      <span class="n">3</span>
      <h3>Le client l’expédie</h3>
      <p>Il coche ses colis, choisit un transporteur, voit le prix. La demande devient une commande WooCommerce qu’il paie comme d’habitude.</p>
      <span class="tag">Commande #1042</span>
    </article>
  </section>

  <section class="feature" id="reception">
    <div class="feature-copy">
      <p class="eyebrow">Réception</p>
      <h2>Un colis enregistré en trente secondes, étiquette comprise.</h2>
      <p class="lede">Le formulaire suit l’ordre du comptoir : le client, le carton, les règles d’expédition. À l’enregistrement, la référence s’affiche en grand avec le bouton d’impression.</p>
      <ul>
        <li>Recherche du client par référence, nom ou e-mail</li>
        <li>Frais avancés à la livraison, droits de douane ou TVA à l’import, refacturés au coût réel</li>
        <li>Transporteurs autorisés colis par colis, groupage autorisé ou non</li>
        <li>Commentaire interne jamais visible du client</li>
      </ul>
    </div>
    <div class="shot">
      <figure class="browser" style="margin:0">
        <div class="browser-bar"><span class="dots"><span></span><span></span><span></span></span><span class="url">votre-site.fr/wp-admin › Colisly › Nouveau colis</span></div>
        <button type="button" class="zoom" data-lightbox data-caption="Bloc Informations du colis : numéro de suivi, poids, dimensions, frais avancés, commentaire interne"><img src="<?php echo esc_url( $u . 'assets/img/s-reception-infos.jpg' ); ?>" alt="Bloc Informations du colis : numéro de suivi, poids, dimensions, frais avancés, commentaire interne" width="2000" height="1252" loading="lazy"></button>
      </figure>
      <figure class="browser" style="margin:0">
        <div class="browser-bar"><span class="dots"><span></span><span></span><span></span></span><span class="url">Colis enregistré</span></div>
        <button type="button" class="zoom" data-lightbox data-caption="Panneau Colis enregistré : référence en grand, Imprimer l’étiquette, Nouveau colis pour ce client"><img src="<?php echo esc_url( $u . 'assets/img/s-reception-enregistre.jpg' ); ?>" alt="Panneau Colis enregistré : référence en grand, Imprimer l’étiquette, Nouveau colis pour ce client" width="1996" height="260" loading="lazy"></button>
      </figure>
      <div class="label-real">
        <p class="eyebrow">L’étiquette, à taille réelle</p>
        <img src="<?php echo esc_url( $u . 'assets/img/etiquette.jpg' ); ?>" alt="Étiquette de colis 62 × 30 mm : référence, client, date de réception, commentaire" width="940" height="456">
        <small>62 × 30&nbsp;mm sur votre écran, la petite étiquette des imprimantes thermiques. Taille et contenu réglables.</small>
      </div>
    </div>
  </section>

  <section class="feature flip" id="client">
    <div class="feature-copy">
      <p class="eyebrow">Espace client</p>
      <h2>Le client voit ses colis et demande l’envoi lui-même.</h2>
      <p class="lede">Dans Mon compte de WooCommerce, quatre onglets : mes colis, mes expéditions, mes documents, demande d’expédition. Le prix s’affiche avant de valider.</p>
      <ul>
        <li>Estimation en direct : colis, stockage, transport, assurance</li>
        <li>Seuls les transporteurs possibles pour ces colis sont proposés</li>
        <li>Déclaration douanière et factures d’achat quand la destination l’exige</li>
        <li>Annulation possible tant que rien n’est parti</li>
      </ul>
    </div>
    <div class="shot">
      <figure class="browser" style="margin:0">
        <div class="browser-bar"><span class="dots"><span></span><span></span><span></span></span><span class="url">votre-site.fr/mon-compte/mes-colis</span></div>
        <button type="button" class="zoom" data-lightbox data-caption="Encart Votre adresse de livraison : nom et référence, adresse de l’entrepôt, bouton Copier l’adresse"><img src="<?php echo esc_url( $u . 'assets/img/s-client-adresse.jpg' ); ?>" alt="Encart Votre adresse de livraison : nom et référence, adresse de l’entrepôt, bouton Copier l’adresse" width="1224" height="700" loading="lazy"></button>
      </figure>
      <figure class="browser" style="margin:0">
        <div class="browser-bar"><span class="dots"><span></span><span></span><span></span></span><span class="url">votre-site.fr/mon-compte/demande-expedition</span></div>
        <button type="button" class="zoom" data-lightbox data-caption="Transporteur souhaité avec son prix, assurance, total estimé"><img src="<?php echo esc_url( $u . 'assets/img/s-client-estimation.jpg' ); ?>" alt="Transporteur souhaité avec son prix, assurance, total estimé" width="1320" height="498" loading="lazy"></button>
      </figure>
    </div>
  </section>

  <section class="feature">
    <div class="feature-copy">
      <p class="eyebrow">WooCommerce natif</p>
      <h2>Une commande, vos moyens de paiement, votre comptabilité.</h2>
      <p class="lede">Chaque demande crée une vraie commande WooCommerce : une ligne par colis, le stockage, l’assurance, les frais avancés, le transporteur en ligne de livraison. Vos passerelles de paiement, vos taxes et vos e-mails s’appliquent sans rien configurer.</p>
      <ul>
        <li>Encart Colisly sur la commande : colis, déclaration, factures, commentaire interne</li>
        <li>Paiement reçu, colis passés en « payé », prêts à préparer</li>
        <li>Commande annulée, colis de retour en stock</li>
      </ul>
    </div>
    <div class="shot">
      <figure class="browser" style="margin:0">
        <div class="browser-bar"><span class="dots"><span></span><span></span><span></span></span><span class="url">votre-site.fr/wp-admin › WooCommerce › Commande #1384</span></div>
        <button type="button" class="zoom" data-lightbox data-caption="Lignes de la commande : colis, droits de douane avancés sur le colis, Colissimo, total"><img src="<?php echo esc_url( $u . 'assets/img/s-commande-lignes.jpg' ); ?>" alt="Lignes de la commande : colis, droits de douane avancés sur le colis, Colissimo, total" width="1400" height="1004" loading="lazy"></button>
      </figure>
      <figure class="browser" style="margin:0">
        <div class="browser-bar"><span class="dots"><span></span><span></span><span></span></span><span class="url">Encart Colisly sur la commande</span></div>
        <button type="button" class="zoom" data-lightbox data-caption="Encart Colisly : expédition, colis, commentaire interne, déclaration"><img src="<?php echo esc_url( $u . 'assets/img/s-commande-encart.jpg' ); ?>" alt="Encart Colisly : expédition, colis, commentaire interne, déclaration" width="1400" height="546" loading="lazy"></button>
      </figure>
    </div>
  </section>

  <section class="feature flip">
    <div class="feature-copy">
      <p class="eyebrow">Vos tarifs</p>
      <h2>Vos transporteurs, vos grilles, vos limites.</h2>
      <p class="lede">Base plus prix au kilo, ou grille par tranche de poids et par zone. Poids volumétrique pour l’express. Poids, longueur et développé maximum pour ne jamais vendre un envoi que le transporteur refusera.</p>
      <ul>
        <li>Zones par pays, choisies par leur nom</li>
        <li>Paliers de tarification du colis au poids</li>
        <li>Niveaux d’assurance et frais de stockage réglables</li>
      </ul>
    </div>
    <div class="shot">
      <figure class="browser" style="margin:0">
        <div class="browser-bar"><span class="dots"><span></span><span></span><span></span></span><span class="url">votre-site.fr/wp-admin › Colisly › Réglages › Transporteurs</span></div>
        <button type="button" class="zoom" data-lightbox data-caption="Tableau des transporteurs : base, prix au kilo, volumétrique, poids et dimensions maximum"><img src="<?php echo esc_url( $u . 'assets/img/s-transporteurs.jpg' ); ?>" alt="Tableau des transporteurs : base, prix au kilo, volumétrique, poids et dimensions maximum" width="2036" height="602" loading="lazy"></button>
      </figure>
    </div>
  </section>

  <section class="band" aria-label="Chiffres">
    <div><b>62 × 30</b><span>mm, l’étiquette par défaut, réglable</span></div>
    <div><b>15 j</b><span>de stockage offert, selon votre politique</span></div>
    <div><b>3</b><span>langues livrées : français, anglais, espagnol</span></div>
    <div><b>426</b><span>vérifications automatiques à chaque version</span></div>
  </section>

  <section>
    <p class="eyebrow" style="padding-top:40px">Au quotidien</p>
    <h2 style="margin-top:10px">Les écrans du gérant.</h2>
    <div class="gallery">
      <div class="frame"><button type="button" class="zoom" data-lightbox data-caption="Clients, recherche multi-critères"><img src="<?php echo esc_url( $u . 'assets/img/s-clients.jpg' ); ?>" alt="Liste des clients avec recherche" width="1996" height="1240" loading="lazy"></button><div class="frame-cap">Clients, recherche multi-critères</div></div>
      <div class="frame"><button type="button" class="zoom" data-lightbox data-caption="Fiche client, colis, expéditions, historique"><img src="<?php echo esc_url( $u . 'assets/img/s-fiche-client.jpg' ); ?>" alt="Fiche client avec indicateurs et onglets" width="1996" height="1120" loading="lazy"></button><div class="frame-cap">Fiche client, colis, expéditions, historique</div></div>
      <div class="frame"><button type="button" class="zoom" data-lightbox data-caption="Colis en stock, statut changeable en ligne"><img src="<?php echo esc_url( $u . 'assets/img/s-colis.jpg' ); ?>" alt="Liste des colis avec statut et actions" width="1996" height="1240" loading="lazy"></button><div class="frame-cap">Colis en stock, statut changeable en ligne</div></div>
    </div>
  </section>

  <section class="pricing" id="tarif">
    <div class="price-card">
      <p class="eyebrow">Tarif</p>
      <p class="price">0&nbsp;€<small>pour toujours, licence GPL</small></p>
      <ul>
        <li>Toutes les fonctions, sans version « pro »</li>
        <li>Installé sur votre hébergement, vos données restent chez vous</li>
        <li>Mises à jour depuis wordpress.org, comme n’importe quelle extension</li>
        <li>Export et effacement RGPD par les outils natifs de WordPress</li>
      </ul>
      <div class="cta-row"><a class="btn btn-primary" href="https://fr.wordpress.org/plugins/colisly/">Télécharger sur wordpress.org</a></div>
    </div>
    <div class="price-side">
      <p class="eyebrow">Sur mesure</p>
      <h3>Besoin d’une adaptation ?</h3>
      <p style="color:var(--ink-2)">Adresses de livraison multiples, reprise de vos données, intégration avec vos outils, hébergement et installation. <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Pixfeed</a> réalise ces prestations sur devis.</p>
      <a class="btn btn-sun" href="https://pixfeed.net/venez-discuter-de-votre-projet/">Parler du projet</a>
    </div>
  </section>

  <section id="faq">
    <p class="eyebrow">Questions fréquentes</p>
    <h2 style="margin-top:10px">Avant d’installer.</h2>
    <div class="faq">
      <details><summary>Faut-il WooCommerce ?</summary><p>Oui. WooCommerce fournit le compte client, la commande, le paiement et les e-mails. Colisly s’appuie dessus au lieu de tout refaire.</p></details>
      <details><summary>Quels transporteurs sont pris en charge ?</summary><p>N’importe lequel. Les tarifs et les tranches de poids sont les vôtres, ceux d’un contrat négocié ou d’une grille publique, saisis dans les réglages.</p></details>
      <details><summary>Peut-on regrouper plusieurs colis en une expédition ?</summary><p>Oui, c’est le cœur du métier. Le client sélectionne ses colis en stock et demande un envoi unique. Un colis peut aussi être marqué comme devant voyager seul.</p></details>
      <details><summary>Comment les frais de stockage sont-ils calculés ?</summary><p>Chaque colis bénéficie d’une franchise, 15 jours par défaut. Au-delà, le tarif journalier des réglages s’applique et s’ajoute automatiquement à la commande d’expédition.</p></details>
      <details><summary>Les documents sont-ils privés ?</summary><p>Oui. Ils sont stockés hors du dossier public et téléchargés via une adresse authentifiée. Seul le client concerné et l’équipe y accèdent.</p></details>
      <details><summary>Et les droits de douane avancés à la livraison ?</summary><p>Ils se notent sur le colis à la réception. Le client les voit dans son espace et ils sont refacturés au coût réel, sur une ligne à part de sa commande.</p></details>
    </div>
  </section>

  <section class="final">
    <h2>Testez-le sur une vraie boutique.</h2>
    <p style="max-width:34em;opacity:.85">La démo publique arrive : un compte gérant, un compte client, des données remises à zéro chaque nuit.</p>
    <div class="cta-row">
      <a class="btn btn-sun" href="#demo">Revoir la vidéo</a>
      <a class="btn btn-ghost" href="https://fr.wordpress.org/plugins/colisly/">Fiche wordpress.org</a>
    </div>
  </section>

  <div class="sticky-cta" aria-label="Actions rapides">
    <a class="btn btn-sun" href="#demo">Voir la démo</a>
    <a class="btn btn-primary" href="https://fr.wordpress.org/plugins/colisly/">Télécharger</a>
  </div>

  <footer class="foot">
    <span>Colisly, une extension <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Pixfeed</a>, agence web. Licence GPL v2 ou ultérieure.</span>
    <span><a href="https://fr.wordpress.org/plugins/colisly/">wordpress.org</a> · <a href="https://wordpress.org/support/plugin/colisly/">Support</a></span>
  </footer>
</main>

<div class="lb" id="lb" role="dialog" aria-modal="true" aria-label="Capture agrandie" hidden>
  <button type="button" class="lb-btn lb-close" id="lb-close" aria-label="Fermer">×</button>
  <button type="button" class="lb-btn lb-prev" id="lb-prev" aria-label="Précédente">‹</button>
  <button type="button" class="lb-btn lb-next" id="lb-next" aria-label="Suivante">›</button>
  <div class="lb-stage" id="lb-stage"><img id="lb-img" src="" alt=""></div>
  <div class="lb-foot"><span id="lb-cap"></span><span class="lb-count" id="lb-count"></span></div>
</div>

<script>
(function () {
  var items = Array.prototype.slice.call(document.querySelectorAll('[data-lightbox]'));
  var lb = document.getElementById('lb'), img = document.getElementById('lb-img'), cap = document.getElementById('lb-cap'), count = document.getElementById('lb-count');
  var current = -1, last = null, touchX = null;
  function show(i) {
    current = (i + items.length) % items.length;
    var src = items[current].querySelector('img');
    img.src = src.currentSrc || src.src; img.alt = src.alt;
    cap.textContent = items[current].getAttribute('data-caption') || src.alt;
    count.textContent = (current + 1) + ' / ' + items.length;
  }
  function open(i) { last = document.activeElement; show(i); lb.hidden = false; lb.classList.add('is-open'); document.body.style.overflow = 'hidden'; document.getElementById('lb-close').focus(); }
  function close() { lb.classList.remove('is-open'); lb.hidden = true; document.body.style.overflow = ''; img.src = ''; if (last && last.focus) { last.focus(); } }
  items.forEach(function (el, i) { el.addEventListener('click', function () { open(i); }); });
  document.getElementById('lb-close').addEventListener('click', close);
  document.getElementById('lb-prev').addEventListener('click', function () { show(current - 1); });
  document.getElementById('lb-next').addEventListener('click', function () { show(current + 1); });
  lb.addEventListener('click', function (e) { if (e.target === lb || e.target.id === 'lb-stage') { close(); } });
  document.addEventListener('keydown', function (e) {
    if (lb.hidden) { return; }
    if (e.key === 'Escape') { close(); } else if (e.key === 'ArrowRight') { show(current + 1); } else if (e.key === 'ArrowLeft') { show(current - 1); }
  });
  lb.addEventListener('touchstart', function (e) { touchX = e.touches[0].clientX; }, { passive: true });
  lb.addEventListener('touchend', function (e) {
    if (touchX === null) { return; }
    var dx = e.changedTouches[0].clientX - touchX; touchX = null;
    if (Math.abs(dx) > 50) { show(current + (dx < 0 ? 1 : -1)); }
  }, { passive: true });
})();
</script>

<script>
(function () {
  var phone = window.matchMedia('(max-width: 760px)').matches;
  var bg = document.getElementById('bg');
  bg.poster = phone ? "<?php echo esc_url( $u . 'assets/img/hero-poster-p.jpg' ); ?>" : "<?php echo esc_url( $u . 'assets/img/hero-poster-l.jpg' ); ?>";
  if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    bg.src = phone ? "<?php echo esc_url( $u . 'assets/video/hero-portrait.mp4' ); ?>" : "<?php echo esc_url( $u . 'assets/video/hero-landscape.mp4' ); ?>";
    bg.play().catch(function () {});
  }
  var chapters = phone ? [
    { src: "<?php echo esc_url( $u . 'assets/video/m-entrepot.mp4' ); ?>", poster: "<?php echo esc_url( $u . 'assets/img/m-poster-entrepot.jpg' ); ?>", cap: "Réception d’un colis et étiquette, sur téléphone" },
    { src: "<?php echo esc_url( $u . 'assets/video/m-client.mp4' ); ?>", poster: "<?php echo esc_url( $u . 'assets/img/m-poster-client.jpg' ); ?>", cap: "Le client choisit ses colis, voit le prix, paie" }
  ] : [
    { src: "<?php echo esc_url( $u . 'assets/video/entrepot.mp4' ); ?>", poster: "<?php echo esc_url( $u . 'assets/img/poster-entrepot.jpg' ); ?>", cap: "Réception d’un colis, étiquette, liste du stock" },
    { src: "<?php echo esc_url( $u . 'assets/video/client.mp4' ); ?>", poster: "<?php echo esc_url( $u . 'assets/img/poster-client.jpg' ); ?>", cap: "Le client choisit ses colis, voit le prix, paie" }
  ];
  if (phone) { document.getElementById('v').poster = chapters[0].poster; document.getElementById('v').src = chapters[0].src; document.getElementById('vcap').textContent = chapters[0].cap; }
  var v = document.getElementById('v'), cap = document.getElementById('vcap'), bar = document.getElementById('vbar'), play = document.getElementById('vplay');
  var tabs = Array.prototype.slice.call(document.querySelectorAll('.chapter[data-i]'));
  var current = 0;
  var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  function load(i, autoplay) {
    current = i;
    tabs.forEach(function (t, k) { t.setAttribute('aria-selected', k === i ? 'true' : 'false'); });
    cap.textContent = chapters[i].cap;
    v.poster = chapters[i].poster;
    v.src = chapters[i].src;
    bar.style.width = '0';
    if (autoplay) { v.play().catch(function () {}); }
  }
  tabs.forEach(function (t) { t.addEventListener('click', function () { load(parseInt(t.getAttribute('data-i'), 10), true); }); });
  v.addEventListener('timeupdate', function () { if (v.duration) { bar.style.width = (v.currentTime / v.duration * 100) + '%'; } });
  v.addEventListener('ended', function () { if (current < chapters.length - 1) { load(current + 1, true); } else { play.textContent = 'Revoir'; } });
  v.addEventListener('play', function () { play.textContent = 'Pause'; });
  v.addEventListener('pause', function () { if (!v.ended) { play.textContent = 'Lecture'; } });
  play.addEventListener('click', function () { if (v.ended && current === chapters.length - 1) { load(0, true); return; } if (v.paused) { v.play(); } else { v.pause(); } });
  v.addEventListener('click', function () { if (v.paused) { v.play(); } else { v.pause(); } });
  if (!reduced && 'IntersectionObserver' in window) {
    var seen = false;
    new IntersectionObserver(function (entries) {
      entries.forEach(function (e) { if (e.isIntersecting && !seen) { seen = true; v.play().catch(function () {}); } });
    }, { threshold: 0.6 }).observe(v);
  }
})();
</script>

</body>
</html>
