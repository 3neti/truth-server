import { defineComponent as L, toRefs as W, computed as g, openBlock as e, createElementBlock as o, normalizeClass as d, toDisplayString as z, unref as l, createCommentVNode as f, createElementVNode as w, normalizeStyle as m, Fragment as b, renderList as C } from "vue";
const P = ["aria-label"], T = {
  key: 0,
  class: "tally-count tally-count--above"
}, $ = { class: "tally-marks-wrapper" }, B = {
  key: 0,
  class: "tally-count tally-count--before"
}, V = {
  key: 1,
  class: "tally-count tally-count--after"
}, A = {
  key: 1,
  class: "tally-count tally-count--below"
}, E = /* @__PURE__ */ L({
  __name: "TallyMarks",
  props: {
    count: {},
    highlightColor: { default: "#e74c3c" },
    size: { default: "medium" },
    color: { default: "currentColor" },
    animated: { type: Boolean, default: !1 },
    animationDelay: { default: 100 },
    showCount: { type: Boolean, default: !1 },
    countPosition: { default: "after" },
    customClass: { default: "" },
    ariaLabel: { default: "" }
  },
  setup(a) {
    const t = a, { count: s, size: x, color: h, animated: y, animationDelay: F } = W(t), v = g(() => Math.floor(t.count / 5)), k = g(() => t.count % 5), _ = g(() => k.value > 0), M = (u) => u === v.value - 1, r = g(() => {
      const u = {
        small: { fontSize: "0.9rem", gap: "4px", groupGap: "1px", diagonalWidth: "42px" },
        medium: { fontSize: "1.2rem", gap: "6px", groupGap: "2px", diagonalWidth: "52px" },
        large: { fontSize: "1.5rem", gap: "8px", groupGap: "3px", diagonalWidth: "62px" }
      };
      if (t.size in u)
        return u[t.size];
      const c = t.size, n = parseInt(c) || 16, i = Math.round(n * 2.7);
      return {
        fontSize: c,
        gap: `${Math.max(4, n * 0.3)}px`,
        groupGap: `${Math.max(1, n * 0.1)}px`,
        diagonalWidth: `${i}px`
      };
    }), G = g(() => t.ariaLabel ? t.ariaLabel : `Tally marks showing count of ${t.count}`), p = (u, c) => {
      if (!t.animated)
        return "0s";
      const n = u * 5 * t.animationDelay, i = c !== void 0 ? c * t.animationDelay : 0;
      return `${(n + i) / 1e3}s`;
    };
    return (u, c) => (e(), o("div", {
      class: d(["tally-marks-container", a.customClass]),
      "aria-label": G.value,
      role: "img"
    }, [
      a.showCount && a.countPosition === "above" ? (e(), o("div", T, z(l(s)), 1)) : f("", !0),
      w("div", $, [
        a.showCount && a.countPosition === "before" ? (e(), o("span", B, z(l(s)), 1)) : f("", !0),
        w("div", {
          class: "tally-marks",
          style: m({
            gap: r.value.gap,
            color: l(h)
          })
        }, [
          (e(!0), o(b, null, C(v.value, (n, i) => (e(), o("div", {
            key: "group-" + i,
            class: d(["tally-group", { "tally-group--animated": l(y) }]),
            style: m({
              gap: r.value.groupGap,
              animationDelay: p(i)
            })
          }, [
            (e(), o(b, null, C(4, (D) => w("span", {
              key: "mark-" + D,
              class: d(["mark", { "mark--animated": l(y) }]),
              style: m({
                fontSize: r.value.fontSize,
                animationDelay: p(i, D - 1)
              })
            }, "|", 6)), 64)),
            w("span", {
              class: d(["diagonal", { "diagonal--animated": l(y) }]),
              style: m({
                backgroundColor: M(i) && !_.value ? a.highlightColor : "black",
                width: r.value.diagonalWidth,
                animationDelay: p(i, 4)
              })
            }, null, 6)
          ], 6))), 128)),
          k.value > 0 ? (e(), o("div", {
            key: 0,
            class: d(["tally-group", { "tally-group--animated": l(y) }]),
            style: m({
              gap: r.value.groupGap,
              animationDelay: p(v.value)
            })
          }, [
            (e(!0), o(b, null, C(k.value, (n) => (e(), o("span", {
              key: "mark-" + n,
              class: d(["mark", { "mark--animated": l(y) }]),
              style: m({
                fontSize: r.value.fontSize,
                color: n === k.value ? a.highlightColor : l(h),
                animationDelay: p(v.value, n - 1)
              })
            }, "|", 6))), 128))
          ], 6)) : f("", !0)
        ], 4),
        a.showCount && a.countPosition === "after" ? (e(), o("span", V, z(l(s)), 1)) : f("", !0)
      ]),
      a.showCount && a.countPosition === "below" ? (e(), o("div", A, z(l(s)), 1)) : f("", !0)
    ], 10, P));
  }
});
const N = (a, t) => {
  const s = a.__vccOpts || a;
  for (const [x, h] of t)
    s[x] = h;
  return s;
}, R = /* @__PURE__ */ N(E, [["__scopeId", "data-v-311d8834"]]);
function S(a) {
  a.component("TallyMarks", R);
}
const j = {
  install: S
};
typeof window < "u" && window.Vue && window.Vue.use({ install: S });
export {
  R as TallyMarks,
  j as default,
  S as install
};
//# sourceMappingURL=index.es.js.map
