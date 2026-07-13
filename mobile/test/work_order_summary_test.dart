import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:asansor_mobile/features/work_orders/domain/work_order_summary.dart';
import 'package:asansor_mobile/features/work_orders/presentation/work_orders_page.dart';
import 'package:asansor_mobile/features/work_orders/presentation/work_orders_providers.dart';

void main() {
  test('WorkOrderSummary parses the summary payload', () {
    final summary = WorkOrderSummary.fromJson({
      'date': '2026-07-11',
      'scheduled_today': 4,
      'assigned': 2,
      'in_progress': 1,
      'completed_today': 3,
    });

    expect(summary.date, '2026-07-11');
    expect(summary.scheduledToday, 4);
    expect(summary.assigned, 2);
    expect(summary.inProgress, 1);
    expect(summary.completedToday, 3);
  });

  test('WorkOrderSummary defaults missing counters to zero', () {
    final summary = WorkOrderSummary.fromJson(const {});

    expect(summary.date, '');
    expect(summary.scheduledToday, 0);
    expect(summary.assigned, 0);
    expect(summary.inProgress, 0);
    expect(summary.completedToday, 0);
  });

  testWidgets('summary card shows the backend counters with their labels',
      (WidgetTester tester) async {
    const summary = WorkOrderSummary(
      date: '2026-07-11',
      scheduledToday: 4,
      assigned: 2,
      inProgress: 1,
      completedToday: 3,
    );

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          workOrdersProvider.overrideWith((ref) async => []),
          workOrderSummaryProvider.overrideWith((ref) async => summary),
        ],
        child: const MaterialApp(home: WorkOrdersPage()),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Bugünün Özeti'), findsOneWidget);

    // Her sayaç kendi etiketiyle birlikte görünmeli.
    for (final (value, label) in [
      ('4', 'Bugün Planlı'),
      ('2', 'Atanmış'),
      ('1', 'Devam Eden'),
      ('3', 'Bugün Biten'),
    ]) {
      expect(find.text(value), findsOneWidget);
      expect(find.text(label), findsOneWidget);
    }
  });
}
