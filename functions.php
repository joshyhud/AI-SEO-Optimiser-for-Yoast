<?php

// Enqueue the styles and script files
function ai_seo_enqueue_styles()
{
  wp_enqueue_style('ai-seo-styles', plugin_dir_url(__FILE__) . 'assets/css/styles.min.css');
  wp_enqueue_script('ai-seo-scripts', plugin_dir_url(__FILE__) . 'assets/js/scripts.js', array('jquery'), null, true);
  // Localize script with nonce and ajax URL
  wp_localize_script('ai-seo-scripts', 'ai_seo_ajax_object', array(
    'ajax_url' => admin_url('admin-ajax.php'),
    'nonce'    => wp_create_nonce('ai_seo_nonce')
  ));
}
add_action('admin_enqueue_scripts', 'ai_seo_enqueue_styles');

use GuzzleHttp\Client;

/**
 * Function to handle the POST request and generate SEO descriptions and keywords for posts and pages.
 */
function handle_post_request()
{
  if (isset($_POST['action'])) {
    // Verify nonce for CSRF protection
    if (!isset($_POST['ai_seo_generate_nonce']) || !wp_verify_nonce($_POST['ai_seo_generate_nonce'], 'ai_seo_generate_action')) {
      echo 'Security check failed';
      wp_die();
    }

    // Sanitize inputs
    $is_dry_run = isset($_POST['ai_seo_dry_run']);
    $content_types = isset($_POST['content_type']) ? sanitize_text_field($_POST['content_type']) : '';
    $specific_page_id = isset($_POST['specific_page']) ? intval($_POST['specific_page']) : '';

    // Validate content type
    if (!in_array($content_types, ['post', 'page', 'specific_page'])) {
      echo 'Select a valid content type';
      wp_die();
    }

    $descriptions = [];

    if ($content_types == 'post') {
      $posts = get_posts(['post_type' => 'post', 'posts_per_page' => -1]);
      foreach ($posts as $post) {
        $current_meta_desc = get_post_meta($post->ID, '_yoast_wpseo_metadesc', true);
        $current_keywords = get_post_meta($post->ID, '_yoast_wpseo_focuskw', true);
        if (!empty($current_meta_desc) && !empty($current_keywords)) {
          echo "<p class='response'>Meta description and Keywords already exist for post: " . esc_html(get_the_title($post->ID)) . ". Skipping...</p>";
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
          echo "<p class='response'>Meta description or Keywords already exist for page: " . esc_html(get_the_title($page->ID)) . ". Skipping...</p>";
          continue;
        }
        $descriptions[$page->ID] = fetch_ai_description($page->post_content);
      }
    }

    // Display or update the descriptions
    if (!empty($descriptions)) {
      echo "<h2>Generated Descriptions</h2>";
    }
    foreach ($descriptions as $id => $desc) {
      $desc_text = is_string($desc) ? sanitize_text_field($desc) : '';
      $keywords = extract_keywords_from_text($desc_text);
      $keywords = is_array($keywords) ? array_map('sanitize_text_field', $keywords) : [''];
      if (!$is_dry_run) {
        if (!empty($desc_text)) {
          update_post_meta($id, '_yoast_wpseo_metadesc', $desc_text);
          echo "<p class='response'>Meta description updated for post/page: " . esc_html(get_the_title($id)) . " - Description: " . esc_html($desc_text) . "</p>";
        }

        if (!empty($keywords[0])) {
          update_post_meta($id, '_yoast_wpseo_focuskw', $keywords[0]);
          echo "<p class='response'>Keywords updated for post/page: " . esc_html(get_the_title($id)) . " - Keywords: " . esc_html($keywords[0]) . "</p>";
        }
      } else {
        echo "<p class='response'><strong>Post/Page: " . esc_html(get_the_title($id)) . " - </strong> Description: " . esc_html($desc_text) . ", Keywords: " . esc_html($keywords[0]) . " </p>";
      }
    }
    wp_die(); // This is required to terminate immediately and return a proper response
  }
}
add_action('wp_ajax_ai_seo_generate_action', 'handle_post_request');
add_action('wp_ajax_nopriv_ai_seo_generate_action', 'handle_post_request');

/**
 * Extracts text content from Gutenberg blocks.
 */
function extract_text_from_gutenberg_content($content)
{
  $parsed_blocks = parse_blocks($content);
  $text_content = '';

  foreach ($parsed_blocks as $block) {
    if (!empty($block['blockName']) && isset($block['attrs']['content'])) {
      $text_content .= wp_strip_all_tags($block['attrs']['content']) . "\n";
    } elseif (isset($block['innerHTML'])) {
      $text_content .= wp_strip_all_tags($block['innerHTML']) . "\n";
    }
  }

  return $text_content;
}

/**
 * Fetch AI-generated description for a given post content.
 */
function fetch_ai_description($post_content)
{
  if (empty($post_content)) {
    return 'Content is empty.';
  }

  $text_content = extract_text_from_gutenberg_content($post_content);
  $api_key = get_option('openai_api_key');

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
        'model' => 'gpt-4o',  // Use an appropriate model
        'messages' => [
          ['role' => 'user', 'content' => "Write a concise, enticing SEO meta description that summarizes the following content while emphasizing key points and relevant keywords with no more than 155 characters: " . $text_content]
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
 */
function extract_keywords_from_text($text, $num_keywords = 5)
{
  if (empty($text)) {
    return [];
  }

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
