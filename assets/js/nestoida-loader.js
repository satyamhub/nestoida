(function () {
    var LOADER_ID = "nestoida-global-loader";
    var HIDE_DELAY_MS = 420;

    function createLoader() {
        if (document.getElementById(LOADER_ID)) return null;

        var loader = document.createElement("div");
        loader.id = LOADER_ID;
        loader.setAttribute("aria-live", "polite");
        loader.style.position = "fixed";
        loader.style.inset = "0";
        loader.style.zIndex = "10000";
        loader.style.display = "flex";
        loader.style.alignItems = "center";
        loader.style.justifyContent = "center";
        loader.style.background = "rgba(255,255,255,0.96)";
        loader.style.backdropFilter = "blur(2px)";
        loader.style.opacity = "0";
        loader.style.pointerEvents = "none";
        loader.style.transition = "opacity 220ms ease";

        if (document.documentElement.classList.contains("dark")) {
            loader.style.background = "rgba(2,6,23,0.96)";
        }

        var inner = document.createElement("div");
        inner.style.display = "flex";
        inner.style.flexDirection = "column";
        inner.style.alignItems = "center";
        inner.style.gap = "12px";

        var logo = document.createElement("img");
        logo.src = "assets/img/nestoida-logo.svg";
        logo.alt = "Nestoida";
        logo.style.width = "72px";
        logo.style.height = "72px";

        var text = document.createElement("p");
        text.textContent = "Loading Nestoida...";
        text.style.margin = "0";
        text.style.fontSize = "12px";
        text.style.fontWeight = "700";
        text.style.letterSpacing = "0.12em";
        text.style.textTransform = "uppercase";
        text.style.color = document.documentElement.classList.contains("dark") ? "#e2e8f0" : "#0f172a";

        inner.appendChild(logo);
        inner.appendChild(text);
        loader.appendChild(inner);
        document.body.appendChild(loader);
        return loader;
    }

    function showLoader(loader) {
        if (!loader) return;
        loader.style.pointerEvents = "auto";
        loader.style.opacity = "1";
    }

    function hideLoader(loader) {
        if (!loader) return;
        loader.style.opacity = "0";
        loader.style.pointerEvents = "none";
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
            showLoader(loader);
        }, true);

        document.addEventListener("submit", function () {
            showLoader(loader);
        }, true);
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }
})();
