<?php
/**
 * "Parcel received" customer e-mail.
 *
 * @package ColislyParcelForwarding
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Native WooCommerce e-mail sent to the customer when a parcel is received.
 */
class COLISLY_Email_Parcel_Received extends WC_Email {

	/**
	 * Parcel row being notified.
	 *
	 * @var object|null
	 */
	public $parcel = null;

	/**
	 * Sets up the e-mail.
	 */
	public function __construct() {
		$this->id             = 'colisly_parcel_received';
		$this->customer_email = true;
		$this->title          = __( 'Parcel received (Colisly Parcel Forwarding)', 'colisly-parcel-forwarding' );
		$this->description    = __( 'Sent to the client when one of their parcels is registered at the warehouse.', 'colisly-parcel-forwarding' );
		$this->template_html  = 'emails/colisly-parcel-received.php';
		$this->template_plain = 'emails/plain/colisly-parcel-received.php';
		$this->template_base  = COLISLY_PLUGIN_DIR . 'templates/';
		$this->placeholders   = array(
			'{parcel_reference}' => '',
		);

		add_action( 'colisly_send_parcel_received_email', array( $this, 'trigger' ), 10, 2 );

		parent::__construct();
	}

	/**
	 * Default subject.
	 *
	 * @return string
	 */
	public function get_default_subject() {
		return __( '[{site_title}] Your parcel {parcel_reference} has been received', 'colisly-parcel-forwarding' );
	}

	/**
	 * Default heading.
	 *
	 * @return string
	 */
	public function get_default_heading() {
		return __( 'Parcel {parcel_reference} received', 'colisly-parcel-forwarding' );
	}

	/**
	 * Sends the e-mail for a parcel.
	 *
	 * @param int    $parcel_id Parcel ID.
	 * @param object $client    Client row.
	 * @return void
	 */
	public function trigger( $parcel_id, $client ) {
		$this->setup_locale();

		$parcel = COLISLY_Parcels::get( $parcel_id );
		$user   = $client ? get_userdata( (int) $client->user_id ) : null;

		if ( $parcel && $user ) {
			$this->parcel                             = $parcel;
			$this->recipient                          = $user->user_email;
			$this->placeholders['{parcel_reference}'] = $parcel->reference;
		}

		if ( $this->parcel && $this->is_enabled() && $this->get_recipient() ) {
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
				'parcel'             => $this->parcel,
				'email_heading'      => $this->get_heading(),
				'additional_content' => $this->get_additional_content(),
				'sent_to_admin'      => false,
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
				'parcel'             => $this->parcel,
				'email_heading'      => $this->get_heading(),
				'additional_content' => $this->get_additional_content(),
				'sent_to_admin'      => false,
				'plain_text'         => true,
				'email'              => $this,
			),
			'',
			$this->template_base
		);
	}
}
