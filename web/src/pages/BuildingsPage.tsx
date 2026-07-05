import * as React from "react";
import { Building2, MapPin, Plus } from "lucide-react";
import { PageHeader } from "@/components/common/PageHeader";
import { Toolbar } from "@/components/common/Toolbar";
import { SearchInput } from "@/components/common/SearchInput";
import { ALL_VALUE, FilterSelect } from "@/components/common/FilterSelect";
import { DataTable, type Column } from "@/components/common/DataTable";
import { EmptyState } from "@/components/common/EmptyState";
import { StatusBadge } from "@/components/common/StatusBadge";
import { Button } from "@/components/ui/button";
import { buildings } from "@/mock";
import type { Building } from "@/types";

const activeMeta = { label: "Aktif", variant: "success", dot: "bg-success" } as const;
const passiveMeta = { label: "Pasif", variant: "secondary", dot: "bg-muted-foreground" } as const;

const columns: Column<Building>[] = [
  {
    key: "name",
    header: "Bina",
    sortAccessor: (b) => b.name,
    cell: (b) => (
      <div className="min-w-0">
        <div className="font-medium text-foreground">{b.name}</div>
        {b.code && <div className="font-mono text-xs text-muted-foreground">{b.code}</div>}
      </div>
    ),
  },
  {
    key: "location",
    header: "Konum",
    sortAccessor: (b) => `${b.city} ${b.district}`,
    cell: (b) => (
      <div className="flex items-center gap-1.5 text-muted-foreground">
        <MapPin className="size-3.5 shrink-0" />
        {b.district} / {b.city}
      </div>
    ),
  },
  {
    key: "manager",
    header: "Bina Yöneticisi",
    hideOnMobile: true,
    cell: (b) =>
      b.manager_name ? (
        <div className="min-w-0">
          <div className="text-foreground">{b.manager_name}</div>
          <div className="text-xs text-muted-foreground">{b.manager_phone ?? "—"}</div>
        </div>
      ) : (
        <span className="text-muted-foreground">—</span>
      ),
  },
  {
    key: "elevators",
    header: "Asansör",
    align: "right",
    sortAccessor: (b) => b.elevator_count,
    cell: (b) => <span className="tabular-nums">{b.elevator_count}</span>,
  },
  {
    key: "status",
    header: "Durum",
    sortAccessor: (b) => (b.is_active ? 0 : 1),
    cell: (b) => <StatusBadge meta={b.is_active ? activeMeta : passiveMeta} />,
  },
];

export function BuildingsPage() {
  const [query, setQuery] = React.useState("");
  const [city, setCity] = React.useState(ALL_VALUE);

  const cities = [...new Set(buildings.map((b) => b.city))].sort();

  const filtered = buildings.filter((b) => {
    if (city !== ALL_VALUE && b.city !== city) return false;
    if (!query) return true;
    const q = query.toLocaleLowerCase("tr-TR");
    return [b.name, b.code, b.address, b.city, b.district, b.manager_name]
      .filter(Boolean)
      .some((v) => v!.toLocaleLowerCase("tr-TR").includes(q));
  });

  return (
    <div className="space-y-5">
      <PageHeader
        title="Binalar"
        description="Hizmet verilen binalar ve sorumluları"
        count={buildings.length}
        actions={
          <Button>
            <Plus />
            Yeni Bina
          </Button>
        }
      />

      <Toolbar>
        <SearchInput value={query} onChange={setQuery} placeholder="Bina, kod veya yönetici ara..." />
        <FilterSelect
          value={city}
          onChange={setCity}
          allLabel="Tüm Şehirler"
          options={cities.map((c) => ({ value: c, label: c }))}
        />
      </Toolbar>

      <DataTable
        columns={columns}
        data={filtered}
        getRowId={(b) => b.id}
        empty={
          <EmptyState
            icon={Building2}
            title="Bina bulunamadı"
            description="Arama veya filtre kriterlerine uyan bina yok."
          />
        }
      />
    </div>
  );
}
