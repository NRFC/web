<?php


/**
 * Get club sponsors for display in the footer
 *
 * @return array Array of sponsor posts
 */
function nrfc_get_gold_sponsors(): array {
	$args = array(
		'post_type' => 'sponsor',
		'posts_per_page' => -1,
		'meta_query' => array(
			array(
				'key' => '_sponsor_type',
				'value' => 'club',
				'compare' => '='
			)
		)
	);

	$sponsors = new WP_Query($args);

	return $sponsors->posts;
}

add_action( 'after_setup_theme', 'add_custom_image_sizes' );

function add_custom_image_sizes() {
	add_image_size( 'sponsor-footer', 9999, 50 );
	add_image_size( 'sponsor', 9999, 150 );
}

function enqueue_sponsors_styles() {
	$css_path = dirname( __DIR__ ) . '/nrfc/sponsors.css';
	$ver      = file_exists( $css_path ) ? (string) filemtime( $css_path ) : '1.0.0';

	// Enqueue sponsors CSS
	wp_enqueue_style(
		'sponsors-css',
		get_template_directory_uri() . '/../nrfc/sponsors.css',
		array(),
		$ver,
		'all'
	);
}

add_action( 'wp_enqueue_scripts', 'enqueue_sponsors_styles' );

function nrfc_enqueue_contact_styles() {
	// Load only on the Contact Us page
	if ( is_page( 'contact-us' ) ) { // you can also use the numeric ID
		wp_enqueue_style(
			'nrfc-contact-css',
			get_template_directory_uri() . '/../nrfc/contact-us.css',
			array(),
			'1.0.0',
			'all'
		);
	}
}

add_action( 'wp_enqueue_scripts', 'nrfc_enqueue_contact_styles' );

/**
 * Render header sponsors HTML
 *
 * @return string
 */
function nrfc_render_header_sponsors(): string {
	ob_start();
	include __DIR__ . '/header.php';
	return ob_get_clean();
}

add_shortcode( 'nrfc_header_sponsors', 'nrfc_render_header_sponsors' );

/**
 * Widget for displaying header sponsors
 */
class NRFC_Header_Sponsors_Widget extends WP_Widget {

	public function __construct() {
		parent::__construct(
			'nrfc_header_sponsors_widget',
			__( 'NRFC Header Sponsors', 'vantage' ),
			array(
				'description' => __( 'Displays the club sponsors list in header format.', 'vantage' ),
			)
		);
	}

	public function widget( $args, $instance ) {
		echo $args['before_widget'];
		if ( ! empty( $instance['title'] ) ) {
			echo $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ) . $args['after_title'];
		}
		echo nrfc_render_header_sponsors();
		echo $args['after_widget'];
	}

	public function form( $instance ) {
		$title = ! empty( $instance['title'] ) ? $instance['title'] : '';
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_attr_e( 'Title (optional):', 'vantage' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>
		<?php
	}

	public function update( $new_instance, $old_instance ) {
		$instance          = array();
		$instance['title'] = ( ! empty( $new_instance['title'] ) ) ? sanitize_text_field( $new_instance['title'] ) : '';
		return $instance;
	}
}

function nrfc_register_header_sponsors_widget() {
	register_widget( 'NRFC_Header_Sponsors_Widget' );
}
add_action( 'widgets_init', 'nrfc_register_header_sponsors_widget' );