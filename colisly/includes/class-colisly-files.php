<?php
/**
 * Private file storage.
 *
 * Client documents and reception photos are stored outside the public media
 * library, in a dedicated uploads sub-directory protected by .htaccess and
 * randomized file names, and served only through an authenticated,
 * nonce-protected download endpoint.
 *
 * @package ColislyParcelForwarding
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles uploads to, and downloads from, the plugin's private directory.
 */
class COLISLY_Files {

	/**
	 * Allowed mime types for client documents.
	 *
	 * @return array
	 */
	public static function document_mimes() {
		return array(
			'jpg|jpeg|jpe' => 'image/jpeg',
			'png'          => 'image/png',
			'webp'         => 'image/webp',
			'gif'          => 'image/gif',
			'pdf'          => 'application/pdf',
			'doc'          => 'application/msword',
			'docx'         => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'xls'          => 'application/vnd.ms-excel',
			'xlsx'         => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
		);
	}

	/**
	 * Allowed mime types for the purchase invoices a client attaches for
	 * customs: what an online shop hands out, and nothing executable.
	 *
	 * @return array
	 */
	public static function invoice_mimes() {
		return array(
			'pdf'          => 'application/pdf',
			'jpg|jpeg|jpe' => 'image/jpeg',
			'png'          => 'image/png',
			'webp'         => 'image/webp',
		);
	}

	/**
	 * Allowed mime types for reception photos.
	 *
	 * @return array
	 */
	public static function photo_mimes() {
		return array(
			'jpg|jpeg|jpe' => 'image/jpeg',
			'png'          => 'image/png',
			'webp'         => 'image/webp',
			'gif'          => 'image/gif',
		);
	}

	/**
	 * Absolute path of the private storage directory.
	 *
	 * @return string
	 */
	public static function base_dir() {
		$uploads = wp_upload_dir( null, false );

		return $uploads['basedir'] . '/colisly-private';
	}

	/**
	 * Creates the private directory and its protection files if needed.
	 *
	 * @return bool Whether the directory exists and is protected.
	 */
	public static function ensure_dir() {
		$dir = self::base_dir();

		if ( ! wp_mkdir_p( $dir ) ) {
			return false;
		}

		// Apache: deny direct HTTP access. Nginx users must deny the folder in
		// their server config; random file names provide defence in depth.
		$htaccess = $dir . '/.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- writing a protection file at upload time.
			file_put_contents( $htaccess, "Require all denied\n<IfModule !mod_authz_core.c>\nOrder deny,allow\nDeny from all\n</IfModule>\n" );
		}

		$index = $dir . '/index.html';
		if ( ! file_exists( $index ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- writing a protection file at upload time.
			file_put_contents( $index, '' );
		}

		return true;
	}

	/**
	 * Moves an uploaded file ($_FILES entry) into the private directory.
	 *
	 * @param string $file_key Key in $_FILES.
	 * @param array  $mimes    Allowed mime types (extension pattern => type).
	 * @return array|WP_Error {
	 *     File info on success.
	 *
	 *     @type string $path Relative path inside the private directory.
	 *     @type string $name Original (sanitized) file name.
	 *     @type string $type Mime type.
	 * }
	 */
	public static function upload( $file_key, $mimes ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- callers verify their own nonce before calling.
		if ( empty( $_FILES[ $file_key ] ) || empty( $_FILES[ $file_key ]['name'] ) ) {
			return new WP_Error( 'colisly_no_file', __( 'No file uploaded.', 'colisly' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised in upload_entry().
		return self::upload_entry( $_FILES[ $file_key ], $mimes );
	}

	/**
	 * Returns the entries of a multi-file field, one per file.
	 *
	 * PHP posts `name="field[42][]"` as five parallel arrays keyed the same
	 * way, which nothing downstream wants. This turns them back into one
	 * array per file, for the sub-key asked, skipping the empty slots a
	 * browser posts for a file input left blank.
	 *
	 * @param string     $file_key Key in $_FILES.
	 * @param string|int $sub      Sub-key, for instance the parcel ID.
	 * @return array[] File entries: name, type, tmp_name, error, size.
	 */
	public static function entries( $file_key, $sub ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- callers verify their own nonce before calling.
		if ( empty( $_FILES[ $file_key ] ) || ! is_array( $_FILES[ $file_key ]['name'] ) ) {
			return array();
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised in upload_entry().
		$field   = $_FILES[ $file_key ];
		$entries = array();

		if ( ! isset( $field['name'][ $sub ] ) || ! is_array( $field['name'][ $sub ] ) ) {
			return array();
		}

		foreach ( $field['name'][ $sub ] as $i => $name ) {
			if ( '' === (string) $name ) {
				continue;
			}

			$entries[] = array(
				'name'     => $name,
				'type'     => isset( $field['type'][ $sub ][ $i ] ) ? $field['type'][ $sub ][ $i ] : '',
				'tmp_name' => isset( $field['tmp_name'][ $sub ][ $i ] ) ? $field['tmp_name'][ $sub ][ $i ] : '',
				'error'    => isset( $field['error'][ $sub ][ $i ] ) ? (int) $field['error'][ $sub ][ $i ] : 0,
				'size'     => isset( $field['size'][ $sub ][ $i ] ) ? (int) $field['size'][ $sub ][ $i ] : 0,
			);
		}

		return $entries;
	}

	/**
	 * Moves one uploaded file entry into the private directory.
	 *
	 * @param array $file      One $_FILES entry: name, type, tmp_name, error, size.
	 * @param array $mimes     Allowed mime types (extension pattern => type).
	 * @param int   $max_bytes Size cap, 0 for the server limit only.
	 * @param bool  $sideload  Take a local file rather than an HTTP upload.
	 * @return array|WP_Error Same shape as upload().
	 */
	public static function upload_entry( $file, $mimes, $max_bytes = 0, $sideload = false ) {
		if ( empty( $file['name'] ) ) {
			return new WP_Error( 'colisly_no_file', __( 'No file uploaded.', 'colisly' ) );
		}

		if ( $max_bytes > 0 && isset( $file['size'] ) && (int) $file['size'] > $max_bytes ) {
			return new WP_Error(
				'colisly_file_too_large',
				sprintf(
					/* translators: 1: file name, 2: size limit. */
					__( 'File %1$s is too large (limit %2$s).', 'colisly' ),
					sanitize_file_name( (string) $file['name'] ),
					size_format( $max_bytes )
				)
			);
		}

		if ( ! self::ensure_dir() ) {
			return new WP_Error( 'colisly_dir_error', __( 'The protected storage directory could not be created.', 'colisly' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		$to_private = static function ( $dirs ) {
			$dirs['subdir'] = '/colisly-private';
			$dirs['path']   = $dirs['basedir'] . '/colisly-private';
			$dirs['url']    = $dirs['baseurl'] . '/colisly-private';

			return $dirs;
		};

		add_filter( 'upload_dir', $to_private );

		$clean = array_map( 'sanitize_text_field', wp_unslash( $file ) );
		// tmp_name must not be sanitized: it is a server-generated path.
		$clean['tmp_name'] = isset( $file['tmp_name'] ) ? $file['tmp_name'] : '';

		$original_name = sanitize_file_name( $clean['name'] );

		$overrides = array(
			'test_form'                => false,
			'mimes'                    => $mimes,
			'unique_filename_callback' => static function ( $dir, $name, $ext ) {
				// Random, non-guessable file name (defence in depth).
				return gmdate( 'Ymd' ) . '-' . wp_generate_password( 24, false ) . strtolower( $ext );
			},
		);

		$result = $sideload ? wp_handle_sideload( $clean, $overrides ) : wp_handle_upload( $clean, $overrides );

		remove_filter( 'upload_dir', $to_private );

		if ( isset( $result['error'] ) ) {
			return new WP_Error( 'colisly_upload_error', $result['error'] );
		}

		return array(
			'path' => basename( $result['file'] ),
			'name' => $original_name,
			'type' => $result['type'],
		);
	}

	/**
	 * Resolves a stored relative path to an absolute file inside the private
	 * directory, rejecting traversal attempts.
	 *
	 * @param string $relative Relative path stored in the database.
	 * @return string|false Absolute path, or false when invalid.
	 */
	public static function resolve( $relative ) {
		$base = realpath( self::base_dir() );

		if ( ! $base || '' === (string) $relative ) {
			return false;
		}

		$path = realpath( trailingslashit( self::base_dir() ) . ltrim( (string) $relative, '/\\' ) );

		if ( ! $path || 0 !== strpos( $path, $base . DIRECTORY_SEPARATOR ) || ! is_file( $path ) ) {
			return false;
		}

		return $path;
	}

	/**
	 * Streams a private file to the browser and exits.
	 *
	 * @param string $relative      Relative path inside the private directory.
	 * @param string $download_name File name presented to the user.
	 * @param bool   $inline        Whether to display inline (images) instead of attachment.
	 * @return void
	 */
	public static function send( $relative, $download_name = '', $inline = false ) {
		$path = self::resolve( $relative );

		if ( ! $path ) {
			wp_die( esc_html__( 'File not found.', 'colisly' ), '', array( 'response' => 404 ) );
		}

		$type        = wp_check_filetype( $path );
		$disposition = $inline ? 'inline' : 'attachment';
		$name        = sanitize_file_name( $download_name ? $download_name : basename( $path ) );

		nocache_headers();
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Type: ' . ( $type['type'] ? $type['type'] : 'application/octet-stream' ) );
		header( 'Content-Disposition: ' . $disposition . '; filename="' . $name . '"' );
		header( 'Content-Length: ' . (string) filesize( $path ) );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- streaming a validated private file is the purpose of this endpoint.
		readfile( $path );
		exit;
	}

	/**
	 * Deletes a private file.
	 *
	 * @param string $relative Relative path inside the private directory.
	 * @return void
	 */
	public static function delete( $relative ) {
		$path = self::resolve( $relative );

		if ( $path ) {
			wp_delete_file( $path );
		}
	}
}
