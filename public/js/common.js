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

    var selectorBack = ".f-go-back",
        hlClass = "f-highlighted";


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
            hash = (win.location.hash || "").replace(/^#/, "");

        if (hash && (target = doc.getElementById(hash))) {
            target.classList.add(hlClass);

            setTimeout(function() {
                target.classList.remove(hlClass);
            }, 1500);
        }
    }

    function initShowPass()
    {
        var inps = doc.querySelectorAll("input[type='password']");

        for (var i = 0; i < inps.length; i++) {
            var span = doc.createElement("span");
            span.classList.add('f-pass-ctrl');

            span.addEventListener('click', (function(i, s){
                return function () {
                    if (i.getAttribute('type') == 'password') {
                        i.setAttribute('type', 'text');
                        s.classList.add('f-pass-dspl');
                    } else {
                        i.setAttribute('type', 'password');
                        s.classList.remove('f-pass-dspl');
                    }

                    i.focus();
                }
            })(inps[i], span));

            var parent = inps[i].parentNode;
            parent.appendChild(span);
            parent.classList.add('f-pass-prnt');
        }
    }

    function initForm()
    {
        var inps = doc.querySelectorAll("input[type='hidden'][name='nekot']");

        for (var i = 0; i < inps.length; i++) {
            inps[i].value = (inps[i].parentNode.querySelector("input[type='hidden'][name='token']").value.replace(/\D/g, '').replace(/(......).*/, '$1'));
        }
    }

    function initSubmitReactions()
    {
        var forms = doc.querySelectorAll("form.f-reaction-form");

        for (var i = 0; i < forms.length; i++) {
            forms[i].addEventListener('click', function (event) {
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
                        throw new Error('HTTP Error: ' + response.status);
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

    return {
        init : function () {
            initGoBack();
            initForm();

            if (typeof DOMTokenList !== 'undefined') {
                initAnchorHL();
                initShowPass();
            }

            if (typeof fetch !== "undefined") {
                initSubmitReactions();
            }
        },
    };
}(document, window));

document.addEventListener("DOMContentLoaded", ForkBB.common.init, false);
