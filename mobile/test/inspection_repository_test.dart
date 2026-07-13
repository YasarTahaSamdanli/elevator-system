import 'package:flutter_test/flutter_test.dart';

import 'package:asansor_mobile/features/inspections/data/inspection_repository.dart';

void main() {
  test('normalizeElevatorQrValue keeps bare identifiers', () {
    expect(normalizeElevatorQrValue('  elevator-qr-123  '), 'elevator-qr-123');
  });

  test('normalizeElevatorQrValue extracts identifier from web QR URLs', () {
    expect(
      normalizeElevatorQrValue('https://app.example.com/qr/elevator-qr-123'),
      'elevator-qr-123',
    );
  });
}
