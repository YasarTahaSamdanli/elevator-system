import 'package:flutter/material.dart';

/// Etiket renk/isim eşlemesi — form ve geçmiş listesi aynı diziyi kullanır.
const inspectionLabels = <({String value, String name, Color color})>[
  (value: 'green', name: 'Yeşil', color: Colors.green),
  (value: 'blue', name: 'Mavi', color: Colors.blue),
  (value: 'yellow', name: 'Sarı', color: Colors.orange),
  (value: 'red', name: 'Kırmızı', color: Colors.red),
];

({String name, Color color}) inspectionLabelMeta(String label) {
  for (final entry in inspectionLabels) {
    if (entry.value == label) {
      return (name: entry.name, color: entry.color);
    }
  }

  return (name: label, color: Colors.grey);
}

/// `ElevatorInspectionResource` yanıtının geçmiş listesinde kullanılan
/// izdüşümü.
class InspectionSummary {
  const InspectionSummary({
    required this.uuid,
    required this.type,
    required this.label,
    this.inspectedAt,
    this.inspectionBody,
    this.reportNumber,
    this.followUpDueDate,
    this.nextInspectionDate,
    this.workOrderNumber,
    this.notes,
    this.findings = const [],
  });

  factory InspectionSummary.fromJson(Map<String, dynamic> json) {
    DateTime? date(String key) {
      final value = json[key];
      return value is String ? DateTime.tryParse(value) : null;
    }

    final workOrder = json['work_order'] as Map<String, dynamic>?;

    return InspectionSummary(
      uuid: json['uuid'] as String,
      type: json['type'] as String? ?? 'periodic',
      label: json['label'] as String? ?? '',
      inspectedAt: date('inspected_at'),
      inspectionBody: json['inspection_body'] as String?,
      reportNumber: json['report_number'] as String?,
      followUpDueDate: date('follow_up_due_date'),
      nextInspectionDate: date('next_inspection_date'),
      workOrderNumber: workOrder?['work_order_number'] as String?,
      notes: json['notes'] as String?,
      findings: (json['findings'] as List<dynamic>? ?? const [])
          .whereType<Map<String, dynamic>>()
          .map(InspectionFindingSummary.fromJson)
          .toList(),
    );
  }

  final String uuid;
  final String type;
  final String label;
  final DateTime? inspectedAt;
  final String? inspectionBody;
  final String? reportNumber;
  final DateTime? followUpDueDate;
  final DateTime? nextInspectionDate;
  final String? workOrderNumber;
  final String? notes;
  final List<InspectionFindingSummary> findings;

  String get typeLabel => switch (type) {
        'periodic' => 'Periyodik',
        'follow_up' => 'Takip Kontrolü',
        _ => type,
      };

  String get labelName => inspectionLabelMeta(label).name;

  Color get labelColor => inspectionLabelMeta(label).color;

  int get unresolvedFindingCount =>
      findings.where((finding) => !finding.isResolved).length;
}

class InspectionFindingSummary {
  const InspectionFindingSummary({
    required this.uuid,
    required this.description,
    required this.isResolved,
  });

  factory InspectionFindingSummary.fromJson(Map<String, dynamic> json) =>
      InspectionFindingSummary(
        uuid: json['uuid'] as String,
        description: json['description'] as String? ?? '',
        isResolved: json['is_resolved'] as bool? ?? false,
      );

  final String uuid;
  final String description;
  final bool isResolved;
}
