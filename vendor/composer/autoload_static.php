<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit62b8c0a821dc44c3b86fc9def1bcbee9
{
    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
        'WP_Autoplugin\\API' => __DIR__ . '/../..' . '/includes/api/class-api.php',
        'WP_Autoplugin\\Admin' => __DIR__ . '/../..' . '/includes/admin/class-admin.php',
        'WP_Autoplugin\\Admin\\Ajax' => __DIR__ . '/../..' . '/includes/admin/class-ajax.php',
        'WP_Autoplugin\\Admin\\Bulk_Actions' => __DIR__ . '/../..' . '/includes/admin/class-bulk-actions.php',
        'WP_Autoplugin\\Admin\\Notices' => __DIR__ . '/../..' . '/includes/admin/class-notices.php',
        'WP_Autoplugin\\Admin\\Plugin_List_Table' => __DIR__ . '/../..' . '/includes/admin/class-plugin-list-table.php',
        'WP_Autoplugin\\Admin\\Scripts' => __DIR__ . '/../..' . '/includes/admin/class-scripts.php',
        'WP_Autoplugin\\Admin\\Settings' => __DIR__ . '/../..' . '/includes/admin/class-settings.php',
        'WP_Autoplugin\\Anthropic_API' => __DIR__ . '/../..' . '/includes/api/class-anthropic-api.php',
        'WP_Autoplugin\\Custom_API' => __DIR__ . '/../..' . '/includes/api/class-custom-api.php',
        'WP_Autoplugin\\GitHub_Updater' => __DIR__ . '/../..' . '/includes/class-github-updater.php',
        'WP_Autoplugin\\Google_Gemini_API' => __DIR__ . '/../..' . '/includes/api/class-google-gemini-api.php',
        'WP_Autoplugin\\Hooks_Extender' => __DIR__ . '/../..' . '/includes/class-hooks-extender.php',
        'WP_Autoplugin\\OpenAI_API' => __DIR__ . '/../..' . '/includes/api/class-openai-api.php',
        'WP_Autoplugin\\Plugin_Explainer' => __DIR__ . '/../..' . '/includes/class-plugin-explainer.php',
        'WP_Autoplugin\\Plugin_Extender' => __DIR__ . '/../..' . '/includes/class-plugin-extender.php',
        'WP_Autoplugin\\Plugin_Fixer' => __DIR__ . '/../..' . '/includes/class-plugin-fixer.php',
        'WP_Autoplugin\\Plugin_Generator' => __DIR__ . '/../..' . '/includes/class-plugin-generator.php',
        'WP_Autoplugin\\Plugin_Installer' => __DIR__ . '/../..' . '/includes/class-plugin-installer.php',
        'WP_Autoplugin\\XAI_API' => __DIR__ . '/../..' . '/includes/api/class-xai-api.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->classMap = ComposerStaticInit62b8c0a821dc44c3b86fc9def1bcbee9::$classMap;

        }, null, ClassLoader::class);
    }
}
