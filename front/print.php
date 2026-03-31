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

// Validate CSRF token
Session::checkLoginUser();

// Only accept POST + AJAX
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success'    => false,
        'statusCode' => 405,
        'error'      => 'METHOD_NOT_ALLOWED',
        'message'    => 'Somente requisições POST são aceitas.',
    ]);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

// CSRF is validated automatically by GLPI when inc/includes.php is loaded.

// Get ticket ID
$ticketId = intval($_POST['ticket_id'] ?? 0);

if ($ticketId <= 0) {
    http_response_code(400);
    echo json_encode([
        'success'    => false,
        'statusCode' => 400,
        'error'      => 'INVALID_INPUT',
        'message'    => 'ID do chamado inválido.',
    ]);
    exit;
}

// Create the print job
$result = PluginEtiquetachamadosPrintjob::createFromTicket($ticketId);

http_response_code($result['statusCode'] ?? 200);
echo json_encode($result);
exit;
