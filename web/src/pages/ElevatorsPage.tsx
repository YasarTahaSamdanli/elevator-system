import * as React from "react";
import { ArrowUpDown, Plus, QrCode } from "lucide-react";
import { PageHeader } from "@/components/common/PageHeader";
import { Toolbar } from "@/components/common/Toolbar";
import { SearchInput } from "@/components/common/SearchInput";
import { ALL_VALUE, FilterSelect } from "@/components/common/FilterSelect";
import { DataTable, type Column } from "@/components/common/DataTable";
import { EmptyState } from "@/components/common/EmptyState";
import { ListError } from "@/components/common/ListError";
import { Pagination } from "@/components/common/Pagination";
import { StatusBadge } from "@/components/common/StatusBadge";
import { Button } from "@/components/ui/button";
import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip";
import { elevatorStatusMeta } from "@/lib/status";
import { formatNumber } from "@/lib/format";
import { fetchBuildings, fetchElevators } from "@/api/resources";
import { useDebounced, useList } from "@/hooks/useList";
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
  const [page, setPage] = React.useState(1);
  const debouncedQuery = useDebounced(query);

  React.useEffect(() => {
    setPage(1);
  }, [debouncedQuery, status, building]);

  const listParams = React.useMemo(
    () => ({
      page,
      perPage: 25,
      search: debouncedQuery,
      sort: "name",
      filter: {
        ...(status === ALL_VALUE ? {} : { status }),
        ...(building === ALL_VALUE ? {} : { building_uuid: building }),
      },
    }),
    [page, debouncedQuery, status, building]
  );
  const buildingParams = React.useMemo(() => ({ perPage: 100, sort: "name" }), []);
  const { items: elevators, pagination, isLoading, error, reload } = useList(
    fetchElevators,
    listParams
  );
  const { items: buildingOptions } = useList(fetchBuildings, buildingParams);

  return (
    <div className="space-y-5">
      <PageHeader
        title="Asansörler"
        description="Bakım sözleşmeli asansör envanteri"
        count={pagination?.total ?? elevators.length}
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
          options={buildingOptions.map((b) => ({ value: b.id, label: b.name }))}
        />
      </Toolbar>

      {error && <ListError message={error.message} onRetry={reload} />}

      <DataTable
        columns={columns}
        data={elevators}
        getRowId={(e) => e.id}
        isLoading={isLoading}
        empty={
          <EmptyState
            icon={ArrowUpDown}
            title="Asansör bulunamadı"
            description="Arama veya filtre kriterlerine uyan asansör yok."
          />
        }
      />

      <Pagination pagination={pagination} onPageChange={setPage} />
    </div>
  );
}
