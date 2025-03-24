/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

if (typeof ForkBB === "undefined" || !ForkBB) {
    var ForkBB = {};
}

ForkBB.ipinfo = (function (doc, win) {
    'use strict';

    var selectorIP = ".f-js-ipinfo",
        classDone = ".f-js-ipinfo-done",
        timeout = 200,
        dataIP = "data-ip",
        url = "https://ip.guide/%%%";

    function initIPInfo()
    {
        var ip, ips = doc.querySelectorAll(selectorIP);

        for (var i = 0; i < ips.length; i++) {
            ip = ips[i].getAttribute(dataIP);

            if (ip) {
                (function (node, url, ip) {
                    setTimeout(function animIPInfo() {
                        if (! node.classList.contains(classDone)) {
                            node.insertAdjacentText("beforeend", ".");

                            setTimeout(animIPInfo, timeout);
                        }
                    }, timeout);
                    fetch(url.replace("%%%", ip))
                    .then(function (response) {
                        return response.json();
                    }).then(function (result) {
                        var pre = doc.createElement("pre");
                        pre.textContent = JSON.stringify(result, null, 2);

                        while (node.lastChild) {
                            node.removeChild(node.lastChild);
                        }

                        node.classList.add(classDone);
                        node.appendChild(pre);
                    }).catch(function (e) {
                        alert(e);
                    });
                }(ips[i], url, ip));
            }
        }
    }

    return {
        init : function () {
            if (typeof fetch !== "undefined") {
                initIPInfo();
            }
        },
    };
}(document, window));

document.addEventListener("DOMContentLoaded", ForkBB.ipinfo.init, false);
