import { ApiClient } from "../../../../app/core/api/api-client.js";
import { StudentProfileChangeService } from "../../services/profile-change.service.js";
import { normalizeStudentProfileChange } from "../../models/profile-change.model.js";

export class StudentSettingsPage {
  constructor(document = globalThis.document, { service, window = globalThis.window } = {}) {
    this.document = document;
    this.window = window;
    this.error = document?.getElementById("student-settings-error");
    this.success = document?.getElementById("student-settings-success");
    this.form = document?.getElementById("student-settings-form");
    this.photoData = "";
    this.service = service || new StudentProfileChangeService({ api: new ApiClient({ csrf: document?.querySelector('meta[name="csrf"]')?.content || "", fetchImpl: window?.fetch?.bind(window) }) });
    this.bindEvents();
    this.load();
  }

  load() {
    return this.service.load().then((response) => {
      if (!response?.ok) throw new Error(response?.message || "Unable to load your account details.");
      this.render(normalizeStudentProfileChange(response.data || {}));
      return response;
    }).catch((error) => {
      if (this.error) { this.error.textContent = error.message; this.error.hidden = false; }
      return null;
    });
  }

  render(data) {
    const profile = data.profile || {};
    const values = {
      "student-firstname": profile.firstname,
      "student-middlename": profile.middlename,
      "student-lastname": profile.lastname,
      "student-email": profile.email,
      "student-contact": profile.contact_no,
      "student-course": profile.course,
      "student-year-level": profile.year_level,
      "student-department": profile.department,
      "student-position": profile.position,
      "student-barcode": profile.barcode,
      "current-user-name": [profile.firstname, profile.lastname].filter(Boolean).join(" "),
      "current-user-role": "Student",
    };
    Object.entries(values).forEach(([id, value]) => {
      const node = this.document?.getElementById(id);
      if (!node) return;
      if ("value" in node) node.value = value || "";
      else node.textContent = value || "";
    });
    this.renderPhoto(profile.photo);
    this.renderRequestStatus(data.pending_request);
  }

  renderPhoto(path) {
    const image = this.document?.getElementById("student-photo-preview");
    const empty = this.document?.getElementById("student-photo-empty");
    const safePath = this.safePhotoPath(path);
    if (image) { image.src = safePath; image.hidden = safePath === ""; }
    if (empty) { empty.hidden = safePath !== ""; }
  }

  renderRequestStatus(request) {
    const title = this.document?.getElementById("student-request-status-title");
    const detail = this.document?.getElementById("student-request-status-detail");
    const diff = this.document?.getElementById("student-request-diff");
    const submit = this.document?.getElementById("student-submit-request");
    if (!request) {
      if (title) title.textContent = "No pending request";
      if (detail) detail.textContent = "Your current profile is active. Submit a change when something needs correcting.";
      if (diff) diff.replaceChildren();
      if (submit) submit.disabled = false;
      return;
    }
    if (title) title.textContent = "Pending administrator review";
    if (detail) detail.textContent = request.requested_at ? `Submitted ${request.requested_at}.` : "Your request is waiting for review.";
    if (submit) submit.disabled = true;
    if (diff) {
      diff.replaceChildren();
      Object.entries(request.requested_values || {}).forEach(([field, value]) => {
        const row = this.document.createElement("div");
        row.className = "profile-request-diff__row";
        row.textContent = `${this.label(field)}: ${value || "(empty)"}`;
        diff.appendChild(row);
      });
      if (request.requested_photo) {
        const row = this.document.createElement("div");
        row.className = "profile-request-diff__row";
        row.textContent = "Profile photo: new photo attached";
        diff.appendChild(row);
      }
    }
  }

  submit(event) {
    event?.preventDefault();
    if (!this.form) return Promise.resolve(null);
    const body = new FormData(this.form);
    body.delete("photo");
    if (this.photoData) body.set("photo_data", this.photoData);
    return this.service.submit(body).then((response) => {
      if (!response?.ok) throw new Error(response?.message || response?.errors?.[0] || "Request failed.");
      if (this.success) { this.success.textContent = response.data?.message || "Profile change request submitted."; this.success.hidden = false; }
      return this.load();
    }).catch((error) => {
      if (this.error) { this.error.textContent = error.message; this.error.hidden = false; }
      return null;
    });
  }

  bindEvents() {
    this.form?.addEventListener("submit", (event) => this.submit(event));
    this.document?.getElementById("student-photo")?.addEventListener("change", (event) => this.readPhoto(event.target.files?.[0]));
  }

  readPhoto(file) {
    if (!file || typeof FileReader === "undefined") return;
    const reader = new FileReader();
    reader.addEventListener("load", () => { this.photoData = typeof reader.result === "string" ? reader.result : ""; this.renderPhoto(this.photoData); });
    reader.readAsDataURL(file);
  }

  safePhotoPath(path) {
    if (!path || typeof path !== "string") return "";
    return path.startsWith("/scan2borrow/uploads/") || path.startsWith("uploads/") ? (path.startsWith("uploads/") ? `/scan2borrow/${path}` : path) : "";
  }

  label(field) { return String(field).replaceAll("_", " ").replace(/^./, (letter) => letter.toUpperCase()); }

  escapeHtml(value) { return String(value == null ? "" : value).replace(/[&<>"']/g, (character) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[character])); }
}

if (typeof window !== "undefined") window.addEventListener("DOMContentLoaded", () => new StudentSettingsPage());
