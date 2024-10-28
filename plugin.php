<?php
/*

Plugin Name: Certificate Verification
Author: Your Name

*/

// Hook to activate the plugin
register_activation_hook(__FILE__, 'cvp_create_table');

// Create the database table on plugin activation or update it
function cvp_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'certificates';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        cert_id varchar(50) NOT NULL,
        cert_name varchar(255) NOT NULL,
        cert_date date NOT NULL,
        cert_description text NOT NULL,
        id_type varchar(50) NOT NULL,
        duration varchar(100) NOT NULL,
        verified tinyint(1) NOT NULL DEFAULT 0,
        signature varchar(255),
        UNIQUE KEY id (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Add menu item in WordPress admin
add_action('admin_menu', 'cvp_add_admin_menu');

function cvp_add_admin_menu() {
    add_menu_page(
        'Certificate Verification', 
        'Certificate Verification', 
        'manage_options', 
        'certificate-verification', 
        'cvp_admin_page',
        'dashicons-awards', 
        6
    );
}

// Admin page content
function cvp_admin_page() {
    ?>
    <div class="wrap">
        <h1>Add Certificate Details</h1>
        <form method="post" enctype="multipart/form-data" action="">
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Certificate ID</th>
                    <td><input type="text" name="cert_id" required /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Certificate Name</th>
                    <td><input type="text" name="cert_name" required /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Date of Issue</th>
                    <td><input type="date" name="cert_date" required /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Description</th>
                    <td><textarea name="cert_description" required></textarea></td>
                </tr>
                <tr valign="top">
                    <th scope="row">ID Type</th>
                    <td>
                        <select name="id_type" required>
                            <option value="Certification">Certification</option>
                            <option value="Training Program">Training Program</option>
                            <option value="Workshop">Workshop</option>
                            <option value="Graduate Program">Graduate Program</option>
                            <option value="Summer Internship">Summer Internship</option>
                        </select>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Duration</th>
                    <td><input type="text" name="duration" required placeholder="e.g., 3 months" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Verified</th>
                    <td><input type="checkbox" name="verified" value="1" /> Verified</td>
                </tr>
                <tr valign="top">
                    <th scope="row">Digital Signature</th>
                    <td><input type="file" name="signature" accept="image/*" /></td>
                </tr>
            </table>
            <?php submit_button('Add Certificate'); ?>
        </form>
    </div>
    <?php

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        cvp_save_certificate();
    }
}

// Save certificate details to the database
function cvp_save_certificate() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'certificates';

    $cert_id = sanitize_text_field($_POST['cert_id']);
    $cert_name = sanitize_text_field($_POST['cert_name']);
    $cert_date = sanitize_text_field($_POST['cert_date']);
    $cert_description = sanitize_textarea_field($_POST['cert_description']);
    $id_type = sanitize_text_field($_POST['id_type']);
    $duration = sanitize_text_field($_POST['duration']);
    $verified = isset($_POST['verified']) ? 1 : 0;

    // Handle the signature upload
    $signature_url = '';
    if (!empty($_FILES['signature']['name'])) {
        $uploaded_file = wp_upload_bits($_FILES['signature']['name'], null, file_get_contents($_FILES['signature']['tmp_name']));
        if (!$uploaded_file['error']) {
            $signature_url = $uploaded_file['url'];
        }
    }

    $wpdb->insert($table_name, array(
        'cert_id' => $cert_id,
        'cert_name' => $cert_name,
        'cert_date' => $cert_date,
        'cert_description' => $cert_description,
        'id_type' => $id_type,
        'duration' => $duration,
        'verified' => $verified,
        'signature' => $signature_url
    ));

    echo "<div class='notice notice-success'><p>Certificate added successfully!</p></div>";
}

// Frontend form for users to verify certificates
add_shortcode('certificate_verification_form', 'cvp_frontend_form');

function cvp_frontend_form() {
    ob_start();
    ?>
    <h3>Verify Certificate</h3>
    <form method="post">
        <input type="text" name="cert_id" placeholder="Enter Certificate ID" required />
        <input type="submit" name="verify_certificate" value="Verify" />
    </form>

    <?php
    if (isset($_POST['verify_certificate'])) {
        $cert_id = sanitize_text_field($_POST['cert_id']);
        cvp_verify_certificate($cert_id);
    }
    return ob_get_clean();
}

// Verify the certificate based on the entered ID
function cvp_verify_certificate($cert_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'certificates';

    $result = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE cert_id = %s", $cert_id));

    if ($result) {
        echo "<h3>Certificate Found</h3>";
        echo "<p><strong>Certificate ID:</strong> " . esc_html($result->cert_id) . "</p>";
        echo "<p><strong>Certificate Name:</strong> " . esc_html($result->cert_name) . "</p>";
        echo "<p><strong>Date of Issue:</strong> " . esc_html($result->cert_date) . "</p>";
        echo "<p><strong>Description:</strong> " . esc_html($result->cert_description) . "</p>";
        echo "<p><strong>ID Type:</strong> " . esc_html($result->id_type) . "</p>";
        echo "<p><strong>Duration:</strong> " . esc_html($result->duration) . "</p>";
        echo "<p><strong>Verified:</strong> " . ($result->verified ? '<span style="color:green;">Yes</span>' : '<span style="color:red;">No</span>') . "</p>";

        if (!empty($result->signature)) {
            echo "<p><strong>Digitally Signed by:</strong> <img src='" . esc_url($result->signature) . "' style='max-width:150px;' alt='Signature'></p>";
        }
    } else {
        echo "<p>No certificate found for ID: " . esc_html($cert_id) . "</p>";
    }
}
?>
