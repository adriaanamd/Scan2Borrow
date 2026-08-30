(function (root) {
  "use strict";

  const defaults = {
    title: "Confirm action",
    message: "Are you sure you want to continue?",
    confirmLabel: "Confirm",
    confirmClass: "btn-danger",
  };

  class ConfirmationService {
    constructor({ document = root.document, window = root.window || root } = {}) {
      this.document = document;
      this.window = window;
      this.modalElement = null;
      this.modal = null;
      this.pending = null;
      this.installed = false;
    }

    confirm(options = {}) {
      const config = { ...defaults, ...options };
      if (this.pending) return this.pending.promise;
      this.ensureModal();

      let resolvePromise;
      let rejectPromise;
      const promise = new Promise((resolve, reject) => {
        resolvePromise = resolve;
        rejectPromise = reject;
      });
      this.pending = {
        config,
        promise,
        resolve: resolvePromise,
        reject: rejectPromise,
        trigger: config.trigger || null,
        settling: false,
      };

      this.title.textContent = config.title;
      this.message.textContent = config.message;
      this.confirmButton.textContent = config.confirmLabel;
      this.confirmButton.className = "btn " + config.confirmClass;
      this.cancelButton.disabled = false;
      this.confirmButton.disabled = false;
      this.modal.show();
      return promise;
    }

    ensureModal() {
      if (this.modalElement) return;

      const modal = this.document.createElement("div");
      modal.id = "scan2borrow-confirmation-modal";
      modal.className = "modal fade";
      modal.setAttribute("tabindex", "-1");
      modal.setAttribute("role", "dialog");
      modal.setAttribute("aria-modal", "true");
      modal.setAttribute("data-confirm-modal", "true");

      const dialog = this.document.createElement("div");
      dialog.className = "modal-dialog modal-dialog-centered";
      const content = this.document.createElement("div");
      content.className = "modal-content";
      const header = this.document.createElement("div");
      header.className = "modal-header";
      const title = this.document.createElement("h2");
      title.className = "modal-title h5";
      title.id = "scan2borrow-confirmation-title";
      title.setAttribute("data-confirm-title", "true");
      const close = this.document.createElement("button");
      close.type = "button";
      close.className = "btn-close";
      close.setAttribute("aria-label", "Close");
      close.setAttribute("data-confirm-cancel", "true");
      const body = this.document.createElement("div");
      body.className = "modal-body";
      const message = this.document.createElement("p");
      message.className = "mb-0";
      message.id = "scan2borrow-confirmation-message";
      message.setAttribute("data-confirm-message-target", "true");
      const footer = this.document.createElement("div");
      footer.className = "modal-footer";
      const cancel = this.document.createElement("button");
      cancel.type = "button";
      cancel.className = "btn btn-light";
      cancel.textContent = "Cancel";
      cancel.setAttribute("data-confirm-cancel", "true");
      const confirm = this.document.createElement("button");
      confirm.type = "button";
      confirm.className = "btn btn-danger";
      confirm.setAttribute("data-confirm-confirm", "true");

      header.appendChild(title);
      header.appendChild(close);
      body.appendChild(message);
      footer.appendChild(cancel);
      footer.appendChild(confirm);
      content.appendChild(header);
      content.appendChild(body);
      content.appendChild(footer);
      dialog.appendChild(content);
      modal.appendChild(dialog);
      modal.setAttribute("aria-labelledby", title.id);
      modal.setAttribute("aria-describedby", message.id);
      this.document.body.appendChild(modal);

      this.modalElement = modal;
      this.title = title;
      this.message = message;
      this.cancelButton = cancel;
      this.closeButton = close;
      this.confirmButton = confirm;
      const getBootstrapModal = this.window?.bootstrap?.Modal?.getOrCreateInstance;
      if (typeof getBootstrapModal === "function") {
        this.modal = getBootstrapModal.call(this.window.bootstrap.Modal, modal);
      } else {
        modal.dataset.confirmMode = "fallback";
        modal.setAttribute("aria-hidden", "true");
        this.modal = {
          show: () => {
            modal.classList.add("show");
            modal.setAttribute("aria-hidden", "false");
            this.confirmButton.focus?.();
          },
          hide: () => {
            modal.classList.remove("show");
            modal.setAttribute("aria-hidden", "true");
          },
        };
        modal.addEventListener("click", (event) => {
          if (event.target === modal) this.cancel();
        });
        modal.addEventListener("keydown", (event) => this.handleFallbackKeydown(event));
      }

      cancel.addEventListener("click", () => this.cancel());
      close.addEventListener("click", () => this.cancel());
      confirm.addEventListener("click", () => this.accept());
      modal.addEventListener("hidden.bs.modal", () => {
        if (this.pending && !this.pending.settling) this.cancel();
      });
    }

    handleFallbackKeydown(event) {
      if (!this.pending) return;
      if (event.key === "Escape") {
        event.preventDefault();
        this.cancel();
        return;
      }
      if (event.key !== "Tab") return;

      const focusable = [this.closeButton, this.cancelButton, this.confirmButton]
        .filter((element) => element && !element.disabled);
      if (!focusable.length) return;
      const currentIndex = focusable.indexOf(this.document.activeElement);
      const nextIndex = currentIndex < 0
        ? 0
        : (currentIndex + (event.shiftKey ? -1 : 1) + focusable.length) % focusable.length;
      event.preventDefault();
      focusable[nextIndex].focus?.();
    }

    async accept() {
      const pending = this.pending;
      if (!pending || pending.settling) return;
      pending.settling = true;
      this.cancelButton.disabled = true;
      this.confirmButton.disabled = true;
      this.confirmButton.textContent = "Processing…";
      try {
        await pending.config.onConfirm?.();
        this.finish(true);
      } catch (error) {
        this.finish(false, error);
      }
    }

    cancel() {
      if (!this.pending || this.pending.settling) return;
      this.finish(false);
    }

    finish(result, error = null) {
      const pending = this.pending;
      if (!pending) return;
      this.pending = null;
      this.cancelButton.disabled = false;
      this.confirmButton.disabled = false;
      this.confirmButton.textContent = defaults.confirmLabel;
      this.modal.hide();
      pending.trigger?.focus?.();
      if (error) pending.reject(error);
      else pending.resolve(result);
    }

    install() {
      if (this.installed || !this.document?.addEventListener) return;
      this.installed = true;
      this.document.addEventListener("click", (event) => this.guardLink(event));
      this.document.addEventListener("submit", (event) => this.guardForm(event));
    }

    guardLink(event) {
      const link = event.target?.closest?.(".nav-logout");
      if (!link || link.dataset.confirmBypass === "true") return;
      event.preventDefault();
      this.confirm({
        title: link.dataset.confirmTitle || "Log out?",
        message: link.dataset.confirmMessage || "Are you sure you want to log out?",
        confirmLabel: link.dataset.confirmLabel || "Log out",
        confirmClass: link.dataset.confirmClass || "btn-danger",
        trigger: link,
        onConfirm: () => {
          link.dataset.confirmBypass = "true";
          this.window.location.href = link.href;
        },
      }).catch(() => {});
    }

    guardForm(event) {
      const form = event.target?.closest?.("form") || event.target;
      const submitter = event.submitter || null;
      const actionData = submitter?.dataset?.confirmAction
        ? submitter.dataset
        : form?.dataset?.confirmAction
          ? form.dataset
          : null;
      if (!form || !actionData || form.dataset.confirmBypass === "true") return;
      event.preventDefault();
      const proceed = () => {
        form.dataset.confirmBypass = "true";
        if (typeof form.requestSubmit === "function") form.requestSubmit(submitter);
        else form.submit();
        delete form.dataset.confirmBypass;
      };
      const confirmAction = () => this.confirm({
        title: actionData.confirmTitle || "Confirm action",
        message: actionData.confirmMessage || "Are you sure you want to continue?",
        confirmLabel: actionData.confirmLabel || "Confirm",
        confirmClass: actionData.confirmClass || "btn-danger",
        trigger: submitter || form,
        onConfirm: proceed,
      });
      const reasonSelector = form.dataset.confirmReasonSelector;
      const reason = reasonSelector ? form.querySelector(reasonSelector) : null;
      const reasonWarning = reason && !String(reason.value || "").trim()
        ? this.confirm({
            title: "Missing rejection reason",
            message: "Reject without a reason?",
            confirmLabel: "Continue",
            confirmClass: "btn-danger",
            trigger: submitter || form,
          })
        : Promise.resolve(true);
      reasonWarning.then((accepted) => {
        if (accepted) return confirmAction();
        return false;
      }).catch(() => {});
    }
  }

  const service = new ConfirmationService();
  service.install();
  root.Scan2BorrowConfirmation = service;
  root.ConfirmationService = ConfirmationService;
})(typeof globalThis !== "undefined" ? globalThis : window);
