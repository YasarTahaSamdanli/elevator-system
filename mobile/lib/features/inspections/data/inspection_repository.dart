import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../domain/inspection_summary.dart';
import '../domain/scanned_elevator.dart';

final inspectionRepositoryProvider = Provider<InspectionRepository>(
  (ref) => InspectionRepository(ref.watch(apiClientProvider)),
);

String normalizeElevatorQrValue(String rawValue) {
  final trimmed = rawValue.trim();
  final uri = Uri.tryParse(trimmed);

  if (uri != null && uri.hasScheme && uri.pathSegments.isNotEmpty) {
    return uri.pathSegments.last.trim();
  }

  return trimmed;
}

class InspectionRepository {
  InspectionRepository(this._api);

  final ApiClient _api;

  /// Okutulan QR değerini şirketin asansörüne çözer; eşleşme yoksa `null`
  /// (yanlış/yabancı etiket — akış çağıran tarafta kesilir).
  Future<ScannedElevator?> resolveByQr(String qrIdentifier) async {
    final normalizedQrIdentifier = normalizeElevatorQrValue(qrIdentifier);
    final envelope = await _api.get(
      '/elevators',
      query: {
        'per_page': 1,
        'filter[qr_identifier]': normalizedQrIdentifier,
      },
    );

    final data = envelope['data'] as List<dynamic>? ?? const [];
    final first = data.whereType<Map<String, dynamic>>().firstOrNull;

    return first == null ? null : ScannedElevator.fromJson(first);
  }

  /// Asansörün kontrol geçmişi, en yeni kontrol başta (backend varsayılanı
  /// `-inspected_at`). Saha ekranı için tek sayfa yeterli.
  Future<List<InspectionSummary>> listForElevator(String elevatorUuid) async {
    final envelope = await _api.get(
      '/elevator-inspections',
      query: {
        'per_page': 50,
        'filter[elevator_uuid]': elevatorUuid,
      },
    );

    final data = envelope['data'] as List<dynamic>? ?? const [];

    return data
        .whereType<Map<String, dynamic>>()
        .map(InspectionSummary.fromJson)
        .toList();
  }

  /// Tarih alanları `YYYY-MM-DD` formatında gider. `followUpDueDate` boş
  /// bırakılırsa backend kırmızı etikette +30, sarıda +60 gün önerir.
  Future<void> create({
    required String elevatorUuid,
    required String type,
    required String inspectedAt,
    required String label,
    String? inspectionBody,
    String? reportNumber,
    String? followUpDueDate,
    String? nextInspectionDate,
    String? notes,
    List<String> findings = const [],
  }) async {
    await _api.post(
      '/elevator-inspections',
      body: {
        'elevator_uuid': elevatorUuid,
        'type': type,
        'inspected_at': inspectedAt,
        'label': label,
        if (inspectionBody != null) 'inspection_body': inspectionBody,
        if (reportNumber != null) 'report_number': reportNumber,
        if (followUpDueDate != null) 'follow_up_due_date': followUpDueDate,
        if (nextInspectionDate != null)
          'next_inspection_date': nextInspectionDate,
        if (notes != null) 'notes': notes,
        'findings': [
          for (final description in findings) {'description': description},
        ],
      },
    );
  }
}
