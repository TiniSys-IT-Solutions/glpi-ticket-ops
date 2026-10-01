<?php

declare(strict_types=1);

use Glpi\Http\Firewall;
use Glpi\Plugin\HookManager;
use Glpi\Plugin\Hooks;
use GlpiPlugin\Ticketops\Install\ProfileRightSynchronizer;
use GlpiPlugin\Ticketops\Profile;
use GlpiPlugin\Ticketops\Ui\TicketPanel;
use GlpiPlugin\Ticketops\Ui\TicketTab;

defined('GLPI_ROOT') or die('No direct access allowed');

const PLUGIN_TICKETOPERATIONS_VERSION = '0.1.7';
const PLUGIN_TICKETOPERATIONS_MIN_GLPI = '11.0.8';
const PLUGIN_TICKETOPERATIONS_MAX_GLPI = '11.1.0';
const PLUGIN_TICKETOPERATIONS_MIN_PHP = '8.2.0';

function plugin_ticketops_autoload(): void
{
    $autoload = __DIR__ . '/vendor/autoload.php';
    if (is_file($autoload)) {
        require_once $autoload;

        return;
    }

    // Keep a direct Git checkout installable even when Composer is only
    // available on the development machine or in the release builder.
    spl_autoload_register(static function (string $class): void {
        $prefix = 'GlpiPlugin\\Ticketops\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }
        $path = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($path)) {
            require_once $path;
        }
    });
}

function plugin_init_ticketops(): void
{
    global $PLUGIN_HOOKS;

    plugin_ticketops_autoload();
    $PLUGIN_HOOKS[Hooks::CSRF_COMPLIANT]['ticketops'] = true;

    $hookManager = new HookManager('ticketops');
    $hookManager->registerCSSFile('css/ticketoperations.css');
    $hookManager->registerJavascriptFile('js/ticketoperations.js');
    $PLUGIN_HOOKS[Hooks::CONFIG_PAGE]['ticketops'] = 'front/config.php';
    $PLUGIN_HOOKS[Hooks::POST_ITIL_INFO_SECTION]['ticketops'] = [TicketPanel::class, 'render'];

    if (class_exists(Firewall::class)) {
        Firewall::addPluginStrategyForLegacyScripts(
            'ticketops',
            '#^/front/config\.php$#',
            Firewall::STRATEGY_CENTRAL_ACCESS,
        );
    }

    if (class_exists(Plugin::class) && Plugin::isPluginActive('ticketops')) {
        (new ProfileRightSynchronizer())->refreshActiveProfileRights();
        Plugin::registerClass(Profile::class, ['addtabon' => [\Profile::class]]);
        Plugin::registerClass(TicketTab::class, ['addtabon' => [\Ticket::class]]);
    }
}

function plugin_version_ticketops(): array
{
    return [
        'name' => __('TicketOps', 'ticketops'),
        'version' => PLUGIN_TICKETOPERATIONS_VERSION,
        'author' => 'TiniSys IT Solutions',
        'license' => 'GPL-3.0-or-later',
        'homepage' => 'https://github.com/TiniSys-IT-Solutions/glpi-ticket-ops',
        'requirements' => [
            'glpi' => ['min' => PLUGIN_TICKETOPERATIONS_MIN_GLPI, 'max' => PLUGIN_TICKETOPERATIONS_MAX_GLPI],
            'php' => ['min' => PLUGIN_TICKETOPERATIONS_MIN_PHP],
        ],
    ];
}

function plugin_ticketops_check_prerequisites(): bool
{
    return defined('GLPI_VERSION')
        && version_compare(GLPI_VERSION, PLUGIN_TICKETOPERATIONS_MIN_GLPI, '>=')
        && version_compare(GLPI_VERSION, PLUGIN_TICKETOPERATIONS_MAX_GLPI, '<')
        && version_compare(PHP_VERSION, PLUGIN_TICKETOPERATIONS_MIN_PHP, '>=');
}

function plugin_ticketops_check_config(bool $verbose = false): bool
{
    return true;
}
