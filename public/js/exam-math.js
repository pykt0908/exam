/**
 * Exam Math System (MathJax 3 + OMML / MathML Converter + Summernote Integration)
 * รองรับการคัดลอกจาก Microsoft Word (OMML, MathML, LaTeX) มาวางได้โดยตรง
 * และเรนเดอร์สูตรคณิตศาสตร์ (สแควรูท, เศษส่วน, เลขยกกำลัง ฯลฯ) สวยงามทุกหน้า
 */

(function () {
    'use strict';

    // 1. กำหนดค่า MathJax 3 ล่วงหน้า (ก่อนตัวสคริปต์โหลด)
    window.MathJax = {
        tex: {
            inlineMath: [['$', '$'], ['\\(', '\\)']],
            displayMath: [['$$', '$$'], ['\\[', '\\]']],
            processEscapes: true,
            processEnvironments: true
        },
        options: {
            enableMenu: false,
            skipHtmlTags: ['script', 'noscript', 'style', 'textarea', 'pre', 'code']
        },
        startup: {
            pageReady: function () {
                return MathJax.startup.defaultPageReady().then(function () {
                    window.mathJaxReady = true;
                    if (window.renderMath) {
                        window.renderMath();
                    }
                });
            }
        }
    };

    // 2. ฟังก์ชัน Global สำหรับสั่ง Render สูตรคณิตศาสตร์บนหน้าเว็บ
    window.renderMath = function (target) {
        if (window.MathJax && window.MathJax.typesetPromise) {
            var elements;
            if (target) {
                if (typeof target === 'string') {
                    elements = document.querySelectorAll(target);
                } else if (window.jQuery && target instanceof jQuery) {
                    elements = target.toArray();
                } else if (Array.isArray(target)) {
                    elements = target;
                } else if (target instanceof Element) {
                    elements = [target];
                }
            }
            return window.MathJax.typesetPromise(elements).then(function () {
                var roots = elements || [document.body];
                roots.forEach(function (root) {
                    if (root && root.querySelectorAll) {
                        var containers = root.querySelectorAll('mjx-container');
                        containers.forEach(function (c) {
                            c.setAttribute('contenteditable', 'false');
                        });
                    }
                });
            }).catch(function (err) {
                console.warn('MathJax render error:', err);
            });
        }
        return Promise.resolve();
    };

    // ถอดรหัสสูตรคณิตศาสตร์กลับเป็นข้อความสะอาด (LaTeX \(...\) หรือ MathML) ก่อนส่งบันทึกลงฐานข้อมูล
    window.serializeMathHtmlForSave = function (html) {
        if (!html || typeof html !== 'string') return html || '';

        // 1. ถ้ามี span.exam-math-formula ที่มี data-latex
        html = html.replace(/<span[^>]*class=["']?[^"']*exam-math-formula[^"']*["']?[^>]*data-latex=["']([^"']*)["'][^>]*>[\s\S]*?<\/span>/gi, function (match, latex) {
            var decoded = latex.replace(/&quot;/g, '"').replace(/&amp;/g, '&');
            return '\\(' + decoded.trim() + '\\)';
        });

        // 2. ถ้ามี mjx-container ที่มี data-latex
        html = html.replace(/<mjx-container[^>]*data-latex=["']([^"']*)["'][^>]*>[\s\S]*?<\/mjx-container>/gi, function (match, latex) {
            var decoded = latex.replace(/&quot;/g, '"').replace(/&amp;/g, '&');
            return '\\(' + decoded.trim() + '\\)';
        });

        // 3. ถ้ามี mjx-container ที่มี MathML ภายใน
        html = html.replace(/<mjx-container[^>]*>[\s\S]*?(<math[\s\S]*?<\/math>)[\s\S]*?<\/mjx-container>/gi, function (match, mathml) {
            return mathml;
        });

        // 4. ลบแท็ก mjx-* ใดๆ ที่อาจตกค้าง
        html = html.replace(/<mjx-[^>]+>/gi, '').replace(/<\/mjx-[^>]+>/gi, '');

        return html;
    };

    // 3. ตัวแปลง OMML (Microsoft Word Equation) -> MathML
    function findDirectChild(parent, tagName) {
        if (!parent || !parent.childNodes) return null;
        for (var i = 0; i < parent.childNodes.length; i++) {
            var c = parent.childNodes[i];
            var name = (c.localName || c.nodeName || '').replace(/^.*:/, '');
            if (name.toLowerCase() === tagName.toLowerCase()) {
                return c;
            }
        }
        return null;
    }

    function findDirectChildren(parent, tagName) {
        var results = [];
        if (!parent || !parent.childNodes) return results;
        for (var i = 0; i < parent.childNodes.length; i++) {
            var c = parent.childNodes[i];
            var name = (c.localName || c.nodeName || '').replace(/^.*:/, '');
            if (name.toLowerCase() === tagName.toLowerCase()) {
                results.push(c);
            }
        }
        return results;
    }

    function createMrow(ommlChildNode) {
        var mrow = document.createElementNS('http://www.w3.org/1998/Math/MathML', 'mrow');
        if (ommlChildNode) {
            for (var i = 0; i < ommlChildNode.childNodes.length; i++) {
                var converted = convertOmmlNodeToMathML(ommlChildNode.childNodes[i]);
                if (converted) mrow.appendChild(converted);
            }
        }
        return mrow;
    }

    function appendParsedMathText(parentMrow, rawText) {
        if (!rawText) return;
        var i = 0;
        while (i < rawText.length) {
            var ch = rawText.charAt(i);

            // Numbers
            if (/[0-9.]/.test(ch)) {
                var num = '';
                while (i < rawText.length && /[0-9.]/.test(rawText.charAt(i))) {
                    num += rawText.charAt(i);
                    i++;
                }
                var mn = document.createElementNS('http://www.w3.org/1998/Math/MathML', 'mn');
                mn.textContent = num;
                parentMrow.appendChild(mn);
            }
            // Alphabetic variables / symbols
            else if (/[a-zA-Z\u0370-\u03FF]/.test(ch)) {
                var mi = document.createElementNS('http://www.w3.org/1998/Math/MathML', 'mi');
                mi.textContent = ch;
                parentMrow.appendChild(mi);
                i++;
            }
            // Operators and punctuation
            else if (/[+\-*/=<>≤≥≠±()[\],;:!?]/.test(ch)) {
                var mo = document.createElementNS('http://www.w3.org/1998/Math/MathML', 'mo');
                mo.textContent = ch;
                parentMrow.appendChild(mo);
                i++;
            }
            // Whitespace or other text
            else {
                var txt = ch;
                i++;
                while (i < rawText.length && !/[0-9.a-zA-Z\u0370-\u03FF+\-*/=<>≤≥≠±()[\],;:!?]/.test(rawText.charAt(i))) {
                    txt += rawText.charAt(i);
                    i++;
                }
                var mtext = document.createElementNS('http://www.w3.org/1998/Math/MathML', 'mtext');
                mtext.textContent = txt;
                parentMrow.appendChild(mtext);
            }
        }
    }

    function convertOmmlNodeToMathML(node) {
        if (!node) return null;
        var nodeName = (node.localName || node.nodeName || '').replace(/^.*:/, '').toLowerCase();

        switch (nodeName) {
            case 'omathpara':
            case 'omath': {
                var math = document.createElementNS('http://www.w3.org/1998/Math/MathML', 'math');
                math.setAttribute('xmlns', 'http://www.w3.org/1998/Math/MathML');
                math.setAttribute('display', nodeName === 'omathpara' ? 'block' : 'inline');
                for (var i = 0; i < node.childNodes.length; i++) {
                    var child = convertOmmlNodeToMathML(node.childNodes[i]);
                    if (child) math.appendChild(child);
                }
                return math;
            }
            case 'f': { // Fraction
                var mfrac = document.createElementNS('http://www.w3.org/1998/Math/MathML', 'mfrac');
                var numNode = findDirectChild(node, 'num');
                var denNode = findDirectChild(node, 'den');
                mfrac.appendChild(createMrow(numNode));
                mfrac.appendChild(createMrow(denNode));
                return mfrac;
            }
            case 'rad': { // Radical / Root
                var radPr = findDirectChild(node, 'radPr');
                var degHide = false;
                if (radPr) {
                    var dh = findDirectChild(radPr, 'degHide');
                    if (dh) {
                        var val = dh.getAttribute('m:val') || dh.getAttribute('val');
                        if (val === '1' || val === 'on' || val === 'true') {
                            degHide = true;
                        }
                    }
                }
                var degNode = findDirectChild(node, 'deg');
                var baseNode = findDirectChild(node, 'e');
                if (degHide || !degNode || !degNode.textContent.trim()) {
                    var msqrt = document.createElementNS('http://www.w3.org/1998/Math/MathML', 'msqrt');
                    msqrt.appendChild(createMrow(baseNode));
                    return msqrt;
                } else {
                    var mroot = document.createElementNS('http://www.w3.org/1998/Math/MathML', 'mroot');
                    mroot.appendChild(createMrow(baseNode));
                    mroot.appendChild(createMrow(degNode));
                    return mroot;
                }
            }
            case 'ssup': { // Superscript
                var msup = document.createElementNS('http://www.w3.org/1998/Math/MathML', 'msup');
                msup.appendChild(createMrow(findDirectChild(node, 'e')));
                msup.appendChild(createMrow(findDirectChild(node, 'sup')));
                return msup;
            }
            case 'ssub': { // Subscript
                var msub = document.createElementNS('http://www.w3.org/1998/Math/MathML', 'msub');
                msub.appendChild(createMrow(findDirectChild(node, 'e')));
                msub.appendChild(createMrow(findDirectChild(node, 'sub')));
                return msub;
            }
            case 'ssubsup': { // Sub-Superscript
                var msubsup = document.createElementNS('http://www.w3.org/1998/Math/MathML', 'msubsup');
                msubsup.appendChild(createMrow(findDirectChild(node, 'e')));
                msubsup.appendChild(createMrow(findDirectChild(node, 'sub')));
                msubsup.appendChild(createMrow(findDirectChild(node, 'sup')));
                return msubsup;
            }
            case 'd': { // Delimiter / brackets / parentheses
                var mrowD = document.createElementNS('http://www.w3.org/1998/Math/MathML', 'mrow');
                var dPr = findDirectChild(node, 'dPr');
                var begChr = '(';
                var endChr = ')';
                var sepChr = null;
                if (dPr) {
                    var b = findDirectChild(dPr, 'begChr');
                    var e = findDirectChild(dPr, 'endChr');
                    var s = findDirectChild(dPr, 'sepChr');
                    if (b) begChr = b.getAttribute('m:val') || b.getAttribute('val') || begChr;
                    if (e) endChr = e.getAttribute('m:val') || e.getAttribute('val') || endChr;
                    if (s) sepChr = s.getAttribute('m:val') || s.getAttribute('val');
                }
                if (begChr) {
                    var moOpen = document.createElementNS('http://www.w3.org/1998/Math/MathML', 'mo');
                    moOpen.textContent = begChr;
                    mrowD.appendChild(moOpen);
                }
                var eList = findDirectChildren(node, 'e');
                for (var idx = 0; idx < eList.length; idx++) {
                    if (idx > 0 && sepChr) {
                        var moSep = document.createElementNS('http://www.w3.org/1998/Math/MathML', 'mo');
                        moSep.textContent = sepChr;
                        mrowD.appendChild(moSep);
                    }
                    mrowD.appendChild(createMrow(eList[idx]));
                }
                if (endChr) {
                    var moClose = document.createElementNS('http://www.w3.org/1998/Math/MathML', 'mo');
                    moClose.textContent = endChr;
                    mrowD.appendChild(moClose);
                }
                return mrowD;
            }
            case 'r': { // Run / Text
                var mrowR = document.createElementNS('http://www.w3.org/1998/Math/MathML', 'mrow');
                var tNodes = findDirectChildren(node, 't');
                for (var t = 0; t < tNodes.length; t++) {
                    appendParsedMathText(mrowR, tNodes[t].textContent);
                }
                return mrowR;
            }
            case 'm': { // Matrix
                var mtable = document.createElementNS('http://www.w3.org/1998/Math/MathML', 'mtable');
                var mrList = findDirectChildren(node, 'mr');
                for (var r = 0; r < mrList.length; r++) {
                    var mtr = document.createElementNS('http://www.w3.org/1998/Math/MathML', 'mtr');
                    var eCells = findDirectChildren(mrList[r], 'e');
                    for (var c = 0; c < eCells.length; c++) {
                        var mtd = document.createElementNS('http://www.w3.org/1998/Math/MathML', 'mtd');
                        mtd.appendChild(createMrow(eCells[c]));
                        mtr.appendChild(mtd);
                    }
                    mtable.appendChild(mtr);
                }
                return mtable;
            }
            case 'nary': { // Integral, Summation, Product
                var naryPr = findDirectChild(node, 'naryPr');
                var chr = '∑';
                if (naryPr) {
                    var chrNode = findDirectChild(naryPr, 'chr');
                    if (chrNode) chr = chrNode.getAttribute('m:val') || chrNode.getAttribute('val') || chr;
                }
                var subNode = findDirectChild(node, 'sub');
                var supNode = findDirectChild(node, 'sup');
                var eNaryNode = findDirectChild(node, 'e');

                var mrowNary = document.createElementNS('http://www.w3.org/1998/Math/MathML', 'mrow');
                var moNary = document.createElementNS('http://www.w3.org/1998/Math/MathML', 'mo');
                moNary.textContent = chr;

                var hasSub = subNode && subNode.textContent.trim();
                var hasSup = supNode && supNode.textContent.trim();

                if (hasSub && hasSup) {
                    var munderover = document.createElementNS('http://www.w3.org/1998/Math/MathML', 'munderover');
                    munderover.appendChild(moNary);
                    munderover.appendChild(createMrow(subNode));
                    munderover.appendChild(createMrow(supNode));
                    mrowNary.appendChild(munderover);
                } else if (hasSub) {
                    var munder = document.createElementNS('http://www.w3.org/1998/Math/MathML', 'munder');
                    munder.appendChild(moNary);
                    munder.appendChild(createMrow(subNode));
                    mrowNary.appendChild(munder);
                } else if (hasSup) {
                    var mover = document.createElementNS('http://www.w3.org/1998/Math/MathML', 'mover');
                    mover.appendChild(moNary);
                    mover.appendChild(createMrow(supNode));
                    mrowNary.appendChild(mover);
                } else {
                    mrowNary.appendChild(moNary);
                }
                if (eNaryNode) {
                    mrowNary.appendChild(createMrow(eNaryNode));
                }
                return mrowNary;
            }
            case 'bar': { // Overbar
                var moverBar = document.createElementNS('http://www.w3.org/1998/Math/MathML', 'mover');
                moverBar.appendChild(createMrow(findDirectChild(node, 'e')));
                var moBar = document.createElementNS('http://www.w3.org/1998/Math/MathML', 'mo');
                moBar.textContent = '¯';
                moverBar.appendChild(moBar);
                return moverBar;
            }
            case 'e':
            case 'num':
            case 'den':
            case 'sub':
            case 'sup':
            case 'lim': {
                return createMrow(node);
            }
            default: {
                if (node.nodeType === 1) {
                    var frag = document.createDocumentFragment();
                    for (var k = 0; k < node.childNodes.length; k++) {
                        var chObj = convertOmmlNodeToMathML(node.childNodes[k]);
                        if (chObj) frag.appendChild(chObj);
                    }
                    return frag;
                }
                return null;
            }
        }
    }

    // แปลง MathML Element เป็น LaTeX String (สำหรับแปลงสูตร Word OMML -> LaTeX)
    window.convertMathMLToLatex = function (node) {
        if (!node) return '';
        var name = (node.localName || node.nodeName || '').replace(/^.*:/, '').toLowerCase();

        if (node.nodeType === 3) {
            return node.nodeValue || '';
        }
        if (node.nodeType !== 1) {
            return '';
        }

        function getChildrenLatex(parent) {
            var str = '';
            for (var i = 0; i < parent.childNodes.length; i++) {
                str += window.convertMathMLToLatex(parent.childNodes[i]);
            }
            return str;
        }

        function getDirectChildElements(parent) {
            var els = [];
            for (var i = 0; i < parent.childNodes.length; i++) {
                if (parent.childNodes[i].nodeType === 1) {
                    els.push(parent.childNodes[i]);
                }
            }
            return els;
        }

        switch (name) {
            case 'math':
            case 'mrow':
                return getChildrenLatex(node);
            case 'msqrt':
                return '\\sqrt{' + getChildrenLatex(node) + '}';
            case 'mroot': {
                var children = getDirectChildElements(node);
                var base = children[0] ? getChildrenLatex(children[0]) : '';
                var deg = children[1] ? getChildrenLatex(children[1]) : '';
                return '\\sqrt[' + deg + ']{' + base + '}';
            }
            case 'mfrac': {
                var fChildren = getDirectChildElements(node);
                var num = fChildren[0] ? getChildrenLatex(fChildren[0]) : '';
                var den = fChildren[1] ? getChildrenLatex(fChildren[1]) : '';
                return '\\frac{' + num + '}{' + den + '}';
            }
            case 'msup': {
                var supChildren = getDirectChildElements(node);
                var sBase = supChildren[0] ? window.convertMathMLToLatex(supChildren[0]) : '';
                var sExp = supChildren[1] ? getChildrenLatex(supChildren[1]) : '';
                return sBase + '^{' + sExp + '}';
            }
            case 'msub': {
                var subChildren = getDirectChildElements(node);
                var sbBase = subChildren[0] ? window.convertMathMLToLatex(subChildren[0]) : '';
                var sbExp = subChildren[1] ? getChildrenLatex(subChildren[1]) : '';
                return sbBase + '_{' + sbExp + '}';
            }
            case 'msubsup': {
                var subSupChildren = getDirectChildElements(node);
                var ssBase = subSupChildren[0] ? window.convertMathMLToLatex(subSupChildren[0]) : '';
                var ssSub = subSupChildren[1] ? getChildrenLatex(subSupChildren[1]) : '';
                var ssSup = subSupChildren[2] ? getChildrenLatex(subSupChildren[2]) : '';
                return ssBase + '_{' + ssSub + '}^{' + ssSup + '}';
            }
            case 'mo':
            case 'mi':
            case 'mn':
            case 'mtext':
                return node.textContent || '';
            default:
                return getChildrenLatex(node);
        }
    };

    // แปลงตัวเลขไทย (๐-๙) เป็นตัวเลขอารบิก (0-9)
    window.thaiToArabicDigits = function (str) {
        if (!str || typeof str !== 'string') return str;
        return str.replace(/[๐-๙]/g, function (d) {
            return String.fromCharCode(d.charCodeAt(0) - 3664 + 48);
        });
    };

    // จัดระยะเคาะวรรค (Spaces) และฟังก์ชันตรีโกณ/แคลคูลัส (sin, cos, log) ให้แสดงผลตรงตามที่พิมพ์จริง
    window.formatMathSpacing = function (str) {
        if (!str || typeof str !== 'string') return str;

        // 1. แปลงชื่อฟังก์ชันคณิตศาสตร์ที่ไม่มี backslash ให้นำหน้าด้วย \ (เช่น cos -> \cos, sin -> \sin)
        var mathFuncs = "(?:sin|cos|tan|cot|sec|csc|arcsin|arccos|arctan|log|ln|lim|exp|det|deg|gcd|min|max)";
        var rFunc = new RegExp("(^|[^\\\\a-zA-Z])(" + mathFuncs + ")(?![a-zA-Z])", "g");
        str = str.replace(rFunc, "$1\\$2");

        // 2. ถ้ามีการเคาะวรรคหลายครั้งติดต่อกัน (2 เคาะขึ้นไป) แปลงเป็น \  เท่ากับจำนวนที่เคาะ
        str = str.replace(/  +/g, function (match) {
            var res = '';
            for (var i = 0; i < match.length; i++) {
                res += '\\ ';
            }
            return res;
        });

        // 3. คงระยะเคาะวรรคเดี่ยวระหว่างพจน์/ตัวแปร/ฟังก์ชัน เพื่อไม่ให้ MathJax กลืนช่องว่างหายไป
        // เช่น "6x \cos" -> "6x\ \cos", "dx dy" -> "dx\ dy", "(x+1) (x-1)" -> "(x+1)\ (x-1)"
        str = str.replace(/([a-zA-Z0-9\)\}\]]) +(?=[a-zA-Z0-9\(\{\\\\])/g, '$1\\ ');

        // 4. คงระยะเคาะวรรคหลังฟังก์ชัน เช่น "\cos (3x)" -> "\cos\ (3x)", "\sin theta" -> "\sin\ theta"
        str = str.replace(/(\\\b(?:sin|cos|tan|cot|sec|csc|log|ln|lim|exp|det|deg|gcd|min|max)\b) +(?=[a-zA-Z0-9\(\{\\\\])/g, '$1\\ ');

        return str;
    };

    // ตรวจจับคำสั่ง LaTeX โดดๆ ที่อยู่ในข้อความ แล้วครอบด้วย \(...\) เพื่อให้คงวรรคตอนข้อความรอบข้างไว้
    window.autoWrapLatexInText = function (str) {
        if (!str || typeof str !== 'string') return str;

        // แปลง unicode superscript เป็น ^{...} เช่น 3x² -> 3x^{2}
        var supMap = { '⁰': '^0', '¹': '^1', '²': '^2', '³': '^3', '⁴': '^4', '⁵': '^5', '⁶': '^6', '⁷': '^7', '⁸': '^8', '⁹': '^9' };
        str = str.replace(/([a-zA-Z0-9\)])([⁰¹²³⁴⁵⁶⁷⁸⁹]+)/g, function (m, base, sups) {
            var res = '';
            for (var i = 0; i < sups.length; i++) {
                res += (supMap[sups[i]] || '').replace('^', '');
            }
            return base + '^{' + res + '}';
        });

        // แปลงฟังก์ชันตรีโกณ/แคลคูลัส
        var mathFuncs = "(?:sin|cos|tan|cot|sec|csc|arcsin|arccos|arctan|log|ln|lim|exp|det|deg|gcd)";
        var rFunc = new RegExp("(^|[^\\\\a-zA-Z])(" + mathFuncs + ")(?![a-zA-Z])", "g");
        str = str.replace(rFunc, "$1\\$2");

        // 1. ครอบคำสั่ง LaTeX มาตรฐาน เช่น \frac, \sqrt, \sin, \cos, \pm ฯลฯ
        var latexPattern = /(\\(?:frac\s*\{[^{}]*\}\s*\{[^{}]*\}|sqrt(?:\s*\[[^{}]*\])?\s*\{[^{}]*\}|sin|cos|tan|cot|sec|csc|arcsin|arccos|arctan|log|ln|lim|exp|det|deg|gcd|pm|times|div|le|ge|ne|approx|sum(?:_[^{}\s]+)?(?:\^[^{}\s]+)?|int(?:_[^{}\s]+)?(?:\^[^{}\s]+)?|infty|pi|theta|alpha|beta|Delta|vec\{[^{}]*\}|begin\{[a-z]+\}[\s\S]*?end\{[a-z]+\}))/g;

        str = str.replace(latexPattern, function (match, cmd, offset, full) {
            var before = full.substring(0, offset);
            var dollarCount = (before.match(/\$/g) || []).length;
            if (dollarCount % 2 === 1) return match; // อยู่ใน $...$ อยู่แล้ว
            var openParen = before.lastIndexOf('\\(');
            var closeParen = before.lastIndexOf('\\)');
            if (openParen > closeParen) return match; // อยู่ใน \(...\) อยู่แล้ว
            return '\\(' + cmd + '\\)';
        });

        // 2. ครอบเลขยกกำลัง เช่น 3x^2 หรือ x^{2}
        var expPattern = /([a-zA-Z0-9]+(?:\^[0-9a-zA-Z]+|\^\{[^{}]+\}))/g;
        str = str.replace(expPattern, function (match, exp, offset, full) {
            var before = full.substring(0, offset);
            var dollarCount = (before.match(/\$/g) || []).length;
            if (dollarCount % 2 === 1) return match;
            var openParen = before.lastIndexOf('\\(');
            var closeParen = before.lastIndexOf('\\)');
            if (openParen > closeParen) return match;
            return '\\(' + exp + '\\)';
        });

        return str;
    };

    // 4. ทำความสะอาดและแปลง HTML จาก Word Clipboard
    window.cleanWordMathHtml = function (html) {
        if (!html || typeof html !== 'string') return html;

        // ลบรูปภาพ fallback ที่อ้างอิง file:/// ซึ่ง Word ชอบใส่มาแล้วพัง
        html = html.replace(/<img[^>]+src=["']file:\/\/[^"']*["'][^>]*>/gi, '');

        // ค้นหา OMML ทั้งที่อยู่ใน <!--[if gte mso 9]><xml>... และที่อยู่นอกคอมเมนต์
        var ommlPattern = /(?:<!--\[if gte mso 9\]><xml>)?\s*(<(?:m:)?(?:oMathPara|oMath)\b[\s\S]*?<\/(?:m:)?(?:oMathPara|oMath)>)\s*(?:<\/xml><!\[endif\]-->)?/gi;

        if (ommlPattern.test(html)) {
            var parser = new DOMParser();

            html = html.replace(ommlPattern, function (match, ommlContent) {
                try {
                    var wrappedXml = '<root xmlns:m="http://schemas.openxmlformats.org/officeDocument/2006/math">' + ommlContent + '</root>';
                    var doc = parser.parseFromString(wrappedXml, 'application/xml');
                    if (doc.getElementsByTagName('parsererror').length > 0) {
                        return match;
                    }
                    var oMathNode = doc.querySelector('oMath, oMathPara, *|oMath, *|oMathPara');
                    if (!oMathNode) return match;

                    var mathmlElement = convertOmmlNodeToMathML(oMathNode);
                    if (mathmlElement && window.convertMathMLToLatex) {
                        var latex = window.convertMathMLToLatex(mathmlElement);
                        if (latex && latex.trim()) {
                            return '<span class="exam-math-formula" contenteditable="false" data-latex="' + latex.replace(/"/g, '&quot;') + '">\\(' + latex + '\\)</span>';
                        }
                    }
                } catch (e) {
                    console.warn('OMML to MathML conversion failed:', e);
                }
                return match;
            });
        }

        // แปลง vertical-align:super ของ Word เป็น <sup> และ vertical-align:sub เป็น <sub>
        html = html.replace(/<span[^>]*style=["'][^"']*vertical-align:\s*super[^"']*["'][^>]*>([\s\S]*?)<\/span>/gi, '<sup>$1</sup>');
        html = html.replace(/<span[^>]*style=["'][^"']*vertical-align:\s*sub[^"']*["'][^>]*>([\s\S]*?)<\/span>/gi, '<sub>$1</sub>');

        // ทำความสะอาด mso-spacerun และช่องว่างรอบตัวยก/ตัวห้อย
        html = html.replace(/(?:<span[^>]*mso-spacerun:[^>]*>[\s\S]*?<\/span>|&nbsp;|\s)*<sup\b([^>]*)>(?:&nbsp;|\s)*([\s\S]*?)(?:&nbsp;|\s)*<\/sup>(?:<span[^>]*mso-spacerun:[^>]*>[\s\S]*?<\/span>|&nbsp;|\s)*/gi, '<sup$1>$2</sup>');
        html = html.replace(/(?:<span[^>]*mso-spacerun:[^>]*>[\s\S]*?<\/span>|&nbsp;|\s)*<sub\b([^>]*)>(?:&nbsp;|\s)*([\s\S]*?)(?:&nbsp;|\s)*<\/sub>(?:<span[^>]*mso-spacerun:[^>]*>[\s\S]*?<\/span>|&nbsp;|\s)*/gi, '<sub$1>$2</sub>');

        // แปลงเลขไทยเป็นเลขอารบิก
        html = window.thaiToArabicDigits(html);

        // ดักจับคำสั่ง LaTeX ที่ยังไม่ครอบ \(...\) เพื่อให้ตัวหนังสือไทยรอบๆ ไม่โดนดูดเป็นสูตร
        html = window.autoWrapLatexInText(html);

        // ลบคอมเมนต์ mso อื่นๆ ที่เหลือ
        html = html.replace(/<!--\[if gte mso [\s\S]*?<!\[endif\]-->/gi, '');
        html = html.replace(/<!\[if !mso\]>[\s\S]*?<!\[endif\]>/gi, '');

        return html;
    };

    // 5. ตัวจัดการ Paste ใน Summernote
    window.handleSummernotePaste = function (e, editor) {
        var clipboardData = (e.originalEvent || e).clipboardData;
        if (!clipboardData) return;

        var html = clipboardData.getData('text/html');
        var text = clipboardData.getData('text/plain');

        var isFromWord = (html && (html.indexOf('mso-') !== -1 || html.indexOf('MsoNormal') !== -1 || html.indexOf('oMath') !== -1 || html.indexOf('urn:schemas-microsoft-com') !== -1));
        var hasThaiDigits = (html && /[๐-๙]/.test(html)) || (text && /[๐-๙]/.test(text));
        var hasMath = (html && (html.indexOf('<math') !== -1 || html.indexOf('oMath') !== -1));
        var hasLatexInText = (html && /\\(?:frac|sqrt|pm|times|div|le|ge|ne|sum|int|pi|theta|alpha|beta)\b/.test(html)) ||
            (text && /\\(?:frac|sqrt|pm|times|div|le|ge|ne|sum|int|pi|theta|alpha|beta)\b/.test(text));

        // ถ้ามี HTML ที่มาจาก Word หรือมีเลขไทย หรือมีสมการ หรือมีโค้ด LaTeX
        if (html && (isFromWord || hasThaiDigits || hasMath || hasLatexInText)) {
            e.preventDefault();
            var cleanedHtml = window.cleanWordMathHtml(html);
            $(editor).summernote('pasteHTML', cleanedHtml);
            var $editorContainer = $(editor).next('.note-editor');
            window.renderMath($editorContainer.length ? $editorContainer[0] : editor);
            $(editor).trigger('change');
            return true;
        }

        // ถ้าข้อความล้วนเป็น MathML (เช่น กรณี Word ตั้งค่า Copy MathML to clipboard as plain text)
        if (text && text.trim().indexOf('<math') === 0 && text.trim().lastIndexOf('</math>') !== -1) {
            e.preventDefault();
            $(editor).summernote('pasteHTML', text.trim());
            var $editorContainer2 = $(editor).next('.note-editor');
            window.renderMath($editorContainer2.length ? $editorContainer2[0] : editor);
            $(editor).trigger('change');
            return true;
        }

        // กรณีข้อความล้วนแต่มีเลขไทย หรือมี LaTeX โดดๆ อยู่ในข้อความ
        if (text && (hasThaiDigits || hasLatexInText)) {
            e.preventDefault();
            var cleanedText = window.thaiToArabicDigits(text);
            cleanedText = window.autoWrapLatexInText(cleanedText);
            cleanedText = cleanedText.replace(/\r?\n/g, '<br>');
            $(editor).summernote('pasteHTML', cleanedText);
            var $editorContainer3 = $(editor).next('.note-editor');
            window.renderMath($editorContainer3.length ? $editorContainer3[0] : editor);
            $(editor).trigger('change');
            return true;
        }

        return false;
    };

    // 6. ลงทะเบียน Summernote Custom Button สำหรับแทรกสูตรคณิตศาสตร์ (Math Formula Dialog)
    if (window.jQuery && $.summernote) {
        $.extend($.summernote.plugins, {
            'math': function (context) {
                var ui = $.summernote.ui;
                var $editor = context.layoutInfo.editor;

                context.memo('button.math', function () {
                    var button = ui.button({
                        contents: '<i class="fas fa-square-root-alt text-primary"></i> <span class="font-weight-bold">สูตร</span>',
                        tooltip: 'แทรกสูตรคณิตศาสตร์ (Math Formula)',
                        click: function () {
                            openMathModal(context);
                        }
                    });
                    return button.render();
                });
            }
        });
    }

    // Modal แทรกสูตรคณิตศาสตร์
    var currentMathContext = null;
    function openMathModal(context) {
        currentMathContext = context;
        ensureMathModalExists();
        $('#exam-math-modal-input').val('');
        $('#exam-math-modal-preview').html('<span class="text-muted">ตัวอย่างสูตรจะแสดงที่นี่...</span>');
        $('#examMathModal').modal('show');
    }

    function ensureMathModalExists() {
        if ($('#examMathModal').length) return;

        var modalHtml = '' +
            '<div class="modal fade" id="examMathModal" tabindex="-1" role="dialog" aria-labelledby="examMathModalLabel" aria-hidden="true" style="z-index: 10600;">' +
            '  <div class="modal-dialog modal-lg modal-dialog-centered" role="document">' +
            '    <div class="modal-content shadow-lg border-0" style="border-radius: 12px; overflow: hidden;">' +
            '      <div class="modal-header bg-primary text-white py-3">' +
            '        <h5 class="modal-title font-weight-bold" id="examMathModalLabel"><i class="fas fa-calculator mr-2"></i>พิมพ์และแก้ไขสูตรข้อสอบ</h5>' +
            '        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">' +
            '          <span aria-hidden="true">&times;</span>' +
            '        </button>' +
            '      </div>' +
            '      <div class="modal-body p-4">' +
            '        <div class="mb-3">' +
            '          <div class="d-flex justify-content-between align-items-center mb-1">' +
            '            <label class="font-weight-bold text-dark text-sm mb-0">เลือกสัญลักษณ์ / แม่แบบสูตรด่วน:</label>' +
            '          </div>' +
            '          <div class="d-flex flex-wrap" style="gap: 6px;" id="exam-math-quick-buttons">' +
            '            <button type="button" class="btn btn-sm btn-outline-primary math-insert-btn" data-latex="\\frac{a}{b}">เศษส่วน $\\frac{a}{b}$</button>' +
            '            <button type="button" class="btn btn-sm btn-outline-primary math-insert-btn" data-latex="\\sqrt{x}">สแควรูท $\\sqrt{x}$</button>' +
            '            <button type="button" class="btn btn-sm btn-outline-primary math-insert-btn" data-latex="\\sqrt[n]{x}">รูท n $\\sqrt[n]{x}$</button>' +
            '            <button type="button" class="btn btn-sm btn-outline-primary math-insert-btn" data-latex="x^{2}">ยกกำลัง $x^2$</button>' +
            '            <button type="button" class="btn btn-sm btn-outline-primary math-insert-btn" data-latex="x_{1}">ตัวห้อย $x_1$</button>' +
            '            <button type="button" class="btn btn-sm btn-outline-secondary math-insert-btn" data-latex="\\pm">$\\pm$</button>' +
            '            <button type="button" class="btn btn-sm btn-outline-secondary math-insert-btn" data-latex="\\times">$\\times$</button>' +
            '            <button type="button" class="btn btn-sm btn-outline-secondary math-insert-btn" data-latex="\\div">$\\div$</button>' +
            '            <button type="button" class="btn btn-sm btn-outline-secondary math-insert-btn" data-latex="\\le">$\\le$</button>' +
            '            <button type="button" class="btn btn-sm btn-outline-secondary math-insert-btn" data-latex="\\ge">$\\ge$</button>' +
            '            <button type="button" class="btn btn-sm btn-outline-secondary math-insert-btn" data-latex="\\ne">$\\ne$</button>' +
            '            <button type="button" class="btn btn-sm btn-outline-secondary math-insert-btn" data-latex="\\approx">$\\approx$</button>' +
            '            <button type="button" class="btn btn-sm btn-outline-secondary math-insert-btn" data-latex="\\pi">$\\pi$</button>' +
            '            <button type="button" class="btn btn-sm btn-outline-secondary math-insert-btn" data-latex="\\theta">$\\theta$</button>' +
            '            <button type="button" class="btn btn-sm btn-outline-secondary math-insert-btn" data-latex="\\sum_{i=1}^{n} x_i">$\\sum_{i=1}^{n}$</button>' +
            '            <button type="button" class="btn btn-sm btn-outline-secondary math-insert-btn" data-latex="\\int_{a}^{b} f(x)dx">$\\int_{a}^{b}$</button>' +
            '          </div>' +
            '        </div>' +
            '        <div class="form-group mb-3">' +
            '          <textarea id="exam-math-modal-input" class="form-control" rows="3" placeholder="วางข้อความจาก Word หรือพิมพ์สูตรที่นี่ เช่น: กำหนดให้ \\frac{d}{dx} sin u = cos u \\frac{du}{dx} ค่าอนุพันธ์ของ y = sin(3x^2 + 1) เท่ากับข้อใด" style="font-family: monospace; font-size: 14px; border-radius: 8px;"></textarea>' +
            '        </div>' +
            '        <div class="form-group mb-0">' +
            '          <div class="d-flex justify-content-between align-items-center mb-1">' +
            '            <label class="font-weight-bold text-dark text-sm mb-0">ตัวอย่าง</label>' +
            '          </div>' +
            '          <div id="exam-math-modal-preview" contenteditable="true" class="p-3 border rounded bg-white text-left shadow-xs" style="min-height: 85px; max-height: 250px; overflow-y: auto; font-size: 1.1rem; line-height: 1.8; border-radius: 8px; word-break: break-word; outline: none; cursor: text;">' +
            '            <span class="text-muted">ตัวอย่างสูตรจะแสดงที่นี่...</span>' +
            '          </div>' +
            '        </div>' +
            '      </div>' +
            '      <div class="modal-footer bg-light">' +
            '        <button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">ยกเลิก</button>' +
            '        <button type="button" id="exam-math-insert-btn" class="btn btn-primary font-weight-bold text-white"><i class="fas fa-check mr-1"></i>แทรกสูตรลงในข้อสอบ</button>' +
            '      </div>' +
            '    </div>' +
            '  </div>' +
            '</div>';

        $('body').append(modalHtml);

        // Event: Quick Template click
        $(document).on('click', '.math-insert-btn', function () {
            var latex = $(this).data('latex');
            var $input = $('#exam-math-modal-input');
            var currentVal = $input.val();
            $input.val(currentVal ? (currentVal + ' ' + latex) : latex);
            updateMathPreview();
            $input.focus();
        });

        // Event: Button Merge Lines (จัดให้อยู่บรรทัดเดียวกันต่อเนื่อง)
        $(document).on('click', '#btn-merge-lines', function () {
            var text = $('#exam-math-modal-input').val();
            if (text) {
                text = text.replace(/[\r\n]+/g, ' ').replace(/\s{2,}/g, ' ').trim();
                $('#exam-math-modal-input').val(text);
                updateMathPreview();
            }
        });

        // Event: Button Clear All
        $(document).on('click', '#btn-clear-math-modal', function () {
            $('#exam-math-modal-input').val('');
            $('#exam-math-modal-preview').html('<span class="text-muted">ตัวอย่างสูตรจะแสดงที่นี่...</span>');
        });

        // ฟังก์ชันช่วยทำความสะอาดข้อความจาก Clipboard (ลอกคราบ CSS, แปลงสูตร Word OMML/MathML เป็น LaTeX, จัดบรรทัดเดียว)
        function cleanPastedWordMathContent(clipboardData) {
            var html = clipboardData.getData('text/html');
            var text = clipboardData.getData('text/plain');

            // 0. แปลง OMML จาก Word (ใน HTML) ให้กลายเป็น LaTeX ก่อนดึงข้อความ ไม่ให้สูตรหาย
            var ommlPattern = /(?:<!--\[if gte mso 9\]><xml>)?\s*(<(?:m:)?(?:oMathPara|oMath)\b[\s\S]*?<\/(?:m:)?(?:oMathPara|oMath)>)\s*(?:<\/xml><!\[endif\]-->)?/gi;
            if (html && ommlPattern.test(html)) {
                var parser = new DOMParser();
                html = html.replace(ommlPattern, function (match, ommlContent) {
                    try {
                        var wrappedXml = '<root xmlns:m="http://schemas.openxmlformats.org/officeDocument/2006/math">' + ommlContent + '</root>';
                        var doc = parser.parseFromString(wrappedXml, 'application/xml');
                        var oMathNode = doc.querySelector('oMath, oMathPara, *|oMath, *|oMathPara');
                        if (oMathNode) {
                            var mathml = convertOmmlNodeToMathML(oMathNode);
                            if (mathml && window.convertMathMLToLatex) {
                                var latex = window.convertMathMLToLatex(mathml);
                                if (latex && latex.trim()) {
                                    return ' \\(' + latex.trim() + '\\) ';
                                }
                            }
                        }
                    } catch (e) {
                        console.warn('OMML to LaTeX error:', e);
                    }
                    return match;
                });
            }

            // 0.1 แปลง MathML ที่อาจมีอยู่ใน HTML ให้กลายเป็น LaTeX
            if (html && /<math[\s\S]*?<\/math>/i.test(html)) {
                var parser2 = new DOMParser();
                html = html.replace(/<math[\s\S]*?<\/math>/gi, function(mathStr) {
                    try {
                        var doc2 = parser2.parseFromString(mathStr, 'application/xml');
                        var mathEl = doc2.querySelector('math');
                        if (mathEl && window.convertMathMLToLatex) {
                            var ltx = window.convertMathMLToLatex(mathEl);
                            if (ltx && ltx.trim()) {
                                return ' \\(' + ltx.trim() + '\\) ';
                            }
                        }
                    } catch(e) {}
                    return mathStr;
                });
            }

            var textToInsert = '';
            if (html) {
                var tempDiv = document.createElement('div');
                tempDiv.innerHTML = html;

                // แปลงตัวยกเป็น ^ และตัวห้อยเป็น _
                var sups = tempDiv.querySelectorAll('sup, span[style*="vertical-align:super"], span[style*="vertical-align: super"]');
                sups.forEach(function (el) {
                    var t = el.textContent.trim();
                    if (t) el.textContent = '^' + (t.length > 1 ? '{' + t + '}' : t);
                });

                var subs = tempDiv.querySelectorAll('sub, span[style*="vertical-align:sub"], span[style*="vertical-align: sub"]');
                subs.forEach(function (el) {
                    var t = el.textContent.trim();
                    if (t) el.textContent = '_' + (t.length > 1 ? '{' + t + '}' : t);
                });

                textToInsert = tempDiv.innerText || tempDiv.textContent;
            } else {
                textToInsert = text || '';
            }

            // ลอกแท็ก HTML / CSS ทั้งหมดออก ไม่ให้มีโค้ด CSS รก
            textToInsert = textToInsert.replace(/<[^>]+>/g, ' ');

            // แปลงเลขไทยเป็นเลขอารบิก
            if (window.thaiToArabicDigits) {
                textToInsert = window.thaiToArabicDigits(textToInsert);
            }

            // ดักจับคำสั่ง LaTeX โดดๆ ที่อยู่ในข้อความ
            if (window.autoWrapLatexInText) {
                textToInsert = window.autoWrapLatexInText(textToInsert);
            }

            // จัดให้อยู่บรรทัดเดียวกันต่อเนื่องอัตโนมัติ (ยุบบรรทัดที่แตกย่อยจากตาราง Word)
            textToInsert = textToInsert.replace(/[\r\n]+/g, ' ').replace(/\s{2,}/g, ' ').trim();

            return textToInsert;
        }

        // Event: Input typing in Code box
        $(document).on('input', '#exam-math-modal-input', function () {
            updateMathPreview();
        });

        // Event: Paste into Code box
        $(document).on('paste', '#exam-math-modal-input', function (e) {
            var clipboardData = (e.originalEvent || e).clipboardData;
            if (!clipboardData) return;
            e.preventDefault();

            var textToInsert = cleanPastedWordMathContent(clipboardData);

            var input = this;
            var start = input.selectionStart || 0;
            var end = input.selectionEnd || 0;
            var val = input.value;
            input.value = val.substring(0, start) + textToInsert + val.substring(end);
            input.selectionStart = input.selectionEnd = start + textToInsert.length;
            updateMathPreview();
        });

        // ฟังก์ชันดึงข้อความและสูตรคณิตศาสตร์ออกจากกล่องตัวอย่างการแสดงผลจริง
        function extractContentFromPreview(previewEl) {
            if (!previewEl) return '';
            var result = '';
            function walk(node) {
                if (!node) return;
                if (node.nodeType === 3) { // TEXT_NODE
                    result += node.nodeValue;
                } else if (node.nodeType === 1) { // ELEMENT_NODE
                    var tag = (node.tagName || '').toLowerCase();
                    if (tag === 'mjx-container') {
                        var latex = node.getAttribute('data-latex');
                        if (latex) {
                            result += ' \\(' + latex.trim() + '\\) ';
                        } else {
                            result += ' ' + (node.getAttribute('aria-label') || node.innerText || '') + ' ';
                        }
                    } else if (tag === 'math') {
                        result += ' ' + node.outerHTML + ' ';
                    } else if (tag === 'br') {
                        result += ' ';
                    } else if (tag === 'div' || tag === 'p') {
                        for (var c = 0; c < node.childNodes.length; c++) {
                            walk(node.childNodes[c]);
                        }
                        result += ' ';
                    } else {
                        for (var i = 0; i < node.childNodes.length; i++) {
                            walk(node.childNodes[i]);
                        }
                    }
                }
            }
            walk(previewEl);
            return result.replace(/\u00A0/g, ' ').replace(/\s{2,}/g, ' ').trim();
        }

        // Event: แก้ไขคำหรือลบข้อความได้โดยตรงจากกล่องตัวอย่างการแสดงผลจริง (Live Preview Direct Editing)
        var previewSyncTimer = null;
        $(document).on('input', '#exam-math-modal-preview', function () {
            clearTimeout(previewSyncTimer);
            previewSyncTimer = setTimeout(function () {
                var extracted = extractContentFromPreview($('#exam-math-modal-preview')[0]);
                if (extracted && extracted !== 'ตัวอย่างสูตรจะแสดงที่นี่...') {
                    $('#exam-math-modal-input').val(extracted);
                }
            }, 300);
        });

        // Event: สามารถกด Paste ลงในกล่องตัวอย่างการแสดงผลจริงได้โดยตรงเช่นกัน
        $(document).on('paste', '#exam-math-modal-preview', function (e) {
            var clipboardData = (e.originalEvent || e).clipboardData;
            if (!clipboardData) return;
            e.preventDefault();

            var textToInsert = cleanPastedWordMathContent(clipboardData);
            $('#exam-math-modal-input').val(textToInsert);
            updateMathPreview();
        });

        // Event: Insert into Summernote
        $(document).on('click', '#exam-math-insert-btn', function () {
            var $preview = $('#exam-math-modal-preview');
            var rawInput = $('#exam-math-modal-input').val().trim();
            var extracted = extractContentFromPreview($preview[0]);
            var contentToInsert = (extracted && extracted !== 'ตัวอย่างสูตรจะแสดงที่นี่...') ? extracted : rawInput;

            if (!contentToInsert) {
                $('#examMathModal').modal('hide');
                return;
            }
            if (currentMathContext) {
                var formulaHtml;
                if (/[\u0E00-\u0E7F]/.test(contentToInsert) || contentToInsert.indexOf('\\(') !== -1 || contentToInsert.indexOf('<math') !== -1) {
                    var converted = window.autoWrapLatexInText ? window.autoWrapLatexInText(contentToInsert) : contentToInsert;
                    converted = window.thaiToArabicDigits ? window.thaiToArabicDigits(converted) : converted;
                    converted = converted.replace(/[\r\n]+/g, ' ').trim();
                    converted = converted.replace(/\\\(([\s\S]*?)\\\)/g, function (match, inner) {
                        return '<span class="exam-math-formula" contenteditable="false" data-latex="' + inner.replace(/"/g, '&quot;') + '">\\(' + inner + '\\)</span>';
                    });
                    formulaHtml = converted;
                } else {
                    var formatted = window.formatMathSpacing ? window.formatMathSpacing(contentToInsert) : contentToInsert;
                    formulaHtml = '<span class="exam-math-formula" contenteditable="false" data-latex="' + formatted.replace(/"/g, '&quot;') + '">\\(' + formatted + '\\)</span>';
                }
                currentMathContext.invoke('editor.pasteHTML', formulaHtml);
                var $container = currentMathContext.layoutInfo.editor;
                window.renderMath($container);
            }
            $('#examMathModal').modal('hide');
        });

        // Render math in quick buttons once
        setTimeout(function () {
            window.renderMath('#exam-math-quick-buttons');
        }, 300);
    }

    var previewTimer = null;
    function updateMathPreview() {
        clearTimeout(previewTimer);
        previewTimer = setTimeout(function () {
            var latex = $('#exam-math-modal-input').val();
            if (latex) {
                latex = latex.replace(/^[\s\u00A0\u200B\u2028\u2029\r\n]+|[\s\u00A0\u200B\u2028\u2029\r\n]+$/g, '');
                // ยุบเป็นบรรทัดเดียวกันต่อเนื่อง (คงระยะเคาะวรรคที่ผู้ใช้ตั้งใจเคาะไว้)
                latex = latex.replace(/[\r\n]+/g, ' ');
            }
            var $preview = $('#exam-math-modal-preview');
            if (!latex) {
                $preview.html('<span class="text-muted">ตัวอย่างสูตรจะแสดงที่นี่...</span>');
                return;
            }
            if (/[\u0E00-\u0E7F]/.test(latex) || latex.indexOf('\n') !== -1) {
                var converted = window.autoWrapLatexInText ? window.autoWrapLatexInText(latex) : latex;
                converted = window.thaiToArabicDigits ? window.thaiToArabicDigits(converted) : converted;
                $preview.html(converted);
            } else {
                var formatted = window.formatMathSpacing ? window.formatMathSpacing(latex) : latex;
                $preview.html('$$\n' + formatted + '\n$$');
            }
            window.renderMath($preview[0]).then(function () {
                // ติดป้ายกำกับสูตร LaTeX ให้แต่ละ mjx-container เพื่อให้คลิกพิมพ์แก้ไขรอบๆ สูตรได้อย่างราบรื่น
                if (window.MathJax && window.MathJax.startup && window.MathJax.startup.document) {
                    try {
                        var mathList = window.MathJax.startup.document.getMathItemsWithin($preview[0]);
                        if (mathList && mathList.length) {
                            for (var m = 0; m < mathList.length; m++) {
                                var item = mathList[m];
                                if (item && item.typesetRoot) {
                                    item.typesetRoot.setAttribute('data-latex', item.math || '');
                                    item.typesetRoot.setAttribute('contenteditable', 'false');
                                }
                            }
                        }
                    } catch (e) {
                        console.warn('Tagging MathJax items error:', e);
                    }
                }
            });
        }, 150);
    }

    // 7. Auto-render เมื่อโหลดหน้าเว็บเสร็จ
    if (typeof document !== 'undefined') {
        document.addEventListener('DOMContentLoaded', function () {
            window.renderMath();
        });
    }

    if (window.jQuery) {
        $(document).ready(function () {
            window.renderMath();
        });
        $(document).ajaxComplete(function () {
            window.renderMath();
        });
    }
})();
