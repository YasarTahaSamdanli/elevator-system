import * as React from "react";
import QRCode from "react-qr-code";
import { Printer, QrCode as QrCodeIcon } from "lucide-react";
import { PageHeader } from "@/components/common/PageHeader";
import { SearchInput } from "@/components/common/SearchInput";
import { ALL_VALUE, FilterSelect } from "@/components/common/FilterSelect";
import { ListError } from "@/components/common/ListError";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { fetchBuildings, fetchElevators } from "@/api/resources";
import { useDebounced, useList } from "@/hooks/useList";
import type { Elevator } from "@/types";

/**
 * Elevators carry a stable `qr_identifier`. When VITE_QR_BASE_URL is set
 * (e.g. https://app.firma.com) the label encodes a scannable URL; until the
 * production domain is decided we fall back to the bare identifier so the
 * printed stickers at least carry the canonical id.
 */
const QR_BASE_URL = (import.meta.env.VITE_QR_BASE_URL as string | undefined)?.trim();

function qrValue(elevator: Elevator): string {
  if (QR_BASE_URL) {
    return `${QR_BASE_URL.replace(/\/+$/, "")}/qr/${elevator.qr_identifier}`;
  }
  return elevator.qr_identifier;
}

type LabelSizeKey = "small" | "medium" | "large";

interface LabelSizeDef {
  label: string;
  width: string;
  height: string;
  qr: string;
  font: string;
}

const labelSizes: Record<LabelSizeKey, LabelSizeDef> = {
  small: { label: "Küçük — 6×5 cm (15/sayfa)", width: "6cm", height: "5cm", qr: "2.6cm", font: "7pt" },
  medium: { label: "Orta — 9×7 cm (6/sayfa)", width: "9cm", height: "7cm", qr: "4cm", font: "9pt" },
  large: { label: "Büyük — 12×10 cm (2/sayfa)", width: "12cm", height: "10cm", qr: "6cm", font: "11pt" },
};

/**
 * Only the label sheet is visible in print output; @page keeps a 1cm A4
 * margin and labels never split across pages.
 */
const printStyles = `
@page { size: A4; margin: 1cm; }
@media print {
  body * { visibility: hidden; }
  #qr-print-area, #qr-print-area * { visibility: visible; }
  #qr-print-area {
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
    margin: 0;
    padding: 0;
  }
  .qr-label { break-inside: avoid; page-break-inside: avoid; }
}
`;

function QrLabel({ elevator, size }: { elevator: Elevator; size: LabelSizeDef }) {
  return (
    <div
      className="qr-label flex flex-col items-center justify-center text-center"
      style={{
        width: size.width,
        height: size.height,
        padding: "0.25cm",
        gap: "0.15cm",
        backgroundColor: "#ffffff",
        color: "#000000",
        border: "1px dashed #b0b0b0",
        fontSize: size.font,
        lineHeight: 1.35,
      }}
    >
      <QRCode
        value={qrValue(elevator)}
        level="H"
        bgColor="#ffffff"
        fgColor="#000000"
        style={{ width: size.qr, height: size.qr }}
      />
      <div className="min-w-0">
        <div className="truncate font-semibold">{elevator.building_name}</div>
        <div className="truncate">{elevator.name ?? elevator.serial_number}</div>
        <div className="truncate font-mono">SN: {elevator.serial_number}</div>
        <div className="truncate font-mono" style={{ color: "#555555" }}>
          {elevator.qr_identifier.slice(0, 8).toUpperCase()}
        </div>
      </div>
    </div>
  );
}

export function QrLabelsPage() {
  const [query, setQuery] = React.useState("");
  const [building, setBuilding] = React.useState(ALL_VALUE);
  const [sizeKey, setSizeKey] = React.useState<LabelSizeKey>("medium");
  const [copies, setCopies] = React.useState("1");
  const [selected, setSelected] = React.useState<Map<string, Elevator>>(new Map());
  const debouncedQuery = useDebounced(query);

  const listParams = React.useMemo(
    () => ({
      perPage: 100,
      sort: "name",
      search: debouncedQuery,
      filter: {
        ...(building === ALL_VALUE ? {} : { building_uuid: building }),
      },
    }),
    [debouncedQuery, building]
  );
  const buildingParams = React.useMemo(() => ({ perPage: 100, sort: "name" }), []);
  const { items: elevators, isLoading, error, reload } = useList(fetchElevators, listParams);
  const { items: buildingOptions } = useList(fetchBuildings, buildingParams);

  const toggle = (elevator: Elevator) => {
    setSelected((prev) => {
      const next = new Map(prev);
      if (next.has(elevator.id)) {
        next.delete(elevator.id);
      } else {
        next.set(elevator.id, elevator);
      }
      return next;
    });
  };

  const selectListed = () => {
    setSelected((prev) => {
      const next = new Map(prev);
      elevators.forEach((elevator) => next.set(elevator.id, elevator));
      return next;
    });
  };

  const size = labelSizes[sizeKey];
  const copyCount = Number(copies);
  const selectedElevators = [...selected.values()];
  const labelCount = selectedElevators.length * copyCount;

  return (
    <div className="space-y-5">
      <style>{printStyles}</style>

      <PageHeader
        title="QR Etiketleri"
        description="Asansörlere yapıştırılacak QR etiketlerini hazırla ve yazdır"
        count={selected.size}
        actions={
          <Button onClick={() => window.print()} disabled={labelCount === 0}>
            <Printer />
            Yazdır ({labelCount} etiket)
          </Button>
        }
      />

      <div className="grid items-start gap-5 lg:grid-cols-[minmax(20rem,24rem)_1fr]">
        {/* Elevator picker */}
        <div className="space-y-3 rounded-lg border border-border bg-surface p-4">
          <div className="space-y-2">
            <SearchInput value={query} onChange={setQuery} placeholder="Seri no, ad veya bina ara..." />
            <FilterSelect
              value={building}
              onChange={setBuilding}
              allLabel="Tüm Binalar"
              options={buildingOptions.map((b) => ({ value: b.id, label: b.name }))}
            />
          </div>

          <div className="flex items-center justify-between text-xs">
            <button
              type="button"
              className="font-medium text-primary hover:underline"
              onClick={selectListed}
            >
              Listelenenleri seç
            </button>
            <button
              type="button"
              className="font-medium text-muted-foreground hover:underline"
              onClick={() => setSelected(new Map())}
            >
              Seçimi temizle
            </button>
          </div>

          {error && <ListError message={error.message} onRetry={reload} />}

          <div className="max-h-[28rem] divide-y divide-border overflow-y-auto rounded-md border border-border">
            {isLoading ? (
              <div className="space-y-2 p-3">
                <Skeleton className="h-9 w-full" />
                <Skeleton className="h-9 w-full" />
                <Skeleton className="h-9 w-full" />
              </div>
            ) : elevators.length === 0 ? (
              <div className="p-4 text-sm text-muted-foreground">
                Kriterlere uyan asansör bulunamadı.
              </div>
            ) : (
              elevators.map((elevator) => (
                <label
                  key={elevator.id}
                  className="flex cursor-pointer items-center gap-3 px-3 py-2 transition-colors hover:bg-muted/50"
                >
                  <input
                    type="checkbox"
                    className="size-4 accent-primary"
                    checked={selected.has(elevator.id)}
                    onChange={() => toggle(elevator)}
                  />
                  <div className="min-w-0">
                    <div className="truncate text-sm text-foreground">
                      {elevator.name ?? elevator.serial_number}
                    </div>
                    <div className="truncate text-xs text-muted-foreground">
                      {elevator.building_name} · <span className="font-mono">{elevator.serial_number}</span>
                    </div>
                  </div>
                </label>
              ))
            )}
          </div>
        </div>

        {/* Settings + preview */}
        <div className="space-y-3">
          <div className="flex flex-wrap items-center gap-2">
            <Select value={sizeKey} onValueChange={(value) => setSizeKey(value as LabelSizeKey)}>
              <SelectTrigger className="w-56">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {(Object.keys(labelSizes) as LabelSizeKey[]).map((key) => (
                  <SelectItem key={key} value={key}>
                    {labelSizes[key].label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            <Select value={copies} onValueChange={setCopies}>
              <SelectTrigger className="w-32">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="1">1 kopya</SelectItem>
                <SelectItem value="2">2 kopya</SelectItem>
                <SelectItem value="3">3 kopya</SelectItem>
              </SelectContent>
            </Select>
            <span className="text-xs text-muted-foreground">
              Kabin içi + makine dairesi için 2 kopya önerilir.
            </span>
          </div>

          <div className="overflow-auto rounded-lg border border-border bg-muted/30 p-4">
            {selectedElevators.length === 0 ? (
              <div className="flex flex-col items-center gap-2 py-16 text-center text-sm text-muted-foreground">
                <QrCodeIcon className="size-8" />
                Soldan asansör seçtikçe etiket önizlemesi burada görünür.
              </div>
            ) : (
              <div id="qr-print-area" className="flex flex-wrap" style={{ gap: "0.35cm" }}>
                {selectedElevators.flatMap((elevator) =>
                  Array.from({ length: copyCount }, (_, copy) => (
                    <QrLabel key={`${elevator.id}-${copy}`} elevator={elevator} size={size} />
                  ))
                )}
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
