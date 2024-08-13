<?php
/*
Plugin Name: Rename Images Plugin
Description: Adds a button to rename all images attached to a post using the post title, geotags images with saved latitude and longitude, and displays EXIF data in the media selection window.
Version: 1.0.7
Author: Jackson Green
*/

require 'plugin-update-checker/plugin-update-checker.php';

$myUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
    'https://github.com/exploitdev/rename-images-boomit/',
    __FILE__,
    'rename-images-plugin'
);


// Add settings page
add_action('admin_menu', 'rip_add_settings_page');
add_action('admin_init', 'rip_register_settings');

function rip_add_settings_page() {
    add_menu_page(
        'BoomIT Rename Images Settings',
        'Rename Images',
        'manage_options',
        'rip-settings',
        'rip_settings_page_callback',
        'dashicons-palmtree'
    );
}

function rip_register_settings() {
    register_setting('rip_settings_group', 'rip_latitude');
    register_setting('rip_settings_group', 'rip_longitude');
    register_setting('rip_settings_group', 'rip_license_key');
}

function rip_settings_page_callback() {
    $license_key = get_option('rip_license_key');
    ?>
    <div class="wrap">
        <h1>Rename Images Plugin Settings</h1>
        <form method="post" action="">
            <?php
            settings_fields('rip_settings_group');
            do_settings_sections('rip_settings_group');
            ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Latitude</th>
                    <td><input type="text" name="rip_latitude" value="<?php echo esc_attr(get_option('rip_latitude')); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Longitude</th>
                    <td><input type="text" name="rip_longitude" value="<?php echo esc_attr(get_option('rip_longitude')); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">License Key</th>
                    <td>
                        <input type="text" name="rip_license_key" id="rip_license_key" value="<?php echo esc_attr($license_key); ?>" readonly />
                        <button type="submit" name="rip_generate_license_key" class="button">Generate License Key</button>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// Handle form submissions including license key generation and saving latitude/longitude
add_action('admin_init', 'rip_handle_form_submission');

function rip_handle_form_submission() {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        // Save latitude and longitude fields
        if (isset($_POST['rip_latitude'])) {
            update_option('rip_latitude', sanitize_text_field($_POST['rip_latitude']));
        }
        if (isset($_POST['rip_longitude'])) {
            update_option('rip_longitude', sanitize_text_field($_POST['rip_longitude']));
        }

        // Handle license key generation
        if (isset($_POST['rip_generate_license_key'])) {
            $license_key = rip_generate_license_key();

            if ($license_key) {
                update_option('rip_license_key', $license_key);

                // Send the license key to the license server
                $response = wp_remote_post('https://test.andmuchmore.dev/save-license-key.php', array(
                    'body' => array(
                        'license_key' => $license_key,
                        'domain'      => home_url(),
                    ),
                ));

                if (is_wp_error($response)) {
                    add_settings_error('rip_license_key', 'rip_license_key_error', 'Failed to communicate with the license server.');
                } else {
                    $response_body = wp_remote_retrieve_body($response);
                    $response_data = json_decode($response_body, true);

                    if (isset($response_data['status']) && $response_data['status'] === 'success') {
                        add_settings_error('rip_license_key', 'rip_license_key_success', 'License key generated and saved successfully.', 'updated');
                    } else {
                        add_settings_error('rip_license_key', 'rip_license_key_error', 'Failed to save license key to the server.');
                    }
                }
            }
        }
    }
}

function generate_license_key_on_activate(){
    $license_key = rip_generate_license_key();

    if ($license_key) {
        update_option('rip_license_key', $license_key);

        // Send the license key to the license server
        $response = wp_remote_post('https://test.andmuchmore.dev/save-license-key.php', array(
            'body' => array(
                'license_key' => $license_key,
                'domain'      => home_url(),
            ),
        ));

        if (is_wp_error($response)) {
            add_settings_error('rip_license_key', 'rip_license_key_error', 'Failed to communicate with the license server.');
        } else {
            $response_body = wp_remote_retrieve_body($response);
            $response_data = json_decode($response_body, true);

            if (isset($response_data['status']) && $response_data['status'] === 'success') {
                add_settings_error('rip_license_key', 'rip_license_key_success', 'License key generated and saved successfully.', 'updated');
            } else {
                add_settings_error('rip_license_key', 'rip_license_key_error', 'Failed to save license key to the server.');
            }
        }
    }
}

function rip_generate_license_key() {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    $license_key = '';
    for ($i = 0; $i < 32; $i++) {
        $license_key .= $chars[wp_rand(0, strlen($chars) - 1)];
    }
    return $license_key;
}

// Function to validate license key
function rip_validate_license_key($license_key) {
    $api_url = 'https://test.andmuchmore.dev/validate.php'; // Replace with your license server URL

    $response = wp_remote_post($api_url, array(
        'body' => array(
            'license_key' => $license_key,
            'domain'      => home_url(),
        ),
    ));

    if (is_wp_error($response)) {
        return false;
    }

    $response_body = wp_remote_retrieve_body($response);
    $response_data = json_decode($response_body, true);

    return isset($response_data['valid']) && $response_data['valid'] === true;
}

// Hook into plugin activation to validate license key
function rip_plugin_activate() {
    generate_license_key_on_activate();
    $license_key = get_option('rip_license_key');

    if (!$license_key || !rip_validate_license_key($license_key)) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('Invalid license key. Please enter a valid license key to activate this plugin.');
    }
}

register_activation_hook(__FILE__, 'rip_plugin_activate');

// Add the meta box for renaming images
add_action('add_meta_boxes', 'rip_add_meta_box');

function rip_add_meta_box() {
    $post_types = get_post_types(array('public' => true), 'names');
    foreach ($post_types as $post_type) {
        add_meta_box(
            'rip_meta_box',
            'Rename and Geotag Images',
            'rip_meta_box_callback',
            $post_type,
            'side',
            'high'
        );
    }
}

function rip_meta_box_callback($post) {
    echo '<button id="rip-rename-images" class="button button-primary">Rename and Geotag Images</button>';
    echo '<script type="text/javascript">
        jQuery(document).ready(function($) {
            $("#rip-rename-images").click(function(e) {
                e.preventDefault();
                if(confirm("Are you sure you want to rename and geotag all images?")) {
                    $.ajax({
                        type: "POST",
                        url: ajaxurl,
                        data: {
                            action: "rip_rename_images",
                            post_id: ' . $post->ID . '
                        },
                        success: function(response) {
                            alert(response);
                        }
                    });
                }
            });
        });
    </script>';
}

add_action('wp_ajax_rip_rename_images', 'rip_rename_images_callback');

function rip_rename_images_callback() {
    $license_key = get_option('rip_license_key');

    if (!$license_key || !rip_validate_license_key($license_key)) {
        echo 'Invalid license key. Please enter a valid license key in the settings.';
        wp_die();
    }

    $post_id = intval($_POST['post_id']);
    $post = get_post($post_id);

    if (!$post) {
        echo 'Post not found!';
        wp_die();
    }

    $post_title = sanitize_title($post->post_title);
    $attachments = get_attached_media('image', $post_id);
    $counter = 1;

    $latitude = get_option('rip_latitude');
    $longitude = get_option('rip_longitude');

    foreach ($attachments as $attachment) {
        $file = get_attached_file($attachment->ID);
        $path_info = pathinfo($file);
        $new_filename = $post_title . '-' . $counter . '.' . $path_info['extension'];

        $new_file = $path_info['dirname'] . '/' . $new_filename;
        $result = rename($file, $new_file);

        if ($result) {
            // Update attachment metadata
            $attachment_data = array(
                'ID' => $attachment->ID,
                'guid' => str_replace(basename($file), basename($new_file), $attachment->guid)
            );
            wp_update_post($attachment_data);

            // Update file path in the metadata
            update_attached_file($attachment->ID, $new_file);

            // Geotag the image
            if ($latitude && $longitude) {
                rip_add_geotag_to_image($new_file, $latitude, $longitude);
            }
        }

        $counter++;
    }

    echo 'Images renamed and geotagged successfully!';
    wp_die();
}

// Function to add geotag to the image
function rip_add_geotag_to_image($image_path, $latitude, $longitude) {
    if (!file_exists($image_path) || !is_writable($image_path)) {
        return false;
    }

    $exif_ifd0 = exif_read_data($image_path, 'IFD0', 0, true);
    if (!$exif_ifd0 || !isset($exif_ifd0['IFD0'])) {
        return false;
    }

    $exif_data = array(
        'GPSLatitude' => rip_convert_decimal_to_dms(abs($latitude)),
        'GPSLatitudeRef' => ($latitude >= 0) ? 'N' : 'S',
        'GPSLongitude' => rip_convert_decimal_to_dms(abs($longitude)),
        'GPSLongitudeRef' => ($longitude >= 0) ? 'E' : 'W',
    );

    $jpeg_image = imagecreatefromjpeg($image_path);
    $new_image = $image_path;

    $result = imagejpeg($jpeg_image, $new_image, 100);

    imagedestroy($jpeg_image);

    if ($result && function_exists('iptcembed')) {
        $iptc_data = iptcparse(iptcembed(NULL, $image_path));
        if ($iptc_data) {
            $iptc_data = array_merge($iptc_data, $exif_data);
            $iptc_data_string = iptcembed(implode($iptc_data), $new_image);

            $fp = fopen($new_image, 'wb');
            fwrite($fp, $iptc_data_string);
            fclose($fp);
        }
    }

    return $result;
}

// Helper function to convert decimal to DMS
function rip_convert_decimal_to_dms($decimal) {
    $degrees = floor($decimal);
    $minutes_full = ($decimal - $degrees) * 60;
    $minutes = floor($minutes_full);
    $seconds = ($minutes_full - $minutes) * 60;

    return array(
        $degrees,
        $minutes,
        $seconds
    );
}

// Add EXIF data display in the media modal
add_filter('attachment_fields_to_edit', 'rip_add_exif_to_media_modal', 10, 2);

function rip_add_exif_to_media_modal($form_fields, $post) {
    if (wp_attachment_is_image($post->ID)) {
        $file = get_attached_file($post->ID);
        $exif = exif_read_data($file);

        if ($exif) {
            $exif_data = '<ul>';
            if (isset($exif['Model'])) $exif_data .= '<li><strong>Camera Model:</strong> ' . $exif['Model'] . '</li>';
            if (isset($exif['DateTime'])) $exif_data .= '<li><strong>Date Taken:</strong> ' . $exif['DateTime'] . '</li>';
            if (isset($exif['ExposureTime'])) $exif_data .= '<li><strong>Exposure Time:</strong> ' . $exif['ExposureTime'] . '</li>';
            if (isset($exif['FNumber'])) $exif_data .= '<li><strong>Aperture:</strong> f/' . $exif['FNumber'] . '</li>';
            if (isset($exif['ISOSpeedRatings'])) $exif_data .= '<li><strong>ISO:</strong> ' . $exif['ISOSpeedRatings'] . '</li>';
            if (isset($exif['GPSLatitude'])) $exif_data .= '<li><strong>Latitude:</strong> ' . implode(', ', rip_convert_dms_to_decimal($exif['GPSLatitude'], $exif['GPSLatitudeRef'])) . '</li>';
            if (isset($exif['GPSLongitude'])) $exif_data .= '<li><strong>Longitude:</strong> ' . implode(', ', rip_convert_dms_to_decimal($exif['GPSLongitude'], $exif['GPSLongitudeRef'])) . '</li>';
            $exif_data .= '</ul>';
        } else {
            $exif_data = 'No EXIF data found.';
        }

        $form_fields['exif_data'] = array(
            'label' => 'EXIF Data',
            'input' => 'html',
            'html' => $exif_data,
        );
    }

    return $form_fields;
}

// Helper function to convert DMS to decimal
function rip_convert_dms_to_decimal($dms, $ref) {
    $degrees = $dms[0];
    $minutes = $dms[1];
    $seconds = $dms[2];

    $decimal = $degrees + ($minutes / 60) + ($seconds / 3600);

    if ($ref == 'S' || $ref == 'W') {
        $decimal *= -1;
    }

    return $decimal;
}
