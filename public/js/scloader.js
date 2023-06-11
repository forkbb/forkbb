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
        nameSelector = ".f-username",
        dataName = "data-SCEditorConfig",
        emotName = "data-smiliesEnabled",
        linkName = "data-linkEnabled",
        selector = "textarea[" + dataName + "]",
        textarea,
        elForScroll,
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
                'emoticon,date,time|maximize,source',
            colors: '#000000,#808080,#C0C0C0,#FFFFFF|#FF00FF,#800080,#FF0000,#800000|#FFFF00,#808000,#00FF00,#008000|#00FFFF,#008080,#0000FF,#000080'
        };

    function initEditor()
    {
        var conf, smiliesEnabled, linkEnabled;

        if (
            !sceditor
            || !(textarea = doc.querySelector(selector))
            || !(conf = JSON.parse(textarea.getAttribute(dataName)))
        ) {
            return;
        }

        options = Object.assign(options, conf);
        smiliesEnabled = '1' == textarea.getAttribute(emotName);
        linkEnabled = '1' == textarea.getAttribute(linkName);

        var forDelete = ['youtube', 'rtl', 'ltr'];

        if (!smiliesEnabled) {
            forDelete = forDelete.concat('emoticon');
        }
        if (!linkEnabled) {
            forDelete = forDelete.concat('url', 'link', 'image', 'img', 'email');
        }

        for (var bbcodeForDelete of forDelete) {
            sceditor.command.remove(bbcodeForDelete);
            sceditor.formats.bbcode.remove(bbcodeForDelete);

            options.toolbar = options.toolbar.replace(new RegExp("\\b" + bbcodeForDelete + "\\b", "gi"), '');
        }

        options.toolbar = options.toolbar.replace(/[^\w]*\|[^\w]*/g, '|').replace(/,{2,}/g, ',');

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

        elForScroll = textarea.parentNode;
        var users = doc.querySelectorAll(nameSelector);

        for (var node of users) {
            var a = doc.createElement("a");
            a.textContent = "@";
            a.addEventListener('click', function (e) {
                instance.insert("[b]" + e.target.parentNode.textContent + "[/b], ");
                elForScroll.scrollIntoView({behavior: "smooth", block: "end"});
            });
            node.insertBefore(a, node.firstChild);
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
