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

    function initShowPAss()
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

    return {
        init : function () {
            initGoBack();
            initAnchorHL();
            initShowPAss();
        },
    };
}(document, window));

if (document.addEventListener) {
	document.addEventListener("DOMContentLoaded", ForkBB.common.init, false);
}
