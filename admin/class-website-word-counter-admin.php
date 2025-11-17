<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://customwp.io
 * @since      1.0.0
 *
 * @package    Website_Word_Counter
 * @subpackage Website_Word_Counter/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * @package    Website_Word_Counter
 * @subpackage Website_Word_Counter/admin
 */
class Website_Word_Counter_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Transient key for storing word count.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $transient_key    The transient key name.
	 */
	private $transient_key = 'website_word_counter_total';

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {
		if ( ! is_admin() ) {
			return;
		}

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/website-word-counter-admin.css', array(), $this->version, 'all' );
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
		if ( ! is_admin() ) {
			return;
		}

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/website-word-counter-admin.js', array( 'jquery' ), $this->version, false );

		wp_localize_script(
			$this->plugin_name,
			'WebsiteWordCounter',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'website_word_counter_nonce' ),
			)
		);
	}

	/**
	 * Register the admin menu page.
	 *
	 * @since    1.0.0
	 */
	public function register_menu_page() {
		if ( ! is_admin() ) {
			return;
		}

		add_menu_page(
			__('Website Word Counter', 'website-word-counter'),
			__('Website Word Counter', 'website-word-counter'),
			'manage_options',
			'website-word-counter',
			array( $this, 'render_admin_page' ),
			'dashicons-editor-paste-text',
			65
		);
	}

	/**
	 * Render the admin settings page.
	 *
	 * @since    1.0.0
	 */
	public function render_admin_page() {
		// Get cached word count from transient
		$cached_data = get_transient( $this->transient_key );
		
		echo '<div class="wrap">';
		echo '<h1>' . __('Website Word Counter', 'website-word-counter') . '</h1>';
		echo '<div id="website-word-counter-total-words">';
		
		if ( false !== $cached_data && is_array( $cached_data ) ) {
			$total = isset( $cached_data['total'] ) ? $cached_data['total'] : 0;
			$by_post_type = isset( $cached_data['by_post_type'] ) ? $cached_data['by_post_type'] : array();
			
			echo '<h2 class="word-count-display">';
			echo '<strong>' . __('Total Words:', 'website-word-counter') . '</strong> ';
			echo '<span id="wwc-total-count">' . number_format( $total ) . '</span>';
			echo '</h2>';
		} else {
			echo '<p class="word-count-display">';
			echo '<strong>' . __('Total Words:', 'website-word-counter') . '</strong> ';
			echo '<span id="wwc-total-count">' . __('Not calculated yet', 'website-word-counter') . '</span>';
			echo '</p>';
		}
		
		// Always show the breakdown table structure
		echo '<hr>';
		echo '<h2>' . __('Words by Post Type', 'website-word-counter') . '</h2>';
		echo '<table class="wp-list-table widefat fixed striped all-count-table">';
		echo '<thead><tr>';
		echo '<th>' . __('Post Type', 'website-word-counter') . '</th>';
		echo '<th>' . __('Word Count', 'website-word-counter') . '</th>';
		echo '</tr></thead>';
		echo '<tbody id="wwc-post-type-breakdown">';
		
		if ( false !== $cached_data && is_array( $cached_data ) ) {
			$by_post_type = isset( $cached_data['by_post_type'] ) ? $cached_data['by_post_type'] : array();
			if ( ! empty( $by_post_type ) ) {
				foreach ( $by_post_type as $post_type => $count ) {
					$post_type_obj = get_post_type_object( $post_type );
					$post_type_label = $post_type_obj ? $post_type_obj->labels->name : $post_type;
					echo '<tr>';
					echo '<td>' . esc_html( $post_type_label ) . '</td>';
					echo '<td class="wwc-count-' . esc_attr( $post_type ) . '">' . number_format( $count ) . '</td>';
					echo '</tr>';
				}
			} else {
				echo '<tr><td colspan="2">' . __('No data available', 'website-word-counter') . '</td></tr>';
			}
		} else {
			echo '<tr><td colspan="2">' . __('Not calculated yet', 'website-word-counter') . '</td></tr>';
		}
		
		echo '</tbody></table>';
		
		echo '<button id="wwc-refresh" class="button button-primary">' . __('Refresh Count', 'website-word-counter') . '</button>';
		echo '</div>';
	}

	/**
	 * Handle AJAX request to refresh word count.
	 *
	 * @since    1.0.0
	 */
	public function ajax_refresh_count() {
		// Security checks: only allow in admin and for users with proper capabilities
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized access.', 'website-word-counter' ) ) );
			return;
		}

		check_ajax_referer( 'website_word_counter_nonce', 'nonce' );
	
		// Delete old transient to force refresh
		delete_transient( $this->transient_key );
		
		$data = $this->calculate_total_words();
		
		// Save to transient (expires in 12 hours)
		set_transient( $this->transient_key, $data, 12 * HOUR_IN_SECONDS );
		
		// Prepare post type labels for response
		$by_post_type_with_labels = array();
		foreach ( $data['by_post_type'] as $post_type => $count ) {
			$post_type_obj = get_post_type_object( $post_type );
			$post_type_label = $post_type_obj ? $post_type_obj->labels->name : $post_type;
			$by_post_type_with_labels[ $post_type ] = array(
				'label' => $post_type_label,
				'count' => $count,
			);
		}
		
		wp_send_json_success( array( 
			'total_words' => $data['total'],
			'by_post_type' => $by_post_type_with_labels
		) );
	}

	/**
	 * Calculate total word count across all published posts of all post types.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return   array    Array with 'total' and 'by_post_type' keys.
	 */
	private function calculate_total_words() {
		// Additional security check - only run in admin context
		if ( ! is_admin() ) {
			return array(
				'total' => 0,
				'by_post_type' => array(),
			);
		}

		$total_words = 0;
		$by_post_type = array();

		// Get all published posts of all post types
		$args = array(
			'post_type'      => 'any',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids', // Only get IDs for better performance
		);

		$posts = get_posts( $args );

		foreach ( $posts as $post_id ) {
			$post = get_post( $post_id );

			if ( ! $post ) {
				continue;
			}

			$post_type = $post->post_type;

			// Combine title and content
			$content = $post->post_title . ' ' . $post->post_content;

			// Add ACF fields content if ACF is available
			if ( function_exists( 'get_fields' ) ) {
				$acf_content = $this->get_acf_fields_content( $post_id );
				if ( ! empty( $acf_content ) ) {
					$content .= ' ' . $acf_content;
				}
			}

			// Strip HTML tags and decode entities
			$content = wp_strip_all_tags( $content );
			$content = html_entity_decode( $content, ENT_QUOTES, 'UTF-8' );

			// Count words (handles multiple spaces and newlines)
			$content = trim( $content );
			if ( ! empty( $content ) ) {
				$word_count = str_word_count( $content, 0, '0123456789' );
				$total_words += $word_count;
				
				// Track by post type
				if ( ! isset( $by_post_type[ $post_type ] ) ) {
					$by_post_type[ $post_type ] = 0;
				}
				$by_post_type[ $post_type ] += $word_count;
			}
		}

		// Sort by word count descending
		arsort( $by_post_type );

		return array(
			'total' => $total_words,
			'by_post_type' => $by_post_type,
		);
	}

	/**
	 * Get text content from all ACF fields for a post.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    int    $post_id    The post ID.
	 * @return   string    Combined text content from all ACF fields.
	 */
	private function get_acf_fields_content( $post_id ) {
		if ( ! function_exists( 'get_fields' ) ) {
			return '';
		}

		$fields = get_fields( $post_id );
		if ( ! $fields || ! is_array( $fields ) ) {
			return '';
		}

		$content_parts = array();
		$this->extract_acf_text_content( $fields, $content_parts );

		return implode( ' ', $content_parts );
	}

	/**
	 * Recursively extract text content from ACF fields.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array    $fields         ACF fields array.
	 * @param    array    $content_parts  Array to store extracted text content.
	 */
	private function extract_acf_text_content( $fields, &$content_parts ) {
		if ( ! is_array( $fields ) ) {
			return;
		}

		foreach ( $fields as $field_name => $field_value ) {
			if ( empty( $field_value ) && $field_value !== '0' && $field_value !== 0 ) {
				continue;
			}

			// Try to get field object to check field type
			$field_type = '';
			if ( function_exists( 'get_field_object' ) ) {
				$field_object = get_field_object( $field_name );
				if ( $field_object && isset( $field_object['type'] ) ) {
					$field_type = $field_object['type'];
				}
			}

			// Handle different field types
			switch ( $field_type ) {
				case 'text':
				case 'textarea':
				case 'wysiwyg':
				case 'email':
				case 'url':
					// Simple text fields
					if ( is_string( $field_value ) ) {
						$content_parts[] = $field_value;
					}
					break;

				case 'repeater':
					// Repeater fields - iterate through rows
					if ( is_array( $field_value ) ) {
						foreach ( $field_value as $row ) {
							if ( is_array( $row ) ) {
								$this->extract_acf_text_content( $row, $content_parts );
							} elseif ( is_string( $row ) ) {
								$content_parts[] = $row;
							}
						}
					}
					break;

				case 'group':
					// Group fields - recursively extract
					if ( is_array( $field_value ) ) {
						$this->extract_acf_text_content( $field_value, $content_parts );
					}
					break;

				case 'flexible_content':
					// Flexible content fields
					if ( is_array( $field_value ) ) {
						foreach ( $field_value as $layout ) {
							if ( is_array( $layout ) ) {
								// Skip 'acf_fc_layout' key and extract other fields
								foreach ( $layout as $layout_key => $layout_value ) {
									if ( $layout_key !== 'acf_fc_layout' && is_array( $layout_value ) ) {
										$this->extract_acf_text_content( array( $layout_key => $layout_value ), $content_parts );
									} elseif ( $layout_key !== 'acf_fc_layout' && is_string( $layout_value ) ) {
										$content_parts[] = $layout_value;
									}
								}
							}
						}
					}
					break;

				default:
					// For unknown field types or when field type can't be determined
					// Try to extract text based on value type
					if ( is_string( $field_value ) ) {
						$content_parts[] = $field_value;
					} elseif ( is_array( $field_value ) ) {
						// Check if it's a numeric array (likely repeater rows) or associative (likely group/nested)
						$is_numeric_array = array_keys( $field_value ) === range( 0, count( $field_value ) - 1 );
						
						if ( $is_numeric_array ) {
							// Likely a repeater - process each row
							foreach ( $field_value as $row ) {
								if ( is_array( $row ) ) {
									$this->extract_acf_text_content( $row, $content_parts );
								} elseif ( is_string( $row ) ) {
									$content_parts[] = $row;
								}
							}
						} else {
							// Likely a group or nested fields - recursively extract
							$this->extract_acf_text_content( $field_value, $content_parts );
						}
					}
					break;
			}
		}
	}
}
