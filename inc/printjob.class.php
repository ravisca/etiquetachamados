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
 * PluginEtiquetachamadosPrintjob
 *
 * Manages the async print queue. When a user clicks "Print Label",
 * a job is created with the rendered ZPL. A CronTask processes
 * pending jobs and sends ZPL directly to the Zebra printer via
 * raw TCP socket (port 9100 by default).
 */
class PluginEtiquetachamadosPrintjob extends CommonDBTM
{
    public static $rightname = 'plugin_etiquetachamados_print';

    // Job status constants
    const STATUS_PENDING    = 0;
    const STATUS_PROCESSING = 1;
    const STATUS_DONE       = 2;
    const STATUS_ERROR      = 3;

    /**
     * Get the table name.
     *
     * @return string
     */
    public static function getTable($classname = null)
    {
        return 'glpi_plugin_etiquetachamados_printjobs';
    }

    /**
     * Get type name for display.
     *
     * @param int $nb
     * @return string
     */
    public static function getTypeName($nb = 0)
    {
        return _n('Job de Impressão', 'Jobs de Impressão', $nb, 'etiquetachamados');
    }

    /**
     * Create a print job from a Ticket.
     *
     * Loads ticket data, resolves entity config (with recursion),
     * renders the ZPL template, and inserts a pending job.
     *
     * @param int $ticketId
     * @return array JSON-compatible result array
     */
    public static function createFromTicket(int $ticketId): array
    {
        // Validate ticket
        $ticket = new Ticket();
        if (!$ticket->getFromDB($ticketId)) {
            return [
                'success'    => false,
                'statusCode' => 404,
                'error'      => 'NOT_FOUND',
                'message'    => __('Chamado não encontrado.', 'etiquetachamados'),
            ];
        }

        // Check right
        if (!Session::haveRight('plugin_etiquetachamados_print', READ)) {
            return [
                'success'    => false,
                'statusCode' => 403,
                'error'      => 'FORBIDDEN',
                'message'    => __('Você não tem permissão para imprimir etiquetas.', 'etiquetachamados'),
            ];
        }

        // Get effective config for the ticket's entity
        $entityId = $ticket->fields['entities_id'] ?? 0;
        $config = PluginEtiquetachamadosConfig::getEffectiveConfig($entityId);

        if ($config === null || empty($config['printer_ip'])) {
            return [
                'success'    => false,
                'statusCode' => 422,
                'error'      => 'NO_CONFIG',
                'message'    => __('Nenhuma configuração de impressora encontrada para esta entidade.', 'etiquetachamados'),
            ];
        }

        if (!$config['is_active']) {
            return [
                'success'    => false,
                'statusCode' => 422,
                'error'      => 'INACTIVE',
                'message'    => __('A impressão de etiquetas está desativada para esta entidade.', 'etiquetachamados'),
            ];
        }

        // Render ZPL
        $zplContent = self::renderZpl($config['zpl_template'], $ticket);
        if (empty($zplContent)) {
            return [
                'success'    => false,
                'statusCode' => 422,
                'error'      => 'EMPTY_ZPL',
                'message'    => __('O template ZPL está vazio ou inválido.', 'etiquetachamados'),
            ];
        }

        // Insert print job
        $printjob = new self();
        $jobId = $printjob->add([
            'tickets_id'    => $ticketId,
            'entities_id'   => $entityId,
            'users_id'      => Session::getLoginUserID(),
            'status'        => self::STATUS_PENDING,
            'zpl_content'   => $zplContent,
            'date_creation' => date('Y-m-d H:i:s'),
            'date_mod'      => date('Y-m-d H:i:s'),
        ]);

        if (!$jobId) {
            Toolbox::logInFile(
                'etiquetachamados',
                "Erro ao criar job de impressão para ticket #{$ticketId}\n"
            );
            return [
                'success'    => false,
                'statusCode' => 500,
                'error'      => 'DB_ERROR',
                'message'    => __('Erro ao enfileirar o job de impressão.', 'etiquetachamados'),
            ];
        }

        Toolbox::logInFile(
            'etiquetachamados',
            "Job #{$jobId} criado para ticket #{$ticketId} (entidade #{$entityId})\n"
        );

        // ---------------------------------------------------------------
        // Tentativa imediata de impressão (fire-and-try)
        // Se falhar, o job permanece PENDING para o CronTask retentar.
        // ---------------------------------------------------------------
        $sendResult = self::sendToPrinter(
            $config['printer_ip'],
            $config['printer_port'] ?? 9100,
            $zplContent
        );

        if ($sendResult['success']) {
            $printjob->update([
                'id'       => $jobId,
                'status'   => self::STATUS_DONE,
                'date_mod' => date('Y-m-d H:i:s'),
            ]);
            Toolbox::logInFile(
                'etiquetachamados',
                "Job #{$jobId}: Impressão imediata enviada com sucesso para {$config['printer_ip']}:{$config['printer_port']}\n"
            );

            return [
                'success'    => true,
                'statusCode' => 201,
                'message'    => __('Etiqueta impressa com sucesso!', 'etiquetachamados'),
                'job_id'     => $jobId,
                'printed'    => true,
            ];
        }

        // Falhou — fica na fila para o cron retentar
        Toolbox::logInFile(
            'etiquetachamados',
            "Job #{$jobId}: Tentativa imediata falhou ({$sendResult['error']}). Ficará na fila para retentativa.\n"
        );

        return [
            'success'    => true,
            'statusCode' => 201,
            'message'    => __('Não foi possível imprimir agora. A etiqueta será retentada automaticamente.', 'etiquetachamados'),
            'job_id'     => $jobId,
            'printed'    => false,
        ];
    }

    /**
     * Render a ZPL template with ticket data.
     *
     * Replaces placeholders like {{ticket_id}}, {{ticket_title}}, etc.
     *
     * @param string $template
     * @param Ticket $ticket
     * @return string
     */
    public static function renderZpl(string $template, Ticket $ticket): string
    {
        if (empty($template)) {
            return '';
        }

        $ticketId    = $ticket->getID();
        $ticketTitle = $ticket->fields['name'] ?? '';
        $ticketDate  = $ticket->fields['date'] ?? '';
        $ticketContent = strip_tags($ticket->fields['content'] ?? '');

        // Truncate content if too long for a label
        if (mb_strlen($ticketContent) > 200) {
            $ticketContent = mb_substr($ticketContent, 0, 200) . '...';
        }

        // Get entity name
        $entityName = '';
        $entity = new Entity();
        if ($entity->getFromDB($ticket->fields['entities_id'] ?? 0)) {
            $entityName = $entity->fields['name'] ?? '';
        }

        // Get requester name (first requester user)
        $requesterName = '';
        global $DB;
        $userIterator = $DB->request([
            'FROM'  => 'glpi_tickets_users',
            'WHERE' => [
                'tickets_id' => $ticketId,
                'type'       => CommonITILActor::REQUESTER,
            ],
            'LIMIT' => 1,
        ]);

        if ($row = $userIterator->current()) {
            $user = new User();
            if ($user->getFromDB($row['users_id'])) {
                $requesterName = $user->getFriendlyName();
            }
        }

        // Format date for label
        $formattedDate = '';
        if (!empty($ticketDate)) {
            try {
                $dt = new DateTime($ticketDate);
                $formattedDate = $dt->format('d/m/Y H:i');
            } catch (Exception $e) {
                $formattedDate = $ticketDate;
            }
        }

        // Build ticket URL from GLPI base URL
        global $CFG_GLPI;
        $ticketUrl = ($CFG_GLPI['url_base'] ?? '') . '/front/ticket.form.php?id=' . $ticketId;

        // Replace placeholders
        $replacements = [
            '{{ticket_id}}'      => $ticketId,
            '{{ticket_title}}'   => $ticketTitle,
            '{{ticket_date}}'    => $formattedDate,
            '{{entity_name}}'    => $entityName,
            '{{requester_name}}' => $requesterName,
            '{{ticket_content}}' => $ticketContent,
            '{{ticket_url}}'     => $ticketUrl,
        ];

        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $template
        );
    }

    // ---------------------------------------------------------------
    // CronTask methods
    // ---------------------------------------------------------------

    /**
     * Provide information about the cron task.
     *
     * @return array
     */
    public static function cronInfo($name)
    {
        switch ($name) {
            case 'EtiquetaPrint':
                return [
                    'description' => __('Processa a fila de impressão de etiquetas (envio direto à impressora Zebra)', 'etiquetachamados'),
                ];
        }
        return [];
    }

    /**
     * Execute the cron task: process pending print jobs.
     *
     * @param CronTask $task
     * @return int Number of jobs processed (0 = nothing to do, >0 = actions taken)
     */
    public static function cronEtiquetaPrint(CronTask $task): int
    {
        global $DB;

        $maxJobs = $task->fields['param'] ?? 10;
        $processed = 0;

        // Fetch pending jobs
        $iterator = $DB->request([
            'FROM'    => self::getTable(),
            'WHERE'   => ['status' => self::STATUS_PENDING],
            'ORDER'   => ['date_creation ASC'],
            'LIMIT'   => $maxJobs,
        ]);

        foreach ($iterator as $row) {
            $jobId = $row['id'];
            $printjob = new self();

            // Mark as processing
            $printjob->update([
                'id'       => $jobId,
                'status'   => self::STATUS_PROCESSING,
                'date_mod' => date('Y-m-d H:i:s'),
            ]);

            // Get config for the entity
            $config = PluginEtiquetachamadosConfig::getEffectiveConfig($row['entities_id']);

            if ($config === null || empty($config['printer_ip'])) {
                $printjob->update([
                    'id'            => $jobId,
                    'status'        => self::STATUS_ERROR,
                    'error_message' => 'Configuração de impressora não encontrada para a entidade.',
                    'date_mod'      => date('Y-m-d H:i:s'),
                ]);
                Toolbox::logInFile(
                    'etiquetachamados',
                    "Job #{$jobId}: Sem configuração de impressora para entidade #{$row['entities_id']}\n"
                );
                $processed++;
                continue;
            }

            // Send ZPL to printer
            $result = self::sendToPrinter(
                $config['printer_ip'],
                $config['printer_port'] ?? 9100,
                $row['zpl_content']
            );

            if ($result['success']) {
                $printjob->update([
                    'id'       => $jobId,
                    'status'   => self::STATUS_DONE,
                    'date_mod' => date('Y-m-d H:i:s'),
                ]);
                Toolbox::logInFile(
                    'etiquetachamados',
                    "Job #{$jobId}: Enviado com sucesso para {$config['printer_ip']}:{$config['printer_port']}\n"
                );
                $task->addVolume(1);
            } else {
                $printjob->update([
                    'id'            => $jobId,
                    'status'        => self::STATUS_ERROR,
                    'error_message' => $result['error'],
                    'date_mod'      => date('Y-m-d H:i:s'),
                ]);
                Toolbox::logInFile(
                    'etiquetachamados',
                    "Job #{$jobId}: ERRO - {$result['error']}\n"
                );
            }

            $processed++;
        }

        return $processed;
    }

    /**
     * Send raw ZPL data to a printer via TCP socket.
     *
     * @param string $ip   Printer IP or hostname
     * @param int    $port Printer port (default 9100)
     * @param string $zpl  ZPL content to send
     * @return array ['success' => bool, 'error' => string|null]
     */
    public static function sendToPrinter(string $ip, int $port, string $zpl): array
    {
        $timeout = 10; // seconds

        try {
            $errno  = 0;
            $errstr = '';
            $socket = @fsockopen($ip, $port, $errno, $errstr, $timeout);

            if ($socket === false) {
                $error = "Não foi possível conectar à impressora {$ip}:{$port} - [{$errno}] {$errstr}";
                Toolbox::logInFile('etiquetachamados', $error . "\n");
                return [
                    'success' => false,
                    'error'   => $error,
                ];
            }

            // Set stream timeout for write operations
            stream_set_timeout($socket, $timeout);

            $bytesWritten = fwrite($socket, $zpl);
            fclose($socket);

            if ($bytesWritten === false || $bytesWritten === 0) {
                $error = "Falha ao enviar dados ZPL para {$ip}:{$port}";
                Toolbox::logInFile('etiquetachamados', $error . "\n");
                return [
                    'success' => false,
                    'error'   => $error,
                ];
            }

            return [
                'success' => true,
                'error'   => null,
            ];
        } catch (Exception $e) {
            $error = "Exceção ao comunicar com {$ip}:{$port}: " . $e->getMessage();
            Toolbox::logInFile('etiquetachamados', $error . "\n");
            return [
                'success' => false,
                'error'   => $error,
            ];
        }
    }

    /**
     * Get rights array for the rights management matrix.
     *
     * @return array
     */
    public function getRights($interface = 'central')
    {
        return [
            READ => __('Imprimir etiquetas', 'etiquetachamados'),
        ];
    }
}
