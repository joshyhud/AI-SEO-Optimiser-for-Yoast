<?php

// Enqueue the styles and script files
function ai_seo_enqueue_styles()
{
  wp_enqueue_style('ai-seo-styles', plugin_dir_url(__FILE__) . 'styles.min.css');
  wp_enqueue_script('ai-seo-scripts', plugin_dir_url(__FILE__) . 'scripts.js', array('jquery'), null, true);
}
add_action('admin_enqueue_scripts', 'ai_seo_enqueue_styles');


/**
 * Function to handle the POST request and generate SEO descriptions and keywords for posts and pages.
 *
 * This function handles the POST request and generates SEO descriptions and keywords for posts and pages.
 * It verifies the nonce for CSRF protection, sanitizes inputs, validates content type, and fetches AI-generated descriptions.
 * It then displays or updates the descriptions and keywords for each post or page.
 *
 * @return void
 */

use GuzzleHttp\Client;

function handle_post_request()
{
  if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ai_seo_generate'])) {
    // Verify nonce for CSRF protection
    if (!isset($_POST['ai_seo_generate_nonce']) || !wp_verify_nonce($_POST['ai_seo_generate_nonce'], 'ai_seo_generate_action')) {
      die('Security check failed');
    }

    // Sanitize inputs
    $is_dry_run = isset($_POST['ai_seo_dry_run']);
    $content_types = isset($_POST['content_type']) ? sanitize_text_field($_POST['content_type']) : '';
    $specific_page_id = isset($_POST['specific_page']) ? intval($_POST['specific_page']) : '';

    // Validate content type
    if (!in_array($content_types, ['post', 'page', 'specific_page'])) {
      die('Invalid content type');
    }

    $descriptions = [];

    if ($content_types == 'post') {
      $posts = get_posts(['post_type' => 'post', 'posts_per_page' => -1]);
      foreach ($posts as $post) {
        $current_meta_desc = get_post_meta($post->ID, '_yoast_wpseo_metadesc', true);
        $current_keywords = get_post_meta($post->ID, '_yoast_wpseo_focuskw', true);
        if (!empty($current_meta_desc) && !empty($current_keywords)) {
          echo "<p>Meta description and Keywords already exist for post/page ID: " . esc_html($post->ID) . ". Skipping...</p>";
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
          echo "<p>Meta description or Keywords already exist for post/page ID: " . esc_html($page->ID) . ". Skipping...</p>";
          continue;
        }
        $descriptions[$page->ID] = fetch_ai_description($page->post_content);
      }
    }

    // Display or update the descriptions
    echo "<h2>Generated Descriptions</h2>";
    foreach ($descriptions as $id => $desc) {
      $desc_text = is_string($desc) ? sanitize_text_field($desc) : '';

      // Move the keywords extraction inside the loop
      $keywords = extract_keywords_from_text($desc_text);
      $keywords = is_array($keywords) ? array_map('sanitize_text_field', $keywords) : [''];
      if (!$is_dry_run) {
        if (!empty($desc_text)) {
          update_post_meta($id, '_yoast_wpseo_metadesc', $desc_text);
          echo "<p>Meta description updated for post/page ID: " . esc_html($id) . " - Description: " . esc_html($desc_text) . "</p>";
        }

        if (!empty($keywords[0])) {
          update_post_meta($id, '_yoast_wpseo_focuskw', $keywords[0]);
          echo "<p>Keywords updated for post/page ID: " . esc_html($id) . " - Keywords: " . esc_html($keywords[0]) . "</p>";
        }
      } else {
        echo "<p><strong>Post/Page ID: " . esc_html($id) . " - </strong> Description: " . esc_html($desc_text) . ", Keywords: " . esc_html($keywords[0]) . " </p>";
      }
    }
  }
}

/**
 * Extracts text content from Gutenberg blocks.
 *
 * This function takes a Gutenberg content string and extracts the text content from each block.
 * It supports blocks that store content in 'attrs' > 'content' and blocks that use 'innerHTML'.
 *
 * @param string $content The Gutenberg content string.
 * @return string The extracted text content.
 */
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

/**
 * Fetch AI-generated description for a given post content.
 *
 * This function takes the post content and uses the OpenAI API to generate a concise, enticing SEO meta description.
 *
 * @param string $post_content The content of the post.
 * @return string The AI-generated meta description.
 */
function fetch_ai_description($post_content)
{
  // If no content, skip
  if (empty($post_content)) {
    return 'Content is empty.';
  }

  $text_content = extract_text_from_gutenberg_content($post_content);
  $api_key = get_option('openai_api_key');

  // Check if API key is empty
  if (empty($api_key)) {
    return 'Please update your API key in the plugin settings page.';
  }

  $client = new Client(['verify' => false]);
  try {
    $response = $client->request('POST', 'https://api.openai.com/v1/chat/completions', [
      'headers' => [
        'Authorization' => 'Bearer ' . $api_key,
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
      return 'AI response is empty.';
    }
  } catch (\GuzzleHttp\Exception\GuzzleException $e) {
    // Handle the exception or log it
    return 'Error: ' . $e->getMessage();
  }
}

/**
 * Extract keywords from text.
 *
 * This function takes a text string and extracts the most frequently occurring keywords.
 *
 * @param string $text The text to extract keywords from.
 * @param int $num_keywords The number of keywords to extract (default: 5).
 * @return array The array of extracted keywords.
 */
function extract_keywords_from_text($text, $num_keywords = 5)
{
  // If no text, skip
  if (empty($text)) {
    return [];
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

  $words = str_word_count(strtolower($text), 1);
  $words = array_diff($words, $stopwords);  // Exclude common words
  $keywords = array_count_values($words);
  arsort($keywords);  // Sort by frequency
  return array_slice(array_keys($keywords), 0, $num_keywords);
}
