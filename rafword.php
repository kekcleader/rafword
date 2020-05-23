<?php
/*
Plugin Name: RAF Word
Plugin URI: http://safard.tut.su/rafword
Description: Allows to use [rafword]something[/rafword] to translate individual words in posts and pages, as well as rafword(). Also works with standard __('something', 'rafword'). Uses simple TXT language files for word translation.
Version: 0.1
Author: Artur Efimov
Author URI: http://safard.tut.su/artur-efimov
*/

if (!function_exists('rafword_get_lang_folder')) {
  function rafword_get_lang_folder() {
    return dirname(__FILE__).'/translations/';
  }
}

if (!function_exists('rafword_get_def_lang')) {
  function rafword_get_def_lang() {
    return 'en';
  }
}

if (!function_exists('rafword_get_lang')) {
  function rafword_get_lang() {
    $lang = @$_GET['lang'];
    $lang = trim(strtolower($lang));
    if (!$lang) {
      $lang = rafword_get_def_lang();
    }
    return $lang;
  }
}

add_filter('gettext', 'rafword_gettext', 20, 3);
function rafword_gettext($translated, $text=NULL, $domain=NULL) {
  if ($domain == 'rafword') {
    $lang = rafword_get_lang();
    $translations = rafword_get_translations($lang);
    #!FIXME Add account for big and small register, and caps lock
    if (isset($translations[$text])) {
      $translated = $translations[$text];
    }
  }
  return $translated;
}

# Shortcode usage: [rafword]something[/rafword]
# Alternative usage: [rafword word="something"]
add_shortcode('rafword', 'rafword_shortcode');
function rafword_shortcode($atts, $content='') {
  if (isset($atts['word'])) {
    $content = $atts['word'];
  }
  return __($content, 'rafword');
}

# API function usage: echo rafword('something')
function rafword($word) {
  return __($word, 'rafword');
}

function rafword_get_lang_fname($lang) {
  return rafword_get_lang_folder().$lang.'.txt';
}

function rafword_load_translations($lang) {
  $fname = rafword_get_lang_fname($lang);
  if (file_exists($fname)) {
    $lines = file($fname);
    $words = array();
    foreach ($lines as $line) {
      $line = trim($line);
      if ($line && ($line[0] != '#')) {
        $p = explode('=>', $line, 2);
        if (count($p) == 2) {
          $from = trim($p[0]);
          $to = trim($p[1]);
          $words[$from] = $to;
        }
      }
    }
  } else {
    $words = false;
  }
  return $words;
}

function rafword_get_translations($lang) {
  global $rafword_translations;
  if (!is_array($rafword_translations)) {
    $rafword_translations = array();
  }
  if (!isset($rafword_translations[$lang])) {
    $rafword_translations[$lang] = rafword_load_translations($lang);
  }
  return $rafword_translations[$lang];
}


# Admin panel

add_action('admin_menu', 'rafword_admin_menu');
function rafword_admin_menu() {
  add_submenu_page('options-general.php', 'Translations', 'Translations', 'manage_options', 'rafword_options', 'rafword_show_admin');
}

add_action('admin_init', 'rafword_admin_save');
function rafword_admin_save() {
  if (!isset($_POST['raf-translations-editor'])) return;
  $text = stripslashes($_POST['raf-translations-editor']);
  $lang = stripslashes($_POST['rwlang']);
  $res = file_put_contents(rafword_get_lang_fname($lang), $text);
  $param = ($res === false) ? '&err=1' : '&saved=1';
  wp_redirect(admin_url('options-general.php?page=rafword_options&rwlang='.$lang.$param));
  die;
}

function rafword_show_admin() {
  echo '<div class="wrap"><h2>Translations &ndash; RAF Word</h2>';
  $lang = @$_GET['rwlang'];
  if ($lang) {
    rafword_admin_show_editor($lang);
  } else {
    rafword_admin_show_menu();
  }
  echo '</div>';
}

function rafword_admin_show_editor($lang) {
  echo '<h3>Language: '.strtoupper($lang).'</h3>';
  echo '<a href="'.admin_url('options-general.php?page=rafword_options').'">Change Language</a><br />';
  if (isset($_GET['saved'])) {
    echo '<div class="updated" id="message"><p>Translation file updated.</p></div>';
  } else if (isset($_GET['err'])) {
    echo '<div class="error" id="message"><p>Can\'t write translation file. Check permissions of "'.rafword_get_lang_folder().'".</p></div>';
  }
  echo '<form method="POST">';
  echo '<input type="hidden" name="rwlang" value="'.$lang.'" />';
  echo '<textarea name="raf-translations-editor" class="raf-translations">'.
       file_get_contents(rafword_get_lang_fname($lang)).'</textarea><br />';
  echo '<input type="submit" class="button" value="Save Changes" />';
  echo '</form>';
  echo '<style>textarea.raf-translations{width:100%;min-height:600px;font-family:\'Courier New\',mono,sans-serif;font-size:18px;}</style>';
}

function rafword_admin_show_menu() {
  echo '<br />Choose language:<br /><br />';
  foreach (rafword_get_langs() as $lang) {
    $url = admin_url('options-general.php?page=rafword_options&rwlang='.$lang);
    echo '<a class="button-primary" style="margin-right:15px;font-size:48px;height:65px;line-height:60px" href="'.$url.'">'.strtoupper($lang).'</a> ';
  }
}

function rafword_get_langs() {
  $folder = rafword_get_lang_folder();
  $files = scandir($folder);
  $langs = array();
  foreach ($files as $file) {
    if (!$file) continue;
    if ($file[0] == '.') continue;
    $parts = explode('.', $file);
    if (count($parts) != 2) continue;
    if ($parts[1] != 'txt') continue;
    $lang = $parts[0];
    if (strlen($parts[0]) != 2) continue;
    $langs[] = $lang;
  }
  return $langs;
}

