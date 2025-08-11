jQuery(document).ready(function($) {
    // Variables globales para el proceso
    let processing = false;
    let totalImages = 0;
    let processedCount = 0;
    let offset = 0;
    let batchSize = 5; // Número de imágenes a procesar por lote AJAX
    let ajaxNonce = mri_bulk_params.nonce;
    let ajaxUrl = mri_bulk_params.ajax_url;
    let criteria = 'all'; // 'all' or 'missing_alt'
    let request = null; // Para poder abortar la petición AJAX si se detiene

    // Elementos del DOM
    const $startButton = $('#mri-start-processing');
    const $stopButton = $('#mri-stop-processing');
    const $spinner = $('#mri-bulk-spinner');
    const $progressWrap = $('#mri-bulk-progress');
    const $progressBar = $('#mri-progress-bar');
    const $progressText = $('#mri-progress-text');
    const $logWrap = $('#mri-bulk-log');
    const $logList = $('#mri-log-list');
    const $criteriaCheckbox = $('#mri-criteria');

    // --- Funciones ---

    function updateProgress() {
        if (totalImages > 0) {
            const percentage = Math.min(100, Math.round((processedCount / totalImages) * 100));
            $progressBar.val(percentage);
            $progressText.text(`${processedCount} / ${totalImages} (${percentage}%)`);
        } else {
            $progressBar.val(0);
            $progressText.text('0 / 0');
        }
         $progressWrap.show();
    }

    function addLog(message, type = 'info') {
        // Limitar tamaño del log para no sobrecargar el navegador
        if ($logList.children().length > 200) {
            $logList.children().slice(0, 50).remove(); // Eliminar los 50 más antiguos
        }
         const itemClass = type ? `mri-log-${type}` : '';
        $logList.append(`<li class="${itemClass}">${message}</li>`);
        // Auto-scroll al final
        $logWrap.scrollTop($logWrap[0].scrollHeight);
         $logWrap.show();
    }

    function finishProcessing(errorOccurred = false) {
        processing = false;
        if (request) {
            request.abort(); // Abortar petición AJAX si está en curso
            request = null;
        }
        $startButton.text(mri_bulk_params.text_start).prop('disabled', false);
        $stopButton.hide();
        $spinner.removeClass('is-active');
        if (errorOccurred) {
            addLog(mri_bulk_params.text_error, 'error');
        } else {
             addLog(mri_bulk_params.text_complete, 'success');
             updateProgress(); // Asegurar que la barra muestra 100% si todo fue bien
        }

    }

    function processBatch() {
        if (!processing) {
            return; // Detenido por el usuario
        }

        if (offset >= totalImages) {
            finishProcessing();
            return; // Completado
        }

         addLog(`Enviando lote... Offset: ${offset}, Tamaño: ${batchSize}`);

        request = $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: mri_bulk_params.action_batch,
                nonce: ajaxNonce,
                offset: offset,
                batchSize: batchSize,
                criteria: criteria
            },
            dataType: 'json',
            beforeSend: function() {
                $spinner.addClass('is-active');
            }
        });

        request.done(function(response) {
            request = null; // Limpiar referencia
            $spinner.removeClass('is-active');

            if (!processing) {
                 addLog(mri_bulk_params.text_stopping, 'notice'); // Loguear que se detuvo si fue el caso
                return; // Salir si se detuvo mientras la petición estaba en curso
            }

            if (response.success) {
                const batchProcessed = response.data.processedCount || 0;
                processedCount += batchProcessed;
                offset += batchSize; // Avanzar offset por el tamaño del lote solicitado

                if (response.data.logMessages && Array.isArray(response.data.logMessages)) {
                    response.data.logMessages.forEach(msg => addLog(msg));
                } else {
                     addLog(`Lote procesado. ${batchProcessed} imágenes manejadas.`);
                }

                updateProgress();

                // Si se procesaron 0 en este lote, pero no hemos llegado al total,
                // podría significar que no hay más que cumplan el criterio. Finalizar.
                if (batchProcessed === 0 && offset < totalImages) {
                     addLog('No se procesaron imágenes en el último lote y aún no se alcanza el total. Posiblemente no hay más imágenes que cumplan el criterio. Finalizando.', 'notice');
                     finishProcessing();
                     return;
                }

                // Continuar con el siguiente lote tras una pequeña pausa
                setTimeout(processBatch, 500); // 0.5 segundos de pausa

            } else {
                // Error devuelto por PHP (ej. nonce inválido, error interno)
                addLog('Error recibido del servidor: ' + (response.data?.message || 'Error desconocido'), 'error');
                finishProcessing(true);
            }
        });

        request.fail(function(jqXHR, textStatus, errorThrown) {
            request = null; // Limpiar referencia
             $spinner.removeClass('is-active');
            if (textStatus === 'abort') {
                 addLog('Procesamiento detenido por el usuario.', 'notice');
                 finishProcessing(); // No marcar como error si fue abortado
            } else {
                addLog(`Error AJAX: ${textStatus} - ${errorThrown}`, 'error');
                if (jqXHR.responseText) {
                    addLog('Respuesta del servidor: ' + jqXHR.responseText.substring(0, 500) + '...', 'error');
                }
                finishProcessing(true);
            }
        });
    }

    function startProcessing() {
        if (processing) {
            return; // Ya está en proceso
        }

        processing = true;
        totalImages = 0;
        processedCount = 0;
        offset = 0;
        criteria = $criteriaCheckbox.is(':checked') ? 'missing_alt' : 'all';

        $startButton.text(mri_bulk_params.text_processing).prop('disabled', true);
        $stopButton.show();
        $spinner.addClass('is-active');
        $logList.empty(); // Limpiar log anterior
        $logWrap.hide();
        $progressWrap.hide();
        addLog('Iniciando proceso...');
        addLog(`Criterio seleccionado: ${criteria === 'missing_alt' ? 'Solo imágenes sin Alt Text' : 'Todas las imágenes'}`);

        // 1. Obtener el total de imágenes
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: mri_bulk_params.action_total,
                nonce: ajaxNonce,
                criteria: criteria
            },
            dataType: 'json'
        })
        .done(function(response) {
            if (response.success) {
                totalImages = parseInt(response.data.total, 10) || 0;
                addLog(`Total de imágenes a procesar: ${totalImages}`);
                if (totalImages > 0) {
                    updateProgress();
                    // 2. Iniciar el primer lote
                    processBatch();
                } else {
                    addLog('No se encontraron imágenes que cumplan el criterio.', 'notice');
                    finishProcessing();
                }
            } else {
                addLog('Error al obtener el total de imágenes: ' + (response.data?.message || 'Error desconocido'), 'error');
                finishProcessing(true);
            }
        })
        .fail(function(jqXHR, textStatus, errorThrown) {
            addLog(`Error AJAX al obtener el total: ${textStatus} - ${errorThrown}`, 'error');
            finishProcessing(true);
        });
    }

    // --- Event Handlers ---
    $startButton.on('click', startProcessing);

    $stopButton.on('click', function() {
        if (processing) {
            if (confirm(mri_bulk_params.text_confirm_stop)) {
                processing = false; // Señal para detener el bucle
                $stopButton.prop('disabled', true).text(mri_bulk_params.text_stopping);
                if (request) {
                    request.abort(); // Intentar abortar la petición actual
                } else {
                     // Si no hay request activo, forzar la finalización visualmente
                     finishProcessing();
                     $stopButton.prop('disabled', false).text(mri_bulk_params.text_stop).hide(); // Resetear botón stop
                }
                 addLog('Detención solicitada por el usuario...', 'notice');
            }
        }
    });

});