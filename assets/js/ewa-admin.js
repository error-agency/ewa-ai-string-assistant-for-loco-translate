/* global ewaasAdmin, jQuery */
(function ($) {
    'use strict';

    var config = (typeof ewaasAdmin !== 'undefined') ? ewaasAdmin : {};

    // ─── AI Transport Mode Toggle ──────────────────────────────────────────
    $('input[name="ewaas_settings[ai_transport]"]').on('change', function () {
        var transport = $(this).val();
        if (transport === 'wordpress') {
            $('#ewa-card-wp-ai-client').slideDown(200);
            $('#ewa-direct-settings-container').slideUp(200);
        } else {
            $('#ewa-card-wp-ai-client').slideUp(200);
            $('#ewa-direct-settings-container').slideDown(200);
        }
    });

    // ─── Check WordPress AI Client Status ───────────────────────────────────
    $('#ewa-check-wp-ai-status').on('click', function () {
        var $btn         = $(this);
        var $result      = $('#ewa-wp-ai-check-result');
        var checkingText = (config.i18n && config.i18n.checkingStatus) ? config.i18n.checkingStatus : 'Checking…';
        var checkText    = (config.i18n && config.i18n.checkStatus) ? config.i18n.checkStatus : 'Check AI Availability';
        var networkErr   = (config.i18n && config.i18n.networkError) ? config.i18n.networkError : 'Network error';

        $btn.prop('disabled', true).text(checkingText);
        $result.text('').css('color', '');

        $.post(config.ajaxUrl, {
            action: 'ewaas_check_ai_status',
            nonce: config.nonce
        }, function (res) {
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-update" style="vertical-align:text-bottom;"></span> ' + checkText);
            if (res.success) {
                $result.css('color', '#00a32a').text(res.data && res.data.message ? res.data.message : 'Ready');
            } else {
                $result.css('color', '#d63638').text(res.data && res.data.message ? res.data.message : 'Unavailable');
            }
        }).fail(function () {
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-update" style="vertical-align:text-bottom;"></span> ' + checkText);
            $result.css('color', '#d63638').text(networkErr);
        });
    });

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
            var hideText = (config.i18n && config.i18n.hideDefaultPrompt) ? config.i18n.hideDefaultPrompt : 'Hide default prompt';
            var viewText = (config.i18n && config.i18n.viewDefaultPrompt) ? config.i18n.viewDefaultPrompt : 'View default prompt';
            $btn.text($pre.is(':visible') ? hideText : viewText);
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

        var loadingText    = (config.i18n && config.i18n.loading) ? config.i18n.loading : 'Loading…';
        var loadModelsText = (config.i18n && config.i18n.loadModels) ? config.i18n.loadModels : 'Load Models';
        var chooseText     = (config.i18n && config.i18n.chooseModel) ? config.i18n.chooseModel : '— choose a model —';
        var unknownText    = (config.i18n && config.i18n.unknownError) ? config.i18n.unknownError : 'Unknown error';
        var errorPrefix    = (config.i18n && config.i18n.errorPrefix) ? config.i18n.errorPrefix : 'Error:';

        $btn.text(loadingText).prop('disabled', true);

        $.post(config.ajaxUrl, {
            action: 'ewaas_fetch_models',
            nonce: config.nonce,
            provider: provider,
            api_endpoint: apiEndpoint,
            api_key: apiKey,
        }, function (res) {
            $btn.html('<span class="dashicons dashicons-update"></span> ' + loadModelsText).prop('disabled', false);

            if (!res.success) {
                alert(errorPrefix + ' ' + (res.data ? res.data.message : unknownText));
                return;
            }

            var models = res.data.models;
            $select.empty().append($('<option>').val('').text(chooseText));

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
            $btn.html('<span class="dashicons dashicons-update"></span> ' + loadModelsText).prop('disabled', false);
            var netErr = (config.i18n && config.i18n.networkErrorModels) ? config.i18n.networkErrorModels : 'Network error while loading models.';
            alert(netErr);
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

        var testingText        = (config.i18n && config.i18n.testing) ? config.i18n.testing : 'Testing…';
        var testConnectionText = (config.i18n && config.i18n.testConnection) ? config.i18n.testConnection : 'Test Connection';
        var unknownText        = (config.i18n && config.i18n.unknownError) ? config.i18n.unknownError : 'Unknown error';
        var networkErrText     = (config.i18n && config.i18n.networkError) ? config.i18n.networkError : 'Network error';

        $btn.html('<span class="dashicons dashicons-update ewa-spin"></span> ' + testingText).prop('disabled', true);
        $result.removeClass('ewa-test-ok ewa-test-err').empty();

        $.post(config.ajaxUrl, {
            action: 'ewaas_test_connection',
            nonce: config.nonce,
            provider: provider,
            api_endpoint: apiEndpoint,
            api_key: apiKey,
            model: model,
        }, function (res) {
            $btn.html('<span class="dashicons dashicons-rest-api"></span> ' + testConnectionText).prop('disabled', false);

            if (res.success) {
                $result.addClass('ewa-test-ok')
                    .html('<span class="dashicons dashicons-yes-alt" style="vertical-align:text-bottom;font-size:16px;color:#00a32a;"></span> ' +
                        $('<div>').text(res.data.message + ' ("Hello" → "' + res.data.test_output + '")').html());
            } else {
                $result.addClass('ewa-test-err')
                    .html('<span class="dashicons dashicons-dismiss" style="vertical-align:text-bottom;font-size:16px;color:#d63638;"></span> ' +
                        $('<div>').text(res.data ? res.data.message : unknownText).html());
            }
        }).fail(function () {
            $btn.html('<span class="dashicons dashicons-rest-api"></span> ' + testConnectionText).prop('disabled', false);
            $result.addClass('ewa-test-err')
                .html('<span class="dashicons dashicons-dismiss" style="vertical-align:text-bottom;font-size:16px;color:#d63638;"></span> ' + networkErrText);
        });
    });

})(jQuery);
