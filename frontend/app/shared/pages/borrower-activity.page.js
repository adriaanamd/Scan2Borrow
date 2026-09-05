import { ActivityTimelineComponent } from "../components/activity-timeline/activity-timeline.component.js";

export class BorrowerActivityPage {
  constructor({ api, role, title, description, classPrefix, document = globalThis.document, fetchImpl = globalThis.fetch } = {}) {
    this.api = api;
    this.role = role;
    this.title = title;
    this.description = description;
    this.classPrefix = classPrefix || "borrower-activity";
    this.document = document;
    this.fetchImpl = fetchImpl;
    this.timeline = new ActivityTimelineComponent(
      this.document?.getElementById("activity-timeline"),
      { document: this.document, classPrefix: this.classPrefix },
    );
  }

  start() {
    this.document.title = `${this.title} | Scan2Borrow`;
    this.document.getElementById("activity-title")?.replaceChildren(this.document.createTextNode(this.title));
    this.document.getElementById("activity-description")?.replaceChildren(this.document.createTextNode(this.description));
    this.document.getElementById("current-user-role")?.replaceChildren(this.document.createTextNode(this.role));
    return this.load();
  }

  load() {
    return this.fetchImpl(this.api, { headers: { "X-Requested-With": "fetch" } })
      .then((response) => response.json().then((payload) => ({ ok: response.ok && payload.ok, payload })))
      .then(({ ok, payload }) => {
        if (!ok) throw new Error(payload.message || payload.errors?.[0] || "Unable to load activity.");
        this.render(payload.data?.activity || []);
        return payload;
      })
      .catch((error) => {
        this.timeline.renderError(error.message || "Unable to load activity.");
        return null;
      });
  }

  render(rows) {
    this.timeline.render(Array.isArray(rows) ? rows : []);
  }
}
