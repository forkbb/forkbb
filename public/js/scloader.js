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
        dataName = "data-SCEditorConfig",
        emotName = "data-smiliesEnabled",
        selector = "textarea[" + dataName + "]",
        textarea,
        options = {
            format: 'bbcode',
            icons: 'monocons',
            style: '',
            emoticonsCompat: true,
            emoticonsEnabled: false,
            resizeWidth: false,
            width: '100%',
            toolbar: 'bold,italic,underline,strike,subscript,superscript|' +
                'left,center,right,justify|font,size,color,removeformat|' +
                'bulletlist,orderedlist,indent,outdent|' +
                'table|code,quote|horizontalrule,image,email,link,unlink|' +
                'emoticon,date,time|maximize,source'
        };

    function trimHTML(str)
    {
        return str.replace(/^\x20*<br \/>/, '').replace(/<br \/>\x20*$/, '');
    }

    /**
     * Removes any leading or trailing quotes ('")
     *
     * @return string
     * @since v1.4.0
     */
    function _stripQuotes(str) {
        return str ? str.replace(/\\(.)/g, '$1').replace(/^(["'])(.*?)\1$/, '$2') : str;
    }

    /**
     * Converts a number 0-255 to hex.
     *
     * Will return 00 if number is not a valid number.
     *
     * @param  {any} number
     * @return {string}
     * @private
     */
    function toHex(number) {
        number = parseInt(number, 10);

        if (isNaN(number)) {
            return '00';
        }

        number = Math.max(0, Math.min(number, 255)).toString(16);

        return number.length < 2 ? '0' + number : number;
    }

    /**
     * Normalises a CSS colour to hex #xxxxxx format
     *
     * @param  {string} colorStr
     * @return {string}
     * @private
     */
    function _normaliseColour(colorStr) {
        var match;

        colorStr = colorStr || '#000';

        // rgb(n,n,n);
        if ((match =
            colorStr.match(/rgb\((\d{1,3}),\s*?(\d{1,3}),\s*?(\d{1,3})\)/i))) {
            return '#' +
                toHex(match[1]) +
                toHex(match[2]) +
                toHex(match[3]);
        }

        // expand shorthand
        if ((match = colorStr.match(/#([0-9a-f])([0-9a-f])([0-9a-f])\s*?$/i))) {
            return '#' +
                match[1] + match[1] +
                match[2] + match[2] +
                match[3] + match[3];
        }

        return colorStr;
    }


    function initPlugin()
    {
        sceditor.plugins['forkbb'] = function () {
            var escapeEntities  = sceditor.escapeEntities;
            var escapeUriScheme = sceditor.escapeUriScheme;
            var dom             = sceditor.dom;
            var utils           = sceditor.utils;
            var QuoteType       = sceditor.BBCodeParser.QuoteType;

            var css    = dom.css;
            var attr   = dom.attr;
            var is     = dom.is;
            var extend = utils.extend;
            var each   = utils.each;
            var base   = this;

            base.init = function () {
                var opts = this.opts;

                // Enable for BBCode only
                if (!opts.format || opts.format !== 'bbcode') {
                    return;
                }

// START_COMMAND: Bold
                sceditor.formats.bbcode.set('b', {
                    tags: {
                        b: null,
                        strong: null
                    },
                    styles: {
                        // 401 is for FF 3.5
                        'font-weight': ['bold', 'bolder', '401', '700', '800', '900']
                    },
                    format: '[b]{0}[/b]',
                    html: function (token, attrs, content) {
                        return '<b>' + trimHTML(content) + '</b>';
                    }
                });

// START_COMMAND: Italic
                sceditor.formats.bbcode.set('i', {
                    tags: {
                        i: null,
                        em: null
                    },
                    styles: {
                        'font-style': ['italic', 'oblique']
                    },
                    format: '[i]{0}[/i]',
                    html: function (token, attrs, content) {
                        return  '<i>' + trimHTML(content) + '</i>';
                    }
                });

// START_COMMAND: Underline
                sceditor.formats.bbcode.set('u', {
                    tags: {
                        u: null
                    },
                    styles: {
                        'text-decoration': ['underline']
                    },
                    format: '[u]{0}[/u]',
                    html: function (token, attrs, content) {
                        return  '<u>' + trimHTML(content) + '</u>';
                    }
                });

// START_COMMAND: Strikethrough
                sceditor.formats.bbcode.set('s', {
                    tags: {
                        s: null,
                        strike: null
                    },
                    styles: {
                        'text-decoration': ['line-through']
                    },
                    format: '[s]{0}[/s]',
                    html: function (token, attrs, content) {
                        return  '<s>' + trimHTML(content) + '</s>';
                    }
                });

// START_COMMAND: Subscript
                sceditor.formats.bbcode.set('sub', {
                    tags: {
                        sub: null
                    },
                    format: '[sub]{0}[/sub]',
                    html: function (token, attrs, content) {
                        return  '<sub>' + trimHTML(content) + '</sub>';
                    }
                });

// START_COMMAND: Superscript
                sceditor.formats.bbcode.set('sup', {
                    tags: {
                        sup: null
                    },
                    format: '[sup]{0}[/sup]',
                    html: function (token, attrs, content) {
                        return  '<sup>' + trimHTML(content) + '</sup>';
                    }
                });

// START_COMMAND: Font
                sceditor.formats.bbcode.set('font', {
                    tags: {
                        font: {
                            face: null
                        }
                    },
                    styles: {
                        'font-family': null
                    },
                    quoteType: QuoteType.never,
                    format: function (element, content) {
                        var font;

                        if (!is(element, 'font') || !(font = attr(element, 'face'))) {
                            font = css(element, 'font-family');
                        }

                        return '[font=' + _stripQuotes(font) + ']' + content + '[/font]';
                    },
                    html: function (token, attrs, content) {
                        return  '<font face="' + escapeEntities(attrs.defaultattr) + '">' + trimHTML(content) + '</font>';
                    }
                });

// START_COMMAND: Size
                sceditor.formats.bbcode.set('size:', {
                    tags: {
                        font: {
                            size: null
                        }
                    },
                    styles: {
                        'font-size': null
                    },
                    format: function (element, content) {
                        var    fontSize = attr(element, 'size'),
                            size     = 2;

                        if (!fontSize) {
                            fontSize = css(element, 'fontSize');
                        }

                        // Most browsers return px value but IE returns 1-7
                        if (fontSize.indexOf('px') > -1) {
                            // convert size to an int
                            fontSize = fontSize.replace('px', '') - 0;

                            if (fontSize < 12) {
                                size = 1;
                            }
                            if (fontSize > 15) {
                                size = 3;
                            }
                            if (fontSize > 17) {
                                size = 4;
                            }
                            if (fontSize > 23) {
                                size = 5;
                            }
                            if (fontSize > 31) {
                                size = 6;
                            }
                            if (fontSize > 47) {
                                size = 7;
                            }
                        } else {
                            size = fontSize;
                        }

                        return '[size=' + size + ']' + content + '[/size]';
                    },
                    html: function (token, attrs, content) {
                        return  '<font size="' + escapeEntities(attrs.defaultattr) + '">' + trimHTML(content) + '</font>';
                    }
                });

// START_COMMAND: Color
                sceditor.formats.bbcode.set('color', {
                    tags: {
                        font: {
                            color: null
                        }
                    },
                    styles: {
                        color: null
                    },
                    quoteType: QuoteType.never,
                    format: function (elm, content) {
                        var    color;

                        if (!is(elm, 'font') || !(color = attr(elm, 'color'))) {
                            color = elm.style.color || css(elm, 'color');
                        }

                        return '[color=' + _normaliseColour(color) + ']' + content + '[/color]';
                    },
                    html: function (token, attrs, content) {
                        return '<font color="' + escapeEntities(_normaliseColour(attrs.defaultattr), true) + '">' + trimHTML(content) + '</font>';
                    }
                });

                sceditor.formats.bbcode.set('', {
                });

                sceditor.formats.bbcode.set('', {
                });

                sceditor.formats.bbcode.set('', {
                });

                sceditor.formats.bbcode.set('', {
                });

                sceditor.formats.bbcode.set('', {
                });

                sceditor.formats.bbcode.set('', {
                });

// START_COMMAND: Code
/*                sceditor.formats.bbcode.set('code', {
                    tags: {
                        code: null
                    },
                    isInline: false,
                    allowedChildren: ['#', '#newline'],
                    format: '[code]{0}[/code]',
                    html: function (token, attrs, content) {
                        return  '<code>' + trimHTML(content) + '</code>';
                    }
                });
*/
            }
        }
    }

    function initEditor()
    {
        var conf, smiliesEnabled;

        if (
            !sceditor
            || !(textarea = doc.querySelector(selector))
            || !(conf = JSON.parse(textarea.getAttribute(dataName)))
        ) {
            return;
        }

        options = Object.assign(options, conf);
        smiliesEnabled = '1' == textarea.getAttribute(emotName);

        if (!smiliesEnabled) {
            options.toolbar = options.toolbar.replace(/\bemoticon\b/, '').replace(/[^\w]*\|[^\w]*/g, '|').replace(/,{2,}/g, ',') ;
        }

        initPlugin();

        sceditor.create(textarea, options);
        instance = sceditor.instance(textarea);

        if (smiliesEnabled) {
            var checkbox = doc.querySelector('input[name="hide_smilies"]');

            if (checkbox) {
                checkbox.addEventListener('change', function (e) {
                    instance.emoticons(!e.target.checked);
                });
                instance.emoticons(!checkbox.checked);
            } else {
                instance.emoticons(true);
            }
        }
    }

    return {
        init : function () {
            initEditor();
        },
    };
}(document, window));

if (document.addEventListener && Object.assign) {
    document.addEventListener("DOMContentLoaded", ForkBB.editor.init, false);
}
