add_action('init', function () {
    if (!is_admin()) return;

    if (get_option('rfs_gallery_remove_done') === 'yes') return;

    $existing = get_page_by_title('RFS - Delivery Information', OBJECT, 'attachment');
    if (!$existing) return;

    $image_id = $existing->ID;

    $offset = (int) get_option('rfs_gallery_remove_offset', 0);
    $per_page = 200;

    $args = array(
        'post_type'      => 'product',
        'posts_per_page' => $per_page,
        'offset'         => $offset,
        'post_status'    => 'publish',
        'orderby'        => 'ID',
        'order'          => 'ASC'
    );

    $products = get_posts($args);

    if (empty($products)) {
        update_option('rfs_gallery_remove_done', 'yes');
        return;
    }

    foreach ($products as $product) {
        $product_id = $product->ID;
        $gallery = get_post_meta($product_id, '_product_image_gallery', true);
        $gallery_array = $gallery ? explode(',', $gallery) : [];

        // Remove the image ID if it exists in gallery
        $updated_gallery = array_filter($gallery_array, function($id) use ($image_id) {
            return $id != $image_id;
        });

        // Only update if there's a change
        if (count($updated_gallery) !== count($gallery_array)) {
            update_post_meta($product_id, '_product_image_gallery', implode(',', $updated_gallery));
        }
    }

    update_option('rfs_gallery_remove_offset', $offset + $per_page);
});
