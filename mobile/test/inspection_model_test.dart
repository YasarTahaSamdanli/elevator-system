import 'package:flutter_test/flutter_test.dart';

import 'package:asansor_mobile/features/inspections/domain/inspection_summary.dart';
import 'package:asansor_mobile/features/inspections/domain/scanned_elevator.dart';

void main() {
  test('ScannedElevator parses elevator QR resolution payload', () {
    final elevator = ScannedElevator.fromJson({
      'uuid': 'elevator-uuid',
      'serial_number': 'ELV-001',
      'name': 'A Blok',
      'building': {'name': 'Merkez Apartmani'},
      'current_label': 'yellow',
      'last_inspection_at': '2026-07-09',
      'next_inspection_due': '2027-07-09',
    });

    expect(elevator.uuid, 'elevator-uuid');
    expect(elevator.displayName, 'A Blok');
    expect(elevator.buildingName, 'Merkez Apartmani');
    expect(elevator.currentLabel, 'yellow');
  });

  test('InspectionSummary parses inspection history payload', () {
    final inspection = InspectionSummary.fromJson({
      'uuid': 'inspection-uuid',
      'type': 'periodic',
      'label': 'red',
      'inspected_at': '2026-07-09',
      'inspection_body': 'TSE',
      'report_number': 'R-123',
      'follow_up_due_date': '2026-08-08',
      'next_inspection_date': null,
      'work_order': {
        'uuid': 'work-order-uuid',
        'work_order_number': 'WO-123',
        'status': 'assigned',
      },
      'notes': 'Acil takip gerekli',
      'findings': [
        {
          'uuid': 'finding-uuid',
          'description': 'Fren testi uygunsuz',
          'is_resolved': false,
        },
      ],
    });

    expect(inspection.uuid, 'inspection-uuid');
    expect(inspection.typeLabel, 'Periyodik');
    expect(inspection.label, 'red');
    expect(inspection.workOrderNumber, 'WO-123');
    expect(inspection.unresolvedFindingCount, 1);
  });
}
