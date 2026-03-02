(function () {
    function isMobile() {
        return window.matchMedia("(max-width: 768px)").matches;
    }

    function pickNavContainer(header) {
        if (!header) return null;

        var nav = header.querySelector("nav");
        if (nav) return nav;

        var candidates = header.querySelectorAll("div, section");
        var best = null;
        var bestScore = 0;

        candidates.forEach(function (el) {
            var controls = el.querySelectorAll("a, button");
            if (controls.length < 2) return;

            // Prefer small action rows, avoid wrapping full header containers.
            var score = controls.length;
            var className = (el.className || "").toString();
            if (className.includes("text-sm")) score += 2;
            if (className.includes("gap-2") || className.includes("gap-3")) score += 2;
            if (className.includes("justify-between")) score -= 1;
            if (el.querySelector("h1,h2,h3")) score -= 2;

            if (score > bestScore) {
                bestScore = score;
                best = el;
            }
        });

        return best;
    }

    function applyBottomNav() {
        var header = document.querySelector("header");
        if (!header) return;

        var existing = header.querySelector(".mobile-bottom-nav-active");
        if (existing) {
            if (!isMobile()) existing.classList.remove("mobile-bottom-nav-active");
            return;
        }

        var target = pickNavContainer(header);
        if (!target) return;

        if (isMobile()) {
            target.classList.add("mobile-bottom-nav-active");
        } else {
            target.classList.remove("mobile-bottom-nav-active");
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", applyBottomNav);
    } else {
        applyBottomNav();
    }
    window.addEventListener("resize", applyBottomNav);
})();
