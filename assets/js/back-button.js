(function () {
    function createBackButton() {
        var btn = document.createElement("button");
        btn.type = "button";
        btn.setAttribute("aria-label", "Go back");
        btn.textContent = "← Back";
        btn.style.position = "fixed";
        btn.style.right = "16px";
        btn.style.bottom = "16px";
        btn.style.zIndex = "9999";
        btn.style.padding = "10px 14px";
        btn.style.borderRadius = "9999px";
        btn.style.border = "1px solid #d1d5db";
        btn.style.background = "#ffffff";
        btn.style.color = "#111827";
        btn.style.fontSize = "14px";
        btn.style.fontWeight = "600";
        btn.style.cursor = "pointer";
        btn.style.boxShadow = "0 6px 20px rgba(0, 0, 0, 0.12)";
        btn.style.transition = "opacity 0.2s ease";

        btn.addEventListener("mouseenter", function () {
            btn.style.opacity = "0.9";
        });
        btn.addEventListener("mouseleave", function () {
            btn.style.opacity = "1";
        });

        btn.addEventListener("click", function () {
            try {
                var hasInternalReferrer = false;
                if (document.referrer) {
                    try {
                        var ref = new URL(document.referrer);
                        hasInternalReferrer = ref.host === window.location.host;
                    } catch (e) {
                        hasInternalReferrer = false;
                    }
                }

                if (window.history.length > 1 && hasInternalReferrer) {
                    window.history.back();
                    return;
                }
            } catch (e) {}
            window.location.href = "index.php";
        });

        document.body.appendChild(btn);
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", createBackButton);
    } else {
        createBackButton();
    }
})();
