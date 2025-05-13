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
            format: "bbcode",
            icons: "monocons",
            style: "",
            emoticonsCompat: true,
            emoticonsEnabled: false,
            resizeWidth: false,
            width: "100%",
            toolbar: "bold,italic,underline,strike,subscript,superscript|" +
                "left,center,right,justify|h1,h2,h3|font,size,color,removeformat|" +
                "bulletlist,orderedlist,indent,outdent|" +
                "table|code,mono,quote|horizontalrule,image,email,link,unlink|" +
                "emoticon,date,time|maximize,source"
        };

    function initEditor()
    {
        var conf, smiliesEnabled, linkEnabled;

        if (
            !Object.assign
            || !sceditor
            || !(textarea = doc.querySelector(selector))
            || !(conf = JSON.parse(textarea.getAttribute(dataName)))
        ) {
            return;
        }

        options = Object.assign(options, conf);
        smiliesEnabled = "1" == textarea.getAttribute(emotName);
        linkEnabled = "1" == textarea.getAttribute(linkName);

        var forDelete = ["youtube", "rtl", "ltr"];

        if (!smiliesEnabled) {
            forDelete = forDelete.concat("emoticon");
        }
        if (!linkEnabled) {
            forDelete = forDelete.concat("url", "link", "image", "img", "email");
        }

        for (var bbcodeForDelete of forDelete) {
            sceditor.command.remove(bbcodeForDelete);
            sceditor.formats.bbcode.remove(bbcodeForDelete);

            options.toolbar = options.toolbar.replace(new RegExp("\\b" + bbcodeForDelete + "\\b", "gi"), "");
        }

        options.toolbar = options.toolbar.replace(/[^\w]*\|[^\w]*/g, "|").replace(/,{2,}/g, ",");

        sceditor.create(textarea, options);
        instance = sceditor.instance(textarea);

        instance.height(instance.height() - instance.getContentAreaContainer().offsetHeight + 250);

        if (smiliesEnabled) {
            var checkbox = doc.querySelector("input[name=\"hide_smilies\"]");

            if (checkbox) {
                checkbox.addEventListener("change", function (e) {
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
            a.addEventListener("click", function (e) {
                instance.insert("[b]" + e.target.parentNode.textContent + "[/b], ");
                elForScroll.scrollIntoView({behavior: "smooth", block: "end"});
            });
            node.insertBefore(a, node.firstChild);
        }

        if (textarea.form && textarea.form.elements.username) {
            initField(textarea.form.elements.username);

            if (textarea.form.elements.email) {
                initField(textarea.form.elements.email);
            }
        }
    }

    function initField(node)
    {
        var item = "guest_form_" + node.name,
            old = localStorage.getItem(item) || "";

        if (node.value == "") {
            node.value = old;
        }

        node.addEventListener("change", (function (node, item) {
            return function () {
                localStorage.setItem(item, node.value);
            }
        })(node, item));
    }

    return {
        init : function () {
            initEditor();
        },
        getInstance : function () {
            return instance;
        }
    };
}(document, window));

document.addEventListener("DOMContentLoaded", ForkBB.editor.init, false);
