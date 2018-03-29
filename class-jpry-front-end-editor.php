<?php
/**
 * File for the Front End Editor class.
 *
 * @package JPry_Front_End_Editor
 */

/**
 * Front End Editor class.
 *
 * This class handles the logic to display and manage the front-end form.
 */
class JPry_Front_End_Editor {

	/**
	 * Default set of shortcode atts.
	 *
	 * Also used to limit what can be passed via $_GET.
	 *
	 * @var array
	 */
	protected $default_atts = array(
		'post_status' => 'publish',
	);

	/**
	 * The draft post object.
	 *
	 * @var WP_Post
	 */
	protected $draft_post = null;

	/**
	 * The ID used with CMB2.
	 *
	 * @var string
	 */
	protected $form_id = 'jpry_front_end_form';

	/**
	 * The slug for our front end form page.
	 *
	 * @var string
	 */
	protected $front_end_page_slug = 'front-end-form';

	/**
	 * The post type to edit.
	 *
	 * @var array
	 */
	protected $post_type = array( 'post' );

	/**
	 * The base URL for the plugin.
	 *
	 * @var string
	 */
	protected $url = '';

	/**
	 * Get the single instance of this class.
	 *
	 * @return JPry_Front_End_Editor
	 */
	public static function instance() {
		static $instance = null;
		if ( is_null( $instance ) ) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * Constructor.
	 */
	protected function __construct() {
		$this->url = trailingslashit( plugin_dir_url( JPRY_FRONT_END_EDITOR ) );
	}

	/**
	 * Add hidden fields to the form.
	 *
	 * @param CMB2   $cmb        CMB2 Object.
	 * @param array  $field_data Array of field data.
	 * @param string $name       The name that the hidden fields are grouped under.
	 */
	public function add_hidden_fields( $cmb, $field_data, $name = 'atts' ) {
		foreach ( $field_data as $key => $value ) {
			$cmb->add_hidden_field( array(
				'field_args' => array(
					'id'      => "{$name}[{$key}]",
					'type'    => 'hidden',
					'default' => $value,
				),
			) );
		}
	}

	/**
	 * Check various things around the post submission.
	 *
	 * @param CMB2 $cmb The CMB2 object.
	 *
	 * @return true|WP_Error True if all of the checks pass, or WP_Error object.
	 */
	protected function check_post_submission( $cmb ) {

		// Check security nonce.
		if ( ! isset( $_POST[ $cmb->nonce() ] ) || ! wp_verify_nonce( $_POST[ $cmb->nonce() ], $cmb->nonce() ) ) {
			return $cmb->prop( 'submission_error', new WP_Error( 'security_fail', __( 'Security check failed.', 'jpry-front-end-editor' ) ) );
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			return $cmb->prop( 'submission_error', new WP_Error( 'non_logged_in_user', __( 'You do not have permission to add new posts.', 'jpry-front-end-editor' ) ) );
		}

		// Check title submitted.
		if ( empty( $_POST['submitted_post_title'] ) ) {
			return $cmb->prop( 'submission_error', new WP_Error( 'post_data_missing', __( 'New post requires a title.', 'jpry-front-end-editor' ) ) );
		}

		// All good, return true.
		return true;
	}

	/**
	 * Set up all of the hooks in WordPress
	 */
	public function do_hooks() {

		// CMB2 Hooks.
		add_action( 'cmb2_init', array( $this, 'frontend_form_register' ) );
		add_action( 'cmb2_after_init', array( $this, 'handle_frontend_form_submission' ) );

		// Shortcode.
		add_shortcode( 'frontend_form', array( $this, 'frontend_form' ) );
	}

	/**
	 * Enqueue our scripts.
	 */
	public function enqueue_scripts() {

		// Localize our form JS.
		$data = array(
			'cancel_message'     => __( 'Unless you save your post you will lose any changes you have made.  Are you sure you want to leave this page?', 'jpry-front-end-editor' ),
			'redirect_url'       => isset( $_GET['action'] ) && 'edit' === $_GET['action'] ? esc_url_raw( remove_query_arg( 'action' ) ) : site_url(),
			'nonce'              => wp_create_nonce( 'jpry-front-end-editor' ),
			'cancel_button_text' => __( 'Cancel', 'jpry-front-end-editor' ),
			'ajaxurl'            => admin_url( '/admin-ajax.php' ),
		);

		// Enqueue and localize our init script.
		wp_enqueue_script(
			'jpry_frontend_form',
			$this->url . 'js/frontend-form.js',
			array(
				'jquery',
			)
		);
		wp_localize_script( 'jpry_frontend_form', 'jpry_front_end_form_config', $data );
	}

	/**
	 * Main display for our front-end form.
	 *
	 * @param array $atts The shortocde attributes.
	 */
	public function frontend_form( $atts = array() ) {
		try {
			echo $this->get_frontend_form( $atts );
		} catch ( Exception $e ) {
			?>
			<div class="cmb2-metabox cmb-field-list">
				<hr />
				<strong><?php echo $e->getMessage(); ?></strong>
				<br />&raquo;
				<a href="javascript:window.history.back();"><?php _e( 'Go Back', 'jpry-front-end-editor' ); ?></a>
			</div>
			<?php
		}
	}

	/**
	 * Register the front-end form.
	 */
	public function frontend_form_register() {
		// Create the CMB2 object.
		$cmb = new_cmb2_box( array(
			'id'           => $this->form_id,
			'object_types' => array( 'post' ),
			'hookup'       => false,
			'save_fields'  => false,
			// 'cmb_styles'   => false,
		) );

		// Register our fields.
		$cmb->add_field( array(
			'name'       => __( 'Title', 'jpry-front-end-editor' ),
			'id'         => 'submitted_post_title',
			'type'       => 'text',
		) );
		$cmb->add_field( array(
			'name'       => __( 'Post Content', 'jpry-front-end-editor' ),
			'id'         => 'submitted_post_content',
			'type'       => 'wysiwyg',
			'options'    => array(
				'textarea_rows' => 16,
				'media_buttons' => true,
			),
		) );
	}

	/**
	 * Get CMB2 object.
	 *
	 * @param int $object_id (Optional) Object ID to associate with CMB2 object.
	 *
	 * @return CMB2 Our CMB2 object.
	 */
	public function get_cmb_object( $object_id = 0 ) {
		return cmb2_get_metabox( $this->form_id, $object_id );
	}

	/**
	 * Get the singular draft post for this user
	 *
	 * @return WP_Post Draft post object
	 */
	public function get_draft() {
		if ( ! isset( $this->draft_post ) ) {
			$this->set_draft();
		}

		return $this->draft_post;
	}

	/**
	 * Get the singular draft post ID for this user
	 *
	 * @return int Draft post ID
	 */
	public function get_draft_id() {
		return $this->get_draft()->ID;
	}

	/**
	 * Handle the shortcode.
	 *
	 * @param array $atts Array of shortcode attributes.
	 *
	 * @throws \Exception When user is not able to create new posts (various reasons).
	 * @return string Form html.
	 */
	public function get_frontend_form( $atts = array() ) {

		if ( is_user_logged_in() && ! current_user_can( 'edit_posts' ) ) {
			throw new \Exception( __( 'You do not have permission to create new posts.', 'jpry-front-end-editor' ) );
		}

		// Get CMB2 metabox object.
		$cmb = cmb2_get_metabox( $this->form_id, $this->get_draft_id() );

		// Register media_view_settings filter (to override the Post ID passed to JS).
		add_filter( 'media_view_settings', array( $this, 'media_view_settings' ) );

		// Parse attributes.
		$atts = shortcode_atts( $this->default_atts, $atts, 'jpry-front-end-editor' );

		/*
		 * Let's add these attributes as hidden fields to our cmb form
		 * so that they will be passed through to our form submission
		 */
		$this->add_hidden_fields( $cmb, $atts );

		$this->enqueue_scripts();

		// Initiate our output variable.
		$output = '';

		// Get any submission errors.
		$output .= $this->handle_submission_errors( $cmb );

		// Get our form.
		$output .= cmb2_get_metabox_form( $cmb, $this->get_draft_id(), array( 'save_button' => __( 'Submit Post', 'jpry-front-end-editor' ) ) );

		return $output;
	}

	/**
	 * Handle the front-end form submission.
	 */
	public function handle_frontend_form_submission() {
		// If no form submission, bail.
		if ( empty( $_POST ) || ! isset( $_POST['object_id'] ) ) {
			return false;
		}

		// Bail if we didn't get a post object back.
		$post = get_post( (int) sanitize_text_field( $_POST['object_id'] ) );
		if ( is_null( $post ) ) {
			return false;
		}

		// Determine if we have a new post based on the auto-draft status (see get_default_post_to_edit()).
		$new_post = 'auto-draft' == $post->post_status;

		// Get CMB2 metabox objects.
		$cmb_form = cmb2_get_metabox( $this->form_id, $post->ID );

		// Do some security checks.
		$check = $this->check_post_submission( $cmb_form );
		if ( true !== $check ) {
			return $check;
		}

		// Set up initial post data.
		$post_data = array(
			'ID' => $post->ID,
		);

		// Get our shortcode attributes and set them as our initial post_data args
		if ( isset( $_POST['atts'] ) ) {
			// Sanitize user input
			$atts = array_map( 'sanitize_text_field', $_POST['atts'] );

			// Ensure only pre-determined keys can be added.
			$atts = shortcode_atts( $this->default_atts, $atts );

			// Loop through processed attributes and add them to post data.
			foreach ( $atts as $key => $value ) {
				$post_data[ $key ] = $value;
			}
			unset( $_POST['atts'] );
		}

		// Fetch sanitized values.
		$sanitized_values = $cmb_form->get_sanitized_values( $_POST );

		// Set our post data arguments.
		foreach ( array( 'title', 'content' ) as $field ) {
			$post_data[ "post_{$field}" ] = $sanitized_values[ "submitted_post_{$field}" ];
			unset( $sanitized_values[ "submitted_post_{$field}" ] );
		}

		if ( $new_post ) {
			$post_exists = get_page_by_title( $post_data['post_title'], OBJECT, $this->post_type );
			if ( ! empty( $post_exists ) && $post_exists->ID != $post->ID ) {
				return $cmb_form->prop( 'submission_error', new WP_Error( 'duplicate_post_title', __( 'There is already a post with that name.', 'jpry-front-end-editor' ) ) );
			}
		}

		// If we hit a snag, update the user.
		$result = $this->maybe_update_post( $post, $post_data );
		if ( is_wp_error( $result ) ) {
			return $cmb_form->prop( 'submission_error', $result );
		}

		// Let CMB2 save its own fields.
		$cmb_form->save_fields( $post->ID, 'post', $sanitized_values );

		/*
		 * Redirect back to the form page with a query variable with the new post ID.
		 * This will help double-submissions with browser refreshes
		 */
		wp_redirect( esc_url_raw( add_query_arg( 'post_submitted', $post->ID ) ) );
		exit;
	}

	/**
	 *
	 *
	 * @param CMB2 $cmb Custom Meta Box object.
	 *
	 * @return string
	 */
	public function handle_submission_errors( $cmb ) {
		$output = '';
		if ( ( $error = $cmb->prop( 'submission_error' ) ) && is_wp_error( $error ) ) {
			// If there was an error with the submission, add it to our ouput.
			$output .= '<h3 class="add-message">' . sprintf( __( 'There was an error in the submission: %s', 'jpry-front-end-editor' ), '<strong>' . $error->get_error_message() . '</strong>' ) . '</h3>';
		}

		// If the post was submitted successfully, notify the user.
		if ( isset( $_GET['post_submitted'] ) && ( $post = get_post( absint( $_GET['post_submitted'] ) ) ) ) {

			// Give a link to the new post.
			$new_post_permalink = get_permalink( $post->ID );
			$output .= '<h3 class="add-message">' . sprintf( __( 'You can view your post here: %s', 'jpry-front-end-editor' ), '<a href="' . $new_post_permalink . '">' . wp_kses_post( get_the_title( $post->ID ) ) . '</a></strong>' ) . '</h3>';
		}

		return $output;
	}

	/**
	 * Maybe update a post based on field values.
	 *
	 * This will check an array of updated post data against the current post values. If anything is different,
	 * then the post will be updated in the database. Possibly saves a call to the DB.
	 *
	 * @param WP_Post $post      The Post object.
	 * @param array   $post_data Array of post data.
	 *
	 * @return bool|int|WP_Error True if no update is needed, integer if the post was updated, or WP_Error if we needed
	 *                           to update and something went wrong.
	 */
	protected function maybe_update_post( WP_Post $post, array $post_data ) {
		$needs_update = false;
		$result       = true;
		$update       = array(
			'post_content',
			'post_title',
		);

		// Check each field.
		foreach ( $update as $field ) {
			if ( $post->$field !== $post_data[ $field ] ) {
				$needs_update = true;
				break;
			}
		}

		// Do the needful.
		if ( $needs_update ) {
			$result = wp_update_post( $post_data, true );
		}

		return $result;
	}

	/**
	 * Filter the media view settings.
	 *
	 * This adds the correct post ID for localization.
	 *
	 * @param array $settings The array of settings.
	 *
	 * @return array The modified array of settings.
	 */
	public function media_view_settings( $settings ) {
		$post_id          = $this->get_draft_id();
		$settings['post'] = array(
			'id'    => $post_id,
			'nonce' => wp_create_nonce( 'update-post_' . $post_id ),
		);

		return $settings;
	}

	/**
	 * Sets (and maybe creates) the singular draft post for this user.
	 *
	 * This uses the menu_order setting to create a unique draft post that is easy to retrieve, and is unique to each
	 * user.
	 */
	public function set_draft() {

		// Check for an existing draft post.
		$drafts = new WP_Query( array(
			'post_type'      => $this->post_type,
			'post_author'    => get_current_user_id(),
			'post_status'    => 'auto-draft',
			'orderby'        => 'menu_order',
			'posts_per_page' => 1,
		) );

		$draft = ! empty( $drafts->posts ) ? end( $drafts->posts ) : false;
		$draft = isset( $draft->menu_order ) && $draft->menu_order >= 1999999999 ? $draft : false;

		if ( ! $draft ) {
			$post_data = array(
				'post_title'  => __( 'Auto Draft' ),
				'post_type'   => $this->post_type,
				'post_status' => 'auto-draft',
				'menu_order'  => 1999999999,
			);

			$draft = get_post( wp_insert_post( $post_data ) );
		}

		$this->draft_post = $draft;
	}
}
