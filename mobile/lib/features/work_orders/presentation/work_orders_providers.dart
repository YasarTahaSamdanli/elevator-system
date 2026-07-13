import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../data/work_order_repository.dart';
import '../domain/work_order.dart';
import '../domain/work_order_summary.dart';

/// İlk sayfa iş emri listesi. Sonsuz kaydırma/sayfalama sonraki fazda —
/// saha kullanımında ilk 25 kayıt günlük görev listesini karşılıyor.
final workOrdersProvider =
    FutureProvider.autoDispose<List<WorkOrder>>((ref) async {
  final page = await ref.watch(workOrderRepositoryProvider).list();

  return page.items;
});

/// Ana ekran sayaçları — liste sayfalamasından bağımsız, backend'de
/// veritabanından sayılır.
final workOrderSummaryProvider =
    FutureProvider.autoDispose<WorkOrderSummary>(
  (ref) => ref.watch(workOrderRepositoryProvider).summary(),
);

final workOrderDetailProvider = FutureProvider.autoDispose
    .family<WorkOrder, String>(
  (ref, uuid) => ref.watch(workOrderRepositoryProvider).find(uuid),
);
