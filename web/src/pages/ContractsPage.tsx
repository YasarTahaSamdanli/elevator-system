import * as React from "react";
import { FileText, Plus } from "lucide-react";
import { PageHeader } from "@/components/common/PageHeader";
import { Toolbar } from "@/components/common/Toolbar";
import { SearchInput } from "@/components/common/SearchInput";
import { ALL_VALUE, FilterSelect } from "@/components/common/FilterSelect";
import { DataTable, type Column } from "@/components/common/DataTable";
import { EmptyState } from "@/components/common/EmptyState";
import { StatusBadge } from "@/components/common/StatusBadge";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { contractStatusMeta } from "@/lib/status";
import { daysUntil, formatCurrency, formatDate } from "@/lib/format";
import { contracts } from "@/mock";
import type { ContractStatus, ServiceContract } from "@/types";

const columns: Column<ServiceContract>[] = [
  {
    key: "number",
    header: "Sözleşme",
    sortAccessor: (c) => c.contract_number,
    cell: (c) => (
      <div className="min-w-0">
        <div className="font-mono text-xs text-foreground">{c.contract_number ?? "—"}</div>
        <div className="truncate text-xs text-muted-foreground">
          {c.building_name} · {c.elevator_name}
        </div>
      </div>
    ),
  },
  {
    key: "start",
    header: "Başlangıç",
    hideOnMobile: true,
    sortAccessor: (c) => c.start_date,
    cell: (c) => <span className="tabular-nums text-muted-foreground">{formatDate(c.start_date)}</span>,
  },
  {
    key: "end",
    header: "Bitiş",
    sortAccessor: (c) => c.end_date,
    cell: (c) => {
      const days = daysUntil(c.end_date);
      const expiringSoon = c.status === "active" && days >= 0 && days <= 30;
      return (
        <div className="flex items-center gap-2">
          <span className="tabular-nums">{formatDate(c.end_date)}</span>
          {expiringSoon && <Badge variant="warning">{days} gün kaldı</Badge>}
        </div>
      );
    },
  },
  {
    key: "fee",
    header: "Aylık Ücret",
    align: "right",
    hideOnMobile: true,
    sortAccessor: (c) => c.monthly_fee,
    cell: (c) => <span className="tabular-nums">{formatCurrency(c.monthly_fee)}</span>,
  },
  {
    key: "status",
    header: "Durum",
    sortAccessor: (c) => c.status,
    cell: (c) => <StatusBadge meta={contractStatusMeta[c.status]} />,
  },
];

const statusOptions = (Object.keys(contractStatusMeta) as ContractStatus[]).map((s) => ({
  value: s,
  label: contractStatusMeta[s].label,
}));

export function ContractsPage() {
  const [query, setQuery] = React.useState("");
  const [status, setStatus] = React.useState(ALL_VALUE);

  const filtered = contracts.filter((c) => {
    if (status !== ALL_VALUE && c.status !== status) return false;
    if (!query) return true;
    const q = query.toLocaleLowerCase("tr-TR");
    return [c.contract_number, c.building_name, c.elevator_name]
      .filter(Boolean)
      .some((v) => v!.toLocaleLowerCase("tr-TR").includes(q));
  });

  return (
    <div className="space-y-5">
      <PageHeader
        title="Sözleşmeler"
        description="Asansör bakım sözleşmeleri ve yenileme takibi"
        count={contracts.length}
        actions={
          <Button>
            <Plus />
            Yeni Sözleşme
          </Button>
        }
      />

      <Toolbar>
        <SearchInput
          value={query}
          onChange={setQuery}
          placeholder="Sözleşme no veya bina ara..."
        />
        <FilterSelect
          value={status}
          onChange={setStatus}
          allLabel="Tüm Durumlar"
          options={statusOptions}
        />
      </Toolbar>

      <DataTable
        columns={columns}
        data={filtered}
        getRowId={(c) => c.id}
        empty={
          <EmptyState
            icon={FileText}
            title="Sözleşme bulunamadı"
            description="Arama veya filtre kriterlerine uyan sözleşme yok."
          />
        }
      />
    </div>
  );
}
