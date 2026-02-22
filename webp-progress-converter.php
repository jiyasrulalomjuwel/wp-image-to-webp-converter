<?php
/*
Plugin Name: Webp Converter Pro
Version: 1.1.0
Description: Convert images to WebP with bulk actions, settings, and advanced UI.
Author: htmlrunner
Author URI: https://htmlrunner.com
*/

if (!defined('ABSPATH')) exit;

// Menu Hooks
add_action('admin_menu', function () {
    // Main Menu
    add_menu_page(
        'Webp Converter', 
        'Webp Converter', 
        'manage_options', 
        'webp-progress', 
        'wpc_render_page', 
        'dashicons-images-alt2', 
        25
    );
    
    // Submenu: Dashboard (Same as main menu)
    add_submenu_page(
        'webp-progress', 
        'Dashboard', 
        'Dashboard', 
        'manage_options', 
        'webp-progress', 
        'wpc_render_page'
    );
    
    // Submenu: Settings
    add_submenu_page(
        'webp-progress', 
        'Settings', 
        'Settings', 
        'manage_options', 
        'webp-settings', 
        'wpc_render_settings_page'
    );
});

// Assets
add_action('admin_enqueue_scripts', function ($hook) {
    // Only load scripts on our plugin pages
    if (strpos($hook, 'webp') === false) return;

    wp_enqueue_script('webp-js', plugin_dir_url(__FILE__) . 'webp.js', ['jquery'], '2.0', true);
    wp_enqueue_style('webp-css', plugin_dir_url(__FILE__) . 'webp-style.css', [], '2.0');

    wp_localize_script('webp-js', 'WPC', [
        'ajax' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('webp_nonce')
    ]);
});

/**
 * 1. Main Dashboard (Bulk List)
 */
function wpc_render_page() {
    $images = get_posts([
        'post_type' => 'attachment',
        'post_mime_type' => 'image',
        'posts_per_page' => -1,
        'post_status' => 'inherit',
    ]);
    
    // Filter out existing WebP and ensure metadata exists
    $convertible_images = array_filter($images, function($img) {
        return wp_get_attachment_metadata($img->ID) && get_post_mime_type($img->ID) !== 'image/webp';
    });
    ?>

    <div class="wrap wpc-wrap">
        <div class="wpc-bulk-bar" id="wpcBulkBar">
            <div class="bulk-info">
                <span class="dashicons dashicons-format-gallery"></span>
                <span id="selectedCount">0</span> images selected
            </div>
            <div class="bulk-actions">
                <div class="wpc-global-progress">
                    <div class="wpc-global-bar" id="globalProgressBar"></div>
                    <span id="globalProgressText">Processing...</span>
                </div>
                <button id="btnBulkConvert" class="button wpc-btn-primary">
                    <span class="dashicons dashicons-loop"></span> Convert Selected
                </button>
            </div>
        </div>

        <div class="wpc-header">
            <div>
                <h1>🖼️ Library Manager</h1>
                <p class="subtitle">Select images to optimize into WebP format.</p>
            </div>
            <div class="wpc-stats-mini">
                <span><strong><?php echo count($convertible_images); ?></strong> Pending</span>
            </div>
        </div>

        <?php if (empty($convertible_images)): ?>
            <div class="wpc-empty-state" style="text-align: center; padding: 50px; background: white; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
                <span class="dashicons dashicons-yes-alt" style="font-size: 48px; color: #00a32a; height: auto; width: auto;"></span>
                <h3 style="margin-top: 20px; color: #2c3338;">All Clean!</h3>
                <p style="color: #646970;">All your images are optimized or the library is empty.</p>
            </div>
        <?php else: ?>
            <div class="wpc-table-card">
                <table class="wpc-table">
                    <thead>
                        <tr>
                            <th class="check-col"><input type="checkbox" id="cb-select-all"></th>
                            <th>Preview</th>
                            <th>Filename</th>
                            <th>Current Type</th>
                            <th class="text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($convertible_images as $img): 
                        $url = wp_get_attachment_url($img->ID);
                        $mime = get_post_mime_type($img->ID);
                    ?>
                        <tr data-id="<?php echo $img->ID; ?>" class="wpc-row">
                            <td class="check-col">
                                <input type="checkbox" class="cb-image" value="<?php echo $img->ID; ?>">
                            </td>
                            <td class="thumb-col">
                                <div class="wpc-thumb">
                                    <img src="<?php echo esc_url($url); ?>" loading="lazy">
                                </div>
                            </td>
                            <td>
                                <strong><?php echo esc_html(basename(get_attached_file($img->ID))); ?></strong>
                            </td>
                            <td><span class="wpc-badge"><?php echo str_replace('image/', '', $mime); ?></span></td>
                            <td class="text-right">
                                <div class="action-wrapper">
                                    <button class="convert-btn button wpc-btn-outline">Convert</button>
                                    <div class="progress-mini">
                                        <div class="bar"></div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php
}

/**
 * 2. Settings Page
 */
function wpc_render_settings_page() {
    // Save Logic
    if (isset($_POST['wpc_save_settings']) && check_admin_referer('wpc_settings_action')) {
        update_option('wpc_quality', intval($_POST['wpc_quality']));
        echo '<div class="notice notice-success is-dismissible"><p>Settings saved successfully.</p></div>';
    }

    $quality = get_option('wpc_quality', 80);
    ?>
    <div class="wrap wpc-wrap">
        <h1>⚙️ Converter Settings</h1>
        
        <form method="post" action="">
            <?php wp_nonce_field('wpc_settings_action'); ?>
            
            <div class="wpc-card wpc-settings-card">
                <div class="wpc-card-header">
                    <h2>Compression Configuration</h2>
                </div>
                <div class="wpc-card-body">
                    <div class="wpc-form-group">
                        <label>WebP Quality Level</label>
                        <div class="wpc-range-wrapper">
                            <input type="range" name="wpc_quality" id="qualityRange" min="10" max="100" value="<?php echo esc_attr($quality); ?>">
                            <div class="range-value-box">
                                <span id="qualityValue"><?php echo esc_html($quality); ?></span>%
                            </div>
                        </div>
                        <p class="description">Higher percentage means better quality but larger file size. 80% is recommended.</p>
                    </div>
                </div>
                <div class="wpc-card-footer">
                    <button type="submit" name="wpc_save_settings" class="button wpc-btn-primary">Save Changes</button>
                </div>
            </div>
        </form>
    </div>
    <script>
        document.getElementById('qualityRange').addEventListener('input', function() {
            document.getElementById('qualityValue').innerText = this.value;
        });
    </script>
<?php
}

/**
 * 3. AJAX Handler
 */
add_action('wp_ajax_wpc_convert', function () {
    check_ajax_referer('webp_nonce', 'nonce');

    $id = (int) $_POST['id'];
    $file = get_attached_file($id);
    $quality = get_option('wpc_quality', 80); // Get dynamic quality

    if (!$file || !file_exists($file)) wp_send_json_error('File not found');

    $info = pathinfo($file);
    $mime = mime_content_type($file);

    switch ($mime) {
        case 'image/jpeg': $img = imagecreatefromjpeg($file); break;
        case 'image/png': 
            $img = imagecreatefrompng($file);
            imagepalettetotruecolor($img);
            imagealphablending($img, true);
            imagesavealpha($img, true);
            break;
        default: wp_send_json_error('Invalid format'); return;
    }

    $webp = $info['dirname'] . '/' . $info['filename'] . '.webp';
    
    // Convert
    imagewebp($img, $webp, $quality);
    imagedestroy($img);
    
    // Replace original source logic
    unlink($file);
    update_attached_file($id, $webp);
    wp_update_post(['ID' => $id, 'post_mime_type' => 'image/webp']);
    
    // Metadata regeneration
    require_once ABSPATH . 'wp-admin/includes/image.php';
    wp_update_attachment_metadata($id, wp_generate_attachment_metadata($id, $webp));

    wp_send_json_success();
});