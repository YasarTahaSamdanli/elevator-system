import * as React from "react";
import { ApiError } from "@/lib/api";

/**
 * State machine for a create/edit dialog: which record is being edited,
 * validation errors and the submit lifecycle. `submit` runs the given save
 * action, maps ApiError to field/form errors and closes the dialog on
 * success — pages only provide the create/update call itself.
 */
export function useFormDialog<T>() {
  const [open, setOpen] = React.useState(false);
  const [editing, setEditing] = React.useState<T | null>(null);
  const [errors, setErrors] = React.useState<Record<string, string[]>>({});
  const [formError, setFormError] = React.useState<string | null>(null);
  const [isSubmitting, setSubmitting] = React.useState(false);

  const openCreate = () => {
    setEditing(null);
    setErrors({});
    setFormError(null);
    setOpen(true);
  };

  const openEdit = (record: T) => {
    setEditing(record);
    setErrors({});
    setFormError(null);
    setOpen(true);
  };

  const onOpenChange = (next: boolean) => {
    setOpen(next);
    if (!next) setEditing(null);
  };

  const submit = async (save: () => Promise<void>) => {
    setSubmitting(true);
    setErrors({});
    setFormError(null);

    try {
      await save();
      setOpen(false);
      setEditing(null);
    } catch (err) {
      if (err instanceof ApiError) {
        setErrors(err.details);
        setFormError(err.message);
      } else {
        setFormError("Beklenmeyen bir hata oluştu.");
      }
    } finally {
      setSubmitting(false);
    }
  };

  return { open, editing, errors, formError, isSubmitting, openCreate, openEdit, onOpenChange, submit };
}
