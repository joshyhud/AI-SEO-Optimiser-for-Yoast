<?php
function enqueue_styles()
{
  wp_enqueue_style('plugin-styles', plugin_dir_url(__FILE__) . 'styles.css');
}
add_action('wp_enqueue_scripts', 'enqueue_styles');
