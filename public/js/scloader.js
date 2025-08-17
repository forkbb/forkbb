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
        quoteSelector = ".f-postquote",
        postSelector = "article",
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
                "table|code,mono,quote,hide|horizontalrule,image,email,link,unlink|" +
                "emoticon,date,time|maximize,source"
        };

    function getHTMLOfSelection() {
        var selection = window.getSelection();

        if (selection.rangeCount > 0) {
            var range = selection.getRangeAt(0),
                container = document.createElement('div');

            container.appendChild(range.cloneContents());

            return container.innerHTML;
        } else {
            return '';
        }
    }

    function quoteSelected(e)
    {
        var selection = getHTMLOfSelection();

        if (selection) {
            var user = e.target.closest(postSelector).querySelectorAll(nameSelector)[0],
                name = user.textContent,
                mode = instance.inSourceMode();

            if (user.innerHTML.indexOf("<a>@</a>") > -1 && name.indexOf("@") == 0) {
                name = name.slice(1);
            }

            selection = "<blockquote><cite>" + name + "</cite>" + selection + "</blockquote>";

            if (mode) {
                instance.sourceMode(false);
            } else { // перевод курсора в конец поля sceditor O_o
                instance.sourceMode(true);
                instance.sourceMode(false);
            }

            instance.wysiwygEditorInsertHtml(selection);

            if (mode) {
                instance.sourceMode(true);
            }

            elForScroll.scrollIntoView({behavior: "smooth", block: "end"});
            e.preventDefault();
        }
    }

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

        var quotes = doc.querySelectorAll(quoteSelector);

        for (var node of quotes) {
            node.addEventListener("click", function (e) {
                quoteSelected(e);
            });
        }

        if (textarea.form && textarea.form.elements.username) {
            initField(textarea.form.elements.username);

            if (textarea.form.elements.email) {
                initField(textarea.form.elements.email);
            }
        }

        if (textarea.form && (node = textarea.form.querySelector('input[type=file]'))) {
            node.addEventListener("change", function (e) {
                var target = e.target;

                if (target.files.length > 0) {
                    var formData = new FormData();

                    for (var i = 0; i < target.files.length; i++) {
                        if (node.form.children.MAX_FILE_SIZE && node.form.children.MAX_FILE_SIZE.value < target.files[i].size) {
                            alert(target.files[i].name + " file too large");

                            return;
                        }

                        formData.append("files[]", target.files[i]);
                    }

                    fetch(target.getAttribute("data-d"), {
                        method: "POST",
                        body: formData,
                    }).then(function (response) {
                        if (!response.ok) {
                            throw new Error("HTTP Error: " + response.status);
                        }

                        return response.json();
                    }).then(function (data) {
                        if (data.error) {
                            throw new Error(data.error);
                        }

//                        console.log(data);
                        instance.insert("", data.text);
                    }).catch(function (e) {
                        alert(e);
                    });
                }

                target.value = '';
            });
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
