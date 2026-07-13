import * as React from "react";
import { Loader2, Plus, Users as UsersIcon } from "lucide-react";
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
import { ConfirmDeleteDialog, useConfirmDelete } from "@/components/common/ConfirmDeleteDialog";
import { RowActionsMenu } from "@/components/common/RowActionsMenu";
import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
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
import { initials } from "@/lib/format";
import { blankToNull, fieldError } from "@/lib/forms";
import { createUser, deleteUser, fetchUsers, updateUser, type UserInput } from "@/api/resources";
import { useAuth } from "@/providers/AuthProvider";
import { useDebounced, useList } from "@/hooks/useList";
import { useFormDialog } from "@/hooks/useFormDialog";
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

const roleOptions = (Object.keys(roleVariant) as UserRole[]).map((r) => ({
  value: r,
  label: r,
}));

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

interface UserFormValues {
  name: string;
  email: string;
  phone: string;
  password: string;
  role: UserRole;
  is_active: "true" | "false";
}

const emptyForm: UserFormValues = {
  name: "",
  email: "",
  phone: "",
  password: "",
  role: "Technician",
  is_active: "true",
};

function formFromUser(user: User | null): UserFormValues {
  if (!user) return emptyForm;

  return {
    name: user.name,
    email: user.email,
    phone: user.phone ?? "",
    password: "",
    role: user.role,
    is_active: user.is_active ? "true" : "false",
  };
}

function formToInput(values: UserFormValues): UserInput {
  return {
    name: values.name.trim(),
    email: values.email.trim(),
    phone: blankToNull(values.phone),
    password: values.password.trim() || undefined,
    role: values.role,
    is_active: values.is_active === "true",
  };
}

function UserFormDialog({
  open,
  user,
  errors,
  formError,
  isSubmitting,
  onOpenChange,
  onSubmit,
}: {
  open: boolean;
  user: User | null;
  errors: Record<string, string[]>;
  formError: string | null;
  isSubmitting: boolean;
  onOpenChange: (open: boolean) => void;
  onSubmit: (values: UserFormValues) => Promise<void>;
}) {
  const [values, setValues] = React.useState<UserFormValues>(() => formFromUser(user));
  const isEditing = !!user;

  React.useEffect(() => {
    if (open) setValues(formFromUser(user));
  }, [user, open]);

  const setValue = (field: keyof UserFormValues, value: string) => {
    setValues((prev) => ({ ...prev, [field]: value }));
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent
        className="max-h-[90vh] overflow-y-auto sm:max-w-lg"
        onInteractOutside={(event) => event.preventDefault()}
      >
        <DialogHeader>
          <DialogTitle>{isEditing ? "Kullanıcıyı Düzenle" : "Yeni Kullanıcı"}</DialogTitle>
          <DialogDescription>
            Şirket kullanıcısının bilgilerini ve rolünü gir.
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
            <Field label="Ad Soyad" error={fieldError(errors, "name")}>
              <Input
                value={values.name}
                onChange={(event) => setValue("name", event.target.value)}
                required
              />
            </Field>
            <Field label="Telefon" error={fieldError(errors, "phone")}>
              <Input
                value={values.phone}
                onChange={(event) => setValue("phone", event.target.value)}
              />
            </Field>
          </div>

          <Field label="E-posta" error={fieldError(errors, "email")}>
            <Input
              type="email"
              value={values.email}
              onChange={(event) => setValue("email", event.target.value)}
              required
            />
          </Field>

          <Field label={isEditing ? "Yeni Şifre (opsiyonel)" : "Şifre"} error={fieldError(errors, "password")}>
            <Input
              type="password"
              value={values.password}
              onChange={(event) => setValue("password", event.target.value)}
              placeholder={isEditing ? "Değiştirmek için doldur" : undefined}
              autoComplete="new-password"
              required={!isEditing}
            />
          </Field>

          <div className="grid gap-4 sm:grid-cols-2">
            <Field label="Rol" error={fieldError(errors, "role")}>
              <Select value={values.role} onValueChange={(value) => setValue("role", value)}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {roleOptions.map((option) => (
                    <SelectItem key={option.value} value={option.value}>
                      {option.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </Field>
            <Field label="Durum" error={fieldError(errors, "is_active")}>
              <Select
                value={values.is_active}
                onValueChange={(value) => setValue("is_active", value)}
              >
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="true">Aktif</SelectItem>
                  <SelectItem value="false">Pasif</SelectItem>
                </SelectContent>
              </Select>
            </Field>
          </div>

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
              {isEditing ? "Kaydet" : "Kullanıcı Ekle"}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

export function UsersPage() {
  const { user: currentUser } = useAuth();
  const [query, setQuery] = React.useState("");
  const [role, setRole] = React.useState(ALL_VALUE);
  const [page, setPage] = React.useState(1);
  const form = useFormDialog<User>();
  const del = useConfirmDelete<User>();
  const debouncedQuery = useDebounced(query);

  React.useEffect(() => {
    setPage(1);
  }, [debouncedQuery, role]);

  const listParams = React.useMemo(
    () => ({
      page,
      perPage: 25,
      search: debouncedQuery,
      sort: "name",
      filter: { ...(role === ALL_VALUE ? {} : { role }) },
    }),
    [page, debouncedQuery, role]
  );
  const { items: users, pagination, isLoading, error, reload } = useList(fetchUsers, listParams);

  const handleSubmit = (values: UserFormValues) =>
    form.submit(async () => {
      const input = formToInput(values);
      if (form.editing) {
        await updateUser(form.editing.id, input);
      } else {
        await createUser({ ...input, password: values.password.trim() });
      }
      reload();
    });

  return (
    <div className="space-y-5">
      <PageHeader
        title="Ekip"
        description="Şirket kullanıcıları ve saha teknisyenleri"
        count={pagination?.total ?? users.length}
        actions={
          <Button onClick={form.openCreate}>
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
        rowActions={(user) => (
          <RowActionsMenu
            ariaLabel="Kullanıcı işlemleri"
            onEdit={() => form.openEdit(user)}
            onDelete={currentUser?.uuid !== user.id ? () => del.request(user) : undefined}
          />
        )}
        empty={
          <EmptyState
            icon={UsersIcon}
            title="Kullanıcı bulunamadı"
            description="Arama veya filtre kriterlerine uyan kullanıcı yok."
          />
        }
      />

      <Pagination pagination={pagination} onPageChange={setPage} />

      <UserFormDialog
        open={form.open}
        user={form.editing}
        errors={form.errors}
        formError={form.formError}
        isSubmitting={form.isSubmitting}
        onOpenChange={form.onOpenChange}
        onSubmit={handleSubmit}
      />

      <ConfirmDeleteDialog
        open={!!del.target}
        title="Kullanıcıyı Sil"
        description={`${del.target?.name ?? ""} kaydı silinecek. Bu işlem kullanıcının erişimini hemen kapatır.`}
        error={del.error}
        isDeleting={del.isDeleting}
        onClose={del.close}
        onConfirm={() =>
          void del.confirm(async () => {
            if (!del.target) return;
            await deleteUser(del.target.id);
            reload();
          })
        }
      />
    </div>
  );
}
