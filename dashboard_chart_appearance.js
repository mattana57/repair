/* Original Overview chart presentation, separated from analytics and filtering. */
(() => {
  "use strict";
  const factories = {};

  const commonTooltipOptions = {
    backgroundColor: "#1e293b", // สีดำ/เทาเข้ม
    titleColor: "#ffffff",
    bodyColor: "#e2e8f0",
    borderColor: "#334155",
    borderWidth: 1,
    padding: 10,
    displayColors: true,
    boxPadding: 4,
    usePointStyle: true, // ทำให้กล่องสีใน Tooltip เป็นวงกลม
    titleFont: { size: 13, weight: "bold", family: "'Kanit', sans-serif" },
    bodyFont: { size: 12, family: "'Kanit', sans-serif" },
  };

  function techCompactViewport() {
    return window.matchMedia("(max-width: 767px), (any-pointer: coarse)")
      .matches;
  }
  function techLocationAxisWidth(chart) {
    // ให้พื้นที่ตามชื่อจริง ชื่อสั้นไม่ต้องเว้นช่องว่างกว้างเท่าชื่อยาว
    const ctx = chart.ctx;
    ctx.save();
    ctx.font = "bold 12px 'Plus Jakarta Sans', 'Kanit', sans-serif";
    const longest = Math.max(
      0,
      ...chart.data.labels.map((label) => ctx.measureText(String(label)).width),
    );
    ctx.restore();
    return Math.min(
      Math.max(70, Math.min(150, chart.width * 0.3)),
      Math.max(30, Math.ceil(longest) + 16),
    );
  }
  function techLocationTick(value) {
    const label = String(this.getLabelForValue(value));
    if (!techCompactViewport()) return label;
    const width = techLocationAxisWidth(this.chart) - 16;
    const chars =
      typeof Intl.Segmenter === "function"
        ? Array.from(
            new Intl.Segmenter("th", { granularity: "grapheme" }).segment(
              label,
            ),
            (part) => part.segment,
          )
        : label.match(
            /[^\u0E31\u0E34-\u0E3A\u0E47-\u0E4E][\u0E31\u0E34-\u0E3A\u0E47-\u0E4E]*/gu,
          ) || [label];
    const lines = [];
    let line = "";
    this.ctx.save();
    this.ctx.font = "bold 12px 'Plus Jakarta Sans', 'Kanit', sans-serif";
    for (const char of chars) {
      if (line && this.ctx.measureText(line + char).width > width) {
        lines.push(line);
        line = "";
      }
      line += char;
    }
    if (line) lines.push(line);
    this.ctx.restore();
    return lines.length ? lines : label;
  }

  factories["admin-equipment"] = function (canvas, pairs, meta) {
    const ctx = canvas.getContext("2d"),
      eqCtx = ctx,
      statusCtx = ctx,
      locCtx = ctx;
    const sorted = pairs.map(([name, count], i) => ({
        name: meta.labels?.[i] ?? name,
        count,
      })),
      eqSorted = sorted,
      locSorted = sorted;
    const locLabels = sorted.length
      ? sorted.map((e) => e.name)
      : ["ไม่มีข้อมูล"];
    const techLabels = sorted.length
      ? sorted.map((e) => {
          const name = String(e.name).trim(),
            p = name.split(/\s+/);
          return window.innerWidth <= 1366 && name.length > 5 && p.length > 1
            ? [p[0], p.slice(1).join(" ")]
            : name;
        })
      : ["ไม่มีข้อมูล"];
    const pending = pairs.find((p) => p[0] === "รอรับเรื่อง")?.[1] || 0,
      progress = pairs.find((p) => p[0] === "กำลังดำเนินการ")?.[1] || 0,
      completed = pairs.find((p) => p[0] === "ซ่อมเสร็จแล้ว")?.[1] || 0;
    const totalStatus = pairs.reduce((n, p) => n + p[1], 0),
      isEmpty = totalStatus === 0;
    const gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, "rgba(37, 99, 235, 0.16)");
    gradient.addColorStop(1, "rgba(37, 99, 235, 0.0)");
    const deptArr = meta.departments
      ? meta.departments.map((d) => ({
          name: d.name,
          topTech: d.leader.name,
          topTechAvg: d.leader.avg.toFixed(1),
          avg: d.avg,
          count: d.reviews,
        }))
      : (meta.ratings || []).map((t) => ({
          name: t.department || "ไม่ระบุฝ่ายงาน",
          topTech: t.name,
          topTechAvg: t.avg.toFixed(1),
          avg: t.avg,
          count: t.reviews,
        }));
    const getRatingColor = () => "#3b82f6";

    return {
      type: "line",
      // ✨ 1. เพิ่ม Plugins สำหรับวาดตัวหนังสือแกน X ให้ตรงจุดเป๊ะๆ 100% ✨
      plugins: [
        {
          id: "custom_equip_x_labels",
          afterDraw: (chart) => {
            if (window.innerWidth > 1366) return; // ถ้าเป็นคอมพิวเตอร์ให้ใช้ระบบปกติ

            const ctx = chart.ctx;
            const xAxis = chart.scales.x;

            ctx.save();
            ctx.textAlign = "left";
            ctx.textBaseline = "top";
            ctx.fillStyle = "#64748b"; // สีเทาเหมือนระบบหลัก
            ctx.font = `bold 11px "Kanit", sans-serif`;

            const yPos = chart.chartArea.bottom + 10;

            const getVisualWidth = (text) => {
              const cleanText = text.replace(
                /[\u0E31\u0E34-\u0E3A\u0E47-\u0E4E]/g,
                "",
              );
              return ctx.measureText(cleanText).width;
            };

            chart.data.labels.forEach((label, index) => {
              const xCenter = xAxis.getPixelForTick(index);
              const textWidth = getVisualWidth(label);
              // วาดข้อความโดยให้กึ่งกลางข้อความอยู่ตรงจุด xCenter พอดี
              ctx.fillText(label, xCenter - textWidth / 2, yPos);
            });
            ctx.restore();
          },
        },
      ],
      data: {
        labels: sorted.length ? sorted.map((e) => e.name) : ["ไม่มีข้อมูล"],
        datasets: [
          {
            label: "จำนวน (ครั้ง)",
            data: sorted.length ? sorted.map((e) => e.count) : [0],
            borderColor: "#2563eb",
            backgroundColor: gradient,
            borderWidth: 3,
            pointBackgroundColor: "#ffffff",
            pointBorderColor: "#2563eb",
            pointBorderWidth: 2,
            pointRadius: 4,
            fill: true,
            tension: 0.4,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        layout: { padding: { bottom: 0 } }, // ✨ เอา Padding Bottom ออกเพื่อให้กราฟสูงเต็ม 280px ✨
        plugins: { legend: { display: false } },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              stepSize: 1,
              font: { family: "'Plus Jakarta Sans', 'Kanit', sans-serif" },
            },
            grid: { color: "#f8fafc" },
            border: { display: false },
          },
          x: {
            // ✨ 2. บังคับให้แกน X จุดตรงกลางเสมอ และซ่อนข้อความออริจินัลบนไอแพด ✨
            offset: false,
            ticks: {
              color: window.innerWidth <= 1366 ? "transparent" : "#64748b",
              font: {
                family: "'Kanit', sans-serif",
                size: window.innerWidth <= 1366 ? 11 : 12,
                weight: "bold",
              },
              autoSkip: false,
              maxRotation: 0,
              minRotation: 0,
              align: "center",
              crossAlign: "center",
            },
            grid: { display: false },
            border: { display: false },
          },
        },
      },
    };
  };

  factories["admin-status"] = function (canvas, pairs, meta) {
    const ctx = canvas.getContext("2d"),
      eqCtx = ctx,
      statusCtx = ctx,
      locCtx = ctx;
    const sorted = pairs.map(([name, count], i) => ({
        name: meta.labels?.[i] ?? name,
        count,
      })),
      eqSorted = sorted,
      locSorted = sorted;
    const locLabels = sorted.length
      ? sorted.map((e) => e.name)
      : ["ไม่มีข้อมูล"];
    const techLabels = sorted.length
      ? sorted.map((e) => {
          const name = String(e.name).trim(),
            p = name.split(/\s+/);
          return window.innerWidth <= 1366 && name.length > 5 && p.length > 1
            ? [p[0], p.slice(1).join(" ")]
            : name;
        })
      : ["ไม่มีข้อมูล"];
    const pending = pairs.find((p) => p[0] === "รอรับเรื่อง")?.[1] || 0,
      progress = pairs.find((p) => p[0] === "กำลังดำเนินการ")?.[1] || 0,
      completed = pairs.find((p) => p[0] === "ซ่อมเสร็จแล้ว")?.[1] || 0;
    const totalStatus = pairs.reduce((n, p) => n + p[1], 0),
      isEmpty = totalStatus === 0;
    const gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, "rgba(37, 99, 235, 0.16)");
    gradient.addColorStop(1, "rgba(37, 99, 235, 0.0)");
    const deptArr = meta.departments
      ? meta.departments.map((d) => ({
          name: d.name,
          topTech: d.leader.name,
          topTechAvg: d.leader.avg.toFixed(1),
          avg: d.avg,
          count: d.reviews,
        }))
      : (meta.ratings || []).map((t) => ({
          name: t.department || "ไม่ระบุฝ่ายงาน",
          topTech: t.name,
          topTechAvg: t.avg.toFixed(1),
          avg: t.avg,
          count: t.reviews,
        }));
    const getRatingColor = () => "#3b82f6";

    return {
      type: "doughnut",
      data: {
        labels: ["รอรับเรื่อง", "กำลังดำเนินการ", "ซ่อมเสร็จแล้ว"],
        datasets: [
          {
            data: isEmpty ? [1] : [pending, progress, completed],
            backgroundColor: isEmpty
              ? ["#f1f5f9"]
              : ["#f59e0b", "#38bdf8", "#10b981"],
            borderWidth: 0,
            hoverOffset: 4,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: "bottom",
            labels: {
              usePointStyle: true,
              padding: 20,
              font: {
                family: "'Plus Jakarta Sans', 'Kanit', sans-serif",
                weight: "600",
              },
            },
          },
          tooltip: {
            callbacks: {
              label: function (context) {
                return isEmpty
                  ? " ไม่มีข้อมูล"
                  : " " + context.formattedValue + " งาน";
              },
            },
          },
        },
        cutout: "75%",
      },
    };
  };

  factories["admin-location"] = function (canvas, pairs, meta) {
    const ctx = canvas.getContext("2d"),
      eqCtx = ctx,
      statusCtx = ctx,
      locCtx = ctx;
    const sorted = pairs.map(([name, count], i) => ({
        name: meta.labels?.[i] ?? name,
        count,
      })),
      eqSorted = sorted,
      locSorted = sorted;
    const locLabels = sorted.length
      ? sorted.map((e) => e.name)
      : ["ไม่มีข้อมูล"];
    const techLabels = sorted.length
      ? sorted.map((e) => {
          const name = String(e.name).trim(),
            p = name.split(/\s+/);
          return window.innerWidth <= 1366 && name.length > 5 && p.length > 1
            ? [p[0], p.slice(1).join(" ")]
            : name;
        })
      : ["ไม่มีข้อมูล"];
    const pending = pairs.find((p) => p[0] === "รอรับเรื่อง")?.[1] || 0,
      progress = pairs.find((p) => p[0] === "กำลังดำเนินการ")?.[1] || 0,
      completed = pairs.find((p) => p[0] === "ซ่อมเสร็จแล้ว")?.[1] || 0;
    const totalStatus = pairs.reduce((n, p) => n + p[1], 0),
      isEmpty = totalStatus === 0;
    const gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, "rgba(37, 99, 235, 0.16)");
    gradient.addColorStop(1, "rgba(37, 99, 235, 0.0)");
    const deptArr = meta.departments
      ? meta.departments.map((d) => ({
          name: d.name,
          topTech: d.leader.name,
          topTechAvg: d.leader.avg.toFixed(1),
          avg: d.avg,
          count: d.reviews,
        }))
      : (meta.ratings || []).map((t) => ({
          name: t.department || "ไม่ระบุฝ่ายงาน",
          topTech: t.name,
          topTechAvg: t.avg.toFixed(1),
          avg: t.avg,
          count: t.reviews,
        }));
    const getRatingColor = () => "#3b82f6";

    return {
      type: "bar",
      plugins: [
        {
          id: "custom_loc_labels",
          afterDraw: (chart) => {
            const ctx = chart.ctx;
            const yAxis = chart.scales.y;
            let isTablet = window.innerWidth <= 1366;

            ctx.save();
            ctx.textBaseline = "middle";
            ctx.fillStyle = "#475569"; // สีเทาเข้ม
            ctx.font = `bold ${isTablet ? 11 : 12}px "Kanit", sans-serif`;

            yAxis.ticks.forEach((tick, index) => {
              const y = yAxis.getPixelForTick(index);
              const labelText = chart.data.labels[index];

              if (isTablet) {
                // ✨ ฝั่งไอแพด: จัดข้อความ "ชิดซ้าย" ป้องกันการทับกราฟ 100%
                ctx.textAlign = "left";
                ctx.fillText(labelText, 5, y);
              } else {
                // ✨ จัดข้อความ "ชิดซ้าย" (Left Align) เริ่มจากขอบซ้ายสุด 5px ให้เหมือนกันทุกอุปกรณ์
                ctx.textAlign = "left";
                ctx.fillText(labelText, 5, y);
              }
            });
            ctx.restore();
          },
        },
      ],
      data: {
        labels: locLabels,
        datasets: [
          {
            label: "แจ้งซ่อม (ครั้ง)",
            data: sorted.length ? sorted.map((e) => e.count) : [0],
            backgroundColor: "#0284c7",
            borderRadius: 6,
          },
        ],
      },
      options: {
        indexAxis: "y",
        responsive: true,
        maintainAspectRatio: false,
        layout: {
          padding: { left: 0, right: window.innerWidth <= 1366 ? 15 : 0 },
        },
        plugins: { legend: { display: false } },
        scales: {
          x: {
            beginAtZero: true,
            ticks: {
              stepSize: 1,
              font: { family: "'Plus Jakarta Sans', 'Kanit', sans-serif" },
            },
            grid: { color: "#f8fafc" },
            border: { display: false },
          },
          y: {
            ticks: {
              color: "transparent", // ✨ ซ่อนตัวอักษรเก่าที่ชอบมีปัญหาทับกราฟ
              font: {
                family: "'Kanit', sans-serif",
                size: window.innerWidth <= 1366 ? 11 : 12,
              },
              autoSkip: false,
              maxRotation: 0,
              minRotation: 0,
            },
            grid: { display: false },
            border: { display: false },
            afterFit: function (scaleInstance) {
              // ✨ จองพื้นที่ให้ตัวหนังสือ "ชิดซ้าย" บนไอแพด
              if (window.innerWidth <= 1366) {
                // ✨ ลดพื้นที่จองจาก 130 เหลือ 100 เพื่อดึงกราฟสีแดงและเลข 0, 1, 2 ขยับมาทางซ้ายให้ชิดตัวหนังสือมากขึ้น
                scaleInstance.width = 100;
              }
            },
          },
        },
      },
    };
  };

  factories["admin-technician"] = function (canvas, pairs, meta) {
    const ctx = canvas.getContext("2d"),
      eqCtx = ctx,
      statusCtx = ctx,
      locCtx = ctx;
    const sorted = pairs.map(([name, count], i) => ({
        name: meta.labels?.[i] ?? name,
        count,
      })),
      eqSorted = sorted,
      locSorted = sorted;
    const locLabels = sorted.length
      ? sorted.map((e) => e.name)
      : ["ไม่มีข้อมูล"];
    const techLabels = sorted.length
      ? sorted.map((e) => {
          const name = String(e.name).trim(),
            p = name.split(/\s+/);
          return window.innerWidth <= 1366 && name.length > 5 && p.length > 1
            ? [p[0], p.slice(1).join(" ")]
            : name;
        })
      : ["ไม่มีข้อมูล"];
    const pending = pairs.find((p) => p[0] === "รอรับเรื่อง")?.[1] || 0,
      progress = pairs.find((p) => p[0] === "กำลังดำเนินการ")?.[1] || 0,
      completed = pairs.find((p) => p[0] === "ซ่อมเสร็จแล้ว")?.[1] || 0;
    const totalStatus = pairs.reduce((n, p) => n + p[1], 0),
      isEmpty = totalStatus === 0;
    const gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, "rgba(37, 99, 235, 0.16)");
    gradient.addColorStop(1, "rgba(37, 99, 235, 0.0)");
    const deptArr = meta.departments
      ? meta.departments.map((d) => ({
          name: d.name,
          topTech: d.leader.name,
          topTechAvg: d.leader.avg.toFixed(1),
          avg: d.avg,
          count: d.reviews,
        }))
      : (meta.ratings || []).map((t) => ({
          name: t.department || "ไม่ระบุฝ่ายงาน",
          topTech: t.name,
          topTechAvg: t.avg.toFixed(1),
          avg: t.avg,
          count: t.reviews,
        }));
    const getRatingColor = () => "#3b82f6";

    return {
      type: "bar",
      plugins: [
        {
          id: "custom_tech_x_labels",
          afterDraw: (chart) => {
            if (window.innerWidth > 1366) return;

            const ctx = chart.ctx;
            const xAxis = chart.scales.x;

            ctx.save();
            // ✨ 1. เปลี่ยนมาวาดจากขอบซ้าย เพื่อให้เราคุมพิกัด X ได้เองแบบ 100%
            ctx.textAlign = "left";
            ctx.textBaseline = "top";
            ctx.fillStyle = "#64748b";
            ctx.font = `bold 11px "Kanit", sans-serif`;

            const yPos = chart.chartArea.bottom + 10;

            // ✨ 2. ฟังก์ชันไม้ตาย: ตัดสระบน/ล่างและวรรณยุกต์ทิ้ง "เฉพาะตอนคำนวณความกว้าง"
            const getVisualWidth = (text) => {
              const cleanText = text.replace(
                /[\u0E31\u0E34-\u0E3A\u0E47-\u0E4E]/g,
                "",
              );
              return ctx.measureText(cleanText).width;
            };

            chart.data.labels.forEach((label, index) => {
              const xCenter = xAxis.getPixelForTick(index);

              if (Array.isArray(label)) {
                label.forEach((line, i) => {
                  // ✨ 3. เอาพิกัดกึ่งกลางกราฟ ลบด้วย ครึ่งนึงของความกว้างข้อความ = ตรงกลางเป๊ะ!
                  const textWidth = getVisualWidth(line);
                  ctx.fillText(line, xCenter - textWidth / 2, yPos + i * 14);
                });
              } else {
                const textWidth = getVisualWidth(label);
                ctx.fillText(label, xCenter - textWidth / 2, yPos);
              }
            });
            ctx.restore();
          },
        },
      ],
      data: {
        labels: techLabels,
        datasets: [
          {
            label: "รับผิดชอบ (งาน)",
            data: sorted.length ? sorted.map((e) => e.count) : [0],
            backgroundColor: "#1e40af",
            borderRadius: 6,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        layout: {
          padding: {
            left: 5,
            right: 5,
            bottom: window.innerWidth <= 1366 ? 10 : 0,
          },
        },
        plugins: { legend: { display: false } },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              stepSize: 1,
              font: { family: "'Plus Jakarta Sans', 'Kanit', sans-serif" },
            },
            grid: { color: "#f8fafc" },
            border: { display: false },
          },
          x: {
            offset: true, // ✨ คำสั่งสำคัญ: บังคับให้แกน X จับคู่แท่งกราฟกับตัวหนังสือให้อยู่ตรงกลางเป๊ะๆ เสมอ
            ticks: {
              // ✨ ซ่อนข้อความระบบในไอแพดเพื่อไม่ให้ทับกับ Plugin (คอมพิวเตอร์แสดงตัวหนาปกติ ไม่กระทบ 100%)
              color: window.innerWidth <= 1366 ? "transparent" : "#64748b",
              font: {
                family: "'Kanit', sans-serif",
                size: window.innerWidth <= 1366 ? 11 : 12,
                weight: "bold",
              },
              autoSkip: false,
              maxRotation: 0,
              minRotation: 0,
              align: "center",
              crossAlign: "center",
              // ✨ ดันตัวหนังสือให้ห่างจากแท่งกราฟ 8 พิกเซล เฉพาะฝั่งคอมพิวเตอร์ (ไอแพดเป็น 0 ไม่กระทบแน่นอน)
              padding: window.innerWidth <= 1366 ? 0 : 8,
            },
            grid: { display: false },
            border: { display: false },
          },
        },
        categoryPercentage: window.innerWidth <= 1366 ? 0.75 : 0.8,
        barPercentage: window.innerWidth <= 1366 ? 0.85 : 0.9,
      },
    };
  };

  factories["admin-rating"] = function (canvas, pairs, meta) {
    const ctx = canvas.getContext("2d"),
      eqCtx = ctx,
      statusCtx = ctx,
      locCtx = ctx;
    const sorted = pairs.map(([name, count], i) => ({
        name: meta.labels?.[i] ?? name,
        count,
      })),
      eqSorted = sorted,
      locSorted = sorted;
    const locLabels = sorted.length
      ? sorted.map((e) => e.name)
      : ["ไม่มีข้อมูล"];
    const techLabels = sorted.length
      ? sorted.map((e) => {
          const name = String(e.name).trim(),
            p = name.split(/\s+/);
          return window.innerWidth <= 1366 && name.length > 5 && p.length > 1
            ? [p[0], p.slice(1).join(" ")]
            : name;
        })
      : ["ไม่มีข้อมูล"];
    const pending = pairs.find((p) => p[0] === "รอรับเรื่อง")?.[1] || 0,
      progress = pairs.find((p) => p[0] === "กำลังดำเนินการ")?.[1] || 0,
      completed = pairs.find((p) => p[0] === "ซ่อมเสร็จแล้ว")?.[1] || 0;
    const totalStatus = pairs.reduce((n, p) => n + p[1], 0),
      isEmpty = totalStatus === 0;
    const gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, "rgba(37, 99, 235, 0.16)");
    gradient.addColorStop(1, "rgba(37, 99, 235, 0.0)");
    const deptArr = meta.departments
      ? meta.departments.map((d) => ({
          name: d.name,
          topTech: d.leader.name,
          topTechAvg: d.leader.avg.toFixed(1),
          avg: d.avg,
          count: d.reviews,
        }))
      : (meta.ratings || []).map((t) => ({
          name: t.department || "ไม่ระบุฝ่ายงาน",
          topTech: t.name,
          topTechAvg: t.avg.toFixed(1),
          avg: t.avg,
          count: t.reviews,
        }));
    const getRatingColor = () => "#3b82f6";

    return {
      type: "bar",
      plugins: [
        {
          id: "custom_star_gradient",
          afterDatasetsDraw: (chart) => {
            // ✨ ทำงานเฉพาะมือถือแนวตั้งเท่านั้น เพื่อให้กล่องฟิลเตอร์สีดำ (Tooltip) ลอยทับชื่อช่างและชื่อฝ่ายงาน ✨
            const isMobilePortrait =
              window.innerWidth < 640 && window.innerHeight > window.innerWidth;
            if (!isMobilePortrait) return;
            chart._drawCustomRatingLabels(chart);
          },
          afterDraw: (chart) => {
            // ✨ หน้าจออื่นๆ ทั้งหมด (คอม, iPad, มือถือแนวนอน) ทำงานเหมือนเดิม 100% ไม่กระทบส่วนอื่น ✨
            const isMobilePortrait =
              window.innerWidth < 640 && window.innerHeight > window.innerWidth;
            if (isMobilePortrait) return;
            chart._drawCustomRatingLabels(chart);
          },
          beforeInit: (chart) => {
            chart._drawCustomRatingLabels = (chart) => {
              const ctx = chart.ctx;
              const yAxis = chart.scales.y;

              ctx.save();
              // ✨ 1. เปลี่ยนมาใช้จัดชิดซ้าย (Left-aligned) เพื่อให้ตัวหนังสือเกาะขอบซ้ายเป๊ะเหมือนในคอมโน้ตบุ๊ค
              ctx.textAlign = "left";
              ctx.textBaseline = "middle";

              yAxis.ticks.forEach((tick, index) => {
                const y = yAxis.getPixelForTick(index);
                const labelArray = chart.data.labels[index];
                if (!labelArray) return;

                if (Array.isArray(labelArray) && labelArray.length === 3) {
                  const scoreStr = labelArray[0].trim();
                  const scoreVal = parseFloat(scoreStr) || 0;
                  const tName = labelArray[1];
                  const dName = labelArray[2];

                  let isTablet = window.innerWidth <= 1366;
                  let fSizeDept = isTablet ? 12 : 14;
                  let fSizeTech = isTablet ? 11 : 13;
                  let fSizeScore = isTablet ? 11 : 12;
                  let fSizeStar = isTablet ? 11 : 13;

                  // ✨ 2. ล็อคพิกัดให้ตัวหนังสือเริ่มวาดที่ตำแหน่งคงที่เสมอ
                  // การันตีว่าตัวหนังสือจะอยู่ริมซ้ายสวยงาม ไม่ลอยไปทับกราฟตรงกลางแน่นอน
                  const textDrawX = 10;

                  const yOffsetTop = isTablet ? -18 : -16;
                  const yOffsetBottom = isTablet ? 18 : 16;

                  // วาดชื่อฝ่ายงาน (บรรทัดล่าง) - สีน้ำเงิน
                  ctx.font = `800 ${fSizeDept}px "Sarabun", sans-serif`;
                  ctx.fillStyle = "#4f46e5";
                  ctx.fillText(dName, textDrawX, y + yOffsetBottom);

                  // วาดชื่อช่าง (บรรทัดกลาง) - สีเทาเข้ม
                  ctx.font = `bold ${fSizeTech}px "Sarabun", sans-serif`;
                  ctx.fillStyle = "#475569";
                  ctx.fillText(tName, textDrawX, y);

                  // วาดดาว (บรรทัดบน)
                  const textY = y + yOffsetTop;
                  ctx.font = `900 ${fSizeStar}px "Font Awesome 6 Free"`;
                  const starIcon = "\uf005";
                  const starWidth = ctx.measureText(starIcon).width;

                  // ดาวพื้นหลัง (สีเทา)
                  ctx.fillStyle = "#e2e8f0";
                  ctx.fillText(starIcon, textDrawX, textY);

                  // ดาวทับ (สีเหลือง ไล่ตาม %)
                  if (scoreVal > 0) {
                    const fillPercent = scoreVal / 5.0;
                    ctx.save();
                    ctx.beginPath();
                    ctx.rect(
                      textDrawX,
                      textY - 10,
                      starWidth * fillPercent,
                      20,
                    );
                    ctx.clip();
                    ctx.fillStyle = "#f59e0b";
                    ctx.fillText(starIcon, textDrawX, textY);
                    ctx.restore();
                  }

                  // วาดคะแนนตัวเลข
                  ctx.font = `bold ${fSizeScore}px "Sarabun", sans-serif`;
                  ctx.fillStyle = "#64748b";
                  ctx.fillText(scoreStr, textDrawX + starWidth + 6, textY);
                } else {
                  ctx.font = 'bold 13px "Sarabun", sans-serif';
                  ctx.fillStyle = "#475569";
                  ctx.fillText(
                    Array.isArray(labelArray)
                      ? labelArray.join(" ")
                      : labelArray,
                    10,
                    y,
                  );
                }
              });
              ctx.restore();
            };
          },
        },
      ],
      data: {
        labels: deptArr.length
          ? deptArr.map((d) => ["   " + d.topTechAvg, d.topTech, d.name])
          : [["ไม่มีข้อมูล"]],
        datasets: [
          {
            label: "คะแนนเฉลี่ยฝ่าย",
            data: deptArr.length ? deptArr.map((d) => d.avg) : [0],
            backgroundColor: deptArr.length
              ? deptArr.map((d) => getRatingColor(d.avg))
              : ["#e2e8f0"],
            borderRadius: 10,
            barThickness: window.innerWidth <= 1366 ? 20 : 24,
            maxBarThickness: 32,
            borderSkipped: false,
            z: 2,
          },
          {
            label: "เต็ม 5",
            data: deptArr.length ? deptArr.map((d) => 5.0) : [5.0],
            backgroundColor: "#f1f5f9",
            borderRadius: 10,
            barThickness: window.innerWidth <= 1366 ? 20 : 24,
            maxBarThickness: 32,
            borderSkipped: false,
            z: 1,
          },
        ],
      },
      options: {
        indexAxis: "y",
        grouped: false,
        responsive: true,
        maintainAspectRatio: false,
        layout: {
          padding: { left: 0, right: window.innerWidth <= 1366 ? 10 : 20 },
        },
        interaction: { mode: "y", intersect: false },
        onClick: (e, elements, chart) => {
          const activeElements = chart.getElementsAtEventForMode(
            e,
            "y",
            { intersect: false },
            true,
          );
          if (
            activeElements &&
            activeElements.length > 0 &&
            deptArr.length > 0
          ) {
            const index = activeElements[0].index;
            if (deptArr && deptArr[index]) {
              let selectedDept = deptArr[index].name;
              openTechReviewsModal(selectedDept, m, y);
            }
          }
        },
        onHover: (event, chartElement) => {
          event.native.target.style.cursor =
            chartElement.length > 0 ? "pointer" : "default";
        },
        plugins: {
          legend: { display: false },
          tooltip: {
            filter: function (tooltipItem) {
              return tooltipItem.datasetIndex === 0;
            },
            callbacks: {
              title: function (context) {
                let idx = context[0].dataIndex;
                return deptArr[idx] ? deptArr[idx].name : "";
              },
              label: function (context) {
                let idx = context.dataIndex;
                if (!deptArr[idx]) return "";
                return ` เจ้าหน้าที่ ${deptArr[idx].topTech} (⭐ ${deptArr[idx].topTechAvg})`;
              },
              afterLabel: function (context) {
                let idx = context.dataIndex;
                if (!deptArr[idx]) return "";
                return `จำนวน: ${deptArr[idx].count} รีวิว`;
              },
            },
            // ✨ ให้ตัวหนาเฉพาะชื่อฝ่ายงาน (titleFont) ส่วนเนื้อหา (bodyFont) ใช้ตัวบางปกติ
            titleFont: {
              family: "'Sarabun', sans-serif",
              size: 14,
              weight: "bold",
            },
            bodyFont: {
              family: "'Sarabun', sans-serif",
              size: 13,
              weight: "normal",
            },
            // ✨ ลบสีพื้นหลังและกรอบตกแต่งทิ้ง ปล่อยให้ใช้สีดำโปร่งใสอัตโนมัติเหมือนกราฟอื่นๆ
          },
        },
        scales: {
          x: {
            min: 0,
            max: 5,
            ticks: {
              stepSize: 1,
              font: { family: "'Sarabun', sans-serif", weight: "bold" },
              color: "#94a3b8",
            },
            grid: { color: "#f1f5f9", drawBorder: false },
            border: { display: false },
          },
          y: {
            ticks: {
              color: "transparent",
              font: {
                family: "'Sarabun', sans-serif",
                size: 1,
                weight: "bold",
              }, // หลอกให้กินพื้นที่น้อยสุด
              autoSkip: false,
              maxRotation: 0,
              minRotation: 0,
              padding: 0,
            },
            grid: { display: false },
            border: { display: false },
            afterFit: function (scaleInstance) {
              // ✨ 3. สร้าง "กำแพง" แบบเป๊ะๆ:
              // บนไอแพด ให้กันพื้นที่ซ้ายไว้ 160px (พอดีกับความยาวตัวหนังสือ ไม่เหลือที่ว่างเกินไป)
              // บนคอม ให้กันไว้ 190px (เหมือนรูปที่ 2 เป๊ะๆ)
              // แท่งกราฟจะถูกดันไปเริ่มที่หลังกำแพงนี้ 100% ตัวหนังสือจะไม่ซ้อนทับอีกต่อไป
              if (window.innerWidth <= 1366) {
                scaleInstance.width = 160;
              } else {
                scaleInstance.width = 190;
              }
            },
          },
        },
      },
    };
  };

  factories["executive-equipment"] = function (canvas, pairs, meta) {
    const ctx = canvas.getContext("2d"),
      eqCtx = ctx,
      statusCtx = ctx,
      locCtx = ctx;
    const sorted = pairs.map(([name, count], i) => ({
        name: meta.labels?.[i] ?? name,
        count,
      })),
      eqSorted = sorted,
      locSorted = sorted;
    const locLabels = sorted.length
      ? sorted.map((e) => e.name)
      : ["ไม่มีข้อมูล"];
    const techLabels = sorted.length
      ? sorted.map((e) => {
          const name = String(e.name).trim(),
            p = name.split(/\s+/);
          return window.innerWidth <= 1366 && name.length > 5 && p.length > 1
            ? [p[0], p.slice(1).join(" ")]
            : name;
        })
      : ["ไม่มีข้อมูล"];
    const pending = pairs.find((p) => p[0] === "รอรับเรื่อง")?.[1] || 0,
      progress = pairs.find((p) => p[0] === "กำลังดำเนินการ")?.[1] || 0,
      completed = pairs.find((p) => p[0] === "ซ่อมเสร็จแล้ว")?.[1] || 0;
    const totalStatus = pairs.reduce((n, p) => n + p[1], 0),
      isEmpty = totalStatus === 0;
    const gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, "rgba(37, 99, 235, 0.16)");
    gradient.addColorStop(1, "rgba(37, 99, 235, 0.0)");
    const deptArr = meta.departments
      ? meta.departments.map((d) => ({
          name: d.name,
          topTech: d.leader.name,
          topTechAvg: d.leader.avg.toFixed(1),
          avg: d.avg,
          count: d.reviews,
        }))
      : (meta.ratings || []).map((t) => ({
          name: t.department || "ไม่ระบุฝ่ายงาน",
          topTech: t.name,
          topTechAvg: t.avg.toFixed(1),
          avg: t.avg,
          count: t.reviews,
        }));
    const getRatingColor = () => "#3b82f6";

    return {
      type: "line",
      // ✨ 1. เพิ่ม Plugins สำหรับวาดตัวหนังสือแกน X ให้ตรงจุดเป๊ะๆ 100% ✨
      plugins: [
        {
          id: "custom_equip_x_labels",
          afterDraw: (chart) => {
            if (window.innerWidth > 1366) return; // ถ้าเป็นคอมพิวเตอร์ให้ใช้ระบบปกติ

            const ctx = chart.ctx;
            const xAxis = chart.scales.x;

            ctx.save();
            ctx.textAlign = "left";
            ctx.textBaseline = "top";
            ctx.fillStyle = "#64748b"; // สีเทาเหมือนระบบหลัก
            ctx.font = `bold 11px "Kanit", sans-serif`;

            const yPos = chart.chartArea.bottom + 10;

            const getVisualWidth = (text) => {
              const cleanText = text.replace(
                /[\u0E31\u0E34-\u0E3A\u0E47-\u0E4E]/g,
                "",
              );
              return ctx.measureText(cleanText).width;
            };

            chart.data.labels.forEach((label, index) => {
              const xCenter = xAxis.getPixelForTick(index);
              const textWidth = getVisualWidth(label);
              // วาดข้อความโดยให้กึ่งกลางข้อความอยู่ตรงจุด xCenter พอดี
              ctx.fillText(label, xCenter - textWidth / 2, yPos);
            });
            ctx.restore();
          },
        },
      ],
      data: {
        labels: sorted.length ? sorted.map((e) => e.name) : ["ไม่มีข้อมูล"],
        datasets: [
          {
            label: "จำนวน (ครั้ง)",
            data: sorted.length ? sorted.map((e) => e.count) : [0],
            borderColor: "#2563eb",
            backgroundColor: gradient,
            borderWidth: 3,
            pointBackgroundColor: "#ffffff",
            pointBorderColor: "#2563eb",
            pointBorderWidth: 2,
            pointRadius: 4,
            fill: true,
            tension: 0.4,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        layout: { padding: { bottom: 0 } }, // ✨ เอา Padding Bottom ออกเพื่อให้กราฟสูงเต็ม 280px ✨
        plugins: { legend: { display: false } },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              stepSize: 1,
              font: { family: "'Plus Jakarta Sans', 'Kanit', sans-serif" },
            },
            grid: { color: "#f8fafc" },
            border: { display: false },
          },
          x: {
            // ✨ 2. บังคับให้แกน X จุดตรงกลางเสมอ และซ่อนข้อความออริจินัลบนไอแพด ✨
            offset: false,
            ticks: {
              color: window.innerWidth <= 1366 ? "transparent" : "#64748b",
              font: {
                family: "'Kanit', sans-serif",
                size: window.innerWidth <= 1366 ? 11 : 12,
                weight: "bold",
              },
              autoSkip: false,
              maxRotation: 0,
              minRotation: 0,
              align: "center",
              crossAlign: "center",
            },
            grid: { display: false },
            border: { display: false },
          },
        },
      },
    };
  };

  factories["executive-status"] = function (canvas, pairs, meta) {
    const ctx = canvas.getContext("2d"),
      eqCtx = ctx,
      statusCtx = ctx,
      locCtx = ctx;
    const sorted = pairs.map(([name, count], i) => ({
        name: meta.labels?.[i] ?? name,
        count,
      })),
      eqSorted = sorted,
      locSorted = sorted;
    const locLabels = sorted.length
      ? sorted.map((e) => e.name)
      : ["ไม่มีข้อมูล"];
    const techLabels = sorted.length
      ? sorted.map((e) => {
          const name = String(e.name).trim(),
            p = name.split(/\s+/);
          return window.innerWidth <= 1366 && name.length > 5 && p.length > 1
            ? [p[0], p.slice(1).join(" ")]
            : name;
        })
      : ["ไม่มีข้อมูล"];
    const pending = pairs.find((p) => p[0] === "รอรับเรื่อง")?.[1] || 0,
      progress = pairs.find((p) => p[0] === "กำลังดำเนินการ")?.[1] || 0,
      completed = pairs.find((p) => p[0] === "ซ่อมเสร็จแล้ว")?.[1] || 0;
    const totalStatus = pairs.reduce((n, p) => n + p[1], 0),
      isEmpty = totalStatus === 0;
    const gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, "rgba(37, 99, 235, 0.16)");
    gradient.addColorStop(1, "rgba(37, 99, 235, 0.0)");
    const deptArr = meta.departments
      ? meta.departments.map((d) => ({
          name: d.name,
          topTech: d.leader.name,
          topTechAvg: d.leader.avg.toFixed(1),
          avg: d.avg,
          count: d.reviews,
        }))
      : (meta.ratings || []).map((t) => ({
          name: t.department || "ไม่ระบุฝ่ายงาน",
          topTech: t.name,
          topTechAvg: t.avg.toFixed(1),
          avg: t.avg,
          count: t.reviews,
        }));
    const getRatingColor = () => "#3b82f6";

    return {
      type: "doughnut",
      data: {
        // ✨ เปลี่ยนคำศัพท์สถานะให้ตรงตามข้อตกลงเป๊ะๆ
        labels: ["รอรับเรื่อง", "กำลังดำเนินการ", "ซ่อมเสร็จแล้ว"],
        datasets: [
          {
            data: isEmpty ? [1] : [pending, progress, completed],
            backgroundColor: isEmpty
              ? ["#f1f5f9"]
              : ["#f59e0b", "#38bdf8", "#10b981"],
            borderWidth: 0,
            hoverOffset: 4,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: "bottom",
            labels: {
              usePointStyle: true,
              padding: 20,
              font: {
                family: "'Plus Jakarta Sans', 'Kanit', sans-serif",
                weight: "600",
              },
            },
          },
          tooltip: {
            callbacks: {
              label: function (context) {
                return isEmpty
                  ? " ไม่มีข้อมูล"
                  : " " + context.formattedValue + " งาน";
              },
            },
          },
        },
        cutout: "75%",
      },
    };
  };

  factories["executive-location"] = function (canvas, pairs, meta) {
    const ctx = canvas.getContext("2d"),
      eqCtx = ctx,
      statusCtx = ctx,
      locCtx = ctx;
    const sorted = pairs.map(([name, count], i) => ({
        name: meta.labels?.[i] ?? name,
        count,
      })),
      eqSorted = sorted,
      locSorted = sorted;
    const locLabels = sorted.length
      ? sorted.map((e) => e.name)
      : ["ไม่มีข้อมูล"];
    const techLabels = sorted.length
      ? sorted.map((e) => {
          const name = String(e.name).trim(),
            p = name.split(/\s+/);
          return window.innerWidth <= 1366 && name.length > 5 && p.length > 1
            ? [p[0], p.slice(1).join(" ")]
            : name;
        })
      : ["ไม่มีข้อมูล"];
    const pending = pairs.find((p) => p[0] === "รอรับเรื่อง")?.[1] || 0,
      progress = pairs.find((p) => p[0] === "กำลังดำเนินการ")?.[1] || 0,
      completed = pairs.find((p) => p[0] === "ซ่อมเสร็จแล้ว")?.[1] || 0;
    const totalStatus = pairs.reduce((n, p) => n + p[1], 0),
      isEmpty = totalStatus === 0;
    const gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, "rgba(37, 99, 235, 0.16)");
    gradient.addColorStop(1, "rgba(37, 99, 235, 0.0)");
    const deptArr = meta.departments
      ? meta.departments.map((d) => ({
          name: d.name,
          topTech: d.leader.name,
          topTechAvg: d.leader.avg.toFixed(1),
          avg: d.avg,
          count: d.reviews,
        }))
      : (meta.ratings || []).map((t) => ({
          name: t.department || "ไม่ระบุฝ่ายงาน",
          topTech: t.name,
          topTechAvg: t.avg.toFixed(1),
          avg: t.avg,
          count: t.reviews,
        }));
    const getRatingColor = () => "#3b82f6";

    return {
      type: "bar",
      plugins: [
        {
          id: "custom_loc_labels",
          afterDraw: (chart) => {
            const ctx = chart.ctx;
            const yAxis = chart.scales.y;
            let isTablet = window.innerWidth <= 1366;

            ctx.save();
            ctx.textBaseline = "middle";
            ctx.fillStyle = "#475569"; // สีเทาเข้ม
            ctx.font = `bold ${isTablet ? 11 : 12}px "Kanit", sans-serif`;

            yAxis.ticks.forEach((tick, index) => {
              const y = yAxis.getPixelForTick(index);
              const labelText = chart.data.labels[index];

              // ✨ จัดข้อความ "ชิดซ้าย" (Left Align) เริ่มจากขอบซ้ายสุด 5px ให้เหมือนกันทุกอุปกรณ์
              ctx.textAlign = "left";
              ctx.fillText(labelText, 5, y);
            });
            ctx.restore();
          },
        },
      ],
      data: {
        labels: locLabels,
        datasets: [
          {
            label: "แจ้งซ่อม (ครั้ง)",
            data: sorted.length ? sorted.map((e) => e.count) : [0],
            backgroundColor: "#0284c7",
            borderRadius: 6,
          },
        ],
      },
      options: {
        indexAxis: "y",
        responsive: true,
        maintainAspectRatio: false,
        layout: {
          padding: { left: 0, right: window.innerWidth <= 1366 ? 15 : 0 },
        },
        plugins: { legend: { display: false } },
        scales: {
          x: {
            beginAtZero: true,
            ticks: {
              stepSize: 1,
              font: { family: "'Plus Jakarta Sans', 'Kanit', sans-serif" },
            },
            grid: { color: "#f8fafc" },
            border: { display: false },
          },
          y: {
            ticks: {
              color: "transparent", // ✨ ซ่อนตัวอักษรเก่าที่ชอบมีปัญหาทับกราฟ
              font: {
                family: "'Kanit', sans-serif",
                size: window.innerWidth <= 1366 ? 11 : 12,
              },
              autoSkip: false,
              maxRotation: 0,
              minRotation: 0,
            },
            grid: { display: false },
            border: { display: false },
            afterFit: function (scaleInstance) {
              if (window.innerWidth <= 1366) {
                scaleInstance.width = 100;
              }
            },
          },
        },
      },
    };
  };

  factories["executive-technician"] = function (canvas, pairs, meta) {
    const ctx = canvas.getContext("2d"),
      eqCtx = ctx,
      statusCtx = ctx,
      locCtx = ctx;
    const sorted = pairs.map(([name, count], i) => ({
        name: meta.labels?.[i] ?? name,
        count,
      })),
      eqSorted = sorted,
      locSorted = sorted;
    const locLabels = sorted.length
      ? sorted.map((e) => e.name)
      : ["ไม่มีข้อมูล"];
    const techLabels = sorted.length
      ? sorted.map((e) => {
          const name = String(e.name).trim(),
            p = name.split(/\s+/);
          return window.innerWidth <= 1366 && name.length > 5 && p.length > 1
            ? [p[0], p.slice(1).join(" ")]
            : name;
        })
      : ["ไม่มีข้อมูล"];
    const pending = pairs.find((p) => p[0] === "รอรับเรื่อง")?.[1] || 0,
      progress = pairs.find((p) => p[0] === "กำลังดำเนินการ")?.[1] || 0,
      completed = pairs.find((p) => p[0] === "ซ่อมเสร็จแล้ว")?.[1] || 0;
    const totalStatus = pairs.reduce((n, p) => n + p[1], 0),
      isEmpty = totalStatus === 0;
    const gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, "rgba(37, 99, 235, 0.16)");
    gradient.addColorStop(1, "rgba(37, 99, 235, 0.0)");
    const deptArr = meta.departments
      ? meta.departments.map((d) => ({
          name: d.name,
          topTech: d.leader.name,
          topTechAvg: d.leader.avg.toFixed(1),
          avg: d.avg,
          count: d.reviews,
        }))
      : (meta.ratings || []).map((t) => ({
          name: t.department || "ไม่ระบุฝ่ายงาน",
          topTech: t.name,
          topTechAvg: t.avg.toFixed(1),
          avg: t.avg,
          count: t.reviews,
        }));
    const getRatingColor = () => "#3b82f6";

    return {
      type: "bar",
      plugins: [
        {
          id: "custom_tech_x_labels",
          afterDraw: (chart) => {
            if (window.innerWidth > 1366) return;

            const ctx = chart.ctx;
            const xAxis = chart.scales.x;

            ctx.save();
            // ✨ 1. เปลี่ยนมาวาดจากขอบซ้าย เพื่อให้เราคุมพิกัด X ได้เองแบบ 100%
            ctx.textAlign = "left";
            ctx.textBaseline = "top";
            ctx.fillStyle = "#64748b";
            ctx.font = `bold 11px "Kanit", sans-serif`;

            const yPos = chart.chartArea.bottom + 10;

            // ✨ 2. ฟังก์ชันไม้ตาย: ตัดสระบน/ล่างและวรรณยุกต์ทิ้ง "เฉพาะตอนคำนวณความกว้าง"
            const getVisualWidth = (text) => {
              const cleanText = text.replace(
                /[\u0E31\u0E34-\u0E3A\u0E47-\u0E4E]/g,
                "",
              );
              return ctx.measureText(cleanText).width;
            };

            chart.data.labels.forEach((label, index) => {
              const xCenter = xAxis.getPixelForTick(index);

              if (Array.isArray(label)) {
                label.forEach((line, i) => {
                  // ✨ 3. เอาพิกัดกึ่งกลางกราฟ ลบด้วย ครึ่งนึงของความกว้างข้อความ = ตรงกลางเป๊ะ!
                  const textWidth = getVisualWidth(line);
                  ctx.fillText(line, xCenter - textWidth / 2, yPos + i * 14);
                });
              } else {
                const textWidth = getVisualWidth(label);
                ctx.fillText(label, xCenter - textWidth / 2, yPos);
              }
            });
            ctx.restore();
          },
        },
      ],
      data: {
        labels: techLabels,
        datasets: [
          {
            label: "รับผิดชอบ (งาน)",
            data: sorted.length ? sorted.map((e) => e.count) : [0],
            backgroundColor: "#1e40af",
            borderRadius: 6,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        layout: {
          padding: {
            left: 5,
            right: 5,
            bottom: window.innerWidth <= 1366 ? 10 : 0,
          },
        },
        plugins: { legend: { display: false } },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              stepSize: 1,
              font: { family: "'Plus Jakarta Sans', 'Kanit', sans-serif" },
            },
            grid: { color: "#f8fafc" },
            border: { display: false },
          },
          x: {
            offset: true,
            ticks: {
              // ✨ ซ่อนข้อความระบบในไอแพดเพื่อไม่ให้ทับกับ Plugin (คอมพิวเตอร์แสดงตัวหนาปกติ ไม่กระทบ 100%)
              color: window.innerWidth <= 1366 ? "transparent" : "#64748b",
              font: {
                family: "'Kanit', sans-serif",
                size: window.innerWidth <= 1366 ? 11 : 12,
                weight: "bold",
              },
              autoSkip: false,
              maxRotation: 0,
              minRotation: 0,
              align: "center",
              crossAlign: "center",
              // ✨ ดันตัวหนังสือให้ห่างจากแท่งกราฟ 8 พิกเซล เฉพาะฝั่งคอมพิวเตอร์
              padding: window.innerWidth <= 1366 ? 0 : 8,
            },
            grid: { display: false },
            border: { display: false },
          },
        },
        categoryPercentage: window.innerWidth <= 1366 ? 0.75 : 0.8,
        barPercentage: window.innerWidth <= 1366 ? 0.85 : 0.9,
      },
    };
  };

  factories["executive-rating"] = function (canvas, pairs, meta) {
    const ctx = canvas.getContext("2d"),
      eqCtx = ctx,
      statusCtx = ctx,
      locCtx = ctx;
    const sorted = pairs.map(([name, count], i) => ({
        name: meta.labels?.[i] ?? name,
        count,
      })),
      eqSorted = sorted,
      locSorted = sorted;
    const locLabels = sorted.length
      ? sorted.map((e) => e.name)
      : ["ไม่มีข้อมูล"];
    const techLabels = sorted.length
      ? sorted.map((e) => {
          const name = String(e.name).trim(),
            p = name.split(/\s+/);
          return window.innerWidth <= 1366 && name.length > 5 && p.length > 1
            ? [p[0], p.slice(1).join(" ")]
            : name;
        })
      : ["ไม่มีข้อมูล"];
    const pending = pairs.find((p) => p[0] === "รอรับเรื่อง")?.[1] || 0,
      progress = pairs.find((p) => p[0] === "กำลังดำเนินการ")?.[1] || 0,
      completed = pairs.find((p) => p[0] === "ซ่อมเสร็จแล้ว")?.[1] || 0;
    const totalStatus = pairs.reduce((n, p) => n + p[1], 0),
      isEmpty = totalStatus === 0;
    const gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, "rgba(37, 99, 235, 0.16)");
    gradient.addColorStop(1, "rgba(37, 99, 235, 0.0)");
    const deptArr = meta.departments
      ? meta.departments.map((d) => ({
          name: d.name,
          topTech: d.leader.name,
          topTechAvg: d.leader.avg.toFixed(1),
          avg: d.avg,
          count: d.reviews,
        }))
      : (meta.ratings || []).map((t) => ({
          name: t.department || "ไม่ระบุฝ่ายงาน",
          topTech: t.name,
          topTechAvg: t.avg.toFixed(1),
          avg: t.avg,
          count: t.reviews,
        }));
    const getRatingColor = () => "#3b82f6";

    return {
      type: "bar",
      plugins: [
        {
          id: "custom_star_gradient",
          afterDatasetsDraw: (chart) => {
            // ✨ ทำงานเฉพาะมือถือแนวตั้งเท่านั้น เพื่อให้กล่องฟิลเตอร์สีดำ (Tooltip) ลอยทับชื่อช่างและชื่อฝ่ายงาน ✨
            const isMobilePortrait =
              window.innerWidth < 640 && window.innerHeight > window.innerWidth;
            if (!isMobilePortrait) return;
            chart._drawCustomRatingLabels(chart);
          },
          afterDraw: (chart) => {
            // ✨ หน้าจออื่นๆ ทั้งหมด (คอม, iPad, มือถือแนวนอน) ทำงานเหมือนเดิม 100% ไม่กระทบส่วนอื่น ✨
            const isMobilePortrait =
              window.innerWidth < 640 && window.innerHeight > window.innerWidth;
            if (isMobilePortrait) return;
            chart._drawCustomRatingLabels(chart);
          },
          beforeInit: (chart) => {
            chart._drawCustomRatingLabels = (chart) => {
              const ctx = chart.ctx;
              const yAxis = chart.scales.y;

              ctx.save();
              // ✨ 1. เปลี่ยนมาใช้จัดชิดซ้าย (Left-aligned) ตามที่แอดมินใช้
              ctx.textAlign = "left";
              ctx.textBaseline = "middle";

              yAxis.ticks.forEach((tick, index) => {
                const y = yAxis.getPixelForTick(index);
                const labelArray = chart.data.labels[index];
                if (!labelArray) return;

                if (Array.isArray(labelArray) && labelArray.length === 3) {
                  const scoreStr = labelArray[0].trim();
                  const scoreVal = parseFloat(scoreStr) || 0;
                  const tName = labelArray[1];
                  const dName = labelArray[2];

                  let isTablet = window.innerWidth <= 1366;
                  let fSizeDept = isTablet ? 12 : 14;
                  let fSizeTech = isTablet ? 11 : 13;
                  let fSizeScore = isTablet ? 11 : 12;
                  let fSizeStar = isTablet ? 11 : 13;

                  // ✨ 2. ล็อคพิกัดให้ตัวหนังสือเริ่มวาดที่ตำแหน่งริมซ้าย
                  const textDrawX = 10;

                  const yOffsetTop = isTablet ? -18 : -16;
                  const yOffsetBottom = isTablet ? 18 : 16;

                  // วาดชื่อฝ่ายงาน (บรรทัดล่าง) - สีน้ำเงิน
                  ctx.font = `800 ${fSizeDept}px "Sarabun", sans-serif`;
                  ctx.fillStyle = "#4f46e5";
                  ctx.fillText(dName, textDrawX, y + yOffsetBottom);

                  // วาดชื่อช่าง (บรรทัดกลาง) - สีเทาเข้ม
                  ctx.font = `bold ${fSizeTech}px "Sarabun", sans-serif`;
                  ctx.fillStyle = "#475569";
                  ctx.fillText(tName, textDrawX, y);

                  // วาดดาว (บรรทัดบน)
                  const textY = y + yOffsetTop;
                  ctx.font = `900 ${fSizeStar}px "Font Awesome 6 Free"`;
                  const starIcon = "\uf005";
                  const starWidth = ctx.measureText(starIcon).width;

                  // ดาวพื้นหลัง (สีเทา)
                  ctx.fillStyle = "#e2e8f0";
                  ctx.fillText(starIcon, textDrawX, textY);

                  // ดาวทับ (สีเหลือง ไล่ตาม %)
                  if (scoreVal > 0) {
                    const fillPercent = scoreVal / 5.0;
                    ctx.save();
                    ctx.beginPath();
                    ctx.rect(
                      textDrawX,
                      textY - 10,
                      starWidth * fillPercent,
                      20,
                    );
                    ctx.clip();
                    ctx.fillStyle = "#f59e0b";
                    ctx.fillText(starIcon, textDrawX, textY);
                    ctx.restore();
                  }

                  // วาดคะแนนตัวเลข
                  ctx.font = `bold ${fSizeScore}px "Sarabun", sans-serif`;
                  ctx.fillStyle = "#64748b";
                  ctx.fillText(scoreStr, textDrawX + starWidth + 6, textY);
                } else {
                  ctx.font = 'bold 13px "Sarabun", sans-serif';
                  ctx.fillStyle = "#475569";
                  ctx.fillText(
                    Array.isArray(labelArray)
                      ? labelArray.join(" ")
                      : labelArray,
                    10,
                    y,
                  );
                }
              });
              ctx.restore();
            };
          },
        },
      ],
      data: {
        labels: deptArr.length
          ? deptArr.map((d) => ["   " + d.topTechAvg, d.topTech, d.name])
          : [["ไม่มีข้อมูล"]],
        datasets: [
          {
            label: "คะแนนเฉลี่ยฝ่าย",
            data: deptArr.length ? deptArr.map((d) => d.avg) : [0],
            backgroundColor: deptArr.length
              ? deptArr.map((d) => getRatingColor(d.avg))
              : ["#e2e8f0"],
            borderRadius: 10,
            barThickness: window.innerWidth <= 1366 ? 20 : 24,
            maxBarThickness: 32,
            borderSkipped: false,
            z: 2,
          },
          {
            label: "เต็ม 5",
            data: deptArr.length ? deptArr.map((d) => 5.0) : [5.0],
            backgroundColor: "#f1f5f9",
            borderRadius: 10,
            barThickness: window.innerWidth <= 1366 ? 20 : 24,
            maxBarThickness: 32,
            borderSkipped: false,
            z: 1,
          },
        ],
      },
      options: {
        indexAxis: "y",
        grouped: false,
        responsive: true,
        maintainAspectRatio: false,
        layout: {
          padding: { left: 0, right: window.innerWidth <= 1366 ? 10 : 20 },
        },
        interaction: { mode: "y", intersect: false },
        onClick: (e, elements, chart) => {
          const activeElements = chart.getElementsAtEventForMode(
            e,
            "y",
            { intersect: false },
            true,
          );
          if (
            activeElements &&
            activeElements.length > 0 &&
            deptArr.length > 0
          ) {
            const index = activeElements[0].index;
            if (deptArr && deptArr[index]) {
              let selectedDept = deptArr[index].name;
              openTechReviewsModal(selectedDept, m, y);
            }
          }
        },
        onHover: (event, chartElement) => {
          event.native.target.style.cursor =
            chartElement.length > 0 ? "pointer" : "default";
        },
        plugins: {
          legend: { display: false },
          tooltip: {
            filter: function (tooltipItem) {
              return tooltipItem.datasetIndex === 0;
            },
            callbacks: {
              title: function (context) {
                let idx = context[0].dataIndex;
                return deptArr[idx] ? deptArr[idx].name : "";
              },
              label: function (context) {
                let idx = context.dataIndex;
                if (!deptArr[idx]) return "";
                return ` เจ้าหน้าที่ ${deptArr[idx].topTech} (⭐ ${deptArr[idx].topTechAvg})`;
              },
              afterLabel: function (context) {
                let idx = context.dataIndex;
                if (!deptArr[idx]) return "";
                return `จำนวน: ${deptArr[idx].count} รีวิว`;
              },
            },
            titleFont: {
              family: "'Sarabun', sans-serif",
              size: 14,
              weight: "bold",
            },
            bodyFont: {
              family: "'Sarabun', sans-serif",
              size: 13,
              weight: "normal",
            },
          },
        },
        scales: {
          x: {
            min: 0,
            max: 5,
            ticks: {
              stepSize: 1,
              font: { family: "'Sarabun', sans-serif", weight: "bold" },
              color: "#94a3b8",
            },
            grid: { color: "#f1f5f9", drawBorder: false },
            border: { display: false },
          },
          y: {
            ticks: {
              color: "transparent",
              font: {
                family: "'Sarabun', sans-serif",
                size: 1,
                weight: "bold",
              }, // หลอกให้กินพื้นที่น้อยสุด
              autoSkip: false,
              maxRotation: 0,
              minRotation: 0,
              padding: 0,
            },
            grid: { display: false },
            border: { display: false },
            afterFit: function (scaleInstance) {
              // ✨ 3. สร้าง "กำแพง" ดันตัวหนังสือไม่ให้ทับกราฟ ✨
              if (window.innerWidth <= 1366) {
                scaleInstance.width = 160;
              } else {
                scaleInstance.width = 190;
              }
            },
          },
        },
      },
    };
  };

  factories["technician-equipment"] = function (canvas, pairs, meta) {
    const ctx = canvas.getContext("2d"),
      eqCtx = ctx,
      statusCtx = ctx,
      locCtx = ctx;
    const sorted = pairs.map(([name, count], i) => ({
        name: meta.labels?.[i] ?? name,
        count,
      })),
      eqSorted = sorted,
      locSorted = sorted;
    const locLabels = sorted.length
      ? sorted.map((e) => e.name)
      : ["ไม่มีข้อมูล"];
    const techLabels = sorted.length
      ? sorted.map((e) => {
          const name = String(e.name).trim(),
            p = name.split(/\s+/);
          return window.innerWidth <= 1366 && name.length > 5 && p.length > 1
            ? [p[0], p.slice(1).join(" ")]
            : name;
        })
      : ["ไม่มีข้อมูล"];
    const pending = pairs.find((p) => p[0] === "รอรับเรื่อง")?.[1] || 0,
      progress = pairs.find((p) => p[0] === "กำลังดำเนินการ")?.[1] || 0,
      completed = pairs.find((p) => p[0] === "ซ่อมเสร็จแล้ว")?.[1] || 0;
    const totalStatus = pairs.reduce((n, p) => n + p[1], 0),
      isEmpty = totalStatus === 0;
    const gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, "rgba(37, 99, 235, 0.16)");
    gradient.addColorStop(1, "rgba(37, 99, 235, 0.0)");
    const deptArr = meta.departments
      ? meta.departments.map((d) => ({
          name: d.name,
          topTech: d.leader.name,
          topTechAvg: d.leader.avg.toFixed(1),
          avg: d.avg,
          count: d.reviews,
        }))
      : (meta.ratings || []).map((t) => ({
          name: t.department || "ไม่ระบุฝ่ายงาน",
          topTech: t.name,
          topTechAvg: t.avg.toFixed(1),
          avg: t.avg,
          count: t.reviews,
        }));
    const getRatingColor = () => "#3b82f6";

    return {
      type: "line",
      data: {
        labels: eqSorted.length ? eqSorted.map((e) => e.name) : ["ไม่มีข้อมูล"],
        datasets: [
          {
            label: "จำนวน (ครั้ง)",
            data: eqSorted.length ? eqSorted.map((e) => e.count) : [0],
            borderColor: "#2563eb",
            backgroundColor: gradient,
            borderWidth: 3,
            pointBackgroundColor: "#ffffff",
            pointBorderColor: "#2563eb",
            pointBorderWidth: 2,
            pointRadius: 4,
            fill: true,
            tension: 0.4,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: commonTooltipOptions, // ✨ ใส่ Tooltip สีดำ
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: { stepSize: 1 },
            grid: { color: "#f8fafc" },
            border: { display: false },
          },
          x: {
            grid: { display: false },
            border: { display: false },
            ticks: { minRotation: 0, maxRotation: 0, font: { weight: "bold" } },
          },
        },
      },
    };
  };

  factories["technician-status"] = function (canvas, pairs, meta) {
    const ctx = canvas.getContext("2d"),
      eqCtx = ctx,
      statusCtx = ctx,
      locCtx = ctx;
    const sorted = pairs.map(([name, count], i) => ({
        name: meta.labels?.[i] ?? name,
        count,
      })),
      eqSorted = sorted,
      locSorted = sorted;
    const locLabels = sorted.length
      ? sorted.map((e) => e.name)
      : ["ไม่มีข้อมูล"];
    const techLabels = sorted.length
      ? sorted.map((e) => {
          const name = String(e.name).trim(),
            p = name.split(/\s+/);
          return window.innerWidth <= 1366 && name.length > 5 && p.length > 1
            ? [p[0], p.slice(1).join(" ")]
            : name;
        })
      : ["ไม่มีข้อมูล"];
    const pending = pairs.find((p) => p[0] === "รอรับเรื่อง")?.[1] || 0,
      progress = pairs.find((p) => p[0] === "กำลังดำเนินการ")?.[1] || 0,
      completed = pairs.find((p) => p[0] === "ซ่อมเสร็จแล้ว")?.[1] || 0;
    const totalStatus = pairs.reduce((n, p) => n + p[1], 0),
      isEmpty = totalStatus === 0;
    const gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, "rgba(37, 99, 235, 0.16)");
    gradient.addColorStop(1, "rgba(37, 99, 235, 0.0)");
    const deptArr = meta.departments
      ? meta.departments.map((d) => ({
          name: d.name,
          topTech: d.leader.name,
          topTechAvg: d.leader.avg.toFixed(1),
          avg: d.avg,
          count: d.reviews,
        }))
      : (meta.ratings || []).map((t) => ({
          name: t.department || "ไม่ระบุฝ่ายงาน",
          topTech: t.name,
          topTechAvg: t.avg.toFixed(1),
          avg: t.avg,
          count: t.reviews,
        }));
    const getRatingColor = () => "#3b82f6";

    return {
      type: "doughnut",
      data: {
        labels:
          totalStatus === 0
            ? ["ไม่มีข้อมูล"]
            : ["รอรับเรื่อง", "กำลังดำเนินการ", "ซ่อมเสร็จแล้ว"],
        datasets: [
          {
            label: "จำนวนงาน",
            data: totalStatus === 0 ? [1] : [pending, progress, completed],
            backgroundColor:
              totalStatus === 0
                ? ["#f1f5f9"]
                : ["#fbbf24", "#38bdf8", "#10b981"],
            borderWidth: 0,
            hoverOffset: 4,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: "75%",
        plugins: {
          legend: {
            position: "bottom",
            labels: {
              usePointStyle: true,
              padding: 20,
              font: { size: 12, weight: "bold" },
            },
          },
          tooltip: Object.assign({}, commonTooltipOptions, {
            // ✨ ใส่ Tooltip สีดำ + ปิดการแสดงผลถ้าไม่มีข้อมูล
            enabled: totalStatus > 0,
            callbacks: {
              label: function (context) {
                return " " + context.formattedValue + " งาน";
              },
            },
          }),
        },
      },
    };
  };

  factories["technician-location"] = function (canvas, pairs, meta) {
    const ctx = canvas.getContext("2d"),
      eqCtx = ctx,
      statusCtx = ctx,
      locCtx = ctx;
    const sorted = pairs.map(([name, count], i) => ({
        name: meta.labels?.[i] ?? name,
        count,
      })),
      eqSorted = sorted,
      locSorted = sorted;
    const locLabels = sorted.length
      ? sorted.map((e) => e.name)
      : ["ไม่มีข้อมูล"];
    const techLabels = sorted.length
      ? sorted.map((e) => {
          const name = String(e.name).trim(),
            p = name.split(/\s+/);
          return window.innerWidth <= 1366 && name.length > 5 && p.length > 1
            ? [p[0], p.slice(1).join(" ")]
            : name;
        })
      : ["ไม่มีข้อมูล"];
    const pending = pairs.find((p) => p[0] === "รอรับเรื่อง")?.[1] || 0,
      progress = pairs.find((p) => p[0] === "กำลังดำเนินการ")?.[1] || 0,
      completed = pairs.find((p) => p[0] === "ซ่อมเสร็จแล้ว")?.[1] || 0;
    const totalStatus = pairs.reduce((n, p) => n + p[1], 0),
      isEmpty = totalStatus === 0;
    const gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, "rgba(37, 99, 235, 0.16)");
    gradient.addColorStop(1, "rgba(37, 99, 235, 0.0)");
    const deptArr = meta.departments
      ? meta.departments.map((d) => ({
          name: d.name,
          topTech: d.leader.name,
          topTechAvg: d.leader.avg.toFixed(1),
          avg: d.avg,
          count: d.reviews,
        }))
      : (meta.ratings || []).map((t) => ({
          name: t.department || "ไม่ระบุฝ่ายงาน",
          topTech: t.name,
          topTechAvg: t.avg.toFixed(1),
          avg: t.avg,
          count: t.reviews,
        }));
    const getRatingColor = () => "#3b82f6";

    return {
      type: "bar",
      data: {
        labels: locSorted.length
          ? locSorted.map((e) => e.name)
          : ["ไม่มีข้อมูล"],
        datasets: [
          {
            label: "แจ้งซ่อม (ครั้ง)",
            data: locSorted.length ? locSorted.map((e) => e.count) : [0],
            backgroundColor: "#0284c7",
            borderRadius: 6,
          },
        ],
      },
      options: {
        indexAxis: "y",
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: commonTooltipOptions, // ✨ ใส่ Tooltip สีดำ
        },
        scales: {
          x: {
            beginAtZero: true,
            ticks: { stepSize: 1 },
            grid: { color: "#f8fafc" },
            border: { display: false },
          },
          y: {
            afterFit: (scale) => {
              if (techCompactViewport())
                scale.width = techLocationAxisWidth(scale.chart);
            },
            grid: { display: false },
            border: { display: false },
            ticks: {
              font: { weight: "bold" },
              crossAlign: () => (techCompactViewport() ? "far" : "near"),
              autoSkip: false,
              callback: techLocationTick,
            },
          },
        },
      },
    };
  };

  window.MbsOverviewAppearance = {
    build(role, kind, canvas, pairs, meta = {}) {
      const factory =
        factories[role + "-" + kind] || factories["admin-" + kind];
      if (!factory) return null;
      const config = factory(canvas, pairs, meta);
      // One restrained hue per Overview metric; preserve chart geometry and data.
      const overviewColors = {
        equipment: { base: "#6585b8", hover: "#4f6fa2" },
        location: { base: "#568f88", hover: "#40766f" },
        technician: { base: "#8c7bab", hover: "#73628f" },
      };
      const tone = overviewColors[kind];
      if (tone) {
        config.data.datasets.forEach((dataset) => {
          if (config.type === "line") {
            const fill = canvas.getContext("2d").createLinearGradient(0, 0, 0, 400);
            fill.addColorStop(0, "rgba(101, 133, 184, 0.16)");
            fill.addColorStop(1, "rgba(101, 133, 184, 0)");
            dataset.borderColor = tone.base;
            dataset.backgroundColor = fill;
            dataset.pointBorderColor = tone.base;
            dataset.pointHoverBorderColor = tone.hover;
            dataset.pointHoverBackgroundColor = "#ffffff";
          } else {
            dataset.backgroundColor = tone.base;
            dataset.hoverBackgroundColor = tone.hover;
          }
        });
      }
      if (kind === "rating" && meta.departments) {
        config.data.datasets[0].label = "คะแนนเฉลี่ยเจ้าหน้าที่";
        config.plugins = config.plugins || [];
        config.plugins.push({
          id: "mbs_visible_rating_counts",
          afterDraw(chart) {
            const axis = chart.scales.y;
            if (!axis) return;
            const ctx = chart.ctx;
            ctx.save();
            ctx.font = '500 11px "Sarabun", sans-serif';
            ctx.fillStyle = "#64748b";
            ctx.textAlign = "left";
            ctx.textBaseline = "middle";
            meta.departments.forEach((d, index) => {
              ctx.fillText(
                d.n + " คะแนน · " + d.reviews + " รีวิว",
                10,
                axis.getPixelForTick(index) + 35,
              );
            });
            ctx.restore();
          },
        });
      }
      if (kind === "status" && pairs.length) {
        // Keep actual filtered counts and labels; only the original ring/legend presentation is restored.
        config.data.labels = pairs.map((p) => p[0]);
        config.data.datasets[0].data = pairs.map((p) => p[1]);
        config.data.datasets[0].backgroundColor = pairs.map(
          (p) =>
            ({
              รอรับเรื่อง: role === "technician" ? "#fbbf24" : "#f59e0b",
              กำลังดำเนินการ: "#38bdf8",
              ซ่อมเสร็จแล้ว: "#10b981",
            })[p[0]] || "#a78bfa",
        );
      }
      if (meta.onClick) config.options.onClick = meta.onClick;
      if (meta.onHover) config.options.onHover = meta.onHover;
      if (meta.tooltip) {
        config.options.plugins.tooltip = {
          ...config.options.plugins.tooltip,
          callbacks: meta.tooltip,
        };
      }
      if (role !== "technician") {
        if (kind === "location")
          canvas.parentElement.style.height =
            window.innerWidth <= 1366 ? "280px" : "250px";
        if (kind === "technician")
          canvas.parentElement.style.height =
            window.innerWidth <= 1366 ? "290px" : "250px";
        if (kind === "rating")
          canvas.parentElement.style.height =
            window.innerWidth <= 1366 ? "400px" : "380px";
      }
      return config;
    },
  };
})();
