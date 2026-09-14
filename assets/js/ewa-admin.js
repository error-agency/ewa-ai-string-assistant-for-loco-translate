/* global ewaasAdmin, jQuery */
(function ($) {
    'use strict';

    var config = (typeof ewaasAdmin !== 'undefined') ? ewaasAdmin : {};

    // ─── Provider Tabs ──────────────────────────────────────────────────────
    $('.ewa-provider-tab input[type=radio]').on('change', function () {
        $('.ewa-provider-tab').removeClass('active');
        $(this).closest('.ewa-provider-tab').addClass('active');

        var provider = $(this).val();

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
        var $btn = $(this);
        var $pre = $('#ewa-default-prompt-preview');
        $pre.slideToggle(200, function () {
            $btn.text($pre.is(':visible') ? 'Hide default prompt' : 'View default prompt');
        });
    });

    // ─── Load Models ────────────────────────────────────────────────────────
    $('#ewa-fetch-models').on('click', function () {
        var $btn = $(this);
        var $select = $('#ewa-model-select');
        var $input = $('#ewa-model-input');

        var provider    = $('.ewa-provider-tab input[type=radio]:checked').val() || 'openrouter';
        var apiEndpoint = String($('#ewa-api-endpoint').val() || '').trim();
        var apiKey      = String($('input[name="ewaas_settings[api_key]"]').val() || '').trim();

        $btn.text('Loading…').prop('disabled', true);

        $.post(config.ajaxUrl, {
            action: 'ewaas_fetch_models',
            nonce: config.nonce,
            provider: provider,
            api_endpoint: apiEndpoint,
            api_key: apiKey,
        }, function (res) {
            $btn.html('<span class="dashicons dashicons-update"></span> Load Models').prop('disabled', false);

            if (!res.success) {
                alert('Error: ' + (res.data ? res.data.message : 'Unknown error'));
                return;
            }

            var models = res.data.models;
            $select.empty().append('<option value="">— choose a model —</option>');

            models.forEach(function (m) {
                var label = m.id;
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
        var $btn = $(this);
        var $result = $('#ewa-test-result');

        var provider    = $('.ewa-provider-tab input[type=radio]:checked').val() || 'openrouter';
        var apiEndpoint = String($('#ewa-api-endpoint').val() || '').trim();
        var apiKey      = String($('input[name="ewaas_settings[api_key]"]').val() || '').trim();
        var model       = String($('#ewa-model-input').val() || '').trim();

        $btn.html('<span class="dashicons dashicons-update ewa-spin"></span> Testing…').prop('disabled', true);
        $result.removeClass('ewa-test-ok ewa-test-err').empty();

        $.post(config.ajaxUrl, {
            action: 'ewaas_test_connection',
            nonce: config.nonce,
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
                        $('<div>').text(res.data ? res.data.message : 'Unknown error').html());
            }
        }).fail(function () {
            $btn.html('<span class="dashicons dashicons-rest-api"></span> Test Connection').prop('disabled', false);
            $result.addClass('ewa-test-err')
                .html('<span class="dashicons dashicons-dismiss" style="vertical-align:text-bottom;font-size:16px;color:#d63638;"></span> Network error');
        });
    });

})(jQuery);
