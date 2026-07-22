import * as React from "react";
import { Link } from "react-router-dom";
import {
  ArrowRight,
  Ban,
  Building2,
  CheckCircle2,
  ClipboardList,
  FileWarning,
  PackageCheck,
  PackageX,
  Play,
  PlusCircle,
  Search,
  TrendingDown,
  UserCheck,
  Wrench,
  Zap,
  type LucideIcon,
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
import { ListError } from "@/components/common/ListError";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { useTheme } from "@/providers/ThemeProvider";
import { getChartPalette, workOrderTypeOrder } from "@/lib/chartColors";
import { formatCurrency, formatDateTime, formatNumber, initials, timeAgo } from "@/lib/format";
import { workOrderPriorityMeta, workOrderStatusMeta } from "@/lib/status";
import { cn } from "@/lib/utils";
import { ApiError } from "@/lib/api";
import {
  fetchDashboardStats,
  fetchInventoryMovementValue,
  fetchLowStockMaterials,
  fetchOperationsSummary,
  fetchRecentActivity,
  fetchRecentStockMovements,
  fetchTopConsumedMaterials,
  fetchTopOpenWorkOrders,
  fetchWorkOrderTypeDistribution,
  fetchWorkOrderVolume,
  type ActivityItem,
  type DashboardStats,
  type InventoryMovementPoint,
  type OperationsSummary,
  type TopConsumedMaterial,
  type TypeDistributionPoint,
  type VolumePoint,
} from "@/api/dashboard";
import type { Material, StockMovement, WorkOrder } from "@/types";

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

/* ---------- operations lanes (Bakım / Arıza / Revizyon / Muayene) ----------
 * The office's four working lanes, one card each: a couple of live counts
 * plus a link to where that work is done. Mirrors the boss's old dashboard.
 */

function OperationRow({
  label,
  value,
  emphasize,
}: {
  label: string;
  value: number;
  emphasize?: boolean;
}) {
  return (
    <div className="flex items-baseline justify-between gap-2 text-sm">
      <span className="text-muted-foreground">{label}</span>
      <span
        className={cn(
          "tabular-nums font-medium",
          emphasize && value > 0 ? "text-danger-foreground" : "text-foreground"
        )}
      >
        {formatNumber(value)}
      </span>
    </div>
  );
}

function OperationCard({
  title,
  icon: Icon,
  accent,
  to,
  cta,
  footnote,
  children,
}: {
  title: string;
  icon: LucideIcon;
  accent: string;
  to: string;
  cta: string;
  footnote?: string;
  children: React.ReactNode;
}) {
  return (
    <Card className="flex flex-col">
      <CardHeader className="flex-row items-center gap-2 space-y-0 pb-3">
        <Icon className={cn("size-4", accent)} />
        <CardTitle>{title}</CardTitle>
      </CardHeader>
      <CardContent className="flex flex-1 flex-col gap-1.5 pt-0">
        {children}
        {footnote && <p className="pt-1 text-xs text-muted-foreground/80">{footnote}</p>}
        <div className="flex-1" />
        <Button variant="outline" size="sm" className="mt-2 w-full" asChild>
          <Link to={to}>
            {cta}
            <ArrowRight className="size-3.5" />
          </Link>
        </Button>
      </CardContent>
    </Card>
  );
}

function OperationsCards({ summary }: { summary: OperationsSummary }) {
  return (
    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <OperationCard
        title="Bakım"
        icon={Wrench}
        accent="text-info"
        to="/work-orders?type=maintenance"
        cta="Bakım İş Emirleri"
      >
        <OperationRow label="Açık bakım" value={summary.maintenance.open} />
        <OperationRow label="Bugün planlı" value={summary.maintenance.scheduledToday} />
        <OperationRow label="Bu ay tamamlanan" value={summary.maintenance.completedThisMonth} />
      </OperationCard>

      <OperationCard
        title="Arıza"
        icon={Zap}
        accent="text-danger"
        to="/work-orders?type=fault"
        cta="Arıza İş Emirleri"
      >
        <OperationRow label="Bekleyen arıza" value={summary.fault.open} emphasize />
        <OperationRow label="Bu ay giderilen" value={summary.fault.completedThisMonth} />
      </OperationCard>

      <OperationCard
        title="Revizyon"
        icon={Wrench}
        accent="text-warning"
        to="/work-orders?type=repair"
        cta="Revizyon İş Emirleri"
        footnote="Takip sınırı: kırmızı 60 gün · sarı 120 gün"
      >
        <OperationRow label="Açık revizyon" value={summary.revision.open} />
        <OperationRow label="Kırmızı etiketli" value={summary.revision.redLabeled} emphasize />
        <OperationRow label="Sarı etiketli" value={summary.revision.yellowLabeled} />
      </OperationCard>

      <OperationCard
        title="Muayene"
        icon={Search}
        accent="text-primary"
        to="/inspections"
        cta="Muayene İşlemleri"
      >
        <OperationRow label="Bu ay muayenesi gelen" value={summary.inspection.dueThisMonth} />
        <OperationRow label="Takip süresi yaklaşan" value={summary.inspection.followUpSoon} emphasize />
        <OperationRow label="İncelenecek rapor" value={summary.inspection.reportsToReview} />
      </OperationCard>
    </div>
  );
}

/* ---------- stat tiles ----------
 * There's no historical snapshot table behind these counts, so only "Bu Ay
 * Tamamlanan" gets an honest month-over-month delta. The others show the
 * current total only — no invented sparkline/trend without data to back it.
 */

interface StatDef {
  key: string;
  label: string;
  value: number;
  icon: LucideIcon;
  delta?: number;
  positiveIsGood?: boolean;
  formatter?: (value: number) => string;
}

function StatTile({ stat }: { stat: StatDef }) {
  const hasDelta = stat.delta !== undefined && Number.isFinite(stat.delta);
  const isGood = hasDelta ? (stat.delta! >= 0 ? stat.positiveIsGood : !stat.positiveIsGood) : true;

  return (
    <Card>
      <CardContent className="flex items-center justify-between gap-3 p-5">
        <div className="space-y-1.5">
          <div className="text-sm text-muted-foreground">{stat.label}</div>
          <div className="text-2xl font-semibold tabular-nums tracking-tight text-foreground">
            {stat.formatter ? stat.formatter(stat.value) : formatNumber(stat.value)}
          </div>
          {hasDelta && (
            <div
              className={cn(
                "inline-flex items-center gap-0.5 text-xs font-medium tabular-nums",
                isGood ? "text-success-foreground" : "text-danger-foreground"
              )}
            >
              %{Math.abs(stat.delta!).toLocaleString("tr-TR", { maximumFractionDigits: 1 })}
              <span className="ml-1 font-normal text-muted-foreground">geçen aya göre</span>
            </div>
          )}
        </div>
        <stat.icon className="size-8 shrink-0 text-muted-foreground/40" strokeWidth={1.5} />
      </CardContent>
    </Card>
  );
}

function monthOverMonthDelta(current: number, previous: number): number | undefined {
  if (previous === 0) return current === 0 ? 0 : undefined;
  return ((current - previous) / previous) * 100;
}

function statsToTiles(stats: DashboardStats): StatDef[] {
  return [
    { key: "active_elevators", label: "Aktif Asansör", value: stats.activeElevators, icon: Building2 },
    { key: "open_work_orders", label: "Açık İş Emri", value: stats.openWorkOrders, icon: ClipboardList },
    {
      key: "completed_this_month",
      label: "Bu Ay Tamamlanan",
      value: stats.completedThisMonth,
      icon: CheckCircle2,
      delta: monthOverMonthDelta(stats.completedThisMonth, stats.completedLastMonth),
      positiveIsGood: true,
    },
    {
      key: "expiring_contracts",
      label: "30 Gün İçinde Bitecek Sözleşme",
      value: stats.expiringContracts,
      icon: FileWarning,
    },
    {
      key: "inventory_value",
      label: "Stok Değeri",
      value: stats.inventoryValue,
      icon: PackageCheck,
      formatter: formatCurrency,
    },
    {
      key: "monthly_consumption",
      label: "Bu Ay Parça Tüketimi",
      value: stats.monthlyConsumptionValue,
      icon: TrendingDown,
      formatter: formatCurrency,
    },
    {
      key: "low_stock",
      label: "Minimum Altı Parça",
      value: stats.lowStockMaterials,
      icon: PackageX,
      positiveIsGood: false,
    },
    {
      key: "stock_movements",
      label: "Stok Hareketi",
      value: stats.stockMovementCount,
      icon: ClipboardList,
    },
  ];
}

/* ---------- volume area chart (single series → no legend) ---------- */

function VolumeChart({ data }: { data: VolumePoint[] }) {
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
          <AreaChart data={data} margin={{ top: 4, right: 12, bottom: 0, left: 0 }}>
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

function InventoryMovementChart({ data }: { data: InventoryMovementPoint[] }) {
  const { theme } = useTheme();
  const palette = getChartPalette(theme);
  const inColor = palette.categorical[1];
  const outColor = palette.categorical[2];

  return (
    <Card className="lg:col-span-2">
      <CardHeader>
        <CardTitle>Stok Giriş / Çıkış Değeri</CardTitle>
        <p className="text-xs text-muted-foreground">Son 30 gün, hareket fiyatlarıyla</p>
      </CardHeader>
      <CardContent className="h-64 pl-0">
        <ResponsiveContainer width="100%" height="100%">
          <AreaChart data={data} margin={{ top: 4, right: 12, bottom: 0, left: 0 }}>
            <defs>
              <linearGradient id="stock-in-fill" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stopColor={inColor} stopOpacity={0.2} />
                <stop offset="100%" stopColor={inColor} stopOpacity={0.02} />
              </linearGradient>
              <linearGradient id="stock-out-fill" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stopColor={outColor} stopOpacity={0.18} />
                <stop offset="100%" stopColor={outColor} stopOpacity={0.02} />
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
              width={54}
              tick={{ fill: palette.axis, fontSize: 11 }}
              tickFormatter={(value: number) => `${Math.round(value / 1000)}K`}
              axisLine={false}
              tickLine={false}
              allowDecimals={false}
            />
            <Tooltip
              formatter={(value: number) => formatCurrency(value)}
              labelFormatter={(label: string) => trShortDay.format(new Date(label))}
              contentStyle={{
                background: palette.tooltipBg,
                color: palette.tooltipText,
                border: `1px solid ${palette.tooltipBorder}`,
                borderRadius: 6,
                fontSize: 12,
              }}
            />
            <Area
              type="monotone"
              dataKey="inValue"
              name="Giriş"
              stroke={inColor}
              strokeWidth={2}
              fill="url(#stock-in-fill)"
              isAnimationActive={false}
            />
            <Area
              type="monotone"
              dataKey="outValue"
              name="Çıkış"
              stroke={outColor}
              strokeWidth={2}
              fill="url(#stock-out-fill)"
              isAnimationActive={false}
            />
          </AreaChart>
        </ResponsiveContainer>
      </CardContent>
    </Card>
  );
}

/* ---------- type distribution donut (text legend is mandatory) ---------- */

function TypeDonut({ data }: { data: TypeDistributionPoint[] }) {
  const { theme } = useTheme();
  const palette = getChartPalette(theme);
  // slice colors follow the fixed categorical order defined in chartColors.ts
  const colorByType = new Map(workOrderTypeOrder.map((t, i) => [t, palette.categorical[i]]));
  const surface = theme === "dark" ? "#15151a" : "#ffffff";
  const total = data.reduce((sum, d) => sum + d.value, 0);

  return (
    <Card>
      <CardHeader>
        <CardTitle>İş Emri Türleri</CardTitle>
        <p className="text-xs text-muted-foreground">Son 90 gün dağılımı</p>
      </CardHeader>
      <CardContent className="flex items-center gap-4">
        {total === 0 ? (
          <div className="flex h-40 w-full items-center justify-center text-sm text-muted-foreground">
            Son 90 günde iş emri yok.
          </div>
        ) : (
          <>
            <div className="relative h-40 w-40 shrink-0">
              <ResponsiveContainer width="100%" height="100%">
                <PieChart>
                  <Pie
                    data={data}
                    dataKey="value"
                    nameKey="label"
                    innerRadius={52}
                    outerRadius={72}
                    paddingAngle={1}
                    stroke={surface}
                    strokeWidth={2}
                    isAnimationActive={false}
                  >
                    {data.map((d) => (
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
              {data.map((d) => (
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
          </>
        )}
      </CardContent>
    </Card>
  );
}

/* ---------- open work orders ---------- */

function OpenWorkOrders({ workOrders }: { workOrders: WorkOrder[] }) {
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
        {workOrders.length === 0 ? (
          <div className="px-2 py-6 text-center text-sm text-muted-foreground">Açık iş emri yok.</div>
        ) : (
          workOrders.map((wo) => {
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
          })
        )}
      </CardContent>
    </Card>
  );
}

/* ---------- recent activity ---------- */

const activityIcon: Record<ActivityItem["kind"], { icon: LucideIcon; className: string }> = {
  completed: { icon: CheckCircle2, className: "text-success" },
  created: { icon: PlusCircle, className: "text-muted-foreground" },
  assigned: { icon: UserCheck, className: "text-info" },
  progress: { icon: Play, className: "text-info" },
  cancelled: { icon: Ban, className: "text-danger" },
};

function RecentActivity({ activity }: { activity: ActivityItem[] }) {
  return (
    <Card>
      <CardHeader>
        <CardTitle>Son Aktivite</CardTitle>
      </CardHeader>
      <CardContent className="space-y-4 pt-0">
        {activity.length === 0 ? (
          <div className="text-sm text-muted-foreground">Henüz aktivite yok.</div>
        ) : (
          activity.map((item) => {
            const meta = activityIcon[item.kind];
            return (
              <div key={item.id} className="flex gap-3">
                <meta.icon className={cn("mt-0.5 size-4 shrink-0", meta.className)} strokeWidth={1.75} />
                <div className="min-w-0 space-y-0.5 text-sm">
                  <div className="text-foreground">{item.message}</div>
                  <div className="truncate text-xs text-muted-foreground">{item.target}</div>
                  <div className="text-xs text-muted-foreground/70">{timeAgo(item.at)}</div>
                </div>
              </div>
            );
          })
        )}
      </CardContent>
    </Card>
  );
}

function LowStockMaterials({ materials }: { materials: Material[] }) {
  return (
    <Card>
      <CardHeader className="flex-row items-center justify-between space-y-0">
        <CardTitle>Minimum Stok Altı</CardTitle>
        <Button variant="ghost" size="sm" asChild>
          <Link to="/inventory">
            Envanter
            <ArrowRight className="size-3.5" />
          </Link>
        </Button>
      </CardHeader>
      <CardContent className="space-y-1 p-3 pt-0">
        {materials.length === 0 ? (
          <div className="px-2 py-6 text-center text-sm text-muted-foreground">Kritik stok yok.</div>
        ) : (
          materials.map((material) => (
            <Link
              key={material.id}
              to="/inventory"
              className="flex items-center gap-3 rounded-md px-2 py-2.5 transition-colors hover:bg-muted"
            >
              <PackageX className="size-4 shrink-0 text-danger" />
              <div className="min-w-0 flex-1">
                <div className="truncate text-sm font-medium text-foreground">{material.name}</div>
                <div className="font-mono text-xs text-muted-foreground">{material.code}</div>
              </div>
              <span className="tabular-nums text-danger-foreground">
                {formatNumber(material.stock_on_hand)} / {formatNumber(material.min_stock_level)}
              </span>
            </Link>
          ))
        )}
      </CardContent>
    </Card>
  );
}

const movementLabel: Record<StockMovement["type"], string> = {
  purchase_in: "Mal kabul",
  work_order_out: "İş emri çıkış",
  work_order_return: "İş emri iade",
  transfer_in: "Transfer giriş",
  transfer_out: "Transfer çıkış",
  adjustment_in: "Sayım (+)",
  adjustment_out: "Sayım (−)",
};

function TopConsumedMaterials({ items }: { items: TopConsumedMaterial[] }) {
  return (
    <Card>
      <CardHeader className="flex-row items-center justify-between space-y-0">
        <CardTitle>En Çok Tüketilen Parçalar</CardTitle>
        <Button variant="ghost" size="sm" asChild>
          <Link to="/inventory">
            Detay
            <ArrowRight className="size-3.5" />
          </Link>
        </Button>
      </CardHeader>
      <CardContent className="space-y-1 p-3 pt-0">
        {items.length === 0 ? (
          <div className="px-2 py-6 text-center text-sm text-muted-foreground">Henüz tüketim yok.</div>
        ) : (
          items.map((item) => (
            <Link
              key={item.material.id}
              to="/inventory"
              className="flex items-center gap-3 rounded-md px-2 py-2.5 transition-colors hover:bg-muted"
            >
              <TrendingDown className="size-4 shrink-0 text-muted-foreground" />
              <div className="min-w-0 flex-1">
                <div className="truncate text-sm font-medium text-foreground">{item.material.name}</div>
                <div className="font-mono text-xs text-muted-foreground">{item.material.code}</div>
              </div>
              <div className="text-right">
                <div className="text-sm tabular-nums text-foreground">{formatCurrency(item.value)}</div>
                <div className="text-xs tabular-nums text-muted-foreground">
                  {formatNumber(item.quantity)} {item.material.unit}
                </div>
              </div>
            </Link>
          ))
        )}
      </CardContent>
    </Card>
  );
}

function RecentStockMovements({ movements }: { movements: StockMovement[] }) {
  return (
    <Card>
      <CardHeader className="flex-row items-center justify-between space-y-0">
        <CardTitle>Son Stok Hareketleri</CardTitle>
        <Button variant="ghost" size="sm" asChild>
          <Link to="/inventory">
            Defter
            <ArrowRight className="size-3.5" />
          </Link>
        </Button>
      </CardHeader>
      <CardContent className="space-y-1 p-3 pt-0">
        {movements.length === 0 ? (
          <div className="px-2 py-6 text-center text-sm text-muted-foreground">Henüz hareket yok.</div>
        ) : (
          movements.map((movement) => (
            <Link
              key={movement.id}
              to="/inventory"
              className="flex items-center gap-3 rounded-md px-2 py-2.5 transition-colors hover:bg-muted"
            >
              <PackageCheck
                className={cn(
                  "size-4 shrink-0",
                  movement.signed_quantity < 0 ? "text-danger" : "text-success"
                )}
              />
              <div className="min-w-0 flex-1">
                <div className="truncate text-sm font-medium text-foreground">{movement.material.name}</div>
                <div className="text-xs text-muted-foreground">
                  {movementLabel[movement.type]} · {movement.warehouse.name}
                </div>
              </div>
              <div className="text-right">
                <div className={cn("text-sm tabular-nums", movement.signed_quantity < 0 ? "text-danger-foreground" : "text-success")}>
                  {movement.signed_quantity > 0 ? "+" : ""}
                  {formatNumber(movement.signed_quantity)}
                </div>
                <div className="text-xs text-muted-foreground">{formatDateTime(movement.occurred_at)}</div>
              </div>
            </Link>
          ))
        )}
      </CardContent>
    </Card>
  );
}

/* ---------- page ---------- */

interface DashboardData {
  stats: DashboardStats;
  operations: OperationsSummary;
  volume: VolumePoint[];
  inventoryMovement: InventoryMovementPoint[];
  distribution: TypeDistributionPoint[];
  openWorkOrders: WorkOrder[];
  activity: ActivityItem[];
  lowStockMaterials: Material[];
  topConsumedMaterials: TopConsumedMaterial[];
  recentStockMovements: StockMovement[];
}

function useDashboardData() {
  const [data, setData] = React.useState<DashboardData | null>(null);
  const [isLoading, setIsLoading] = React.useState(true);
  const [error, setError] = React.useState<ApiError | null>(null);
  const [reloadKey, setReloadKey] = React.useState(0);

  React.useEffect(() => {
    let cancelled = false;
    setIsLoading(true);
    setError(null);

    Promise.all([
      fetchDashboardStats(),
      fetchOperationsSummary(),
      fetchWorkOrderVolume(),
      fetchInventoryMovementValue(),
      fetchWorkOrderTypeDistribution(),
      fetchTopOpenWorkOrders(),
      fetchRecentActivity(),
      fetchLowStockMaterials(),
      fetchTopConsumedMaterials(),
      fetchRecentStockMovements(),
    ])
      .then(([
        stats,
        operations,
        volume,
        inventoryMovement,
        distribution,
        openWorkOrders,
        activity,
        lowStockMaterials,
        topConsumedMaterials,
        recentStockMovements,
      ]) => {
        if (cancelled) return;
        setData({
          stats,
          operations,
          volume,
          inventoryMovement,
          distribution,
          openWorkOrders,
          activity,
          lowStockMaterials,
          topConsumedMaterials,
          recentStockMovements,
        });
      })
      .catch((err: unknown) => {
        if (cancelled) return;
        setError(
          err instanceof ApiError ? err : new ApiError("Beklenmeyen bir hata oluştu.", "UNKNOWN_ERROR", 0)
        );
      })
      .finally(() => {
        if (!cancelled) setIsLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, [reloadKey]);

  const reload = React.useCallback(() => setReloadKey((k) => k + 1), []);
  return { data, isLoading, error, reload };
}

export function DashboardPage() {
  const { data, isLoading, error, reload } = useDashboardData();

  return (
    <div className="space-y-6">
      <PageHeader title="Gösterge Paneli" description="Saha operasyonlarının anlık görünümü" />

      {error && <ListError message={error.message} onRetry={reload} />}

      {isLoading || !data ? (
        <div className="space-y-6">
          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            {Array.from({ length: 8 }, (_, i) => (
              <Skeleton key={i} className="h-24 w-full" />
            ))}
          </div>
          <div className="grid gap-4 lg:grid-cols-3">
            <Skeleton className="h-72 w-full lg:col-span-2" />
            <Skeleton className="h-72 w-full" />
          </div>
        </div>
      ) : (
        <>
          <OperationsCards summary={data.operations} />

          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            {statsToTiles(data.stats).map((stat) => (
              <StatTile key={stat.key} stat={stat} />
            ))}
          </div>

          <div className="grid gap-4 lg:grid-cols-3">
            <VolumeChart data={data.volume} />
            <TypeDonut data={data.distribution} />
          </div>

          <div className="grid gap-4 lg:grid-cols-3">
            <InventoryMovementChart data={data.inventoryMovement} />
            <TopConsumedMaterials items={data.topConsumedMaterials} />
          </div>

          <div className="grid gap-4 lg:grid-cols-2">
            <OpenWorkOrders workOrders={data.openWorkOrders} />
            <LowStockMaterials materials={data.lowStockMaterials} />
          </div>

          <div className="grid gap-4 lg:grid-cols-2">
            <RecentStockMovements movements={data.recentStockMovements} />
            <RecentActivity activity={data.activity} />
          </div>
        </>
      )}
    </div>
  );
}
