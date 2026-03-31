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

/**
 * Hook called when the profile changes.
 *
 * @return void
 */
function plugin_change_profile_etiquetachamados()
{
    // Refresh rights in session when the profile changes
    $profile = new PluginEtiquetachamadosProfile();
    // No specific action needed; GLPI auto-reloads rights
}


/**
 * Plugin install process
 *
 * @return boolean
 */
function plugin_etiquetachamados_install()
{
    global $DB;

    $migration = new Migration(PLUGIN_ETIQUETACHAMADOS_VERSION);

    $default_charset   = DBConnection::getDefaultCharset();
    $default_collation = DBConnection::getDefaultCollation();
    $default_key_sign  = DBConnection::getDefaultPrimaryKeySignOption();

    // ---------------------------------------------------------------
    // Table: glpi_plugin_etiquetachamados_configs
    // Stores per-entity printer configuration + ZPL template
    // ---------------------------------------------------------------
    if (!$DB->tableExists('glpi_plugin_etiquetachamados_configs')) {
        $query = "CREATE TABLE `glpi_plugin_etiquetachamados_configs` (
            `id`            int {$default_key_sign} NOT NULL AUTO_INCREMENT,
            `entities_id`   int {$default_key_sign} NOT NULL DEFAULT '0',
            `is_recursive`  tinyint NOT NULL DEFAULT '1',
            `printer_ip`    varchar(255) DEFAULT NULL,
            `printer_port`  int NOT NULL DEFAULT '9100',
            `zpl_template`  text DEFAULT NULL,
            `is_active`     tinyint NOT NULL DEFAULT '1',
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod`      timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `entities_id` (`entities_id`),
            KEY `is_recursive` (`is_recursive`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset}
          COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";

        $DB->doQuery($query) or die("Erro ao criar tabela configs: " . $DB->error());

        Toolbox::logInFile(
            'etiquetachamados',
            "Tabela glpi_plugin_etiquetachamados_configs criada com sucesso.\n"
        );
    }

    // ---------------------------------------------------------------
    // Table: glpi_plugin_etiquetachamados_printjobs
    // Async print queue
    // ---------------------------------------------------------------
    if (!$DB->tableExists('glpi_plugin_etiquetachamados_printjobs')) {
        $query = "CREATE TABLE `glpi_plugin_etiquetachamados_printjobs` (
            `id`            int {$default_key_sign} NOT NULL AUTO_INCREMENT,
            `tickets_id`    int {$default_key_sign} NOT NULL DEFAULT '0',
            `entities_id`   int {$default_key_sign} NOT NULL DEFAULT '0',
            `users_id`      int {$default_key_sign} NOT NULL DEFAULT '0',
            `status`        int NOT NULL DEFAULT '0'
                            COMMENT '0=pendente, 1=processando, 2=concluido, 3=erro',
            `zpl_content`   text DEFAULT NULL,
            `error_message` text DEFAULT NULL,
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod`      timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `tickets_id` (`tickets_id`),
            KEY `entities_id` (`entities_id`),
            KEY `users_id` (`users_id`),
            KEY `status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset}
          COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";

        $DB->doQuery($query) or die("Erro ao criar tabela printjobs: " . $DB->error());

        Toolbox::logInFile(
            'etiquetachamados',
            "Tabela glpi_plugin_etiquetachamados_printjobs criada com sucesso.\n"
        );
    }

    // ---------------------------------------------------------------
    // Rights: register the plugin right
    // ---------------------------------------------------------------
    // Rights: register the plugin right
    ProfileRight::addProfileRights([
        'plugin_etiquetachamados_print',
        'plugin_etiquetachamados_config'
    ]);

    // Grant full access to super-admin profiles (those that can update Config)
    $migration->addRight(
        'plugin_etiquetachamados_config',
        ALL_RES,
        [Config::$rightname => UPDATE]
    );

    // ---------------------------------------------------------------
    // CronTask: register the async print job processor
    // ---------------------------------------------------------------
    CronTask::register(
        PluginEtiquetachamadosPrintjob::class,
        'EtiquetaPrint',
        60, // Run every 60 seconds
        [
            'param'   => 10,     // Process up to 10 jobs per run
            'mode'    => CronTask::MODE_EXTERNAL,
            'comment' => 'Processa a fila de impressão de etiquetas',
        ]
    );

    $migration->executeMigration();

    return true;
}


/**
 * Plugin uninstall process
 *
 * @return boolean
 */
function plugin_etiquetachamados_uninstall()
{
    global $DB;

    // Drop plugin tables
    $tables = [
        'glpi_plugin_etiquetachamados_configs',
        'glpi_plugin_etiquetachamados_printjobs',
    ];

    foreach ($tables as $table) {
        if ($DB->tableExists($table)) {
            $DB->doQuery("DROP TABLE `{$table}`");
            Toolbox::logInFile(
                'etiquetachamados',
                "Tabela {$table} removida.\n"
            );
        }
    }

    // Remove rights
    ProfileRight::deleteProfileRights([
        'plugin_etiquetachamados_print',
        'plugin_etiquetachamados_config'
    ]);

    // Remove CronTask
    $crontask = new CronTask();
    if ($crontask->getFromDBbyName(PluginEtiquetachamadosPrintjob::class, 'EtiquetaPrint')) {
        $crontask->delete(['id' => $crontask->getID()]);
    }

    return true;
}

function plugin_etiquetachamados_pre_item_update_profile($profile) {
    // Só age se o POST veio da nossa aba
    if (isset($_POST['update_plugin_etiquetachamados_profile'])) {
        global $DB;
        $profiles_id = $profile->getID();
        
        $managed_rights = [
            'plugin_etiquetachamados_print',
            'plugin_etiquetachamados_config'
        ];

        foreach ($managed_rights as $right) {
            $post_key = '_' . $right;
            $rightsValue = 0;

            if (isset($_POST[$post_key]) && is_array($_POST[$post_key])) {
                foreach (array_keys($_POST[$post_key]) as $v) {
                    $exploded = explode('_', $v);
                    $rightsValue += (int)$exploded[0];
                }
            }

            // Atualiza diretamente no banco para contornar falhas de cache
            $DB->delete('glpi_profilerights', [
                'profiles_id' => $profiles_id,
                'name'        => $right
            ]);

            if ($rightsValue > 0) {
                $DB->insert('glpi_profilerights', [
                    'profiles_id' => $profiles_id,
                    'name'        => $right,
                    'rights'      => $rightsValue
                ]);
            }
        }

        // Limpa o cache de sessão do perfil
        if (isset($_SESSION['glpiprofiles'][$profiles_id])) {
            unset($_SESSION['glpiprofiles'][$profiles_id]);
        }
    }
    
    return true; // Retorna true para deixar o GLPI continuar o fluxo dele
}