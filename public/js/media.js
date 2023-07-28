/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

if (typeof ForkBB === "undefined" || !ForkBB) {
    var ForkBB = {};
}

ForkBB.media = (function (doc, win, nav) {
    'use strict';

    var id = 0,
        html5mime = {},
        html5aRegx = getHTML5("audio", ["mp3", "m4a", "ogg", "oga", "webma", "wav", "flac"], ["mpeg", "mp4", "ogg", "ogg", "webm", "wav", "flac"]),
        html5vRegx = getHTML5("video", ["mp4", "m4v", "ogv", "webm", "webmv"], ["mp4", "mp4", "ogg", "webm", "webm"]),
        lazyFlag = false,
        lazyClass = "mediajslazy",
        lazyData = [],
        lazyObserver,
        searchClass = "formedia",
        addClass = "mediajslink",
        fb = false;

    function getHTML5(type, exts, mimes) {
        var i,
            r = "",
            a = doc.createElement(type);

        if (!!a.canPlayType) {
            for (i = 0; i<exts.length; i++) {
                if (a.canPlayType(type + "/" + mimes[i]) !== "") {
                    html5mime[exts[i]] = type + "/" + mimes[i];
                    r += (r ? "|" : "") + exts[i];
                }
            }

            return r ? new RegExp('^([^\\\\\\?\\s<>"\'&]+\\.)(' + r + ')$', 'i') : false;
        }

        return false;
    }

    function getOembedSize(data) {
        if (data.width && data.height) {
            var e = 1;

            if (data.width < data.height) {
                if (data.height < 480) {
                    e = 480 / data.height;
                } else if (data.height > 640) {
                    e = 640 / data.height;
                }
            } else {
                if (data.width > 640) {
                    e = 640 / data.width;
                } else if (data.height < 480) {
                    e = Math.min(640 / data.width, 480 / data.height);
                }
            }

            return [Math.floor(data.width * e), Math.floor(data.height * e)];
        }
    }

    function getOembed(url, successHandler, errorHandler) {
        var xhr = new XMLHttpRequest();

        xhr.open("GET", url, true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState == 4) {
                if (xhr.status == 200) {
                    var data = xhr.responseText;

                    if (typeof data === "string") {
                        try {
                            data = JSON.parse(data);
                        } catch (e) {
                            errorHandler && errorHandler(e);
                            return;
                        }
                    }

                    if ("width" in data && "height" in data && "html" in data) {
                        successHandler && successHandler(data);
                    } else {
                        errorHandler && errorHandler(data);
                    }
                } else {
                    errorHandler && errorHandler(xhr);
                }
            }
        };
        xhr.send();
    }

    function createEl(node, type, attr, param, after) {
        attr = attr || {};
        param = param || {};

        var child,
            flag = type === "video" || type === "audio",
            obj = doc.createElement(type);

        for (var i in attr) {
            if (attr.hasOwnProperty(i)) {
                if (i == "style") {
                    obj.style.cssText = attr[i];
                } else {
                    obj.setAttribute(i, attr[i]);
                }
            }
        }

        if (flag) {
            child = doc.createElement("source");
        }

        for (var i in param) {
            if (param.hasOwnProperty(i)) {
                if (flag) {
                    child.setAttribute(i, param[i]);
                } else {
                    child = doc.createElement("param");
                    child.setAttribute("name", i);
                    child.setAttribute("value", param[i]);
                    obj.appendChild(child);
                }
            }
        }

        if (flag) {
            obj.appendChild(child);
        }

        if (!after) {
            return node.appendChild(obj);
        }

        return node.parentNode.insertBefore(obj, node.nextSibling);
    }

    function createMedia(node, playAttr, size, type, playParam) {
        var style1 = "padding-top:5px;",
            style2 = "overflow:hidden;position:relative;",
            attrDiv1 = {style: style1 + style2},
            attrDiv2, tw, th;

        type = type || "iframe";
        playParam = playParam || {};

        if ("iframe" === type) {
            playAttr.frameborder = "0";
            playAttr.scrolling = "no";

            if (! playAttr.allowfullscreen) {
                playAttr.allowfullscreen = "allowfullscreen";
            }
        }

        size = size || [640, 360];
        // 0 - ошибка, 1 - размер не указывать, 2 - в пикселах, 3 - в процентах
        tw = typeSize(size[0]);
        th = typeSize(size[1]);

        if (!tw || !th) {
            return;
        }

        if (tw > 1) {
            attrDiv1.style = attrDiv1.style + (3 === tw ? "width:" + size[0] + ";" : "max-width:" + size[0] + "px;");
        }

        if (3 === tw && 2 === th) {
            attrDiv2 = {style: style2 + "height:" + size[1] + "px;"};
            playAttr.style = "width:100%;height:" + size[1] + "px;";
        } else if (tw > 1 && th > 1) {
            attrDiv2 = {style: style2 + "height:0;padding-bottom:" + (3 === th ? size[1] : (100 * size[1] / size[0]) + "%;")};
            playAttr.style = "position:absolute;left:0;top:0;width:100%;height:100%;";
        } else if (tw > 1) {
            playAttr.style = "width:100%;";
        }

        ++id;
        playAttr.id = "mediajs" + id;

        node.classList.replace(searchClass, addClass);

        var div = createEl(node, "div", attrDiv1, {}, true);

        if (attrDiv2) {
            div = createEl(div, "div", attrDiv2);
        }

        if (lazyFlag && "div" !== type) {
            div.classList.add(lazyClass);
            div.setAttribute("data-lazyid", id);

            lazyData[id] = {
                type: type,
                attr: playAttr,
                param: playParam
            };

            if (! lazyObserver) {
                lazyObserver = new IntersectionObserver(function(entries, observer) {
                    entries.forEach(function(entry) {
                        if (entry.isIntersecting) {
                            var lazyObj = entry.target,
                                id = lazyObj.dataset.lazyid,
                                set = lazyData[id];

                            createEl(lazyObj, set.type, set.attr, set.param);
                            lazyObj.classList.remove(lazyClass);
                            observer.unobserve(lazyObj);
                        }
                    });
                });
            }

            lazyObserver.observe(div);
        } else {
            createEl(div, type, playAttr, playParam);
        }

        return true;
    }

    function convertRangeToZoom(range, type) {
        if (type != "z") {
          range = Math.log(70400000 / range) / Math.log(2);
        }

        return Math.min(18, Math.max(Math.round(range), 3));
    }

    function hmsToSec(str) {
        if (/^\d+$/.test(str)) {
            return str;
        }

        if (str = str.match(/^((\d{1,2})h)?((\b\d{1,3}|\d{1,2})m)?((\b\d{1,5}|\d{1,2})s)?$/)) {
            return 3600 * (str[2] || 0) + 60 * (str[4] || 0) + 1 * (str[6] || 0);
        }

        return "";
    }

    // 0 - ошибка, 1 - размер не указывать, 2 - в пикселах, 3 - в процентах
    function typeSize(size) {
        if ("string" === typeof size && /^(?:100|[1-9]\d(?:\.\d{1,2})?)%$/.test(size)) {
            return 3;
        } else if (0 !== size % 1) {
            return 0;
        } else if (size > 15) {
            return 2;
        } else if (size < 1) {
            return 1;
        } else {
            return 0;
        }
    }

    function test(node)
    {
        var url = node.href,
            m = url.match(/^(.+)(?:#|%23)(\d+(?:\.\d{1,2})?)(%|%25)?(?::|%3A)(\d+(?:\.\d{1,2})?)(%|%25)?$/),
            arr, size;

        if (m) {
            node.herf = url = m[1];
            m[2] += (m[3] ? "%" : "");
            m[4] += (m[5] ? "%" : "");

            if (typeSize(m[2]) > 0 && typeSize(m[4]) > 0) {
                size = [m[2], m[4]];
            }
        }

        if (!(m = url.replace(/&amp;/g, "&").match(/^(https?:\/\/)(www\.)?(.+)$/))) {
            return;
        }

        url = m[3];

        if (!!html5aRegx && (arr = url.match(html5aRegx))) {
            createMedia(node, {controls: true, preload: "metadata", controlsList: "nodownload"}, size || ["100%", 0], "audio", {src: m[1] + (m[2] ? m[2] : "") + arr[1] + arr[2], type: html5mime[arr[2].toLowerCase()]});

        } else if (!!html5vRegx && (arr = url.match(html5vRegx))) {
            createMedia(node, {controls: true, preload: "metadata", controlsList: "nodownload"}, size, "video", {src: m[1] + (m[2] ? m[2] : "") + arr[1] + arr[2], type: html5mime[arr[2].toLowerCase()]});

        } else if (arr = url.match(/^(?:[a-z\d-]+\.)?youtu(?:be(?:-nocookie)?\.com|\.be)\/(?:playlist|(?:(?:.*?[\?&]v=)?([\w-]{11})(?:\?|&|#|$)))/)) {
            var b = "https://www.youtube.com/embed",
                param = ["wmode=transparent"], // , "controls=2"
                need = [],
                c, d, e, only;

            if (arr[1]) {
                b += "/" + arr[1];
                only = ["start", "t", "end", "list", "rel", "loop"];
            } else {
                param.push("listType=playlist");
                only = ["list", "index", "loop"];
                need = ["list"]
            }

            e = url.split(/[\?&#]/);

            while (c = e.shift()) {
                d = c.split("=");

                if (d[0] && d[1] && only.indexOf(d[0]) > -1 && /^[\w-]+$/.test(d[1])) {
                    if (d[0] == "start" || d[0] == "t" || d[0] == "end") {
                        param.push((d[0] == "end" ? "end" : "start") + "=" + hmsToSec(d[1]));
                    } else {
                        param.push(d[0] + "=" + d[1]);

                        if (d[0] == "loop" && d[1] == "1" && arr[1]) {
                            param.push("playlist=" + arr[1]);
                        }
                    }

                    d = need.indexOf(d[0]);

                    if (d > -1) {
                        need.splice(d, 1);
                    }
                }
            }

            if (!need.length) {
                createMedia(node, {src: b + "?" + param.join("&")}, size);
            }

        } else if (arr = url.match(/^(?:player\.)?vimeo\.com\/(?:[^\s\/<>'"]+\/){0,3}(?:\d+)(?=\?|#|$)/)) {
            getOembed("https://vimeo.com/api/oembed.json?url=https%3A//" + arr[0], function(data) {
                if (data.m = data.html.match(/<iframe [^>]*?src=["']([^"'<>]+)["']/)) {
                    createMedia(node, {src: data.m[1]}, size || getOembedSize(data));
                }
            });

        } else if (arr = url.match(/^dai(?:lymotion\.com\/video|\.ly)\/([a-zA-Z\d]+)/)) {
            createMedia(node, {src: "https://www.dailymotion.com/embed/video/" + arr[1] + "?theme=none"}, size);

        } else if (arr = url.match(/^(?:video\.rutube\.ru|rutube\.ru\/(?:video(?:\/embed)?|play\/embed))\/([a-f\d]+)(?=\/|\?|#|$)\/?(?:\?t=(\d+))?/)) {
            createMedia(node, {src: "https://rutube.ru/play/embed/" + arr[1] + (arr[2] ? "?t=" + arr[2] : "")}, size);

        } else if (arr = url.match(/^api\.soundcloud\.com\/(?:tracks|playlists)\/\d+/)) {
            createSC(node, arr[0], size)

        } else if (arr = url.match(/^soundcloud\.com\/[\w-]+\/(?:sets\/)?[\w-]+/)) {
            getOembed("https://soundcloud.com/oembed?format=json&url=https%3A//" + arr[0], function (data) {
                if (data.m = data.html.replace(/%2F/g, "/").match(/api\.soundcloud\.com\/(?:tracks|playlists)\/\d+/)) {
                    createSC(node, data.m[0], size)
                }
            });

        } else if (arr = url.match(/^video\.sibnet\.ru\/[^\?&]*?video(\d+)/)) {
            createMedia(node, {src: "https://video.sibnet.ru/shell.php?videoid=" + arr[1] + "/"}, size);

        } else if (arr = url.match(/^promodj\.com\/(?:[^\/]+\/[^\/]+|download|embed)\/(\d+)/)) {
            createMedia(node, {src: "https://promodj.com/embed/" + arr[1] + "/big"}, size || ["100%", 70]);

        } else if (arr = url.match(/^([a-z]+\.)?gamespot\.com\/(?:video(?:s|embed)?|[\w\/-]+\/videos?)(?=\/)[^\?&#]*[\/-](\d{7,})\//)) {
            var d = url.split("?");

            d = d[1] ? d[1].replace(/&?autoplay=[^&]*/g, "").replace(/^&/, "") : false;

            createMedia(node, {src: "https://www.gamespot.com/videos/embed/" + arr[2] + "/" + (d ? "?" + d : "")}, size);

        } else if (arr = url.match(/^(mixcloud\.com\/(?!(categories|competitions|projects|developers|tag)\/)[\w-]+\/(?!(listens|favorites|activity|messages|following)\/)[\w-]+\/)$/)) {
            createMedia(node, {src: "https://www.mixcloud.com/widget/iframe/?hide_artwork=1&embed_type=widget_standard&hide_tracklist=1&feed=https://www." + arr[1]}, size || ["100%", 120]);

        } else if (arr = url.match(/^coub\.com\/view\/([a-zA-Z\d]+)/)) {
            createMedia(node, {src: "https://coub.com/embed/" + arr[1] + "?muted=false&autostart=false&originalSize=false"}, size);

        } else if (arr = url.match(/^(?=[^\/]*\byandex\.([a-z]+)\/)(?=[^\?&]*\bmaps\b).+\/\-\/([\w\~\-]+)$/)) {
            createMedia(node, {src: "https://yandex." + (arr[1] == "ru" || arr[1] == "by" ?  "ru" : "com") + "/map-widget/v1/-/" + arr[2]}, size || [600, 600]);

        } else if (arr = url.match(/^(?=[^\/]*\byandex\.([a-z]+)\/)(?=[^\?&]*\bmaps\b)[^\?]+\?(ll=[\d.,%C]+&z=\d+)/)) {
            createMedia(node, {src: "https://yandex." + (arr[1] == "ru" || arr[1] == "by" ?  "ru" : "com") + "/map-widget/v1/?" + arr[2]}, size || [600, 600]);

        } else if (arr = url.match(/^maps\.google\.((?:com?\.)?[a-z]{2,})\/(?:maps)?\?([^\s<>'"]+)$/)) {
            createMedia(node, {src: "https://maps.google.com/?" + arr[2] + "&ie=UTF8&output=embed"}, size || [600, 600]);

        } else if (arr = url.match(/^google\.((?:com?\.)?[a-z]{2,})\/maps\/embed\?pb=([^\s\?<>,&'"]+)$/)) {
            createMedia(node, {src: "https://www.google.com/maps/embed?pb=" + arr[2]}, size || [600, 600]);

        } else if (arr = url.match(/^google\.((?:com?\.)?[a-z]{2,})\/maps\/(?:place\/([^\/<>\[\]\?&'"]+))?[^\?&%]*(?:@|%40)(-?\d+\.\d+)(?:,|%2C)(-?\d+\.\d+)(?:,|%2C)(\d+(?:\.\d+)?)([zm])/)) {
            createMedia(node, {src: "https://maps.google.com/?ll=" + arr[3] + "," + arr[4] + "&t=" + (arr[6] == "z" ? "m" : "h") + "&z=" + convertRangeToZoom(arr[5], arr[6]) + (!!arr[2] ? "&q=" + arr[2] : "") + "&ie=UTF8&output=embed"}, size || [600, 600]);

        } else if (arr = url.match(/^ok\.ru\/video(?:embed)?\/(\d+)/)) {
            createMedia(node, {src: "https://ok.ru/videoembed/" + arr[1]}, size);

        } else if (arr = url.match(/^aparat\.com\/(?:v|embed)\/(\w+)/)) {
            createMedia(node, {src: "https://www.aparat.com/video/video/embed/videohash/" + arr[1] + "/vt/frame"}, size);

        } else if (arr = url.match(/^audiomack\.com\/(?:embed\/)?([\w-]+)\/([\w-]+)\/([\w-]+)/)) {
            if (/^(playlist|song|album)$/.test(arr[1])) {
                var d = arr[2];
                arr[2] = arr[1];
                arr[1] = d;
            }

            if (/^(playlist|song|album)$/.test(arr[2])) {
                createMedia(node, {src: "https://audiomack.com/embed/" + arr[1] + "/" + arr[2] + "/" + arr[3] + "?background=1"}, size || ["100%", arr[2] === "song" ? 252 : 400]);
            }

        } else if (arr = url.match(/^izlesene\.com\/(?:embedplayer|video\/[^\/]+)\/(\d+)/)) {
            createMedia(node, {src: "https://www.izlesene.com/embedplayer/" + arr[1] + "/?showrel=0&loop=0&autoplay=0&autohide=1&showinfo=1&socialbuttons=1&annotation=&volume=0.5"}, size);

        } else if (arr = url.match(/^hearthis\.at\/(?!(?:categories|maps)\/)[\w\.-]+\/(?:set\/)?[\w\.-]+\//)) {
            getOembed("https://hearthis.at/oembed/?format=json&url=https%3A//" + arr[0], function (data) {
                if (data.m = data.html.match(/<iframe [^>]*?src=["']([^"'<>]+)["']/)) {
                    createMedia(node, {src: data.m[1]}, size || [data.width, data.height]);
                }
            });

        } else if (arr = url.match(/^iz\.ru\/(?:(\d+)\/video\/|video\/embed\/(\d+)$)/)) {
            createMedia(node, {src: "https://iz.ru/video/embed/" + (arr[1] || arr[2])}, size);

        } else if (arr = url.match(/^t\.me\/([\w-]+\/\d+)$/)) {
            createMedia(node, {src: "https://telegram.org/js/telegram-widget.js?18", "data-telegram-post": arr[1], "data-width": "100%"}, size || [0, 0], "script");

        } else if (arr = url.match(/^music.yandex.ru\/(?:iframe\/#)?album\/(\d+)/)) {
            createMedia(node, {src: "https://music.yandex.ru/iframe/#album/" + arr[1]}, size || ["100%", 450]);

        }
    }

    function createSC(node, url, size) {
        createMedia(node, {src: "https://w.soundcloud.com/player/?sharing=false&liking=false&show_playcount=false&show_comments=false&url=https%3A//" + url}, size || ["100%", /\/playlists\//.test(url) ? 450 : 166]);
    }

    function crawl()
    {
        var a,
            links = doc.querySelectorAll("a." + searchClass);

        if ("IntersectionObserver" in win && !/Googlebot|YandexBot/.test(nav.userAgent)) {
            lazyFlag = true;
        }

        for (var i = 0; i < links.length; i++) {
            a = links[i];

            if (
                !!a.href
                && (!a.nextSibling || "BR" === a.nextSibling.nodeName)
                && (!a.previousSibling || "BR" === a.previousSibling.nodeName)
                && !a.closest("blockquote")
            ) {
                test(a);
            }
        }
    }

    return {
        init : function () {
            crawl();
        },
    };
}(document, window, navigator));

document.addEventListener("DOMContentLoaded", ForkBB.media.init, false);
