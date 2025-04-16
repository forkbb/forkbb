! function() {
    var e = {
        defaultSettings: {
            url: "https://imgbb.com/upload",
            vendor: "forkbb",
            mode: "auto",
            lang: "auto",
            autoInsert: "bbcode-embed-thumbnail",
            palette: "default",
            init: "onload",
            containerClass: 1,
            buttonClass: 1,
            sibling: 0,
            siblingPos: "after",
            fitEditor: 0,
            observe: 0,
            observeCache: 1,
            html: "",
            css: ""
        },
        ns: {
            plugin: "imgbb"
        },
        palettes: {
            default: ["#ececec", "#2980b9", "#2980b9", "#fff"],
            clear: ["inherit", "inherit", "inherit", "#2980b9"],
            turquoise: ["#16a085", "#fff", "#1abc9c", "#fff"],
            green: ["#27ae60", "#fff", "#2ecc71", "#fff"],
            blue: ["#2980b9", "#fff", "#3498db", "#fff"],
            purple: ["#8e44ad", "#fff", "#9b59b6", "#fff"],
            darkblue: ["#2c3e50", "#fff", "#34495e", "#fff"],
            yellow: ["#f39c12", "#fff", "#f1c40f", "#fff"],
            orange: ["#d35400", "#fff", "#e67e22", "#fff"],
            red: ["#c0392b", "#fff", "#e74c3c", "#fff"],
            grey: ["#ececec", "#000", "#e0e0e0", "#000"],
            black: ["#333", "#fff", "#666", "#fff"]
        },
        classProps: ["button", "container"],
        iconSvg: '<svg class="%iClass" xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"><path d="M76.7 87.5c12.8 0 23.3-13.3 23.3-29.4 0-13.6-5.2-25.7-15.4-27.5 0 0-3.5-0.7-5.6 1.7 0 0 0.6 9.4-2.9 12.6 0 0 8.7-32.4-23.7-32.4 -29.3 0-22.5 34.5-22.5 34.5 -5-6.4-0.6-19.6-0.6-19.6 -2.5-2.6-6.1-2.5-6.1-2.5C10.9 25 0 39.1 0 54.6c0 15.5 9.3 32.7 29.3 32.7 2 0 6.4 0 11.7 0V68.5h-13l22-22 22 22H59v18.8C68.6 87.4 76.7 87.5 76.7 87.5z" style="fill: currentcolor;"/></svg>',
        l10n: {
            ar: "تحميل الصور",
            cs: "Nahrát obrázky",
            da: "Upload billeder",
            de: "Bilder hochladen",
            es: "Subir imágenes",
            fi: "Lataa kuvia",
            fr: "Importer des images",
            id: "Unggah gambar",
            it: "Carica immagini",
            ja: "画像をアップロード",
            nb: "Last opp bilder",
            nl: "Upload afbeeldingen",
            pl: "Wyślij obrazy",
            pt_BR: "Enviar imagens",
            ru: "Загрузить изображения",
            tr: "Resim Yukle",
            uk: "Завантажити зображення",
            zh_CN: "上传图片",
            zh_TW: "上傳圖片"
        },
        vendors: {
            forkbb: {
                settings: {
                    autoInsert: "bbcode-embed-medium",
                    sibling: ".sceditor-container",
                    html: '<div class="%cClass"><button %x class="%bClass" title="%text"><span class="%iClass">%iconSvg</span><span class="%tClass">ImgBB</span></button></div>',
                    css: "#fork .%cClass{display:inline-block;margin-top:0.3125rem}#fork .%bClass{line-height:normal;transition:all .2s;outline:0;color:%2;border:none;cursor:pointer;border:.0625rem solid rgba(0,0,0,.15);background:%1;border-radius:.2rem;padding:.3125rem .625rem;font-size:0.75rem;font-weight:700;text-shadow:none}#fork .%bClass:hover{background:%3;color:%4;border-color:rgba(0,0,0,.1)}#fork .%iClass,.%tClass{display:inline-block;vertical-align:middle}#fork .%iClass svg{display:block;width:1rem;height:1rem;fill:currentColor}#fork .%tClass{padding-inline-start:.25rem}"
                },
                check: "ForkBB",
                getEditor: function() {
                    return document.getElementById("id-dl-message");
                },
                editorValue: function(t) {
                    var m = t.match(/\s*(\[img\][^\[\]]+\[\/img\])\s*/);
                    if (m) {
                        t = t.replace(m[0], "{img}");
                        var n = t.match(/\[url=[^\[\]]+\]{img}\[\/url\]/);
                        t = n ? n[0].replace("{img}", m[1]) : m[1];
                        ForkBB.editor.getInstance().insert(t);
                    }
                },
                useCustomEditor: function() {
                    return !!ForkBB.editor;
                }
            }
        },
        generateGuid: function() {
            var i = (new Date).getTime();
            return "undefined" != typeof performance && "function" == typeof performance.now && (i += performance.now()), "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g, function(t) {
                var e = (i + 16 * Math.random()) % 16 | 0;
                return i = Math.floor(i / 16), ("x" === t ? e : 3 & e | 8).toString(16)
            })
        },
        getNewValue: function(t, e) {
            var i = "string" != typeof t.getAttribute("contenteditable") ? "value" : "innerHTML",
                s = "value" == i ? "\n" : "<br>",
                t = t[i],
                i = e;
            if (!1, 0 == t.length) return i;
            e = "", t = t.match(/\n+$/g), t = t ? t[0].split("\n").length : 0;
            return t <= 2 && (e += s.repeat(0 == t ? 2 : 1)), e + i
        },
        insertTrigger: function() {
            var t, e = this.vendors[this.settings.vendor],
                i = this.settings.sibling ? document.querySelectorAll(this.settings.sibling + ":not([" + this.ns.dataPlugin + "])")[0] : 0;
            if ("auto" == this.settings.mode) t = this.vendors[e.hasOwnProperty("getEditor") ? this.settings.vendor : "default"].getEditor();
            else {
                for (var s = document.querySelectorAll("[" + this.ns.dataPluginTrigger + "][data-target]:not([" + this.ns.dataPluginId + "])"), n = [], r = 0; r < s.length; r++) n.push(s[r].dataset.target);
                0 < n.length && (t = document.querySelectorAll(n.join(",")))
            }
            if (t) {
                !document.getElementById(this.ns.pluginStyle) && this.settings.css && (o = document.createElement("style"), a = this.settings.css, a = this.appyTemplate(a), o.innerHTML = a.replace(/%p/g, "." + this.ns.plugin), o.setAttribute("id", this.ns.pluginStyle), document.body.appendChild(o)), t instanceof NodeList || (t = [t]);
                for (var o, a, l, u = 0, r = 0; r < t.length; r++) t[r].getAttribute(this.ns.dataPluginTarget) || ((l = i || t[r]).setAttribute(this.ns.dataPlugin, "sibling"), l.insertAdjacentHTML({
                    before: "beforebegin",
                    after: "afterend"
                } [this.settings.siblingPos], this.appyTemplate(this.settings.html)), l = l.parentElement.querySelector("[" + this.ns.dataPluginTrigger + "]"), this.setBoundId(l, t[r]), u++);
                this.triggerCounter = u, "function" == typeof e.callback && e.callback.call()
            }
        },
        appyTemplate: function(t) {
            if (!this.cacheTable) {
                var e = [{
                    "%iconSvg": this.iconSvg
                }, {
                    "%text": this.settings.langString
                }];
                if (this.palette) {
                    for (var i = /%(\d+)/g, s = i.exec(t), n = []; null !== s;) - 1 == n.indexOf(s[1]) && n.push(s[1]), s = i.exec(t);
                    if (n) {
                        n.sort(function(t, e) {
                            return e - t
                        });
                        this.vendors[this.settings.vendor];
                        for (var r = 0; r < n.length; r++) {
                            var o = n[r] - 1,
                                a = this.palette[o] || "",
                                o = (a || "default" === this.settings.vendor || "default" === this.settings.palette || (a = this.palette[o - 2]), {});
                            o["%" + n[r]] = a, e.push(o)
                        }
                    }
                }
                for (var l = this.settings.buttonClass || this.ns.plugin + "-button", u = [{
                        "%cClass": this.settings.containerClass || this.ns.plugin + "-container"
                    }, {
                        "%bClass": l
                    }, {
                        "%iClass": l + "-icon"
                    }, {
                        "%tClass": l + "-text"
                    }, {
                        "%x": this.ns.dataPluginTrigger
                    }, {
                        "%p": this.ns.plugin
                    }], r = 0; r < u.length; r++) e.push(u[r]);
                this.cacheTable = e
            }
            return this.strtr(t, this.cacheTable)
        },
        strtr: function(t, e) {
            if (!(t = t.toString()) || void 0 === e) return t;
            for (var i = 0; i < e.length; i++) {
                var s, n = e[i];
                for (s in n) void 0 !== n[s] && (re = new RegExp(s, "g"), t = t.replace(re, n[s]))
            }
            return t
        },
        setBoundId: function(t, e) {
            var i = this.generateGuid();
            t.setAttribute(this.ns.dataPluginId, i), e.setAttribute(this.ns.dataPluginTarget, i)
        },
        openPopup: function(t) {
            if ("string" == typeof t) {
                var e = this;
                if (void 0 === this.popups && (this.popups = {}), void 0 === this.popups[t]) {
                    this.popups[t] = {};
                    var i, s = {
                            l: null != window.screenLeft ? window.screenLeft : screen.left,
                            t: null != window.screenTop ? window.screenTop : screen.top,
                            w: window.innerWidth || document.documentElement.clientWidth || screen.width,
                            h: window.innerHeight || document.documentElement.clientHeight || screen.height
                        },
                        n = {
                            w: 720,
                            h: 690
                        },
                        r = {
                            w: .5,
                            h: .85
                        };
                    for (i in n) n[i] / s[i] > r[i] && (n[i] = s[i] * r[i]);
                    var o = Math.trunc(s.w / 2 - n.w / 2 + s.l),
                        a = Math.trunc(s.h / 2 - n.h / 2 + s.t);
                    this.popups[t].window = window.open(this.settings.url, t, "width=" + n.w + ",height=" + n.h + ",top=" + a + ",left=" + o), this.popups[t].timer = window.setInterval(function() {
                        e.popups[t].window && !1 === e.popups[t].window.closed || (window.clearInterval(e.popups[t].timer), e.popups[t] = void 0)
                    }, 200)
                } else this.popups[t].window.focus()
            }
        },
        postSettings: function(t) {
            this.popups[t].window.postMessage({
                id: t,
                settings: this.settings
            }, this.settings.url)
        },
        liveBind: function(n, t, r) {
            document.addEventListener(t, function(t) {
                var e = document.querySelectorAll(n);
                if (e) {
                    for (var i = t.target, s = -1; i && -1 === (s = Array.prototype.indexOf.call(e, i));) i = i.parentElement; - 1 < s && (t.preventDefault(), r.call(t, i))
                }
            }, !0)
        },
        prepare: function() {
            var e = this,
                t = (this.ns.dataPlugin = "data-" + this.ns.plugin, this.ns.dataPluginId = this.ns.dataPlugin + "-id", this.ns.dataPluginTrigger = this.ns.dataPlugin + "-trigger", this.ns.dataPluginTarget = this.ns.dataPlugin + "-target", this.ns.pluginStyle = this.ns.plugin + "-style", this.ns.selDataPluginTrigger = "[" + this.ns.dataPluginTrigger + "]", document.currentScript || document.getElementById(this.ns.plugin + "-src")),
                i = (t ? t.dataset.buttonTemplate && (t.dataset.html = t.dataset.buttonTemplate) : t = {
                    dataset: {}
                }, 0);
            for (n in this.settings = {}, this.defaultSettings) {
                var s = (t && t.dataset[n] ? t.dataset : this.defaultSettings)[n];
                "string" == typeof(s = "1" === s || "0" === s ? "true" == s : s) && -1 < this.classProps.indexOf(n.replace(/Class$/, "")) && (i = 1), this.settings[n] = s
            }
            var r = ["lang", "url", "vendor", "target"],
                o = ("default" == this.settings.vendor && (this.vendors.default.settings = {}), this.vendors[this.settings.vendor]);
            if (o.settings)
                for (var n in o.settings) t && t.dataset.hasOwnProperty(n) || (this.settings[n] = o.settings[n]);
            else
                for (var n in o.settings = {}, this.defaultSettings) - 1 == r.indexOf(n) && (o.settings[n] = this.defaultSettings[n]);
            if ("default" !== this.settings.vendor)
                if (o.settings.hasOwnProperty("fitEditor") || t.dataset.hasOwnProperty("fitEditor") || (this.settings.fitEditor = 1), this.settings.fitEditor) i = !o.settings.css;
                else {
                    r = ["autoInsert", "observe", "observeCache"];
                    for (n in o.settings) - 1 != r.indexOf(n) || t.dataset.hasOwnProperty(n) || (this.settings[n] = this.defaultSettings[n])
                }
                i ? this.settings.css = "" : (this.settings.css = this.settings.css.replace("%defaultCSS", this.defaultSettings.css), o.settings.extracss && this.settings.css && (this.settings.css += o.settings.extracss), 1 < (l = this.settings.palette.split(",")).length ? this.palette = l : this.palettes.hasOwnProperty(l) || (this.settings.palette = "default"), this.palette || (this.palette = (this.settings.fitEditor && o.palettes && o.palettes[this.settings.palette] ? o : this).palettes[this.settings.palette]));
            for (var d = this.classProps, a = 0; a < d.length; a++) {
                var c = d[a] + "Class";
                "string" != typeof this.settings[c] && (this.settings[c] = this.ns.plugin + "-" + d[a], this.settings.fitEditor && (this.settings[c] += "--" + this.settings.vendor))
            }
            var l = ("auto" == this.settings.lang ? navigator.language || navigator.userLanguage : this.settings.lang).replace("-", "_"),
                l = (this.settings.langString = "Upload images", l in this.l10n ? l : l.substring(0, 2) in this.l10n ? l.substring(0, 2) : null),
                l = (l && (this.settings.langString = this.l10n[l]), document.createElement("a")),
                u = (l.href = this.settings.url, this.originUrlPattern = "^" + (l.protocol + "//" + l.hostname).replace(/\./g, "\\.").replace(/\//g, "\\/") + "$", document.querySelectorAll(this.ns.selDataPluginTrigger + "[data-target]"));
            if (0 < u.length)
                for (a = 0; a < u.length; a++) {
                    var g = document.querySelector(u[a].dataset.target);
                    this.setBoundId(u[a], g)
                }
            this.settings.observe && (l = this.settings.observe, this.settings.observeCache && (l += ":not([" + this.ns.dataPlugin + "])"), this.liveBind(l, "click", function(t) {
                t.setAttribute(e.ns.dataPlugin, 1), e.observe()
            }.bind(this))), this.settings.sibling && !this.settings.onDemand ? this.waitForSibling() : "onload" == this.settings.init ? "loading" === document.readyState ? document.addEventListener("DOMContentLoaded", function(t) {
                e.init()
            }, !1) : this.init() : this.observe()
        },
        observe: function() {
            this.waitForSibling("observe")
        },
        waitForSibling: function(t) {
            var e = this.initialized ? "insertTrigger" : "init";
            if (this.settings.sibling) var i = document.querySelector(this.settings.sibling + ":not([" + this.ns.dataPlugin + "])");
            else if ("observe" == t && (this[e](), this.triggerCounter)) return;
            i ? this[e]() : "complete" === document.readyState && "observe" !== t || setTimeout(("observe" == t ? this.observe : this.waitForSibling).bind(this), 250)
        },
        init: function() {
            this.insertTrigger();
            var o = this,
                a = this.vendors[this.settings.vendor];
            this.liveBind(this.ns.selDataPluginTrigger, "click", function(t) {
                t = t.getAttribute(o.ns.dataPluginId);
                o.openPopup(t)
            }), window.addEventListener("message", function(t) {
                if (new RegExp(o.originUrlPattern, "i").test(t.origin) || void 0 !== t.data.id && void 0 !== t.data.message) {
                    var e, i = t.data.id;
                    if (i && t.source === o.popups[i].window)
                        if (t.data.requestAction && o.hasOwnProperty(t.data.requestAction)) o[t.data.requestAction](i);
                        else {
                            if ("default" !== o.settings.vendor) {
                                if (a.hasOwnProperty("useCustomEditor") && a.useCustomEditor()) return void a.editorValue(t.data.message, i);
                                a.hasOwnProperty("getEditor") && (e = a.getEditor())
                            }
                            if (e = e || document.querySelector("[" + o.ns.dataPluginTarget + "=\"" + i + "\"]"))
                                for (var i = null === e.getAttribute("contenteditable") ? "value" : "innerHTML", s = (e[i] += o.getNewValue(e, t.data.message), ["blur", "focus", "input", "change", "paste"]), n = 0; n < s.length; n++) {
                                    var r = new Event(s[n]);
                                    e.dispatchEvent(r)
                                } else alert("Target not found")
                        }
                }
            }, !1), this.initialized = 1
        }
    };
    e.prepare()
}();
