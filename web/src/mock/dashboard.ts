/** Pre-aggregated numbers & series for the dashboard (mock analytics). */

export interface StatSeriesPoint {
  x: string;
  value: number;
}

export interface DashboardStat {
  key: string;
  label: string;
  value: number;
  /** percentage change vs. previous period */
  delta: number;
  /** whether an upward delta is good (affects color) */
  positiveIsGood: boolean;
  spark: number[];
  format: "number" | "currency";
}

export const dashboardStats: DashboardStat[] = [
  {
    key: "active_elevators",
    label: "Aktif Asansör",
    value: 13,
    delta: 2.4,
    positiveIsGood: true,
    spark: [11, 12, 12, 13, 12, 13, 13],
    format: "number",
  },
  {
    key: "open_work_orders",
    label: "Açık İş Emri",
    value: 7,
    delta: 16.7,
    positiveIsGood: false,
    spark: [4, 5, 5, 6, 6, 7, 7],
    format: "number",
  },
  {
    key: "completed_this_month",
    label: "Bu Ay Tamamlanan",
    value: 42,
    delta: 8.1,
    positiveIsGood: true,
    spark: [28, 31, 34, 33, 37, 40, 42],
    format: "number",
  },
  {
    key: "expiring_contracts",
    label: "Süresi Dolan Sözleşme",
    value: 4,
    delta: 33.3,
    positiveIsGood: false,
    spark: [1, 2, 2, 3, 3, 4, 4],
    format: "number",
  },
];

/** Work-order volume, last 30 days (mock). */
export const workOrderVolume: StatSeriesPoint[] = Array.from({ length: 30 }, (_, i) => {
  const day = new Date("2026-07-05T00:00:00Z");
  day.setDate(day.getDate() - (29 - i));
  const base = 3 + Math.round(3 * Math.abs(Math.sin(i / 3.2)));
  const spike = i === 27 ? 5 : i === 15 ? 3 : 0;
  return {
    x: day.toISOString().slice(0, 10),
    value: base + spike,
  };
});

/** Work-order type distribution (donut). */
export const workOrderTypeDistribution: { type: string; label: string; value: number }[] = [
  { type: "maintenance", label: "Periyodik Bakım", value: 58 },
  { type: "fault", label: "Arıza", value: 21 },
  { type: "inspection", label: "Muayene", value: 12 },
  { type: "repair", label: "Onarım", value: 6 },
  { type: "modernization", label: "Modernizasyon", value: 3 },
];

export interface ActivityItem {
  id: string;
  actor: string;
  action: string;
  target: string;
  at: string;
  kind: "completed" | "created" | "assigned" | "alert";
}

export const recentActivity: ActivityItem[] = [
  {
    id: "act_1", actor: "Mehmet Kaya", action: "arıza müdahalesine başladı",
    target: "Nurol Tower · A2 - Güney", at: "2026-07-05T09:10:00Z", kind: "alert",
  },
  {
    id: "act_2", actor: "Sistem", action: "yeni iş emri oluşturdu",
    target: "WO-20260707-1A2B3C4D", at: "2026-07-05T08:05:00Z", kind: "created",
  },
  {
    id: "act_3", actor: "Zeynep Aksoy", action: "iş emrini atadı",
    target: "Emre Yıldız · Kızılay P1", at: "2026-07-05T07:45:00Z", kind: "assigned",
  },
  {
    id: "act_4", actor: "Ayşe Demir", action: "bakımı tamamladı",
    target: "Palladium · Blok A", at: "2026-07-01T10:20:00Z", kind: "completed",
  },
  {
    id: "act_5", actor: "Emre Yıldız", action: "onarımı tamamladı",
    target: "Palladium · Yük Asansörü", at: "2026-06-30T13:15:00Z", kind: "completed",
  },
];
