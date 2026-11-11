<?php
/**
 * Plugin Name: PCZ Custom Gallery
 * Description: Creates a Gallery CPT that runs standalone purely via shortcodes.
 * Version: 1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// ==========================================
// 1. REGISTER CPT (HIDDEN FROM FRONTEND)
// ==========================================
function pcz_register_gallery_cpt() {
    $labels = array(
        'name'               => _x( 'Galleries', 'post type general name' ),
        'singular_name'      => _x( 'Gallery', 'post type singular name' ),
        'menu_name'          => _x( 'Galleries', 'admin menu' ),
        'add_new_item'       => __( 'Add New Gallery' ),
        'edit_item'          => __( 'Edit Gallery' ),
        'all_items'          => __( 'All Galleries' ),
    );

    $args = array(
        'labels'             => $labels,
        'public'             => false, // Hides it from search engines and frontend queries
        'show_ui'            => true,  // Keeps it visible in the WP Admin menu
        'show_in_menu'       => true,
        'show_in_rest'       => true,  // Enables the Gutenberg/Block editor
        'supports'           => array( 'title', 'editor' ),
        'menu_icon'          => 'dashicons-format-gallery',
        'has_archive'        => false,
        'publicly_queryable' => false, // Disables structural URLs like /gallery/post-name
    );

    register_post_type( 'custom_gallery', $args );
}
add_action( 'init', 'pcz_register_gallery_cpt' );

// ==========================================
// 2. ADD SHORTCODE COLUMN TO THE ALL GALLERIES LIST PAGE
// ==========================================

// Step A: Register the new custom column header
function pcz_add_gallery_admin_columns( $columns ) {
    // This inserts the 'Shortcode' column right after the 'Title' column
    $new_columns = array();
    foreach ( $columns as $key => $title ) {
        $new_columns[$key] = $title;
        if ( $key === 'title' ) {
            $new_columns['gallery_shortcode'] = __( 'Shortcode', 'textdomain' );
        }
    }
    return $new_columns;
}
add_filter( 'manage_custom_gallery_posts_columns', 'pcz_add_gallery_admin_columns' );


// Step B: Render the shortcode value inside each post row
function pcz_render_gallery_admin_column_content( $column, $post_id ) {
    if ( $column === 'gallery_shortcode' ) {
        $shortcode = '[custom_gallery id="' . $post_id . '"]';
        
        // Output a clean copyable input box inside the table cell row
        echo '<input type="text" 
                     value="' . esc_attr( $shortcode ) . '" 
                     readonly 
                     onclick="this.select();" 
                     style="width: 220px; font-family: monospace; background: #f6f7f7; text-align: center; font-size: 0.85rem; padding: 1px; border: 1px solid #c3c4c7; border-radius: 4px; cursor: pointer;" 
                     title="Click to select the shortcode" />';
    }
}
add_action( 'manage_custom_gallery_posts_custom_column', 'pcz_render_gallery_admin_column_content', 10, 2 );

// ==========================================
// 3. ADD SHORTCODE DISPLAY BOX IN ADMIN EDIT PAGE
// ==========================================

// Step A: Register
function pcz_add_shortcode_meta_box() {
    add_meta_box(
        'pcz_gallery_shortcode_box',
        __( 'Gallery Shortcode', 'textdomain' ),
        'pcz_render_shortcode_meta_box',
        'custom_gallery', // CPT slug
        'side',           // Displays in the right sidebar panel
        'high'
    );
}
add_action( 'add_meta_boxes', 'pcz_add_shortcode_meta_box' );

// Step A: Render
function pcz_render_shortcode_meta_box( $post ) {
    // Check if we have a valid, non-zero numeric ID assigned by WordPress
    if ( ! isset( $post->ID ) || empty( $post->ID ) || $post->ID <= 0 ) {
        echo '<p style="color:#d63638; font-style:italic;">Please wait for the post editor to initialize...</p>';
        return;
    }
    
    $shortcode = '[custom_gallery id="' . $post->ID . '"]';
    ?>
    <p><?php _e( 'Copy and paste this shortcode into any page or post:', 'textdomain' ); ?></p>
    <input type="text" 
           value="<?php echo esc_attr( $shortcode ); ?>" 
           readonly 
           onclick="this.select();" 
           style="width: 100%; font-family: monospace; background: #f0f0f1; text-align: center; font-size: 14px; padding: 6px; border: 1px solid #8c8f94; border-radius: 4px; cursor: pointer;" 
           title="Click to select all text" />
    <small style="display:block; margin-top:6px; color:#646970; text-align:center;">Click inside the box to highlight and copy.</small>
    <?php
}


// ==========================================
// 4. ADD SHORTCODE STYLES AND JAVASCRIPT
// ==========================================
function pcz_register_gallery_assets() {
    // Register the stylesheet
    wp_register_style(
        'pcz-gallery-style',
        plugin_dir_url( __FILE__ ) . 'assets/gallery.css',
        array(),
        '1.0.0'
    );

    // 2. Register the javascript
    wp_register_script(
        'pcz-gallery-script',
        plugin_dir_url( __FILE__ ) . 'assets/gallery.js',
        array(),
        '1.0.0',
        true
    );
}
add_action( 'wp_enqueue_scripts', 'pcz_register_gallery_assets' );

// ==========================================
// 5. SHORTCODE FRONTEND LOGIC
// ==========================================

function pcz_gallery_shortcode_handler( $atts ) {
    $atts = shortcode_atts( array(
        'id' => 0,
    ), $atts, 'custom_gallery' );

    $post_id = intval( $atts['id'] );
    if ( ! $post_id ) {
        return '<!-- No ID passed to custom gallery shortcode -->';
    }

    // Pull raw layout content bypassing typical theme filters
    $post_content = get_post_field( 'post_content', $post_id );
    if ( empty( $post_content ) ) {
        return '<!-- Target gallery content is completely empty -->';
    }

    $blocks = parse_blocks( $post_content );
    $gallery_images = array();

    foreach ( $blocks as $block ) {
        if ( $block['blockName'] === 'core/gallery' ) {
            $image_ids = array();

            // Fallback A: Pull structural ids from standard block attributes
            if ( ! empty( $block['attrs']['ids'] ) ) {
                $image_ids = $block['attrs']['ids'];
            } 
            // Fallback B: Extract IDs if nested inside innerBlocks (Modern WP Structure)
            elseif ( ! empty( $block['innerBlocks'] ) ) {
                foreach ( $block['innerBlocks'] as $inner_block ) {
                    if ( $inner_block['blockName'] === 'core/image' && ! empty( $inner_block['attrs']['id'] ) ) {
                        $image_ids[] = $inner_block['attrs']['id'];
                    }
                }
            }

            // Cap the total extracted collection to exactly 11 images
            $image_ids = array_slice( $image_ids, 0, 11 );

            foreach ( $image_ids as $attachment_id ) {
                $url = wp_get_attachment_image_url( $attachment_id, 'full' );
                $alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );

                if ( $url ) {
                    // FIX: Appending using [] prevents overwriting previous rows
                    $gallery_images[] = array(
                        'url'  => $url,
                        'alt'  => ! empty( $alt ) ? $alt : get_the_title( $attachment_id ),
                        'path' => wp_get_attachment_metadata( $attachment_id )
                    );
                }
            }
            break; // Exit block check after rendering the main container
        }
    }

    if ( empty( $gallery_images ) ) {
        return '<!-- Gallery found but contains 0 valid images. Ensure images are added inside the Block Editor -->';
    }

    // LOAD THE CSS AND JAVASCRIPT ONLY WHEN THIS SHORTCODE RENDERS
    wp_enqueue_style( 'pcz-gallery-style' );
    wp_enqueue_script( 'pcz-gallery-script' );

    // Output buffering container
    ob_start();
    ?>
    <div class="custom-gallery-ext" data-gallery-id="<?php echo esc_attr( $post_id ); ?>">
        <div class="container">
            <div class="gallery-grid fade-up">
                <?php foreach ( $gallery_images as $pcz_i => $img ) : ?>
                    <div class="g-item pcz-gallery-item" data-index="<?php echo esc_attr( $pcz_i ); ?>" role="button" tabindex="0" aria-label="Open Photo">
                        <img src="<?php echo esc_url( $img['url'] ); ?>" alt="<?php echo esc_attr( $img['alt'] ); ?>" />
                        <div class="g-item-overlay"></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="g-lightbox" id="gLightbox">
                <button class="g-lightbox-close" id="gLightboxClose" type="button" aria-label="Close">&times;</button>
                <button class="g-lightbox-nav g-lightbox-prev" id="gLightboxPrev" type="button" aria-label="Previous Photo">&#8249;</button>
                <img class="g-lightbox-img" id="gLightboxImg" src="" alt="<?php esc_attr_e( 'Pub Crawl Zurich photo', 'pubcrawl-zurich' ); ?>">
                <button class="g-lightbox-nav g-lightbox-next" id="gLightboxNext" type="button" aria-label="Next Photo">&#8250;</button>
                <div class="g-lightbox-counter" id="gLightboxCounter"></div>
            </div>
        </div>
    </div>
    <script>window.pczGalleryPhotos = <?php echo wp_json_encode( wp_list_pluck( $gallery_images, 'url') ); ?>;</script>
    <?php
    return ob_get_clean();
}
add_shortcode( 'custom_gallery', 'pcz_gallery_shortcode_handler' );




