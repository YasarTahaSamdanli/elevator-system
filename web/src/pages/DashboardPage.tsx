import { Link } from "react-router-dom";
import {
  AlertTriangle,
  ArrowDownRight,
  ArrowRight,
  ArrowUpRight,
  CheckCircle2,
  PlusCircle,
  UserCheck,
} from "lucide-react";
import {
  Area,
  AreaChart,
  CartesianGrid,
  Cell,
  Pie,
  PieChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from "recharts";
import { PageHeader } from "@/components/common/PageHeader";
import { StatusBadge } from "@/components/common/StatusBadge";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { useTheme } from "@/providers/ThemeProvider";
import { getChartPalette, workOrderTypeOrder } from "@/lib/chartColors";
import { formatNumber, initials, timeAgo } from "@/lib/format";
import { workOrderPriorityMeta, workOrderStatusMeta } from "@/lib/status";
import { cn } from "@/lib/utils";
import {
  dashboardStats,
  recentActivity,
  workOrders,
  workOrderTypeDistribution,
  workOrderVolume,
  type ActivityItem,
  type DashboardStat,
} from "@/mock";
import type { WorkOrderPriority } from "@/types";

/* ---------- shared chart pieces ---------- */

const trShortDay = new Intl.DateTimeFormat("tr-TR", { day: "numeric", month: "short" });

interface TooltipEntry {
  value?: number | string;
  name?: string;
}

function ChartTooltip({
  active,
  payload,
  label,
  palette,
}: {
  active?: boolean;
  payload?: TooltipEntry[];
  label?: string;
  palette: ReturnType<typeof getChartPalette>;
}) {
  if (!active || !payload?.length) return null;
  return (
    <div
      className="rounded-md px-3 py-2 text-xs shadow-md"
      style={{
        background: palette.tooltipBg,
        color: palette.tooltipText,
        border: `1px solid ${palette.tooltipBorder}`,
      }}
    >
      {label && <div className="mb-0.5 opacity-70">{trShortDay.format(new Date(label))}</div>}
      <div className="font-semibold tabular-nums">{payload[0]?.value} iş emri</div>
    </div>
  );
}

/* ---------- stat tiles ---------- */

function Sparkline({ data, color, id }: { data: number[]; color: string; id: string }) {
  const points = data.map((value, i) => ({ i, value }));
  return (
    <div className="h-10 w-24">
      <ResponsiveContainer width="100%" height="100%">
        <AreaChart data={points} margin={{ top: 2, right: 0, bottom: 0, left: 0 }}>
          <defs>
            <linearGradient id={id} x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stopColor={color} stopOpacity={0.25} />
              <stop offset="100%" stopColor={color} stopOpacity={0.02} />
            </linearGradient>
          </defs>
          <Area
            type="monotone"
            dataKey="value"
            stroke={color}
            strokeWidth={1.75}
            fill={`url(#${id})`}
            isAnimationActive={false}
          />
        </AreaChart>
      </ResponsiveContainer>
    </div>
  );
}

function StatTile({ stat }: { stat: DashboardStat }) {
  const { theme } = useTheme();
  const palette = getChartPalette(theme);
  const isGood = stat.delta >= 0 ? stat.positiveIsGood : !stat.positiveIsGood;
  const DeltaIcon = stat.delta >= 0 ? ArrowUpRight : ArrowDownRight;

  return (
    <Card>
      <CardContent className="flex items-end justify-between gap-3 p-5">
        <div className="space-y-1.5">
          <div className="text-sm text-muted-foreground">{stat.label}</div>
          <div className="text-2xl font-semibold tabular-nums tracking-tight text-foreground">
            {formatNumber(stat.value)}
          </div>
          <div
            className={cn(
              "inline-flex items-center gap-0.5 text-xs font-medium tabular-nums",
              isGood ? "text-success-foreground" : "text-danger-foreground"
            )}
          >
            <DeltaIcon className="size-3.5" />
            %{Math.abs(stat.delta).toLocaleString("tr-TR")}
            <span className="ml-1 font-normal text-muted-foreground">geçen aya göre</span>
          </div>
        </div>
        <Sparkline data={stat.spark} color={palette.accent} id={`spark-${stat.key}`} />
      </CardContent>
    </Card>
  );
}

/* ---------- volume area chart (single series → no legend) ---------- */

function VolumeChart() {
  const { theme } = useTheme();
  const palette = getChartPalette(theme);

  return (
    <Card className="lg:col-span-2">
      <CardHeader>
        <CardTitle>İş Emri Hacmi</CardTitle>
        <p className="text-xs text-muted-foreground">Son 30 gün, günlük açılan iş emri sayısı</p>
      </CardHeader>
      <CardContent className="h-64 pl-0">
        <ResponsiveContainer width="100%" height="100%">
          <AreaChart data={workOrderVolume} margin={{ top: 4, right: 12, bottom: 0, left: 0 }}>
            <defs>
              <linearGradient id="volume-fill" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stopColor={palette.accentFill} stopOpacity={0.2} />
                <stop offset="100%" stopColor={palette.accentFill} stopOpacity={0.02} />
              </linearGradient>
            </defs>
            <CartesianGrid stroke={palette.grid} strokeDasharray="0" vertical={false} />
            <XAxis
              dataKey="x"
              tick={{ fill: palette.axis, fontSize: 11 }}
              tickFormatter={(v: string) => trShortDay.format(new Date(v))}
              axisLine={{ stroke: palette.grid }}
              tickLine={false}
              minTickGap={32}
            />
            <YAxis
              width={32}
              tick={{ fill: palette.axis, fontSize: 11 }}
              axisLine={false}
              tickLine={false}
              allowDecimals={false}
            />
            <Tooltip
              content={<ChartTooltip palette={palette} />}
              cursor={{ stroke: palette.axis, strokeWidth: 1 }}
            />
            <Area
              type="monotone"
              dataKey="value"
              stroke={palette.accent}
              strokeWidth={2}
              fill="url(#volume-fill)"
              isAnimationActive={false}
              activeDot={{ r: 4, strokeWidth: 2, stroke: palette.tooltipBg }}
            />
          </AreaChart>
        </ResponsiveContainer>
      </CardContent>
    </Card>
  );
}

/* ---------- type distribution donut (text legend is mandatory) ---------- */

function TypeDonut() {
  const { theme } = useTheme();
  const palette = getChartPalette(theme);
  // slice colors follow the fixed categorical order defined in chartColors.ts
  const colorByType = new Map(workOrderTypeOrder.map((t, i) => [t, palette.categorical[i]]));
  const surface = theme === "dark" ? "#15151a" : "#ffffff";
  const total = workOrderTypeDistribution.reduce((sum, d) => sum + d.value, 0);

  return (
    <Card>
      <CardHeader>
        <CardTitle>İş Emri Türleri</CardTitle>
        <p className="text-xs text-muted-foreground">Son 90 gün dağılımı</p>
      </CardHeader>
      <CardContent className="flex items-center gap-4">
        <div className="relative h-40 w-40 shrink-0">
          <ResponsiveContainer width="100%" height="100%">
            <PieChart>
              <Pie
                data={workOrderTypeDistribution}
                dataKey="value"
                nameKey="label"
                innerRadius={52}
                outerRadius={72}
                paddingAngle={1}
                stroke={surface}
                strokeWidth={2}
                isAnimationActive={false}
              >
                {workOrderTypeDistribution.map((d) => (
                  <Cell key={d.type} fill={colorByType.get(d.type as never) ?? palette.accent} />
                ))}
              </Pie>
            </PieChart>
          </ResponsiveContainer>
          <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
            <div className="text-xl font-semibold tabular-nums text-foreground">{total}</div>
            <div className="text-xs text-muted-foreground">toplam</div>
          </div>
        </div>
        <ul className="min-w-0 flex-1 space-y-2">
          {workOrderTypeDistribution.map((d) => (
            <li key={d.type} className="flex items-center gap-2 text-sm">
              <span
                className="size-2.5 shrink-0 rounded-[3px]"
                style={{ background: colorByType.get(d.type as never) }}
              />
              <span className="truncate text-muted-foreground">{d.label}</span>
              <span className="ml-auto tabular-nums text-foreground">
                %{Math.round((d.value / total) * 100)}
              </span>
            </li>
          ))}
        </ul>
      </CardContent>
    </Card>
  );
}

/* ---------- open work orders ---------- */

const priorityRank: Record<WorkOrderPriority, number> = {
  critical: 0,
  high: 1,
  normal: 2,
  low: 3,
};

function OpenWorkOrders() {
  const open = workOrders
    .filter((wo) => !["completed", "cancelled"].includes(wo.status))
    .sort((a, b) => priorityRank[a.priority] - priorityRank[b.priority])
    .slice(0, 5);

  return (
    <Card>
      <CardHeader className="flex-row items-center justify-between space-y-0">
        <CardTitle>Açık İş Emirleri</CardTitle>
        <Button variant="ghost" size="sm" asChild>
          <Link to="/work-orders">
            Tümü
            <ArrowRight className="size-3.5" />
          </Link>
        </Button>
      </CardHeader>
      <CardContent className="space-y-1 p-3 pt-0">
        {open.map((wo) => {
          const prio = workOrderPriorityMeta[wo.priority];
          return (
            <Link
              key={wo.id}
              to="/work-orders"
              className="flex items-center gap-3 rounded-md px-2 py-2.5 transition-colors hover:bg-muted"
            >
              <span className={cn("size-2 shrink-0 rounded-full", prio.dot)} title={prio.label} />
              <div className="min-w-0 flex-1">
                <div className="truncate font-mono text-xs text-foreground">
                  {wo.work_order_number}
                </div>
                <div className="truncate text-xs text-muted-foreground">
                  {wo.building_name} · {wo.elevator_name}
                </div>
              </div>
              <StatusBadge meta={workOrderStatusMeta[wo.status]} dot={false} />
              {wo.assigned_user && (
                <Avatar className="size-6">
                  <AvatarFallback className="bg-muted text-[10px] font-medium text-muted-foreground">
                    {initials(wo.assigned_user.name)}
                  </AvatarFallback>
                </Avatar>
              )}
            </Link>
          );
        })}
      </CardContent>
    </Card>
  );
}

/* ---------- recent activity ---------- */

const activityIcon: Record<ActivityItem["kind"], { icon: typeof CheckCircle2; className: string }> = {
  completed: { icon: CheckCircle2, className: "text-success" },
  created: { icon: PlusCircle, className: "text-muted-foreground" },
  assigned: { icon: UserCheck, className: "text-info" },
  alert: { icon: AlertTriangle, className: "text-danger" },
};

function RecentActivity() {
  return (
    <Card>
      <CardHeader>
        <CardTitle>Son Aktivite</CardTitle>
      </CardHeader>
      <CardContent className="space-y-4 pt-0">
        {recentActivity.map((item) => {
          const meta = activityIcon[item.kind];
          return (
            <div key={item.id} className="flex gap-3">
              <meta.icon className={cn("mt-0.5 size-4 shrink-0", meta.className)} strokeWidth={1.75} />
              <div className="min-w-0 space-y-0.5 text-sm">
                <div className="text-foreground">
                  <span className="font-medium">{item.actor}</span>{" "}
                  <span className="text-muted-foreground">{item.action}</span>
                </div>
                <div className="truncate text-xs text-muted-foreground">{item.target}</div>
                <div className="text-xs text-muted-foreground/70">{timeAgo(item.at)}</div>
              </div>
            </div>
          );
        })}
      </CardContent>
    </Card>
  );
}

/* ---------- page ---------- */

export function DashboardPage() {
  return (
    <div className="space-y-6">
      <PageHeader
        title="Gösterge Paneli"
        description="Saha operasyonlarının anlık görünümü"
      />

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {dashboardStats.map((stat) => (
          <StatTile key={stat.key} stat={stat} />
        ))}
      </div>

      <div className="grid gap-4 lg:grid-cols-3">
        <VolumeChart />
        <TypeDonut />
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <OpenWorkOrders />
        <RecentActivity />
      </div>
    </div>
  );
}
