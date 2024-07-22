<?php
/*
Plugin Name: AI SEO Description Generator
Plugin URI: http://yourwebsite.com/
Description: Generates SEO descriptions and keywords for pages and posts using OpenAI and updates Yoast SEO fields.
Version: 1.0
Author: Your Name
Author URI: https://josh-hudson.co.uk
*/

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/vendor/autoload.php';

register_activation_hook(__FILE__, 'ai_seo_plugin_activation');

function ai_seo_plugin_activation()
{
  if (!is_plugin_active('wordpress-seo/wp-seo.php')) {
    // Yoast SEO plugin is not active, display a message to install it first
    wp_die('Please install and activate the Yoast SEO plugin before activating the AI SEO Description Generator plugin.');
  }
}

// Hook into WordPress
add_action('save_post', 'ai_generate_seo_content');

function ai_generate_seo_content($post_id)
{
  // Functionality to be added here
}

// Create WordPress admin menu items
add_action('admin_menu', 'ai_seo_description_menu');

function ai_seo_description_menu()
{
  add_menu_page(
    'AI SEO Generator',      // Page Title
    'AI SEO Generator',      // Menu Title
    'manage_options',        // Capability
    'ai-seo-generator',      // Menu Slug
    'ai_seo_generator_page', // Function
    'dashicons-admin-generic', // Icon URL
    20                       // Position
  );

  add_submenu_page(
    'ai-seo-generator', // Parent menu slug
    'AI SEO Generator Submenu', // Page title
    'Settings', // Menu title
    'manage_options', // Capability
    'ai-seo-generator-setting', // Menu slug
    'ai_seo_generator_settings_page' // Callback function
  );
}

// Main AI SEO generator page
function ai_seo_generator_page()
{
?>
  <section id="aiSeo" class="wrapper">
    <h1>AI SEO Description Generator</h1>

    <form method="post" class="ai-form">
      <?php wp_nonce_field('ai_seo_generate_action', 'ai_seo_generate_nonce'); ?>
      <div class="postType">
        <ul>
          <li>
            <input type="radio" name="content_type" value="post" <?php if (isset($_POST['content_type']) && sanitize_text_field($_POST['content_type']) == 'post') echo "checked"; ?>> Posts<br>
          </li>
          <li>
            <input type="radio" name="content_type" value="page" <?php if (isset($_POST['content_type']) && sanitize_text_field($_POST['content_type']) == 'page') echo "checked"; ?>> Pages<br>
          </li>
          <li>
            <input type="radio" id="specific_page_radio" name="content_type" value="specific_page" <?php if (isset($_POST['content_type']) && sanitize_text_field($_POST['content_type']) == 'specific_page') echo "checked"; ?>> Specific Page - <i>Select one page to amend</i><br>
          </li>
          <select id="specific_page" name="specific_page" <?php if (!isset($_POST['content_type']) || sanitize_text_field($_POST['content_type']) != 'specific_page') echo "disabled"; ?>>
            <option value="">Select a Page</option>
            <?php
            $pages = get_pages();
            foreach ($pages as $page) {
              echo '<option value="' . esc_attr($page->ID) . '"' . selected(sanitize_text_field($_POST['specific_page']), $page->ID, false) . '>' . esc_html($page->post_title) . '</option>';
            }
            ?>
          </select>
        </ul>
      </div>
      <div class="input"><input title="Select this to perform a dry run without making data changes" type="checkbox" class="dry-run" name="ai_seo_dry_run" checked><label for="ai_seo_dry_run">Dry Run - <i>Generate SEO data without amending posts/pages</i></label></div>
      <input type="submit" name="ai_seo_generate" class="button button-primary" value="Generate Descriptions">
      <?php
      //Function to handle form submission
      handle_post_request();
      ?>
    </form>
  </section>
<?php
}

// AI SEO generator settings page
function ai_seo_generator_settings_page()
{
  // Save API key
  if (isset($_POST['save_api_key'])) {
    check_admin_referer('ai_seo_generator_save_api_key');
    $api_key = sanitize_text_field($_POST['api_key']);
    update_option('openai_api_key', $api_key);
    echo '<div class="notice notice-success"><p>API key saved successfully.</p></div>';
  }

  // Get API key
  $api_key = get_option('openai_api_key', '');

  // Display settings form
?>
  <section id="aiSeo" class="wrapper">
    <h1>AI SEO Generator Settings</h1>
    <form method="post">
      <?php wp_nonce_field('ai_seo_generator_save_api_key'); ?>
      <table class="form-table">
        <tr>
          <th scope="row"><label for="api_key">ChatGPT API Key</label></th>
          <td>
            <input class="api_key" type="text" id="api_key" name="api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text">
            <p class="description">Enter your ChatGPT API key.</p>
          </td>
        </tr>
      </table>
      <?php submit_button('Save API Key', 'primary', 'save_api_key'); ?>
    </form>
    <h2>How to get an API key from OpenAI</h2>
    <ol>
      <li>Go to the OpenAI website: <a target="_blank" href="https://openai.com">https://openai.com</a></li>
      <li>Create an account or log in to your existing account</li>
      <li>Go to the API section of your account settings</li>
      <li>Generate an API key</li>
      <li>Copy the generated API key</li>
      <li>Paste the API key into the "ChatGPT API Key" field above</li>
      <li>Click "Save API Key"</li>
    </ol>
  </section>
<?php
}
