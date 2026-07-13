import * as React from "react";
import { HandCoins, Loader2, Plus, Settings2, Wallet } from "lucide-react";
import { PageHeader } from "@/components/common/PageHeader";
import { Toolbar } from "@/components/common/Toolbar";
import { SearchInput } from "@/components/common/SearchInput";
import { ALL_VALUE, FilterSelect } from "@/components/common/FilterSelect";
import { DataTable, type Column } from "@/components/common/DataTable";
import { EmptyState } from "@/components/common/EmptyState";
import { ListError } from "@/components/common/ListError";
import { Pagination } from "@/components/common/Pagination";
import { StatusBadge } from "@/components/common/StatusBadge";
import { Field, FormErrorBanner } from "@/components/common/Field";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { accountTransactionTypeMeta } from "@/lib/status";
import { formatCurrency, formatDate } from "@/lib/format";
import { fieldError, metaOptions, todayIso } from "@/lib/forms";
import {
  createAccountTransaction,
  createPaymentMethod,
  fetchAccountSummary,
  fetchAccountTransactions,
  fetchBuildings,
  fetchPaymentMethods,
  updatePaymentMethod,
  type AccountTransactionInput,
} from "@/api/resources";
import { useDebounced, useList } from "@/hooks/useList";
import { ApiError } from "@/lib/api";
import type {
  AccountSummary,
  AccountTransaction,
  AccountTransactionType,
  Building,
  PaymentMethod,
} from "@/types";

/** Fatura kesilmiyor; KDV yalnızca gösterim amaçlı şirket geneli tek oran. */
const VAT_RATE = 0.2;

const typeOptions = metaOptions<AccountTransactionType>(accountTransactionTypeMeta);

const columns: Column<AccountTransaction>[] = [
  {
    key: "occurred_at",
    header: "Tarih",
    sortAccessor: (t) => t.occurred_at,
    cell: (t) => <span className="tabular-nums">{formatDate(t.occurred_at)}</span>,
  },
  {
    key: "building",
    header: "Bina / Asansör",
    cell: (t) => (
      <div className="min-w-0">
        <div className="font-medium text-foreground">{t.building.name}</div>
        {t.elevator && (
          <div className="text-xs text-muted-foreground">
            {t.elevator.name ?? t.elevator.serial_number}
          </div>
        )}
      </div>
    ),
  },
  {
    key: "type",
    header: "Tip",
    sortAccessor: (t) => t.type,
    cell: (t) => <StatusBadge meta={accountTransactionTypeMeta[t.type]} />,
  },
  {
    key: "description",
    header: "Açıklama",
    hideOnMobile: true,
    cell: (t) => (
      <div className="min-w-0 max-w-xs">
        <div className="truncate text-muted-foreground">{t.description ?? "—"}</div>
        <div className="truncate text-xs text-muted-foreground/80">
          {[
            t.work_order?.work_order_number,
            t.payment_method?.name,
            t.payer_name,
            t.collected_by ? `Tahsil: ${t.collected_by.name}` : null,
          ]
            .filter(Boolean)
            .join(" · ")}
        </div>
      </div>
    ),
  },
  {
    key: "amount",
    header: "Tutar",
    align: "right",
    sortAccessor: (t) => t.signed_amount,
    cell: (t) => (
      <span
        className={
          t.signed_amount < 0
            ? "font-medium tabular-nums text-success"
            : "tabular-nums text-foreground"
        }
      >
        {formatCurrency(t.signed_amount)}
      </span>
    ),
  },
];

function SummaryTile({ label, value, highlight }: { label: string; value: string; highlight?: boolean }) {
  return (
    <Card>
      <CardContent className="p-4">
        <div className="text-xs font-medium text-muted-foreground">{label}</div>
        <div
          className={
            highlight
              ? "mt-1 text-lg font-semibold tabular-nums text-foreground"
              : "mt-1 text-lg font-medium tabular-nums text-foreground"
          }
        >
          {value}
        </div>
      </CardContent>
    </Card>
  );
}

interface TransactionFormValues {
  building_uuid: string;
  type: AccountTransactionType;
  amount: string;
  occurred_at: string;
  payment_method_uuid: string;
  payer_name: string;
  description: string;
}

function TransactionDialog({
  open,
  mode,
  buildings,
  paymentMethods,
  defaultBuilding,
  errors,
  formError,
  isSubmitting,
  onOpenChange,
  onSubmit,
}: {
  open: boolean;
  /** payment: tahsilat girişi; manual: devir/düzeltme/serbest borç kaydı */
  mode: "payment" | "manual";
  buildings: Building[];
  paymentMethods: PaymentMethod[];
  defaultBuilding: string;
  errors: Record<string, string[]>;
  formError: string | null;
  isSubmitting: boolean;
  onOpenChange: (open: boolean) => void;
  onSubmit: (values: TransactionFormValues) => Promise<void>;
}) {
  const emptyValues = React.useCallback(
    (): TransactionFormValues => ({
      building_uuid: defaultBuilding,
      type: mode === "payment" ? "payment" : "opening_balance",
      amount: "",
      occurred_at: todayIso(),
      payment_method_uuid: "",
      payer_name: "",
      description: "",
    }),
    [defaultBuilding, mode]
  );
  const [values, setValues] = React.useState<TransactionFormValues>(emptyValues);

  React.useEffect(() => {
    if (open) setValues(emptyValues());
  }, [open, emptyValues]);

  const setValue = <K extends keyof TransactionFormValues>(
    field: K,
    value: TransactionFormValues[K]
  ) => setValues((prev) => ({ ...prev, [field]: value }));

  const manualTypes = typeOptions.filter((t) => t.value !== "payment");

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent onInteractOutside={(event) => event.preventDefault()}>
        <DialogHeader>
          <DialogTitle>{mode === "payment" ? "Tahsilat Al" : "Manuel Cari Kayıt"}</DialogTitle>
          <DialogDescription>
            {mode === "payment"
              ? "Binadan alınan ödemeyi deftere işle. Tahsil eden olarak oturumdaki kullanıcı kaydedilir."
              : "Devir, düzeltme veya serbest borçlandırma kaydı. Defter değiştirilemez — hata ters kayıtla düzeltilir."}
          </DialogDescription>
        </DialogHeader>
        <form
          noValidate
          className="space-y-4"
          onSubmit={(event) => {
            event.preventDefault();
            void onSubmit(values);
          }}
        >
          <FormErrorBanner message={formError} />

          <div className="grid gap-4 sm:grid-cols-2">
            <Field label="Bina" error={fieldError(errors, "building_uuid")}>
              <Select
                value={values.building_uuid || undefined}
                onValueChange={(value) => setValue("building_uuid", value)}
              >
                <SelectTrigger>
                  <SelectValue placeholder="Bina seç" />
                </SelectTrigger>
                <SelectContent>
                  {buildings.map((building) => (
                    <SelectItem key={building.id} value={building.id}>
                      {building.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </Field>
            {mode === "manual" ? (
              <Field label="Kayıt Tipi" error={fieldError(errors, "type")}>
                <Select
                  value={values.type}
                  onValueChange={(value) => setValue("type", value as AccountTransactionType)}
                >
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {manualTypes.map((option) => (
                      <SelectItem key={option.value} value={option.value}>
                        {option.label}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </Field>
            ) : (
              <Field label="Ödeme Kanalı" error={fieldError(errors, "payment_method_uuid")}>
                <Select
                  value={values.payment_method_uuid || undefined}
                  onValueChange={(value) => setValue("payment_method_uuid", value)}
                >
                  <SelectTrigger>
                    <SelectValue
                      placeholder={paymentMethods.length === 0 ? "Önce kanal tanımla" : "Kanal seç"}
                    />
                  </SelectTrigger>
                  <SelectContent>
                    {paymentMethods
                      .filter((method) => method.is_active)
                      .map((method) => (
                        <SelectItem key={method.id} value={method.id}>
                          {method.name}
                        </SelectItem>
                      ))}
                  </SelectContent>
                </Select>
              </Field>
            )}
          </div>

          <div className="grid gap-4 sm:grid-cols-2">
            <Field label="Tutar (₺)" error={fieldError(errors, "amount")}>
              <Input
                type="number"
                min="0.01"
                step="0.01"
                value={values.amount}
                onChange={(event) => setValue("amount", event.target.value)}
                required
              />
            </Field>
            <Field label="Tarih" error={fieldError(errors, "occurred_at")}>
              <Input
                type="date"
                value={values.occurred_at}
                onChange={(event) => setValue("occurred_at", event.target.value)}
                required
              />
            </Field>
          </div>

          {mode === "payment" && (
            <Field label="Ödeme Yapan" error={fieldError(errors, "payer_name")}>
              <Input
                value={values.payer_name}
                onChange={(event) => setValue("payer_name", event.target.value)}
                placeholder="Örn: bina yöneticisi adı"
              />
            </Field>
          )}

          <Field label="Açıklama" error={fieldError(errors, "description")}>
            <Input
              value={values.description}
              onChange={(event) => setValue("description", event.target.value)}
            />
          </Field>

          <DialogFooter>
            <Button
              type="button"
              variant="outline"
              disabled={isSubmitting}
              onClick={() => onOpenChange(false)}
            >
              Vazgeç
            </Button>
            <Button type="submit" disabled={isSubmitting}>
              {isSubmitting && <Loader2 className="animate-spin" />}
              Kaydet
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

function PaymentMethodsDialog({
  open,
  methods,
  onOpenChange,
  onChanged,
}: {
  open: boolean;
  methods: PaymentMethod[];
  onOpenChange: (open: boolean) => void;
  onChanged: () => void;
}) {
  const [name, setName] = React.useState("");
  const [error, setError] = React.useState<string | null>(null);
  const [isBusy, setBusy] = React.useState(false);

  const addMethod = async () => {
    if (!name.trim()) return;
    setBusy(true);
    setError(null);
    try {
      await createPaymentMethod(name.trim());
      setName("");
      onChanged();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Beklenmeyen bir hata oluştu.");
    } finally {
      setBusy(false);
    }
  };

  const toggleMethod = async (method: PaymentMethod) => {
    setBusy(true);
    setError(null);
    try {
      await updatePaymentMethod(method.id, { is_active: !method.is_active });
      onChanged();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Beklenmeyen bir hata oluştu.");
    } finally {
      setBusy(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Ödeme Kanalları</DialogTitle>
          <DialogDescription>
            Tahsilatların işlendiği hesaplar (örn. Banka GR, Banka Resmi, Elden). Kullanılmış kanal
            silinemez, pasife alınır.
          </DialogDescription>
        </DialogHeader>
        <FormErrorBanner message={error} />
        <div className="space-y-2">
          {methods.length === 0 && (
            <p className="text-sm text-muted-foreground">Henüz kanal tanımlanmadı.</p>
          )}
          {methods.map((method) => (
            <div
              key={method.id}
              className="flex items-center justify-between rounded-md border border-border px-3 py-2 text-sm"
            >
              <span className={method.is_active ? "" : "text-muted-foreground line-through"}>
                {method.name}
              </span>
              <Button
                variant="outline"
                size="sm"
                disabled={isBusy}
                onClick={() => void toggleMethod(method)}
              >
                {method.is_active ? "Pasife Al" : "Aktifleştir"}
              </Button>
            </div>
          ))}
        </div>
        <div className="flex items-center gap-2">
          <Input
            value={name}
            onChange={(event) => setName(event.target.value)}
            placeholder="Yeni kanal adı"
            onKeyDown={(event) => {
              if (event.key === "Enter") {
                event.preventDefault();
                void addMethod();
              }
            }}
          />
          <Button disabled={isBusy || !name.trim()} onClick={() => void addMethod()}>
            <Plus />
            Ekle
          </Button>
        </div>
      </DialogContent>
    </Dialog>
  );
}

export function LedgerPage() {
  const [query, setQuery] = React.useState("");
  const [building, setBuilding] = React.useState(ALL_VALUE);
  const [type, setType] = React.useState(ALL_VALUE);
  const [page, setPage] = React.useState(1);
  const [summary, setSummary] = React.useState<AccountSummary | null>(null);
  const [dialog, setDialog] = React.useState<"payment" | "manual" | "methods" | null>(null);
  const [formErrors, setFormErrors] = React.useState<Record<string, string[]>>({});
  const [formError, setFormError] = React.useState<string | null>(null);
  const [isSubmitting, setSubmitting] = React.useState(false);
  const [refreshKey, setRefreshKey] = React.useState(0);
  const debouncedQuery = useDebounced(query);

  React.useEffect(() => {
    setPage(1);
  }, [debouncedQuery, building, type]);

  const listParams = React.useMemo(
    () => ({
      page,
      perPage: 25,
      search: debouncedQuery,
      sort: "-occurred_at",
      filter: {
        ...(building === ALL_VALUE ? {} : { building_uuid: building }),
        ...(type === ALL_VALUE ? {} : { type }),
      },
    }),
    [page, debouncedQuery, building, type]
  );
  const buildingParams = React.useMemo(() => ({ perPage: 100, sort: "name" }), []);
  const methodParams = React.useMemo(() => ({ perPage: 100, sort: "name" }), []);
  const { items: transactions, pagination, isLoading, error, reload } = useList(
    fetchAccountTransactions,
    listParams
  );
  const { items: buildingOptions } = useList(fetchBuildings, buildingParams);
  const { items: paymentMethods, reload: reloadMethods } = useList(
    fetchPaymentMethods,
    methodParams
  );

  React.useEffect(() => {
    let cancelled = false;
    fetchAccountSummary(building === ALL_VALUE ? {} : { building_uuid: building })
      .then((result) => {
        if (!cancelled) setSummary(result);
      })
      .catch(() => {
        if (!cancelled) setSummary(null);
      });
    return () => {
      cancelled = true;
    };
  }, [building, refreshKey]);

  const refreshAll = () => {
    reload();
    setRefreshKey((k) => k + 1);
  };

  const openDialog = (target: "payment" | "manual" | "methods") => {
    setFormErrors({});
    setFormError(null);
    setDialog(target);
  };

  const handleSubmit = async (values: TransactionFormValues) => {
    setSubmitting(true);
    setFormErrors({});
    setFormError(null);

    try {
      const input: AccountTransactionInput = {
        building_uuid: values.building_uuid,
        type: values.type,
        amount: Number(values.amount),
        occurred_at: values.occurred_at,
        payment_method_uuid: values.payment_method_uuid || null,
        payer_name: values.payer_name.trim() || null,
        description: values.description.trim() || null,
      };
      await createAccountTransaction(input);
      setDialog(null);
      refreshAll();
    } catch (err) {
      if (err instanceof ApiError) {
        setFormErrors(err.details);
        setFormError(err.message);
      } else {
        setFormError("Beklenmeyen bir hata oluştu.");
      }
    } finally {
      setSubmitting(false);
    }
  };

  const balance = summary?.balance ?? 0;

  return (
    <div className="space-y-5">
      <PageHeader
        title="Cari Hesap"
        description="Bina bazlı borç/tahsilat defteri ve bakiyeler"
        count={pagination?.total ?? transactions.length}
        actions={
          <>
            <Button variant="outline" onClick={() => openDialog("methods")}>
              <Settings2 />
              Ödeme Kanalları
            </Button>
            <Button variant="outline" onClick={() => openDialog("manual")}>
              <Plus />
              Manuel Kayıt
            </Button>
            <Button onClick={() => openDialog("payment")}>
              <HandCoins />
              Tahsilat Al
            </Button>
          </>
        }
      />

      {summary && (
        <div className="grid gap-3 sm:grid-cols-3 lg:grid-cols-6">
          <SummaryTile label="Devir" value={formatCurrency(summary.totals.opening_balance)} />
          <SummaryTile label="Bakım" value={formatCurrency(summary.totals.maintenance_fee)} />
          <SummaryTile label="Parça" value={formatCurrency(summary.totals.part_charge)} />
          <SummaryTile label="Revizyon" value={formatCurrency(summary.totals.revision_charge)} />
          <SummaryTile label="Tahsilat" value={formatCurrency(summary.credits_total)} />
          <SummaryTile
            label={`Kalan Bakiye (KDV dahil ${formatCurrency(balance * (1 + VAT_RATE))})`}
            value={formatCurrency(balance)}
            highlight
          />
        </div>
      )}

      <Toolbar>
        <SearchInput
          value={query}
          onChange={setQuery}
          placeholder="Açıklama veya ödeyen ara..."
        />
        <FilterSelect
          value={building}
          onChange={setBuilding}
          allLabel="Tüm Binalar"
          options={buildingOptions.map((b) => ({ value: b.id, label: b.name }))}
        />
        <FilterSelect value={type} onChange={setType} allLabel="Tüm Tipler" options={typeOptions} />
      </Toolbar>

      {error && <ListError message={error.message} onRetry={reload} />}

      <DataTable
        columns={columns}
        data={transactions}
        getRowId={(t) => t.id}
        isLoading={isLoading}
        empty={
          <EmptyState
            icon={Wallet}
            title="Cari hareket yok"
            description="Seçili filtrelere uyan defter kaydı bulunamadı. Aylık bakım tahakkukları her ayın 1'inde otomatik oluşur."
          />
        }
      />

      <Pagination pagination={pagination} onPageChange={setPage} />

      <TransactionDialog
        open={dialog === "payment" || dialog === "manual"}
        mode={dialog === "manual" ? "manual" : "payment"}
        buildings={buildingOptions}
        paymentMethods={paymentMethods}
        defaultBuilding={building === ALL_VALUE ? "" : building}
        errors={formErrors}
        formError={formError}
        isSubmitting={isSubmitting}
        onOpenChange={(open) => {
          if (!open) setDialog(null);
        }}
        onSubmit={handleSubmit}
      />

      <PaymentMethodsDialog
        open={dialog === "methods"}
        methods={paymentMethods}
        onOpenChange={(open) => {
          if (!open) setDialog(null);
        }}
        onChanged={reloadMethods}
      />
    </div>
  );
}
