import * as React from "react";
import L from "leaflet";
import { MapContainer, Marker, TileLayer, useMapEvents } from "react-leaflet";
import { Loader2, Search } from "lucide-react";
import { Button } from "@/components/ui/button";
import "leaflet/dist/leaflet.css";
import iconRetinaUrl from "leaflet/dist/images/marker-icon-2x.png";
import iconUrl from "leaflet/dist/images/marker-icon.png";
import shadowUrl from "leaflet/dist/images/marker-shadow.png";

// Vite rewrites asset URLs, so Leaflet's default relative icon paths 404
// unless the bundled files are wired up explicitly.
L.Icon.Default.mergeOptions({ iconRetinaUrl, iconUrl, shadowUrl });

const TURKEY_CENTER: [number, number] = [39.0, 35.2];
const TURKEY_ZOOM = 5;
const PICKED_ZOOM = 16;

function parseCoord(value: string): number | null {
  const trimmed = value.trim();
  if (trimmed === "") return null;
  const parsed = Number(trimmed);
  return Number.isFinite(parsed) ? parsed : null;
}

function ClickToPick({ onPick }: { onPick: (lat: number, lng: number) => void }) {
  useMapEvents({
    click(event) {
      onPick(event.latlng.lat, event.latlng.lng);
    },
  });
  return null;
}

export function LocationPicker({
  latitude,
  longitude,
  searchQuery,
  onChange,
}: {
  /** Current coordinate values as form strings ("" when unset). */
  latitude: string;
  longitude: string;
  /** Free-text address used by the "find from address" geocode button. */
  searchQuery: string;
  onChange: (latitude: string, longitude: string) => void;
}) {
  const mapRef = React.useRef<L.Map | null>(null);
  const [isSearching, setSearching] = React.useState(false);
  const [searchError, setSearchError] = React.useState<string | null>(null);

  const lat = parseCoord(latitude);
  const lng = parseCoord(longitude);
  const hasMarker = lat != null && lng != null;

  // The dialog animates while the map mounts, so Leaflet measures a stale
  // container size; re-measure once the layout settles.
  React.useEffect(() => {
    const timer = window.setTimeout(() => mapRef.current?.invalidateSize(), 250);
    return () => window.clearTimeout(timer);
  }, []);

  const pick = (nextLat: number, nextLng: number) => {
    setSearchError(null);
    onChange(nextLat.toFixed(6), nextLng.toFixed(6));
  };

  const searchAddress = async () => {
    const query = searchQuery.trim();
    if (query === "") {
      setSearchError("Önce adres, ilçe veya şehir alanlarını doldur.");
      return;
    }

    setSearching(true);
    setSearchError(null);

    try {
      const response = await fetch(
        `https://nominatim.openstreetmap.org/search?format=json&limit=1&accept-language=tr&q=${encodeURIComponent(query)}`,
        { headers: { Accept: "application/json" } }
      );
      const results = (await response.json()) as { lat: string; lon: string }[];

      if (!results.length) {
        setSearchError("Adres bulunamadı. Haritadan elle seçebilirsin.");
        return;
      }

      const found: [number, number] = [Number(results[0].lat), Number(results[0].lon)];
      pick(found[0], found[1]);
      mapRef.current?.flyTo(found, PICKED_ZOOM, { duration: 0.8 });
    } catch {
      setSearchError("Adres araması başarısız oldu. Haritadan elle seçebilirsin.");
    } finally {
      setSearching(false);
    }
  };

  return (
    <div className="space-y-2">
      <div className="flex items-center justify-between gap-2">
        <p className="text-xs text-muted-foreground">
          Haritaya tıklayarak konum seç veya işaretçiyi sürükle.
        </p>
        <Button
          type="button"
          variant="outline"
          size="sm"
          disabled={isSearching}
          onClick={() => void searchAddress()}
        >
          {isSearching ? <Loader2 className="animate-spin" /> : <Search />}
          Adresten Bul
        </Button>
      </div>

      {searchError && <p className="text-xs text-danger-foreground">{searchError}</p>}

      <div className="h-64 overflow-hidden rounded-md border border-input">
        <MapContainer
          ref={mapRef}
          center={hasMarker ? [lat, lng] : TURKEY_CENTER}
          zoom={hasMarker ? PICKED_ZOOM : TURKEY_ZOOM}
          className="size-full"
        >
          <TileLayer
            attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
            url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
          />
          <ClickToPick onPick={pick} />
          {hasMarker && (
            <Marker
              position={[lat, lng]}
              draggable
              eventHandlers={{
                dragend(event) {
                  const next = (event.target as L.Marker).getLatLng();
                  pick(next.lat, next.lng);
                },
              }}
            />
          )}
        </MapContainer>
      </div>
    </div>
  );
}
