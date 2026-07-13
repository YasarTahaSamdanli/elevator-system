import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/network/paginated.dart';
import '../domain/work_order.dart';
import '../domain/work_order_summary.dart';

final workOrderRepositoryProvider = Provider<WorkOrderRepository>(
  (ref) => WorkOrderRepository(ref.watch(apiClientProvider)),
);

class WorkOrderRepository {
  WorkOrderRepository(this._api);

  final ApiClient _api;

  Future<Paginated<WorkOrder>> list({
    int page = 1,
    int perPage = 25,
    String? status,
    String? elevatorUuid,
  }) async {
    final envelope = await _api.get(
      '/work-orders',
      query: {
        'page': page,
        'per_page': perPage,
        'sort': '-scheduled_at',
        if (status != null) 'filter[status]': status,
        if (elevatorUuid != null) 'filter[elevator_uuid]': elevatorUuid,
      },
    );

    return Paginated.fromEnvelope(envelope, WorkOrder.fromJson);
  }

  /// Ana ekran sayaçları. "Bugün" cihazın gününe göre hesaplansın diye
  /// yerel tarih backend'e gönderilir.
  Future<WorkOrderSummary> summary({DateTime? date}) async {
    final target = date ?? DateTime.now();
    final formatted = '${target.year.toString().padLeft(4, '0')}-'
        '${target.month.toString().padLeft(2, '0')}-'
        '${target.day.toString().padLeft(2, '0')}';

    final envelope = await _api.get(
      '/work-orders/summary',
      query: {'date': formatted},
    );

    return WorkOrderSummary.fromJson(envelope['data'] as Map<String, dynamic>);
  }

  Future<WorkOrder> find(String uuid) async {
    final envelope = await _api.get('/work-orders/$uuid');

    return WorkOrder.fromJson(envelope['data'] as Map<String, dynamic>);
  }

  Future<List<AvailableMaterial>> listMaterials() async {
    final envelope = await _api.get(
      '/materials',
      query: {
        'per_page': 100,
        'sort': 'name',
        'filter[is_active]': true,
      },
    );
    final page = Paginated.fromEnvelope(envelope, AvailableMaterial.fromJson);

    return page.items;
  }

  /// [qrIdentifier]: işe başlarken okutulan asansör QR'ı — backend
  /// teknisyen için `in_progress` geçişinde eşleşme doğrular.
  Future<WorkOrder> updateStatus(
    String uuid,
    String status, {
    String? qrIdentifier,
  }) async {
    final envelope = await _api.patch(
      '/work-orders/$uuid',
      body: {
        'status': status,
        if (qrIdentifier != null) 'qr_identifier': qrIdentifier,
      },
    );

    return WorkOrder.fromJson(envelope['data'] as Map<String, dynamic>);
  }

  Future<WorkOrderChecklistItem> updateChecklistItem(
    String workOrderUuid,
    String itemUuid, {
    bool? isDone,
    String? note,
    bool includeNote = false,
  }) async {
    final envelope = await _api.patch(
      '/work-orders/$workOrderUuid/checklist-items/$itemUuid',
      body: {
        if (isDone != null) 'is_done': isDone,
        if (includeNote) 'note': note,
      },
    );

    return WorkOrderChecklistItem.fromJson(
      envelope['data'] as Map<String, dynamic>,
    );
  }

  Future<WorkOrderMaterialItem> createItem(
    String workOrderUuid, {
    required String materialUuid,
    required num quantity,
    num? unitPrice,
    String? note,
  }) async {
    final envelope = await _api.post(
      '/work-orders/$workOrderUuid/items',
      body: {
        'material_uuid': materialUuid,
        'quantity': quantity,
        if (unitPrice != null) 'unit_price': unitPrice,
        if (note != null && note.isNotEmpty) 'note': note,
      },
    );

    return WorkOrderMaterialItem.fromJson(
      envelope['data'] as Map<String, dynamic>,
    );
  }

  Future<void> deleteItem(String workOrderUuid, String itemUuid) async {
    await _api.delete('/work-orders/$workOrderUuid/items/$itemUuid');
  }
}
