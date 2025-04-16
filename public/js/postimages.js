var postimage = postimage || {};
postimage.output = function(i, t, co) {
    var w = co && opener != null ? opener : window,
        m = t.match(/\s*(\[img\][^\[\]]+\[\/img\])\s*/);
    if (m) {
        t = t.replace(m[0], "{img}");
        var n = t.match(/\[url=[^\[\]]+\]{img}\[\/url\]/);
        t = n ? n[0].replace("{img}", m[1]) : m[1];
        w.ForkBB.editor.getInstance().insert(t);
    }
//    var area = w.document.querySelector('[data-postimg="' + i + '"]');
//    area.value = area.value + t;
    if (co && opener != null) {
        opener.focus();
        window.close();
    }
};
postimage.insert = function(area, container) {
    area.parentNode.insertBefore(container, area.nextSibling);
};
if (typeof postimage.ready === "undefined") {
    postimage.opt = postimage.opt || {};
    postimage.opt.mode = postimage.opt.mode || "fluxbb";
    postimage.opt.host = postimage.opt.host || "postimages.org";
    postimage.opt.selector = postimage.opt.selector || ".sceditor-container";
    postimage.opt.lang = "english";
    postimage.opt.code = "thumb";
    postimage.opt.content = "";
    postimage.opt.hash = postimage.opt.hash || "1";
    postimage.opt.customtext = postimage.opt.customtext || "";
    postimage.dz = [];
    postimage.windows = {};
    postimage.session = "";
    postimage.gallery = "";
    postimage.previous = 0;
    postimage.resp = null;
    postimage.dzcheck = null;
    postimage.dzimported = false;
    postimage.dragcounter = 0;
    postimage.text = {
        "default": "Add image to post",
        "ar": "\u0623\u0636\u0641 \u0627\u0644\u0635\u0648\u0631\u0629 \u0644\u0644\u0645\u0648\u0636\u0648\u0639",
        "hy": "\u0531\u057e\u0565\u056c\u0561\u0581\u0580\u0565\u055b\u0584 \u0576\u056f\u0561\u0580",
        "eu": "Gehitu Irudiak",
        "bs": "Dodaj sliku u objavu",
        "bg": "\u0414\u043e\u0431\u0430\u0432\u0435\u0442\u0435 \u0438\u0437\u043e\u0431\u0440\u0430\u0436\u0435\u043d\u0438\u0435",
        "ca": "Afegeix una imatge a la publicaci\u00f3",
        "zh_CN": "\u6dfb\u52a0\u56fe\u7247\u4ee5\u4e0a\u4f20",
        "zh_TW": "\u6dfb\u52a0\u5716\u7247\u4e0a\u50b3",
        "hr": "Dodaj sliku u objavu",
        "cs": "P\u0159idej obr\u00e1zek do \u010dl\u00e1nku",
        "da": "Tilf\u00f8j billede for at sende",
        "nl": "Afbeelding aan bericht toevoegen",
        "et": "Lisa pilt postitusse",
        "fi": "Lis\u00e4\u00e4 viestiin kuva",
        "fr": "Ajouter une image au message",
        "ka": "\u10e4\u10dd\u10e2\u10dd\u10e1 \u10d3\u10d0\u10db\u10d0\u10e2\u10d4\u10d1\u10d0 \u10de\u10dd\u10e1\u10e2\u10d8\u10e1\u10d7\u10d5\u10d8\u10e1",
        "de": "Bild hinzuf\u00fcgen",
        "el": "\u03a0\u03c1\u03bf\u03c3\u03b8\u03ae\u03ba\u03b7 \u03b5\u03b9\u03ba\u03cc\u03bd\u03b1\u03c2 \u03c0\u03c1\u03bf\u03c2 \u03b4\u03b7\u03bc\u03bf\u03c3\u03af\u03b5\u03c5\u03c3\u03b7",
        "he": "\u05d4\u05d5\u05e1\u05e3 \u05ea\u05de\u05d5\u05e0\u05d4 \u05dc\u05d4\u05d5\u05d3\u05e2\u05d4",
        "hi": "\u092a\u094b\u0938\u094d\u091f \u092e\u0947 \u091b\u0935\u093f \u091c\u094b\u0921\u093c\u0947\u0902",
        "hu": "K\u00e9p hozz\u00e1ad\u00e1sa a bejegyz\u00e9shez",
        "id": "Menambahkan gambar ke posting",
        "it": "Aggiungi immagine al messaggio",
        "ja": "\u6295\u7a3f\u306b\u753b\u50cf\u3092\u8ffd\u52a0",
        "ko": "\ud3ec\uc2a4\ud2b8\uc5d0 \uc774\ubbf8\uc9c0 \ucd94\uac00",
        "ku": "\u200e\u0628\u0627\u0631\u0643\u0631\u062f\u0646\u06cc \u0648\u06ce\u0646\u0647\u200c",
        "lv": "Pievienot att\u0113lu Post",
        "lt": "Prid\u0117ti paveiksliuka \u012f post\u0105",
        "mk": "\u0414\u043e\u0434\u0430\u0434\u0438 \u0441\u043b\u0438\u043a\u0430 \u0432\u043e \u043f\u043e\u0441\u0442",
        "ms": "Tambah imej ke pos.",
        "no": "Legg til bilde i meldingen",
        "fa": "\u0627\u0641\u0632\u0648\u062f\u0646 \u0639\u06a9\u0633 \u0628\u0647 \u0646\u0648\u0634\u062a\u0647",
        "pl": "Dodaj zdj\u0119cie do wiadomo\u015bci",
        "pt": "Adicionar imagem \u00e0 mensagem",
        "pt_BR": "Adicionar imagem \u00e0 mensagem",
        "ro": "Adaug\u0103 imagine pentru postare",
        "ru": "\u0414\u043e\u0431\u0430\u0432\u0438\u0442\u044c \u043a\u0430\u0440\u0442\u0438\u043d\u043a\u0443 \u0432 \u0441\u043e\u043e\u0431\u0449\u0435\u043d\u0438\u0435",
        "sr": "\u0414\u043e\u0434\u0430\u0458 \u0441\u043b\u0438\u043a\u0443 \u0443 \u043f\u043e\u0440\u0443\u043a\u0443",
        "sr_LATN": "Dodaj sliku u poruku",
        "sk": "Prida\u0165 obr\u00e1zok do pr\u00edspevku",
        "sl": "Dodaj sliko v sporo\u010dilo",
        "es": "Insertar una imagen",
        "es_US": "Insertar una imagen",
        "sv": "L\u00e4gg till bild p\u00e5 inl\u00e4gg",
        "tl": "Magdagdag ng larawan sa paskil",
        "th": "\u0e43\u0e2a\u0e48\u0e20\u0e32\u0e1e\u0e40\u0e02\u0e49\u0e32\u0e44\u0e1b\u0e43\u0e19\u0e42\u0e1e\u0e2a",
        "tr": "Temel Resim Y\u00fckleme Modu",
        "uk": "\u0414\u043e\u0434\u0430\u0442\u0438 \u043a\u0430\u0440\u0442\u0438\u043d\u043a\u0443 \u0432 \u043f\u043e\u0432\u0456\u0434\u043e\u043c\u043b\u0435\u043d\u043d\u044f",
        "vi": "Th\u00eam \u1ea3nh v\u00e0o b\u00e0i \u0111\u0103ng",
        "cy": "Ychwanegu llun i sylw"
    };
    postimage.ts = new Date();
    postimage.ui = "";
    postimage.ui += typeof screen.colorDepth != "undefined" ? screen.colorDepth : "?";
    postimage.ui += typeof screen.width != "undefined" ? screen.width : "?";
    postimage.ui += typeof screen.height != "undefined" ? screen.height : "?";
    postimage.ui += typeof navigator.cookieEnabled != "undefined" ? "true" : "?";
    postimage.ui += typeof navigator.systemLanguage != "undefined" ? navigator.systemLanguage : "?";
    postimage.ui += typeof navigator.userLanguage != "undefined" ? navigator.userLanguage : "?";
    postimage.ui += typeof postimage.ts.toLocaleString == "function" ? postimage.ts.toLocaleString() : "?";
    postimage.ui += typeof navigator.userAgent != navigator.userAgent ? navigator.userAgent : "?";
    var scripts = document.getElementsByTagName("script");
    for (var i = 0; i < scripts.length; i++) {
        var script = scripts[i];
        if (script.src && script.src.indexOf("postimage") !== -1) {
            var options = script.getAttribute("src").split("/")[3].replace(".js", "").split("-");
            for (var j = 0; j < options.length; j++) {
                if (options[j] === "hotlink") {
                    postimage.opt.code = "hotlink";
                } else if (options[j] === "adult") {
                    postimage.opt.content = "adult";
                } else if (options[j] === "family") {
                    postimage.opt.content = "family";
                } else if (postimage.text.hasOwnProperty(options[j])) {
                    postimage.opt.lang = options[j];
                }
            }
        }
    }
    var clientLang = (postimage.opt.lang == "english" ? navigator.language || navigator.userLanguage : postimage.opt.lang).replace("-", "_");
    var langKey = postimage.text.hasOwnProperty(clientLang) ? clientLang : postimage.text.hasOwnProperty(clientLang.substring(0, 2)) ? clientLang.substring(0, 2) : null;
    if (langKey) {
        postimage.text = postimage.text[langKey];
    } else if (postimage.text.hasOwnProperty(postimage.opt.lang)) {
        postimage.text = postimage.text[postimage.opt.lang];
    } else {
        postimage.text = postimage.text["default"];
    }
    if (postimage.opt.customtext != "") {
        postimage.text = postimage.opt.customtext;
    }(function() {
        var match, plus = /\+/g,
            search = /([^&=]+)=?([^&]*)/g,
            decode = function(s) {
                return decodeURIComponent(s.replace(plus, " "));
            },
            query = postimage.opt.hash == "1" ? window.location.hash.substring(1) : window.location.search.substring(1);
        postimage.params = {};
        while (match = search.exec(query)) {
            postimage.params[decode(match[1])] = decode(match[2]);
        }
    })();
    window.addEventListener("message", function(e) {
        var regex = new RegExp("^" + ("https://" + postimage.opt.host).replace(/\./g, "\\.").replace(/\//g, "\\/") + "$", "i");
        if (!regex.test(e.origin) || !e.data || !e.data.id || !e.data.message) {
            return;
        };
        var id = e.data.id;
        if (!id || !postimage.windows[id] || e.source !== postimage.windows[id].window) {
            return;
        }
        postimage.output(id, decodeURIComponent(e.data.message), false);
        var area = document.querySelector("[data-postimg=\"" + id + "\"]");
        if (area) {
            var events = ["blur", "focus", "input", "change", "paste"];
            for (var i = 0; i < events.length; i++) {
                var event = new Event(events[i]);
                area.dispatchEvent(event);
            }
        }
    }, false);
}
postimage.serialize = function(obj, prefix) {
    var q = [];
    for (var p in obj) {
        if (!obj.hasOwnProperty(p)) {
            continue;
        }
        var k = prefix ? prefix + "[" + p + "]" : p,
            v = obj[p];
        q.push(typeof v == "object" ? serialize(v, k) : encodeURIComponent(k) + "=" + encodeURIComponent(v));
    }
    return q.join("&");
};
postimage.upload = function(areaid) {
    var params = {
        "mode": postimage.opt.mode,
        "areaid": areaid,
        "hash": postimage.opt.hash,
        "pm": "1",
        "lang": postimage.opt.lang,
        "code": postimage.opt.code,
        "content": postimage.opt.content,
        "forumurl": encodeURIComponent(document.location.href)
    };
    if (typeof SECURITYTOKEN != "undefined") {
        params["securitytoken"] = SECURITYTOKEN;
    }
    var self = postimage;
    params = postimage.serialize(params);
    if (typeof postimage.windows[areaid] !== typeof undefined) {
        window.clearInterval(postimage.windows[areaid].timer);
        if (postimage.windows[areaid].window !== typeof undefined && postimage.windows[areaid].window) {
            postimage.windows[areaid].window.close();
        }
    }
    postimage.windows[areaid] = {};
    postimage.windows[areaid].window = window.open("https://" + postimage.opt.host + "/upload?" + params, areaid, "scrollbars=1,resizable=0,width=690,height=620");
    var self = postimage;
    postimage.windows[areaid].timer = window.setInterval(function() {
        if (self.windows[areaid] === typeof undefined || !self.windows[areaid].window || self.windows[areaid].window.closed !== false) {
            window.clearInterval(self.windows[areaid].timer);
            self.windows[areaid] = undefined;
        }
    }, 200);
};
postimage.render = function(i) {
    if (!document.getElementById("postimages-style")) {
        var style = document.createElement("style");
        style.id = "postimages-style";
        style.innerText = "#fork .postimages-container--forkbb{display:inline-block;margin-top:0.3125rem}#fork .postimages-button--forkbb{line-height:normal;transition:all .2s;outline:0;color:#3a80ea;border:none;cursor:pointer;border:.0625rem solid rgba(0,0,0,.15);background:#ececec;border-radius:.2rem;padding:.3125rem .625rem;font-size:0.75rem;font-weight:700;text-shadow:none}#fork .postimages-button--forkbb:hover{background:#3a80ea;color:#fff;border-color:rgba(0,0,0,.1)}#fork .postimages-button--forkbb-icon,.postimages-button--forkbb-text{display:inline-block;vertical-align:middle}#fork .postimages-button--forkbb-icon{width:1rem;height:1rem}#fork .postimages-button--forkbb-text{padding-inline-start:.25rem}";
        document.body.appendChild(style);
    }
    var div = document.createElement("div");
    div.className = "postimages-container--forkbb";
    var but = document.createElement("button");
    but.className = "postimages-button--forkbb";
    but.title = postimage.text;
    but.addEventListener("click", function(i) {
        return function(e) {
            e.preventDefault();
            postimage.upload(i);
        };
    }(i));
    var icon = document.createElement("span");
    icon.className = "postimages-button--forkbb-icon";
    icon.innerHTML = '<svg class="postimages-button--forkbb-icon" xmlns="http://www.w3.org/2000/svg" width="240" height="240" viewBox="0 0 180 180" style="fill: currentcolor;"><path d="M0 90v90h180V0H0v90zm108.3-63.3c9.2 2.7 20.6 9.4 25.8 15.2 10.1 11.4 13.3 21.2 12.8 39.1-.4 10.7-.7 12-4 19-4 8.5-11.6 17.3-18.7 21.9-2.6 1.7-8.4 4.3-12.8 5.7-6.5 2.2-9.7 2.7-18.4 2.7-12 .1-18.8-1.9-26.5-7.9L62 119v42H35.9l.3-48.3c.3-54.4 0-52.2 8.5-64.9C50.1 39.6 54 36.3 64.4 31c13.4-6.8 29.9-8.4 43.9-4.3z"/><path d="M81.9 49.5c-6.4 2-14.1 9.2-17 15.7-3.2 7.4-3.2 18.4.1 24.8 6.1 12.1 14.2 17.3 26.4 17.3 5.2 0 8.5-.6 12.3-2.3 17.2-7.4 22.6-30.8 10.8-46.2-6.7-8.8-21.2-13-32.6-9.3z"/></svg>';
    var text = document.createElement("span");
    text.className = "postimages-button--forkbb-text";
    text.innerText = "Postimage";
    but.appendChild(icon);
    but.appendChild(text);
    div.appendChild(but);
    return div;
};
postimage.init = function() {
    var areas = document.querySelectorAll(postimage.opt.selector);
    for (var i = 0; i < areas.length; i++) {
        var area = areas[i];
        if ((area.getAttribute("data-postimg") !== null)) {
            continue;
        }
        area.setAttribute("data-postimg", "pi_" + Math.floor(Math.random() * 1e9));
        postimage.insert(area, postimage.render("'" + area.getAttribute("data-postimg") + "'"));
    }
};
if (opener && !opener.closed && postimage.params.hasOwnProperty("postimage_id") && postimage.params.hasOwnProperty("postimage_text")) {
    postimage.output(postimage.params["postimage_id"], postimage.params["postimage_text"], true);
} else {
    if (typeof(window.addEventListener) == "function") {
        window.addEventListener("DOMContentLoaded", postimage.init, false);
        window.addEventListener("load", postimage.init, false);
    } else if (typeof(window.attachEvent) == "function") {
        window.attachEvent("onload", postimage.init);
    } else {
        if (window.onload != null) {
            var onload = window.onload;
            window.onload = function(e) {
                onload(e);
                postimage.init();
            };
        } else {
            window.onload = postimage.init;
        }
    }
}
postimage.ready = true;
