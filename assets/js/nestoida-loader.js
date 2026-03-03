(function () {
    var LOADER_ID = "nestoida-top-loader";
    var HIDE_DELAY_MS = 220;
    var progressTimer = null;

    function createLoader() {
        if (document.getElementById(LOADER_ID)) return null;

        var loader = document.createElement("div");
        loader.id = LOADER_ID;
        loader.setAttribute("aria-live", "polite");
        loader.style.position = "fixed";
        loader.style.top = "0";
        loader.style.left = "0";
        loader.style.right = "0";
        loader.style.height = "3px";
        loader.style.zIndex = "10000";
        loader.style.background = "transparent";
        loader.style.pointerEvents = "none";

        var bar = document.createElement("div");
        bar.style.height = "100%";
        bar.style.width = "0%";
        bar.style.background = "linear-gradient(90deg, #ff385c 0%, #ff8a5b 50%, #22d3ee 100%)";
        bar.style.transition = "width 280ms ease, opacity 220ms ease";
        bar.style.opacity = "0";

        loader.appendChild(bar);
        document.body.appendChild(loader);
        loader._bar = bar;
        return loader;
    }

    function showLoader(loader) {
        if (!loader) return;
        if (progressTimer) {
            clearInterval(progressTimer);
            progressTimer = null;
        }
        var bar = loader._bar;
        if (!bar) return;
        bar.style.opacity = "1";
        bar.style.width = "12%";
        progressTimer = setInterval(function () {
            var current = parseFloat(bar.style.width) || 0;
            var next = Math.min(92, current + Math.random() * 8);
            bar.style.width = next + "%";
        }, 260);
    }

    function hideLoader(loader) {
        if (!loader) return;
        if (progressTimer) {
            clearInterval(progressTimer);
            progressTimer = null;
        }
        var bar = loader._bar;
        if (!bar) return;
        bar.style.width = "100%";
        setTimeout(function () {
            bar.style.opacity = "0";
            bar.style.width = "0%";
        }, HIDE_DELAY_MS);
    }
    function hidePageLoader() {
        return;
    }

    function boot() {
        var loader = createLoader();
        if (!loader) return;

        showLoader(loader);
        setTimeout(function () {
            hideLoader(loader);
        }, HIDE_DELAY_MS);

        document.addEventListener("click", function (event) {
            var link = event.target && event.target.closest ? event.target.closest("a[href]") : null;
            if (!link) return;
            if (link.target === "_blank" || link.hasAttribute("download")) return;
            var href = link.getAttribute("href") || "";
            if (href.startsWith("#") || href.startsWith("javascript:")) return;
            if (link.dataset && link.dataset.noLoader === "true") return;
            showLoader(loader);
        }, true);

        document.addEventListener("submit", function () {
            showLoader(loader);
        }, true);

        window.addEventListener("pageshow", function () {
            hideLoader(loader);
            hidePageLoader();
        });
        window.addEventListener("visibilitychange", function () {
            if (document.visibilityState === "visible") {
                hideLoader(loader);
                hidePageLoader();
            }
        });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }
})();
