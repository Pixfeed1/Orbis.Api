<?php
/**
 * Pixfeed v2 — Navigation Partial
 * 
 * Paramètres via $args :
 * - 'solid' => true : nav avec fond solide (pages internes)
 * - 'solid' => false : nav transparent → solide au scroll (homepage)
 */
$nav_class = 'pf-nav';
if ( ! empty( $args['solid'] ) ) {
    $nav_class .= ' pf-nav--solid';
}

$logo_url = PIXFEED_URI . '/img/logo.png';
if ( ! file_exists( PIXFEED_DIR . '/img/logo.png' ) ) {
    $logo_url = 'https://pixfeed.net/wp-content/uploads/2025/04/logo.png';
}

$contact_url = home_url( '/venez-discuter-de-votre-projet/' );
?>
<nav class="<?php echo esc_attr( $nav_class ); ?>" id="pfNav">
  <div class="pf-nav-inner">
    <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="pf-logo">
      <img src="<?php echo esc_url( $logo_url ); ?>" alt="" loading="eager">
      <span class="pf-logo-text">Pixfeed</span>
    </a>
    <div class="pf-nav-links">
      <a href="<?php echo esc_url( home_url( '/nos-realisations/' ) ); ?>" class="pf-nav-link">Réalisations</a>
      <div class="pf-nav-item pf-has-sub">
        <a href="<?php echo esc_url( home_url( '/nos-offres/' ) ); ?>" class="pf-nav-link" aria-haspopup="true" aria-expanded="false">Nos Offres <span class="pf-caret" aria-hidden="true"></span></a>
        <div class="pf-sub" role="menu">
          <a href="<?php echo esc_url( home_url( '/nos-offres/' ) ); ?>" role="menuitem">Toutes nos offres</a>
          <a href="<?php echo esc_url( home_url( '/colisly-extension-woocommerce-de-reexpedition-de-colis/' ) ); ?>" role="menuitem">Colisly, réexpédition de colis<small>Extension WooCommerce gratuite</small></a>
        </div>
      </div>
      <a href="<?php echo esc_url( home_url( '/blog/' ) ); ?>" class="pf-nav-link">Blog</a>
      <a href="<?php echo esc_url( $contact_url ); ?>" class="pf-nav-link">Contact</a>
      <a href="<?php echo esc_url( $contact_url ); ?>" class="btn btn-fill btn-nav">Un projet ? ↗︎</a>
    </div>
    <button class="pf-hamburger" id="pfHamburger" onclick="pfToggleMenu()" aria-label="Menu">
      <span></span><span></span><span></span>
    </button>
  </div>
</nav>

<!-- Fullscreen mobile menu -->
<div class="pf-mobile-overlay" id="pfMobileOverlay">
  <div class="pf-mobile-overlay-header">
    <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="pf-logo">
      <img src="<?php echo esc_url( $logo_url ); ?>" alt="">
      <span class="pf-logo-text">Pixfeed</span>
    </a>
    <button class="pf-mobile-close" onclick="pfToggleMenu()" aria-label="Fermer">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--ink)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>
  </div>
  <div class="pf-mobile-overlay-links">
    <a href="<?php echo esc_url( home_url( '/nos-realisations/' ) ); ?>" class="accent" onclick="pfToggleMenu()">Réalisations</a>
    <a href="<?php echo esc_url( home_url( '/nos-offres/' ) ); ?>" onclick="pfToggleMenu()">Nos offres</a>
    <a href="<?php echo esc_url( home_url( '/colisly-extension-woocommerce-de-reexpedition-de-colis/' ) ); ?>" class="pf-mobile-sub" onclick="pfToggleMenu()">Colisly, réexpédition de colis</a>
    <a href="<?php echo esc_url( home_url( '/decouvrir/' ) ); ?>" onclick="pfToggleMenu()">Découvrir Pixfeed</a>
    <a href="<?php echo esc_url( home_url( '/blog/' ) ); ?>" onclick="pfToggleMenu()">Blog</a>
    <a href="<?php echo esc_url( home_url( '/certifications/' ) ); ?>" onclick="pfToggleMenu()">Certifications</a>
    <a href="<?php echo esc_url( $contact_url ); ?>" onclick="pfToggleMenu()">Contact</a>
  </div>
  <div class="pf-mobile-overlay-cta">
    <a href="<?php echo esc_url( $contact_url ); ?>" onclick="pfToggleMenu()">Un projet ? ↗︎</a>
  </div>
</div>
