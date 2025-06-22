<?php
namespace AffiliateBloom;

class Autoload {

    public static function autoload() {
        $directories = array(
            AFFILIATE_BLOOM_INCLUDES_DIR_PATH,
        );

        foreach ( $directories as $directory ) {
            self::load_files_recursive($directory);
        }
    }

    private static function load_files_recursive($directory) {
        $files = glob($directory . '*.php');
        foreach ($files as $file) {
            if (file_exists($file)) {
                require_once $file;
            }
        }

        $subdirectories = glob($directory . '*/', GLOB_ONLYDIR);
        foreach ($subdirectories as $subdirectory) {
            self::load_files_recursive($subdirectory);
        }
    }

    public static function init() {
        spl_autoload_register(array(__CLASS__, 'autoload'));
    }
}

Autoload::init();