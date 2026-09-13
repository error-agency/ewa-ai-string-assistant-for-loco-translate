/* global ewaAdmin, jQuery */
(function ($) {
    'use strict';

    // ─── Provider Tabs ──────────────────────────────────────────────────────
    $('.ewa-provider-tab input[type=radio]').on('change', function () {
        $('.ewa-provider-tab').removeClass('active');
        $(this).closest('.ewa-provider-tab').addClass('active');

        const provider = $(this).val();

        if (provider === 'ollama') {
            $('.ewa-row-apikey').hide();
        } else {
            $('.ewa-row-apikey').show();
        }
    });

    // ─── Endpoint Presets ───────────────────────────────────────────────────
    $('.ewa-preset').on('click', function () {
        $('#ewa-api-endpoint').val($(this).data('value'));
    });

    // ─── Default Prompt Preview ─────────────────────────────────────────────
    $('#ewa-show-default-prompt').on('click', function () {
        const $btn = $(this);
        const $pre = $('#ewa-default-prompt-preview');
        $pre.slideToggle(200, function () {
            $btn.text($pre.is(':visible') ? 'Hide default prompt' : 'View default prompt');
        });
    });

    // ─── Load Models ────────────────────────────────────────────────────────
    $('#ewa-fetch-models').on('click', function () {
        const $btn = $(this);
        const $select = $('#ewa-model-select');
        const $input = $('#ewa-model-input');

        const provider    = $('.ewa-provider-tab input[type=radio]:checked').val() || 'openrouter';
        const apiEndpoint = String($('#ewa-api-endpoint').val() || '').trim();
        const apiKey      = String($('input[name="ewa_settings[api_key]"]').val() || '').trim();

        $btn.text('Loading…').prop('disabled', true);

        $.post(ewaAdmin.ajaxUrl, {
            action: 'ewa_fetch_models',
            nonce: ewaAdmin.nonce,
            provider: provider,
            api_endpoint: apiEndpoint,
            api_key: apiKey,
        }, function (res) {
            $btn.html('<span class="dashicons dashicons-update"></span> Load Models').prop('disabled', false);

            if (!res.success) {
                alert('Error: ' + (res.data?.message || 'Unknown error'));
                return;
            }

            const models = res.data.models;
            $select.empty().append('<option value="">— choose a model —</option>');

            models.forEach(function (m) {
                let label = m.id;
                if (m.name && m.name !== m.id) label += ' — ' + m.name;
                if (m.context) label += ' (' + Math.round(m.context / 1000) + 'k ctx)';
                $select.append($('<option>').val(m.id).text(label));
            });

            $select.val($input.val());
            $select.show();

            $select.off('change').on('change', function () {
                $input.val($(this).val());
            });
        }).fail(function () {
            $btn.html('<span class="dashicons dashicons-update"></span> Load Models').prop('disabled', false);
            alert('Network error while loading models.');
        });
    });

    // ─── Test Connection ────────────────────────────────────────────────────
    $('#ewa-test-connection').on('click', function () {
        const $btn = $(this);
        const $result = $('#ewa-test-result');

        const provider    = $('.ewa-provider-tab input[type=radio]:checked').val() || 'openrouter';
        const apiEndpoint = String($('#ewa-api-endpoint').val() || '').trim();
        const apiKey      = String($('input[name="ewa_settings[api_key]"]').val() || '').trim();
        const model       = String($('#ewa-model-input').val() || '').trim();

        $btn.html('<span class="dashicons dashicons-update ewa-spin"></span> Testing…').prop('disabled', true);
        $result.removeClass('ewa-test-ok ewa-test-err').empty();

        $.post(ewaAdmin.ajaxUrl, {
            action: 'ewa_test_connection',
            nonce: ewaAdmin.nonce,
            provider: provider,
            api_endpoint: apiEndpoint,
            api_key: apiKey,
            model: model,
        }, function (res) {
            $btn.html('<span class="dashicons dashicons-rest-api"></span> Test Connection').prop('disabled', false);

            if (res.success) {
                $result.addClass('ewa-test-ok')
                    .html('<span class="dashicons dashicons-yes-alt" style="vertical-align:text-bottom;font-size:16px;color:#00a32a;"></span> ' +
                        $('<div>').text(res.data.message + ' ("Hello" → "' + res.data.test_output + '")').html());
            } else {
                $result.addClass('ewa-test-err')
                    .html('<span class="dashicons dashicons-dismiss" style="vertical-align:text-bottom;font-size:16px;color:#d63638;"></span> ' +
                        $('<div>').text(res.data?.message || 'Unknown error').html());
            }
        }).fail(function () {
            $btn.html('<span class="dashicons dashicons-rest-api"></span> Test Connection').prop('disabled', false);
            $result.addClass('ewa-test-err')
                .html('<span class="dashicons dashicons-dismiss" style="vertical-align:text-bottom;font-size:16px;color:#d63638;"></span> Network error');
        });
    });

})(jQuery);
