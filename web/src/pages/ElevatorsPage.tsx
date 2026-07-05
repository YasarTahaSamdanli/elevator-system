import * as React from "react";
import { ArrowUpDown, Plus, QrCode } from "lucide-react";
import { PageHeader } from "@/components/common/PageHeader";
import { Toolbar } from "@/components/common/Toolbar";
import { SearchInput } from "@/components/common/SearchInput";
import { ALL_VALUE, FilterSelect } from "@/components/common/FilterSelect";
import { DataTable, type Column } from "@/components/common/DataTable";
import { EmptyState } from "@/components/common/EmptyState";
import { StatusBadge } from "@/components/common/StatusBadge";
import { Button } from "@/components/ui/button";
import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip";
import { elevatorStatusMeta } from "@/lib/status";
import { formatNumber } from "@/lib/format";
import { buildings, elevators } from "@/mock";
import type { Elevator, ElevatorStatus } from "@/types";

const columns: Column<Elevator>[] = [
  {
    key: "name",
    header: "Asansör",
    sortAccessor: (e) => e.name ?? e.serial_number,
    cell: (e) => (
      <div className="min-w-0">
        <div className="flex items-center gap-1.5 font-medium text-foreground">
          {e.name ?? e.serial_number}
          <Tooltip>
            <TooltipTrigger asChild>
              <QrCode className="size-3.5 shrink-0 text-muted-foreground" />
            </TooltipTrigger>
            <TooltipContent className="font-mono text-xs">{e.qr_identifier}</TooltipContent>
          </Tooltip>
        </div>
        <div className="text-xs text-muted-foreground">{e.building_name}</div>
      </div>
    ),
  },
  {
    key: "serial",
    header: "Seri No",
    hideOnMobile: true,
    sortAccessor: (e) => e.serial_number,
    cell: (e) => <span className="font-mono text-xs">{e.serial_number}</span>,
  },
  {
    key: "make",
    header: "Üretici / Model",
    hideOnMobile: true,
    cell: (e) => (
      <div className="min-w-0">
        <div className="text-foreground">{e.manufacturer ?? "—"}</div>
        <div className="text-xs text-muted-foreground">{e.model ?? ""}</div>
      </div>
    ),
  },
  {
    key: "capacity",
    header: "Kapasite",
    align: "right",
    hideOnMobile: true,
    sortAccessor: (e) => e.capacity_kg,
    cell: (e) => (
      <span className="tabular-nums text-muted-foreground">
        {e.capacity_kg ? `${formatNumber(e.capacity_kg)} kg` : "—"}
        {e.person_capacity ? ` · ${e.person_capacity} kişi` : ""}
      </span>
    ),
  },
  {
    key: "stops",
    header: "Durak",
    align: "right",
    hideOnMobile: true,
    sortAccessor: (e) => e.stop_count,
    cell: (e) => <span className="tabular-nums">{e.stop_count ?? "—"}</span>,
  },
  {
    key: "status",
    header: "Durum",
    sortAccessor: (e) => e.status,
    cell: (e) => <StatusBadge meta={elevatorStatusMeta[e.status]} />,
  },
];

const statusOptions = (Object.keys(elevatorStatusMeta) as ElevatorStatus[]).map((s) => ({
  value: s,
  label: elevatorStatusMeta[s].label,
}));

export function ElevatorsPage() {
  const [query, setQuery] = React.useState("");
  const [status, setStatus] = React.useState(ALL_VALUE);
  const [building, setBuilding] = React.useState(ALL_VALUE);

  const filtered = elevators.filter((e) => {
    if (status !== ALL_VALUE && e.status !== status) return false;
    if (building !== ALL_VALUE && e.building_id !== building) return false;
    if (!query) return true;
    const q = query.toLocaleLowerCase("tr-TR");
    return [e.name, e.serial_number, e.qr_identifier, e.building_name, e.manufacturer, e.model]
      .filter(Boolean)
      .some((v) => v!.toLocaleLowerCase("tr-TR").includes(q));
  });

  return (
    <div className="space-y-5">
      <PageHeader
        title="Asansörler"
        description="Bakım sözleşmeli asansör envanteri"
        count={elevators.length}
        actions={
          <Button>
            <Plus />
            Yeni Asansör
          </Button>
        }
      />

      <Toolbar>
        <SearchInput
          value={query}
          onChange={setQuery}
          placeholder="Seri no, QR veya bina ara..."
        />
        <FilterSelect
          value={status}
          onChange={setStatus}
          allLabel="Tüm Durumlar"
          options={statusOptions}
        />
        <FilterSelect
          value={building}
          onChange={setBuilding}
          allLabel="Tüm Binalar"
          options={buildings.map((b) => ({ value: b.id, label: b.name }))}
        />
      </Toolbar>

      <DataTable
        columns={columns}
        data={filtered}
        getRowId={(e) => e.id}
        empty={
          <EmptyState
            icon={ArrowUpDown}
            title="Asansör bulunamadı"
            description="Arama veya filtre kriterlerine uyan asansör yok."
          />
        }
      />
    </div>
  );
}
