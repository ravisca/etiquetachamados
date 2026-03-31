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

    // Log every step so we can diagnose installation failures
    Toolbox::logInFile('etiquetachamados', "=== INÍCIO DA INSTALAÇÃO ===\n");

    try {
        $migration = new Migration(PLUGIN_ETIQUETACHAMADOS_VERSION);
        Toolbox::logInFile('etiquetachamados', "Migration criada OK\n");

        // -----------------------------------------------------------
        // Safe defaults: DBConnection methods may NOT exist in all
        // GLPI 10.0.x versions. Use fallbacks.
        // -----------------------------------------------------------
        $default_charset   = 'utf8mb4';
        $default_collation = 'utf8mb4_unicode_ci';
        $default_key_sign  = 'unsigned';

        if (method_exists('DBConnection', 'getDefaultCharset')) {
            $default_charset = DBConnection::getDefaultCharset();
        }
        if (method_exists('DBConnection', 'getDefaultCollation')) {
            $default_collation = DBConnection::getDefaultCollation();
        }
        if (method_exists('DBConnection', 'getDefaultPrimaryKeySignOption')) {
            $default_key_sign = DBConnection::getDefaultPrimaryKeySignOption();
        }

        Toolbox::logInFile(
            'etiquetachamados',
            "DB defaults: charset={$default_charset}, collation={$default_collation}, key_sign={$default_key_sign}\n"
        );

        // -----------------------------------------------------------
        // Table: glpi_plugin_etiquetachamados_configs
        // -----------------------------------------------------------
        if (!$DB->tableExists('glpi_plugin_etiquetachamados_configs')) {
            Toolbox::logInFile('etiquetachamados', "Criando tabela configs...\n");

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

            $result = $DB->doQuery($query);
            if (!$result) {
                $err = $DB->error();
                Toolbox::logInFile('etiquetachamados', "ERRO criando tabela configs: {$err}\n");
                return false;
            }
            Toolbox::logInFile('etiquetachamados', "Tabela configs criada OK\n");
        } else {
            Toolbox::logInFile('etiquetachamados', "Tabela configs já existe, pulando.\n");
        }

        // -----------------------------------------------------------
        // Table: glpi_plugin_etiquetachamados_printjobs
        // -----------------------------------------------------------
        if (!$DB->tableExists('glpi_plugin_etiquetachamados_printjobs')) {
            Toolbox::logInFile('etiquetachamados', "Criando tabela printjobs...\n");

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

            $result = $DB->doQuery($query);
            if (!$result) {
                $err = $DB->error();
                Toolbox::logInFile('etiquetachamados', "ERRO criando tabela printjobs: {$err}\n");
                return false;
            }
            Toolbox::logInFile('etiquetachamados', "Tabela printjobs criada OK\n");
        } else {
            Toolbox::logInFile('etiquetachamados', "Tabela printjobs já existe, pulando.\n");
        }

        // -----------------------------------------------------------
        // Rights
        // -----------------------------------------------------------
        Toolbox::logInFile('etiquetachamados', "Registrando direitos...\n");

        ProfileRight::addProfileRights([
            'plugin_etiquetachamados_print',
            'plugin_etiquetachamados_config'
        ]);

        // Grant full access to super-admin profiles
        // ALLSTANDARDRIGHT = 31 (CREATE|READ|UPDATE|DELETE|PURGE)
        $rightsValue = defined('ALLSTANDARDRIGHT') ? ALLSTANDARDRIGHT : 31;
        $migration->addRight(
            'plugin_etiquetachamados_config',
            $rightsValue,
            ['config' => UPDATE]
        );

        Toolbox::logInFile('etiquetachamados', "Direitos registrados OK\n");

        // -----------------------------------------------------------
        // CronTask
        // -----------------------------------------------------------
        Toolbox::logInFile('etiquetachamados', "Registrando CronTask...\n");

        CronTask::register(
            'PluginEtiquetachamadosPrintjob',
            'EtiquetaPrint',
            60,
            [
                'param'   => 10,
                'mode'    => CronTask::MODE_EXTERNAL,
                'comment' => 'Processa a fila de impressao de etiquetas',
            ]
        );

        Toolbox::logInFile('etiquetachamados', "CronTask registrada OK\n");

        $migration->executeMigration();

        Toolbox::logInFile('etiquetachamados', "=== INSTALAÇÃO CONCLUÍDA COM SUCESSO ===\n");
        return true;

    } catch (\Throwable $e) {
        // Catch ANY error (including fatal-like TypeError, Error, etc.)
        Toolbox::logInFile(
            'etiquetachamados',
            "=== ERRO FATAL NA INSTALAÇÃO ===\n"
            . "Mensagem: " . $e->getMessage() . "\n"
            . "Arquivo:  " . $e->getFile() . ":" . $e->getLine() . "\n"
            . "Trace:\n" . $e->getTraceAsString() . "\n"
        );
        return false;
    }
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