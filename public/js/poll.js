if (typeof ForkBB === "undefined" || !ForkBB) {
    var ForkBB = {};
}

ForkBB.poll = (function (doc, win) {
    'use strict';

    var inputPE = 0,
        classHide = "f-hide-poll",
        parentsPE,
        othersForPE = [],
        question = [],
        answer = [],
        type = [],
        maxQuestion = 0,
        maxAnswer = 0;

    function getParents(start) {
        var parents = [],
            current = start;

        while (current !== start.form && current !== doc.body) {
            current = current.parentElement;
            parents.unshift(current);
        }

        return parents;
    }

    function getBranchingNode(baseParents, node)
    {
        var nodeParents = getParents(node);

        for (var i = 0; i < baseParents.length; i++) {
            if (
                i >= nodeParents.length
                || (
                    0 === i
                    && baseParents[i] !== nodeParents[i]
                )
            ) {
                return null;
            } else if (baseParents[i] !== nodeParents[i]) {
                return nodeParents[i];
            }
        }

        return null;
    }

    return {
        init : function () {
            if (0 !== inputPE) {
                return;
            }

            inputPE = doc.querySelector("input[name=poll_enable]");

            if (!inputPE) {
                return;
            }

            inputPE.addEventListener("change", ForkBB.poll.handlerEnable);
            parentsPE = getParents(inputPE);
            var elements = inputPE.form.elements;

            for (var i = 0; i < elements.length; i++) {
                var name = elements[i].name;

                if (typeof name !== "string") {
                    continue;
                }

                var matches = name.match(/poll\[([^\[\]]+)\](?:\[(\d+)\](?:\[(\d+)\])?)?/);

                if (!matches) {
                    continue;
                }

                var q = typeof matches[2] === "string" ? Number(matches[2]) : 0;
                var a = typeof matches[3] === "string" ? Number(matches[3]) : 0;

                if ("question" === matches[1]) {
                    question[q] = elements[i];

                    if (maxQuestion < q) {
                        maxQuestion = q;
                    }
                } else if ("type" === matches[1]) {
                    type[q] = elements[i];
                } else if ("answer" === matches[1]) {
                    if (!answer[q]) {
                        answer[q] = [];
                    }

                    answer[q][a] = elements[i];

                    if (maxAnswer < a) {
                        maxAnswer = a;
                    }
                } else {
                    othersForPE.push(getBranchingNode(parentsPE, elements[i]));
                }
            }

            for (var q = 1; q <= maxQuestion; q++) {
                var currentQ = question[q],
                    parentsQ = getParents(currentQ);

                for (var a = 1; a <= maxAnswer; a++) {
                    var currentA = answer[q][a];

                    answer[q][a] = {
                        a: currentA,
                        b: getBranchingNode(parentsQ, currentA)
                    };

                    currentA.addEventListener("input", function (x, y) {
                        return function () {ForkBB.poll.handlerAnswer(x, y)};
                    }(q, a));
                }

                question[q] = {
                    q: currentQ,
                    b: getBranchingNode(parentsPE, currentQ)
                };
                type[q] = getBranchingNode(parentsQ, type[q]);

                currentQ.addEventListener("input", function (x) {
                    return function () {ForkBB.poll.handlerQuestion(x)};
                }(q));
            }

            ForkBB.poll.handlerEnable();
        },

        handlerEnable : function () {
            for (var i = 0; i < othersForPE.length; i++) {
                var list = othersForPE[i].classList;
                inputPE.checked ? list.remove(classHide) : list.add(classHide);
            }

            ForkBB.poll.handlerQuestion(1, inputPE.checked ? "+" : "-");
        },

        handlerQuestion : function (q, t) {
            if (q > maxQuestion) {
                return;
            } else if (typeof t === "undefined" || "+" === t) {
                t = question[q].q.value == "" ? "-" : "+";

                question[q].b.classList.remove(classHide);

                if ("+" === t) {
                    type[q].classList.remove(classHide);
                } else {
                    type[q].classList.add(classHide);
                }

                ForkBB.poll.handlerAnswer(q, 1, t);
            } else {
                question[q].b.classList.add(classHide);
            }

            ForkBB.poll.handlerQuestion(q + 1, t);
        },

        handlerAnswer : function (q, a, t)  {
            if (a > maxAnswer) {
                return;
            } else if (typeof t === "undefined" || "+" === t) {
                t = answer[q][a].a.value == "" ? "-" : "+";

                answer[q][a].b.classList.remove(classHide);
            } else {
                answer[q][a].b.classList.add(classHide);
            }

            ForkBB.poll.handlerAnswer(q, a + 1, t);
        },
    };
}(document, window));

if (document.addEventListener) {
	document.addEventListener("DOMContentLoaded", ForkBB.poll.init, false);
}
