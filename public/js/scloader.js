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
        blackName = "data-blackList",
        whiteName = "data-whiteList",
        selector = "textarea[" + dataName + "]",
        textarea,
        elForScroll,
        imgToEmot = {},
        options = {
            format: "bbcode",
            icons: "monocons",
            style: "",
            emoticonsCompat: true,
            emoticonsEnabled: false,
            emojis: ["😀","😃","😄","😁","😆","😅","🤣","😂","🙂","😉","😊","😇","🥰","😍","🤩","😘","😗","☺️","😚","😙","😏","😋","😛","😜","🤪","😝","🤑","🤗","🤭","🤫","🤔","🤤","🤠","🥳","😎","🤓","🧐","🙃","🤐","🤨","😐","😑","😶","😒","🙄","😬","🤥","😌","😔","😪","😴","😷","🤒","🤕","🤢","🤮","🤧","🥵","🥶","🥴","😵","🤯","🥱","😕","😟","🙁","☹️","😮","😯","😲","😳","🥺","😦","😧","😨","😰","😥","😢","😭","😱","😖","😣","😞","😓","😩","😫","😤","😡","😠","🤬","😈","👿","💀","☠️","💩","🤡","👹","👺","👻","👽","👾","🤖","😺","😸","😹","😻","😼","😽","🙀","😿","😾","🙈","🙉","🙊","💌","💘","💝","💖","💗","💓","💞","💕","💟","❣️","💔","❤️","🧡","💛","💚","💙","💜","🤎","🖤","🤍","💋","💯","💢","💥","💦","💨","🕳️","💬","👁️‍🗨️","🗨️","🗯️","💭","💤","🔴","🟠","🟡","🟢","🔵","🟣","🟤","⚫","⚪","🟥","🟧","🟨","🟩","🟦","🟪","🟫","⬛","⬜","🔶","🔷","🔺","🔻","💠","🔘","🔳","🔲","✖️","➕","➖","➗","♾️","‼️","⁉️","❓","❔","❕","❗","💱","💲","#️⃣","*️⃣","0️⃣","1️⃣","2️⃣","3️⃣","4️⃣","5️⃣","6️⃣","7️⃣","8️⃣","9️⃣","🔟","⚕️","♻️","⚜️","📛","🔰","⭕","✅","☑️","✔️","❌","❎","➰","➿","〽️","✳️","✴️","❇️","©️","®️","™️","🚮","🚰","♿","🚹","🚺","🚻","🚼","🚾","🛂","🛃","🛄","🛅","⚠️","🚸","⛔","🚫","🚳","🚭","🚯","🚱","🚷","📵","🔞","☢️","☣️","🌑","🌒","🌓","🌔","🌕","🌖","🌗","🌘","🌙","🌚","🌛","🌜","☀️","🌝","🌞","🪐","⭐","🌟","🌠","🌌","☁️","⛅","⛈️","🌤️","🌥️","🌦️","🌧️","🌨️","🌩️","🌪️","🌫️","🌬️","🌀","🌈","🌂","☂️","☔","⛱️","⚡","❄️","☃️","⛄","☄️","🔥","💧","🌊","💐","🌸","💮","🏵️","🌹","🥀","🌺","🌻","🌼","🌷","🌱","🌲","🌳","🌴","🌵","🌾","🌿","☘️","🍀","🍁","🍂","🍃","🍄","👋","🤚","🖐️","✋","🖖","👌","🤏","✌️","🤞","🤟","🤘","🤙","👈","👉","👆","🖕","👇","☝️","👍","👎","✊","👊","🤛","🤜","👏","🙌","👐","🤲","🤝","🙏","✍️","💅","🤳","💪","🦾","🦿","🦵","🦶","👂","🦻","👃","🧠","🦷","🦴","👀","👁️","👅","👄","👣","🧬","🩸","🪀","🪁","🔫","🎱","🔮","🎮","🕹️","🎰","🎲","🧩","♠️","♥️","♦️","♣️","♟️","🃏","🀄","🎴","🎭","🖼️","🎨"],
            resizeWidth: false,
            width: "100%",
            toolbar: "bold,italic,underline,strike,subscript,superscript|" +
                "left,center,right,justify|h1,h2,h3|font,size,color,removeformat|" +
                "bulletlist,orderedlist,indent,outdent|" +
                "table|code,mono,quote,hide|horizontalrule,image,email,link,unlink|" +
                "emoticon,emojis,date,time|maximize,source"
        },
        list = {
            bold: "b",
            italic: "i",
            underline: "u",
            strike: "s",
            subscript: "sub",
            superscript: "sup",
            left: "left",
            center: "center",
            right: "right",
            justify: "justify",
            h1: "h1",
            h2: "h2",
            h3: "h3",
            h4: "h4",
            h5: "h5",
            h6: "h6",
            font: "font",
            size: "size",
            color: "color",
            background: "background",
            bulletlist: "list",
            orderedlist: "list",
            table: "table",
            code: "code",
            mono: "mono",
            quote: "quote",
            hide: "hide",
            spoiler: "spoiler",
            horizontalrule: "hr",
            image: "img",
            email: "email",
            link: "url",
            hashtag: "hashtag"
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

            selection = "<blockquote><cite>" + name + "</cite>" + ForkBB.editor.imgToEmoticon(selection) + "</blockquote>";

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
        var conf, smiliesEnabled, linkEnabled, blackList, whiteList;

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
        blackList = "|" + textarea.getAttribute(blackName) + "|";
        whiteList = "|" + textarea.getAttribute(whiteName) + "|";

        var forDelete = ["youtube", "rtl", "ltr"];

        if (!smiliesEnabled) {
            forDelete.push("emoticon");
        }
        if (!linkEnabled) {
            forDelete.push("url", "link", "image", "img", "email", "unlink");
        }

        for (var key in list) {
            if (
                ("||" !== whiteList && -1 === whiteList.indexOf("|" + list[key] + "|"))
                || ("||" !== blackList && -1 !== blackList.indexOf("|" + list[key] + "|"))
            ) {
                forDelete.push(key, list[key]);

                if ("list" === list[key]) {
                    forDelete.push("*", "ul", "ol", "li");
                } else if ("table" === list[key]) {
                    forDelete.push("tr", "th" , "td", "caption", "thead", "tbody", "tfoot");;
                }
            }
        }

        for (var bbcodeForDelete of forDelete) {
            sceditor.command.remove(bbcodeForDelete);
            sceditor.formats.bbcode.remove(bbcodeForDelete);

            options.toolbar = options.toolbar.replace(new RegExp("\\b" + bbcodeForDelete.replace("*", "\\*") + "\\b", "gi"), "");
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
                instance.insert("[b]" + e.target.parentNode.textContent + "[/b],");
                elForScroll.scrollIntoView({behavior: "smooth", block: "end"});
                e.preventDefault();
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
                var target = e.target, arr = [], p;

                if (target.files.length > 0) {
                    var formData = new FormData();

                    for (var i = 0; i < target.files.length; i++) {
                        if (node.form.children.MAX_FILE_SIZE && node.form.children.MAX_FILE_SIZE.value < target.files[i].size) {
                            alert(target.files[i].name + " file too large");

                            return;
                        }

                        formData.append("files[]", target.files[i]);
                        arr.push(target.files[i].name);
                    }

                    p = doc.createElement("p");
                    p.className = "f-upfiles";
                    p.innerText = arr.join(', ');
                    target.parentNode.insertBefore(p, target);

                    fetch(target.getAttribute("data-d"), {
                        method: "POST",
                        body: formData,
                    }).then(function (response) {
                        p.remove();

                        if (!response.ok) {
                            throw new Error("HTTP Error: " + response.status);
                        }

                        return response.json();
                    }).then(function (data) {
                        if (data.error) {
                            throw new Error(data.error);
                        }

                        instance.insert("", data.text);
                    }).catch(function (e) {
                        alert(e);
                    });
                }

                target.value = '';
            });
        }

        for (var s in options.emoticons) {
            for (var i in options.emoticons[s]) {
                if (!imgToEmot[options.emoticons[s][i]]) {
                    imgToEmot[options.emoticons[s][i]] = i;
                }
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
        },
        imgToEmoticon : function(str) {
            return str.replace(/<img\s[^<>]+?\/img\/sm\/([^<>"]+)"[^<>]*>/gi, function (match, p1) {
                return imgToEmot[p1] ? imgToEmot[p1] : match;
            })
        }
    };
}(document, window));

document.addEventListener("DOMContentLoaded", ForkBB.editor.init, false);
