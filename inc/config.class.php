<?php

/**
 * -------------------------------------------------------------------------
 * Etiqueta Chamados plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * @copyright Copyright (C) 2026 by RBX Soluções & Tech.
 * @license   GPLv2 https://www.gnu.org/licenses/gpl-2.0.html
 * -------------------------------------------------------------------------
 */

/**
 * PluginEtiquetachamadosConfig
 *
 * Manages per-entity printer configuration for label printing.
 * Supports GLPI entity recursion: if no config exists for the current
 * entity, the parent entity's config is inherited.
 */
class PluginEtiquetachamadosConfig extends CommonDBTM
{
    // Link this item to entities
    public static $rightname = 'plugin_etiquetachamados_config';

    // Enable entity restriction
    public $dohistory = true;

    /**
     * Get the table name for this class.
     *
     * @return string
     */
    public static function getTable($classname = null)
    {
        return 'glpi_plugin_etiquetachamados_configs';
    }

    /**
     * Get type name for display.
     *
     * @param int $nb
     * @return string
     */
    public static function getTypeName($nb = 0)
    {
        return __('Etiqueta Chamados', 'etiquetachamados');
    }

    /**
     * Get tab name to display on Entity form.
     *
     * @param CommonGLPI $item
     * @param int $withtemplate
     * @return string
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if (!$withtemplate && $item instanceof Entity) {
            if (Session::haveRight(self::$rightname, READ)) {
                return self::createTabEntry(__('Etiqueta Chamados', 'etiquetachamados'));
            }
        }
        return '';
    }

    /**
     * Display tab content for Entity.
     *
     * @param CommonGLPI $item
     * @param int $tabnum
     * @param int $withtemplate
     * @return boolean
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item instanceof Entity) {
            $config = new self();
            $config->showConfigForm($item->getID());
        }
        return true;
    }

    /**
     * Display the configuration form for a given entity.
     *
     * @param int $entitiesId
     * @return void
     */
    public function showConfigForm(int $entitiesId): void
    {
        if (!Session::haveRight(self::$rightname, UPDATE)) {
            return;
        }

        $config = self::getConfigForEntity($entitiesId);
        $isNew  = ($config === null);

        // If no local config, show inherited values as placeholders
        $inheritedConfig = null;
        if ($isNew) {
            $inheritedConfig = self::getEffectiveConfig($entitiesId);
        }

        echo "<form name='form' action='" . Toolbox::getItemTypeFormURL(__CLASS__) . "' method='post'>";
        echo "<div class='spaced'>";
        echo "<table class='tab_cadre_fixe'>";

        // Header
        echo "<tr class='tab_bg_1'>";
        echo "<th colspan='4'>" . __('Configuração de Impressão de Etiquetas', 'etiquetachamados') . "</th>";
        echo "</tr>";

        if ($isNew && $inheritedConfig !== null) {
            echo "<tr class='tab_bg_1'>";
            echo "<td colspan='4' class='center'>";
            echo "<i class='fas fa-info-circle'></i> ";
            echo __('Configuração herdada da entidade pai. Preencha para sobrescrever.', 'etiquetachamados');
            echo "</td></tr>";
        }

        // Hidden fields
        echo "<input type='hidden' name='entities_id' value='{$entitiesId}'>";
        if (!$isNew) {
            echo "<input type='hidden' name='id' value='{$config['id']}'>";
        }

        // Printer IP
        $printerIp = $config['printer_ip'] ?? '';
        $placeholder = ($isNew && $inheritedConfig) ? $inheritedConfig['printer_ip'] ?? '' : '';
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('IP da Impressora', 'etiquetachamados') . "</td>";
        echo "<td><input type='text' name='printer_ip' value='" . Html::cleanInputText($printerIp) . "'";
        echo " placeholder='" . Html::cleanInputText($placeholder) . "'";
        echo " size='40' class='form-control'></td>";

        // Printer Port
        $printerPort = $config['printer_port'] ?? '9100';
        $placeholderPort = ($isNew && $inheritedConfig) ? ($inheritedConfig['printer_port'] ?? '9100') : '';
        echo "<td>" . __('Porta TCP', 'etiquetachamados') . "</td>";
        echo "<td><input type='number' name='printer_port' value='" . Html::cleanInputText($printerPort) . "'";
        echo " placeholder='" . Html::cleanInputText($placeholderPort) . "'";
        echo " min='1' max='65535' class='form-control'></td>";
        echo "</tr>";

        // Is Recursive
        $isRecursive = $config['is_recursive'] ?? 1;
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Recursivo (herdar para sub-entidades)', 'etiquetachamados') . "</td>";
        echo "<td>";
        Dropdown::showYesNo('is_recursive', $isRecursive);
        echo "</td>";

        // Is Active
        $isActive = $config['is_active'] ?? 1;
        echo "<td>" . __('Ativo', 'etiquetachamados') . "</td>";
        echo "<td>";
        Dropdown::showYesNo('is_active', $isActive);
        echo "</td>";
        echo "</tr>";

        // ZPL Template
        $zplTemplate = $config['zpl_template'] ?? '';
        $placeholderZpl = ($isNew && $inheritedConfig) ? ($inheritedConfig['zpl_template'] ?? '') : '';
        echo "<tr class='tab_bg_1'>";
        echo "<td colspan='4'>";
        echo "<label>" . __('Template ZPL', 'etiquetachamados') . "</label><br>";
        echo "<small class='text-muted'>"
            . __('Variáveis disponíveis: {{ticket_id}}, {{ticket_title}}, {{ticket_date}}, {{entity_name}}, {{requester_name}}, {{ticket_content}}, {{ticket_url}}', 'etiquetachamados')
            . "</small><br>";
        echo "<textarea name='zpl_template' rows='12' cols='100' class='form-control' style='font-family: monospace;'";
        echo " placeholder='" . htmlspecialchars($placeholderZpl) . "'";
        echo ">" . htmlspecialchars($zplTemplate) . "</textarea>";
        echo "</td></tr>";

        // Submit
        echo "<tr class='tab_bg_2'>";
        echo "<td colspan='4' class='center'>";
        if ($isNew) {
            echo "<input type='submit' name='add' class='btn btn-primary' value='" . _sx('button', 'Save') . "'>";
        } else {
            echo "<input type='submit' name='update' class='btn btn-primary' value='" . _sx('button', 'Save') . "'>";
        }
        echo "</td></tr>";

        echo "</table></div>";
        Html::closeForm();
    }

    /**
     * Get the config row for a specific entity (direct, not inherited).
     *
     * @param int $entitiesId
     * @return array|null
     */
    public static function getConfigForEntity(int $entitiesId): ?array
    {
        global $DB;

        $iterator = $DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => ['entities_id' => $entitiesId],
            'LIMIT' => 1,
        ]);

        if ($row = $iterator->current()) {
            return $row;
        }

        return null;
    }

    /**
     * Get the effective configuration for an entity, respecting recursion.
     * Climbs the entity tree until a config with is_recursive=1 is found.
     *
     * @param int $entitiesId
     * @return array|null
     */
    public static function getEffectiveConfig(int $entitiesId): ?array
    {
        // First, check for a direct config
        $config = self::getConfigForEntity($entitiesId);
        if ($config !== null) {
            return $config;
        }

        // Climb the entity tree
        $entity = new Entity();
        if (!$entity->getFromDB($entitiesId)) {
            return null;
        }

        $ancestors = getAncestorsOf('glpi_entities', $entitiesId);

        // getAncestorsOf returns closest ancestors last; we need to traverse
        // from closest parent to root to find the nearest config
        $ancestors = array_reverse($ancestors, true);

        foreach ($ancestors as $ancestorId) {
            $config = self::getConfigForEntity($ancestorId);
            if ($config !== null && $config['is_recursive']) {
                return $config;
            }
        }

        return null;
    }

    /**
     * Validate input before add.
     *
     * @param array $input
     * @return array|false
     */
    public function prepareInputForAdd($input)
    {
        $input = $this->validateInput($input);
        if ($input === false) {
            return false;
        }

        $input['date_creation'] = date('Y-m-d H:i:s');
        $input['date_mod']      = date('Y-m-d H:i:s');

        return $input;
    }

    /**
     * Validate input before update.
     *
     * @param array $input
     * @return array|false
     */
    public function prepareInputForUpdate($input)
    {
        $input = $this->validateInput($input);
        if ($input === false) {
            return false;
        }

        $input['date_mod'] = date('Y-m-d H:i:s');

        return $input;
    }

    /**
     * Common input validation.
     *
     * @param array $input
     * @return array|false
     */
    private function validateInput(array $input)
    {
        if (isset($input['printer_ip']) && !empty($input['printer_ip'])) {
            $ip = trim($input['printer_ip']);
            // Accept IP or hostname
            if (!filter_var($ip, FILTER_VALIDATE_IP) && !preg_match('/^[a-zA-Z0-9.\-]+$/', $ip)) {
                Session::addMessageAfterRedirect(
                    __('IP ou hostname da impressora inválido.', 'etiquetachamados'),
                    false,
                    ERROR
                );
                return false;
            }
            $input['printer_ip'] = $ip;
        }

        if (isset($input['printer_port'])) {
            $port = intval($input['printer_port']);
            if ($port < 1 || $port > 65535) {
                Session::addMessageAfterRedirect(
                    __('A porta deve estar entre 1 e 65535.', 'etiquetachamados'),
                    false,
                    ERROR
                );
                return false;
            }
            $input['printer_port'] = $port;
        }

        return $input;
    }
}
