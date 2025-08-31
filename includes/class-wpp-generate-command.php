<?php
class WPP_Generate_Command {

    /**
     * Generate a new WordPress plugin boilerplate (MVC + Composer autoload).
     *
     * ## OPTIONS
     *
     *
     * [--name=<name>]
     * : Plugin display name.
     *
     * [--author=<author>]
     * : Plugin author.
     *
     * [--uri=<uri>]
     * : Plugin URI.
     *
     * [--namespace=<namespace>]
     * : PHP Namespace (default based on name).
     *
     * ## EXAMPLES
     *
     *     wp make:plugin
     *     wp make:plugin --name="My Plugin" --author="John Doe"
     */

    public function __invoke($assoc_args)
    {
        // Collect inputs
        $inputs = self::collectInputs();

        // Determine plugin directory
        $pluginDir = self::getPluginDir($inputs['slug']);

        // Copy template
        self::copyTemplate($pluginDir);

        // Replace placeholders
        self::replacePlaceholders($pluginDir, $inputs);

        // Run composer
        self::runComposer($pluginDir);

        WP_CLI::success("👏 Plugin '{$inputs['name']}' created at $pluginDir");


    }

    private static function ask($question, $default = null): string
    {
        return \cli\prompt($question, $default);
    }


    private static function collectInputs(): array
    {
        $name = self::ask("Plugin Name (human-readable)", 'My Plugin');

        $slug = strtolower(str_replace(' ', '-', $name));

        $plugin_dir = self::getPluginDir($slug);

        if (is_dir($plugin_dir)) {
            WP_CLI::error("Plugin directory already exists: $plugin_dir");
            exit();
        }

        // Ask interactively if not provided
        $description        = $assoc_args['description']?? self::ask("Description");
        $author             = $assoc_args['author']     ?? self::ask("Author", "Unknown");
        $uri                = $assoc_args['uri']        ?? self::ask("Plugin URI", "https://wordpress.org/plugins/{$slug}");
        $namespace          = $assoc_args['namespace']  ?? self::ask("Namespace", str_replace(' ', '', ucwords($name)));
        $vendor             = $assoc_args['vendor']     ?? self::ask("Vendor (for composer.json)", str_replace(' ', '', ucwords($name)));
        $version            = "1.0.0";
        $license            = "GPL-2.0-or-later";

        $const_prefix       = strtoupper(str_replace(' ', '', $name));
        $package_name       = strtolower($vendor . '/' . str_replace(' ', '-', $name));


        return compact('name', 'slug', 'description', 'author', 'uri', 'namespace', 'vendor', 'version', 'license', 'const_prefix', 'package_name');
    }


    private static function getPluginDir($slug): string
    {
        $dir = WP_PLUGIN_DIR . '/' . $slug;
        if (file_exists($dir)) {
            WP_CLI::error("Directory already exists: $dir");
        }
        return $dir;
    }

    private function copyTemplate($dst): void
    {
        $src = __DIR__ . '/../templates/boilerplate';

        self::recursiveCopy($src, $dst);

        WP_CLI::line("✔ Files generated successfully.");
    }

    private static function recursiveCopy($src, $plugin_dir): void
    {
        $files = glob($src . '*');
        $count = is_array($files) ? count($files) : 0;

        $progress = \WP_CLI\Utils\make_progress_bar( '🔃 Generating files', $count);


        $dir = opendir($src);
        @mkdir($plugin_dir, 0755, true);

        while (false !== ($file = readdir($dir))) {
            $progress->tick();

            if ($file == '.' || $file == '..') continue;

            $srcPath = "$src/$file";
            $dstPath = "$plugin_dir/$file";

            if (is_dir($srcPath)) {
                self::recursiveCopy($srcPath, $dstPath);
            } else {
                if (substr($dstPath, -5) === '.stub') {
                    $dstPath = substr($dstPath, 0, -5);
                }

                copy($srcPath, $dstPath);
            }
        }
        $progress->finish();

        closedir($dir);
    }

    public static function replacePlaceholders($dir, array $vars): void
    {

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            $path = $file->getPathname();

            // 1. Replace contents
            if ($file->isFile()) {
                $contents = file_get_contents($path);

                foreach ($vars as $key => $value) {
                    $contents = str_replace('{{'.$key.'}}', $value, $contents);

                }
                file_put_contents($path, $contents);
            }

            // 2. Replace plugin file name
            $filename = $file->getFilename();

            if ('plugin.php' === $filename) {
                $filename = $vars['slug'] . '.php';
                rename($path, $file->getPath() . DIRECTORY_SEPARATOR . $filename);
            }
        }

        WP_CLI::line("Placeholders replaced successfully.");
    }

    private static function runComposer($pluginDir): void
    {
        if (!file_exists("$pluginDir/composer.json")) return;

        WP_CLI::line("👉 Running composer install...");
        $pluginDir = realpath($pluginDir) ?: $pluginDir;
        $cmd = "composer install --no-interaction --working-dir=" . escapeshellarg($pluginDir);

        $descriptorSpec = [
            0 => STDIN,
            1 => STDOUT,
            2 => STDERR,
        ];
        $process = proc_open($cmd, $descriptorSpec, $pipes);

        if (is_resource($process)) {
            $exitCode = proc_close($process);
            if ($exitCode !== 0) {
                WP_CLI::warning("Composer install failed. Please run manually in: $pluginDir");
            }
        } else {
            WP_CLI::warning("Could not run composer. Please install dependencies manually.");
        }
    }
}
