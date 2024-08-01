<?php
/*
Plugin Name: AI SEO Optimiser for Yoast
Plugin URI: http://yourwebsite.com/
Description: Boost your website's search engine performance with AI SEO Optimiser for Yoast. This powerful WordPress plugin leverages the advanced capabilities of OpenAI to automatically generate high-quality SEO descriptions and keywords for your pages and posts. Seamlessly integrated with Yoast SEO, AI SEO Optimiser ensures your content is always optimised for search engines, helping you rank higher and attract more organic traffic.
Version: 1.0
Author: Your Name
Author URI: https://josh-hudson.co.uk
*/

// Exit if accessed directly
if (!defined('ABSPATH')) {
  exit;
}

// Include necessary files
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/vendor/autoload.php';

// Register activation hook
register_activation_hook(__FILE__, 'ai_seo_plugin_activation');

// Function to run on plugin activation
function ai_seo_plugin_activation()
{
  if (!is_plugin_active('wordpress-seo/wp-seo.php')) {
    // Yoast SEO plugin is not active, display a message to install it first
    wp_die('Please install and activate the Yoast SEO plugin before activating the AI SEO Description Generator plugin.');
  }
}

// Hook into WordPress save_post action
add_action('save_post', 'ai_generate_seo_content');

// Function to generate SEO content on save_post action
function ai_generate_seo_content($post_id)
{
  // Functionality to be added here
}

// Hook into WordPress admin_menu action
add_action('admin_menu', 'ai_seo_description_menu');

// Function to create WordPress admin menu items
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
  <section id="aiSeo">
    <div class="wrapper">
      <h1>AI SEO Description Generator</h1>
      <?php if (empty(get_option('openai_api_key')) || !is_plugin_active('wordpress-seo/wp-seo.php')) { ?>
        <p>This plugin generates SEO descriptions and keywords for pages and posts using OpenAI and updates the Yoast SEO fields. It provides a user-friendly interface for generating SEO data and allows you to choose the content type (posts, pages, or specific pages) for which you want to generate descriptions. Follow the steps below to get started:</p>

        <?php if (!is_plugin_active('wordpress-seo/wp-seo.php')) { ?>
          <h3>Install Yoast SEO:</h3>
          <p>Ensure that the Yoast SEO plugin is installed and activated on your WordPress site. This plugin works in conjunction with Yoast SEO to update the necessary fields.</p>
        <?php } ?>

        <?php if (empty(get_option('openai_api_key'))) { ?>

          <h3>Obtain an OpenAI API Key:</h3>

          <p>To generate SEO descriptions and keywords, you need an API key from OpenAI. Visit the OpenAI website, sign up for an account, and obtain your API key.</p>

          <h3>Enter the API Key in Plugin Settings:</h3>

          <p>Go to the plugin settings in your WordPress admin area. Enter the OpenAI API key in the designated field and save the settings.</p>
        <?php } ?>
      <?php
      } else { ?>
        <p>This plugin generates SEO descriptions and keywords for pages and posts using OpenAI and updates the Yoast SEO fields. It provides a user-friendly interface for generating SEO data and allows you to choose the content type (posts, pages, or specific pages) for which you want to generate descriptions.</p>
        <i>Select a content type or specific page to get started:</i>

        <form method="post" class="ai-form" id="ai-seo-form">
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
                <input type="radio" id="specific_page_radio" name="content_type" value="specific_page" <?php if (isset($_POST['content_type']) && sanitize_text_field($_POST['content_type']) == 'specific_page') echo "checked"; ?>> Specific Page<br>
              </li>
              <select id="specific_page" name="specific_page" <?php if (!isset($_POST['content_type']) || sanitize_text_field($_POST['content_type']) != 'specific_page') echo "disabled"; ?>>
                <option value="">Select a Page</option>
                <?php
                $pages = get_pages();
                foreach ($pages as $page) {
                  echo '<option value="' . esc_attr($page->ID) . '"' . selected(sanitize_text_field($_POST['specific_page'] ?? ''), $page->ID, false) . '>' . esc_html($page->post_title) . '</option>';
                }
                ?>
              </select>
            </ul>
          </div>
          <div class="input"><input title="Select this to perform a dry run without making data changes" type="checkbox" class="dry-run" name="ai_seo_dry_run" checked><label for="ai_seo_dry_run">Dry Run - <i>Generate SEO data without amending posts/pages</i></label></div>
          <input type="hidden" name="action" value="ai_seo_generate_action">
          <?php wp_nonce_field('ai_seo_generate_action', 'ai_seo_generate_nonce'); ?>
          <button type="submit" name="ai_seo_generate" class="button button-primary">
            <span id="button-text">Generate Descriptions</span>
          </button>
          <img id="loading-gif" src="/wp-content/plugins/seo-ai/assets/images/loading.gif" style="display:none;">

        </form>
        <!-- Area to display the response -->
        <div id="response-area"></div>
      <?php } ?>
    </div>
    <footer>
      <p>Built by <a target="_blank" href="https://josh-hudson.co.uk">Josh Hudson Dev</a></p>
    </footer>
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
  <section id="aiSeo">
    <div class="wrapper">
      <h1>AI SEO Generator Settings</h1>
      <?php if (empty(get_option('openai_api_key'))) { ?>
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
      <?php } ?>
      <form method="post" class="ai-form">
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
    </div>
    <footer>
      <p>Built by <a target="_blank" href="https://josh-hudson.co.uk">Josh Hudson Dev</a></p>
    </footer>
  </section>
  </div>
<?php
}
