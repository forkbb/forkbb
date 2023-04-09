/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

if (typeof ForkBB === "undefined" || !ForkBB) {
    var ForkBB = {};
}

ForkBB.editor = (function (doc, win) {
    'use strict';

    var instance,
        dataName = "data-SCEditorConfig",
        emotName = "data-smiliesEnabled",
        selector = "textarea[" + dataName + "]",
        textarea,
        options = {
            format: 'bbcode',
            icons: 'monocons',
            style: '',
            emoticonsCompat: true,
            emoticonsEnabled: false,
            resizeWidth: false,
            width: '100%',
            toolbar: 'bold,italic,underline,strike,subscript,superscript|' +
                'left,center,right,justify|font,size,color,removeformat|' +
                'bulletlist,orderedlist,indent,outdent|' +
                'table|code,quote|horizontalrule,image,email,link,unlink|' +
                'emoticon,date,time|maximize,source'
        };

    function initEditor()
    {
        var conf, smiliesEnabled;

        if (
            !sceditor
            || !(textarea = doc.querySelector(selector))
            || !(conf = JSON.parse(textarea.getAttribute(dataName)))
        ) {
            return;
        }

        options = Object.assign(options, conf);
        smiliesEnabled = '1' == textarea.getAttribute(emotName);

        if (!smiliesEnabled) {
            options.toolbar = options.toolbar.replace(/\bemoticon\b/, '').replace(/[^\w]*\|[^\w]*/g, '|').replace(/,{2,}/g, ',') ;
        }

        sceditor.create(textarea, options);
        instance = sceditor.instance(textarea);

        if (smiliesEnabled) {
            var checkbox = doc.querySelector('input[name="hide_smilies"]');

            if (checkbox) {
                checkbox.addEventListener('change', function (e) {
                    instance.emoticons(!e.target.checked);
                });
                instance.emoticons(!checkbox.checked);
            } else {
                instance.emoticons(true);
            }
        }
    }

    return {
        init : function () {
            initEditor();
        },
    };
}(document, window));

if (document.addEventListener && Object.assign) {
    document.addEventListener("DOMContentLoaded", ForkBB.editor.init, false);
}
