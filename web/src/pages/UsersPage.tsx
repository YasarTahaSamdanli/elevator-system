import * as React from "react";
import { Loader2, MoreHorizontal, Pencil, Plus, Trash2, Users as UsersIcon } from "lucide-react";
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
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Input } from "@/components/ui/input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { initials } from "@/lib/format";
import { createUser, deleteUser, fetchUsers, updateUser, type UserInput } from "@/api/resources";
import { useAuth } from "@/providers/AuthProvider";
import { useDebounced, useList } from "@/hooks/useList";
import { ApiError } from "@/lib/api";
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

const blankToNull = (value: string): string | null => {
  const trimmed = value.trim();
  return trimmed === "" ? null : trimmed;
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

function fieldError(errors: Record<string, string[]>, field: keyof UserFormValues) {
  return errors[field]?.[0] ?? null;
}

function Field({
  label,
  error,
  children,
}: {
  label: string;
  error?: string | null;
  children: React.ReactNode;
}) {
  return (
    <label className="space-y-1.5 text-sm">
      <span className="font-medium text-foreground">{label}</span>
      {children}
      {error && <span className="block text-xs text-danger-foreground">{error}</span>}
    </label>
  );
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
          {formError && (
            <div className="rounded-md bg-danger-subtle px-3 py-2 text-sm text-danger-foreground">
              {formError}
            </div>
          )}

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
  const [formOpen, setFormOpen] = React.useState(false);
  const [editingUser, setEditingUser] = React.useState<User | null>(null);
  const [deletingUser, setDeletingUser] = React.useState<User | null>(null);
  const [formErrors, setFormErrors] = React.useState<Record<string, string[]>>({});
  const [formError, setFormError] = React.useState<string | null>(null);
  const [isSubmitting, setSubmitting] = React.useState(false);
  const [isDeleting, setDeleting] = React.useState(false);
  const [deleteError, setDeleteError] = React.useState<string | null>(null);
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

  const openCreate = () => {
    setEditingUser(null);
    setFormErrors({});
    setFormError(null);
    setFormOpen(true);
  };

  const openEdit = (user: User) => {
    setEditingUser(user);
    setFormErrors({});
    setFormError(null);
    setFormOpen(true);
  };

  const handleSubmit = async (values: UserFormValues) => {
    setSubmitting(true);
    setFormErrors({});
    setFormError(null);

    try {
      const input = formToInput(values);
      if (editingUser) {
        await updateUser(editingUser.id, input);
      } else {
        await createUser({ ...input, password: values.password.trim() });
      }
      setFormOpen(false);
      setEditingUser(null);
      reload();
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

  const handleDelete = async () => {
    if (!deletingUser) return;
    setDeleting(true);
    setDeleteError(null);

    try {
      await deleteUser(deletingUser.id);
      setDeletingUser(null);
      reload();
    } catch (err) {
      setDeleteError(err instanceof ApiError ? err.message : "Beklenmeyen bir hata oluştu.");
    } finally {
      setDeleting(false);
    }
  };

  return (
    <div className="space-y-5">
      <PageHeader
        title="Ekip"
        description="Şirket kullanıcıları ve saha teknisyenleri"
        count={pagination?.total ?? users.length}
        actions={
          <Button onClick={openCreate}>
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
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="ghost" size="icon-sm" aria-label="Kullanıcı işlemleri">
                <MoreHorizontal />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              <DropdownMenuItem onSelect={() => openEdit(user)}>
                <Pencil className="size-4" />
                Düzenle
              </DropdownMenuItem>
              {currentUser?.uuid !== user.id && (
                <DropdownMenuItem
                  className="text-danger focus:text-danger"
                  onSelect={() => {
                    setDeleteError(null);
                    setDeletingUser(user);
                  }}
                >
                  <Trash2 className="size-4" />
                  Sil
                </DropdownMenuItem>
              )}
            </DropdownMenuContent>
          </DropdownMenu>
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
        open={formOpen}
        user={editingUser}
        errors={formErrors}
        formError={formError}
        isSubmitting={isSubmitting}
        onOpenChange={(open) => {
          setFormOpen(open);
          if (!open) setEditingUser(null);
        }}
        onSubmit={handleSubmit}
      />

      <Dialog open={!!deletingUser} onOpenChange={(open) => !open && setDeletingUser(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Kullanıcıyı Sil</DialogTitle>
            <DialogDescription>
              {deletingUser?.name} kaydı silinecek. Bu işlem kullanıcının erişimini hemen kapatır.
            </DialogDescription>
          </DialogHeader>
          {deleteError && (
            <div className="rounded-md bg-danger-subtle px-3 py-2 text-sm text-danger-foreground">
              {deleteError}
            </div>
          )}
          <DialogFooter>
            <Button variant="outline" disabled={isDeleting} onClick={() => setDeletingUser(null)}>
              Vazgeç
            </Button>
            <Button variant="destructive" disabled={isDeleting} onClick={handleDelete}>
              {isDeleting && <Loader2 className="animate-spin" />}
              Sil
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
