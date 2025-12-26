const cystyle = [
  {
    selector: "node",
    style: {
      shape: function (ele) {
        const category = ele.data("category");
        return categoryShapes[category] || "ellipse";
      },
      "background-color": function (ele) {
        return getNodeColor(ele);
      },
      "background-image": function (ele) {
        const type = ele.data("type");
        const status = ele.data("status") || "unknown";
        if (type && status) {
          return `img/${type}-${status}.png`;
        }
        return "none";
      },
      "background-fit": "contain",
      "background-image-opacity": 0.8,
      label: function (ele) {
        const id = ele.data("id");
        const status = ele.data("status");
        const category = ele.data("category");
        const type = ele.data("type");

        let label = id;
        if (status) {
          label += `\n[${status}]`;
        }
        if (type) {
          label += `\n{${type}}`;
        }
        return label;
      },
      color: "#333",
      "text-valign": "bottom",
      "text-halign": "center",
      "text-margin-y": 5,
      width: 80,
      height: 80,
      "font-size": 10,
      "text-outline-width": 2,
      "text-outline-color": "#fff",
      "text-wrap": "wrap",
      "text-max-width": "120px",
    },
  },
  {
    selector: "edge",
    style: {
      width: 3,
      "line-color": "#ccc",
      "target-arrow-color": "#ccc",
      "target-arrow-shape": "triangle",
      "curve-style": "bezier",
      label: "data(label)",
      "font-size": 10,
      "text-rotation": "autorotate",
      "text-margin-y": -10,
    },
  },
  {
    selector: "node:selected",
    style: {
      "background-color": "#ffc107",
      "border-width": 3,
      "border-color": "#ff9800",
    },
  },
];
