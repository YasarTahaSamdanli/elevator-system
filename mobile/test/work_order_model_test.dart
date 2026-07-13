import 'package:flutter_test/flutter_test.dart';

import 'package:asansor_mobile/features/work_orders/domain/work_order.dart';

void main() {
  test('WorkOrder parses checklist and material items from detail payload', () {
    final workOrder = WorkOrder.fromJson({
      'uuid': 'work-order-uuid',
      'work_order_number': 'WO-001',
      'type': 'maintenance',
      'status': 'in_progress',
      'priority': 'normal',
      'building': {'name': 'Merkez Apartmani'},
      'elevator': {
        'name': 'A Blok',
        'serial_number': 'ELV-123',
      },
      'service_contract': {'contract_number': 'SC-001'},
      'assigned_user': {'name': 'Teknisyen'},
      'scheduled_at': '2026-07-09T09:00:00+03:00',
      'started_at': null,
      'completed_at': null,
      'description': 'Periyodik bakim',
      'notes': null,
      'checklist': [
        {
          'uuid': 'check-1',
          'position': 1,
          'label': 'Kapi kilitlerini kontrol et',
          'is_done': true,
          'note': 'Sorun yok',
        },
        {
          'uuid': 'check-2',
          'position': 2,
          'label': 'Ray yaglamasini kontrol et',
          'is_done': false,
          'note': null,
        },
      ],
      'items': [
        {
          'uuid': 'item-1',
          'material': {
            'uuid': 'material-1',
            'code': 'YAG-01',
            'name': 'Makine yagi',
            'unit': 'liter',
          },
          'quantity': '2.5',
          'unit_price': '100',
          'total_price': '250',
          'note': 'Dolum yapildi',
        },
      ],
    });

    expect(workOrder.checklist, hasLength(2));
    expect(workOrder.completedChecklistCount, 1);
    expect(workOrder.checklist.first.label, 'Kapi kilitlerini kontrol et');
    expect(workOrder.checklist.first.isDone, isTrue);

    expect(workOrder.items, hasLength(1));
    expect(workOrder.items.first.materialCode, 'YAG-01');
    expect(workOrder.items.first.materialName, 'Makine yagi');
    expect(workOrder.items.first.quantity, 2.5);
    expect(workOrder.items.first.totalPrice, 250);
  });

  test('WorkOrder uses empty lists when detail-only arrays are absent', () {
    final workOrder = WorkOrder.fromJson({
      'uuid': 'work-order-uuid',
      'work_order_number': 'WO-002',
      'type': 'fault',
      'status': 'assigned',
      'priority': null,
      'scheduled_at': null,
      'started_at': null,
      'completed_at': null,
      'description': null,
      'notes': null,
    });

    expect(workOrder.priority, 'normal');
    expect(workOrder.checklist, isEmpty);
    expect(workOrder.items, isEmpty);
  });

  test('AvailableMaterial parses selectable material payload', () {
    final material = AvailableMaterial.fromJson({
      'uuid': 'material-uuid',
      'code': 'FREN-01',
      'name': 'Fren balatasi',
      'unit': 'piece',
      'default_unit_price': '125.50',
      'stock_on_hand': '8',
    });

    expect(material.uuid, 'material-uuid');
    expect(material.label, 'FREN-01 - Fren balatasi');
    expect(material.defaultUnitPrice, 125.50);
    expect(material.stockOnHand, 8);
  });
}
