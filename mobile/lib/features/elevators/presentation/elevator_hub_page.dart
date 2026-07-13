import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../inspections/data/inspection_repository.dart';
import '../../inspections/domain/inspection_summary.dart';
import '../../inspections/domain/scanned_elevator.dart';
import '../../inspections/presentation/inspection_form_page.dart';
import '../../work_orders/data/work_order_repository.dart';
import '../../work_orders/domain/work_order.dart';

final _elevatorInspectionsProvider = FutureProvider.autoDispose
    .family<List<InspectionSummary>, String>(
  (ref, elevatorUuid) =>
      ref.watch(inspectionRepositoryProvider).listForElevator(elevatorUuid),
);

final _elevatorWorkOrdersProvider =
    FutureProvider.autoDispose.family<List<WorkOrder>, String>(
  (ref, elevatorUuid) async {
    final page = await ref
        .watch(workOrderRepositoryProvider)
        .list(perPage: 50, elevatorUuid: elevatorUuid);

    return page.items;
  },
);

final _dateFormat = DateFormat('dd.MM.yyyy');

String _formatDate(DateTime? date) =>
    date == null ? '—' : _dateFormat.format(date);

/// QR ile çözülen asansörün saha merkezi: kimlik kartı + iki sekme
/// (periyodik kontroller, iş emirleri). Teknisyen tek QR okutmayla
/// hem etiket/kontrol işini hem günlük iş emrini buradan yürütür.
class ElevatorHubPage extends ConsumerStatefulWidget {
  const ElevatorHubPage({super.key, required this.elevator});

  final ScannedElevator elevator;

  @override
  ConsumerState<ElevatorHubPage> createState() => _ElevatorHubPageState();
}

class _ElevatorHubPageState extends ConsumerState<ElevatorHubPage>
    with SingleTickerProviderStateMixin {
  late final TabController _tabController;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this)
      // FAB yalnızca kontroller sekmesinde görünür.
      ..addListener(() => setState(() {}));
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  Future<void> _openInspectionForm() async {
    final messenger = ScaffoldMessenger.of(context);

    final saved = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => InspectionFormPage(elevator: widget.elevator),
      ),
    );

    if (saved == true) {
      messenger.showSnackBar(
        const SnackBar(content: Text('Periyodik kontrol kaydedildi.')),
      );
      ref.invalidate(_elevatorInspectionsProvider(widget.elevator.uuid));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(widget.elevator.displayName)),
      floatingActionButton: _tabController.index == 0
          ? FloatingActionButton.extended(
              onPressed: _openInspectionForm,
              icon: const Icon(Icons.add_task_outlined),
              label: const Text('Yeni Kontrol Gir'),
            )
          : null,
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(12, 12, 12, 0),
            child: _ElevatorHeaderCard(elevator: widget.elevator),
          ),
          TabBar(
            controller: _tabController,
            tabs: const [
              Tab(text: 'Periyodik Kontroller'),
              Tab(text: 'İş Emirleri'),
            ],
          ),
          Expanded(
            child: TabBarView(
              controller: _tabController,
              children: [
                _InspectionsTab(elevatorUuid: widget.elevator.uuid),
                _WorkOrdersTab(elevatorUuid: widget.elevator.uuid),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _ElevatorHeaderCard extends StatelessWidget {
  const _ElevatorHeaderCard({required this.elevator});

  final ScannedElevator elevator;

  @override
  Widget build(BuildContext context) {
    final labelMeta = elevator.currentLabel == null
        ? null
        : inspectionLabelMeta(elevator.currentLabel!);

    return Card(
      margin: EdgeInsets.zero,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const Icon(Icons.elevator_outlined),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    [
                      if (elevator.buildingName != null)
                        elevator.buildingName!,
                      'Seri: ${elevator.serialNumber}',
                    ].join(' — '),
                    style: Theme.of(context).textTheme.bodyMedium,
                  ),
                ),
                if (labelMeta != null)
                  Chip(
                    label: Text(labelMeta.name),
                    labelStyle: TextStyle(
                      color: labelMeta.color,
                      fontWeight: FontWeight.w600,
                    ),
                    backgroundColor: labelMeta.color.withValues(alpha: 0.12),
                    side: BorderSide.none,
                    visualDensity: VisualDensity.compact,
                  ),
              ],
            ),
            if (elevator.lastInspectionAt != null ||
                elevator.nextInspectionDue != null) ...[
              const Divider(height: 20),
              Row(
                children: [
                  Expanded(
                    child: _InfoRow(
                      icon: Icons.history,
                      label: 'Son kontrol',
                      value: elevator.lastInspectionAt ?? '—',
                    ),
                  ),
                  Expanded(
                    child: _InfoRow(
                      icon: Icons.event_outlined,
                      label: 'Sonraki',
                      value: elevator.nextInspectionDue ?? '—',
                    ),
                  ),
                ],
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({
    required this.icon,
    required this.label,
    required this.value,
  });

  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Icon(icon, size: 16, color: Theme.of(context).colorScheme.outline),
        const SizedBox(width: 6),
        Flexible(
          child: Text(
            '$label: $value',
            style: Theme.of(context)
                .textTheme
                .bodySmall
                ?.copyWith(fontWeight: FontWeight.w600),
            overflow: TextOverflow.ellipsis,
          ),
        ),
      ],
    );
  }
}

class _InspectionsTab extends ConsumerWidget {
  const _InspectionsTab({required this.elevatorUuid});

  final String elevatorUuid;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final inspections = ref.watch(_elevatorInspectionsProvider(elevatorUuid));

    return _TabList(
      state: inspections,
      onRefresh: () =>
          ref.refresh(_elevatorInspectionsProvider(elevatorUuid).future),
      onRetry: () =>
          ref.invalidate(_elevatorInspectionsProvider(elevatorUuid)),
      emptyText: 'Bu asansör için kayıtlı periyodik kontrol yok.',
      itemBuilder: (inspection) => _InspectionCard(inspection: inspection),
    );
  }
}

class _WorkOrdersTab extends ConsumerWidget {
  const _WorkOrdersTab({required this.elevatorUuid});

  final String elevatorUuid;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final workOrders = ref.watch(_elevatorWorkOrdersProvider(elevatorUuid));

    return _TabList(
      state: workOrders,
      onRefresh: () =>
          ref.refresh(_elevatorWorkOrdersProvider(elevatorUuid).future),
      onRetry: () => ref.invalidate(_elevatorWorkOrdersProvider(elevatorUuid)),
      emptyText: 'Bu asansör için iş emri yok.',
      itemBuilder: (workOrder) => _HubWorkOrderCard(
        workOrder: workOrder,
        onReturned: () =>
            ref.invalidate(_elevatorWorkOrdersProvider(elevatorUuid)),
      ),
    );
  }
}

/// İki sekmenin ortak iskeleti: yüklenme/hata/boş durumları ve
/// pull-to-refresh davranışı tek yerde.
class _TabList<T> extends StatelessWidget {
  const _TabList({
    required this.state,
    required this.onRefresh,
    required this.onRetry,
    required this.emptyText,
    required this.itemBuilder,
  });

  final AsyncValue<List<T>> state;
  final Future<List<T>> Function() onRefresh;
  final VoidCallback onRetry;
  final String emptyText;
  final Widget Function(T item) itemBuilder;

  @override
  Widget build(BuildContext context) {
    return RefreshIndicator(
      onRefresh: onRefresh,
      child: state.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => ListView(
          padding: const EdgeInsets.all(24),
          children: [
            Text(error.toString(), textAlign: TextAlign.center),
            const SizedBox(height: 12),
            Center(
              child: FilledButton.tonal(
                onPressed: onRetry,
                child: const Text('Tekrar Dene'),
              ),
            ),
          ],
        ),
        data: (items) => items.isEmpty
            ? ListView(
                children: [
                  const SizedBox(height: 96),
                  Center(child: Text(emptyText)),
                ],
              )
            : ListView.separated(
                padding: const EdgeInsets.fromLTRB(12, 12, 12, 96),
                itemCount: items.length,
                separatorBuilder: (_, __) => const SizedBox(height: 8),
                itemBuilder: (context, index) => itemBuilder(items[index]),
              ),
      ),
    );
  }
}

class _HubWorkOrderCard extends StatelessWidget {
  const _HubWorkOrderCard({required this.workOrder, required this.onReturned});

  final WorkOrder workOrder;

  /// Detaydan dönüşte liste tazelenir (durum değişmiş olabilir).
  final VoidCallback onReturned;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: ListTile(
        onTap: () async {
          await context.push('/work-orders/${workOrder.uuid}');
          onReturned();
        },
        leading: CircleAvatar(
          backgroundColor: workOrder.statusColor.withValues(alpha: 0.15),
          child: Icon(Icons.build_outlined, color: workOrder.statusColor),
        ),
        title: Text(
          '${workOrder.workOrderNumber} · ${workOrder.typeLabel}',
          style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14),
        ),
        subtitle: Padding(
          padding: const EdgeInsets.only(top: 4),
          child: Row(
            children: [
              _LabelChip(
                text: workOrder.statusLabel,
                color: workOrder.statusColor,
              ),
              const SizedBox(width: 8),
              if (workOrder.scheduledAt != null)
                Text(
                  _formatDate(workOrder.scheduledAt),
                  style: Theme.of(context).textTheme.bodySmall,
                ),
            ],
          ),
        ),
        trailing: const Icon(Icons.chevron_right),
      ),
    );
  }
}

class _InspectionCard extends StatelessWidget {
  const _InspectionCard({required this.inspection});

  final InspectionSummary inspection;

  @override
  Widget build(BuildContext context) {
    final subtitleParts = [
      if (inspection.inspectionBody != null) inspection.inspectionBody!,
      if (inspection.reportNumber != null)
        'Rapor: ${inspection.reportNumber}',
      if (inspection.workOrderNumber != null)
        'İş emri: ${inspection.workOrderNumber}',
    ];

    return Card(
      child: ExpansionTile(
        shape: const Border(),
        leading: CircleAvatar(
          backgroundColor: inspection.labelColor.withValues(alpha: 0.15),
          child: Icon(Icons.verified_outlined, color: inspection.labelColor),
        ),
        title: Text(
          '${_formatDate(inspection.inspectedAt)} · ${inspection.typeLabel}',
          style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14),
        ),
        subtitle: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (subtitleParts.isNotEmpty)
              Text(
                subtitleParts.join(' — '),
                style: Theme.of(context).textTheme.bodySmall,
              ),
            const SizedBox(height: 4),
            Wrap(
              spacing: 8,
              runSpacing: 4,
              crossAxisAlignment: WrapCrossAlignment.center,
              children: [
                _LabelChip(
                  text: '${inspection.labelName} Etiket',
                  color: inspection.labelColor,
                ),
                if (inspection.findings.isNotEmpty)
                  Text(
                    inspection.unresolvedFindingCount > 0
                        ? '${inspection.findings.length} kusur '
                            '(${inspection.unresolvedFindingCount} açık)'
                        : '${inspection.findings.length} kusur (kapandı)',
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
              ],
            ),
          ],
        ),
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (inspection.followUpDueDate != null)
                  Text(
                    'Takip tarihi: ${_formatDate(inspection.followUpDueDate)}',
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                if (inspection.nextInspectionDate != null)
                  Text(
                    'Sonraki kontrol: '
                    '${_formatDate(inspection.nextInspectionDate)}',
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                for (final finding in inspection.findings)
                  Padding(
                    padding: const EdgeInsets.only(top: 6),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Icon(
                          finding.isResolved
                              ? Icons.check_circle_outline
                              : Icons.error_outline,
                          size: 16,
                          color: finding.isResolved
                              ? Colors.green
                              : Colors.orange,
                        ),
                        const SizedBox(width: 6),
                        Expanded(child: Text(finding.description)),
                      ],
                    ),
                  ),
                if (inspection.notes != null &&
                    inspection.notes!.trim().isNotEmpty)
                  Padding(
                    padding: const EdgeInsets.only(top: 6),
                    child: Text(
                      inspection.notes!,
                      style: Theme.of(context).textTheme.bodySmall,
                    ),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _LabelChip extends StatelessWidget {
  const _LabelChip({required this.text, required this.color});

  final String text;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        text,
        style: TextStyle(
          color: color,
          fontSize: 12,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }
}
