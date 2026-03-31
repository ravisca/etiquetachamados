<?php

/**
 * -------------------------------------------------------------------------
 * Etiqueta Chamados plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of Etiqueta Chamados.
 *
 * Etiqueta Chamados is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Etiqueta Chamados is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Etiqueta Chamados. If not, see <http://www.gnu.org/licenses/>.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2026 by RBX Soluções & Tech.
 * @license   GPLv2 https://www.gnu.org/licenses/gpl-2.0.html
 * -------------------------------------------------------------------------
 */

use Glpi\Plugin\Hooks;

define('PLUGIN_ETIQUETACHAMADOS_VERSION', '1.0.0');

// Minimal GLPI version, inclusive
define('PLUGIN_ETIQUETACHAMADOS_MIN_GLPI', '10.0.0');
// Maximum GLPI version, exclusive
define('PLUGIN_ETIQUETACHAMADOS_MAX_GLPI', '10.0.99');

/**
 * Init hooks of the plugin.
 * REQUIRED
 *
 * @return void
 */
function plugin_init_etiquetachamados()
{
    global $PLUGIN_HOOKS;

    // CSRF compliance (required since GLPI 0.84)
    $PLUGIN_HOOKS[Hooks::CSRF_COMPLIANT]['etiquetachamados'] = true;

    // Register Config class — adds tab on Entity form
    Plugin::registerClass(
        PluginEtiquetachamadosConfig::class,
        ['addtabon' => ['Entity']]
    );

    // Register Profile class — adds tab on Profile form
    Plugin::registerClass(
        PluginEtiquetachamadosProfile::class,
        ['addtabon' => ['Profile']]
    );

    $PLUGIN_HOOKS['pre_item_update']['etiquetachamados'] = [
        'Profile' => 'plugin_etiquetachamados_pre_item_update_profile'
    ];

    // Config page link
    if (Session::haveRight('config', UPDATE)) {
        $PLUGIN_HOOKS['config_page']['etiquetachamados'] = 'front/config.form.php';
    }

    // Profile change hook
    $PLUGIN_HOOKS['change_profile']['etiquetachamados'] = 'plugin_change_profile_etiquetachamados';

    // Inject print button on Ticket form (POST_ITEM_FORM hook)
    $PLUGIN_HOOKS[Hooks::POST_ITEM_FORM]['etiquetachamados'] = [
        PluginEtiquetachamadosTicket::class, 'postItemForm',
    ];

    // Add CSS and JS assets
    $PLUGIN_HOOKS[Hooks::ADD_CSS]['etiquetachamados']        = 'css/etiquetachamados.css';
    $PLUGIN_HOOKS[Hooks::ADD_JAVASCRIPT]['etiquetachamados'] = 'js/etiquetachamados.js';
}


/**
 * Get the name and the version of the plugin
 * REQUIRED
 *
 * @return array
 */
function plugin_version_etiquetachamados()
{
    return [
        'name'         => 'Etiqueta Chamados',
        'version'      => PLUGIN_ETIQUETACHAMADOS_VERSION,
        'author'       => 'RBX Soluções & Tech',
        'license'      => 'GPLv2+',
        'homepage'     => '',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_ETIQUETACHAMADOS_MIN_GLPI,
                'max' => PLUGIN_ETIQUETACHAMADOS_MAX_GLPI,
            ],
        ],
    ];
}


/**
 * Check pre-requisites before install
 *
 * @return boolean
 */
function plugin_etiquetachamados_check_prerequisites()
{
    if (!function_exists('fsockopen')) {
        echo "A função fsockopen é necessária para comunicação com a impressora.";
        return false;
    }
    return true;
}


/**
 * Check configuration process
 *
 * @param boolean $verbose Whether to display message on failure. Defaults to false
 *
 * @return boolean
 */
function plugin_etiquetachamados_check_config($verbose = false)
{
    if (true) {
        return true;
    }

    if ($verbose) {
        echo __('Installed / not configured', 'etiquetachamados');
    }
    return false;
}
