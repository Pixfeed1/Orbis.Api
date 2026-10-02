<?php
/**
 * Template Name: Colisly (full screen, English)
 *
 * English presentation page of Colisly, the counterpart of page-colisly.php.
 * Pick it in "Template" for the page whose slug is
 * "colisly-package-forwarding-plugin-for-woocommerce". Full screen: no theme header
 * or footer, but wp_head() and wp_footer() are called, so Yoast and Site Kit
 * keep working. The two pages point at each other through hreflang links.
 *
 * Files: css/colisly.css, colisly/en/img/, colisly/en/video/, and the hero
 * videos shared with the French page in colisly/video/.
 *
 * @package Pixfeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$u   = get_stylesheet_directory_uri() . '/colisly/';
$m   = $u . 'en/';
$ver = file_exists( get_stylesheet_directory() . '/css/colisly.css' ) ? (string) filemtime( get_stylesheet_directory() . '/css/colisly.css' ) : '1';
$url = get_permalink();
$og  = $u . 'img/hero-poster-l.jpg';

// The French page, for the language link and the hreflang pair.
$pixfeed_colisly_fr_page = get_page_by_path( 'colisly-extension-woocommerce-de-reexpedition-de-colis' );
$pixfeed_colisly_fr_url  = $pixfeed_colisly_fr_page ? get_permalink( $pixfeed_colisly_fr_page ) : home_url( '/colisly-extension-woocommerce-de-reexpedition-de-colis/' );

$pixfeed_colisly_seo = array(
	'title'       => 'Colisly, the package forwarding plugin for WooCommerce | Pixfeed',
	'description' => 'Colisly turns a WooCommerce store into a package forwarding platform: parcel intake, storage fees, consolidation, reshipping and a client portal. Free WordPress plugin, GPL.',
	'image'       => $og,
);

// Only our styles and the audience measurement scripts (Site Kit, Google
// Analytics) are loaded: the theme CSS would redraw the page.
$pixfeed_colisly_strip = static function () {
	$keep = static function ( $handle ) {
		return 0 === strpos( $handle, 'pixfeed-colisly' )
			|| false !== strpos( $handle, 'googlesitekit' )
			|| false !== strpos( $handle, 'google_gtagjs' )
			|| in_array( $handle, array( 'admin-bar', 'dashicons', 'hoverintent-js' ), true );
	};
	foreach ( (array) wp_styles()->queue as $handle ) {
		if ( ! $keep( $handle ) ) {
			wp_dequeue_style( $handle );
		}
	}
	foreach ( (array) wp_scripts()->queue as $handle ) {
		if ( ! $keep( $handle ) ) {
			wp_dequeue_script( $handle );
		}
	}
};
add_action(
	'wp_enqueue_scripts',
	static function () use ( $ver, $pixfeed_colisly_strip ) {
		wp_enqueue_style( 'pixfeed-colisly-fonts', 'https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700;12..96,800&family=Instrument+Sans:ital,wght@0,400;0,500;0,600;1,400&family=JetBrains+Mono:wght@500;600&display=swap', array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		wp_enqueue_style( 'pixfeed-colisly-page', get_stylesheet_directory_uri() . '/css/colisly.css', array(), $ver );
		$pixfeed_colisly_strip();
	},
	999
);
add_action( 'wp_print_styles', $pixfeed_colisly_strip, 999 );
add_action( 'wp_print_scripts', $pixfeed_colisly_strip, 999 );
add_action( 'wp_print_footer_scripts', $pixfeed_colisly_strip, 1 );
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );
remove_action( 'wp_head', 'wp_generator' );
remove_action( 'wp_head', 'wp_enqueue_global_styles', 1 );
remove_action( 'wp_footer', 'wp_enqueue_global_styles', 1 );
remove_action( 'wp_body_open', 'wp_global_styles_render_svg_filters' );

// Tab title when Yoast is not there to provide it.
add_filter(
	'pre_get_document_title',
	static function () use ( $pixfeed_colisly_seo ) {
		return $pixfeed_colisly_seo['title'];
	},
	20
);

// The language pair is written whatever the SEO plugin: without a
// multilingual plugin, Yoast does not know the French page exists.
add_action(
	'wp_head',
	static function () use ( $url, $pixfeed_colisly_fr_url ) {
		echo '<link rel="alternate" hreflang="en" href="' . esc_url( $url ) . '">' . "\n";
		echo '<link rel="alternate" hreflang="fr" href="' . esc_url( $pixfeed_colisly_fr_url ) . '">' . "\n";
		echo '<link rel="alternate" hreflang="x-default" href="' . esc_url( $url ) . '">' . "\n";
	},
	3
);

// With Yoast: its fields are prefilled once, then editable in the page's
// Yoast box. Without Yoast: the tags are written here.
if ( defined( 'WPSEO_VERSION' ) ) {
	$pixfeed_colisly_id = get_queried_object_id();
	if ( $pixfeed_colisly_id && '' === (string) get_post_meta( $pixfeed_colisly_id, '_yoast_wpseo_metadesc', true ) ) {
		update_post_meta( $pixfeed_colisly_id, '_yoast_wpseo_title', $pixfeed_colisly_seo['title'] );
		update_post_meta( $pixfeed_colisly_id, '_yoast_wpseo_metadesc', $pixfeed_colisly_seo['description'] );
		update_post_meta( $pixfeed_colisly_id, '_yoast_wpseo_opengraph-image', $pixfeed_colisly_seo['image'] );
		update_post_meta( $pixfeed_colisly_id, '_yoast_wpseo_opengraph-title', 'Colisly, package forwarding inside WooCommerce' );
	}
} else {
	add_action(
		'wp_head',
		static function () use ( $pixfeed_colisly_seo, $url ) {
			echo '<meta name="description" content="' . esc_attr( $pixfeed_colisly_seo['description'] ) . '">' . "\n";
			echo '<link rel="canonical" href="' . esc_url( $url ) . '">' . "\n";
			echo '<meta property="og:type" content="website">' . "\n";
			echo '<meta property="og:locale" content="en_US">' . "\n";
			echo '<meta property="og:title" content="Colisly, package forwarding inside WooCommerce">' . "\n";
			echo '<meta property="og:description" content="' . esc_attr( $pixfeed_colisly_seo['description'] ) . '">' . "\n";
			echo '<meta property="og:url" content="' . esc_url( $url ) . '">' . "\n";
			echo '<meta property="og:image" content="' . esc_url( $pixfeed_colisly_seo['image'] ) . '">' . "\n";
			echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
		},
		2
	);
}
?>
<!DOCTYPE html>
<html lang="en-US">
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="icon" href="<?php echo esc_url( $u . 'img/icon.svg' ); ?>" type="image/svg+xml">
<?php wp_head(); ?>
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
		'softwareVersion'     => '1.29.1',
		'inLanguage'          => array( 'en', 'fr', 'es' ),
		'license'             => 'https://www.gnu.org/licenses/gpl-2.0.html',
		'url'                 => $url,
		'downloadUrl'         => 'https://wordpress.org/plugins/colisly/',
		'image'               => $og,
		'description'         => 'Parcel intake, storage, consolidation, reshipping and a client portal for a package forwarding business on WooCommerce.',
		'offers'              => array( '@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'USD' ),
		'author'              => array( '@type' => 'Organization', 'name' => 'Pixfeed', 'url' => home_url( '/' ) ),
		'video'               => array(
			'@type'        => 'VideoObject',
			'name'         => 'Colisly in 40 seconds',
			'description'  => 'A parcel comes in, its label is printed, then the client picks parcels, sees the price and pays.',
			'thumbnailUrl' => $m . 'img/poster-warehouse.jpg',
			'contentUrl'   => $m . 'video/warehouse.mp4',
			'uploadDate'   => '2026-10-02',
			'duration'     => 'PT40S',
		),
	),
	JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
);
?>
</script>
</head>
<body <?php body_class( 'pixfeed-colisly' ); ?>>
<?php wp_body_open(); ?>


<header class="wrap nav">
  <a class="brand" href="#top"><svg class="logo-mark" viewBox="0 0 1024 1024" aria-hidden="true"><rect width="1024" height="1024" rx="226" fill="#7b55ad"/><path d="M512 268 L708 381 L512 494 L316 381 Z" fill="#fff" stroke="#fff" stroke-width="42" stroke-linejoin="round"/><path d="M280 444 L476 557 L476 736 L280 623 Z" fill="#fff" stroke="#fff" stroke-width="42" stroke-linejoin="round"/><path d="M744 444 L548 557 L548 736 L744 623 Z" fill="#FFC24B" stroke="#FFC24B" stroke-width="42" stroke-linejoin="round"/></svg> Colisly</a>
  <ul class="nav-links">
    <li><a href="#flow">How it works</a></li>
    <li><a href="#intake">Intake</a></li>
    <li><a href="#client">Client portal</a></li>
    <li><a href="#pricing">Pricing</a></li>
    <li><a href="#faq">FAQ</a></li>
    <li><a href="<?php echo esc_url( $pixfeed_colisly_fr_url ); ?>" hreflang="fr" lang="fr">Français</a></li>
  </ul>
  <a class="btn btn-primary" href="https://wordpress.org/plugins/colisly/">Download</a>
</header>

<main class="wrap" id="top">
  <section class="hero">
    <div class="hero-video" id="warehouse">
      <video id="bg" class="hero-bg" muted loop playsinline autoplay preload="auto" poster="<?php echo esc_url( $u . 'img/hero-poster-l.jpg' ); ?>" src="<?php echo esc_url( $u . 'video/hero-landscape.mp4' ); ?>" aria-hidden="true" tabindex="-1"></video>
      <div class="hero-copy">
        <p class="eyebrow eyebrow-light">Free WordPress and WooCommerce plugin</p>
        <h1>Your forwarding warehouse, inside WooCommerce.</h1>
        <p class="lede lede-light">Parcel intake, storage, consolidation, reshipping and a client portal. Clients pay on your store, with your carriers and your rates.</p>
        <div class="cta-row">
          <a class="btn btn-sun" href="#demo">See the plugin in 40 seconds</a>
          <a class="btn btn-glass" href="https://wordpress.org/plugins/colisly/">Install from wordpress.org</a>
        </div>
        <ul class="proofs proofs-light">
          <li>GPL license, $0</li>
          <li>English, French, Spanish</li>
          <li>Native WooCommerce orders</li>
        </ul>
      </div>
    </div>

    <div class="screen" id="demo">
      <div class="screen-bar">
        <span class="dots"><span></span><span></span><span></span></span>
        <span>Colisly in 40 seconds</span>
        <div class="chapters" role="tablist" aria-label="Video chapters">
          <button class="chapter" role="tab" type="button" data-i="0" aria-selected="true">Warehouse side</button>
          <button class="chapter" role="tab" type="button" data-i="1" aria-selected="false">Client side</button>
        </div>
      </div>
      <video id="v" playsinline muted preload="none" poster="<?php echo esc_url( $m . 'img/poster-warehouse.jpg' ); ?>" src="<?php echo esc_url( $m . 'video/warehouse.mp4' ); ?>" aria-label="Colisly demonstration"></video>
      <div class="screen-foot">
        <span id="vcap">A parcel comes in, its label, the stock list</span>
        <div class="bar"><i id="vbar"></i></div>
        <button class="chapter" id="vplay" type="button">Play</button>
      </div>
    </div>
  </section>

  <section class="flow" id="flow">
    <article class="step">
      <span class="n">1</span>
      <h3>The parcel arrives</h3>
      <p>Weight, dimensions, tracking number, photo, internal note. The reference is generated and the label prints.</p>
      <span class="tag">COL000123</span>
    </article>
    <article class="step">
      <span class="n">2</span>
      <h3>It waits in stock</h3>
      <p>Free storage per your policy, 15 days by default. Past that, fees compute themselves, day by day.</p>
      <span class="tag">15 days free</span>
    </article>
    <article class="step">
      <span class="n">3</span>
      <h3>The client ships it</h3>
      <p>They tick their parcels, pick a carrier, see the price. The request becomes a WooCommerce order they pay as usual.</p>
      <span class="tag">Order #1042</span>
    </article>
  </section>

  <section class="feature" id="intake">
    <div class="feature-copy">
      <p class="eyebrow">Intake</p>
      <h2>A parcel booked in thirty seconds, label included.</h2>
      <p class="lede">The form follows the counter: the client, the carton, the shipping rules. On save, the reference shows large next to the print button.</p>
      <ul>
        <li>Client search by reference, name or e-mail</li>
        <li>Fees advanced on delivery, customs duties or import VAT, billed back at cost</li>
        <li>Allowed carriers parcel by parcel, consolidation allowed or not</li>
        <li>Internal note never shown to the client</li>
      </ul>
    </div>
    <div class="shot">
      <figure class="browser" style="margin:0">
        <div class="browser-bar"><span class="dots"><span></span><span></span><span></span></span><span class="url">your-site.com/wp-admin › Colisly › New parcel</span></div>
        <button type="button" class="zoom" data-lightbox data-caption="Parcel information block: tracking number, weight, dimensions, fees advanced, internal note"><img src="<?php echo esc_url( $m . 'img/s-intake-info.jpg' ); ?>" alt="Parcel information block: tracking number, weight, dimensions, fees advanced, internal note" width="2208" height="1042" loading="lazy"></button>
      </figure>
      <figure class="browser" style="margin:0">
        <div class="browser-bar"><span class="dots"><span></span><span></span><span></span></span><span class="url">Parcel saved</span></div>
        <button type="button" class="zoom" data-lightbox data-caption="Parcel saved panel: the reference in large type, Print the label, New parcel for this client"><img src="<?php echo esc_url( $m . 'img/s-intake-saved.jpg' ); ?>" alt="Parcel saved panel: the reference in large type, Print the label, New parcel for this client" width="2204" height="288" loading="lazy"></button>
      </figure>
      <div class="label-real">
        <p class="eyebrow">The label, actual size</p>
        <img src="<?php echo esc_url( $m . 'img/label.jpg' ); ?>" alt="Parcel label, 62 × 30 mm: reference, client, intake date, note" width="1428" height="704">
        <small>62 × 30&nbsp;mm on your screen, the small thermal printer label. Size and contents adjustable.</small>
      </div>
    </div>
  </section>

  <section class="feature flip" id="client">
    <div class="feature-copy">
      <p class="eyebrow">Client portal</p>
      <h2>Clients see their parcels and request the shipment themselves.</h2>
      <p class="lede">In the WooCommerce My Account page, four tabs: my parcels, my shipments, my documents, shipment request. The price shows before they confirm.</p>
      <ul>
        <li>Live estimate: parcels, storage, transport, insurance</li>
        <li>Only the carriers possible for those parcels are offered</li>
        <li>Customs declaration and purchase invoices when the destination requires them</li>
        <li>Cancellation possible until anything leaves</li>
      </ul>
    </div>
    <div class="shot">
      <figure class="browser" style="margin:0">
        <div class="browser-bar"><span class="dots"><span></span><span></span><span></span></span><span class="url">your-site.com/my-account/my-parcels</span></div>
        <button type="button" class="zoom" data-lightbox data-caption="Your delivery address block: name and reference, warehouse address, Copy the address button"><img src="<?php echo esc_url( $m . 'img/s-client-address.jpg' ); ?>" alt="Your delivery address block: name and reference, warehouse address, Copy the address button" width="1424" height="860" loading="lazy"></button>
      </figure>
      <figure class="browser" style="margin:0">
        <div class="browser-bar"><span class="dots"><span></span><span></span><span></span></span><span class="url">your-site.com/my-account/shipment-request</span></div>
        <button type="button" class="zoom" data-lightbox data-caption="Preferred carrier with its price and delivery time, insurance, estimated total"><img src="<?php echo esc_url( $m . 'img/s-client-estimate.jpg' ); ?>" alt="Preferred carrier with its price and delivery time, insurance, estimated total" width="1466" height="398" loading="lazy"></button>
      </figure>
    </div>
  </section>

  <section class="feature">
    <div class="feature-copy">
      <p class="eyebrow">Native WooCommerce</p>
      <h2>One order, your payment methods, your bookkeeping.</h2>
      <p class="lede">Every request creates a real WooCommerce order: one line per parcel, storage, insurance, fees advanced, the carrier as the shipping line. Your payment gateways, taxes and e-mails apply with nothing to set up.</p>
      <ul>
        <li>Colisly panel on the order: parcels, declaration, invoices, internal note</li>
        <li>Payment received, parcels marked paid, ready to prepare</li>
        <li>Order cancelled, parcels back in stock</li>
      </ul>
    </div>
    <div class="shot">
      <figure class="browser" style="margin:0">
        <div class="browser-bar"><span class="dots"><span></span><span></span><span></span></span><span class="url">your-site.com/wp-admin › WooCommerce › Order #1384</span></div>
        <button type="button" class="zoom" data-lightbox data-caption="Order lines: parcels, duties advanced on a parcel, insurance, carrier, total"><img src="<?php echo esc_url( $m . 'img/s-order-lines.jpg' ); ?>" alt="Order lines: parcels, duties advanced on a parcel, insurance, carrier, total" width="1560" height="1302" loading="lazy"></button>
      </figure>
      <figure class="browser" style="margin:0">
        <div class="browser-bar"><span class="dots"><span></span><span></span><span></span></span><span class="url">Colisly panel on the order</span></div>
        <button type="button" class="zoom" data-lightbox data-caption="Colisly panel: shipment, parcels, internal note, declaration"><img src="<?php echo esc_url( $m . 'img/s-order-panel.jpg' ); ?>" alt="Colisly panel: shipment, parcels, internal note, declaration" width="1548" height="1478" loading="lazy"></button>
      </figure>
    </div>
  </section>

  <section class="feature flip">
    <div class="feature-copy">
      <p class="eyebrow">Your rates</p>
      <h2>Your carriers, your grids, your limits.</h2>
      <p class="lede">Base plus price per kilo, or a grid by weight bracket and by zone. Volumetric weight for express. Maximum weight, length and girth, so you never sell a shipment the carrier will refuse.</p>
      <ul>
        <li>Zones by country, picked by name</li>
        <li>Handling tiers by parcel weight</li>
        <li>Insurance levels and storage fees you set</li>
      </ul>
    </div>
    <div class="shot">
      <figure class="browser" style="margin:0">
        <div class="browser-bar"><span class="dots"><span></span><span></span><span></span></span><span class="url">your-site.com/wp-admin › Colisly › Settings › Carriers</span></div>
        <button type="button" class="zoom" data-lightbox data-caption="Carriers table: base, price per kilo, volumetric, maximum weight and dimensions"><img src="<?php echo esc_url( $m . 'img/s-carriers.jpg' ); ?>" alt="Carriers table: base, price per kilo, volumetric, maximum weight and dimensions" width="2220" height="478" loading="lazy"></button>
      </figure>
    </div>
  </section>

  <section class="band" aria-label="Figures">
    <div><b>62 × 30</b><span>mm, the default label, adjustable</span></div>
    <div><b>15 days</b><span>of free storage, per your policy</span></div>
    <div><b>3</b><span>languages shipped: English, French, Spanish</span></div>
    <div><b>600</b><span>automated checks on every release</span></div>
  </section>

  <section>
    <p class="eyebrow" style="padding-top:40px">Day to day</p>
    <h2 style="margin-top:10px">The manager's screens.</h2>
    <div class="gallery">
      <div class="frame"><button type="button" class="zoom" data-lightbox data-caption="Clients, multi-criteria search"><img src="<?php echo esc_url( $m . 'img/s-clients.jpg' ); ?>" alt="Clients list with search" width="2244" height="1288" loading="lazy"></button><div class="frame-cap">Clients, multi-criteria search</div></div>
      <div class="frame"><button type="button" class="zoom" data-lightbox data-caption="Client record: parcels, shipments, history"><img src="<?php echo esc_url( $m . 'img/s-client-record.jpg' ); ?>" alt="Client record with indicators and tabs" width="2244" height="1836" loading="lazy"></button><div class="frame-cap">Client record: parcels, shipments, history</div></div>
      <div class="frame"><button type="button" class="zoom" data-lightbox data-caption="Parcels in stock, status changed inline"><img src="<?php echo esc_url( $m . 'img/s-parcels.jpg' ); ?>" alt="Parcels list with status and actions" width="2244" height="1288" loading="lazy"></button><div class="frame-cap">Parcels in stock, status changed inline</div></div>
    </div>
  </section>

  <section class="pricing" id="pricing">
    <div class="price-card">
      <p class="eyebrow">Pricing</p>
      <p class="price">$0<small>forever, GPL license</small></p>
      <ul>
        <li>Every feature, no "pro" version</li>
        <li>Installed on your hosting, your data stays with you</li>
        <li>Updates from wordpress.org, like any plugin</li>
        <li>GDPR export and erasure through the native WordPress tools</li>
      </ul>
      <div class="cta-row"><a class="btn btn-primary" href="https://wordpress.org/plugins/colisly/">Download on wordpress.org</a></div>
    </div>
    <div class="price-side">
      <p class="eyebrow">Custom work</p>
      <h3>Need an adaptation?</h3>
      <p style="color:var(--ink-2)">Multiple delivery addresses, importing your existing data, integration with your tools, hosting and setup. <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Pixfeed</a> does this work on quote, in English or French.</p>
      <a class="btn btn-sun" href="https://pixfeed.net/venez-discuter-de-votre-projet/">Talk about your project</a>
    </div>
  </section>

  <section id="faq">
    <p class="eyebrow">Frequently asked questions</p>
    <h2 style="margin-top:10px">Before you install.</h2>
    <div class="faq">
      <details><summary>Do I need WooCommerce?</summary><p>Yes. WooCommerce provides the client account, the order, the payment and the e-mails. Colisly builds on it instead of redoing everything.</p></details>
      <details><summary>Which carriers are supported?</summary><p>Any. Rates and weight brackets are yours, from a negotiated contract or a public grid, typed in the settings.</p></details>
      <details><summary>Can several parcels be consolidated into one shipment?</summary><p>Yes, that is the heart of the trade. The client selects parcels in stock and requests a single shipment. A parcel can also be marked as having to travel alone.</p></details>
      <details><summary>How are storage fees computed?</summary><p>Each parcel gets a free period, 15 days by default. Past it, the daily rate from the settings applies and is added automatically to the shipment order.</p></details>
      <details><summary>Are documents private?</summary><p>Yes. They are stored outside the public folder and downloaded through an authenticated address. Only the client concerned and your team can reach them.</p></details>
      <details><summary>What about duties advanced on delivery?</summary><p>They are noted on the parcel at intake. The client sees them in their account and they are billed back at cost, on a separate line of the order.</p></details>
    </div>
  </section>

  <section class="final">
    <h2>Try it on a real store.</h2>
    <p style="max-width:34em;opacity:.85">A public demo is coming: a manager account, a client account, data reset every night.</p>
    <div class="cta-row">
      <a class="btn btn-sun" href="#demo">Watch the video again</a>
      <a class="btn btn-ghost" href="https://wordpress.org/plugins/colisly/">wordpress.org listing</a>
    </div>
  </section>

  <div class="sticky-cta" aria-label="Quick actions">
    <a class="btn btn-sun" href="#demo">See the demo</a>
    <a class="btn btn-primary" href="https://wordpress.org/plugins/colisly/">Download</a>
  </div>

  <footer class="foot">
    <span>Colisly, a plugin by <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Pixfeed</a>, web agency. GPL v2 or later license. <a href="<?php echo esc_url( $pixfeed_colisly_fr_url ); ?>" hreflang="fr" lang="fr">Version française</a></span>
    <span><a href="https://wordpress.org/plugins/colisly/">wordpress.org</a> · <a href="https://wordpress.org/support/plugin/colisly/">Support</a></span>
  </footer>
</main>

<div class="lb" id="lb" role="dialog" aria-modal="true" aria-label="Enlarged screenshot" hidden>
  <button type="button" class="lb-btn lb-close" id="lb-close" aria-label="Close">×</button>
  <button type="button" class="lb-btn lb-prev" id="lb-prev" aria-label="Previous">‹</button>
  <button type="button" class="lb-btn lb-next" id="lb-next" aria-label="Next">›</button>
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
  // The background video is already in the HTML, landscape version. The
  // script only switches to the portrait version on a phone, or stops it
  // when the device asks for less motion.
  var bg = document.getElementById('bg');
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    bg.removeAttribute('autoplay'); bg.pause(); bg.removeAttribute('src'); bg.load();
  } else if (phone) {
    bg.poster = "<?php echo esc_url( $u . 'img/hero-poster-p.jpg' ); ?>";
    bg.src = "<?php echo esc_url( $u . 'video/hero-portrait.mp4' ); ?>";
    bg.play().catch(function () {});
  }
  var chapters = phone ? [
    { src: "<?php echo esc_url( $m . 'video/m-warehouse.mp4' ); ?>", poster: "<?php echo esc_url( $m . 'img/m-poster-warehouse.jpg' ); ?>", cap: "A parcel comes in and its label, on a phone" },
    { src: "<?php echo esc_url( $m . 'video/m-client.mp4' ); ?>", poster: "<?php echo esc_url( $m . 'img/m-poster-client.jpg' ); ?>", cap: "The client picks parcels, sees the price, pays" }
  ] : [
    { src: "<?php echo esc_url( $m . 'video/warehouse.mp4' ); ?>", poster: "<?php echo esc_url( $m . 'img/poster-warehouse.jpg' ); ?>", cap: "A parcel comes in, its label, the stock list" },
    { src: "<?php echo esc_url( $m . 'video/client.mp4' ); ?>", poster: "<?php echo esc_url( $m . 'img/poster-client.jpg' ); ?>", cap: "The client picks parcels, sees the price, pays" }
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
  v.addEventListener('ended', function () { if (current < chapters.length - 1) { load(current + 1, true); } else { play.textContent = 'Replay'; } });
  v.addEventListener('play', function () { play.textContent = 'Pause'; });
  v.addEventListener('pause', function () { if (!v.ended) { play.textContent = 'Play'; } });
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

<?php wp_footer(); ?>
</body>
</html>
