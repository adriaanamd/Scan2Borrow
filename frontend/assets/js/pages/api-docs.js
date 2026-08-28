class ApiDocsController {
  constructor() {
    this.tags = document.getElementById("api-docs-tags");
    this.operations = document.getElementById("api-docs-operations");
    this.search = document.getElementById("api-docs-search");
    this.status = document.getElementById("api-docs-status");
    this.endpoints = [];
    this.load();
  }

  async load() {
    try {
      const response = await fetch("/scan2borrow/api/admin/api-docs", {
        headers: { Accept: "application/json", "X-Requested-With": "fetch" },
      });
      const payload = await response.json();
      if (!response.ok || payload.ok === false) {
        throw new Error(payload.errors?.[0] || "Unable to load API documentation.");
      }
      this.endpoints = Array.isArray(payload.data?.endpoints)
        ? payload.data.endpoints
        : [];
      this.renderTags();
      this.renderOperations();
    } catch (error) {
      this.status.textContent = error.message || "Unable to load API documentation.";
      this.operations.replaceChildren(this.empty("API documentation is unavailable."));
    }
  }

  renderTags() {
    const counts = new Map();
    this.endpoints.forEach((endpoint) =>
      counts.set(endpoint.tag, (counts.get(endpoint.tag) || 0) + 1),
    );
    this.tags.replaceChildren();
    [...counts.entries()].forEach(([tag, count], index) => {
      const button = document.createElement("button");
      button.type = "button";
      button.className = `api-docs-tag${index === 0 ? " is-active" : ""}`;
      button.innerHTML = `<span>${this.escape(tag)}</span><span class="api-docs-tag-count">${count}</span>`;
      button.addEventListener("click", () => {
        this.tags.querySelectorAll(".api-docs-tag").forEach((node) => node.classList.remove("is-active"));
        button.classList.add("is-active");
        this.search.value = tag;
        this.renderOperations();
      });
      this.tags.appendChild(button);
    });
  }

  renderOperations() {
    const query = this.search.value.trim().toLowerCase();
    const filtered = this.endpoints.filter((endpoint) => {
      if (!query) return true;
      return [endpoint.tag, endpoint.method, endpoint.path, endpoint.summary, endpoint.description]
        .join(" ")
        .toLowerCase()
        .includes(query);
    });
    this.operations.replaceChildren();
    const groups = new Map();
    filtered.forEach((endpoint) => {
      if (!groups.has(endpoint.tag)) groups.set(endpoint.tag, []);
      groups.get(endpoint.tag).push(endpoint);
    });
    groups.forEach((endpoints, tag) => this.operations.appendChild(this.group(tag, endpoints)));
    if (!filtered.length) this.operations.appendChild(this.empty("No endpoints match your search."));
    this.status.textContent = `${filtered.length} of ${this.endpoints.length} endpoints`;
  }

  group(tag, endpoints) {
    const section = document.createElement("section");
    section.className = "api-docs-group";
    section.id = `api-docs-group-${tag.toLowerCase().replace(/[^a-z0-9]+/g, "-")}`;
    const heading = document.createElement("div");
    heading.className = "api-docs-group-title";
    heading.innerHTML = `<h2>${this.escape(tag)}</h2><span>${endpoints.length} operation${endpoints.length === 1 ? "" : "s"}</span>`;
    section.appendChild(heading);
    endpoints.forEach((endpoint) => section.appendChild(this.operation(endpoint)));
    return section;
  }

  operation(endpoint) {
    const method = String(endpoint.method || "GET").toUpperCase();
    const colors = { GET: "#16803c", POST: "#1769aa", PUT: "#9a6700", PATCH: "#9a6700", DELETE: "#b42318" };
    const details = document.createElement("details");
    details.className = "api-docs-operation";
    details.style.setProperty("--method-color", colors[method] || "#075985");
    const summary = document.createElement("summary");
    summary.innerHTML = `<span class="api-docs-method" style="background:${colors[method] || "#075985"}">${this.escape(method)}</span><span class="api-docs-path">${this.escape(endpoint.path)}</span><span class="api-docs-summary">${this.escape(endpoint.summary)}</span><span class="api-docs-auth">${this.escape(endpoint.auth)}</span>`;
    details.appendChild(summary);
    const body = document.createElement("div");
    body.className = "api-docs-operation-body";
    body.innerHTML = `<p class="api-docs-description">${this.escape(endpoint.description)}</p>`;
    const grid = document.createElement("div");
    grid.className = "api-docs-detail-grid";
    grid.appendChild(this.detail("Parameters", endpoint.parameters || []));
    grid.appendChild(this.detail("Response", [endpoint.response || "JSON response."]));
    body.appendChild(grid);
    details.appendChild(body);
    return details;
  }

  detail(title, values) {
    const wrapper = document.createElement("div");
    wrapper.className = "api-docs-detail";
    const heading = document.createElement("h3");
    heading.textContent = title;
    wrapper.appendChild(heading);
    const list = document.createElement("ul");
    values.forEach((value) => {
      const item = document.createElement("li");
      item.textContent = value;
      list.appendChild(item);
    });
    if (!values.length) {
      const item = document.createElement("li");
      item.textContent = "None";
      list.appendChild(item);
    }
    wrapper.appendChild(list);
    return wrapper;
  }

  empty(message) {
    const node = document.createElement("div");
    node.className = "api-docs-empty";
    node.textContent = message;
    return node;
  }

  escape(value) {
    const node = document.createElement("span");
    node.textContent = value == null ? "" : String(value);
    return node.innerHTML;
  }
}

window.addEventListener("DOMContentLoaded", () => new ApiDocsController());
