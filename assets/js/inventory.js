(function () {
    "use strict";

    var API = "books_api.php";
    var csrfMeta = document.querySelector('meta[name="csrf"]');
    var CSRF = csrfMeta ? csrfMeta.content : '';

    var state = {
        search: "",
        status: "",
        archived: false,
        sort: "created_at",
        dir: "desc",
        page: 1,
        per_page: 10,
        selected: new Set(),
    };


    var $ = function (id) { return document.getElementById(id); };
    var tbody       = $("inv-body");
    var searchInput = $("inv-search");
    var statusFilter = $("inv-status");
    var viewToggle  = $("inv-view");
    var pager       = $("inv-pager");
    var countLabel  = $("inv-count");
    var selectAll   = $("inv-select-all");
    var bulkBar     = $("inv-bulkbar");
    var bulkCount   = $("inv-bulkcount");

    var offcanvasEl = $("bookDrawer");
    var drawer      = new bootstrap.Offcanvas(offcanvasEl);
    var form        = $("book-form");
    var coverFileInput = $("cover-file");
    var coverPreview = $("cover-preview");
    var coverPreviewWrap = $("cover-preview-wrap");
    var coverObjectUrl = null;

    function toast(message, ok) {
        var host = $("toast-host");
        var el = document.createElement("div");
        el.className = "toast align-items-center text-white border-0 show mb-2 bg-" + (ok ? "success" : "danger");
        el.role = "alert";
        el.innerHTML =
            '<div class="d-flex"><div class="toast-body">' + escapeHtml(message) +
            '</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>';
        host.appendChild(el);
        var t = new bootstrap.Toast(el, { delay: 3500 });
        t.show();
        el.addEventListener("hidden.bs.toast", function () { el.remove(); });
    }

    function resolveCoverUrl(value) {
        if (!value) return "";
        if (/^(https?:)?\/\//i.test(value) || value.indexOf("data:image/") === 0) return value;
        try { return new URL(value, window.location.href).toString(); } catch (e) { return value; }
    }

    function showCoverPreview(url) {
        if (!coverPreview || !coverPreviewWrap) return;
        if (coverObjectUrl) {
            URL.revokeObjectURL(coverObjectUrl);
            coverObjectUrl = null;
        }
        if (url) {
            coverPreview.src = url;
            coverPreviewWrap.style.display = "block";
        } else {
            coverPreview.src = "";
            coverPreviewWrap.style.display = "none";
        }
    }

    function previewSelectedCover(input) {
        if (!input || !input.files || !input.files[0]) {
            showCoverPreview("");
            return;
        }
        var file = input.files[0];
        if (!file.type || !file.type.startsWith("image/")) {
            showCoverPreview("");
            return;
        }
        if (coverObjectUrl) {
            URL.revokeObjectURL(coverObjectUrl);
        }
        coverObjectUrl = URL.createObjectURL(file);
        showCoverPreview(coverObjectUrl);
    }

    function escapeHtml(s) {
        return String(s == null ? "" : s).replace(/[&<>"']/g, function (c) {
            return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
        });
    }

    function badge(status) {
        var map = { Available: "success", Borrowed: "danger", Reserved: "warning text-dark" };
        return '<span class="badge bg-' + (map[status] || "secondary") + '">' + escapeHtml(status) + "</span>";
    }

    function apiGet(params) {
        var qs = new URLSearchParams(params).toString();
        return fetch(API + "?" + qs, { headers: { "X-Requested-With": "fetch" } }).then(function (r) { return r.json(); });
    }

    function apiPost(action, data) {
        var body = new FormData();
        body.append("action", action);
        body.append("csrf", CSRF);
        Object.keys(data).forEach(function (k) {
            if (Array.isArray(data[k])) {
                data[k].forEach(function (v) { body.append(k + "[]", v); });
            } else {
                body.append(k, data[k]);
            }
        });
        return fetch(API, { method: "POST", body: body }).then(function (r) { return r.json(); });
    }

    function load() {
        apiGet({
            action: "list",
            search: state.search,
            status: state.status,
            archived: state.archived ? 1 : 0,
            sort: state.sort,
            dir: state.dir,
            page: state.page,
            per_page: state.per_page,
        }).then(render).catch(function (err) { 
            console.error('Load error:', err);
            toast("Failed to load inventory. Check console for details.", false); 
        });
    }

    function render(res) {
        if (!res.ok) { toast(res.message || "Error", false); return; }
        state.selected.clear();
        updateBulkBar();
        selectAll.checked = false;

        tbody.innerHTML = "";
        if (!res.data.length) {
            tbody.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-4">No books found.</td></tr>';
        }

        res.data.forEach(function (b) {
            var tr = document.createElement("tr");
            var actions = state.archived
                ? '<button class="btn btn-success btn-sm" data-act="restore" data-id="' + b.id + '">Restore</button> ' +
                  '<button class="btn btn-outline-danger btn-sm" data-act="delete" data-id="' + b.id + '">Delete</button>'
                : '<button class="btn btn-outline-primary btn-sm" data-act="edit" data-id="' + b.id + '">Edit</button> ' +
                  '<button class="btn btn-outline-warning btn-sm" data-act="archive" data-id="' + b.id + '">Archive</button>';

            function createCell(content, className, style) {
                var td = document.createElement("td");
                if (className) td.className = className;
                if (style) td.style.cssText = style;
                td.innerHTML = content;
                return td;
            }

            tr.appendChild(createCell('<input type="checkbox" class="form-check-input row-check" value="' + b.id + '">', '', 'width:38px;'));
            tr.appendChild(createCell(escapeHtml(b.barcode || ''), '', 'min-width:110px;'));
            tr.appendChild(createCell('<strong>' + escapeHtml(b.title || '') + '</strong>' + (b.isbn ? '<br><span class="text-muted small">ISBN ' + escapeHtml(b.isbn) + '</span>' : ''), '', 'min-width:220px;'));
            tr.appendChild(createCell(escapeHtml(b.author || ''), '', 'min-width:160px;'));
            tr.appendChild(createCell(b.publisher ? escapeHtml(b.publisher) : '<span class="text-muted">&mdash;</span>', '', 'min-width:140px;'));
            tr.appendChild(createCell(escapeHtml(b.description || 'No description available'), 'text-muted small', 'min-width:220px;'));
            tr.appendChild(createCell(b.category_name ? escapeHtml(b.category_name) : '<span class="text-muted">&mdash;</span>', '', 'min-width:120px;'));
            tr.appendChild(createCell(badge(b.status), '', 'min-width:110px;'));
            tr.appendChild(createCell((b.due_date ? '&#128197; Due ' + escapeHtml(b.due_date) : '<span class="text-muted">&mdash;</span>') + (b.return_date ? '<br>&#8617;&#65039; Ret ' + escapeHtml(b.return_date) : ''), 'text-muted small', 'min-width:140px;'));
            tr.appendChild(createCell('&#128205; ' + escapeHtml(b.floor_no || '') + (b.section_name ? ' · ' + escapeHtml(b.section_name) : '') + (b.shelf_no ? ' · Shelf ' + escapeHtml(b.shelf_no) : '') + (b.row_no ? ' · Row ' + escapeHtml(b.row_no) : ''), 'text-muted small', 'min-width:180px;'));
            tr.appendChild(createCell(actions, 'text-nowrap', 'min-width:120px;'));
            tr.dataset.book = JSON.stringify(b);
            tbody.appendChild(tr);
        });

        countLabel.textContent = res.total + " book(s)";
        renderPager(res.page, res.pages);
        renderSortIndicators();
    }

    function renderPager(page, pages) {
        pager.innerHTML = "";
        if (pages <= 1) return;
        function item(label, target, disabled, active) {
            var li = document.createElement("li");
            li.className = "page-item" + (disabled ? " disabled" : "") + (active ? " active" : "");
            li.innerHTML = '<a class="page-link" href="#">' + label + "</a>";
            if (!disabled && !active) {
                li.addEventListener("click", function (e) { e.preventDefault(); state.page = target; load(); });
            }
            pager.appendChild(li);
        }
        item("&laquo;", page - 1, page <= 1, false);
        for (var i = 1; i <= pages; i++) item(i, i, false, i === page);
        item("&raquo;", page + 1, page >= pages, false);
    }

    function renderSortIndicators() {
        document.querySelectorAll("th[data-sort]").forEach(function (th) {
            var arrow = th.querySelector(".sort-arrow");
            if (arrow) arrow.textContent = th.dataset.sort === state.sort ? (state.dir === "asc" ? " \u25B2" : " \u25BC") : "";
        });
    }

    function updateBulkBar() {
        var n = state.selected.size;
        bulkCount.textContent = n;
        bulkBar.style.display = n ? "flex" : "none";
        document.querySelectorAll("[data-bulk]").forEach(function (btn) {
            var act = btn.getAttribute("data-bulk");
            var showInArchived = act === "restore" || act === "delete";
            btn.style.display = (state.archived === showInArchived) ? "" : "none";
        });
    }

    function doAction(action, ids, confirmMsg) {
        if (confirmMsg && !window.confirm(confirmMsg)) return;
        apiPost(action, { ids: ids }).then(function (res) {
            toast(res.message, res.ok);
            if (res.ok) load();
        }).catch(function () { toast("Request failed.", false); });
    }

    function openDrawer(book) {
        form.reset();
        showCoverPreview("");
        $("book-id").value = book ? book.id : "";
        $("drawer-title").textContent = book ? "Edit Book" : "Add New Book";
        if (book) {
            ["barcode", "isbn", "title", "author", "publisher", "description", "category_name", "keywords", "floor_no", "section_name", "shelf_no", "row_no", "due_date", "return_date", "status"]
                .forEach(function (f) { if (form.elements[f]) form.elements[f].value = book[f] || ""; });
            if (book.cover_file || book.cover_image) {
                showCoverPreview(resolveCoverUrl(book.cover_file || book.cover_image));
            }
        }
        drawer.show();
    }

    if (coverFileInput) {
        coverFileInput.addEventListener("change", function () { previewSelectedCover(this); });
    }

    var searchTimer;
    searchInput.addEventListener("input", function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () {
            state.search = searchInput.value.trim();
            state.page = 1;
            load();
        }, 300);
    });

    statusFilter.addEventListener("change", function () {
        state.status = statusFilter.value;
        state.page = 1;
        load();
    });

    viewToggle.addEventListener("change", function () {
        state.archived = viewToggle.checked;
        state.page = 1;
        load();
    });

    document.querySelectorAll("th[data-sort]").forEach(function (th) {
        th.style.cursor = "pointer";
        th.addEventListener("click", function () {
            var col = th.dataset.sort;
            if (state.sort === col) {
                state.dir = state.dir === "asc" ? "desc" : "asc";
            } else {
                state.sort = col;
                state.dir = "asc";
            }
            load();
        });
    });

    selectAll.addEventListener("change", function () {
        document.querySelectorAll(".row-check").forEach(function (cb) {
            cb.checked = selectAll.checked;
            if (cb.checked) state.selected.add(cb.value); else state.selected.delete(cb.value);
        });
        updateBulkBar();
    });

    tbody.addEventListener("change", function (e) {
        if (e.target.classList.contains("row-check")) {
            if (e.target.checked) state.selected.add(e.target.value); else state.selected.delete(e.target.value);
            updateBulkBar();
        }
    });

    tbody.addEventListener("click", function (e) {
        var btn = e.target.closest("button[data-act]");
        if (!btn) return;
        var id = btn.dataset.id;
        var act = btn.dataset.act;
        if (act === "edit") {
            openDrawer(JSON.parse(btn.closest("tr").dataset.book));
        } else if (act === "archive") {
            doAction("archive", [id], "Archive this book?");
        } else if (act === "restore") {
            doAction("restore", [id]);
        } else if (act === "delete") {
            doAction("delete", [id], "Permanently delete this archived book? This cannot be undone.");
        }
    });

    document.querySelectorAll("[data-bulk]").forEach(function (btn) {
        btn.addEventListener("click", function () {
            var ids = Array.from(state.selected);
            if (!ids.length) return;
            var act = btn.getAttribute("data-bulk");
            var msg = act === "delete" ? "Permanently delete " + ids.length + " book(s)?" :
                      act === "archive" ? "Archive " + ids.length + " book(s)?" : null;
            doAction(act, ids, msg);
        });
    });

    $("btn-add").addEventListener("click", function () { openDrawer(null); });

    form.addEventListener("submit", function (e) {
        e.preventDefault();
        var id = $("book-id").value;
        var data = new FormData(form);
        data.append("action", id ? "update" : "create");
        data.append("csrf", CSRF);
        if (id) data.append("id", id);

        fetch(API, { method: "POST", body: data }).then(function (r) {
            return r.text().then(function (text) {
                var payload = null;
                try { payload = text ? JSON.parse(text) : null; } catch (e) { payload = null; }
                if (!r.ok) {
                    throw new Error((payload && payload.message) || text || "Save failed.");
                }
                if (!payload || typeof payload !== "object") {
                    throw new Error(text || "Save failed.");
                }
                return payload;
            });
        }).then(function (res) {
            toast(res.message, res.ok);
            if (res.ok) { drawer.hide(); load(); }
        }).catch(function (err) { toast(err.message || "Save failed.", false); });
    });

    load();
})();
