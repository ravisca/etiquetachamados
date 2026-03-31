/**
 * Etiqueta Chamados - JavaScript
 *
 * Handles the print button click in the Ticket form.
 * Sends an AJAX POST to enqueue the print job and provides
 * visual feedback to the user.
 *
 * @copyright Copyright (C) 2026 by RBX Soluções & Tech.
 * @license   GPLv2
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        // Use event delegation since the button is injected dynamically
        document.addEventListener('click', function (event) {
            var btn = event.target.closest('#etiquetachamados-print-btn');
            if (!btn) {
                return;
            }

            event.preventDefault();

            var ticketId = btn.getAttribute('data-ticket-id');
            var ajaxUrl = btn.getAttribute('data-ajax-url');
            var statusEl = document.getElementById('etiquetachamados-print-status');

            if (!ticketId || !ajaxUrl) {
                return;
            }

            // Disable button and show loading
            btn.disabled = true;
            btn.classList.add('etiquetachamados-loading');
            var originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Enviando...';

            if (statusEl) {
                statusEl.innerHTML = '';
                statusEl.className = 'ms-2';
            }

            // Build form data with CSRF token
            var formData = new FormData();
            formData.append('ticket_id', ticketId);

            // Get CSRF token from the page
            var csrfInput = document.querySelector('input[name="_glpi_csrf_token"]');
            if (csrfInput) {
                formData.append('_glpi_csrf_token', csrfInput.value);
            }

            // Send AJAX request
            fetch(ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (data) {
                    if (data.success) {
                        btn.classList.remove('etiquetachamados-loading');

                        if (data.printed) {
                            // Imprimiu na hora — feedback verde
                            btn.innerHTML = '<i class="fas fa-check me-1"></i> Impresso!';
                            btn.classList.remove('btn-outline-primary');
                            btn.classList.add('btn-success');

                            if (statusEl) {
                                statusEl.innerHTML =
                                    '<span class="text-success"><i class="fas fa-check-circle"></i> ' +
                                    (data.message || 'Etiqueta impressa com sucesso!') +
                                    '</span>';
                            }
                        } else {
                            // Não imprimiu agora, ficou na fila — feedback amarelo
                            btn.innerHTML = '<i class="fas fa-clock me-1"></i> Na fila';
                            btn.classList.remove('btn-outline-primary');
                            btn.classList.add('btn-warning');

                            if (statusEl) {
                                statusEl.innerHTML =
                                    '<span class="text-warning"><i class="fas fa-exclamation-triangle"></i> ' +
                                    (data.message || 'Etiqueta na fila de retentativa.') +
                                    '</span>';
                            }
                        }

                        // Reset button after 4 seconds
                        setTimeout(function () {
                            btn.innerHTML = originalHtml;
                            btn.classList.remove('btn-success', 'btn-warning');
                            btn.classList.add('btn-outline-primary');
                            btn.disabled = false;
                        }, 4000);
                    } else {
                        // Error feedback
                        btn.innerHTML = originalHtml;
                        btn.classList.remove('etiquetachamados-loading');
                        btn.disabled = false;

                        if (statusEl) {
                            statusEl.innerHTML =
                                '<span class="text-danger"><i class="fas fa-exclamation-circle"></i> ' +
                                (data.message || 'Erro ao enviar etiqueta.') +
                                '</span>';
                        }
                    }
                })
                .catch(function (error) {
                    // Network error
                    btn.innerHTML = originalHtml;
                    btn.classList.remove('etiquetachamados-loading');
                    btn.disabled = false;

                    if (statusEl) {
                        statusEl.innerHTML =
                            '<span class="text-danger"><i class="fas fa-exclamation-triangle"></i> Erro de rede. Tente novamente.</span>';
                    }

                    console.error('Etiqueta Chamados - Erro:', error);
                });
        });
    });
})();
