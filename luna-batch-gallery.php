<?php
/**
 * Plugin Name: Luna - Batch Image Inserter 
 * Description: Adds/removes an image to/from WooCommerce product galleries in batch, with manual/auto run, position control, and beautiful UI.
 * Version: 2.3
 * Author: Luna
 */

add_action('admin_menu', function () {
    add_menu_page(
        'Luna - Image Inserter',
        'Luna - Image Inserter',
        'manage_woocommerce',
        'image-inserter',
        'image_inserter_page',
        'dashicons-format-image',
        26
    );
});

function image_inserter_page() {
    $message = '';

    if (isset($_POST['run_once']) && check_admin_referer('run_action_nonce')) {
        $settings = get_form_settings();
        update_option('last_settings', $settings);
        $message = process_batch($settings);
    }

    if (isset($_POST['run_auto']) && check_admin_referer('run_action_nonce')) {
        $settings = get_form_settings();
        update_option('last_settings', $settings);
        update_option('auto_run_active', true);
        update_option('auto_run_started_at', time());
        update_option('auto_settings', $settings);
    }

    if (isset($_POST['stop_action']) && check_admin_referer('stop_action_nonce')) {
        update_option('auto_run_active', false);
    }

    if (isset($_POST['reset_action']) && check_admin_referer('reset_action_nonce')) {
        delete_option('gallery_offset');
        delete_option('gallery_log');
        delete_option('auto_run_active');
        delete_option('auto_run_started_at');
        delete_option('auto_settings');
        delete_option('last_settings');
        $message = 'Progress has been reset.';
    }

    $count = wp_count_posts('product');
    $total = (int) $count->publish + (int) $count->private;
    $offset = (int) get_option('gallery_offset', 0);
    $remaining = max(0, $total - $offset);
    $progress = $total > 0 ? min(100, round(($offset / $total) * 100, 2)) : 0;
    $running = get_option('auto_run_active');
    $started_at = get_option('auto_run_started_at');
    $auto_settings = get_option('auto_settings');
    $last = get_option('last_settings', []);
    $log = get_option('gallery_log', []);

    if ($running && $remaining > 0 && is_array($auto_settings)) {
        $message = process_batch($auto_settings);
    }
?>
<style>
.luna-container {
    max-width: 800px;
    margin: 30px auto;
    background: #fff;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
    animation: fadeIn 0.5s ease-in-out;
}
@keyframes fadeIn {
    from {opacity: 0; transform: translateY(20px);}
    to {opacity: 1; transform: translateY(0);}
}
.settings-highlight {
    background: #eaf6ff;
    padding: 12px;
    border-left: 5px solid #2271b1;
    font-weight: bold;
    border-radius: 6px;
    margin: 10px 0;
}
.progress-bar {
    background: #e0e0e0;
    border-radius: 10px;
    overflow: hidden;
    height: 20px;
    margin-bottom: 10px;
}
.progress-bar-fill {
    background: linear-gradient(90deg, #2271b1, #46b450);
    height: 100%;
    width: <?= esc_attr($progress) ?>%;
    transition: width 0.5s ease;
    color: white;
    text-align: center;
    font-size: 13px;
    line-height: 20px;
}
.log-box {
    max-height: 200px;
    overflow-y: auto;
    background: #f9f9f9;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 5px;
}
</style>

<div class="wrap luna-container">
    <h1>🖼️ Luna - Batch Image Inserter</h1>

    <h2>📊 Progress</h2>
    <div class="progress-bar"><div class="progress-bar-fill"><?= esc_html($progress) ?>%</div></div>
    <ul>
        <li><strong>Total Products:</strong> <?= esc_html($total) ?></li>
        <li><strong>Processed:</strong> <?= esc_html($offset) ?></li>
        <li><strong>Remaining:</strong> <?= esc_html($remaining) ?></li>
        <li><strong>Status:</strong> <?= $running ? '🟢 Running' : '🔴 Stopped' ?></li>
        <li><strong>Started At:</strong> <?= $started_at ? date('Y-m-d H:i:s', $started_at) : 'N/A' ?></li>
    </ul>

    <?php if ($running && is_array($auto_settings)): ?>
    <div class="settings-highlight">
        🔧 Current Settings → Mode: <?= esc_html($auto_settings['mode']) ?> |
        Batch: <?= esc_html($auto_settings['batch_size']) ?> |
        Order: <?= esc_html($auto_settings['order']) ?> |
        Title: <?= esc_html($auto_settings['image_title']) ?> |
        Position: <?= esc_html($auto_settings['insert_position']) ?> <?= $auto_settings['insert_position'] === 'custom' ? '(Index: ' . esc_html($auto_settings['custom_position']) . ')' : '' ?>
    </div>
    <?php endif; ?>

    <form method="post" id="form">
        <?php wp_nonce_field('run_action_nonce'); ?>
        <table class="form-table">
            <tr><th>Batch Size</th><td><input type="number" name="batch_size" value="<?= esc_attr($last['batch_size'] ?? 100) ?>"></td></tr>
            <tr><th>Image Title</th><td><input type="text" name="image_title" value="<?= esc_attr($last['image_title'] ?? '') ?>"></td></tr>
            <tr>
                <th>Mode</th>
                <td>
                    <label><input type="radio" name="mode" value="add" <?= (!isset($last['mode']) || $last['mode'] === 'add') ? 'checked' : '' ?>> Add</label>
                    <label><input type="radio" name="mode" value="remove" <?= ($last['mode'] ?? '') === 'remove' ? 'checked' : '' ?>> Remove</label>
                </td>
            </tr>
            <tr id="insert_position_row">
                <th>Insert Position</th>
                <td>
                    <select name="insert_position" onchange="document.getElementById('custom_position').style.display = (this.value === 'custom') ? 'inline-block' : 'none';">
                        <option value="end" <?= ($last['insert_position'] ?? 'end') === 'end' ? 'selected' : '' ?>>End</option>
                        <option value="start" <?= ($last['insert_position'] ?? '') === 'start' ? 'selected' : '' ?>>Start</option>
                        <option value="custom" <?= ($last['insert_position'] ?? '') === 'custom' ? 'selected' : '' ?>>Custom</option>
                    </select>
                    <input type="number" name="custom_position" id="custom_position" placeholder="Index" value="<?= esc_attr($last['custom_position'] ?? '') ?>" style="<?= ($last['insert_position'] ?? '') === 'custom' ? '' : 'display:none;' ?>">
                </td>
            </tr>
            <tr>
                <th>Sort Order</th>
                <td>
                    <label><input type="radio" name="order" value="ASC" <?= (!isset($last['order']) || $last['order'] === 'ASC') ? 'checked' : '' ?>> Oldest to Newest</label><br>
                    <label><input type="radio" name="order" value="DESC" <?= ($last['order'] ?? '') === 'DESC' ? 'checked' : '' ?>> Newest to Oldest</label>
                </td>
            </tr>
        </table>
        <p>
            <input type="submit" name="run_once" class="button button-primary" value="▶️ Run Once">
            <input type="submit" name="run_auto" class="button button-secondary" value="🔁 Start Auto Run Every 5s">
        </p>
    </form>

    <form method="post" style="display:inline-block;">
        <?php wp_nonce_field('stop_action_nonce'); ?>
        <input type="submit" name="stop_action" class="button" value="⏹️ Stop Auto Run">
    </form>

    <form method="post" style="display:inline-block;">
        <?php wp_nonce_field('reset_action_nonce'); ?>
        <input type="submit" name="reset_action" class="button button-secondary" value="🔄 Reset Progress">
    </form>

    <?php if ($message): ?>
        <div class="notice notice-success"><p><?= esc_html($message) ?></p></div>
    <?php endif; ?>

    <?php if (!empty($log)): ?>
        <h2>📜 Log</h2>
        <div class="log-box"><ul>
            <?php foreach ($log as $entry): ?>
                <li><?= esc_html($entry) ?></li>
            <?php endforeach; ?>
        </ul></div>
    <?php endif; ?>
</div>

<script>
function toggleInsertRow() {
    const addMode = document.querySelector('input[name="mode"][value="add"]').checked;
    document.getElementById('insert_position_row').style.display = addMode ? 'table-row' : 'none';
}
document.querySelectorAll('input[name="mode"]').forEach(radio => {
    radio.addEventListener('change', toggleInsertRow);
});
toggleInsertRow();
</script>

<?php if ($running && $remaining > 0): ?>
    <script>setTimeout(() => document.getElementById("form").submit(), 5000);</script>
<?php endif;
}

function get_form_settings() {
    return [
        'batch_size' => isset($_POST['batch_size']) ? max(1, intval($_POST['batch_size'])) : 100,
        'image_title' => sanitize_text_field($_POST['image_title'] ?? ''),
        'mode' => $_POST['mode'] ?? 'add',
        'order' => $_POST['order'] ?? 'ASC',
        'insert_position' => $_POST['insert_position'] ?? 'end',
        'custom_position' => isset($_POST['custom_position']) ? max(0, intval($_POST['custom_position']) - 1) : null
    ];
}

function process_batch($settings) {
    $batch_size = $settings['batch_size'];
    $image_title = $settings['image_title'];
    $mode = $settings['mode'];
    $order = $settings['order'];
    $position = $settings['insert_position'] ?? 'end';
    $custom_index = $settings['custom_position'];

    $existing = get_page_by_title($image_title, OBJECT, 'attachment');
    if (!$existing) return "⚠️ Image '$image_title' not found.";

    $image_id = $existing->ID;
    $offset_option = 'gallery_offset';
    $offset = (int) get_option($offset_option, 0);

    $args = [
        'post_type' => 'product',
        'posts_per_page' => $batch_size,
        'offset' => $offset,
        'post_status' => ['publish', 'private'],
        'orderby' => 'ID',
        'order' => $order
    ];

    $products = get_posts($args);
    if (empty($products)) {
        update_option('auto_run_active', false);
        return '✅ No more products to update.';
    }

    $updated_count = 0;
    foreach ($products as $product) {
        $product_id = $product->ID;
        $gallery = get_post_meta($product_id, '_product_image_gallery', true);
        $gallery_array = $gallery ? explode(',', $gallery) : [];

        if ($mode === 'add' && !in_array($image_id, $gallery_array)) {
            if ($position === 'start') {
                array_unshift($gallery_array, $image_id);
            } elseif ($position === 'custom' && is_int($custom_index)) {
                array_splice($gallery_array, $custom_index, 0, $image_id);
            } else {
                $gallery_array[] = $image_id;
            }
            update_post_meta($product_id, '_product_image_gallery', implode(',', $gallery_array));
            $updated_count++;

            do_action('save_post', $product_id, get_post($product_id), true);
            do_action('woocommerce_update_product', $product_id);
        }

        if ($mode === 'remove' && in_array($image_id, $gallery_array)) {
            $gallery_array = array_filter($gallery_array, fn($id) => $id != $image_id);
            update_post_meta($product_id, '_product_image_gallery', implode(',', $gallery_array));
            $updated_count++;

            do_action('save_post', $product_id, get_post($product_id), true);
            do_action('woocommerce_update_product', $product_id);
        }
    }

    update_option($offset_option, $offset + $batch_size);

    $log = get_option('gallery_log', []);
    $log[] = date('Y-m-d H:i:s') . " — $mode $updated_count products (from " . ($offset + 1) . " to " . ($offset + $batch_size) . ")";
    if (count($log) > 30) $log = array_slice($log, -30);
    update_option('gallery_log', $log);

    return "✅ $mode completed: $updated_count products processed.";
}
?>
