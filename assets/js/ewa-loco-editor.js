/* global ewaasLoco, jQuery */
/* EWA AI String Assistant for Loco Translate */
(function ($) {
    'use strict';

    var config = (typeof ewaasLoco !== 'undefined') ? ewaasLoco : {};

    /* ═══════════════════════════════════════════════════════════════════
       LOCALE MAP
    ═══════════════════════════════════════════════════════════════════ */
    var LOCALE_MAP = {
        'af':'Afrikaans','ar':'Arabic','az':'Azerbaijani','be_BY':'Belarusian',
        'bg_BG':'Bulgarian','bn_BD':'Bengali','bs_BA':'Bosnian','ca':'Catalan',
        'cs_CZ':'Czech','cy':'Welsh','da_DK':'Danish','de_DE':'German',
        'de_AT':'German (Austria)','de_CH':'German (Switzerland)','el':'Greek',
        'eo':'Esperanto','es_ES':'Spanish','es_AR':'Spanish (Argentina)',
        'es_MX':'Spanish (Mexico)','et':'Estonian','eu':'Basque','fa_IR':'Persian',
        'fi':'Finnish','fr_FR':'French','fr_BE':'French (Belgium)',
        'fr_CA':'French (Canada)','gl_ES':'Galician','gu':'Gujarati',
        'he_IL':'Hebrew','hi_IN':'Hindi','hr':'Croatian','hu_HU':'Hungarian',
        'hy':'Armenian','id_ID':'Indonesian','is_IS':'Icelandic','it_IT':'Italian',
        'ja':'Japanese','ka_GE':'Georgian','kk':'Kazakh','km':'Khmer',
        'ko_KR':'Korean','lt_LT':'Lithuanian','lv':'Latvian','mk_MK':'Macedonian',
        'ml_IN':'Malayalam','mn':'Mongolian','mr':'Marathi','ms_MY':'Malay',
        'my_MM':'Burmese','nb_NO':'Norwegian','nl_NL':'Dutch','nl_BE':'Dutch (Belgium)',
        'nn_NO':'Norwegian (Nynorsk)','pa_IN':'Punjabi','pl_PL':'Polish',
        'pt_BR':'Portuguese (Brazil)','pt_PT':'Portuguese','ro_RO':'Romanian',
        'ru_RU':'Russian','sk_SK':'Slovak','sl_SI':'Slovenian','sq':'Albanian',
        'sr_RS':'Serbian','sv_SE':'Swedish','sw':'Swahili','ta_IN':'Tamil',
        'te':'Telugu','th':'Thai','tl':'Filipino','tr_TR':'Turkish',
        'uk':'Ukrainian','ur':'Urdu','uz_UZ':'Uzbek','vi':'Vietnamese',
        'zh_CN':'Chinese (Simplified)','zh_TW':'Chinese (Traditional)',
        'zh_HK':'Chinese (Hong Kong)',
    };

    function localeToName(code) {
        if (!code) return '';
        if (LOCALE_MAP[code]) return LOCALE_MAP[code];
        var keys = Object.keys(LOCALE_MAP);
        for (var i = 0; i < keys.length; i++) {
            if (keys[i].split('_')[0] === code) return LOCALE_MAP[keys[i]];
        }
        return code;
    }

    /* ═══════════════════════════════════════════════════════════════════
       PATH DETECTION
    ═══════════════════════════════════════════════════════════════════ */
    function detectPoPath() {
        if (config.poPath && config.poPath.length > 4) return config.poPath;
        var selectors = ['input[name="path"]','input[name="po-path"]',
                         'input[name="file"]','form[data-path]','[data-path]'];
        for (var i = 0; i < selectors.length; i++) {
            var $el = $(selectors[i]).first();
            var v   = $el.val ? $el.val() : $el.attr('data-path');
            if (v && v.indexOf('.po') !== -1) return v;
        }
        var urlPath = new URLSearchParams(window.location.search).get('path') || '';
        if (urlPath && urlPath.indexOf('.po') !== -1) return urlPath;
        return '';
    }

    function detectLocale(poPath) {
        if (config.detectedLocale) return config.detectedLocale;
        var src = [poPath, window.location.search, document.title,
                   $('h1,h2,.loco-nav,.loco-title,.loco-lang').text()].join(' ');
        var m = src.match(/[-_]([a-z]{2,3}_[A-Z]{2,3})(?:\.po)?/);
        return m ? m[1] : '';
    }

    /* ═══════════════════════════════════════════════════════════════════
       STATE
    ═══════════════════════════════════════════════════════════════════ */
    var running         = false;
    var currentJobId    = '';
    var cancelPending   = false;
    var _xhrRef         = null;

    var stats = {
        totalOriginal    : 0,
        translated       : 0,
        skipped          : 0,
        batchCount       : 0,
        tokensPrompt     : 0,
        tokensCompletion : 0,
        tokensTotal      : 0,
        jobStartMs       : 0,
        batchLog         : [],
    };

    /* ═══════════════════════════════════════════════════════════════════
       UNLOAD GUARD
    ═══════════════════════════════════════════════════════════════════ */
    function installUnloadGuard() {
        $(window).on('beforeunload.ewa', function () {
            if (!running) return;
            if (currentJobId && navigator.sendBeacon) {
                var fd = new FormData();
                fd.append('action',  'ewaas_cancel_job');
                fd.append('nonce',   config.nonce);
                fd.append('job_id',  currentJobId);
                navigator.sendBeacon(config.ajaxUrl, fd);
            }
            return (config.i18n && config.i18n.leaveConfirm) ? config.i18n.leaveConfirm : 'Translation is in progress. Are you sure you want to leave?';
        });
    }
    function removeUnloadGuard() { $(window).off('beforeunload.ewa'); }

    /* ═══════════════════════════════════════════════════════════════════
       PANEL INJECTION
    ═══════════════════════════════════════════════════════════════════ */
    var injected = false;

    function tryInject() {
        if (injected || $('#ewa-panel').length) { injected = true; return true; }
        var anchors = [
            '.loco-toolbar','#loco-toolbar','div.loco-toolbar','nav.loco-toolbar',
            '[class*="loco-toolbar"]','#loco-editor','.loco-editor',
            'table#loco-entries','table.loco-table','.loco-wrap > form','.loco-wrap',
            '#wpbody-content .wrap > h2','#wpbody-content .wrap',
        ];
        var $anchor = null;
        for (var i = 0; i < anchors.length; i++) {
            var $el = $(anchors[i]).first();
            if ($el.length) { $anchor = $el; break; }
        }
        if (!$anchor) return false;

        var $panel = buildPanel();
        var tag    = ($anchor.prop('tagName') || '').toLowerCase();
        if (tag === 'table' || $anchor.attr('id') === 'loco-editor' || $anchor.hasClass('loco-editor')) {
            $anchor.before($panel);
        } else {
            $anchor.after($panel);
        }
        injected = true;
        wirePanel();
        return true;
    }

    $(document).ready(function () { tryInject(); });
    $(window).on('load', function () { tryInject(); });
    var _attempts = 0;
    var _poller = setInterval(function () {
        _attempts++;
        if (tryInject() || _attempts >= 20) clearInterval(_poller);
    }, 500);
    if (typeof MutationObserver !== 'undefined') {
        var _mo = new MutationObserver(function () { if (tryInject()) _mo.disconnect(); });
        _mo.observe(document.body, { childList: true, subtree: true });
        setTimeout(function () { _mo.disconnect(); }, 30000);
    }

    /* ═══════════════════════════════════════════════════════════════════
       BUILD PANEL
    ═══════════════════════════════════════════════════════════════════ */
    function buildPanel() {
        var poPath   = detectPoPath();
        var locale   = detectLocale(poPath);
        var langName = localeToName(locale);

        var $langSelect = $('<select>', { id: 'ewa-lang-select', class: 'ewa-lang-select' });
        var entries = [];
        for (var code in LOCALE_MAP) { entries.push([code, LOCALE_MAP[code]]); }
        entries.sort(function (a, b) { return a[1].localeCompare(b[1]); });
        entries.forEach(function (pair) {
            var $opt = $('<option>', { value: pair[1], text: pair[1] + ' (' + pair[0] + ')' });
            if (pair[0] === locale || pair[1] === langName) $opt.prop('selected', true);
            $langSelect.append($opt);
        });
        if (!$langSelect.val()) $langSelect.val('Bulgarian');

        var $btn = $('<button>', {
            id: 'ewa-ai-btn', type: 'button',
            class: 'button button-primary ewa-ai-btn',
        }).append(
            $('<span>', { class: 'dashicons dashicons-translation', style: 'color:#ffffff !important;font-size:16px;width:16px;height:16px;line-height:16px;vertical-align:middle;margin-right:4px;display:inline-block;' }),
            $('<span>', { text: config.i18n.btnTranslate })
        );
        var $stopBtn = $('<button>', {
            id: 'ewa-stop-btn', type: 'button',
            class: 'button ewa-stop-btn',
        }).append(
            $('<span>', { class: 'dashicons dashicons-controls-pause', style: 'color:#d63638 !important;font-size:16px;width:16px;height:16px;line-height:16px;vertical-align:middle;margin-right:4px;display:inline-block;' }),
            $('<span>', { text: config.i18n.btnStop })
        ).hide();

        var $badge    = $('<span>', { class: 'ewa-model-badge',
            text: config.provider + ' · ' + config.model });
        var $pathInfo = poPath ? $('<span>', {
            class: 'ewa-path-info', text: basename(poPath), title: poPath }) : null;

        var $pathRow = $('<div>', { id: 'ewa-path-row', class: 'ewa-path-row' }).hide();
        if (!poPath) {
            $pathRow.append(
                $('<span>', { class: 'dashicons dashicons-category', style: 'color:#2271b1 !important;vertical-align:middle;margin-right:4px;font-size:16px;width:16px;height:16px;display:inline-block;' }),
                $('<span>', { text: (config.i18n && config.i18n.enterPoPath) ? config.i18n.enterPoPath : 'Enter .po path: ' }),
                $('<input>', { type:'text', id:'ewa-manual-path',
                    class:'regular-text ewa-manual-path',
                    placeholder: (config.i18n && config.i18n.pathPlaceholder) ? config.i18n.pathPlaceholder : 'Absolute path or relative to wp-content…' }),
                ' ',
                $('<button>', { type:'button', class:'button ewa-path-verify-btn', text: (config.i18n && config.i18n.verify) ? config.i18n.verify : 'Verify' }),
                $('<span>', { id:'ewa-path-verify-result', style:'margin-left:8px;font-size:12px;' })
            ).show();
        }

        var $fill    = $('<div>', { id:'ewa-progress-fill', class:'ewa-progress-bar-fill' });
        var $pct     = $('<span>', { id:'ewa-progress-pct', class:'ewa-progress-pct', text:'0%' });
        var $cnt     = $('<span>', { id:'ewa-progress-cnt', class:'ewa-progress-cnt' });
        var $eta     = $('<span>', { id:'ewa-progress-eta', class:'ewa-progress-eta' });
        var $prog    = $('<div>', { id:'ewa-progress-wrap', class:'ewa-editor-progress' })
            .append($('<div>', { class:'ewa-progress-bar-track' }).append($fill))
            .append($pct, $cnt, $eta)
            .hide();

        var $ticker = $('<div>', { id:'ewa-ticker', class:'ewa-ticker' }).hide();

        var $log = $('<div>', { id:'ewa-batch-log', class:'ewa-batch-log' }).hide().append(
            $('<div>', { class:'ewa-log-header' }).append(
                $('<span>', { text: (config.i18n && config.i18n.batchHeader) ? config.i18n.batchHeader : 'Batch' }),
                $('<span>', { text: (config.i18n && config.i18n.stringsHeader) ? config.i18n.stringsHeader : 'Strings' }),
                $('<span>', { text: (config.i18n && config.i18n.timeHeader) ? config.i18n.timeHeader : 'Time' }),
                $('<span>', { text: (config.i18n && config.i18n.tokensHeader) ? config.i18n.tokensHeader : 'Tokens (in/out)' }),
                $('<span>', { text: (config.i18n && config.i18n.previewHeader) ? config.i18n.previewHeader : 'Preview' })
            ),
            $('<div>', { id:'ewa-log-rows', class:'ewa-log-rows' })
        );

        var $summary = $('<div>', { id:'ewa-summary', class:'ewa-summary' }).hide();
        var $notices = $('<div>', { id:'ewa-editor-notices' });

        return $('<div>', { id:'ewa-panel', class:'ewa-editor-panel' }).append(
            $('<div>', { class:'ewa-panel-controls' }).append(
                $('<span>', { class:'dashicons dashicons-translation ewa-panel-icon', style:'color:#2271b1 !important;font-size:20px;width:20px;height:20px;line-height:20px;vertical-align:middle;margin-right:6px;display:inline-block;' }),
                $('<span>', { class:'ewa-panel-label', text: (config.i18n && config.i18n.translateTo) ? config.i18n.translateTo : 'Translate to:' }),
                $langSelect, $btn, $stopBtn, $badge, $pathInfo
            ),
            $pathRow, $prog, $ticker, $log, $summary, $notices
        );
    }

    function basename(p) { return p ? p.replace(/\\/g,'/').split('/').pop() : ''; }

    /* ═══════════════════════════════════════════════════════════════════
       WIRE EVENTS
    ═══════════════════════════════════════════════════════════════════ */
    function wirePanel() {
        $(document).on('click', '.ewa-path-verify-btn', function () {
            var path = $('#ewa-manual-path').val().trim();
            var $res = $('#ewa-path-verify-result');
            if (!path) return;
            $res.text((config.i18n && config.i18n.checking) ? config.i18n.checking : 'Checking…').css('color','#787878');
            $.post(config.ajaxUrl, {
                action:'ewaas_get_po_info', nonce:config.nonce, po_path:path,
            }, function (res) {
                if (res.success) {
                    $res.html('<span class="dashicons dashicons-yes-alt" style="color:#00a32a;font-size:16px;vertical-align:text-bottom;"></span> ' + res.data.untranslated + ' ' + ((config.i18n && config.i18n.untranslated) ? config.i18n.untranslated : 'untranslated')).css('color','#00a32a');
                } else {
                    $res.html('<span class="dashicons dashicons-dismiss" style="color:#d63638;font-size:16px;vertical-align:text-bottom;"></span> ' + escHtml(res.data ? res.data.message : ((config.i18n && config.i18n.error) ? config.i18n.error : 'Error'))).css('color','#d63638');
                }
            }).fail(function () { $res.html('<span class="dashicons dashicons-dismiss" style="color:#d63638;font-size:16px;vertical-align:text-bottom;"></span> ' + ((config.i18n && config.i18n.networkError) ? config.i18n.networkError : 'Network error')).css('color','#d63638'); });
        });

        $('#ewa-ai-btn').on('click', function () {
            if (running) return;
            var poPath = detectPoPath() || $('#ewa-manual-path').val().trim();
            startTranslation(poPath, $('#ewa-lang-select').val());
        });

        $('#ewa-stop-btn').on('click', function () {
            if (!running || !currentJobId) return;
            cancelPending = true;
            $(this).prop('disabled', true).text((config.i18n && config.i18n.stopping) ? config.i18n.stopping : 'Stopping…');
            if (_xhrRef) { _xhrRef.abort(); _xhrRef = null; }
            $.post(config.ajaxUrl, {
                action:'ewaas_cancel_job', nonce:config.nonce, job_id:currentJobId,
            });
            showNotice((config.i18n && config.i18n.stopSignalSent) ? config.i18n.stopSignalSent : 'Stop signal sent — current batch will finish, then stop.', 'info');
        });
    }

    /* ═══════════════════════════════════════════════════════════════════
       TRANSLATION FLOW
    ═══════════════════════════════════════════════════════════════════ */
    function generateJobId() {
        return 'ewaas_' + Date.now() + '_' + Math.floor(Math.random() * 9999);
    }

    function startTranslation(poPath, targetLang) {
        if (!poPath) {
            showNotice((config.i18n && config.i18n.couldNotDetectPath) ? config.i18n.couldNotDetectPath : 'Could not detect the .po file path. Enter it manually above.', 'error', true);
            $('#ewa-path-row').show();
            return;
        }
        showNotice((config.i18n && config.i18n.checkingFile) ? config.i18n.checkingFile : 'Checking file…', 'info');
        $('#ewa-ai-btn').prop('disabled', true);

        $.post(config.ajaxUrl, {
            action:'ewaas_get_po_info', nonce:config.nonce, po_path:poPath,
        }, function (res) {
            $('#ewa-ai-btn').prop('disabled', false);
            clearNotice();

            if (!res.success) {
                showNotice(res.data ? res.data.message : ((config.i18n && config.i18n.unknownError) ? config.i18n.unknownError : 'Unknown error'), 'error', true);
                return;
            }

            var info = res.data;
            if (info.untranslated === 0) {
                showNotice((config.i18n && config.i18n.allStringsTranslated) ? config.i18n.allStringsTranslated : 'All strings are already translated.', 'success');
                return;
            }

            if (!confirm(
                'Translate ' + info.untranslated + ' untranslated string' +
                (info.untranslated !== 1 ? 's' : '') +
                ' out of ' + info.total_entries + ' total?\n\n' +
                'Language : ' + targetLang + '\n' +
                'Model    : ' + config.model + '\n' +
                'File     : ' + basename(poPath) + '\n\n' +
                'Important: Please ensure you have a backup of this file before continuing.\n' +
                'The file will be saved automatically after each batch.'
            )) return;

            stats = {
                totalOriginal    : info.untranslated,
                translated       : 0,
                skipped          : 0,
                batchCount       : 0,
                tokensPrompt     : 0,
                tokensCompletion : 0,
                tokensTotal      : 0,
                jobStartMs       : Date.now(),
                batchLog         : [],
            };

            running       = true;
            cancelPending = false;
            currentJobId  = generateJobId();

            setUiRunning(true);
            updateProgress(0, 0, info.untranslated);
            $('#ewa-batch-log').show();
            $('#ewa-summary').hide().empty();
            installUnloadGuard();

            doBatch(poPath, targetLang, info.untranslated);

        }).fail(function () {
            $('#ewa-ai-btn').prop('disabled', false);
            showNotice('✗ ' + ((config.i18n && config.i18n.networkErrorChecking) ? config.i18n.networkErrorChecking : 'Network error while checking file.'), 'error', true);
        });
    }

    function doBatch(poPath, targetLang, totalOriginal) {
        if (cancelPending) { finishJob(false); return; }

        var batchStartMs = Date.now();
        var requestId    = 'req_' + Date.now() + '_' + Math.floor(Math.random() * 99999);

        _xhrRef = $.post(config.ajaxUrl, {
            action          : 'ewaas_translate_file',
            nonce           : config.nonce,
            po_path         : poPath,
            target_lang     : targetLang,
            batch_index     : stats.batchCount,
            total_original  : totalOriginal,
            job_id          : currentJobId,
            request_id      : requestId,
        }, function (res) {
            _xhrRef = null;
            var batchMs = Date.now() - batchStartMs;

            if (!res.success) {
                running = false;
                setUiRunning(false);
                removeUnloadGuard();
                showNotice('✗ ' + ((config.i18n && config.i18n.errorPrefix) ? config.i18n.errorPrefix : 'Error:') + ' ' + (res.data ? res.data.message : ((config.i18n && config.i18n.unknownError) ? config.i18n.unknownError : 'Unknown error')), 'error', true);
                return;
            }

            var d = res.data;

            stats.batchCount++;
            stats.translated       = d.translated  || 0;
            stats.skipped          = d.skipped     || 0;
            stats.tokensPrompt    += (d.tokens_prompt     || 0);
            stats.tokensCompletion+= (d.tokens_completion || 0);
            stats.tokensTotal     += (d.tokens_total      || 0);

            updateProgress(d.percent, d.translated, d.total);
            updateTicker(d.batch_preview || []);
            addBatchLogRow(
                stats.batchCount,
                d.batch_count   || 0,
                batchMs,
                d.tokens_prompt || 0,
                d.tokens_completion || 0,
                d.batch_preview || []
            );
            updateSummaryLive();

            if (d.save_warning) {
                showNotice('⚠ ' + ((config.i18n && config.i18n.warningPrefix) ? config.i18n.warningPrefix : 'Warning:') + ' ' + d.save_warning, 'warning');
            }

            if (d.cancelled) { finishJob(false); return; }
            if (d.done)      { finishJob(true);  return; }

            doBatch(poPath, targetLang, totalOriginal);

        }).fail(function (xhr) {
            _xhrRef = null;
            if (xhr.statusText === 'abort') { finishJob(false); return; }

            if (!doBatch._netRetries) doBatch._netRetries = {};
            var key = stats.batchCount;
            doBatch._netRetries[key] = (doBatch._netRetries[key] || 0) + 1;

            if (doBatch._netRetries[key] <= 2) {
                showNotice('⚠ ' + ((config.i18n && config.i18n.networkErrorRetrying) ? config.i18n.networkErrorRetrying : 'Network error — retrying in 3 s…'), 'warning');
                setTimeout(function () {
                    doBatch(poPath, targetLang, totalOriginal);
                }, 3000);
            } else {
                running = false;
                setUiRunning(false);
                removeUnloadGuard();
                showNotice('✗ ' + ((config.i18n && config.i18n.networkErrorStopped) ? config.i18n.networkErrorStopped : 'Network error after retries — translation stopped.'), 'error', true);
            }
        });
    }

    function finishJob(completed) {
        running = false;
        setUiRunning(false);
        removeUnloadGuard();
        hideTicker();

        var elapsed = Math.round((Date.now() - stats.jobStartMs) / 1000);
        var elStr   = elapsed >= 60
            ? Math.floor(elapsed/60) + 'm ' + (elapsed%60) + 's'
            : elapsed + 's';

        var msg;
        var noticeType = 'success';
        var strStopped     = (config.i18n && config.i18n.stoppedNotice) ? config.i18n.stoppedNotice : 'Stopped.';
        var strSavedSoFar  = (config.i18n && config.i18n.stringsSavedSoFar) ? config.i18n.stringsSavedSoFar : 'saved so far.';
        var strCompletedErr= (config.i18n && config.i18n.completedWithErrors) ? config.i18n.completedWithErrors : 'Completed with errors.';
        var strDone        = (config.i18n && config.i18n.done) ? config.i18n.done : 'Done!';
        var strStrings     = (config.i18n && config.i18n.stringsCount) ? config.i18n.stringsCount : 'strings';
        var strTranslated  = (config.i18n && config.i18n.translatedCount) ? config.i18n.translatedCount : 'translated';
        var strSkipped     = (config.i18n && config.i18n.skippedCount) ? config.i18n.skippedCount : 'skipped/failed';
        var strIn          = (config.i18n && config.i18n.inTime) ? config.i18n.inTime : 'in';

        if (!completed) {
            msg = '⏹ ' + strStopped + ' <strong>' + stats.translated + ' ' + strStrings + '</strong> ' + strSavedSoFar;
            noticeType = 'warning';
        } else {
            if (stats.skipped > 0) {
                msg = '⚠ ' + strCompletedErr + ' <strong>' + stats.translated + ' ' + strStrings + '</strong> ' + strTranslated + ', <strong>' +
                      stats.skipped + ' ' + strSkipped + '</strong> ' + strIn + ' ' + elStr + '.';
                noticeType = 'warning';
            } else {
                msg = '✓ ' + strDone + ' <strong>' + stats.translated + ' ' + strStrings + '</strong> ' + strTranslated + ' ' + strIn + ' ' + elStr + '.';
                noticeType = 'success';
            }
        }

        showNotice(msg, noticeType, true);
        showFinalSummary(completed, elStr);

        var $r = $('<button>', {
            type:'button', class:'button button-small ewa-reload-btn', text: (config.i18n && config.i18n.reloadEditor) ? config.i18n.reloadEditor : '↻ Reload editor',
        }).on('click', function () { removeUnloadGuard(); window.location.reload(); });
        $('#ewa-editor-notices .ewa-editor-notice').append(' ', $r);
    }

    function updateProgress(pct, done, total) {
        if (pct !== null && pct !== undefined) {
            $('#ewa-progress-fill').css('width', pct + '%');
            $('#ewa-progress-pct').text(pct + '%');
        }
        if (done !== undefined && total !== undefined) {
            $('#ewa-progress-cnt').text(' — ' + done + ' / ' + total + ' ' + ((config.i18n && config.i18n.stringsCount) ? config.i18n.stringsCount : 'strings'));
        }
        if (done > 0 && total > 0 && stats.jobStartMs) {
            var elapsed  = (Date.now() - stats.jobStartMs) / 1000;
            var rate     = done / elapsed;
            var remaining = total - done;
            var etaSec   = rate > 0 ? Math.round(remaining / rate) : 0;
            var etaPrefix = (config.i18n && config.i18n.eta) ? config.i18n.eta : 'ETA ~';
            var etaStr   = etaSec > 60
                ? etaPrefix + Math.floor(etaSec/60) + 'm ' + (etaSec%60) + 's'
                : (etaSec > 0 ? etaPrefix + etaSec + 's' : '');
            $('#ewa-progress-eta').text(etaStr ? ' · ' + etaStr : '');
        }
    }

    function updateTicker(strings) {
        if (!strings || !strings.length) return;
        var $t = $('#ewa-ticker');
        var parts = strings.map(function (s) {
            var short = s.length > 55 ? s.substring(0, 55) + '…' : s;
            return '<span class="ewa-ticker-item">' + escHtml(short) + '</span>';
        });
        var tickerLabel = (config.i18n && config.i18n.translatingTicker) ? config.i18n.translatingTicker : 'Translating:';
        $t.html(
            '<span class="ewa-ticker-label">' + escHtml(tickerLabel) + '</span> ' +
            parts.join('<span class="ewa-ticker-sep"> · </span>')
        ).show();
    }

    function hideTicker() { $('#ewa-ticker').hide().empty(); }

    function addBatchLogRow(batchNum, count, ms, tIn, tOut, preview) {
        var timeStr   = ms >= 1000 ? (ms/1000).toFixed(1) + 's' : ms + 'ms';
        var tokenStr  = tIn || tOut ? (tIn + ' / ' + tOut) : '—';
        var previewStr = (preview || []).map(function (s) {
            return s.length > 30 ? s.substring(0, 30) + '…' : s;
        }).join(', ') || '—';

        var $row = $('<div>', { class: 'ewa-log-row' }).append(
            $('<span>', { class: 'ewa-log-batch',   text: '#' + batchNum }),
            $('<span>', { class: 'ewa-log-count',   text: count + ' str' }),
            $('<span>', { class: 'ewa-log-time',    text: timeStr }),
            $('<span>', { class: 'ewa-log-tokens',  text: tokenStr }),
            $('<span>', { class: 'ewa-log-preview', text: previewStr })
        );

        var $rows = $('#ewa-log-rows');
        $rows.prepend($row);
        $rows.find('.ewa-log-row').slice(20).remove();
    }

    function updateSummaryLive() {
        var elapsed = Math.round((Date.now() - stats.jobStartMs) / 1000);
        var $s = $('#ewa-summary').show();
        var strTranslated = (config.i18n && config.i18n.translatedCount) ? config.i18n.translatedCount : 'translated';
        var strSkipped    = (config.i18n && config.i18n.skippedCount) ? config.i18n.skippedCount : 'skipped';
        var strTokens     = (config.i18n && config.i18n.tokensCount) ? config.i18n.tokensCount : 'tokens';
        var strBatches    = (config.i18n && config.i18n.batchesCount) ? config.i18n.batchesCount : 'batches';
        $s.html(
            '<span><span class="dashicons dashicons-clock" style="vertical-align:text-bottom;font-size:16px;"></span> ' + fmtTime(elapsed) + '</span>' +
            '<span><span class="dashicons dashicons-yes-alt" style="vertical-align:text-bottom;font-size:16px;color:#00a32a;"></span> ' + stats.translated + ' ' + strTranslated + '</span>' +
            (stats.skipped > 0 ? '<span><span class="dashicons dashicons-warning" style="vertical-align:text-bottom;font-size:16px;color:#dba617;"></span> ' + stats.skipped + ' ' + strSkipped + '</span>' : '') +
            '<span><span class="dashicons dashicons-chart-bar" style="vertical-align:text-bottom;font-size:16px;"></span> ' + stats.tokensTotal + ' ' + strTokens + '</span>' +
            '<span><span class="dashicons dashicons-database" style="vertical-align:text-bottom;font-size:16px;"></span> ' + stats.batchCount + ' ' + strBatches + '</span>'
        );
    }

    function showFinalSummary(completed, elStr) {
        var $s = $('#ewa-summary').show();
        var strCompWarn = (config.i18n && config.i18n.completedWithWarnings) ? config.i18n.completedWithWarnings : 'Completed with warnings';
        var strComplete = (config.i18n && config.i18n.complete) ? config.i18n.complete : 'Complete';
        var strStopped  = (config.i18n && config.i18n.stoppedNotice) ? config.i18n.stoppedNotice : 'Stopped';

        var statusLabel = completed
            ? (stats.skipped > 0
                ? '<span class="dashicons dashicons-warning" style="vertical-align:text-bottom;font-size:16px;color:#dba617;"></span> ' + strCompWarn
                : '<span class="dashicons dashicons-yes-alt" style="vertical-align:text-bottom;font-size:16px;color:#00a32a;"></span> ' + strComplete)
            : '<span class="dashicons dashicons-controls-pause" style="vertical-align:text-bottom;font-size:16px;color:#d63638;"></span> ' + strStopped;

        var strFinalTrans = (config.i18n && config.i18n.translatedCount) ? config.i18n.translatedCount : 'translated';
        var strFinalSkip  = (config.i18n && config.i18n.skippedCount) ? config.i18n.skippedCount : 'skipped';
        var strFinalTok   = (config.i18n && config.i18n.tokensCount) ? config.i18n.tokensCount : 'tokens';
        var strFinalBatch = (config.i18n && config.i18n.batchesCount) ? config.i18n.batchesCount : 'batches';
        $s.html(
            '<strong>' + statusLabel + '</strong>' +
            '<span><span class="dashicons dashicons-clock" style="vertical-align:text-bottom;font-size:16px;"></span> ' + elStr + '</span>' +
            '<span><span class="dashicons dashicons-yes-alt" style="vertical-align:text-bottom;font-size:16px;color:#00a32a;"></span> ' + stats.translated + ' ' + strFinalTrans + '</span>' +
            (stats.skipped > 0 ? '<span><span class="dashicons dashicons-warning" style="vertical-align:text-bottom;font-size:16px;color:#dba617;"></span> ' + stats.skipped + ' ' + strFinalSkip + '</span>' : '') +
            '<span><span class="dashicons dashicons-chart-bar" style="vertical-align:text-bottom;font-size:16px;"></span> ' + stats.tokensTotal + ' ' + strFinalTok + '</span>' +
            '<span><span class="dashicons dashicons-arrow-up-alt" style="vertical-align:text-bottom;font-size:14px;"></span> ' + stats.tokensPrompt + ' / <span class="dashicons dashicons-arrow-down-alt" style="vertical-align:text-bottom;font-size:14px;"></span> ' + stats.tokensCompletion + '</span>' +
            '<span><span class="dashicons dashicons-database" style="vertical-align:text-bottom;font-size:16px;"></span> ' + stats.batchCount + ' ' + strFinalBatch + '</span>'
        );
    }

    function setUiRunning(on) {
        var $btn  = $('#ewa-ai-btn');
        var $stop = $('#ewa-stop-btn');
        var $prog = $('#ewa-progress-wrap');
        if (on) {
            $btn.prop('disabled', true).addClass('ewa-btn-busy').html('<span class="dashicons dashicons-update ewa-spin" style="color:#ffffff !important;font-size:16px;width:16px;height:16px;line-height:16px;vertical-align:middle;margin-right:4px;display:inline-block;"></span> ' + escHtml(config.i18n.translating));
            $stop.show().prop('disabled', false).html('<span class="dashicons dashicons-controls-pause" style="color:#ffffff !important;font-size:16px;width:16px;height:16px;line-height:16px;vertical-align:middle;margin-right:4px;display:inline-block;"></span> ' + escHtml(config.i18n.btnStop));
            $prog.show();
        } else {
            $btn.prop('disabled', false).removeClass('ewa-btn-busy').html('<span class="dashicons dashicons-translation" style="color:#ffffff !important;font-size:16px;width:16px;height:16px;line-height:16px;vertical-align:middle;margin-right:4px;display:inline-block;"></span> ' + escHtml(config.i18n.btnTranslate));
            $stop.hide().prop('disabled', false).html('<span class="dashicons dashicons-controls-pause" style="color:#d63638 !important;font-size:16px;width:16px;height:16px;line-height:16px;vertical-align:middle;margin-right:4px;display:inline-block;"></span> ' + escHtml(config.i18n.btnStop));
        }
    }

    function showNotice(message, type, persistent) {
        var iconClass = 'dashicons-info';
        if (type === 'success') iconClass = 'dashicons-yes-alt';
        else if (type === 'error') iconClass = 'dashicons-dismiss';
        else if (type === 'warning') iconClass = 'dashicons-warning';

        var $n = $('<div>', { class:'ewa-editor-notice ewa-notice-' + type })
            .append($('<span>', { class:'dashicons ' + iconClass, style:'vertical-align:text-bottom;margin-right:6px;font-size:16px;' }))
            .append($('<span>').html(message));
        $('#ewa-editor-notices').empty().append($n);
        if (!persistent) setTimeout(function () { $n.fadeOut(400, function () { $n.remove(); }); }, 7000);
    }

    function clearNotice() { $('#ewa-editor-notices').empty(); }

    function fmtTime(sec) {
        return sec >= 60 ? Math.floor(sec/60) + 'm ' + (sec%60) + 's' : sec + 's';
    }

    function escHtml(s) {
        return $('<div>').text(s).html();
    }

})(jQuery);
