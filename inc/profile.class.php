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
 * PluginEtiquetachamadosProfile
 *
 * Manages plugin rights on the Profile form.
 * Adds a tab "Etiqueta Chamados" on each Profile page with a rights matrix.
 */
class PluginEtiquetachamadosProfile extends Profile
{
    /**
     * Get tab name for Profile item.
     *
     * @param CommonGLPI $item
     * @param int $withtemplate
     * @return string
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if (
            $item instanceof Profile
            && $item->getField('id')
        ) {
            return self::createTabEntry(__('Etiqueta Chamados', 'etiquetachamados'));
        }

        return '';
    }

    /**
     * Display tab content for Profile.
     *
     * @param CommonGLPI $item
     * @param int $tabnum
     * @param int $withtemplate
     * @return boolean
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item instanceof Profile) {
            PluginEtiquetachamadosProfile::showProfileForm($item);
        }
        return true;
    }

    /**
     * Display the rights form for the plugin.
     *
     * @param Profile $profile
     * @return void
     */
    public static function showProfileForm(Profile $profile): void
    {
        $profilesId = $profile->getID();
        if (!Session::haveRight('profile', READ)) {
            return;
        }

        if (!isset($profile->fields['plugin_etiquetachamados_print'])) {
            $profile->getRights();
        }

        echo "<div class='spaced'>";

        $canEdit = Session::haveRight('profile', UPDATE);

        if ($canEdit) {
            // PADRÃO GLPI: Envia para o controlador CORE e passa o token nativo
            echo "<form method='post' action='" . $profile->getFormURL() . "'>";
            echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
        }

        $rights = [
            [
                'itemtype' => 'PluginEtiquetachamadosPrintjob',
                'label'    => __('Impressão de etiquetas', 'etiquetachamados'),
                'field'    => 'plugin_etiquetachamados_print',
            ],
            [
                'itemtype' => 'PluginEtiquetachamadosConfig',
                'label'    => __('Configuração de etiquetas', 'etiquetachamados'),
                'field'    => 'plugin_etiquetachamados_config',
            ],
        ];

        $matrixOptions = [
            'canedit' => $canEdit,
            'title'   => __('Etiqueta Chamados', 'etiquetachamados'),
        ];

        $profile->displayRightsChoiceMatrix($rights, $matrixOptions);

        if ($canEdit) {
            echo "<div class='text-center'>";
            echo Html::hidden('id', ['value' => $profilesId]);
            // Flag para o Hook identificar a nossa aba
            echo Html::hidden('update_plugin_etiquetachamados_profile', ['value' => 1]);
            echo Html::submit(_sx('button', 'Save'), ['name' => 'update']);
            echo "</div>\n";
            Html::closeForm();
        }

        echo '</div>';
    }
}
