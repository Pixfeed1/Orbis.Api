<?php
/**
 * "Shipment requested" admin e-mail.
 *
 * @package ColislyParcelForwarding
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Native WooCommerce e-mail sent to the shop manager when a client requests a
 * shipment.
 */
class COLISLY_Email_Shipment_Requested extends WC_Email {

	/**
	 * Shipment row being notified.
	 *
	 * @var object|null
	 */
	public $shipment = null;

	/**
	 * Client row.
	 *
	 * @var object|null
	 */
	public $client = null;

	/**
	 * Sets up the e-mail.
	 */
	public function __construct() {
		$this->id             = 'colisly_shipment_requested';
		$this->title          = __( 'Shipment requested (Colisly Parcel Forwarding)', 'colisly' );
		$this->description    = __( 'Sent to the staff when a client creates a shipment request.', 'colisly' );
		$this->template_html  = 'emails/colisly-shipment-requested.php';
		$this->template_plain = 'emails/plain/colisly-shipment-requested.php';
		$this->template_base  = COLISLY_PLUGIN_DIR . 'templates/';
		$this->placeholders   = array(
			'{shipment_reference}' => '',
			'{client_reference}'   => '',
		);

		add_action( 'colisly_send_shipment_requested_email', array( $this, 'trigger' ), 10, 2 );

		parent::__construct();

		// Default recipient: shop admin, overridable in the e-mail settings.
		$this->recipient = $this->get_option( 'recipient', get_option( 'admin_email' ) );
	}

	/**
	 * Adds the recipient field to the e-mail settings form.
	 *
	 * @return void
	 */
	public function init_form_fields() {
		parent::init_form_fields();

		$this->form_fields['recipient'] = array(
			'title'       => __( 'Recipient(s)', 'colisly' ),
			'type'        => 'text',
			/* translators: %s: WP admin email. */
			'description' => sprintf( __( 'Comma-separated addresses. Defaults to %s.', 'colisly' ), '<code>' . esc_html( get_option( 'admin_email' ) ) . '</code>' ),
			'placeholder' => '',
			'default'     => '',
			'desc_tip'    => true,
		);
	}

	/**
	 * Default subject.
	 *
	 * @return string
	 */
	public function get_default_subject() {
		return __( '[{site_title}] New shipment request {shipment_reference} (client {client_reference})', 'colisly' );
	}

	/**
	 * Default heading.
	 *
	 * @return string
	 */
	public function get_default_heading() {
		return __( 'New shipment request {shipment_reference}', 'colisly' );
	}

	/**
	 * Sends the e-mail for a shipment request.
	 *
	 * @param int    $shipment_id Shipment ID.
	 * @param object $client      Client row.
	 * @return void
	 */
	public function trigger( $shipment_id, $client ) {
		$this->setup_locale();

		$shipment = COLISLY_Shipments::get( $shipment_id );

		if ( $shipment && $client ) {
			$this->shipment                             = $shipment;
			$this->client                               = $client;
			$this->placeholders['{shipment_reference}'] = $shipment->reference;
			$this->placeholders['{client_reference}']   = $client->reference;
		}

		if ( $this->shipment && $this->is_enabled() && $this->get_recipient() ) {
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}

		$this->restore_locale();
	}

	/**
	 * HTML content.
	 *
	 * @return string
	 */
	public function get_content_html() {
		return wc_get_template_html(
			$this->template_html,
			array(
				'shipment'           => $this->shipment,
				'client'             => $this->client,
				'email_heading'      => $this->get_heading(),
				'additional_content' => $this->get_additional_content(),
				'sent_to_admin'      => true,
				'plain_text'         => false,
				'email'              => $this,
			),
			'',
			$this->template_base
		);
	}

	/**
	 * Plain text content.
	 *
	 * @return string
	 */
	public function get_content_plain() {
		return wc_get_template_html(
			$this->template_plain,
			array(
				'shipment'           => $this->shipment,
				'client'             => $this->client,
				'email_heading'      => $this->get_heading(),
				'additional_content' => $this->get_additional_content(),
				'sent_to_admin'      => true,
				'plain_text'         => true,
				'email'              => $this,
			),
			'',
			$this->template_base
		);
	}
}
