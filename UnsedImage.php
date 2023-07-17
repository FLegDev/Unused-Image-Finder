<?php
/*
Plugin Name: Mon Plugin
Description: Plugin personnalisé pour ajouter une colonne "Utilisé dans" à l'onglet Média
*/

function wpse_add_custom_columns( $columns ) {
    $columns['used_in'] = 'Utilisé dans';
    return $columns;
}
add_filter( 'manage_media_columns', 'wpse_add_custom_columns' );

function wpse_fill_custom_columns( $column_name, $post_id ) {
    if ( 'used_in' == $column_name ) {
        $image_data = get_option('my_image_data');
        $image_name = basename(get_attached_file($post_id));
        if (isset($image_data[$image_name])) {
            // The image is used on one or more pages. 
            // Display the names of the pages as links.
            foreach ($image_data[$image_name]['pages'] as $page_name) {
                echo '<a href="' . get_permalink(get_page_by_title($page_name)) . '">' . $page_name . '</a>, ';
            }
        } else {
            // The image is not used on any page.
            echo 'Non utilisé';
        }
    }
}
add_action( 'manage_media_custom_column', 'wpse_fill_custom_columns', 10, 2 );

function wpse_add_crawl_button() {
  add_submenu_page(
      'upload.php',
      'Mettre à jour les images',
      'Mettre à jour les images',
      'activate_plugins', // Changer le capability pour "activate_plugins" au lieu de "manage_options"
      'update-images',
      'wpse_crawl_pages_and_save_images_info'
  );
}
add_action('admin_menu', 'wpse_add_crawl_button');


function wpse_crawl_pages_and_save_images_info() {
    // Get all posts and pages.
    $args = array(
        'post_type' => array( 'post', 'page' ),
        'post_status' => 'publish',
        'posts_per_page' => -1,
    );
    $query = new WP_Query( $args );

    // Array to store image data.
    $image_data = array();

    if ( $query->have_posts() ) {
        while ( $query->have_posts() ) {
            $query->the_post();

            // Get the post content.
            $content = get_the_content();

            // Use DOMDocument to parse the HTML.
            $doc = new DOMDocument();
            @$doc->loadHTML($content);

            // Get all img tags.
            $tags = $doc->getElementsByTagName('img');

            // Loop over the img tags and extract the src attribute.
            foreach ($tags as $tag) {
                $img_url = $tag->getAttribute('src');
                $img_name = basename($img_url);

                // If this image was found before, add the current page to its pages array.
                if (isset($image_data[$img_name])) {
                    $image_data[$img_name]['pages'][] = get_the_title();
                } else {
                    // This is a new image, add it to the array.
                    $image_data[$img_name] = array(
                        'url' => $img_url,
                        'pages' => array(get_the_title()),
                    );
                }
            }
        }
    }
    wp_reset_postdata();

    // Update the image data option.
    update_option('my_image_data', $image_data);

    // After the crawl, redirect back to the Media page.
    wp_redirect(admin_url('upload.php'));
    exit;
}
function wpse_custom_bulk_action( $actions ) {
  $actions['update_images'] = 'Mettre à jour les images';
  return $actions;
}
add_filter( 'bulk_actions-upload', 'wpse_custom_bulk_action' );

function wpse_custom_bulk_action_handler( $redirect_to, $doaction, $post_ids ) {
  if ( $doaction === 'update_images' ) {
      wpse_crawl_pages_and_save_images_info();
      $redirect_to = add_query_arg( 'updated_images', count( $post_ids ), $redirect_to );
  }
  return $redirect_to;
}
add_filter( 'handle_bulk_actions-upload', 'wpse_custom_bulk_action_handler', 10, 3 );

function wpse_custom_bulk_action_admin_notice() {
  if ( ! empty( $_REQUEST['updated_images'] ) ) {
      $images_updated = intval( $_REQUEST['updated_images'] );
      printf( '<div id="message" class="updated fade">' . _n( 'Mis à jour %s image.', 'Mis à jour %s images.', $images_updated, 'text_domain' ) . '</div>', $images_updated );
  }
}
add_action( 'admin_notices', 'wpse_custom_bulk_action_admin_notice' );



