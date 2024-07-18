<?php
/*
Plugin Name: AI SEO Description Generator
Plugin URI: http://yourwebsite.com/
Description: Generates SEO descriptions and keywords for pages and posts using OpenAI and updates Yoast SEO fields.
Version: 1.0
Author: Your Name
Author URI: https://josh-hudson.co.uk
*/

require_once __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\Client;


// Hook into WordPress
add_action('save_post', 'ai_generate_seo_content');

function ai_generate_seo_content($post_id)
{
  // Functionality to be added here
}


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
}

function ai_seo_generator_page()
{
?>
  <div class="wrap">
    <h1>AI SEO Description Generator</h1>
    <script type="text/javascript">
      window.onload = function() {
        document.getElementById('specific_page_radio').addEventListener('change', function() {
          document.getElementById('specific_page').disabled = !this.checked;
        });
      }
    </script>

    <form method="post">
      <input type="radio" name="content_type" value="post" <?php if (isset($_POST['content_type']) && $_POST['content_type'] == 'post') echo "checked"; ?>> Posts<br>
      <input type="radio" name="content_type" value="page" <?php if (isset($_POST['content_type']) && $_POST['content_type'] == 'page') echo "checked"; ?>> Pages<br>
      <input type="radio" id="specific_page_radio" name="content_type" value="specific_page" <?php if (isset($_POST['content_type']) && $_POST['content_type'] == 'specific_page') echo "checked"; ?>> Specific Page<br>
      <select id="specific_page" name="specific_page" <?php if (!isset($_POST['content_type']) || $_POST['content_type'] != 'specific_page') echo "disabled"; ?>>
        <option value="">Select a Page</option>
        <?php
        $pages = get_pages();
        foreach ($pages as $page) {
          echo '<option value="' . esc_attr($page->ID) . '"' . selected($_POST['specific_page'], $page->ID, false) . '>' . esc_html($page->post_title) . '</option>';
        }
        ?>
      </select><br>
      <input type="submit" name="ai_seo_generate" class="button button-primary" value="Generate Descriptions">
      <input type="checkbox" name="ai_seo_dry_run" checked> Dry Run<br>
    </form>
  </div>

  <script type="text/javascript">
    document.getElementById('specific_page_checkbox').addEventListener('change', function() {
      document.getElementById('specific_page').disabled = !this.checked;
    });
  </script>
<?php
  handle_post_request(); // Function to process the form submission
}


function extract_text_from_gutenberg_content($content)
{
  $parsed_blocks = parse_blocks($content);
  $text_content = '';

  foreach ($parsed_blocks as $block) {
    if (!empty($block['blockName']) && isset($block['attrs']['content'])) {
      // Some blocks store content in 'attrs' > 'content'
      $text_content .= wp_strip_all_tags($block['attrs']['content']) . "\n";
    } elseif (isset($block['innerHTML'])) {
      // Otherwise, use the 'innerHTML'
      $text_content .= wp_strip_all_tags($block['innerHTML']) . "\n";
    }
  }

  return $text_content;
}


function handle_post_request()
{
  if (isset($_POST['ai_seo_generate'])) {
    $is_dry_run = isset($_POST['ai_seo_dry_run']);
    $content_types = $_POST['content_type'] ?? [];
    $specific_page_id = $_POST['specific_page'] ?? '';

    $descriptions = [];

    if ($content_types == 'post') {
      $posts = get_posts(['post_type' => 'post', 'posts_per_page' => -1]);
      foreach ($posts as $post) {
        $current_meta_desc = get_post_meta($post->ID, '_yoast_wpseo_metadesc', true);
        $current_keywords = get_post_meta($post->ID, '_yoast_wpseo_focuskw', true);
        if (!empty($current_meta_desc) && !empty($current_keywords)) {
          echo "<p>Meta description and Keywords already exist for post/page ID: {$post->ID}. Skipping...</p>";
          continue;
        }
        $descriptions[$post->ID] = fetch_ai_description($post->post_content);
      }
    }

    if ($content_types == 'page' || !empty($specific_page_id)) {
      $args = ['post_type' => 'page', 'posts_per_page' => -1];
      if (!empty($specific_page_id)) {
        $args['include'] = [$specific_page_id];
      }
      $pages = get_posts($args);
      foreach ($pages as $page) {
        $current_meta_desc = get_post_meta($page->ID, '_yoast_wpseo_metadesc', true);
        $current_keywords = get_post_meta($page->ID, '_yoast_wpseo_focuskw', true);
        if (!empty($current_meta_desc) && !empty($current_keywords)) {
          echo "<p>Meta description or Keywords already exists for post/page ID: {$page->ID}</p>";
          continue;
        }
        $descriptions[$page->ID] = fetch_ai_description($page->post_content);
      }
    }

    // Display or update the descriptions
    echo "<h2>Generated Descriptions</h2>";
    foreach ($descriptions as $id => $desc) {
      $desc_text = is_string($desc) ? $desc : '';

      // Move the keywords extraction inside the loop
      $keywords = extract_keywords_from_text($desc_text);
      $keywords = is_array($keywords) ? $keywords : [''];
      if (!$is_dry_run) {

        if (!empty($desc_text)) {
          update_post_meta($id, '_yoast_wpseo_metadesc', sanitize_text_field($desc_text));
          echo "<p>Meta description updated for post/page ID: $id - Description: $desc_text</p>";
        }

        if (!empty($keywords[0])) {
          update_post_meta($id, '_yoast_wpseo_focuskw', $keywords[0]);
          echo "<p>Keywords updated for post/page ID: $id -  Keywords: $keywords[0]</p>";
        }
      } else {
        echo "<p><strong>Post/Page ID: $id - </strong> Description: $desc_text, Keywords: $keywords[0] </p>";
      }
    }
  }
}
// Description Function
function fetch_ai_description($post_content)
{
  // If no content, skip
  if (empty($post_content)) {
    return;
  }

  $text_content = extract_text_from_gutenberg_content($post_content);
  $client = new Client(['verify' => false]);
  try {
    $response = $client->request('POST', 'https://api.openai.com/v1/chat/completions', [
      'headers' => [
        'Authorization' => 'Bearer ' . OPENAI_API_KEY,
        'Content-Type' => 'application/json',
      ],
      'json' => [
        'model' => 'gpt-3.5-turbo-0125',  // Use an appropriate model
        'messages' => [
          ['role' => 'user', 'content' =>  "Write a concise, enticing SEO meta description that summarizes the following content while emphasizing key points and relevant keywords with no more than 155 characters: " . $text_content]
        ]
      ]
    ]);
    $body = $response->getBody();
    $response_array = json_decode($body->getContents(), true);
    if (isset($response_array['choices'][0]['message']['content'])) {
      return $response_array['choices'][0]['message']['content'];
    } else {
      return; // Fallback text
    }
  } catch (\GuzzleHttp\Exception\GuzzleException $e) {
    // Handle the exception or log it
    return 'Error: ' . $e->getMessage();
  }
}

function extract_keywords_from_text($text, $num_keywords = 5)
{
  // If no text, skip
  if (empty($text)) {
    return;
  }
  // List of common stop words
  $stopwords = [
    "a", "about", "above", "after", "again", "against", "all", "also", "am", "an", "and", "any", "are", "aren't", "as", "at",
    "be", "because", "been", "before", "being", "below", "between", "both", "but", "by", "can", "can't", "cannot", "could", "couldn't",
    "did", "didn't", "do", "does", "doesn't", "doing", "don't", "down", "during", "each", "few", "for", "from", "further",
    "had", "hadn't", "has", "hasn't", "have", "haven't", "having", "he", "he'd", "he'll", "he's", "her", "here", "here's",
    "hers", "herself", "him", "himself", "his", "how", "how's", "i", "i'd", "i'll", "i'm", "i've", "if", "in", "into",
    "is", "isn't", "it", "it's", "its", "itself", "let's", "me", "more", "most", "mustn't", "my", "myself", "no", "nor",
    "not", "of", "off", "on", "once", "only", "or", "other", "ought", "our", "ours", "ourselves", "out", "over", "own",
    "same", "shan't", "she", "she'd", "she'll", "she's", "should", "shouldn't", "so", "some", "such", "than", "that", "that's",
    "the", "their", "theirs", "them", "themselves", "then", "there", "there's", "these", "they", "they'd", "they'll", "they're",
    "they've", "this", "those", "through", "to", "too", "under", "until", "up", "very", "was", "wasn't", "we", "we'd", "we'll",
    "we're", "we've", "were", "weren't", "what", "what's", "when", "when's", "where", "where's", "which", "while", "who", "who's",
    "whom", "why", "why's", "with", "won't", "would", "wouldn't", "you", "you'd", "you'll", "you're", "you've", "your", "yours",
    "yourself", "yourselves"
  ];
  $text_content = extract_text_from_gutenberg_content($text);
  $words = str_word_count(strtolower($text_content), 1);
  $words = array_diff($words, $stopwords);  // Exclude common words
  $keywords = array_count_values($words);
  arsort($keywords);  // Sort by frequency
  return array_slice(array_keys($keywords), 0, $num_keywords);
}
