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
 * PluginEtiquetachamadosTicket
 *
 * Injects a "Print Label" button into the Ticket form via the
 * POST_ITEM_FORM hook. Only displays if the ticket is saved and
 * the current user has the print right.
 */
class PluginEtiquetachamadosTicket
{
    /**
     * Hook: POST_ITEM_FORM
     * Inserts the print button at the end of the Ticket form.
     *
     * @param array $params Array with 'item' and 'options' keys
     * @return void
     */
    public static function postItemForm(array $params): void
    {
        $item = $params['item'] ?? null;

        // Only act on Tickets
        if (!$item instanceof Ticket) {
            return;
        }

        // Only on existing tickets (not new ones)
        if ($item->isNewItem()) {
            return;
        }

        // Check user right
        if (!Session::haveRight('plugin_etiquetachamados_print', READ)) {
            return;
        }

        // Check if there is an effective config for this entity
        $entityId = $item->fields['entities_id'] ?? 0;
        $config = PluginEtiquetachamadosConfig::getEffectiveConfig($entityId);
        if ($config === null || empty($config['printer_ip']) || !$config['is_active']) {
            return;
        }

        $ticketId = $item->getID();
        $pluginUrl = Plugin::getWebDir('etiquetachamados');
        $ajaxUrl   = $pluginUrl . '/front/print.php';

        // Render the print button
        echo "<tr class='tab_bg_1'>";
        echo "<td colspan='4' class='center'>";
        echo "<button type='button' 
                      id='etiquetachamados-print-btn' 
                      class='btn btn-outline-primary etiquetachamados-print-btn' 
                      data-ticket-id='{$ticketId}' 
                      data-ajax-url='{$ajaxUrl}'
                      title='" . __('Imprimir Etiqueta', 'etiquetachamados') . "'>";
        echo "<i class='fas fa-print me-1'></i> ";
        echo __('Imprimir Etiqueta', 'etiquetachamados');
        echo "</button>";
        echo "<span id='etiquetachamados-print-status' class='ms-2'></span>";
        echo "</td></tr>";
    }
}
