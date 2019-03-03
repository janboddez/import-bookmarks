<?php
/**
 * Plugin Name: Import Bookmarks
 * Description: Import browser bookmarks as WordPress posts.
 * Author: Jan Boddez
 * Author URI: https://janboddez.tech/
 * Version: 0.2.1
 */

namespace Import_Bookmarks;

require_once dirname( __FILE__ ) . '/vendor/netscape-bookmark-parser/NetscapeBookmarkParser.php';

/**
 * Main plugin class and settings.
 */
class Bookmarks_Importer {
	/**
	 * Holds WordPress default post types, minus 'post' itself.
	 *
	 * @since 0.2.1
	 */
	private $default_post_types = array(
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
	 * @since 0.2.1
	 */
	private $post_statuses = array(
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
			'manage_options',
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
		?>
		<div class="wrap">
			<h1><?php _e( 'Import Bookmarks', 'import-bookmarks' ); ?></h1>
			<form action="admin-post.php" method="post" enctype="multipart/form-data">
				<?php wp_nonce_field( 'import-bookmarks', 'import-bookmarks-nonce' ); ?>
				<input type="hidden" name="action" value="import_bookmarks">

				<table class="form-table">
					<tr valign="top">
						<th scope="row"><label for="bookmarks-file"><?php _e( 'Bookmarks File', 'import-bookmarks' ); ?></label></th>
						<td><input type="file" name="bookmarks_file" id="bookmarks-file"></td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="post-type"><?php _e( 'Post Type', 'import-bookmarks' ); ?></label></th>
						<td><select name="post_type" id="post-type">
							<?php
							$post_types = array_diff( get_post_types(), $this->default_post_types );
							?>
							<?php foreach ( $post_types as $post_type ) : ?>
								<option value="<?php $post_type = get_post_type_object( $post_type ); echo esc_attr( $post_type->name ); ?>"><?php echo esc_html( $post_type->labels->singular_name ); ?></option>
							<?php endforeach; ?>
						</select></td>
					</tr>
					<tr valign="top">
						<th scope="row"><label for="post-status"><?php _e( 'Post Status', 'import-bookmarks' ); ?></label></th>
						<td><select name="post_status" id="post-status">
							<?php foreach ( $this->post_statuses as $post_status ) : ?>
								<option value="<?php echo esc_attr( $post_status ); ?>"><?php echo esc_html( ucfirst( $post_status ) ); ?></option>
							<?php endforeach; ?>
						</select></td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e( 'Open in New Tab', 'import-bookmarks' ); ?></th>
						<td><label><input type="checkbox" name="force_new_tab" value="1"> <?php _e( 'Force bookmarks in newly created posts to open in a new tab.', 'import-bookmarks' ); ?></label>
					</tr>
				</table>
				<p class="submit"><?php submit_button( __( 'Import Bookmarks', 'import-bookmarks' ), 'primary', 'submit', false ); ?></p>
			</form>
		</div>

		<?php if ( ! empty( $_GET['message'] ) && 'success' === $_GET['message'] ) : ?>
		<div class="notice notice-success">
			<p><?php _e( 'Bookmarks imported!', 'import-bookmarks' ); ?></p>
		</div>
		<?php endif;
	}

	/**
	 * Runs the importer after a file was uploaded.
	 *
	 * @since 0.1.0
	 */
	public function import() {
		set_time_limit( 0 );

		if ( ! current_user_can( 'import' ) ) {
			return;
		}

		if ( ! isset( $_POST['import-bookmarks-nonce'] ) || ! wp_verify_nonce( $_POST['import-bookmarks-nonce'], 'import-bookmarks' ) ) {
			return;
		}

		if ( empty( $_FILES['bookmarks_file'] ) || 0 === $_FILES['bookmarks_file']['size'] ) {
			return;
		}

		$file_type = wp_check_filetype( basename( $_FILES['bookmarks_file']['name'] ) );

		if ( 'text/html' !== $file_type['type'] ) {
			return;
		}

		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/file.php' );
		}

		$uploaded_file = wp_handle_upload( $_FILES['bookmarks_file'], array( 'test_form' => false ) );

		if ( empty( $uploaded_file['file'] ) || ! is_string( $uploaded_file['file'] ) ) {
			return;
		}

		// Default value.
		$post_type = 'post';

		$post_types = array_diff( get_post_types(), $this->default_post_types );

		if ( ! empty( $_POST['post_type'] ) && in_array( $_POST['post_type'], $post_types ) ) {
			$post_type = $_POST['post_type'];
		}

		$post_status = 'publish';

		if ( ! empty( $_POST['post_status'] ) && in_array( $_POST['post_status'], $this->post_statuses ) ) {
			$post_status = $_POST['post_status'];
		}

		$force_new_tab = '';

		if ( ! empty( $_POST['force_new_tab'] ) && '1' === $_POST['force_new_tab'] ) {
			$force_new_tab = ' target="_blank" rel="noreferrer noopener"';
		}

		$parser    = new \NetscapeBookmarkParser();
		$bookmarks = $parser->parseFile( $uploaded_file['file'] );

		foreach ( $bookmarks as $bookmark ) {
			$post_title    = sanitize_text_field( $bookmark['title'] );
			$post_content  = sanitize_text_field( $bookmark['note'] );
			$post_content .= "\n\n<a href='" . esc_url( $bookmark['uri'] ) . "'" . $force_new_tab . '>' . $post_title . '</a>';
			$post_content  = trim( $post_content );

			if ( function_exists( 'use_block_editor_for_post_type' ) && use_block_editor_for_post_type( $post_type ) ) {
				// Convert to paragraph blocks.
				$post_content = str_replace(
					"\n\n",
					"</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>",
					$post_content
				);
				$post_content = "<!-- wp:paragraph -->\n<p>" . $post_content . "</p>\n<!-- /wp:paragraph -->";
			}

			$post_id = wp_insert_post( array(
				'post_title'   => $post_title,
				'post_content' => $post_content,
				'post_status'  => $post_status,
				'post_type'    => $post_type,
				'post_date'    => get_date_from_gmt( date( 'Y-m-d H:i:s', $bookmark['time'] ), 'Y-m-d H:i:s' ),
			) );

			if ( $post_id && post_type_supports( $post_type, 'custom-fields' ) ) {
				// Also store actual URL, in a custom field.
				update_post_meta( $post_id, 'import_bookmarks_uri', esc_url_raw( $bookmark['uri'] ) );
			}
		}

		wp_redirect( admin_url( 'tools.php?page=import-bookmarks&message=success' ) );
		exit;
	}
}

new Bookmarks_Importer();
