import 'package:flutter/material.dart';

/// `WorkOrderResource` yanıtının teknisyen ekranlarında kullanılan izdüşümü.
class WorkOrder {
  const WorkOrder({
    required this.uuid,
    required this.workOrderNumber,
    required this.type,
    required this.status,
    required this.priority,
    this.buildingName,
    this.elevatorName,
    this.elevatorSerialNumber,
    this.contractNumber,
    this.assignedUserName,
    this.scheduledAt,
    this.startedAt,
    this.completedAt,
    this.description,
    this.notes,
    this.checklist = const [],
    this.items = const [],
  });

  factory WorkOrder.fromJson(Map<String, dynamic> json) {
    Map<String, dynamic>? nested(String key) =>
        json[key] as Map<String, dynamic>?;
    DateTime? date(String key) {
      final value = json[key];
      return value is String ? DateTime.tryParse(value) : null;
    }

    return WorkOrder(
      uuid: json['uuid'] as String,
      workOrderNumber: json['work_order_number'] as String,
      type: json['type'] as String,
      status: json['status'] as String,
      priority: json['priority'] as String? ?? 'normal',
      buildingName: nested('building')?['name'] as String?,
      elevatorName: nested('elevator')?['name'] as String?,
      elevatorSerialNumber: nested('elevator')?['serial_number'] as String?,
      contractNumber: nested('service_contract')?['contract_number'] as String?,
      assignedUserName: nested('assigned_user')?['name'] as String?,
      scheduledAt: date('scheduled_at'),
      startedAt: date('started_at'),
      completedAt: date('completed_at'),
      description: json['description'] as String?,
      notes: json['notes'] as String?,
      checklist: (json['checklist'] as List<dynamic>? ?? const [])
          .whereType<Map<String, dynamic>>()
          .map(WorkOrderChecklistItem.fromJson)
          .toList(),
      items: (json['items'] as List<dynamic>? ?? const [])
          .whereType<Map<String, dynamic>>()
          .map(WorkOrderMaterialItem.fromJson)
          .toList(),
    );
  }

  final String uuid;
  final String workOrderNumber;
  final String type;
  final String status;
  final String priority;
  final String? buildingName;
  final String? elevatorName;
  final String? elevatorSerialNumber;
  final String? contractNumber;
  final String? assignedUserName;
  final DateTime? scheduledAt;
  final DateTime? startedAt;
  final DateTime? completedAt;
  final String? description;
  final String? notes;
  final List<WorkOrderChecklistItem> checklist;
  final List<WorkOrderMaterialItem> items;

  int get completedChecklistCount =>
      checklist.where((item) => item.isDone).length;

  bool get canEditMaterials => status != 'completed' && status != 'cancelled';

  String get statusLabel => switch (status) {
        'draft' => 'Taslak',
        'planned' => 'Planlandı',
        'assigned' => 'Atandı',
        'in_progress' => 'Devam Ediyor',
        'completed' => 'Tamamlandı',
        'cancelled' => 'İptal Edildi',
        _ => status,
      };

      String get typeLabel => switch (type) {
        'maintenance' => 'Bakım',
        'fault' => 'Arıza',
        'inspection' => 'Muayene',
        'repair' => 'Revizyon',
        _ => type,
      };

  String get priorityLabel => switch (priority) {
        'low' => 'Düşük',
        'normal' => 'Normal',
        'high' => 'Yüksek',
        'critical' => 'Kritik',
        _ => priority,
      };

  Color get statusColor => switch (status) {
        'draft' => Colors.blueGrey,
        'planned' => Colors.indigo,
        'assigned' => Colors.orange,
        'in_progress' => Colors.blue,
        'completed' => Colors.green,
        'cancelled' => Colors.red,
        _ => Colors.grey,
      };

  /// Teknisyenin ekranda görebileceği bir sonraki yaşam döngüsü adımı
  /// (backend `canTransitionTo` ile ayrıca doğrular). Yaşam döngüsü adım
  /// atlamaya izin verdiği için planlı/atanmış işler doğrudan başlatılabilir;
  /// taslaklar ofis tarafından yayınlanana kadar sahada başlatılamaz.
  ({String status, String label})? get nextAction => switch (status) {
        'planned' ||
        'assigned' =>
          (status: 'in_progress', label: 'İşe Başla'),
        'in_progress' => (status: 'completed', label: 'Tamamla'),
        _ => null,
      };
}

class WorkOrderChecklistItem {
  const WorkOrderChecklistItem({
    required this.uuid,
    required this.position,
    required this.label,
    required this.isDone,
    this.severity,
    this.itemCode,
    this.note,
  });

  factory WorkOrderChecklistItem.fromJson(Map<String, dynamic> json) =>
      WorkOrderChecklistItem(
        uuid: json['uuid'] as String,
        position: json['position'] as int? ?? 0,
        label: json['label'] as String? ?? '',
        isDone: json['is_done'] as bool? ?? false,
        severity: json['severity'] as String?,
        itemCode: json['item_code'] as String?,
        note: json['note'] as String?,
      );

  final String uuid;
  final int position;
  final String label;
  final bool isDone;

  /// Muayene raporundaki renk bölümü (`red`/`yellow`/`blue`); rapor
  /// kaynaklı olmayan maddelerde null.
  final String? severity;

  /// Rapordaki standart madde numarası, örn. "2.7.8".
  final String? itemCode;

  final String? note;
}

/// EK 7 raporundaki renk bölümlerinin başlık ve rengi; checklist kağıttaki
/// sırayla (kırmızı → sarı → mavi) gruplanarak gösterilir.
class ChecklistSeverityMeta {
  const ChecklistSeverityMeta(this.title, this.color);

  final String title;
  final Color color;

  static const order = ['red', 'yellow', 'blue'];

  static const bySeverity = {
    'red': ChecklistSeverityMeta('Kırmızı Eksikler', Colors.red),
    'yellow': ChecklistSeverityMeta('Sarı Eksikler', Colors.amber),
    'blue': ChecklistSeverityMeta('Mavi Eksikler', Colors.blue),
  };
}

class WorkOrderMaterialItem {
  const WorkOrderMaterialItem({
    required this.uuid,
    required this.materialName,
    this.materialCode,
    this.unit,
    required this.quantity,
    this.unitPrice,
    this.totalPrice,
    this.note,
  });

  factory WorkOrderMaterialItem.fromJson(Map<String, dynamic> json) {
    final material = json['material'] as Map<String, dynamic>? ?? const {};

    return WorkOrderMaterialItem(
      uuid: json['uuid'] as String,
      materialName: material['name'] as String? ?? 'Malzeme',
      materialCode: material['code'] as String?,
      unit: material['unit'] as String?,
      quantity: _num(json['quantity']) ?? 0,
      unitPrice: _num(json['unit_price']),
      totalPrice: _num(json['total_price']),
      note: json['note'] as String?,
    );
  }

  final String uuid;
  final String materialName;
  final String? materialCode;
  final String? unit;
  final num quantity;
  final num? unitPrice;
  final num? totalPrice;
  final String? note;

  static num? _num(dynamic value) {
    if (value is num) {
      return value;
    }

    if (value is String) {
      return num.tryParse(value);
    }

    return null;
  }
}

class AvailableMaterial {
  const AvailableMaterial({
    required this.uuid,
    required this.code,
    required this.name,
    required this.unit,
    this.defaultUnitPrice,
    this.stockOnHand,
  });

  factory AvailableMaterial.fromJson(Map<String, dynamic> json) =>
      AvailableMaterial(
        uuid: json['uuid'] as String,
        code: json['code'] as String? ?? '',
        name: json['name'] as String? ?? 'Malzeme',
        unit: json['unit'] as String? ?? '',
        defaultUnitPrice: WorkOrderMaterialItem._num(
          json['default_unit_price'],
        ),
        stockOnHand: WorkOrderMaterialItem._num(json['stock_on_hand']),
      );

  final String uuid;
  final String code;
  final String name;
  final String unit;
  final num? defaultUnitPrice;
  final num? stockOnHand;

  String get label => code.isEmpty ? name : '$code - $name';
}
