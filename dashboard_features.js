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
            '</small></td><td class="px-6 py-4">' +
            esc(r.status) +
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
        b.onclick = () => openAnalytics("reporter", b.dataset.mbsReporter);
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
  function openAnalytics(kind, value) {
    const title =
      {
        reporter: "ข้อมูลการแจ้งซ่อมของ ",
        technician: "ภาระงานของ ",
        location: "สถานที่: ",
        equipment: "อุปกรณ์: ",
      }[kind] +
      (kind === "reporter"
        ? reporterName(value)
        : kind === "technician"
          ? technicianName(value)
          : value);
    const dlg = dialog("mbsAnalytics", title);
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
    const rows = overviewRows().filter(
      (r) => String(r[key] ?? "").trim() === String(value).trim(),
    );
    const body = dlg.querySelector(".mbs-dialog-body");
    body.innerHTML =
      '<p class="mbs-intro">แจ้งซ่อม ' +
      rows.length +
      ' รายการในช่วงที่เลือก · กดแท่งกราฟเพื่อดูรายการที่เกี่ยวข้อง</p><div class="mbs-drill-charts"></div><div class="mbs-drill-actions"><button type="button" class="mbs-button" id="mbsAllHistory">ดูประวัติทั้งหมดในช่วงนี้</button><span id="mbsMatchLabel"></span></div><div id="mbsDrillTable"></div>';
    const fields =
      kind === "reporter"
        ? ["equipment_type", "location", "technician_id"]
        : kind === "technician"
          ? ["equipment_type", "location", "status"]
          : ["equipment_type", "location", "problem_desc"].filter(
              (f) => f !== key,
            );
    for (const f of fields) {
      const card = document.createElement("section");
      card.innerHTML =
        "<h3>" +
        esc(
          {
            equipment_type: "อุปกรณ์ที่แจ้งซ่อม",
            location: "สถานที่แจ้งซ่อม",
            technician_id: "เจ้าหน้าที่ผู้รับผิดชอบ",
            status: "สถานะงาน",
            problem_desc: "ปัญหาที่แจ้ง",
          }[f],
        ) +
        '</h3><div class="mbs-drill-chart"><canvas></canvas></div>';
      body.querySelector(".mbs-drill-charts").appendChild(card);
      const pairs = groups(rows, f).slice(0, 10);
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
            serverParams(kind, value, selected.match),
          );
        },
      });
    }
    $("mbsAllHistory").onclick = () => {
      selected.match = {};
      $("mbsMatchLabel").textContent = "";
      loadTable($("mbsDrillTable"), serverParams(kind, value));
    };
    if (!dlg.open) dlg.showModal();
    loadTable($("mbsDrillTable"), serverParams(kind, value));
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
    loadTable($("mbsReviewList"), u);
  }
  function openReviews(id = "", department = "") {
    if (data.role === "technician") return;
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
    header.replaceChildren(lead, badge, close);
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
    if (!dlg.open) dlg.showModal();
    dlg.focus({ preventScroll: true });
    refreshReviews();
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
        th.textContent = "ระยะเวลาทำงาน";
        header.appendChild(th);
      }
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
        td.textContent = duration(r);
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
    for (const t of data.technicians) {
      const btns = [
        ...document.querySelectorAll('[onclick*="openTechAdminModal"]'),
      ].filter((b) =>
        new RegExp(
          "openTechAdminModal\\(['\"]Technician['\"],\\s*['\"]?" +
            Number(t.id) +
            "(?:['\"],|,)",
          "i",
        ).test(b.getAttribute("onclick")),
      );
      for (const btn of btns) {
        if (btn.closest("tr")) continue;
        const row = btn.closest(".tech-card-item");
        if (!row) continue;
        const existing = row.querySelector(".mbs-tech-jobs");
        const jobs = data.repairs.filter((r) => +r.technician_id === +t.id);
        const box = document.createElement("div");
        box.className = "mbs-tech-jobs";
        box.innerHTML =
          "<strong>" +
          jobs.length +
          " งาน</strong> · กำลังดำเนินการ " +
          jobs.filter((r) => r.status === "กำลังดำเนินการ").length +
          " · เสร็จแล้ว " +
          jobs.filter((r) => r.status === "ซ่อมเสร็จแล้ว").length;
        const b = document.createElement("button");
        b.type = "button";
        b.textContent = "ดูงานที่รับผิดชอบ";
        b.onclick = () => openAnalytics("technician", String(t.id));
        box.appendChild(b);
        if (existing) existing.replaceWith(box);
        else row.appendChild(box);
      }
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
            (n) => n.nodeType === 1 && n.matches?.("tr,table,tbody"),
          ),
        )
      )
        return;
      pending = true;
      requestAnimationFrame(() => {
        pending = false;
        decorateDuration();
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
