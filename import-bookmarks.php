<?php
/**
 * Plugin Name: Import Bookmarks
 * Description: Import browser bookmarks as WordPress posts.
 * Author: Jan Boddez
 * Author URI: https://janboddez.tech/
 * License: General Public License v3.0
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: import-bookmarks
 * Version: 0.2.6
 *
 * @package Import_Bookmarks
 */

namespace Import_Bookmarks;

/** Prevent direct access. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Import the `NetscapeBookmarkParser` class. */
if ( ! class_exists( '\NetscapeBookmarkParser' ) ) {
	require_once dirname( __FILE__ ) . '/vendor/netscape-bookmark-parser/NetscapeBookmarkParser.php';
}

/**
 * Main plugin class and settings.
 */
class Bookmarks_Importer {
	/**
	 * WordPress' default post types.
	 *
	 * @var array DEFAULT_POST_TYPES Default post types, minus 'post' itself.
	 * @since 0.2.1
	 */
	private const DEFAULT_POST_TYPES = array(
		'page',
		'attachment',
		'revision',
		'nav_menu_item',
		'custom_css',
		'customize_changeset',
		'user_request',
		'oembed_cache',
		'wp_block',
	);

	/**
	 * Allowable post statuses.
	 *
	 * @var array POST_STATUSES Allowable post statuses.
	 * @since 0.2.1
	 */
	private const POST_STATUSES = array(
		'publish',
		'draft',
		'pending',
		'private',
	);

	/**
	 * Registers actions.
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'admin_menu', array( $this, 'create_menu' ) );
		add_action( 'admin_post_import_bookmarks', array( $this, 'import' ) );
	}

	/**
	 * Enables i18n of this plugin.
	 *
	 * @since 0.2.0
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'import-bookmarks', false, basename( dirname( __FILE__ ) ) . '/languages' );
	}

	/**
	 * Registers the plugin 'Tools' page.
	 *
	 * @since 0.1.0
	 */
	public function create_menu() {
		add_management_page(
			__( 'Import Bookmarks', 'import-bookmarks' ),
			__( 'Import Bookmarks', 'import-bookmarks' ),
			'import',
			'import-bookmarks',
			array( $this, 'settings_page' )
		);
	}

	/**
	 * Echoes the upload form.
	 *
	 * @since 0.1.0
	 */
	public function settings_page() {
		$options      = get_option( 'import_bookmarks', array() );
		$post_types   = array_diff( get_post_types(), self::DEFAULT_POST_TYPES );
		$post_formats = get_post_format_slugs();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Import Bookmarks', 'import-bookmarks' ); ?></h1>

			<form action="admin-post.php" method="post" enctype="multipart/form-data">
				<?php wp_nonce_field( 'import-bookmarks-run' ); ?>
				<input type="hidden" name="action" value="import_bookmarks">

				<table class="form-table">
					<tr valign="top">
						<th scope="row"><label for="bookmarks-file"><?php esc_html_e( 'Bookmarks File', 'import-bookmarks' ); ?></label></th>
						<td>
							<input type="file" name="bookmarks_file" id="bookmarks-file" accept="text/html">
							<p class="description"><?php esc_html_e( 'Bookmarks HTML file to be imported.', 'import-bookmarks' ); ?></p>
						</td>
					</tr>

					<tr valign="top">
						<th scope="row"><label for="post-type"><?php esc_html_e( 'Post Type', 'import-bookmarks' ); ?></label></th>
						<td>
							<select name="post_type" id="post-type">
								<?php
								foreach ( $post_types as $post_type ) :
									$post_type = get_post_type_object( $post_type );
									?>
									<option value="<?php $post_type->name; ?>" <?php ( ! empty( $options['post_type'] ) ? selected( $post_type->name, $options['post_type'] ) : '' ); ?>>
										<?php echo esc_html( $post_type->labels->singular_name ); ?>
									</option>
									<?php
								endforeach;
								?>
							</select>
							<p class="description"><?php esc_html_e( 'Imported bookmarks will be of this type.', 'import-bookmarks' ); ?></p>
						</td>
					</tr>

					<tr valign="top">
						<th scope="row"><label for="post-status"><?php esc_html_e( 'Post Status', 'import-bookmarks' ); ?></label></th>
						<td>
							<select name="post_status" id="post-status">
								<?php foreach ( self::POST_STATUSES as $post_status ) : ?>
									<option value="<?php echo esc_attr( $post_status ); ?>" <?php ( ! empty( $options['post_status'] ) ? selected( $post_status, $options['post_status'] ) : '' ); ?>><?php echo esc_html( ucfirst( $post_status ) ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Imported bookmarks will receive this status.', 'import-bookmarks' ); ?></p>
						</td>
					</tr>

					<tr valign="top">
						<th scope="row"><label for="post-format"><?php esc_html_e( 'Post Format', 'import-bookmarks' ); ?></label></th>
						<td>
							<select name="post_format" id="post-format">
								<?php foreach ( $post_formats as $post_format ) : ?>
									<option value="<?php echo esc_attr( $post_format ); ?>" <?php ( ! empty( $options['post_format'] ) ? selected( $post_format, $options['post_format'] ) : '' ); ?>><?php echo esc_html( get_post_format_string( $post_format ) ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( '&lsquo;Link&rsquo; is probably a good idea. Affects only Post Types that actually support Post Formats.', 'import-bookmarks' ); ?></p>
						</td>
					</tr>
				</table>

				<p class="submit"><?php submit_button( __( 'Import Bookmarks', 'import-bookmarks' ), 'primary', 'submit', false ); ?></p>
			</form>
		</div>

		<?php
		if ( ! empty( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'import-bookmarks-success' ) && ! empty( $_GET['message'] ) && 'success' === $_GET['message'] ) :
			?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e( 'Bookmarks imported!', 'import-bookmarks' ); ?></p>
			</div>
			<?php
		endif;
	}

	/**
	 * Runs the importer after a file was uploaded.
	 *
	 * @since 0.1.0
	 */
	public function import() {
		if ( ! current_user_can( 'import' ) ) {
			wp_die( esc_html__( 'You have insufficient permissions to access this page.', 'import-bookmarks' ) );
		}

		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'import-bookmarks-run' ) ) {
			wp_die( esc_html__( 'This page should not be accessed directly.', 'import-bookmarks' ) );
		}

		if ( empty( $_FILES['bookmarks_file'] ) || empty( $_FILES['bookmarks_file']['size'] ) ) {
			wp_die( esc_html__( 'Something went wrong uploading the file.', 'import-bookmarks' ) );
		}

		$bookmarks_file = wp_unslash( $_FILES['bookmarks_file'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$file_type      = wp_check_filetype( basename( $bookmarks_file['name'] ) );

		if ( 'text/html' !== $file_type['type'] ) {
			wp_die( esc_html__( 'Unsupported file type.', 'import-bookmarks' ) );
		}

		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$uploaded_file = wp_handle_upload( $bookmarks_file, array( 'test_form' => false ) );

		if ( empty( $uploaded_file['file'] ) || ! is_string( $uploaded_file['file'] ) ) {
			wp_die( esc_html__( 'Something went wrong uploading the file.', 'import-bookmarks' ) );
		}

		$options   = get_option( 'import_bookmarks', array() );
		$post_type = 'post';

		if ( ! empty( $_POST['post_type'] ) && in_array( $_POST['post_type'], $post_types, true ) ) {
			$post_type = sanitize_text_field( wp_unslash( $_POST['post_type'] ) );

			// Remember.
			$options['post_type'] = $post_type;
			update_option( 'import_bookmarks', $options, false );
		}

		$post_status = 'publish';

		if ( ! empty( $_POST['post_status'] ) && in_array( $_POST['post_status'], self::POST_STATUSES, true ) ) {
			$post_status = sanitize_text_field( wp_unslash( $_POST['post_status'] ) );

			// Remember.
			$options['post_status'] = $post_status;
			update_option( 'import_bookmarks', $options, false );
		}

		$post_format = 'standard';

		if ( ! empty( $_POST['post_format'] ) && in_array( $_POST['post_format'], get_post_format_slugs(), true ) ) {
			$post_format = sanitize_text_field( wp_unslash( $_POST['post_format'] ) );

			// Remember.
			$options['post_format'] = $post_format;
			update_option( 'import_bookmarks', $options, false );
		}

		$parser    = new \NetscapeBookmarkParser();
		$bookmarks = $parser->parseFile( $uploaded_file['file'] );

		if ( empty( $bookmarks ) || ! is_array( $bookmarks ) ) {
			wp_die( esc_html__( 'Empty or invalid bookmarks file.', 'import-bookmarks' ) );
		}

		foreach ( $bookmarks as $bookmark ) {
			$post_title    = sanitize_text_field( $bookmark['title'] );
			$post_content  = sanitize_text_field( $bookmark['note'] );
			$post_content .= "\n\n<a href='" . esc_url( $bookmark['uri'] ) . "'>" . $post_title . '</a>';
			$post_content  = trim( $post_content );

			/**
			 * Allow filtering the post's HTML.
			 *
			 * @since 2.1.0
			 */
			$post_content = apply_filters( 'import_bookmarks_post_content', $post_content, $bookmark, $post_type );

			$post_id = wp_insert_post(
				array(
					'post_title'   => $post_title,
					'post_content' => $post_content,
					'post_status'  => $post_status,
					'post_type'    => $post_type,
					'post_date'    => get_date_from_gmt( date( 'Y-m-d H:i:s', $bookmark['time'] ), 'Y-m-d H:i:s' ),
				)
			);

			if ( $post_id && post_type_supports( $post_type, 'custom-fields' ) ) {
				update_post_meta( $post_id, 'import_bookmarks_uri', esc_url_raw( $bookmark['uri'] ) );
			}

			if ( $post_id && post_type_supports( $post_type, 'post-formats' ) ) {
				set_post_format( $post_id, $post_format );
			}
		}

		wp_safe_redirect( admin_url( 'tools.php?page=import-bookmarks&message=success&_wpnonce=' . wp_create_nonce( 'import-bookmarks-success' ) ) );
		exit;
	}
}

new Bookmarks_Importer();
