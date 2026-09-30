<?php

declare(strict_types=1);

use Glpi\Plugin\HookManager;
use Glpi\Plugin\Hooks;
use GlpiPlugin\Ticketoperations\Install\ProfileRightSynchronizer;
use GlpiPlugin\Ticketoperations\Profile;

defined('GLPI_ROOT') or die('No direct access allowed');

const PLUGIN_TICKETOPERATIONS_VERSION = '0.0.1';
const PLUGIN_TICKETOPERATIONS_MIN_GLPI = '11.0.8';
const PLUGIN_TICKETOPERATIONS_MAX_GLPI = '11.1.0';
const PLUGIN_TICKETOPERATIONS_MIN_PHP = '8.2.0';

function plugin_ticketoperations_autoload(): void
{
    $autoload = __DIR__ . '/vendor/autoload.php';
    if (is_file($autoload)) {
        require_once $autoload;
    }
}

function plugin_init_ticketoperations(): void
{
    global $PLUGIN_HOOKS;

    plugin_ticketoperations_autoload();
    $PLUGIN_HOOKS[Hooks::CSRF_COMPLIANT]['ticketoperations'] = true;

    $hookManager = new HookManager('ticketoperations');
    $hookManager->registerCSSFile('css/ticketoperations.css');
    $hookManager->registerJavascriptFile('js/ticketoperations.js');

    if (class_exists(Plugin::class) && Plugin::isPluginActive('ticketoperations')) {
        (new ProfileRightSynchronizer())->refreshActiveProfileRights();
        Plugin::registerClass(Profile::class, ['addtabon' => [\Profile::class]]);
    }
}

function plugin_version_ticketoperations(): array
{
    return [
        'name' => __('Ticket Operations', 'ticketoperations'),
        'version' => PLUGIN_TICKETOPERATIONS_VERSION,
        'author' => 'TiniSys IT Solutions',
        'license' => 'GPL-3.0-or-later',
        'homepage' => 'https://github.com/TiniSys-IT-Solutions/glpi-ticket-operations',
        'requirements' => [
            'glpi' => ['min' => PLUGIN_TICKETOPERATIONS_MIN_GLPI, 'max' => PLUGIN_TICKETOPERATIONS_MAX_GLPI],
            'php' => ['min' => PLUGIN_TICKETOPERATIONS_MIN_PHP],
        ],
    ];
}

function plugin_ticketoperations_check_prerequisites(): bool
{
    return defined('GLPI_VERSION')
        && version_compare(GLPI_VERSION, PLUGIN_TICKETOPERATIONS_MIN_GLPI, '>=')
        && version_compare(GLPI_VERSION, PLUGIN_TICKETOPERATIONS_MAX_GLPI, '<')
        && version_compare(PHP_VERSION, PLUGIN_TICKETOPERATIONS_MIN_PHP, '>=');
}

function plugin_ticketoperations_check_config(bool $verbose = false): bool
{
    return true;
}
