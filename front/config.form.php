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

include(__DIR__ . '/../../../inc/includes.php');

$config = new PluginEtiquetachamadosConfig();

if (isset($_POST["add"])) {
    $config->check(-1, CREATE, $_POST);
    if ($config->add($_POST)) {
        Html::back();
    }
} else if (isset($_POST["update"])) {
    $config->check($_POST['id'], UPDATE);
    if ($config->update($_POST)) {
        Html::back();
    }
}

Session::checkRight(PluginEtiquetachamadosConfig::$rightname, UPDATE);

// To be available when plugin is not activated
Plugin::load('etiquetachamados');

Html::header(
    __('Etiqueta Chamados - Configuração', 'etiquetachamados'),
    $_SERVER['PHP_SELF'],
    'config',
    'plugins'
);

echo "<div class='center'>";
echo "<h2>" . __('Etiqueta Chamados', 'etiquetachamados') . "</h2>";
echo "<p>" . __('A configuração deste plugin é feita diretamente nas entidades.', 'etiquetachamados') . "</p>";
echo "<p>" . __('Acesse: Administração > Entidades > [Entidade] > Aba "Etiqueta Chamados"', 'etiquetachamados') . "</p>";
echo "<a href='" . Entity::getSearchURL() . "' class='btn btn-primary'>";
echo "<i class='fas fa-sitemap me-1'></i> " . __('Ir para Entidades', 'etiquetachamados');
echo "</a>";
echo "</div>";

Html::footer();
