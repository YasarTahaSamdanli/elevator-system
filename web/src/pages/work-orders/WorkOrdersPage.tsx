import * as React from "react";
import { ClipboardList, Plus } from "lucide-react";
import { PageHeader } from "@/components/common/PageHeader";
import { Toolbar } from "@/components/common/Toolbar";
import { SearchInput } from "@/components/common/SearchInput";
import { ALL_VALUE, FilterSelect } from "@/components/common/FilterSelect";
import { DataTable, type Column } from "@/components/common/DataTable";
import { EmptyState } from "@/components/common/EmptyState";
import { ListError } from "@/components/common/ListError";
import { Pagination } from "@/components/common/Pagination";
import { StatusBadge } from "@/components/common/StatusBadge";
import { ConfirmDeleteDialog, useConfirmDelete } from "@/components/common/ConfirmDeleteDialog";
import { RowActionsMenu } from "@/components/common/RowActionsMenu";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import { Button } from "@/components/ui/button";
import {
  workOrderPriorityMeta,
  workOrderStatusMeta,
  workOrderTypeMeta,
} from "@/lib/status";
import { formatDateTime, initials } from "@/lib/format";
import { metaOptions } from "@/lib/forms";
import {
  createWorkOrder,
  deleteWorkOrder,
  fetchContracts,
  fetchMaterials,
  fetchUsers,
  fetchWorkOrders,
  updateWorkOrder,
} from "@/api/resources";
import { useDebounced, useList } from "@/hooks/useList";
import { useFormDialog } from "@/hooks/useFormDialog";
import type {
  WorkOrder,
  WorkOrderPriority,
  WorkOrderStatus,
  WorkOrderType,
} from "@/types";
import { typeIcons } from "./work-order-meta";
import { formItemsToInput, formToInput, type WorkOrderFormValues } from "./work-order-form";
import { WorkOrderFormDialog } from "./WorkOrderFormDialog";
import { WorkOrderSheet } from "./WorkOrderSheet";

const columns: Column<WorkOrder>[] = [
  {
    key: "number",
    header: "İş Emri",
    sortAccessor: (wo) => wo.work_order_number,
    cell: (wo) => {
      const TypeIcon = typeIcons[wo.type];
      return (
        <div className="min-w-0">
          <div className="font-mono text-xs text-foreground">{wo.work_order_number}</div>
          <div className="flex items-center gap-1 text-xs text-muted-foreground">
            <TypeIcon className="size-3" />
            {workOrderTypeMeta[wo.type].label}
          </div>
        </div>
      );
    },
  },
  {
    key: "location",
    header: "Bina / Asansör",
    sortAccessor: (wo) => `${wo.building_name} ${wo.elevator_name}`,
    cell: (wo) => (
      <div className="min-w-0">
        <div className="truncate text-foreground">{wo.building_name}</div>
        <div className="truncate text-xs text-muted-foreground">{wo.elevator_name}</div>
      </div>
    ),
  },
  {
    key: "priority",
    header: "Öncelik",
    hideOnMobile: true,
    sortAccessor: (wo) => wo.priority,
    cell: (wo) => <StatusBadge meta={workOrderPriorityMeta[wo.priority]} />,
  },
  {
    key: "status",
    header: "Durum",
    sortAccessor: (wo) => wo.status,
    cell: (wo) => <StatusBadge meta={workOrderStatusMeta[wo.status]} dot={false} />,
  },
  {
    key: "assignee",
    header: "Teknisyen",
    hideOnMobile: true,
    sortAccessor: (wo) => wo.assigned_user?.name ?? null,
    cell: (wo) =>
      wo.assigned_user ? (
        <div className="flex items-center gap-2">
          <Avatar className="size-6">
            <AvatarFallback className="bg-muted text-[10px] font-medium text-muted-foreground">
              {initials(wo.assigned_user.name)}
            </AvatarFallback>
          </Avatar>
          <span className="truncate">{wo.assigned_user.name}</span>
        </div>
      ) : (
        <span className="text-muted-foreground">Atanmadı</span>
      ),
  },
  {
    key: "scheduled",
    header: "Planlanan",
    hideOnMobile: true,
    sortAccessor: (wo) => wo.scheduled_at,
    cell: (wo) => (
      <span className="tabular-nums text-muted-foreground">{formatDateTime(wo.scheduled_at)}</span>
    ),
  },
];

const statusOptions = metaOptions<WorkOrderStatus>(workOrderStatusMeta);
const typeOptions = metaOptions<WorkOrderType>(workOrderTypeMeta);
const priorityOptions = metaOptions<WorkOrderPriority>(workOrderPriorityMeta);

export function WorkOrdersPage() {
  const [query, setQuery] = React.useState("");
  const [status, setStatus] = React.useState(ALL_VALUE);
  const [type, setType] = React.useState(ALL_VALUE);
  const [priority, setPriority] = React.useState(ALL_VALUE);
  const [page, setPage] = React.useState(1);
  const [selected, setSelected] = React.useState<WorkOrder | null>(null);
  const form = useFormDialog<WorkOrder>();
  const del = useConfirmDelete<WorkOrder>();
  const debouncedQuery = useDebounced(query);

  React.useEffect(() => {
    setPage(1);
    setSelected(null);
  }, [debouncedQuery, status, type, priority]);

  const listParams = React.useMemo(
    () => ({
      page,
      perPage: 25,
      search: debouncedQuery,
      sort: "-scheduled_at",
      filter: {
        ...(status === ALL_VALUE ? {} : { status }),
        ...(type === ALL_VALUE ? {} : { type }),
        ...(priority === ALL_VALUE ? {} : { priority }),
      },
    }),
    [page, debouncedQuery, status, type, priority]
  );
  const { items: workOrders, pagination, isLoading, error, reload } = useList(
    fetchWorkOrders,
    listParams
  );
  const contractParams = React.useMemo(
    () => ({ perPage: 100, sort: "-start_date" }),
    []
  );
  const userParams = React.useMemo(() => ({ perPage: 100, sort: "name" }), []);
  const materialParams = React.useMemo(
    () => ({ perPage: 100, sort: "code", filter: { is_active: "true" } }),
    []
  );
  const { items: contractOptions } = useList(fetchContracts, contractParams);
  const { items: userOptions } = useList(fetchUsers, userParams);
  const { items: materialOptions } = useList(fetchMaterials, materialParams);

  const openEdit = (workOrder: WorkOrder) => {
    setSelected(null);
    form.openEdit(workOrder);
  };

  const handleSubmit = (values: WorkOrderFormValues) =>
    form.submit(async () => {
      const input = formToInput(values);
      if (form.editing) {
        await updateWorkOrder(form.editing.id, input);
      } else {
        await createWorkOrder({ ...input, items: formItemsToInput(values.items) });
      }
      reload();
    });

  return (
    <div className="space-y-5">
      <PageHeader
        title="İş Emirleri"
        description="Bakım, arıza ve muayene işleri"
        count={pagination?.total ?? workOrders.length}
        actions={
          <Button onClick={form.openCreate}>
            <Plus />
            Yeni İş Emri
          </Button>
        }
      />

      <Toolbar>
        <SearchInput
          value={query}
          onChange={setQuery}
          placeholder="İş emri no, bina veya teknisyen ara..."
        />
        <FilterSelect
          value={status}
          onChange={setStatus}
          allLabel="Tüm Durumlar"
          options={statusOptions}
        />
        <FilterSelect value={type} onChange={setType} allLabel="Tüm Türler" options={typeOptions} />
        <FilterSelect
          value={priority}
          onChange={setPriority}
          allLabel="Tüm Öncelikler"
          options={priorityOptions}
        />
      </Toolbar>

      {error && <ListError message={error.message} onRetry={reload} />}

      <DataTable
        columns={columns}
        data={workOrders}
        getRowId={(wo) => wo.id}
        onRowClick={setSelected}
        isLoading={isLoading}
        rowActions={(workOrder) => (
          <RowActionsMenu
            ariaLabel="İş emri işlemleri"
            onEdit={() => openEdit(workOrder)}
            onDelete={() => del.request(workOrder)}
          />
        )}
        empty={
          <EmptyState
            icon={ClipboardList}
            title="İş emri bulunamadı"
            description="Arama veya filtre kriterlerine uyan iş emri yok."
          />
        }
      />

      <Pagination pagination={pagination} onPageChange={setPage} />

      <WorkOrderSheet
        workOrder={selected}
        onClose={() => setSelected(null)}
        onEdit={openEdit}
        onDelete={(workOrder) => {
          setSelected(null);
          del.request(workOrder);
        }}
        onChanged={reload}
      />

      <WorkOrderFormDialog
        open={form.open}
        workOrder={form.editing}
        contracts={contractOptions}
        materials={materialOptions}
        users={userOptions}
        errors={form.errors}
        formError={form.formError}
        isSubmitting={form.isSubmitting}
        onOpenChange={form.onOpenChange}
        onSubmit={handleSubmit}
      />

      <ConfirmDeleteDialog
        open={!!del.target}
        title="İş Emrini Sil"
        description={`${
          del.target ? `${del.target.work_order_number} numaralı iş emri silinecek.` : ""
        } Bu işlem kaydı liste görünümünden kaldırır.`}
        error={del.error}
        isDeleting={del.isDeleting}
        onClose={del.close}
        onConfirm={() =>
          void del.confirm(async () => {
            if (!del.target) return;
            await deleteWorkOrder(del.target.id);
            reload();
          })
        }
      />
    </div>
  );
}
