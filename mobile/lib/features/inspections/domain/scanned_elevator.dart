/// QR ile çözülen asansörün kontrol girişinde gereken özeti
/// (`ElevatorResource` yanıtının izdüşümü).
class ScannedElevator {
  const ScannedElevator({
    required this.uuid,
    required this.serialNumber,
    this.name,
    this.buildingName,
    this.currentLabel,
    this.lastInspectionAt,
    this.nextInspectionDue,
  });

  factory ScannedElevator.fromJson(Map<String, dynamic> json) {
    final building = json['building'] as Map<String, dynamic>?;

    return ScannedElevator(
      uuid: json['uuid'] as String,
      serialNumber: json['serial_number'] as String? ?? '',
      name: json['name'] as String?,
      buildingName: building?['name'] as String?,
      currentLabel: json['current_label'] as String?,
      lastInspectionAt: json['last_inspection_at'] as String?,
      nextInspectionDue: json['next_inspection_due'] as String?,
    );
  }

  final String uuid;
  final String serialNumber;
  final String? name;
  final String? buildingName;
  final String? currentLabel;
  final String? lastInspectionAt;
  final String? nextInspectionDue;

  String get displayName =>
      name?.isNotEmpty == true ? name! : serialNumber;
}
