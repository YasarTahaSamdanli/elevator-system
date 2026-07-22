/**
 * Chart palettes — validated with the dataviz skill's validator against the
 * app's own light (#ffffff) and dark (#15151a) chart surfaces.
 *
 * Categorical order is FIXED (never cycled): blue, aqua, yellow, green.
 * Donuts/legends always render text labels, which is the required relief for the
 * sub-3:1 (light) / floor-band CVD (dark) slots.
 */
import type { WorkOrderType } from "@/types";

export interface ChartPalette {
  categorical: string[];
  /** single-hue accent for sequential / single-series marks */
  accent: string;
  accentFill: string;
  grid: string;
  axis: string;
  tooltipBg: string;
  tooltipText: string;
  tooltipBorder: string;
}

const light: ChartPalette = {
  categorical: ["#2a78d6", "#1baf7a", "#eda100", "#008300"],
  accent: "#2563eb",
  accentFill: "#2563eb",
  grid: "#e4e4e7",
  axis: "#a1a1aa",
  tooltipBg: "#ffffff",
  tooltipText: "#18181b",
  tooltipBorder: "rgba(9,9,11,0.10)",
};

const dark: ChartPalette = {
  categorical: ["#3987e5", "#199e70", "#c98500", "#008300"],
  accent: "#3b82f6",
  accentFill: "#3b82f6",
  grid: "#27272a",
  axis: "#52525b",
  tooltipBg: "#1c1c22",
  tooltipText: "#fafafa",
  tooltipBorder: "rgba(255,255,255,0.12)",
};

export function getChartPalette(theme: "light" | "dark"): ChartPalette {
  return theme === "dark" ? dark : light;
}

/** Stable color assignment for work-order types (matches donut slot order). */
export const workOrderTypeOrder: WorkOrderType[] = [
  "maintenance",
  "fault",
  "inspection",
  "repair",
];
