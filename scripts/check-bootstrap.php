<?php

declare(strict_types=1);

// Only controller discovery is checked here; no GLPI kernel or database is booted.
namespace Glpi\Controller {
    abstract class AbstractController {}
}

namespace {
    function __(string $message, ?string $domain = null): string
    {
        return $message;
    }

    $root = $argv[1] ?? dirname(__DIR__);
    $mode = $argv[2] ?? 'composer';
    if (!in_array($mode, ['composer', 'native'], true)) {
        throw new RuntimeException('Expected composer or native autoload mode.');
    }
    define('GLPI_ROOT', $root);
    require $root . '/setup.php';
    require $root . '/hook.php';

    $key = 'ticketops';
    $prefix = 'GlpiPlugin\\' . ucfirst($key) . '\\';
    if ($mode === 'composer') {
        require $root . '/vendor/autoload.php';
    } else {
        // GLPI registers this key-derived PSR-4 mapping before discovering services.
        // Do not call plugin_init_ticketops(): discovery precedes plugin initialization.
        spl_autoload_register(static function (string $class) use ($root, $prefix): void {
            if (!str_starts_with($class, $prefix)) {
                return;
            }
            $path = $root . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($path)) {
                require_once $path;
            }
        });
    }

    foreach (["plugin_init_$key", "plugin_version_$key", "plugin_{$key}_install", "plugin_{$key}_uninstall"] as $function) {
        if (!function_exists($function)) {
            throw new RuntimeException('Missing plugin entry point: ' . $function);
        }
    }
    $metadata = plugin_version_ticketops();
    if ($metadata['version'] !== \GlpiPlugin\Ticketops\Plugin::VERSION) {
        throw new RuntimeException('Plugin version metadata mismatch.');
    }
    $controllers = glob($root . '/src/Controller/*.php');
    if ($controllers === false || $controllers === []) {
        throw new RuntimeException('No plugin controllers found.');
    }
    foreach ($controllers as $file) {
        $class = $prefix . 'Controller\\' . basename($file, '.php');
        if (!class_exists($class)) {
            throw new RuntimeException('Unable to discover controller: ' . $class);
        }
        $reflection = new ReflectionClass($class);
        if ($reflection->getFileName() !== realpath($file)
            || !$reflection->isSubclassOf(\Glpi\Controller\AbstractController::class)) {
            throw new RuntimeException('Invalid controller mapping: ' . $class);
        }
    }
    echo 'PASS: plugin metadata and ' . count($controllers) . ' controllers (' . $mode . ")\n";
}
