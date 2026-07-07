import * as React from "react";
import { Plus, Users as UsersIcon } from "lucide-react";
import { PageHeader } from "@/components/common/PageHeader";
import { Toolbar } from "@/components/common/Toolbar";
import { SearchInput } from "@/components/common/SearchInput";
import { ALL_VALUE, FilterSelect } from "@/components/common/FilterSelect";
import { DataTable, type Column } from "@/components/common/DataTable";
import { EmptyState } from "@/components/common/EmptyState";
import { ListError } from "@/components/common/ListError";
import { Pagination } from "@/components/common/Pagination";
import { StatusBadge } from "@/components/common/StatusBadge";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { initials } from "@/lib/format";
import { fetchUsers } from "@/api/resources";
import { useDebounced, useList } from "@/hooks/useList";
import type { User, UserRole } from "@/types";

const activeMeta = { label: "Aktif", variant: "success", dot: "bg-success" } as const;
const passiveMeta = { label: "Pasif", variant: "secondary", dot: "bg-muted-foreground" } as const;

const roleVariant: Record<UserRole, "default" | "secondary" | "outline"> = {
  "Super Admin": "default",
  "Company Owner": "default",
  Manager: "outline",
  Technician: "secondary",
  "Office Staff": "secondary",
  Customer: "outline",
};

const columns: Column<User>[] = [
  {
    key: "name",
    header: "Kullanıcı",
    sortAccessor: (u) => u.name,
    cell: (u) => (
      <div className="flex items-center gap-3">
        <Avatar className="size-8">
          <AvatarFallback className="bg-primary/10 text-xs font-semibold text-primary">
            {initials(u.name)}
          </AvatarFallback>
        </Avatar>
        <div className="min-w-0">
          <div className="truncate font-medium text-foreground">{u.name}</div>
          <div className="truncate text-xs text-muted-foreground">{u.email}</div>
        </div>
      </div>
    ),
  },
  {
    key: "phone",
    header: "Telefon",
    hideOnMobile: true,
    cell: (u) => <span className="tabular-nums text-muted-foreground">{u.phone ?? "—"}</span>,
  },
  {
    key: "role",
    header: "Rol",
    sortAccessor: (u) => u.role,
    cell: (u) => <Badge variant={roleVariant[u.role]}>{u.role}</Badge>,
  },
  {
    key: "status",
    header: "Durum",
    sortAccessor: (u) => (u.is_active ? 0 : 1),
    cell: (u) => <StatusBadge meta={u.is_active ? activeMeta : passiveMeta} />,
  },
];

const roleOptions = (Object.keys(roleVariant) as UserRole[]).map((r) => ({
  value: r,
  label: r,
}));

export function UsersPage() {
  const [query, setQuery] = React.useState("");
  const [role, setRole] = React.useState(ALL_VALUE);
  const [page, setPage] = React.useState(1);
  const debouncedQuery = useDebounced(query);

  React.useEffect(() => {
    setPage(1);
  }, [debouncedQuery, role]);

  const listFilter = React.useMemo<Record<string, string>>(() => {
    const filter: Record<string, string> = {};
    if (role !== ALL_VALUE) filter.role = role;
    return filter;
  }, [role]);

  const listParams = React.useMemo(
    () => ({
      page,
      perPage: 25,
      search: debouncedQuery,
      sort: "name",
      filter: listFilter,
    }),
    [page, debouncedQuery, listFilter]
  );
  const { items: users, pagination, isLoading, error, reload } = useList(fetchUsers, listParams);

  return (
    <div className="space-y-5">
      <PageHeader
        title="Ekip"
        description="Şirket kullanıcıları ve saha teknisyenleri"
        count={pagination?.total ?? users.length}
        actions={
          <Button>
            <Plus />
            Yeni Kullanıcı
          </Button>
        }
      />

      <Toolbar>
        <SearchInput value={query} onChange={setQuery} placeholder="İsim, e-posta veya telefon ara..." />
        <FilterSelect
          value={role}
          onChange={setRole}
          allLabel="Tüm Roller"
          options={roleOptions}
        />
      </Toolbar>

      {error && <ListError message={error.message} onRetry={reload} />}

      <DataTable
        columns={columns}
        data={users}
        getRowId={(u) => u.id}
        isLoading={isLoading}
        empty={
          <EmptyState
            icon={UsersIcon}
            title="Kullanıcı bulunamadı"
            description="Arama veya filtre kriterlerine uyan kullanıcı yok."
          />
        }
      />

      <Pagination pagination={pagination} onPageChange={setPage} />
    </div>
  );
}
