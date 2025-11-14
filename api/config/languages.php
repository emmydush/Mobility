<?php
// Language configuration for the inventory management system

// Supported languages
$supported_languages = [
    'en' => 'English',
    'fr' => 'Français', 
    'rw' => 'Kinyarwanda'
];

// Default language
$default_language = 'en';

// Function to get the current language
function getCurrentLanguage() {
    global $default_language, $supported_languages;
    
    // Check if language is set in session
    if (isset($_SESSION['language']) && array_key_exists($_SESSION['language'], $supported_languages)) {
        return $_SESSION['language'];
    }
    
    // Check if language is set in URL
    if (isset($_GET['lang']) && array_key_exists($_GET['lang'], $supported_languages)) {
        $_SESSION['language'] = $_GET['lang'];
        return $_GET['lang'];
    }
    
    // Return default language
    return $default_language;
}

// Function to set the current language
function setCurrentLanguage($lang) {
    global $supported_languages;
    
    if (array_key_exists($lang, $supported_languages)) {
        $_SESSION['language'] = $lang;
        return true;
    }
    
    return false;
}

// Function to get translated text
function getTranslatedText($key, $lang = null) {
    global $default_language;
    
    if ($lang === null) {
        $lang = getCurrentLanguage();
    }
    
    // Load translations for the language
    $translations = loadTranslations($lang);
    
    // Return translated text or the key if not found
    return isset($translations[$key]) ? $translations[$key] : $key;
}

// Function to load translations
function loadTranslations($lang) {
    $translation_file = __DIR__ . "/../../languages/{$lang}.php";
    
    if (file_exists($translation_file)) {
        return include $translation_file;
    }
    
    // Return empty array if file doesn't exist
    return [];
}

// Function to translate a string
function t($key, $lang = null) {
    return getTranslatedText($key, $lang);
}
?>