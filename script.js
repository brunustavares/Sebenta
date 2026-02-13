/**
 * Sebenta
 * Moodle block for grades synchronization with WISEflow (teachers’ function)
 * and integrated grades and submission statements (students’ function).
 * (developed for UAb - Universidade Aberta)
 *
 * @category   Moodle_Block
 * @package    block_sebenta
 * @author     Bruno Tavares <brunustavares@gmail.com>
 * @link       https://www.linkedin.com/in/brunomastavares/
 * @copyright  Copyright (C) 2023-present Bruno Tavares
 * @license    GNU General Public License v3 or later
 *             https://www.gnu.org/licenses/gpl-3.0.html
 * @version    2026021202
 * @date       2023-03-21
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

// funções para alterar a data de fim da avaliação
function setFlowInfo(value, flowInfo) {
    document.getElementById("btnEndAssessDate").value = value;
    document.getElementById("flow_info").textContent = flowInfo;
}

function endflowmarking(flowid, auth_chain, wf_url) {
    var http = new XMLHttpRequest();
    var ws_url = "../blocks/sebenta/wf_endpoints.php";
    var params = "action=endflowmarking&flowid=" + flowid + "&auth_chain=" + auth_chain + "&url=" + wf_url;

    http.open("POST", ws_url, true);
    http.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    http.onreadystatechange = function() {
        if (http.readyState === 4 && http.status === 200) {
            // console.log(http.responseText);
        }
    };
    http.send(params);

    // window.alert("Operação executada, esta página será recarregada!");
    document.getElementById("confirmation").innerHTML = "<h4><b>Avaliação finalizada!</b><br><br>Esta página está a ser actualizada...</h4>";
    document.getElementById("buttons").style.display = "none";

    self.location.reload();
}

// saneamento do JSON
function safeJsonParse(value, fallback) {
    try {
        return JSON.parse(value);
    } catch (e) {
        return fallback;
    }
}

// inicialização do módulo de gestão dos fluxos para docentes
function initTeacherFlows() {
    var host = document.getElementById("wiseflow");
    
    if (!host || host.dataset.role !== "teacher") {
        return;
    }

    var list = document.getElementById("wiseflow_list");
    var spinner = document.getElementById("wiseflow_loading");
    var button = document.getElementById("wiseflow_load_more");
    var sentinel = document.getElementById("wiseflow_sentinel");
    var status = document.getElementById("wiseflow_status");

    var endpoint = host.dataset.endpoint;
    var flwDoc = host.dataset.flwdoc || "";
    var sesskey = host.dataset.sesskey || "";
    var initialLimit = parseInt(host.dataset.initialLimit || "4", 10);
    var nextLimit = parseInt(host.dataset.nextLimit || "10", 10);

    var cacheKey = "sebenta.teacher.flows." + btoa((flwDoc || "all") + "|" + window.location.pathname);
    var cacheTtlMs = 120000;

    var state = {
        offset: 0,
        totalVisible: 0,
        totalVisibleKnown: false,
        totalRecords: 0,
        hasMore: true,
        loading: false,
        html: []
    };

    function setLoading(isLoading) {
        state.loading = isLoading;
        if (spinner) {
            spinner.classList.toggle("is-visible", isLoading);
        }
    }

    function updateStatus() {
        if (!status) { return; }

        if (state.totalVisible === 0) {
            status.textContent = "0 / 0 flows";
            if (list) {
                list.innerHTML = "<div class='noflow'>(sem provas/flows por avaliar)</div>";
            }
            if (button) {
                button.style.display = "none";
            }
            return;
        }

        if (state.totalVisibleKnown) {
            status.textContent = " (" + state.offset + " / " + state.totalVisible + " flows)";
        } else {
            status.textContent = " (" + state.offset + " / " + state.totalRecords + " flows)";
        }

        if (button) {
            button.style.display = state.hasMore ? "inline-flex" : "none";
        }
    }

    function persistCache() {
        sessionStorage.setItem(cacheKey, JSON.stringify({
            savedAt: Date.now(),
            offset: state.offset,
            totalVisible: state.totalVisible,
            totalVisibleKnown: state.totalVisibleKnown,
            totalRecords: state.totalRecords,
            hasMore: state.hasMore,
            html: state.html
        }));
    }

    function restoreCache() {
        var raw = sessionStorage.getItem(cacheKey);
        if (!raw) {
            return false;
        }

        var cached = safeJsonParse(raw, null);
        if (!cached || !cached.savedAt || (Date.now() - cached.savedAt) > cacheTtlMs) {
            sessionStorage.removeItem(cacheKey);
            return false;
        }

        state.offset = cached.offset || 0;
        state.totalVisible = cached.totalVisible || 0;
        state.totalVisibleKnown = !!cached.totalVisibleKnown;
        state.totalRecords = cached.totalRecords || 0;
        state.hasMore = typeof cached.hasMore === "boolean" ? cached.hasMore : true;
        state.html = Array.isArray(cached.html) ? cached.html : [];

        if (state.html.length > 0) {
            list.innerHTML = state.html.join("");
        }

        updateStatus();
        return true;
    }

    function appendBatch(items) {
        if (!Array.isArray(items) || items.length === 0) {
            return;
        }

        var html = items.map(function (item) {
            return item.html || "";
        });

        state.html = state.html.concat(html);
        list.insertAdjacentHTML("beforeend", html.join(""));
    }

    function fetchBatch(limit) {
        if (state.loading || !state.hasMore) {
            return Promise.resolve();
        }

        setLoading(true);

        var params = new URLSearchParams({
            action: "get_flows",
            flwDoc: flwDoc,
            offset: String(state.offset),
            limit: String(limit),
            sesskey: sesskey
        });

        return fetch(endpoint + "?" + params.toString(), {
            credentials: "same-origin"
        })
            .then(function (response) {
                if (!response.ok) {
                    return response.text().then(function (txt) {
                        throw new Error("HTTP " + response.status + ": " + (txt || "failed to fetch flows"));
                    });
                }
                return response.json();
            })
            .then(function (payload) {
                appendBatch(payload.items || []);

                state.offset = payload.nextOffset || state.offset;
                state.totalVisible = payload.totalVisible || 0;
                state.totalVisibleKnown = !!payload.totalVisibleKnown;
                state.totalRecords = payload.totalRecords || 0;
                state.hasMore = !!payload.hasMore;

                updateStatus();
                persistCache();
            })
            .catch(function (err) {
                if (status) {
                    status.textContent = "Erro no carregamento dos flows: " + err.message;
                }
            })
            .finally(function () {
                setLoading(false);
            });
    }

    var hasCache = restoreCache();

    if (!hasCache) {
        fetchBatch(initialLimit);
    }

    if (button) {
        button.addEventListener("click", function () {
            fetchBatch(nextLimit);
        });
    }
}

// inicialização do módulo de carrossel para os estudantes
function initStudentCarousel() {
    var container = document.querySelector(".sebenta_carousel-container");
    var track = document.getElementById("sebenta_carousel");
    var prev = document.getElementById("sebenta_prev");
    var next = document.getElementById("sebenta_next");
    var dotsHost = document.getElementById("sebenta_dots");

    if (!container || !track) { return; }

    var cacheKey = container.dataset.cacheKey || ("sebenta.student.carousel." + window.location.pathname);
    var cacheTtlMs = 120000;

    function restoreFromCacheIfNeeded() {
        if (track.children.length > 0) {
            return;
        }

        var raw = sessionStorage.getItem(cacheKey);
        var cached = safeJsonParse(raw, null);

        if (!cached || !cached.savedAt || (Date.now() - cached.savedAt) > cacheTtlMs || !cached.html) {
            return;
        }

        track.innerHTML = cached.html;
    }

    function persistCache() {
        sessionStorage.setItem(cacheKey, JSON.stringify({
            savedAt: Date.now(),
            html: track.innerHTML,
            index: currentIndex
        }));
    }

    restoreFromCacheIfNeeded();

    var items = Array.from(track.querySelectorAll(".sebenta_carousel-item"));

    if (items.length === 0) {
        if (prev) {
            prev.style.display = "none";
        }
        if (next) {
            next.style.display = "none";
        }
        return;
    }

    var currentIndex = 0;
    var slideWidth = 0;
    var stepWidth = 0;
    var containerWidth = 0;
    var cardGap = 12;
    var startX = 0;
    var dragging = false;

    function makeDots() {
        if (!dotsHost) { return; }

        dotsHost.innerHTML = "";
        items.forEach(function (_, idx) {
            var dot = document.createElement("button");
            dot.type = "button";
            dot.className = "sebenta-dot";
            dot.setAttribute("aria-label", "Ir para cartao " + (idx + 1));
            dot.addEventListener("click", function () {
                currentIndex = idx;
                update(true);
            });
            dotsHost.appendChild(dot);
        });
    }

    function updateDots() {
        if (!dotsHost) { return; }

        var dots = Array.from(dotsHost.querySelectorAll(".sebenta-dot"));

        dots.forEach(function (dot, idx) {
            dot.classList.toggle("active", idx === currentIndex);
        });
    }

    function update(animate) {
        if (!slideWidth) { return; }

        track.style.transition = animate ? "transform 0.35s ease" : "none";

        var startOffset = Math.max(0, (containerWidth - slideWidth) / 2);

        track.style.transform = "translateX(" + (startOffset - (currentIndex * stepWidth)) + "px)";

        items.forEach(function (item, idx) {
            item.classList.toggle("active", idx === currentIndex);
        });

        updateDots();
        persistCache();
    }

    function layoutSlides() {
        containerWidth = container.clientWidth;

        if (!containerWidth) { return; }

        slideWidth = Math.max(220, Math.floor(containerWidth * 0.72));
        stepWidth = slideWidth + cardGap;

        track.style.setProperty("left", "0", "important");
        track.style.setProperty("width", ((stepWidth * (items.length - 1)) + slideWidth) + "px", "important");
        track.style.setProperty("max-width", "none", "important");
        track.style.setProperty("margin", "0", "important");

        items.forEach(function (item, idx) {
            item.style.width = slideWidth + "px";
            item.style.minWidth = slideWidth + "px";
            item.style.marginRight = idx < (items.length - 1) ? cardGap + "px" : "0";
        });
    }

    function goNext() {
        currentIndex = (currentIndex + 1) % items.length;
        update(true);
    }

    function goPrev() {
        currentIndex = (currentIndex - 1 + items.length) % items.length;
        update(true);
    }

    if (items.length === 1) {
        if (prev) {
            prev.disabled = true;
        }
        if (next) {
            next.disabled = true;
        }
    } else {
        if (prev) {
            prev.addEventListener("click", goPrev);
        }
        if (next) {
            next.addEventListener("click", goNext);
        }
    }

    track.addEventListener("pointerdown", function (ev) {
        dragging = true;
        startX = ev.clientX;
    });

    track.addEventListener("pointerup", function (ev) {
        if (!dragging) { return; }

        var delta = ev.clientX - startX;

        if (Math.abs(delta) > 40) {
            if (delta < 0) {
                goNext();
            } else {
                goPrev();
            }
        }

        dragging = false;
    });

    container.setAttribute("tabindex", "0");
    container.addEventListener("keydown", function (ev) {
        if (ev.key === "ArrowRight") {
            goNext();
        } else if (ev.key === "ArrowLeft") {
            goPrev();
        }
    });

    var cache = safeJsonParse(sessionStorage.getItem(cacheKey), null);
    
    if (cache && typeof cache.index === "number" && cache.index < items.length) {
        currentIndex = cache.index;
    }

    layoutSlides();
    makeDots();
    update(false);

    window.addEventListener("resize", function () {
        layoutSlides();
        update(false);
    });
}

document.addEventListener("DOMContentLoaded", function () {
    initTeacherFlows();
    initStudentCarousel();
});
