<?php
/**
Plugin Name: HNP Clear WooCommerce Cart & Session
Description: Ein Plugin zum Leeren des WooCommerce Warenkorbs und Löschen von Sessions und Cookies.
Version: 1.2
Author: Christopher Rohde 
Author URI: https://homepage-nach-preis.de/
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

// Exit, wenn der Direktzugriff verhindert ist
if (!defined('ABSPATH')) {
    exit;
}

function hnp_cwc_enqueue_scripts() {
    if (is_cart() && get_option('hnp_cwc_enable', 'no') == 'yes' && get_option('hnp_cwc_hook_enable', 'no') == 'yes') {
        $nonce = wp_create_nonce('hnp_cwc_nonce');
        
        $script = "
            var ajaxurl = '" . admin_url('admin-ajax.php') . "';
            var nonce = '" . $nonce . "';
            
            document.addEventListener('DOMContentLoaded', function() {
                var clearCartButton = document.getElementById('clear-cart');
                
                clearCartButton.addEventListener('click', function() {
                    // Clear cart
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', ajaxurl, true);
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    xhr.setRequestHeader('X-WP-Nonce', nonce);
                    xhr.onload = function() {
                        if (this.status >= 200 && this.status < 400) {
                            console.log('Cart Cleared', this.responseText);
                            
                            // Clear sessions and cookies
                            var xhr2 = new XMLHttpRequest();
                            xhr2.open('POST', ajaxurl, true);
                            xhr2.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                            xhr2.setRequestHeader('X-WP-Nonce', nonce);
                            xhr2.onload = function() {
                                if (this.status >= 200 && this.status < 400) {
                                    console.log('Sessions and Cookies Cleared', this.responseText);
                                    
                                    // Reload the page after clearing cart and sessions/cookies
                                    location.reload();
                                } else {
                                    console.error('Error clearing sessions and cookies', this);
                                }
                            };
                            xhr2.onerror = function() {
                                console.error('Error clearing sessions and cookies', this);
                            };
                            xhr2.send('action=hnp_clear_sessions_cookies&nonce=' + nonce);
                            
                        } else {
                            console.error('Error clearing cart', this);
                        }
                    };
                    xhr.onerror = function() {
                        console.error('Error clearing cart', this);
                    };
                    xhr.send('action=hnp_clear_cart_session&nonce=' + nonce);
                });
            });
        ";
        wp_add_inline_script('jquery', $script);
    }
}


add_action('wp_enqueue_scripts', 'hnp_cwc_enqueue_scripts');



// Register shortcode
function hnp_cwc_button_shortcode() {
    ob_start(); ?>
    <button id="clear-cart">Warenkorb leeren</button>
    <?php
    return ob_get_clean();
}
add_shortcode('hnp_cwc_button', 'hnp_cwc_button_shortcode');

// Hook to display the button on cart page
function hnp_cwc_display_button() {
    if (get_option('hnp_cwc_hook_enable', 'no') == 'yes') {
        echo do_shortcode('[hnp_cwc_button]');
    }
}
add_action('woocommerce_cart_actions', 'hnp_cwc_display_button');

// AJAX handler to clear cart
function hnp_cwc_clear_cart() {
    check_ajax_referer('hnp_cwc_nonce', 'nonce');

    try {
        WC()->cart->empty_cart();
        echo 'Cart Cleared';
    } catch (Exception $e) {
        echo 'Error clearing cart: ' . $e->getMessage();
    }
    die();
}

add_action('wp_ajax_hnp_clear_cart_session', 'hnp_cwc_clear_cart');
add_action('wp_ajax_nopriv_hnp_clear_cart_session', 'hnp_cwc_clear_cart');

// AJAX handler to clear sessions and cookies
function hnp_cwc_clear_sessions_cookies() {
    check_ajax_referer('hnp_cwc_nonce', 'nonce');

    try {
        // Reset WooCommerce session
        WC()->session->set_customer_session_cookie(false);
        WC()->session->set_session_cookie(false);
        WC()->session->reset_session();

        // Unset all cookies
        foreach ($_COOKIE as $cookie_key => $cookie_value) {
            unset($_COOKIE[$cookie_key]);
            setcookie($cookie_key, '', time() - 3600, '/', $_SERVER['HTTP_HOST']);
            setcookie($cookie_key, '', time() - 3600, '/', '.' . $_SERVER['HTTP_HOST']);
        }

        echo 'Sessions and Cookies Cleared';
    } catch (Exception $e) {
        echo 'Error clearing sessions and cookies: ' . $e->getMessage();
    }
    die();
}


add_action('wp_ajax_hnp_clear_sessions_cookies', 'hnp_cwc_clear_sessions_cookies');
add_action('wp_ajax_nopriv_hnp_clear_sessions_cookies', 'hnp_cwc_clear_sessions_cookies');


// Admin settings
function hnp_cwc_admin_menu() {
    add_menu_page(
        'HNP Clear WooCommerce Cart Settings', 
        'HNP Clear WooCommerce Cart',           
        'manage_options',                   
        'hnp_cwc_settings',                 
        'hnp_cwc_settings_page',            
		 plugin_dir_url(__FILE__) . 'img/hnp-favi.png'                               
    );
}
add_action('admin_menu', 'hnp_cwc_admin_menu');

function hnp_cwc_settings_page() {
	if (!current_user_can('manage_options')) {
        return;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('hnp_cwc_nonce', 'hnp_cwc_nonce_field')) {
        if (isset($_POST['hnp_cwc_enable'])) {
            update_option('hnp_cwc_enable', $_POST['hnp_cwc_enable']);
        } else {
            update_option('hnp_cwc_enable', 'no');
        }

        if (isset($_POST['hnp_cwc_hook_enable'])) {
            update_option('hnp_cwc_hook_enable', $_POST['hnp_cwc_hook_enable']);
        } else {
            update_option('hnp_cwc_hook_enable', 'no');
        }
    }

    $enabled = get_option('hnp_cwc_enable', 'no');
    $hook_enabled = get_option('hnp_cwc_hook_enable', 'no');
    $nonce = wp_create_nonce('hnp_cwc_nonce');
    ?>
    <div class="wrap">
        <h2>HNP Clear WooCommerce Cart Settings</h2>
        <form method="post" action="">
            <?php wp_nonce_field('hnp_cwc_nonce', 'hnp_cwc_nonce_field'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">Allgemeine Funktion aktivieren</th>
                    <td>
                        <input type="checkbox" name="hnp_cwc_enable" value="yes" <?php checked('yes', $enabled); ?> />
                    </td>
                </tr>
                <tr>
                    <th scope="row">Hook für Warenkorb aktivieren</th>
                    <td>
                        <input type="checkbox" name="hnp_cwc_hook_enable" value="yes" <?php checked('yes', $hook_enabled); ?> />
                    </td>
                </tr>
				<tr>Shortcode um den Button manuell einzubinden: [hnp_cwc_button]</tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}
