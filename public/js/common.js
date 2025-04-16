/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

if (typeof ForkBB === "undefined" || !ForkBB) {
    var ForkBB = {};
}

ForkBB.common = (function (doc, win) {
    'use strict';

    var nav = win.navigator,
        selectorBack = ".f-go-back",
        hlClass = "f-highlighted",
        shlClass = "f-search-highlight";

    function initGoBack()
    {
        var backs = doc.querySelectorAll(selectorBack);

        for (var i = 0; i < backs.length; i++) {
            backs[i].addEventListener("click", function (event) {
                win.history.back();
                event.preventDefault();

                return false;
            });
        }
    }

    function initAnchorHL()
    {
        var target,
            scroll,
            hash = (win.location.hash || "").replace(/^#/, "");

        if (hash) {
            target = doc.getElementById(hash);

            if (target) {
                target.classList.add(hlClass);

                setTimeout(function() {
                    target.classList.remove(hlClass);
                }, 1500);
            }
        } else if (
            (target = doc.getElementById("fork"))
            && (scroll = target.dataset.pageScroll)
            && (scroll = scroll.match(/^(-)?(\d+)$/))
            && "0" !== scroll[2]
        ) {
            target = null;

            if ("2" === scroll[2] && (target = doc.getElementById("fork-announce"))) {
                do {
                    target = target.nextElementSibling;
                } while (target && "none" === win.getComputedStyle(target).display)
            }

            if (!target) {
                target = doc.getElementById("fork-main");
            }

            if (target) {
                target.scrollIntoView("-" === scroll[1] ? {} : {behavior: "smooth"});
            }
        }
    }

    function initShowPass()
    {
        var inps = doc.querySelectorAll("input[type=\"password\"]");

        for (var i = 0; i < inps.length; i++) {
            var span = doc.createElement("span");
            span.classList.add("f-pass-ctrl");

            span.addEventListener("click", (function(i, s){
                return function () {
                    if (i.getAttribute("type") == "password") {
                        i.setAttribute("type", "text");
                        s.classList.add("f-pass-dspl");
                    } else {
                        i.setAttribute("type", "password");
                        s.classList.remove("f-pass-dspl");
                    }

                    i.focus();
                }
            })(inps[i], span));

            var parent = inps[i].parentNode;
            parent.appendChild(span);
            parent.classList.add("f-pass-prnt");
        }
    }

    function initForm()
    {
        var inps = doc.querySelectorAll("input[type=\"hidden\"][name=\"nekot\"]"),
            regx = new RegExp("(" + ".".repeat([1]+[2]-[3]-[2]-[1]) + ").*");

        for (var i = 0; i < inps.length; i++) {
            inps[i].value = (inps[i].parentNode.querySelector("input[type=\"hidden\"][name=\"token\"]").value.replace(/\D/g, "").replace(regx, "$1"));
        }
    }

    function initSubmitReactions()
    {
        var forms = doc.querySelectorAll("form.f-reaction-form");

        for (var i = 0; i < forms.length; i++) {
            forms[i].addEventListener("click", function (event) {
                var form,
                    b = event.target;

                if (b.tagName !== "BUTTON") {
                    return;
                }

                event.preventDefault();

                if (!b.name || !b.value || b.name !== b.value || !(form = b.closest("form"))) {
                    return;
                }

                var data = new FormData();

                data.append(b.name, b.value);

                fetch(form.action, {
                    method: "POST",
                    body: data,
                    headers: {
                        Accept: "application/json",
                        "X-Requested-With": "XMLHttpRequest"
                    }
                }).then(function (response) {
                    if (!response.ok) {
                        throw new Error("HTTP Error: " + response.status);
                    }

                    return response.json();
                }).then(function (data) {
                    if (data.error) {
                        throw new Error(data.error);
                    }

                    form.innerHTML = data.reactions;
                }).catch(function (e) {
                    alert(e);
                });
            });
        }
    }

    function highlightText(element, regexp)
    {
        if (element.nodeType === 3) {
            var text = element.textContent;
            var parts = text.split(regexp);

            if (parts.length > 1) {
                var parent = element.parentNode;

                for (var i = 0; i < parts.length; i++) {
                    if (regexp.test(parts[i])) {
                        var newPart = doc.createElement("span");
                        newPart.classList.add(shlClass);
                        newPart.textContent = parts[i];
                    } else {
                        var newPart = doc.createTextNode(parts[i]);
                    }

                    parent.insertBefore(newPart, element);
                }

                parent.removeChild(element);
            }
        } else if (element.nodeType === 1 && !element.classList.contains(shlClass) && element.childNodes.length && !/(script|style|iframe)/i.test(element.tagName)) {
            for (var i = 0; i < element.childNodes.length; i++) {
                highlightText(element.childNodes[i], regexp);
            }
        }
    }

    function initHighlight()
    {
        var nodes = doc.querySelectorAll("[data-search-regexp]");

        for (var i = 0; i < nodes.length; i++) {
            try {
                if (nodes[i].dataset.searchRegexp) {
                    highlightText(nodes[i], new RegExp("(" + nodes[i].dataset.searchRegexp + ")", "giu"));
                }
            } catch (error) {console.log(error);}
        }
    }

    return {
        init : function () {
            initGoBack();
            initForm();

            if (typeof DOMTokenList !== "undefined") {
                initAnchorHL();
                initShowPass();
                initHighlight();
            }

            if (typeof fetch !== "undefined") {
                initSubmitReactions();
            }
        },
    };
}(document, window));

document.addEventListener("DOMContentLoaded", ForkBB.common.init, false);
