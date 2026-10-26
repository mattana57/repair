/* Shared, authenticated dashboard analytics. All drill-down tables are paginated by PHP. */
(() => {
  "use strict";
  const seed = document.getElementById("mbsFeaturesData");
  if (!seed) return;
  let data;
  try {
    data = JSON.parse(seed.textContent);
  } catch {
    return;
  }
  const $ = (id) => document.getElementById(id),
    esc = (s) =>
      String(s ?? "").replace(
        /[&<>"']/g,
        (c) =>
          ({
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            '"': "&quot;",
            "'": "&#39;",
          })[c],
      );
  const palette = [
    "#1e40af",
    "#2563eb",
    "#3b82f6",
    "#60a5fa",
    "#93c5fd",
    "#1d4ed8",
    "#0284c7",
  ];
  const dateText = (s) =>
    /^\d{4}-\d{2}-\d{2}/.test(s || "")
      ? s.slice(8, 10) +
        "/" +
        s.slice(5, 7) +
        "/" +
        s.slice(0, 4) +
        " " +
        s.slice(11, 16)
      : "ไม่ทราบวันเวลาเดิม";
  const accountDateText = (s) => dateText(s).replace(/ (\d{2}:\d{2})$/, "\u00a0\u00a0$1");
  const validDate = (s) => {
    if (!/^[1-9]\d{3}-\d{2}-\d{2}$/.test(s || "")) return false;
    const d = new Date(s + "T00:00:00Z");
    return !Number.isNaN(d.valueOf()) && d.toISOString().slice(0, 10) === s;
  };
  let selected = { kind: "", value: "", match: {} },
    reviewState = { id: "", star: 0, comments: false },
    chartRefs = [],
    fetchVersion = 0;
  window.mbsRanges = window.mbsRanges || {};
  const urlState = new URL(location.href);
  function period(rows, key = "overview", column = "created_at") {
    const range = window.mbsRanges[key];
    const a =
        range?.start?.value ||
        urlState.searchParams.get("mbs_" + key + "_start"),
      b = range?.end?.value || urlState.searchParams.get("mbs_" + key + "_end");
    const fields = key === "overview" ? overviewFields : null;
    return rows.filter((r) => {
      const dt = String(r[column] || "");
      if (validDate(a) && validDate(b) && a <= b)
        return dt.slice(0, 10) >= a && dt.slice(0, 10) <= b;
      return ["day", "month", "year"].every((part, i) => {
        const v =
          fields?.[part]?.value ||
          urlState.searchParams.get("mbs_" + key + "_" + part) ||
          "all";
        return (
          !/^\d+$/.test(v) ||
          +v === 0 ||
          +dt.slice([8, 5, 0][i], [10, 7, 4][i]) === +v
        );
      });
    });
  }
  let overviewFields = null,
    reviewCalendar = null;
  function ownRows() {
    return data.role === "technician"
      ? data.repairs.filter((r) => +r.technician_id === +data.technicianId)
      : data.repairs;
  }
  function overviewRows() {
    return period(ownRows());
  }
  function groups(rows, field) {
    const map = new Map();
    for (const r of rows) {
      const key = String(r[field] ?? "").trim();
      if (!key) continue;
      map.set(key, (map.get(key) || 0) + 1);
    }
    return [...map].sort(
      (a, b) => b[1] - a[1] || a[0].localeCompare(b[0], "th"),
    );
  }
  function technicianName(id) {
    return (
      data.technicians.find((t) => +t.id === +id)?.full_name ||
      "ไม่ระบุเจ้าหน้าที่"
    );
  }
  function reporterName(id) {
    return (
      data.repairs.find((r) => r.line_user_id === id)?.reporter_label ||
      "ไม่ระบุชื่อผู้แจ้ง"
    );
  }
  function locationLabel(row) {
    return row.location || "ไม่ระบุสถานที่";
  }
  function chart(
    canvas,
    pairs,
    kind,
    { donut = false, labels = null, click = null, horizontal = true } = {},
  ) {
    if (!canvas || !window.Chart) return;
    Chart.getChart(canvas)?.destroy();
    const keys = pairs.map((x) => x[0]),
      values = pairs.map((x) => x[1]);
    const baseConfig = {
      type: donut ? "doughnut" : "bar",
      data: {
        labels: labels || keys,
        datasets: [
          {
            label: "จำนวนงาน",
            data: values,
            backgroundColor: donut
              ? keys.map(
                  (k) =>
                    ({
                      รอรับเรื่อง:
                        data.role === "technician" ? "#fbbf24" : "#f59e0b",
                      กำลังดำเนินการ: "#38bdf8",
                      ซ่อมเสร็จแล้ว: "#10b981",
                    })[k] || "#a78bfa",
                )
              : values.map((_, i) => palette[i % palette.length]),
            borderRadius: donut ? 0 : 6,
            borderWidth: 0,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: donut ? "75%" : undefined,
        indexAxis: donut ? "x" : horizontal ? "y" : "x",
        plugins: {
          legend: { display: donut, position: "bottom" },
          tooltip: {
            callbacks: {
              label: (c) =>
                (labels || keys)[c.dataIndex] +
                ": " +
                values[c.dataIndex] +
                " รายการ",
            },
          },
        },
        scales: donut
          ? {}
          : {
              x: { beginAtZero: true, ticks: { precision: 0 } },
              y: {
                beginAtZero: true,
                ticks: { precision: 0, autoSkip: false },
              },
            },
        onClick: (e, items) => {
          if (!items.length) return;
          const key = keys[items[0].index];
          if (click) click(key);
          else if (kind) openAnalytics(kind, key);
        },
        onHover: (e, items) => {
          canvas.style.cursor =
            items.length && (click || kind) ? "pointer" : "default";
        },
      },
    };
    const overviewKind = {
      mainEquipChart: "equipment",
      eqChart: "equipment",
      mainStatusChart: "status",
      statusChart: "status",
      mainLocChart: "location",
      locChart: "location",
      mainTechChart: "technician",
    }[canvas.id];
    const restored =
      overviewKind &&
      window.MbsOverviewAppearance?.build(
        data.role,
        overviewKind,
        canvas,
        pairs,
        {
          labels,
          onClick: baseConfig.options.onClick,
          onHover: baseConfig.options.onHover,
          tooltip: baseConfig.options.plugins.tooltip.callbacks,
        },
      );
    const config = restored || baseConfig;
    if (donut && click && config.options?.plugins?.legend) {
      config.options.plugins.legend.onClick = (event, item) => {
        const key = keys[item.index];
        if (key) click(key);
      };
    }
    const instance = new Chart(canvas, config);
    if (canvas.closest("#mbsAnalytics")) chartRefs.push(instance);
    return instance;
  }
  function counts() {
    const rows = period(data.repairs),
      pending = rows.filter((r) => r.status === "รอรับเรื่อง").length,
      progress = rows.filter((r) => r.status === "กำลังดำเนินการ").length,
      completed = rows.filter((r) => r.status === "ซ่อมเสร็จแล้ว").length;
    return {
      total: pending + progress + completed,
      pending,
      in_progress: progress,
      completed,
    };
  }
  function openWorkStatus(status) {
    if (!["รอรับเรื่อง", "กำลังดำเนินการ", "ซ่อมเสร็จแล้ว"].includes(status))
      return;
    // Reuse precisely the same navigation and filters as the summary cards.
    if (data.role === "technician") {
      if (typeof window.showTab === "function")
        window.showTab("history", status, true);
    } else if (typeof window.filterRepairs === "function") {
      window.filterRepairs(status);
    }
  }
  function renderCards() {
    const c = counts();
    const extra = {
      execCountTotal: c.total,
      execCountWaiting: c.pending,
      execCountProgress: c.in_progress,
      execCountCompleted: c.completed,
    };
    for (const [id, v] of Object.entries(extra))
      if ($(id)) $(id).textContent = v;
    for (const [key, v] of Object.entries(c))
      for (const id of [
        "techCount-" + key,
        "adminCount-" + key,
        "executiveCount-" + key,
      ])
        if ($(id)) $(id).textContent = v;
  }
  function renderOverview() {
    if (data.error) return;
    if (!overviewFields) return;
    const rows = overviewRows();
    chart(
      $("mainEquipChart") || $("eqChart"),
      groups(rows, "equipment_type").slice(
        0,
        data.role === "technician" ? 6 : 7,
      ),
      "equipment",
      { horizontal: false },
    );
    chart(
      $("mainStatusChart") || $("statusChart"),
      ["รอรับเรื่อง", "กำลังดำเนินการ", "ซ่อมเสร็จแล้ว"].map((status) => [
        status,
        rows.filter((r) => r.status === status).length,
      ]),
      null,
      { donut: true, click: openWorkStatus },
    );
    chart(
      $("mainLocChart") || $("locChart"),
      groups(rows, "location").slice(0, 5),
      "location",
    );
    const byTech = groups(rows, "technician_id")
      .filter(([id]) => +id > 0)
      .slice(0, 5);
    chart($("mainTechChart"), byTech, "technician", {
      labels: byTech.map(([id]) => technicianName(id)),
      horizontal: false,
    });
    const ratings = data.technicians
      .map((t) => {
        const rated = rows.filter(
            (r) =>
              +r.technician_id === +t.id && +r.rating >= 1 && +r.rating <= 5,
          ),
          comments = rows.filter(
            (r) => +r.technician_id === +t.id && hasComment(r),
          );
        return {
          id: t.id,
          name: t.full_name,
          department: t.department,
          n: rated.length,
          scoreSum: rated.reduce((sum, r) => sum + Number(r.rating), 0),
          reviews: rows.filter(
            (r) => +r.technician_id === +t.id && hasReview(r),
          ).length,
          comments: comments.length,
          avg: rated.length
            ? rated.reduce((s, r) => s + +r.rating, 0) / rated.length
            : 0,
        };
      })
      .filter((t) => t.n > 0)
      .sort((a, b) => b.avg - a.avg || b.n - a.n || a.id - b.id);
    const departmentMap = new Map();
    for (const t of ratings) {
      const name =
        String(t.department || "ฝ่ายงานทั่วไป").trim() || "ฝ่ายงานทั่วไป";
      let d = departmentMap.get(name);
      if (!d) {
        d = { name, n: 0, sum: 0, reviews: 0, comments: 0, leader: t };
        departmentMap.set(name, d);
      }
      d.n += t.n;
      d.sum += t.scoreSum;
    }
    for (const d of departmentMap.values()) {
      const ids = new Set(
        data.technicians
          .filter(
            (t) =>
              (String(t.department || "ฝ่ายงานทั่วไป").trim() ||
                "ฝ่ายงานทั่วไป") === d.name,
          )
          .map((t) => Number(t.id)),
      );
      const reviewed = rows.filter(
        (r) => ids.has(Number(r.technician_id)) && hasReview(r),
      );
      d.reviews = reviewed.length;
      d.comments = reviewed.filter(hasComment).length;
      d.avg = d.n ? d.sum / d.n : 0;
    }
    // Preserve the department presentation, but rank and draw the technician
    // named on each bar using that same technician's score and review counts.
    const departments = [...departmentMap.values()]
      .map((d) => ({
        ...d,
        avg: d.leader.avg,
        n: d.leader.n,
        reviews: d.leader.reviews,
        comments: d.leader.comments,
      }))
      .sort(
        (a, b) =>
          b.avg - a.avg ||
          b.n - a.n ||
          Number(a.leader.id) - Number(b.leader.id),
      );
    const canvas = $("mainRatingChart");
    if (canvas && window.Chart) {
      Chart.getChart(canvas)?.destroy();
      const ratingConfig = {
        type: "bar",
        data: {
          labels: departments.map((d) => d.name),
          datasets: [
            {
              label: "คะแนนเฉลี่ย",
              data: departments.map((d) => d.avg),
              backgroundColor: palette[1],
              borderRadius: 6,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          indexAxis: "y",
          scales: { x: { min: 0, max: 5 }, y: { ticks: { autoSkip: false } } },
          plugins: {
            legend: { display: false },
            tooltip: {
              callbacks: {
                label: (c) => {
                  const t = departments[c.dataIndex];
                  return (
                    t.avg.toFixed(2) +
                    " / 5 · " +
                    t.n +
                    " คะแนน · " +
                    t.reviews +
                    " รีวิว · " +
                    t.comments +
                    " ความคิดเห็น"
                  );
                },
              },
            },
          },
          onClick: (e, items) => {
            if (items.length) {
              const d = departments[items[0].index];
              if (d) openReviews(d.leader.id, d.name);
            }
          },
          onHover: (e, x) => {
            canvas.style.cursor = x.length ? "pointer" : "default";
          },
        },
      };
      const restored = window.MbsOverviewAppearance?.build(
        data.role,
        "rating",
        canvas,
        [],
        {
          departments,
          onClick: ratingConfig.options.onClick,
          onHover: ratingConfig.options.onHover,
          tooltip: ratingConfig.options.plugins.tooltip.callbacks,
        },
      );
      new Chart(canvas, restored || ratingConfig);
    }
    const list = $("topReportersList") || $("topReportersContainer");
    if (list) {
      list.replaceChildren();
      const limit =
        data.role === "technician"
          ? typeof techReporterLimit !== "undefined"
            ? techReporterLimit
            : "all"
          : typeof currentTopReportersLimit !== "undefined"
            ? currentTopReportersLimit
            : "all";
      let ranked = groups(rows, "line_user_id");
      if (limit !== "all" && Number(limit) > 0)
        ranked = ranked.slice(0, Number(limit));
      ranked.forEach(([id, n], index) => {
        const rankColor =
          index === 0
            ? "text-[#eab308] bg-[#fefce8] border-[#fde047]"
            : index === 1
              ? "text-[#94a3b8] bg-[#f8fafc] border-[#e2e8f0]"
              : index === 2
                ? "text-[#d97706] bg-[#fffbeb] border-[#fde68a]"
                : "text-indigo-500 bg-indigo-50 border-indigo-100";
        const rankIcon =
          index === 0
            ? '<i class="fas fa-trophy text-lg drop-shadow-sm"></i>'
            : index < 3
              ? '<i class="fas fa-medal text-lg drop-shadow-sm"></i>'
              : '<span class="text-sm font-black">#' + (index + 1) + "</span>";
        const btn = document.createElement("button");
        btn.type = "button";
        btn.className =
          "mbs-ranked-reporter w-full text-left p-4 md:p-5 hover:bg-slate-50 transition-colors flex items-center justify-between border-b border-slate-50 last:border-0 cursor-pointer group";
        btn.title = "คลิกเพื่อดูรายละเอียดการแจ้งซ่อมของ " + reporterName(id);
        btn.innerHTML = `<div class="flex items-center gap-4"><div class="w-10 h-10 rounded-full flex items-center justify-center border shadow-sm ${rankColor} shrink-0 group-hover:scale-110 transition-transform">${rankIcon}</div><div><div class="text-sm font-bold text-slate-800 group-hover:text-indigo-600 transition-colors">${esc(reporterName(id))}</div><div class="text-[11px] text-slate-400 font-medium mt-0.5">บุคลากรผู้แจ้งซ่อม</div></div></div><div class="text-right flex items-center"><div class="flex flex-col items-end"><span class="text-xl font-extrabold text-indigo-600">${n}</span><span class="text-[11px] text-slate-500 font-medium ml-1">รายการ</span></div><div class="ml-4 w-8 h-8 rounded-full bg-slate-50 border border-slate-200 flex items-center justify-center group-hover:bg-indigo-50 group-hover:border-indigo-200 transition-all shadow-sm"><i class="fas fa-chevron-right text-slate-400 group-hover:text-indigo-600 transition-all group-hover:translate-x-0.5 text-xs"></i></div></div>`;
        btn.addEventListener("click", () => openAnalytics("reporter", id));
        list.appendChild(btn);
      });
      if (!ranked.length)
        list.innerHTML =
          '<div class="p-10 flex flex-col items-center justify-center text-center h-full min-h-[250px]"><i class="fas fa-user-tag text-4xl text-slate-200 mb-3"></i><p class="text-slate-400 font-medium text-sm mt-2">ไม่พบข้อมูลผู้แจ้งในช่วงเวลานี้</p></div>';
    }
    renderCards();
    renderRecent();
    decorateDuration();
    enhanceTechnicians();
  }
  function renderRecent() {
    const body =
      $("dashTableBody") || document.querySelector("#dash table tbody");
    if (!body) return;
    body.closest('table')?.classList.add('mbs-recent-status-table');
    const rows = overviewRows().slice(0, 5);
    body.innerHTML =
      rows
        .map(
          (r) =>
            '<tr><td class="px-6 py-4">' +
            Number(r.id) +
            '</td><td class="px-6 py-4">' +
            esc(dateText(r.created_at)) +
            '</td><td class="px-6 py-4">' +
            esc(r.ticket_no) +
            '</td><td class="px-6 py-4">' +
            esc(r.reporter_label) +
            '</td><td class="px-6 py-4">' +
            esc(r.equipment_type) +
            (typeof window.dashboardMediaIcons === "function"
              ? window.dashboardMediaIcons(r)
              : "") +
            '<small style="display:block">' +
            esc(r.location) +
            '</small></td><td class="px-6 py-4 mbs-recent-status-cell"><span class="mbs-recent-status mbs-recent-status-' +
            ({'รอรับเรื่อง':'pending','กำลังดำเนินการ':'progress','ซ่อมเสร็จแล้ว':'complete'}[r.status] || 'other') + '">' + esc(r.status) + '</span>' +
            "</td></tr>",
        )
        .join("") ||
      '<tr><td colspan="6" class="px-6 py-4">ไม่พบรายการในช่วงเวลานี้</td></tr>';
  }
  function hasComment(r) {
    return (
      !!String(r.review_comment || "").trim() &&
      String(r.review_comment).trim() !== "-"
    );
  }
  function hasReview(r) {
    return (+r.rating >= 1 && +r.rating <= 5) || hasComment(r);
  }
  function dialog(id, title) {
    let el = $(id);
    if (el) return el;
    el = document.createElement("dialog");
    el.id = id;
    el.className = "mbs-dialog";
    el.innerHTML =
      '<header><h2></h2><button type="button" class="mbs-close" aria-label="ปิดหน้าต่าง">×</button></header><div class="mbs-dialog-body"></div>';
    el.querySelector("h2").textContent = title;
    el.querySelector(".mbs-close").onclick = () => el.close();
    el.addEventListener("click", (e) => {
      if (e.target === el) {
        const r = el.getBoundingClientRect();
        if (
          e.clientX < r.left ||
          e.clientX > r.right ||
          e.clientY < r.top ||
          e.clientY > r.bottom
        )
          el.close();
      }
    });
    document.body.appendChild(el);
    return el;
  }
  function serverParams(kind, value, match = {}) {
    const u = new URL(location.href);
    u.searchParams.set("mbs_drill", kind);
    u.searchParams.set("mbs_value", value);
    u.searchParams.delete("mbs_features_data");
    u.searchParams.delete("mbs_page");
    for (const [p, field] of Object.entries(overviewFields || {}))
      u.searchParams.set("mbs_overview_" + p, field.value);
    for (const [k, v] of Object.entries(window.mbsRanges)) {
      if (v.start.value && v.end.value) {
        u.searchParams.set("mbs_" + k + "_start", v.start.value);
        u.searchParams.set("mbs_" + k + "_end", v.end.value);
      } else {
        u.searchParams.delete("mbs_" + k + "_start");
        u.searchParams.delete("mbs_" + k + "_end");
      }
    }
    for (const k of [...u.searchParams.keys()])
      if (k.startsWith("mbs_match_")) u.searchParams.delete(k);
    for (const [k, v] of Object.entries(match))
      u.searchParams.set("mbs_match_" + k, v);
    return u;
  }
  async function loadTable(container, url) {
    const version = String(++fetchVersion);
    container.dataset.request = version;
    container.setAttribute("aria-busy", "true");
    try {
      const res = await fetch(url, {
        credentials: "same-origin",
        cache: "no-store",
      });
      if (res.redirected) {
        location.assign(res.url);
        return;
      }
      if (!res.ok) throw Error();
      const text = await res.text();
      if (container.dataset.request !== version) return;
      container.innerHTML = text;
      for (const row of container.querySelectorAll("[data-mbs-review-id]")) {
        const open = () => {
          const id = Number(row.dataset.mbsReviewId);
          if (!Number.isSafeInteger(id) || id < 1) return;
          if (typeof window.openReviewTab === "function")
            window.openReviewTab(id);
          else
            window.open(
              (data.role === "executive"
                ? "view_repair.php"
                : "update_repair.php") +
                "?id=" +
                id +
                "&source=tech_reviews",
              "_blank",
              "noopener",
            );
        };
        row.onclick = (e) => {
          if (e.target.closest("a,button,input,select,textarea,[onclick]"))
            return;
          open();
        };
        row.onkeydown = (e) => {
          if (e.target !== row || !["Enter", " "].includes(e.key)) return;
          e.preventDefault();
          open();
        };
      }
      for (const b of container.querySelectorAll("[data-mbs-reporter]"))
        b.onclick = () => openReporterHistory(b.dataset.mbsReporter, b.dataset.mbsRepairId);
      for (const a of container.querySelectorAll(".mbs-pages a"))
        a.onclick = (e) => {
          e.preventDefault();
          loadTable(container, new URL(a.href));
        };
    } catch {
      if (container.dataset.request === version)
        container.innerHTML =
          '<p role="alert">โหลดรายการไม่สำเร็จ กรุณาปิดแล้วเปิดอีกครั้ง</p>';
    } finally {
      if (container.dataset.request === version)
        container.removeAttribute("aria-busy");
    }
  }
  function openReporterHistory(lineId, repairId = null) {
    const jobId = Number(repairId);
    const exactId = Number.isSafeInteger(jobId) && jobId > 0 ? jobId : null;
    if (typeof lineId !== "string" || !lineId.trim() || lineId.length > 200) return;
    const job = data.repairs.find(r => Number(r.id) === exactId && r.line_user_id === lineId);
    const rawDate = String(job?.created_at || "").slice(0, 10);
    const reportedDate = validDate(rawDate) ? rawDate : null;
    if (exactId && reportedDate) {
      const key = data.role === "technician" ? "techReporter" : "history";
      const range = window.mbsRanges?.[key];
      const u = new URL(location.href);
      for (const part of ["start", "end"]) {
        if (range?.[part]) range[part].value = "";
        u.searchParams.delete("mbs_" + key + "_" + part);
      }
      window.history.replaceState(window.history.state, "", u);
    }
    const technician = data.role === "technician";
    const history = $(technician ? "techReporterModal" : "historyModal");
    const open = technician ? window.openTechReporterHistory : window.viewHistory;
    if (!history || typeof open !== "function") return;
    history.dataset.mbsFocusRepairId = exactId ? String(exactId) : '';
    delete history.dataset.mbsFocusScrolled;
    openHistoryFromAnalytics(history, technician, () => {
      const name = reporterName(lineId);
      return technician ? open(name, lineId, exactId, reportedDate) : data.role === "executive"
        ? open(name, "reporter", lineId, exactId, reportedDate)
        : open(name, "reporter", false, false, null, lineId, exactId, reportedDate);
    });
  }
  function openHistoryFromAnalytics(history, technician, invoke) {
    const analytics = $("mbsAnalytics");
    const returnToAnalytics = !!analytics?.open;
    const body = analytics?.querySelector(".mbs-dialog-body");
    const scrollTop = body?.scrollTop || 0;
    const previousOverflow = document.body.style.overflow;
    let wasOpen = false;
    const isOpen = () => technician ? history.open :
      !history.classList.contains("opacity-0") && !history.classList.contains("pointer-events-none");
    const restore = () => {
      observer.disconnect();
      history.removeEventListener("close", restore);
      if (returnToAnalytics && analytics.isConnected && !analytics.open) {
        analytics.showModal();
        document.body.style.overflow = previousOverflow;
        if (body) body.scrollTop = scrollTop;
      }
    };
    const observer = new MutationObserver(() => {
      if (isOpen()) wasOpen = true;
      else if (wasOpen) restore();
    });
    observer.observe(history, { attributes: true, attributeFilter: ["class", "open"] });
    if (technician) history.addEventListener("close", restore, { once: true });
    if (returnToAnalytics) analytics.close();
    try {
      const result = invoke();
      wasOpen = isOpen();
      Promise.resolve(result).catch(restore);
    } catch {
      restore();
    }
  }
  function openTechnicianTableHistory(id) {
    const techId = Number(id);
    if (!["admin","executive"].includes(data.role) || !Number.isSafeInteger(techId) || techId <= 0 ||
        !data.technicians.some(t => Number(t.id) === techId) || typeof window.viewHistory !== "function") return;
    const modal = $("historyModal");
    if (!modal) return;
    const now = new Intl.DateTimeFormat("en-CA", {timeZone:"Asia/Bangkok",year:"numeric",month:"2-digit"}).formatToParts(new Date());
    const today = Object.fromEntries(now.map(part => [part.type,part.value]));
    const month = overviewFields?.month?.value || urlState.searchParams.get("mbs_overview_month");
    const year = overviewFields?.year?.value || urlState.searchParams.get("mbs_overview_year");
    const values = {Day:"all", Month:/^(0?[1-9]|1[0-2])$/.test(month || "") ? String(month).padStart(2,"0") : today.month,
      Year:/^[1-9]\d{3}$/.test(year || "") ? year : today.year};
    const range = window.mbsRanges.history;
    const u = new URL(location.href);
    for (const part of ["start","end"]) {
      if (range?.[part]) range[part].value = "";
      u.searchParams.delete("mbs_history_" + part);
    }
    window.history.replaceState(window.history.state,"",u);
    for (const [part,value] of Object.entries(values)) if ($("history"+part)) $("history"+part).value = value;
    openHistoryFromAnalytics(modal, false, () => data.role === "executive"
      ? window.viewHistory(technicianName(techId), "technician", null, null, null, techId)
      : window.viewHistory(technicianName(techId), "technician", false, false, techId));
  }
  function openAnalytics(kind, value, options = {}) {
    const cardHistory = options.cardHistory === true && data.role === "admin" && kind === "technician";
    let searchQuery = "", searchTimer;
    const analyticsParams = (match = {}) => {
      const u = serverParams(kind, value, match);
      u.searchParams.delete("mbs_q");
      if (searchQuery) u.searchParams.set("mbs_q", searchQuery);
      u.searchParams.delete("mbs_card_history");
      if (cardHistory) u.searchParams.set("mbs_card_history", "1");
      return u;
    };
    const allJobsLabel = cardHistory ? "แสดงงานทั้งหมดของเจ้าหน้าที่" : "แสดงงานทั้งหมดในช่วงที่เลือก";
    const title =
      {
        reporter: "ข้อมูลการแจ้งซ่อมของ ",
        technician: cardHistory ? "ประวัติงานเจ้าหน้าที่: " : "ภาระงานของ ",
        location: "สถานที่: ",
        equipment: "อุปกรณ์: ",
      }[kind] +
      (kind === "reporter"
        ? reporterName(value)
        : kind === "technician"
          ? technicianName(value)
          : value);
    const dlg = dialog("mbsAnalytics", title);
    const detailed = ["equipment", "location", "technician", "reporter"].includes(kind);
    dlg.classList.toggle("mbs-analytics-detail", detailed);
    dlg.querySelector("h2").textContent = title;
    chartRefs.forEach((c) => c.destroy());
    chartRefs = [];
    selected = { kind, value, match: {} };
    const key = {
      reporter: "line_user_id",
      technician: "technician_id",
      location: "location",
      equipment: "equipment_type",
    }[kind];
    const rows = (cardHistory ? ownRows() : overviewRows()).filter(
      (r) => String(r[key] ?? "").trim() === String(value).trim(),
    );
    const body = dlg.querySelector(".mbs-dialog-body");
    dlg.classList.remove("mbs-analytics-expanded");
    let expand = dlg.querySelector(".mbs-analytics-expand");
    if (!expand) {
      const header = dlg.querySelector(":scope > header");
      const close = header.querySelector(".mbs-close");
      const controls = document.createElement("div"); controls.className = "mbs-analytics-window-actions";
      expand = document.createElement("button"); expand.type="button"; expand.className="mbs-analytics-expand";
      header.appendChild(controls); controls.append(expand,close);
      close.innerHTML='<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 7 10 10M17 7 7 17"/></svg>';
      let resizeAnimation;
      expand.onclick=()=>{
        resizeAnimation?.cancel();
        const before=dlg.getBoundingClientRect();
        const headerBottom=dlg.querySelector(":scope > header").getBoundingClientRect().bottom;
        const anchor=[...dlg.querySelectorAll('.mbs-analytics-work')].find(card=>{const rect=card.getBoundingClientRect();return rect.bottom>headerBottom&&rect.top<before.bottom;});
        const anchorOffset=anchor?anchor.getBoundingClientRect().top-headerBottom:0;
        const active=dlg.classList.toggle("mbs-analytics-expanded");
        expand.setAttribute("aria-pressed",String(active));
        expand.setAttribute("aria-label",active?"ย่อหน้าต่าง":"ขยายหน้าต่าง");expand.title=active?"ย่อหน้าต่าง":"ขยายหน้าต่าง";
        expand.innerHTML='<svg viewBox="0 0 24 24" aria-hidden="true"><path d="'+(active?'M9 3v6H3m12-6v6h6M9 21v-6H3m12 6v-6h6':'M9 3H3v6m12-6h6v6M3 15v6h6m12-6v6h-6')+'"/></svg>';
        if(anchor){
          const nextHeader=dlg.querySelector(":scope > header").getBoundingClientRect().bottom;
          dlg.scrollTop+=anchor.getBoundingClientRect().top-nextHeader-anchorOffset;
        }
        const after=dlg.getBoundingClientRect();
        if(typeof dlg.animate==='function'&&!window.matchMedia('(prefers-reduced-motion: reduce)').matches){
          resizeAnimation=dlg.animate([
            {transformOrigin:'0 0',transform:'translate('+(before.left-after.left)+'px,'+(before.top-after.top)+'px) scale('+(before.width/after.width)+','+(before.height/after.height)+')'},
            {transformOrigin:'0 0',transform:'translate(0,0) scale(1,1)'}
          ],{duration:280,easing:'cubic-bezier(.22,1,.36,1)'});
        }
      };
    }
    expand.setAttribute("aria-pressed","false");expand.setAttribute("aria-label","ขยายหน้าต่าง");expand.title="ขยายหน้าต่าง";
    expand.innerHTML='<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 3H3v6m12-6h6v6M3 15v6h6m12-6v6h-6"/></svg>';
    body.innerHTML =
      '<p class="mbs-intro">แจ้งซ่อม ' +
      rows.length +
      ' รายการในช่วงที่เลือก · กดแท่งกราฟเพื่อดูรายการที่เกี่ยวข้อง</p><div class="mbs-drill-charts"></div><div class="mbs-drill-actions"><button type="button" class="mbs-button" id="mbsAllHistory">ดูประวัติทั้งหมดในช่วงนี้</button><span id="mbsMatchLabel"></span></div><div id="mbsDrillTable"></div>';
    if (detailed) {
      body.innerHTML = '<div class="mbs-analytics-summary"></div><p class="mbs-analytics-guide">เลือกแถบในกราฟเพื่อกรองรายการงาน · กดแถบเดิมซ้ำเพื่อยกเลิก</p><div class="mbs-drill-charts"></div><section class="mbs-analytics-results"><div class="mbs-drill-actions"><div><h3>รายการงานแจ้งซ่อม</h3><p id="mbsMatchLabel" aria-live="polite">แสดงงานทั้งหมดในช่วงที่เลือก</p></div><button type="button" class="mbs-button" id="mbsAllHistory">แสดงงานทั้งหมด</button></div><div id="mbsDrillTable"></div></section>';
      $("mbsMatchLabel").textContent = allJobsLabel;
      if (kind === "technician" && ["admin","executive"].includes(data.role)) {
        const group = document.createElement("div");
        group.className = "mbs-history-actions";
        const button = document.createElement("button");
        button.type = "button"; button.className = "mbs-button";
        button.id = "mbsTechnicianTableHistory";
        button.textContent = "ดูประวัติเจ้าหน้าที่";
        button.onclick = () => openTechnicianTableHistory(value);
        const all = $("mbsAllHistory");
        all.before(group); group.append(button, all);
      }
      for (const [label, count, tone] of [
        ["งานทั้งหมด", rows.length, "total"],
        ["รอรับเรื่อง", rows.filter(r => String(r.status).trim() === "รอรับเรื่อง").length, "pending"],
        ["กำลังดำเนินการ", rows.filter(r => String(r.status).trim() === "กำลังดำเนินการ").length, "progress"],
        ["ซ่อมเสร็จแล้ว", rows.filter(r => String(r.status).trim() === "ซ่อมเสร็จแล้ว").length, "complete"],
      ]) {
        const item = document.createElement("button"); item.type = "button"; item.className = "mbs-analytics-stat mbs-analytics-" + tone;
        const status = tone === "total" ? "" : label;
        item.dataset.mbsSummaryStatus = status;
        item.setAttribute("aria-pressed", String(tone === "total"));
        item.title = "ดูรายการ" + label;
        const icons = {total:'fa-layer-group',pending:'fa-clock',progress:'fa-spinner',complete:'fa-check-circle'};
        item.innerHTML = '<span class="mbs-summary-label">' + label + '</span><span class="mbs-summary-icon" aria-hidden="true"><i class="fas ' + icons[tone] + '"></i></span><strong>' + count + '<small>งาน</small></strong><small class="mbs-summary-open"><span>ดูรายการ</span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14m-5-5 5 5 5-5"/></svg></small>';
        item.onclick = async () => {
          const clear = !status || selected.match.status === status;
          selected.match = clear ? {} : {status};
          for (const bar of body.querySelectorAll('.mbs-analytics-bar')) bar.setAttribute('aria-pressed', 'false');
          syncSummaryCards();
          $("mbsMatchLabel").textContent = clear ? allJobsLabel : "สถานะ: " + status;
          await loadTable($("mbsDrillTable"), analyticsParams(selected.match));
          if (dlg.open) body.querySelector('.mbs-analytics-results')?.scrollIntoView({behavior:'smooth',block:'start'});
        };
        body.querySelector(".mbs-analytics-summary").appendChild(item);
      }
    }
    const actions = body.querySelector(".mbs-drill-actions");
    const search = document.createElement("div"); search.className="mbs-analytics-search";
    search.innerHTML='<svg class="mbs-search-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4 4"/></svg><input id="mbsAnalyticsSearch" type="text" maxlength="250" aria-label="ค้นหาข้อมูลในรายการงาน" placeholder="ค้นหาข้อมูลในรายการงาน..."><button id="mbsAnalyticsSearchClear" type="button" hidden>ล้างค่า</button>';
    const queryInput = search.querySelector("input"), clearSearch = search.querySelector("button");
    const heading = actions.querySelector(":scope > div:not(.mbs-history-actions)");
    if (heading) heading.classList.add("mbs-analytics-result-heading");
    actions.insertBefore(search, actions.querySelector(".mbs-history-actions") || $("mbsAllHistory"));
    const applySearch = () => {
      clearTimeout(searchTimer);
      searchQuery = queryInput.value.trim(); clearSearch.hidden = queryInput.value.length === 0;
      // Invalidate an older request immediately, including during debounce.
      $("mbsDrillTable").dataset.request=String(++fetchVersion);
      searchTimer=setTimeout(()=>{if(dlg.open)loadTable($("mbsDrillTable"),analyticsParams(selected.match));},250);
    };
    queryInput.oninput=applySearch;
    clearSearch.onclick=()=>{
      clearTimeout(searchTimer);queryInput.value="";searchQuery="";clearSearch.hidden=true;
      loadTable($("mbsDrillTable"),analyticsParams(selected.match));queryInput.focus();
    };
    function syncSummaryCards() {
      for (const card of body.querySelectorAll('[data-mbs-summary-status]'))
        card.setAttribute('aria-pressed', String(card.dataset.mbsSummaryStatus === (selected.match.status || '')));
    }
    const fields =
      kind === "reporter"
        ? ["equipment_type", "location", "technician_id", "problem_desc"]
        : kind === "technician"
          ? ["equipment_type", "location", "line_user_id", "problem_desc"]
          : kind === "equipment"
            ? ["equipment_type", "problem_desc", "line_user_id"]
            : ["location", "problem_desc", "line_user_id"];
    for (const f of fields) {
      const card = document.createElement("section");
      card.dataset.mbsAnalyticsField = f;
      card.innerHTML =
        "<h3>" +
        esc(
          {
            equipment_type: "อุปกรณ์ที่แจ้งซ่อม",
            location: "สถานที่แจ้งซ่อม",
            technician_id: "เจ้าหน้าที่ผู้รับผิดชอบ",
            line_user_id: "ผู้แจ้งซ่อม",
            status: "สถานะงาน",
            problem_desc: "ปัญหาที่แจ้ง",
          }[f],
        ) +
        '</h3><div class="mbs-drill-chart"><canvas></canvas></div>';
      body.querySelector(".mbs-drill-charts").appendChild(card);
      const pairs = groups(rows, f).slice(0, 10);
      if (detailed) {
        card.querySelector(".mbs-drill-chart").remove();
        const list = document.createElement("div"); list.className = "mbs-analytics-bars";
        const max = Math.max(1, rows.length);
        list.id = "mbsAnalyticsBars-" + f;
        if (!pairs.length) { const empty=document.createElement("p");empty.className="mbs-analytics-empty";empty.textContent="ยังไม่มีข้อมูลในช่วงที่เลือก";list.appendChild(empty); }
        for (const [key,count] of pairs) {
          const b = document.createElement("button"); b.type="button"; b.className="mbs-analytics-bar";
          b.dataset.mbsAnalyticsFilter=f; b.dataset.mbsAnalyticsValue=key; b.setAttribute("aria-pressed","false");
          b.innerHTML='<span class="mbs-analytics-bar-label"></span><strong>' + count + ' <small>งาน</small></strong><span class="mbs-analytics-track" aria-hidden="true"><span style="width:' + count/max*100 + '%"></span></span>';
          b.querySelector(".mbs-analytics-bar-label").textContent=(f === "technician_id" ? technicianName(key) : f === "line_user_id" ? reporterName(key) : key) || "ไม่ระบุ";
          b.onclick=()=>{
            const clear = selected.match[f] === key;
            selected.match=clear ? {} : { [f]:key };
            syncSummaryCards();
            for(const other of body.querySelectorAll(".mbs-analytics-bar"))other.setAttribute("aria-pressed",String(!clear && other===b));
            $("mbsMatchLabel").textContent=clear ? allJobsLabel : "กรองตาม " + card.querySelector("h3").textContent + ": " + ((f === "technician_id" ? technicianName(key) : f === "line_user_id" ? reporterName(key) : key) || "ไม่ระบุ");
            loadTable($("mbsDrillTable"),analyticsParams(selected.match));
          };
          list.appendChild(b);
        }
        const hint=document.createElement("p");hint.className="mbs-analytics-chart-note";hint.textContent="สัดส่วนเทียบกับงานทั้งหมด " + rows.length + " งาน · เรียงจากมากไปน้อย";
        card.append(list);
        if (pairs.length > 5) {
          const more=document.createElement("button");more.type="button";more.className="mbs-analytics-more";
          more.textContent="แสดงอีก " + (pairs.length-5) + " รายการ";more.setAttribute("aria-expanded","false");more.setAttribute("aria-controls",list.id);
          more.onclick=()=>{const expanded=list.classList.toggle("is-expanded");more.setAttribute("aria-expanded",String(expanded));more.textContent=expanded?"แสดงน้อยลง":"แสดงอีก " + (pairs.length-5) + " รายการ";};
          card.appendChild(more);
        }
        card.appendChild(hint);
        continue;
      }
      chart(card.querySelector("canvas"), pairs, null, {
        labels: pairs.map(([k]) =>
          f === "technician_id" ? technicianName(k) : k,
        ),
        click: (k) => {
          selected.match = { [f]: k };
          $("mbsMatchLabel").textContent =
            f === "technician_id" ? technicianName(k) : k;
          loadTable(
            $("mbsDrillTable"),
            analyticsParams(selected.match),
          );
        },
      });
    }
    $("mbsAllHistory").onclick = () => {
      clearTimeout(searchTimer);queryInput.value="";searchQuery="";clearSearch.hidden=true;
      selected.match = {};
      syncSummaryCards();
      $("mbsMatchLabel").textContent = detailed ? allJobsLabel : "";
      if(detailed)for(const b of body.querySelectorAll(".mbs-analytics-bar"))b.setAttribute("aria-pressed","false");
      loadTable($("mbsDrillTable"), analyticsParams());
    };
    if (!dlg.open) dlg.showModal();
    loadTable($("mbsDrillTable"), analyticsParams());
  }
  function reviewPeriodActive() {
    const r = window.mbsRanges.reviews;
    return (
      !!(r?.start.value && r?.end.value) ||
      ["day", "month", "year"].some(
        (p) => +urlState.searchParams.get("mbs_reviews_" + p) > 0,
      )
    );
  }
  function reviewScopeRows() {
    const base = (reviewPeriodActive() ? data.repairs : overviewRows()).filter(
      (r) => hasReview(r) || r.status === "ซ่อมเสร็จแล้ว",
    );
    return period(
      base.map((r) => ({
        ...r,
        mbs_review_at:
          hasReview(r) && r.rated_at && !String(r.rated_at).startsWith("0000")
            ? r.rated_at
            : r.completed_at,
      })),
      "reviews",
      "mbs_review_at",
    );
  }
  function reviewRows() {
    let rows = reviewScopeRows().filter(
      (r) =>
        (!reviewState.id ||
          Number(r.technician_id) === Number(reviewState.id)) &&
        (reviewState.noReview
          ? !hasReview(r) && r.status === "ซ่อมเสร็จแล้ว"
          : hasReview(r)),
    );
    if (reviewState.comments) rows = rows.filter(hasComment);
    return rows;
  }
  function reviewCandidates() {
    return data.technicians.filter(
      (t) =>
        !reviewState.department ||
        (String(t.department || "ฝ่ายงานทั่วไป").trim() || "ฝ่ายงานทั่วไป") ===
          reviewState.department,
    );
  }
  function updateReviewHeader() {
    const scope = reviewScopeRows(),
      select = $("mbsReviewTech"),
      tech = data.technicians.find(
        (t) => Number(t.id) === Number(reviewState.id),
      );
    select.replaceChildren();
    if (!reviewState.department) {
      const opt = document.createElement("option");
      opt.value = "";
      opt.textContent = "เจ้าหน้าที่ทั้งหมด";
      select.appendChild(opt);
    }
    for (const t of reviewCandidates()) {
      const reviewed = scope.filter(
        (r) => Number(r.technician_id) === Number(t.id) && hasReview(r),
      );
      const scored = reviewed.filter(
        (r) => Number(r.rating) >= 1 && Number(r.rating) <= 5,
      );
      const avg = scored.length
        ? scored.reduce((n, r) => n + Number(r.rating), 0) / scored.length
        : null;
      const opt = document.createElement("option");
      opt.value = String(t.id);
      opt.textContent =
        t.full_name +
        (avg !== null ? "   ★ " + avg.toFixed(1) : "") +
        " (" +
        reviewed.length +
        " รีวิว)";
      select.appendChild(opt);
    }
    select.value = reviewState.id;
    $("mbsReviewTitle").textContent = tech
      ? "รีวิวของเจ้าหน้าที่: " + tech.full_name
      : "รีวิวของเจ้าหน้าที่ทั้งหมด";
    $("mbsReviewDepartment").textContent = tech
      ? tech.department || "ฝ่ายงานทั่วไป"
      : reviewState.department || "ทุกฝ่ายงาน";
    $("mbsReviewPosition").textContent = tech?.position
      ? " (" + tech.position + ")"
      : "";
  }
  function reviewJobRows() {
    const candidates = new Set(reviewCandidates().map((t) => Number(t.id)));
    return period(reviewPeriodActive() ? data.repairs : overviewRows(), "reviews", "created_at")
      .filter((r) => reviewState.id ? Number(r.technician_id) === Number(reviewState.id) : candidates.has(Number(r.technician_id)));
  }
  function openReviewJobs() {
    const rows = reviewJobRows(), technicianId = reviewState.id, department = reviewState.department || "";
    const dlg = dialog("mbsReviewJobsDialog", "งานที่รับผิดชอบ: " + (technicianId ? technicianName(technicianId) : "เจ้าหน้าที่ทั้งหมด"));
    dlg.querySelector("h2").textContent = "งานที่รับผิดชอบ: " + (technicianId ? technicianName(technicianId) : "เจ้าหน้าที่ทั้งหมด");
    const body = dlg.querySelector(".mbs-dialog-body");
    body.innerHTML = '<section class="mbs-jobs-overview"><div><span class="mbs-jobs-eyebrow">งานที่ได้รับมอบหมายทั้งหมด</span><strong>' + rows.length + ' <small>งาน</small></strong><p>ข้อมูลตามเจ้าหน้าที่และช่วงวันที่ที่เลือก</p></div></section><div class="mbs-jobs-filter"><label for="mbsJobsReporterSelect">ผู้แจ้งซ่อม</label><select id="mbsJobsReporterSelect"></select><p>เลือกผู้แจ้งเพื่อดูงานของคนนั้น แล้วกด “ดูรายละเอียดงาน”</p></div><div id="mbsReviewJobsTable"></div>';
    const params = (reporter = "", repair = "") => {
      const u = serverParams("review_jobs", technicianId);
      u.searchParams.set("mbs_jobs_department", department);
      u.searchParams.set("mbs_jobs_reporter", reporter);
      u.searchParams.set("mbs_jobs_repair_id", repair);
      return u;
    };
    const groups = new Map();
    for (const r of rows) {
      const id = String(r.line_user_id || "").trim(), key = id ? "line:" + id : "repair:" + r.id;
      if (!groups.has(key)) groups.set(key, {id, repair:id ? "" : String(r.id), name:r.reporter_label || r.reporter_name || "ไม่ระบุผู้แจ้ง", count:0});
      groups.get(key).count++;
    }
    const select = $("mbsJobsReporterSelect"), all = document.createElement("option");
    all.value = ""; all.textContent = "ผู้แจ้งทั้งหมด · " + rows.length + " งาน"; select.appendChild(all);
    for (const [key,g] of [...groups.entries()].sort((a,b) => b[1].count-a[1].count)) {
      const option = document.createElement("option"); option.value = key;
      option.textContent = g.name + " · " + g.count + " งาน"; select.appendChild(option);
    }
    select.onchange = () => {
      const g = groups.get(select.value);
      loadTable($("mbsReviewJobsTable"), params(g?.id || "", g?.repair || ""));
    };
    if (!dlg.open) dlg.showModal();
    loadTable($("mbsReviewJobsTable"), params());
  }
  function refreshReviews() {
    updateReviewHeader();
    const rows = reviewRows(),
      unreviewed = reviewScopeRows().filter(
        (r) =>
          (!reviewState.id ||
            Number(r.technician_id) === Number(reviewState.id)) &&
          r.status === "ซ่อมเสร็จแล้ว" &&
          !hasReview(r),
      ).length,
      starRows = reviewState.noReview
        ? reviewScopeRows().filter(
            (r) =>
              hasReview(r) &&
              (!reviewState.id ||
                Number(r.technician_id) === Number(reviewState.id)),
          )
        : rows,
      stars = [1, 2, 3, 4, 5].map(
        (n) => starRows.filter((r) => Number(r.rating) === n).length,
      );
    const display = reviewState.star
      ? rows.filter((r) => Number(r.rating) === reviewState.star)
      : rows;
    $("mbsReviewSummary").textContent = reviewState.noReview
      ? display.length + " รายการ · ยังไม่มีรีวิว"
      : display.length +
        " รีวิว · " +
        display.filter(hasComment).length +
        " คอมเมนต์";
    $("mbsReviewCount").textContent =
      display.length + (reviewState.noReview ? " รายการ" : " รีวิว");
    $("mbsReviewJobs").textContent = reviewJobRows().length + " งาน";
    $("mbsNoReview").textContent = "ยังไม่มีรีวิว (" + unreviewed + ")";
    $("mbsNoReview").classList.toggle(
      "is-selected",
      Boolean(reviewState.noReview),
    );
    $("mbsNoReview").setAttribute(
      "aria-pressed",
      String(Boolean(reviewState.noReview)),
    );
    const chips = $("mbsStarChips");
    chips.replaceChildren();
    for (const n of [1, 2, 3, 4, 5]) {
      const b = document.createElement("button");
      b.type = "button";
      b.className =
        "mbs-classic-star" +
        (n === reviewState.star ? " is-selected" : "") +
        (n <= reviewState.star ? " is-filled" : "");
      b.setAttribute("aria-label", n + " ดาว " + stars[n - 1] + " รีวิว");
      b.setAttribute("aria-pressed", String(n === reviewState.star));
      b.innerHTML =
        '<i class="fas fa-star" aria-hidden="true"></i><span>' +
        n +
        " (" +
        stars[n - 1] +
        ")</span>";
      b.onclick = () => {
        reviewState.noReview = false;
        reviewState.star = n;
        refreshReviews();
      };
      chips.appendChild(b);
    }
    $("mbsReviewAll").classList.toggle(
      "is-selected",
      reviewState.star === 0 && !reviewState.comments && !reviewState.noReview,
    );
    $("mbsCommentsOnly").classList.toggle("is-selected", reviewState.comments);
    $("mbsCommentsOnly").setAttribute(
      "aria-pressed",
      String(reviewState.comments),
    );
    const u = serverParams("reviews", reviewState.id);
    u.searchParams.set("mbs_star", reviewState.star);
    u.searchParams.set("mbs_comments", reviewState.comments ? "1" : "0");
    u.searchParams.set("mbs_no_review", reviewState.noReview ? "1" : "0");
    return loadTable($("mbsReviewList"), u);
  }
  let reviewOpenVersion = 0;
  async function openReviews(id = "", department = "") {
    if (data.role === "technician") return;
    const opening = ++reviewOpenVersion;
    // An older caller may pass the department as its first argument.
    if (
      !/^\d+$/.test(String(id)) &&
      typeof id === "string" &&
      data.technicians.some(
        (t) =>
          (String(t.department || "ฝ่ายงานทั่วไป").trim() ||
            "ฝ่ายงานทั่วไป") === id,
      )
    ) {
      department = id;
      id = "";
    }
    reviewState = {
      id: /^\d+$/.test(String(id)) ? String(id) : "",
      department:
        typeof department === "string" &&
        data.technicians.some(
          (t) =>
            (String(t.department || "ฝ่ายงานทั่วไป").trim() ||
              "ฝ่ายงานทั่วไป") === department,
        )
          ? department
          : "",
      star: 0,
      comments: false,
      noReview: false,
    };
    if (reviewState.department && !reviewState.id)
      reviewState.id = String(reviewCandidates()[0]?.id || "");
    const dlg = dialog("mbsReviews", "รีวิวของเจ้าหน้าที่");
    if (dlg.open) dlg.close();
    dlg.setAttribute("aria-labelledby", "mbsReviewTitle");
    dlg.tabIndex = -1;
    dlg.classList.add("mbs-review-classic");
    const header = dlg.querySelector(":scope > header"),
      close = header.querySelector(".mbs-close");
    const lead = document.createElement("div");
    lead.className = "mbs-classic-review-heading";
    lead.innerHTML =
      '<div class="mbs-classic-star-icon"><i class="fas fa-star" aria-hidden="true"></i></div><div class="mbs-classic-heading-text"><h2 id="mbsReviewTitle"></h2><p class="mbs-classic-dept-line"><strong id="mbsReviewDepartment"></strong><span id="mbsReviewPosition"></span></p><label class="mbs-review-sr" for="mbsReviewTech">เลือกเจ้าหน้าที่</label><select id="mbsReviewTech"></select></div>';
    const badge = document.createElement("span");
    badge.id = "mbsReviewCount";
    badge.className = "mbs-classic-count";
    const counts = document.createElement("div");
    counts.className = "mbs-classic-review-counts";
    const jobsBadge = document.createElement("span");
    jobsBadge.id = "mbsReviewJobs";
    jobsBadge.className = "mbs-classic-count mbs-classic-job-count";
    jobsBadge.title = "จำนวนงานที่ได้รับมอบหมาย ตามวันที่แจ้งซ่อมในช่วงที่เลือก";
    counts.append(badge, jobsBadge);
    header.replaceChildren(lead, counts, close);
    const body = dlg.querySelector(".mbs-dialog-body");
    body.innerHTML =
      '<div class="mbs-classic-review-toolbar"><div class="mbs-classic-filter-row"><span class="mbs-classic-filter-label">ระดับคะแนน:</span><div id="mbsStarChips"></div><div class="mbs-classic-review-actions"><button type="button" id="mbsNoReview">ยังไม่มีรีวิว (0)</button><button type="button" id="mbsCommentsOnly">เฉพาะคอมเมนต์</button><button type="button" id="mbsReviewAll">ทั้งหมด</button></div></div><div class="mbs-classic-date-row"><div id="mbsReviewDate"></div><p id="mbsReviewSummary" aria-live="polite"></p></div></div><div id="mbsReviewList"></div>';
    $("mbsReviewTech").onchange = (e) => {
      reviewState.id = e.target.value;
      reviewState.noReview = false;
      reviewState.star = 0;
      refreshReviews();
    };
    $("mbsNoReview").onclick = () => {
      reviewState.noReview = true;
      reviewState.star = 0;
      reviewState.comments = false;
      refreshReviews();
    };
    $("mbsCommentsOnly").onclick = () => {
      reviewState.noReview = false;
      reviewState.comments = !reviewState.comments;
      refreshReviews();
    };
    $("mbsReviewAll").onclick = () => {
      reviewState.noReview = false;
      reviewState.star = 0;
      reviewState.comments = false;
      refreshReviews();
    };
    reviewCalendar?.destroy();
    reviewCalendar = mountLocalCalendar(
      $("mbsReviewDate"),
      "reviews",
      refreshReviews,
    );
    if (reviewCalendar)
      reviewCalendar.dialog.querySelector(".rc-header p").textContent =
        "กรองตามวันที่ให้คะแนน/รีวิวจริง";
    await refreshReviews();
    if (opening !== reviewOpenVersion) return;
    if (!dlg.open) dlg.showModal();
    dlg.focus({ preventScroll: true });
  }
  function mountLocalCalendar(holder, key, apply) {
    if (!window.RepairCalendar) return;
    const controls = [],
      fields = {};
    for (const part of ["day", "month", "year"]) {
      const control = document.createElement("span");
      const input = document.createElement("input");
      input.type = "hidden";
      input.value =
        urlState.searchParams.get("mbs_" + key + "_" + part) || "all";
      control.appendChild(input);
      holder.appendChild(control);
      fields[part] = input;
      controls.push(control);
    }
    return RepairCalendar.mount({
      key,
      controls,
      fields,
      apply() {
        for (const [p, f] of Object.entries(fields)) {
          urlState.searchParams.set("mbs_" + key + "_" + p, f.value);
          const u = new URL(location.href);
          u.searchParams.set("mbs_" + key + "_" + p, f.value);
          history.replaceState(null, "", u);
        }
        apply();
      },
    });
  }
  function mountOverview() {
    const canvas = $("mainEquipChart") || $("eqChart");
    if (!canvas) return;
    const section =
      $("dash") || $("tab-content-dash") || canvas.closest("section");
    if (!section) return;
    const bar = document.createElement("div");
    bar.className = "mbs-overview-filter";
    bar.innerHTML = '<div id="mbsOverviewCalendar"></div>';
    let grid = canvas;
    while (grid.parentElement && grid.parentElement !== section)
      grid = grid.parentElement;
    const techTabs =
      data.role === "technician" ? document.querySelector(".tech-tabs") : null;
    if (techTabs) {
      const row = document.createElement("div");
      row.className = "mbs-tech-tabs-row";
      techTabs.before(row);
      row.append(techTabs, bar);
      const syncVisibility = () => {
        bar.hidden = section.classList.contains("hidden");
      };
      syncVisibility();
      new MutationObserver(syncVisibility).observe(section, {
        attributes: true,
        attributeFilter: ["class"],
      });
    } else {
      section.insertBefore(bar, grid);
    }
    const instance = mountLocalCalendar(
      $("mbsOverviewCalendar"),
      "overview",
      renderOverview,
    );
    if (instance) {
      overviewFields = instance.fields;
    }
    renderOverview();
  }
  function duration(r) {
    if (!r.received_at)
      return r.status === "รอรับเรื่อง"
        ? "ยังไม่รับงาน"
        : "ไม่ทราบเวลารับงานเดิม";
    const parse = (s) => Date.parse(s.replace(" ", "T") + "+07:00");
    const a = parse(r.received_at),
      b = r.completed_at ? parse(r.completed_at) : Date.now();
    if (!Number.isFinite(a) || !Number.isFinite(b) || b < a)
      return "ไม่ทราบระยะเวลา";
    const m = Math.floor((b - a) / 60000);
    return (
      (r.completed_at ? "" : "กำลังดำเนินการ · ") +
      (m >= 1440 ? Math.floor(m / 1440) + " วัน " : "") +
      (m >= 60 ? Math.floor((m % 1440) / 60) + " ชม. " : "") +
      (m % 60) +
      " นาที"
    );
  }
  function decorateDuration() {
    for (const table of document.querySelectorAll(
      "#historyModal table,#historyTableFull,#techReporterModal table",
    )) {
      if (table.closest(".mbs-dialog")) continue;
      const header = table.querySelector("thead tr");
      if (!header) continue;
      let th = header.querySelector(".mbs-duration");
      if (!th) {
        th = document.createElement("th");
        th.className = "mbs-duration";
        header.appendChild(th);
      }
      th.textContent = "Work Duration";
      const columns = [...header.children].filter(cell => !cell.classList.contains("mbs-duration"));
      const actionIndex = columns.findIndex(cell => /^(actions?|จัดการ)$/i.test(cell.textContent.trim()));
      const actionHeader = actionIndex >= 0 ? columns[actionIndex] : null;
      if (actionHeader && actionHeader !== th) {
        if (header.lastElementChild !== actionHeader) header.appendChild(actionHeader);
        if (actionHeader.previousElementSibling !== th) header.insertBefore(th, actionHeader);
      }
      const modal = table.closest('#historyModal,#techReporterModal');
      const focusId = modal?.dataset.mbsFocusRepairId;
      for (const tr of table.querySelectorAll("tbody tr")) {
        const id = tr.dataset.mbsRepairId;
        const r = id
          ? data.repairs.find((r) => +r.id === +id)
          : data.repairs.find(
              (r) =>
                r.ticket_no &&
                [...tr.querySelectorAll("td,div,span")].some(
                  (n) =>
                    !n.children.length && n.textContent.trim() === r.ticket_no,
                ),
            );
        if (!r) continue;
        let td = tr.querySelector(".mbs-duration");
        if (!td) {
          td = document.createElement("td");
          td.className = "mbs-duration";
          tr.appendChild(td);
        }
        const cells = [...tr.children].filter(cell => !cell.classList.contains('mbs-duration'));
        const actionCell = actionIndex >= 0 ? cells[actionIndex] : null;
        if (actionCell) {
          if (tr.lastElementChild !== actionCell) tr.appendChild(actionCell);
          if (actionCell.previousElementSibling !== td) tr.insertBefore(td, actionCell);
        }
        td.textContent = duration(r);
        const focused = !!focusId && String(r.id) === focusId;
        tr.classList.toggle('mbs-history-focused', focused);
        if (focused && modal.dataset.mbsFocusScrolled !== focusId) {
          modal.dataset.mbsFocusScrolled = focusId;
          requestAnimationFrame(() => tr.scrollIntoView({block:'center', inline:'nearest', behavior:'auto'}));
        }
      }
    }
  }
  function wrapAdminModal() {
    if (
      data.role !== "admin" ||
      typeof window.openTechAdminModal !== "function"
    )
      return;
    const validate = window.validateTechAdminForm;
    if (typeof validate === "function")
      window.validateTechAdminForm = function (e) {
        if ($("techAdminForm")?.classList.contains("mbs-readonly-profile"))
          return ["0", "1"].includes($("techAdmin_is_active")?.value);
        return validate.call(this, e);
      };
    const original = window.openTechAdminModal;
    window.openTechAdminModal = function (...args) {
      const before = $("techAdminForm");
      for (const label of before?.querySelectorAll("label") || [])
        if (label.dataset.mbsOriginal)
          label.innerHTML = label.dataset.mbsOriginal;
      for (const el of before?.querySelectorAll(
        "[data-mbs-original-required]",
      ) || [])
        el.required = el.dataset.mbsOriginalRequired === "1";
      const result = original.apply(this, args);
      const [role, id] = args,
        form = $("techAdminForm");
      if (!form) return result;
      form.dataset.mbsManagedRole = strRole(role);
      form.dataset.mbsManagedId = String(id || "");
      const own =
        strRole(role) === "admin" &&
        String(id) ===
          String(document.querySelector("#tb-settings")?.dataset.user);
      const locked = !!id && !own;
      form.classList.toggle("mbs-readonly-profile", locked);
      for (const el of form.querySelectorAll(
        "input:not([type=hidden]),select,textarea",
      )) {
        if (el.id === "techAdmin_is_active") continue;
        if (el.id === "techAdmin_level") {
          el.disabled = true;
          continue;
        }
        el.dataset.mbsOriginalRequired = el.required ? "1" : "0";
        el.disabled = locked;
        if (locked) el.required = false;
      }
      for (const label of form.querySelectorAll("label")) {
        label.dataset.mbsOriginal = label.innerHTML;
        if (locked && !/account status|สถานะบัญชี/i.test(label.textContent)) {
          label.textContent =
            label.textContent
              .replace(/\s*\([^)]*\)/g, "")
              .replace(/\*/g, "")
              .trim() + " (ไม่อนุญาตให้แก้ไข) ";
          const star = document.createElement("span");
          star.className = "mbs-required";
          star.textContent = "*";
          label.appendChild(star);
        }
      }
      for (const note of form.querySelectorAll(".mbs-account-time"))
        note.remove();
      const tech =
          strRole(role) === "technician"
            ? data.technicians.find((t) => +t.id === +id)
            : null,
        account = tech || data.accounts.find((a) => +a.id === +id);
      for (const [field, caption, value] of [
        ["techAdmin_username", "สมัครสมาชิก", account?.registered_at],
        ["techAdmin_line_name", "ผูกบัญชี LINE", tech?.line_linked_at],
      ]) {
        const el = $(field),
          label =
            form.querySelector('label[for="' + field + '"]') ||
            el?.parentElement?.querySelector("label");
        if (label && locked) {
          const note = document.createElement("p");
          note.className = "mbs-account-time";
          note.dataset.mbsAccountTime = field;
          note.textContent = caption + ": " + accountDateText(value);
          label.after(note);
        }
      }
      if (locked && tech && $("techAdmin_line_name"))
        $("techAdmin_line_name").value =
          resolvedLineName(tech) ||
          (tech.line_user_id ? "ยังไม่มีชื่อ LINE ในข้อมูล" : "ยังไม่ผูกบัญชี");
      for (const el of form.querySelectorAll("[onclick]"))
        if (/avatar|upload|delete/i.test(el.getAttribute("onclick") || ""))
          el.hidden = locked;
      refreshManagedLineTime();
      refreshContactLineNames();
      return result;
    };
  }
  let lineTimeRefreshing = false;
  async function refreshManagedLineTime() {
    const form = $("techAdminForm"),
      modal = $("techAdminModal");
    if (
      data.role !== "admin" ||
      document.hidden ||
      lineTimeRefreshing ||
      !form ||
      !modal ||
      modal.classList.contains("opacity-0") ||
      modal.classList.contains("hidden") ||
      modal.hidden ||
      form.dataset.mbsManagedRole !== "technician"
    )
      return;
    const id = form.dataset.mbsManagedId;
    if (!/^[1-9][0-9]*$/.test(id || "")) return;
    lineTimeRefreshing = true;
    try {
      const u = new URL(location.href);
      u.search = "";
      u.searchParams.set("mbs_line_link_data", "1");
      u.searchParams.set("mbs_technician_id", id);
      const response = await nativeFetch(u, {
        credentials: "same-origin",
        cache: "no-store",
      });
      if (!response.ok || response.redirected) return;
      const next = await response.json(),
        tech = next.technician;
      if (
        !tech ||
        String(tech.id) !== id ||
        form.dataset.mbsManagedId !== id ||
        form.dataset.mbsManagedRole !== "technician" ||
        modal.classList.contains("opacity-0") ||
        modal.hidden
      )
        return;
      const note = form.querySelector(
        '[data-mbs-account-time="techAdmin_line_name"]',
      );
      const text = "ผูกบัญชี LINE: " + accountDateText(tech.line_linked_at);
      if (note && note.textContent !== text) note.textContent = text;
      const cached = data.technicians.find((t) => String(t.id) === id);
      if (cached) cached.line_linked_at = tech.line_linked_at;
    } catch {
      // Keep the last confirmed timestamp while the network is unavailable.
    } finally {
      lineTimeRefreshing = false;
    }
  }
  function strRole(v) {
    return String(v || "").toLowerCase();
  }
  function moveEmail() {
    const styleAction = (a, logout = false) => {
      a.classList.add("mbs-profile-action");
      a.classList.toggle("mbs-profile-action-logout", logout);
      a.innerHTML =
        '<span class="mbs-profile-action-icon"><i class="fas ' +
        (logout ? "fa-sign-out-alt" : "fa-envelope") +
        '" aria-hidden="true"></i></span><span class="mbs-profile-action-copy"><strong>' +
        (logout ? "ออกจากระบบ" : "ยืนยันอีเมล") +
        "</strong><small>" +
        (logout ? "สิ้นสุดการใช้งานบัญชีนี้" : "ยืนยันอีเมลของบัญชีคุณ") +
        '</small></span><i class="fas fa-chevron-right mbs-profile-action-arrow" aria-hidden="true"></i>';
    };
    if (data.role === "technician") {
      for (const a of document.querySelectorAll(
        '.tech-mobile-popover a[href="verify_email_page.php"],.tech-mobile-popover a[href="logout.php"]',
      ))
        styleAction(a, a.getAttribute("href") === "logout.php");
      return;
    }
    if (!["admin", "executive"].includes(data.role)) return;
    const menu = $("profileDropdownMenu");
    if (!menu) return;
    for (const a of document.querySelectorAll("#sidebar a,#sidebar button"))
      if (a.textContent.includes("ยืนยันอีเมล")) {
        a.className = "mbs-profile-email";
        styleAction(a);
        menu.appendChild(a);
      }
  }
  const contactLineNames = new Map(), contactLineRetry = new Map();
  let contactLineRefreshing = false;
  function resolvedLineName(t) {
    return String(t.linked_line_name || contactLineNames.get(String(t.id) + ":" + t.line_user_id) || "").trim();
  }
  function applyContactLineName(t) {
    const name = resolvedLineName(t);
    if (!name) return;
    for (const row of document.querySelectorAll('[data-tb-tech-id="' + Number(t.id) + '"]')) {
      const label = row.querySelector(".fa-line")?.nextElementSibling;
      if (label && label.textContent !== name) label.textContent = name;
    }
    for (const label of document.querySelectorAll('#techCardsContainer [data-tech-card-id="' + Number(t.id) + '"] [data-tech-card-line-name]'))
      if (label.textContent !== name) label.textContent = name;
    const form = $("techAdminForm"), input = $("techAdmin_line_name");
    if (input && form?.dataset.mbsManagedRole === "technician" && +form.dataset.mbsManagedId === +t.id)
      input.value = name;
  }
  async function refreshContactLineNames() {
    if (data.role !== "admin" || document.hidden || contactLineRefreshing) return;
    contactLineRefreshing = true;
    try {
      for (const t of data.technicians) {
        if (!t.line_user_id || t.approval_status !== "อนุมัติแล้ว") continue;
        if (resolvedLineName(t)) { applyContactLineName(t); continue; }
        const key = String(t.id) + ":" + t.line_user_id;
        if ((contactLineRetry.get(key) || 0) > Date.now()) continue;
        contactLineRetry.set(key, Date.now() + 60000);
        const u = new URL(location.href); u.search = "";
        u.searchParams.set("mbs_line_profile_data", "1");
        u.searchParams.set("mbs_technician_id", String(t.id));
        try {
          const r = await nativeFetch(u, {credentials:"same-origin", cache:"no-store"});
          if (!r.ok || r.redirected) continue;
          const next = (await r.json()).technician;
          const current = data.technicians.find(v => +v.id === +t.id);
          if (!next || +next.id !== +t.id || next.line_user_id !== t.line_user_id ||
              !current || current.line_user_id !== t.line_user_id || current.approval_status !== "อนุมัติแล้ว") continue;
          if (typeof next.linked_line_name === "string" && next.linked_line_name.trim()) {
            contactLineNames.set(key, next.linked_line_name.trim());
            applyContactLineName(current);
          }
        } catch {}
      }
    } finally { contactLineRefreshing = false; }
  }
  function enhanceTechnicians() {
    if (data.role !== "admin") return;
    refreshContactLineNames();
    for (const card of document.querySelectorAll("#techCardsContainer [data-tech-card-id]")) {
      const id = Number(card.dataset.techCardId), tech = data.technicians.find(t => Number(t.id) === id);
      if (!tech) continue;
      const badge = card.querySelector(".mbs-tech-card-count");
      const text = data.repairs.filter(r => Number(r.technician_id) === id).length + " งาน";
      if (badge && badge.textContent !== text) badge.textContent = text;
      const history = card.querySelector("[data-tech-card-history]");
      if (history) history.onclick = () => openAnalytics("technician", String(id), {cardHistory:true});
      applyContactLineName(tech);
      const edit = card.querySelector("[data-tech-card-edit]");
      if (edit) edit.onclick = () => {
        const current = data.technicians.find(t => Number(t.id) === id);
        if (!current || typeof window.openTechAdminModal !== "function") return;
        window.openTechAdminModal("Technician", String(id), current.web_username || "", current.full_name || "",
          current.english_name || "", current.position || "", current.phone || "", current.department || "",
          current.avatar_url || card.querySelector("img")?.src || "", current.email || "", current.web_active ?? 1,
          resolvedLineName(current), Boolean(current.line_user_id && current.approval_status === "อนุมัติแล้ว"));
      };
    }
  }
  // Carry range values through the existing same-page AJAX and PHP pagination.
  const nativeFetch = window.fetch.bind(window);
  window.fetch = function (input, options) {
    try {
      if (typeof input === "string" || input instanceof URL) {
        const u = new URL(input, location.href);
        if (u.origin === location.origin && u.pathname === location.pathname) {
          for (const [p, f] of Object.entries(overviewFields || {}))
            u.searchParams.set("mbs_overview_" + p, f.value);
          for (const [k, v] of Object.entries(window.mbsRanges)) {
            const prefix = "mbs_" + k + "_";
            if (v.start.value && v.end.value) {
              u.searchParams.set(prefix + "start", v.start.value);
              u.searchParams.set(prefix + "end", v.end.value);
            } else {
              u.searchParams.delete(prefix + "start");
              u.searchParams.delete(prefix + "end");
            }
          }
          input = u.href;
        }
      }
    } catch {}
    return nativeFetch(input, options);
  };
  function init() {
    if (data.error) {
      const alert = document.createElement("p");
      alert.role = "alert";
      alert.textContent = data.error;
      document.querySelector("main")?.prepend(alert);
      return;
    }
    for (const fn of [
      "renderAllCharts",
      "renderEquipChart",
      "renderStatusChart",
      "renderLocChart",
      "renderTechChart",
      "renderRatingChart",
      "renderTopReporters",
      "renderTechReporterRanking",
      "renderDashTable",
    ])
      if (typeof window[fn] === "function") window[fn] = renderOverview;
    for (const name of [
      "applyTechnicianSummary",
      "applyExecutiveSummary",
      "applyAdminSummary",
    ])
      if (typeof window[name] === "function") {
        const old = window[name];
        window[name] = (...args) => {
          const r = old(...args);
          renderCards();
          return r;
        };
      }
    window.openTechReviewsModal = openReviews;
    window.openTechReviews = openReviews;
    mountOverview();
    document.addEventListener(
      "click",
      (event) => {
        const a = event.target.closest("a[href]");
        if (!a) return;
        const u = new URL(a.href, location.href);
        if (u.origin !== location.origin) return;
        const paths = [
          "dashboard.php",
          "executive_dashboard.php",
          "technician_home.php",
          "executive_report.php",
          "print_report.php",
          "export_excel.php",
        ];
        if (!paths.includes(u.pathname.split("/").pop())) return;
        for (const [k, v] of Object.entries(window.mbsRanges)) {
          if (v.start.value && v.end.value) {
            u.searchParams.set("mbs_" + k + "_start", v.start.value);
            u.searchParams.set("mbs_" + k + "_end", v.end.value);
          }
        }
        a.href = u.href;
      },
      true,
    );
    moveEmail();
    wrapAdminModal();
    if (data.role === "admin") {
      setInterval(refreshManagedLineTime, 3000);
      setInterval(refreshContactLineNames, 10000);
      document.addEventListener("visibilitychange", refreshManagedLineTime);
      window.addEventListener("focus", refreshManagedLineTime);
    }
    enhanceTechnicians();
    decorateDuration();
    let pending = false;
    new MutationObserver((records) => {
      if (
        pending ||
        !records.some((r) =>
          [...r.addedNodes].some(
            (n) => n.nodeType === 1 && (n.matches?.("tr,table,tbody,.tech-card-item,#techCardsContainer") || n.querySelector?.(".tech-card-item,#techCardsContainer")),
          ),
        )
      )
        return;
      pending = true;
      requestAnimationFrame(() => {
        pending = false;
        decorateDuration();
        enhanceTechnicians();
        refreshContactLineNames();
      });
    }).observe(document.body, { subtree: true, childList: true });
    let appearanceResizeTimer;
    window.addEventListener("resize", () => {
      clearTimeout(appearanceResizeTimer);
      appearanceResizeTimer = setTimeout(renderOverview, 120);
    });
    setInterval(decorateDuration, 60000);
    let refreshing = false;
    setInterval(async () => {
      if (
        document.hidden ||
        refreshing ||
        document.querySelector("dialog[open]")
      )
        return;
      refreshing = true;
      try {
        const u = new URL(location.href);
        u.searchParams.set("mbs_features_data", "1");
        const r = await nativeFetch(u, {
          credentials: "same-origin",
          cache: "no-store",
        });
        if (r.ok && !r.redirected) {
          const next = await r.json();
          if (!next.error) {
            data = next;
            refreshContactLineNames();
            renderOverview();
          }
        }
      } catch {
      } finally {
        refreshing = false;
      }
    }, 30000);
  }
  // Override renderers before legacy DOMContentLoaded callbacks execute.
  for (const fn of [
    "renderAllCharts",
    "renderEquipChart",
    "renderStatusChart",
    "renderLocChart",
    "renderTechChart",
    "renderRatingChart",
    "renderTopReporters",
    "renderTechReporterRanking",
    "renderDashTable",
  ])
    if (typeof window[fn] === "function") window[fn] = renderOverview;
  if (document.readyState === "loading")
    document.addEventListener("DOMContentLoaded", init, { once: true });
  else init();
})();
