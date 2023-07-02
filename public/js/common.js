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

    return {
        init : function () {
            initGoBack();
            initAnchorHL();
        },
    };
}(document, window));

if (document.addEventListener) {
	document.addEventListener("DOMContentLoaded", ForkBB.common.init, false);
}
