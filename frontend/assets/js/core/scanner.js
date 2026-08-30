(function () {
  "use strict";

  var HTML5_QRCODE_SRC =
    "https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js";
  var libLoading = null;

  function showError(message) {
    var host = document.getElementById("toast-host");
    if (!host) {
      host = document.createElement("div");
      host.id = "toast-host";
      host.className = "toast-container position-fixed bottom-0 end-0 p-3";
      host.style.zIndex = "1090";
      if (!document.body) return;
      document.body.appendChild(host);
    }

    var toast = document.createElement("div");
    toast.className = "toast show align-items-center text-bg-danger border-0";
    toast.setAttribute("role", "alert");
    toast.setAttribute("aria-live", "assertive");
    toast.setAttribute("aria-atomic", "true");

    var body = document.createElement("div");
    body.className = "toast-body";
    body.textContent = String(message || "Scanner unavailable.");
    toast.appendChild(body);
    host.appendChild(toast);

    if (window.bootstrap && window.bootstrap.Toast) {
      var instance = new window.bootstrap.Toast(toast, { delay: 5000 });
      instance.show();
      toast.addEventListener("hidden.bs.toast", function () {
        toast.remove();
      });
    } else {
      window.setTimeout(function () {
        toast.remove();
      }, 5000);
    }
  }

  function loadLibrary() {
    if (window.Html5Qrcode) return Promise.resolve();
    if (libLoading) return libLoading;
    libLoading = new Promise(function (resolve, reject) {
      var s = document.createElement("script");
      s.src = HTML5_QRCODE_SRC;
      s.onload = resolve;
      s.onerror = function () {
        reject(new Error("Failed to load scanner library"));
      };
      document.head.appendChild(s);
    });
    return libLoading;
  }

  function startScanner(targetInput, autoSubmit, button) {
    var viewId = "scanner-view";
    var view = document.getElementById(viewId);
    if (!view) {
      view = document.createElement("div");
      view.id = viewId;
      targetInput.parentNode.parentNode.appendChild(view);
    }

    var scanner = new Html5Qrcode(viewId);
    button.disabled = true;
    button.textContent = "Starting camera...";

    scanner
      .start(
        { facingMode: "environment" },
        { fps: 10, qrbox: { width: 280, height: 140 } },
        function onScan(decodedText) {
          targetInput.value = decodedText.trim();
          scanner.stop().then(function () {
            view.innerHTML = "";
            button.disabled = false;
            button.textContent = "Scan with Camera";
            if (autoSubmit && targetInput.form) {
              targetInput.form.submit();
            } else {
              targetInput.focus();
            }
          });
        },
        function onError() {
          /* ignore per-frame decode errors */
        },
      )
      .catch(function (err) {
        button.disabled = false;
        button.textContent = "Scan with Camera";
        showError("Unable to access camera: " + err);
      });

    // Allow stopping by clicking the view.
    view.onclick = function () {
      scanner.stop().then(function () {
        view.innerHTML = "";
        button.disabled = false;
        button.textContent = "Scan with Camera";
      });
    };
  }

  document.addEventListener("click", function (ev) {
    var btn = ev.target.closest("[data-scan-target]");
    if (!btn) return;
    ev.preventDefault();

    var targetInput = document.getElementById(
      btn.getAttribute("data-scan-target"),
    );
    if (!targetInput) return;

    var autoSubmit = btn.hasAttribute("data-scan-submit");

    loadLibrary()
      .then(function () {
        startScanner(targetInput, autoSubmit, btn);
      })
      .catch(function (err) {
        showError(err.message);
      });
  });
})();
