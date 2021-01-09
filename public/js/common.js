if (typeof ForkBB === "undefined" || !ForkBB) {
    var ForkBB = {};
}

ForkBB.common = (function (doc, win) {
    'use strict';

    var selectorBack = ".f-go-back";

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

    return {
        init : function () {
            initGoBack();
        },
    };
}(document, window));

if (document.addEventListener) {
	document.addEventListener("DOMContentLoaded", ForkBB.common.init, false);
}
