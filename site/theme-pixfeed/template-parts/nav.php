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

/**
 * Le menu affiché est celui d'Apparence › Menus, emplacement « Menu
 * principal », deux niveaux. Sans menu assigné, les liens d'origine
 * servent de secours.
 */
if ( ! class_exists( 'Pixfeed_Nav_Walker' ) ) {
	class Pixfeed_Nav_Walker extends Walker_Nav_Menu {
		/** @var string 'desktop' ou 'mobile'. */
		public $mode = 'desktop';

		public function __construct( $mode = 'desktop' ) {
			$this->mode = $mode;
		}

		public function start_lvl( &$output, $depth = 0, $args = null ) {
			if ( 'desktop' === $this->mode ) {
				$output .= '<div class="pf-sub" role="menu">';
			}
		}

		public function end_lvl( &$output, $depth = 0, $args = null ) {
			if ( 'desktop' === $this->mode ) {
				$output .= '</div>';
			}
		}

		public function start_el( &$output, $item, $depth = 0, $args = null, $id = 0 ) {
			$has_children = in_array( 'menu-item-has-children', (array) $item->classes, true );
			$title        = apply_filters( 'the_title', $item->title, $item->ID );
			$url          = ! empty( $item->url ) ? $item->url : '#';
			$target       = ! empty( $item->target ) ? ' target="' . esc_attr( $item->target ) . '"' : '';
			$desc         = trim( (string) $item->description );

			if ( 'mobile' === $this->mode ) {
				$class   = 0 === $depth ? '' : ' class="pf-mobile-sub"';
				$output .= '<a href="' . esc_url( $url ) . '"' . $class . $target . ' onclick="pfToggleMenu()">' . esc_html( $title ) . '</a>';
				return;
			}

			if ( 0 === $depth ) {
				$output .= '<div class="pf-nav-item' . ( $has_children ? ' pf-has-sub' : '' ) . '">';
				$output .= '<a href="' . esc_url( $url ) . '" class="pf-nav-link"' . $target . ( $has_children ? ' aria-haspopup="true" aria-expanded="false"' : '' ) . '>' . esc_html( $title ) . ( $has_children ? ' <span class="pf-caret" aria-hidden="true"></span>' : '' ) . '</a>';
			} else {
				$output .= '<a href="' . esc_url( $url ) . '" role="menuitem"' . $target . '>' . esc_html( $title ) . ( '' !== $desc ? '<small>' . esc_html( $desc ) . '</small>' : '' ) . '</a>';
			}
		}

		public function end_el( &$output, $item, $depth = 0, $args = null ) {
			if ( 'desktop' === $this->mode && 0 === $depth ) {
				$output .= '</div>';
			}
		}
	}
}

$has_menu = has_nav_menu( 'primary' );
?>
<nav class="<?php echo esc_attr( $nav_class ); ?>" id="pfNav">
  <div class="pf-nav-inner">
    <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="pf-logo">
      <img src="<?php echo esc_url( $logo_url ); ?>" alt="" loading="eager">
      <span class="pf-logo-text">Pixfeed</span>
    </a>
    <div class="pf-nav-links">
      <?php if ( $has_menu ) : ?>
        <?php
        wp_nav_menu(
          array(
            'theme_location' => 'primary',
            'container'      => false,
            'items_wrap'     => '%3$s',
            'depth'          => 2,
            'fallback_cb'    => false,
            'walker'         => new Pixfeed_Nav_Walker( 'desktop' ),
          )
        );
        ?>
      <?php else : ?>
        <a href="<?php echo esc_url( home_url( '/nos-realisations/' ) ); ?>" class="pf-nav-link">Réalisations</a>
        <a href="<?php echo esc_url( home_url( '/nos-offres/' ) ); ?>" class="pf-nav-link">Nos Offres</a>
        <a href="<?php echo esc_url( home_url( '/blog/' ) ); ?>" class="pf-nav-link">Blog</a>
        <a href="<?php echo esc_url( $contact_url ); ?>" class="pf-nav-link">Contact</a>
      <?php endif; ?>
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
    <?php if ( $has_menu ) : ?>
      <?php
      wp_nav_menu(
        array(
          'theme_location' => 'primary',
          'container'      => false,
          'items_wrap'     => '%3$s',
          'depth'          => 2,
          'fallback_cb'    => false,
          'walker'         => new Pixfeed_Nav_Walker( 'mobile' ),
        )
      );
      ?>
    <?php else : ?>
      <a href="<?php echo esc_url( home_url( '/nos-realisations/' ) ); ?>" class="accent" onclick="pfToggleMenu()">Réalisations</a>
      <a href="<?php echo esc_url( home_url( '/nos-offres/' ) ); ?>" onclick="pfToggleMenu()">Nos offres</a>
      <a href="<?php echo esc_url( home_url( '/decouvrir/' ) ); ?>" onclick="pfToggleMenu()">Découvrir Pixfeed</a>
      <a href="<?php echo esc_url( home_url( '/blog/' ) ); ?>" onclick="pfToggleMenu()">Blog</a>
      <a href="<?php echo esc_url( home_url( '/certifications/' ) ); ?>" onclick="pfToggleMenu()">Certifications</a>
      <a href="<?php echo esc_url( $contact_url ); ?>" onclick="pfToggleMenu()">Contact</a>
    <?php endif; ?>
  </div>
  <div class="pf-mobile-overlay-cta">
    <a href="<?php echo esc_url( $contact_url ); ?>" onclick="pfToggleMenu()">Un projet ? ↗︎</a>
  </div>
</div>
